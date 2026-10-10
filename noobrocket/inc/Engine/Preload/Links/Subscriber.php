<?php

namespace NoobRocket\Engine\Preload\Links;

use WP_Filesystem_Direct;
use NoobRocket\Admin\Options_Data;
use NoobRocket\Event_Management\Subscriber_Interface;

class Subscriber implements Subscriber_Interface {

	/**
	 * Options Data instance
	 *
	 * @var Options_Data
	 */
	private $options;

	/**
	 * WP_Filesystem_Direct instance.
	 *
	 * @var WP_Filesystem_Direct
	 */
	private $filesystem;

	/**
	 * Script enqueued status.
	 *
	 * @var bool
	 */
	private $is_enqueued = false;

	/** Filtered configuration used by both the handler and initial document rule. */
	private $configuration = [];

	/** Read core exclusions before replacing its broader document rule. */
	private $reading_core_rules = false;

	/**
	 * Instantiate the class.
	 *
	 * @param Options_Data         $options    Options Data instance.
	 * @param WP_Filesystem_Direct $filesystem The Filesystem object.
	 */
	public function __construct( Options_Data $options, $filesystem ) {
		$this->options    = $options;
		$this->filesystem = $filesystem;
	}

	/**
	 * Return an array of events that this subscriber wants to listen to.
	 *
	 * @return array
	 */
	public static function get_subscribed_events() {
		return [
			'wp_enqueue_scripts' => 'add_preload_script',
			'wp_footer' => [ 'print_document_rules', 19 ],
			'wp_speculation_rules_configuration' => 'coordinate_core_speculation',
		];
	}

	/** The intent handler owns eligibility; avoid a second, broader core rule. */
	public function coordinate_core_speculation( $configuration ) {
		if ( $this->reading_core_rules ) {
			if ( is_array( $configuration ) ) {
				$configuration['mode'] = 'prerender';
			}
			return $configuration;
		}
		return $this->prerender_enabled() && $this->can_prepare_links() ? null : $configuration;
	}

	private function prerender_enabled(): bool {
		return in_array( $this->options->get( 'prerender_links', 0 ), [ true, 1, '1' ], true );
	}

	private function can_prepare_links(): bool {
		return ! is_user_logged_in() && ! is_admin() && ! is_feed() && ! is_embed()
			&& ! is_preview() && ! is_search() && ! is_404()
			&& ! ( is_singular() && post_password_required() )
			&& ! noobrocket_bypass()
			&& ! ( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE )
			&& ! ( defined( 'REST_REQUEST' ) && REST_REQUEST )
			&& ! ( defined( 'DOING_AJAX' ) && DOING_AJAX );
	}

	/**
	 * Adds the inline script to the footer when the option is enabled
	 *
	 * @since 3.7
	 *
	 * @return void
	 */
	public function add_preload_script() {

		if ( ! $this->can_prepare_links() ) {
			return;
		}

		if ( $this->is_enqueued ) {
			return;
		}

		if ( ! (bool) $this->options->get( 'preload_links', 0 ) && ! $this->prerender_enabled() ) {
			return;
		}

		$js_assets_path = noobrocket_get_constant( 'NOOBROCKET_PATH' ) . 'assets/js/';
		$this->configuration = $this->get_preload_links_config();

		if ( ! wp_script_is( 'noobrocket-browser-checker' ) ) {
			$checker_filename = noobrocket_get_constant( 'SCRIPT_DEBUG' ) ? 'browser-checker.js' : 'browser-checker.min.js';

			// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.NoExplicitVersion
			wp_register_script(
				'noobrocket-browser-checker',
				'',
				[],
				noobrocket_get_constant( NOOBROCKET_VERSION, '' ),
				true
			);
			wp_enqueue_script( 'noobrocket-browser-checker' );
			wp_add_inline_script(
				'noobrocket-browser-checker',
				$this->filesystem->get_contents( "{$js_assets_path}{$checker_filename}" )
			);
		}

		$preload_filename = noobrocket_get_constant( 'SCRIPT_DEBUG' ) ? 'preload-links.js' : 'preload-links.min.js';

		// Register handle with no src to add the inline script after.
		// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.NoExplicitVersion
		wp_register_script(
			'noobrocket-preload-links',
			'',
			[
				'noobrocket-browser-checker',
			],
			noobrocket_get_constant( NOOBROCKET_VERSION, '' ),
			true
		);
		wp_enqueue_script( 'noobrocket-preload-links' );
		wp_add_inline_script(
			'noobrocket-preload-links',
			$this->filesystem->get_contents( "{$js_assets_path}{$preload_filename}" )
		);
		wp_add_inline_script(
			'noobrocket-preload-links',
			'var NoobRocketPreloadLinksConfig = ' . wp_json_encode( $this->configuration ) . ';',
			'before'
		);

		$this->is_enqueued = true;
	}

	/** Emit source-visible document rules that remain inert until browser eligibility is validated. */
	public function print_document_rules() {
		if ( ! $this->is_enqueued || ! $this->can_prepare_links()
			|| true !== ( $this->configuration['prerender'] ?? false )
			|| 'document' !== ( $this->configuration['mode'] ?? 'intent' ) ) {
			return;
		}
		$rules = [ 'prerender' => [ [
			'source' => 'document',
			'where' => [ 'and' => [ [ 'selector_matches' => 'a' ], [ 'not' => [ 'selector_matches' => 'a' ] ] ] ],
			'eagerness' => $this->configuration['eagerness'],
		] ] ];
		echo wp_get_inline_script_tag( wp_json_encode( $rules, JSON_HEX_TAG ), [ 'id' => 'noobrocket-document-speculation-rules', 'type' => 'speculationrules' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core script helper escapes attributes; JSON has no executable markup.
	}

	/**
	 * Gets the Preload Links script configuration parameters.
	 *
	 * @since 3.7
	 *
	 * @return array Preload Links script configuration parameters.
	 */
	private function get_preload_links_config() {
		$use_trailing_slash = $this->use_trailing_slash();
		$images_ext         = 'jpg|jpeg|gif|png|tiff|bmp|webp|avif|pdf|doc|docx|xls|xlsx|php';
		$prerender          = $this->prerender_enabled();
		$native_exclusions  = $prerender ? $this->get_native_exclusions() : null;
		if ( $prerender && function_exists( 'wp_get_speculation_rules' ) && null === $native_exclusions ) {
			$prerender = false;
		}

		$config = [
			'prefetch'          => (bool) $this->options->get( 'preload_links', 0 ),
			'prerender'         => $prerender,
			'mode'             => $this->options->get( 'prerender_links_mode', 'intent' ),
			'eagerness'        => $this->options->get( 'prerender_links_eagerness', 'conservative' ),
			'prerenderExcludeUris' => $this->get_prerender_exclusions(),
			'nativeExcludePaths' => $native_exclusions['paths'] ?? [],
			'nativeExcludeSelectors' => $native_exclusions['selectors'] ?? [],
			'excludeUris'       => $this->get_uris_to_exclude( $use_trailing_slash ),
			'usesTrailingSlash' => $use_trailing_slash,
			'imageExt'          => $images_ext,
			'fileExt'           => $images_ext . '|html|htm',
			'siteUrl'           => home_url(),
			'onHoverDelay'      => 100, // milliseconds. -1 disables the "on hover" feature.
			'rateThrottle'      => 3, // on hover: limits the number of links preloaded per second.
		];

		/**
		 * Preload Links script configuration parameters.
		 *
		 * This array of parameters are passed as NoobRocketPreloadLinksConfig object and used by the
		 * `preload-links.min.js` script to configure the behavior of the Preload Links feature.
		 *
		 * @since 3.7
		 *
		 * @param array $config Preload Links script configuration parameters.
		 */
		$filtered_config = wpm_apply_filters_typed( 'array', 'noobrocket_preload_links_config', $config );

		$config = array_merge( $config, $filtered_config );
		$config['mode'] = in_array( $config['mode'], [ 'intent', 'document' ], true ) ? $config['mode'] : 'intent';
		$config['eagerness'] = in_array( $config['eagerness'], [ 'conservative', 'moderate', 'eager' ], true ) ? $config['eagerness'] : 'conservative';
		return $config;
	}

	/** Preserve core's path prefixes and third-party exclusion hooks without converting URLPattern to regex. */
	private function get_native_exclusions(): ?array {
		if ( ! function_exists( 'wp_get_speculation_rules' ) ) {
			return null;
		}
		$this->reading_core_rules = true;
		try {
			$rules = wp_get_speculation_rules();
		} finally {
			$this->reading_core_rules = false;
		}
		if ( null === $rules ) {
			return null;
		}
		$data = $rules->jsonSerialize();
		if ( ! isset( $data['prerender'][0]['where']['and'] ) ) {
			return null;
		}
		$excluded = [ 'paths' => [], 'selectors' => [] ];
		foreach ( $data['prerender'][0]['where']['and'] as $condition ) {
			if ( isset( $condition['not']['href_matches'] ) ) {
				$excluded['paths'] = array_merge( $excluded['paths'], (array) $condition['not']['href_matches'] );
			}
			if ( isset( $condition['not']['selector_matches'] ) ) {
				$excluded['selectors'][] = $condition['not']['selector_matches'];
			}
		}
		return $excluded;
	}

	/** Keep existing regex exclusions rather than translating them to URLPattern. */
	private function get_prerender_exclusions(): string {
		global $wp_rewrite;
		$excluded = [
			'/(?:login|logout|signout|register|cart|checkout|account|my-account|add-to-cart)(?:/|$)',
			'/(?:wp-admin|wp-json)(?:/|$)',
			'/(?:wp-[^/]+|xmlrpc)\\.php(?:/|$)',
		];
		if ( ! empty( $wp_rewrite->search_base ) ) {
			$excluded[] = '/' . preg_quote( $wp_rewrite->search_base, '/' ) . '(?:/|$)';
		}
		foreach ( [ 'prerender_excluded_uri', 'preload_excluded_uri' ] as $option ) {
			$patterns = $this->options->get( $option, [] );
			if ( is_array( $patterns ) ) {
				$excluded = array_merge( $excluded, array_filter( $patterns, 'is_string' ) );
			}
		}
		return implode( '|', array_filter( $excluded ) );
	}

	/**
	 * Gets the URIs to exclude.
	 *
	 * @since 3.7
	 *
	 * @param bool $use_trailing_slash When true, uses trailing slash.
	 *
	 * @return string
	 */
	private function get_uris_to_exclude( $use_trailing_slash ) {
		$site_url = site_url();
		$uris     = get_noobrocket_cache_reject_uri( false, false );
		$uris     = str_replace( [ '/(.*)|', '/(.*)/|' ], '/|', $uris );

		$default = [
			'/refer/',
			'/go/',
			'/recommend/',
			'/recommends/',
		];

		$excluded = $default;

		/**
		 * Filters the patterns excluded from links preload
		 *
		 * @since 3.10.8
		 *
		 * @param string[] $excluded Array of excluded patterns.
		 * @param string[] $default  Array of default excluded patterns.
		 */
		$excluded = wpm_apply_filters_typed( 'array', 'noobrocket_preload_links_exclusions', $excluded, $default );

		$excluded = array_filter( $excluded );

		$login_url = wp_login_url(); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$login_uri = str_replace( home_url(), '', $login_url );

		$excluded = array_filter(
			$excluded,
			function ( $uri ) use ( $login_uri ) {
				return ! str_contains( $login_uri, $uri );
			}
		);

		$excluded_patterns = '';

		if ( ! empty( $excluded ) ) {
			$excluded_patterns = '|' . implode( '|', $excluded );
		}

		return $uris . $excluded_patterns;
	}

	/**
	 * Checks if the given URL has a trailing slash.
	 *
	 * @since 3.7
	 *
	 * @param string $url URL to check.
	 *
	 * @return bool
	 */
	private function has_trailing_slash( $url ) {
		return substr( $url, -1 ) === '/';
	}

	/**
	 * Indicates if the site uses a trailing slash in the permalink structure.
	 *
	 * @since 3.7
	 *
	 * @return bool when true, uses `/`; else, no.
	 */
	private function use_trailing_slash() {
		return $this->has_trailing_slash( get_permalink() );
	}
}
