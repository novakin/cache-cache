<?php
namespace NoobRocket\ThirdParty\Plugins\Optimization;

use NoobRocket\Admin\Options_Data;
use NoobRocket\Engine\CDN\Subscriber;
use NoobRocket\Event_Management\Subscriber_Interface;

/**
 * Subscriber for compatibility with AMP
 *
 * @since  3.5.2
 */
class AMP implements Subscriber_Interface {
	const QUERY       = 'amp';
	const AMP_OPTIONS = 'amp-options';

	/**
	 * NoobRocket CDN Subscriber.
	 *
	 * @var Subscriber_Interface
	 */
	private $cdn_subscriber;

	/**
	 * Constructor
	 *
	 * @param Subscriber_Interface $cdn_subscriber NoobRocket CDN Subscriber.
	 */
	public function __construct( Subscriber_Interface $cdn_subscriber ) {
		$this->cdn_subscriber = $cdn_subscriber;
	}

	/**
	 * Subscribed events for AMP.
	 *
	 * @since  3.5.2
	 */
	public static function get_subscribed_events() {
		$events = [
			'activate_amp/amp.php'       => 'generate_config_file',
			'deactivate_amp/amp.php'     => 'generate_config_file',
			'wp'                         => [ 'disable_options_on_amp', 20 ],
			'noobrocket_cache_query_strings' => 'is_amp_compatible_callback',
		];

		if ( function_exists( 'is_amp_endpoint' ) ) {
			$events['update_option_amp-options']  = 'generate_config_file';
			$events['noobrocket_delay_js_exclusions'] = 'exclude_script_from_delay_js';
		}

		return $events;
	}

	/**
	 * Regenerate config file on plugin activation / deactivation.
	 *
	 * @since  3.5.2
	 */
	public function generate_config_file() {
		noobrocket_generate_config_file();
	}

	/**
	 * Add compatibility with AMP query string by adding it as a cached query string.
	 *
	 * @since  3.5.2
	 *
	 * @param  array $value NoobRocket cache_query_strings value.
	 * @return array
	 */
	public function is_amp_compatible_callback( $value ) {
		if ( ! function_exists( 'is_amp_endpoint' ) ) {
			return $value;
		}

		$options       = get_option( self::AMP_OPTIONS, [] );
		$query_strings = array_diff( $value, [ static::QUERY ] );

		if ( empty( $options['theme_support'] ) ) {
			return $query_strings;
		}

		if ( in_array( $options['theme_support'], [ 'transitional', 'reader' ], true ) ) {
			$query_strings[] = static::QUERY;
		}

		return $query_strings;
	}

	/**
	 * Removes Minification, DNS Prefetch, LazyLoad, Defer JS when on an AMP document.
	 *
	 * This covers AMP documents as output by the official AMP plugin for WordPress
	 * (https://amp-wp.org/) as well as Web Stories for WordPress (https://wp.stories.google/),
	 * which both support the `is_amp_endpoint` function checks.
	 *
	 * However, in the case of Web Stories, `is_amp_endpoint` is only defined on
	 * the `wp` action, not earlier. Hence doing the `function_exists` check at this stage
	 * instead of in the `get_subscribed_events()` method.
	 *
	 * @since  3.5.2
	 */
	public function disable_options_on_amp() {
		// No endpoint function means we're not running amp here.
		if ( ! function_exists( 'is_amp_endpoint' ) ) {
			return;
		}

		// We can get a false negative from is_amp_endpoint when web stories is active, so we have to make sure neither is in play.
		if (
			! is_amp_endpoint()
			&&
			! ( is_singular( 'web-story' ) && ! is_embed() && ! post_password_required() )
		) {
			return;
		}

		global $wp_filter;

		remove_filter( 'wp_resource_hints', 'noobrocket_dns_prefetch', 10 );
		add_filter( 'do_noobrocket_lazyload', '__return_false' );
		add_filter( 'do_noobrocket_lazyload_iframes', '__return_false' );
		add_filter( 'pre_get_noobrocket_option_async_css', '__return_false' );
		add_filter( 'pre_get_noobrocket_option_delay_js', '__return_false' );
		add_filter( 'pre_get_noobrocket_option_preload_links', '__return_false' );
		add_filter( 'pre_get_noobrocket_option_minify_js', '__return_false' );
		add_filter( 'pre_get_noobrocket_option_minify_google_fonts', '__return_false' );
		add_filter( 'pre_get_noobrocket_option_lazyload_css_bg_img', '__return_false' );
		add_filter( 'pre_get_cloudflare_protocol_rewrite', '__return_false' );
		add_filter( 'do_noobrocket_protocol_rewrite', '__return_false' );

		unset( $wp_filter['noobrocket_buffer'] );

		$options = get_option( self::AMP_OPTIONS, [] );

		if ( ! empty( $options['theme_support'] )
			&&
			in_array( $options['theme_support'], [ 'transitional', 'reader' ], true ) ) {
			add_filter( 'noobrocket_cdn_reject_files', [ $this, 'reject_files' ], PHP_INT_MAX );
			add_filter( 'noobrocket_buffer', [ $this->cdn_subscriber, 'rewrite' ] );
			add_filter( 'noobrocket_buffer', [ $this->cdn_subscriber, 'rewrite_srcset' ] );
		}
	}

	/**
	 * Adds all CSS and JS files to the list of excluded CDN files.
	 *
	 * @since 3.5.5
	 *
	 * @param  array $files List of excluded files.
	 * @return array        List of excluded files.
	 */
	public function reject_files( $files ) {
		return array_merge(
			$files,
			[
				'(.*).css',
				'(.*).js',
			]
		);
	}

	/**
	 * Adds the switching script from AMP to delay JS excluded files
	 *
	 * @since 3.11.1
	 *
	 * @param  array $excluded List of excluded files.
	 * @return array        List of excluded files.
	 */
	public function exclude_script_from_delay_js( $excluded ) {
		$excluded[] = 'amp-mobile-version-switcher';
		return $excluded;
	}
}
