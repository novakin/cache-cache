<?php
namespace NoobRocket\Engine\Admin\Settings;

use NoobRocket\Admin\Options_Data;
use NoobRocket\Addon\Sucuri\Subscriber as SucuriSubscriber;
use WP_Error;

/**
 * Settings class.
 *
 * @since 3.5.5 Moves into the new architecture.
 */
class Settings {
	/**
	 * Options that accept boolean values (0 or 1).
	 */
	private const BOOLEAN_OPTIONS = [
		// Cache settings.
		'cache_webp',
		'cache_ssl',
		'cache_mobile',
		'do_caching_mobile_files',
		'cache_stale_refresh',
		'remove_dashicons',
		'remove_embed_assets',
		'remove_block_library',
		'cache_logged_user',
		// File optimization - CSS.
		'minify_css',
		'minify_google_fonts',
		'async_css',
		'async_css_mobile',
		'remove_unused_css',
		'optimize_css_delivery',
		// File optimization - JS.
		'minify_js',
		'minify_concatenate_js',
		'defer_all_js',
		'delay_js',
		'delay_js_execution_safe_mode',
		// Media.
		'image_delivery',
		'lazyload',
		'lazyload_iframes',
		'lazyload_youtube',
		'lazyload_css_bg_img',
		'image_dimensions',
		// Fonts.
		'host_fonts_locally',
		'auto_preload_fonts',
		// Preload.
		'manual_preload',
		'preload_links',
		'prerender_links',
		// Database.
		'database_revisions',
		'database_auto_drafts',
		'database_trashed_posts',
		'database_spam_comments',
		'database_trashed_comments',
		'database_all_transients',
		'database_optimize_tables',
		'schedule_automatic_cleanup',
		// CDN.
		'cdn',
		// Cloudflare.
		'do_cloudflare',
		'cloudflare_devmode',
		'cloudflare_protocol_rewrite',
		'cloudflare_auto_settings',
		// Heartbeat.
		'control_heartbeat',
		// Performance monitoring.
		'performance_monitoring',
		// Add-ons.
		'varnish_auto_purge',
		'sucury_waf_cache_sync',
		// Analytics.
		'analytics_enabled',
		// Misc.
		'emoji',
	];


	/**
	 * Options with predefined allowed values (enums).
	 */
	private const ENUM_OPTIONS = [
		'local_css_mode'              => [ 'off', 'critical', 'used' ],
		'purge_cron_unit'             => [ 'HOUR_IN_SECONDS', 'DAY_IN_SECONDS' ],
		'automatic_cleanup_frequency' => [ 'daily', 'weekly', 'monthly' ],
		'heartbeat_site_behavior'     => [ '', 'reduce_periodicity', 'disable' ],
		'heartbeat_admin_behavior'    => [ '', 'reduce_periodicity', 'disable' ],
		'heartbeat_editor_behavior'   => [ '', 'reduce_periodicity', 'disable' ],
	];


	/**
	 * Options_Data instance.
	 *
	 * @since 3.0
	 *
	 * @var Options_Data
	 */
	private $options;

	/**
	 * Array of settings to build the settings page.
	 *
	 * @since 3.0
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Hidden settings on the settings page.
	 *
	 * @since 3.0
	 *
	 * @var array
	 */
	private $hidden_settings;

	/**
	 * Constructor
	 *
	 * @since 3.0
	 *
	 * @param Options_Data $options Options_Data instance.
	 */
	public function __construct( Options_Data $options ) {
		$this->options = $options;
	}

	/**
	 * Whether a setting owns a boolean domain value.
	 *
	 * @param string $name Setting name.
	 * @return bool
	 */
	public static function is_boolean_option( string $name ): bool {
		return in_array( $name, self::BOOLEAN_OPTIONS, true );
	}

	/**
	 * Normalize supported boolean representations without truthiness coercion.
	 *
	 * @param mixed $value Submitted value.
	 * @return int|WP_Error
	 */
	public static function normalize_boolean( $value ) {
		if ( in_array( $value, [ true, 1, '1', 'true' ], true ) ) {
			return 1;
		}
		if ( in_array( $value, [ false, 0, '0', 'false' ], true ) ) {
			return 0;
		}
		return new WP_Error( 'invalid_boolean', __( 'Use true, false, 1 or 0 for a boolean setting.', 'noobrocket' ) );
	}

	/**
	 * Available domain choices, shared by native forms and single-option writes.
	 *
	 * @param string $name Setting name.
	 * @return array
	 */
	public static function get_allowed_values( string $name ): array {
		return self::ENUM_OPTIONS[ $name ] ?? [];
	}

	/**
	 * Validate an enum without quietly substituting a different requested choice.
	 *
	 * @param string $name Setting name.
	 * @param mixed $value Submitted value.
	 * @return string|WP_Error
	 */
	public static function normalize_enum( string $name, $value ) {
		$allowed = self::get_allowed_values( $name );
		if ( in_array( $value, $allowed, true ) ) {
			return $value;
		}
		return new WP_Error( 'invalid_setting_choice', sprintf( __( 'Choose an available value: %s.', 'noobrocket' ), implode( ', ', $allowed ) ) );
	}

	/**
	 * Cache lifespan supports a nonnegative whole interval; zero means unlimited.
	 *
	 * @param mixed $value Submitted value.
	 * @return int|WP_Error
	 */
	public static function normalize_lifespan_interval( $value ) {
		if ( is_int( $value ) || is_string( $value ) ) {
			$interval = filter_var( $value, FILTER_VALIDATE_INT, [ 'options' => [ 'min_range' => 0 ] ] );
			if ( false !== $interval ) {
				return $interval;
			}
		}
		return new WP_Error( 'invalid_cache_lifespan', __( 'Cache lifespan must be a nonnegative whole number. Use 0 for unlimited.', 'noobrocket' ) );
	}

	/**
	 * Adds a page section to the settings.
	 *
	 * A page section is a top-level block containing settings sections.
	 *
	 * @since 3.0
	 *
	 * @param string $id Page section identifier.
	 * @param array  $args {
	 *      Data to build the section.
	 *
	 *      @type string $title            Section title.
	 *      @type string $menu_description Description displayed in the navigation.
	 * }
	 * @return void
	 */
	public function add_page_section( $id, $args ) {
		$args['id'] = $id;

		$this->settings[ $id ] = $args;
	}

	/**
	 * Adds settings sections to the settings.
	 *
	 * A setting section is a block containing settings fields.
	 *
	 * @since 3.0
	 *
	 * @param array $settings_sections {
	 *      Data to build the section.
	 *
	 *      @type string $title       Section title.
	 *      @type string $type        Type of section.
	 *      @type string $page        Page section identifier it belongs to.
	 *      @type string $description Section description.
	 *      @type string $help        Helper IDs for beacon.
	 * }
	 * @return void
	 */
	public function add_settings_sections( $settings_sections ) {
		foreach ( $settings_sections as $id => $args ) {
			$args['id'] = $id;

			$this->settings[ $args['page'] ]['sections'][ $id ] = $args;
		}
	}

	/**
	 * Adds settings fields to the settings.
	 *
	 * @since 3.0
	 *
	 * @param array $settings_fields {
	 *      Data to build the section.
	 *
	 *      @type string $id    Identifier.
	 *      @type string $title Field title.
	 * }
	 * @return void
	 */
	public function add_settings_fields( $settings_fields ) {
		foreach ( $settings_fields as $id => $args ) {
			$args['id']    = $id;
			$args['value'] = $this->options->get( $id, $args['default'] );
			$page          = $args['page'];
			$section       = $args['section'];
			unset( $args['page'], $args['section'] );
			/**
			 * Filters the field  before add to the settings
			 *
			 * @since 3.10
			 *
			 * @param array    $input    Array of sanitized values after being submitted by the form.
			 */
			$args = apply_filters( 'noobrocket_before_add_field_to_settings', $args );
			$this->settings[ $page ]['sections'][ $section ]['fields'][ $id ] = $args;
		}
	}

	/**
	 * Adds hidden settings fields to the settings.
	 *
	 * @since 3.0
	 *
	 * @param array $hidden_settings_fields {
	 *      Data to build the section.
	 *
	 *      @type string $id    Identifier.
	 * }
	 * @return void
	 */
	public function add_hidden_settings_fields( $hidden_settings_fields ) {
		foreach ( $hidden_settings_fields as $id ) {
			$value = $this->options->get( $id );

			$this->hidden_settings[] = [
				'id'    => $id,
				'value' => $value,
			];
		}
	}

	/**
	 * Returns the plugin settings
	 *
	 * @since 3.0
	 *
	 * @return array
	 */
	public function get_settings() {
		return $this->settings;
	}

	/**
	 * Returns the plugin hidden settings
	 *
	 * @since 3.0
	 *
	 * @return array
	 */
	public function get_hidden_settings() {
		return $this->hidden_settings;
	}

	/**
	 * Sanitizes the submitted values.
	 *
	 * @since 3.0
	 *
	 * @param array $input Array of values submitted by the form.
	 * @return array
	 */
	public function sanitize_callback( $input ) {
		global $wp_settings_errors;

		foreach ( $input as $name => $value ) {
			if ( ! self::is_boolean_option( $name ) ) {
				continue;
			}
			$normalized = self::normalize_boolean( $value );
			$input[ $name ] = $normalized instanceof WP_Error ? $this->options->get( $name, 0 ) : $normalized;
			if ( $normalized instanceof WP_Error ) {
				add_settings_error( 'general', 'invalid_boolean_' . $name, $normalized->get_error_message(), 'error' );
			}
		}

		$input['cache_logged_user'] = ! empty( $input['cache_logged_user'] ) ? 1 : 0;

		$input['cache_ssl'] = ! empty( $input['cache_ssl'] ) ? 1 : 0;

		$input['cache_mobile']            = ! empty( $input['cache_mobile'] ) ? 1 : 0;
		$input['do_caching_mobile_files'] = ! empty( $input['do_caching_mobile_files'] ) ? 1 : 0;

		$input['minify_google_fonts'] = ! empty( $input['minify_google_fonts'] ) ? 1 : 0;

		// Option : Minification CSS & JS.
		$input['minify_css'] = ! empty( $input['minify_css'] ) ? 1 : 0;
		$input['minify_js']  = ! empty( $input['minify_js'] ) ? 1 : 0;

		$input['minify_concatenate_js'] = ! empty( $input['minify_concatenate_js'] ) ? 1 : 0;

		$input['defer_all_js']     = ! empty( $input['defer_all_js'] ) ? 1 : 0;
		$input['exclude_defer_js'] = ! empty( $input['exclude_defer_js'] ) ? noobrocket_sanitize_textarea_field( 'exclude_defer_js', $input['exclude_defer_js'] ) : [];

		$input['emoji'] = ! empty( $input['emoji'] ) ? 1 : 0;

		$input['image_delivery'] = in_array( $input['image_delivery'] ?? 0, [ true, 1, '1' ], true ) ? 1 : 0;
		if ( $input['image_delivery'] ) {
			$input['cache_webp'] = 0;
		}

		$input['lazyload']         = ! empty( $input['lazyload'] ) ? 1 : 0;
		$input['lazyload_iframes'] = ! empty( $input['lazyload_iframes'] ) ? 1 : 0;
		$input['lazyload_youtube'] = ! empty( $input['lazyload_youtube'] ) ? 1 : 0;

		// If iframes lazyload is not checked, uncheck youtube thumbnail option too.
		if ( 0 === $input['lazyload_iframes'] ) {
			$input['lazyload_youtube'] = 0;
		}

		// Option : Purge interval.
		$lifespan = self::normalize_lifespan_interval( $input['purge_cron_interval'] ?? $this->options->get( 'purge_cron_interval', 10 ) );
		$input['purge_cron_interval'] = $lifespan instanceof WP_Error ? $this->options->get( 'purge_cron_interval', 10 ) : $lifespan;
		if ( $lifespan instanceof WP_Error ) {
			add_settings_error( 'general', 'invalid_cache_lifespan', $lifespan->get_error_message(), 'error' );
		}
		$unit = self::normalize_enum( 'purge_cron_unit', $input['purge_cron_unit'] ?? $this->options->get( 'purge_cron_unit', 'HOUR_IN_SECONDS' ) );
		$input['purge_cron_unit'] = $unit instanceof WP_Error ? $this->options->get( 'purge_cron_unit', 'HOUR_IN_SECONDS' ) : $unit;

		// Option : Prefetch DNS requests.
		$input['dns_prefetch'] = $this->sanitize_dns_prefetch( $input );

		// Option : Empty the cache of the following pages when updating a post.
		if ( ! empty( $input['cache_purge_pages'] ) ) {
			$input['cache_purge_pages'] = noobrocket_sanitize_textarea_field( 'cache_purge_pages', $input['cache_purge_pages'] );
		} else {
			$input['cache_purge_pages'] = [];
		}

		// Option : Never cache the following pages.
		if ( ! empty( $input['cache_reject_uri'] ) ) {
			$input['cache_reject_uri'] = noobrocket_sanitize_textarea_field( 'cache_reject_uri', $input['cache_reject_uri'] );
			$input['cache_reject_uri'] = $this->check_global_exclusion( $input['cache_reject_uri'] );
		} else {
			$input['cache_reject_uri'] = [];
		}

		// Option : Don't cache pages that use the following cookies.
		if ( ! empty( $input['cache_reject_cookies'] ) ) {
			$input['cache_reject_cookies'] = noobrocket_sanitize_textarea_field( 'cache_reject_cookies', $input['cache_reject_cookies'] );
		} else {
			$input['cache_reject_cookies'] = [];
		}

		// Option : Cache pages that use the following query strings (GET parameters).
		if ( ! empty( $input['cache_query_strings'] ) ) {
			$input['cache_query_strings'] = noobrocket_sanitize_textarea_field( 'cache_query_strings', $input['cache_query_strings'] );
		} else {
			$input['cache_query_strings'] = [];
		}

		// Option : Never send cache pages for these user agents.
		if ( ! empty( $input['cache_reject_ua'] ) ) {
			$input['cache_reject_ua'] = noobrocket_sanitize_textarea_field( 'cache_reject_ua', $input['cache_reject_ua'] );
		} else {
			$input['cache_reject_ua'] = [];
		}

		// Option : CSS files to exclude from the minification.
		if ( ! empty( $input['exclude_css'] ) ) {
			$input['exclude_css'] = noobrocket_sanitize_textarea_field( 'exclude_css', $input['exclude_css'] );
		} else {
			$input['exclude_css'] = [];
		}

		// Local CSS generation is independent of the disabled cloud options.
		$css_mode = self::normalize_enum( 'local_css_mode', $input['local_css_mode'] ?? 'off' );
		$input['local_css_mode'] = $css_mode instanceof WP_Error ? 'off' : $css_mode;
		$input['local_css_safelist'] = noobrocket_sanitize_local_css_safelist( $input['local_css_safelist'] ?? [] );

		// Option : JS files to exclude from the minification.
		if ( ! empty( $input['exclude_js'] ) ) {
			$input['exclude_js'] = noobrocket_sanitize_textarea_field( 'exclude_js', $input['exclude_js'] );
		} else {
			$input['exclude_js'] = [];
		}

		// Option: inline JS patterns to exclude from combine JS.
		if ( ! empty( $input['exclude_inline_js'] ) ) {
			$input['exclude_inline_js'] = noobrocket_sanitize_textarea_field( 'exclude_inline_js', $input['exclude_inline_js'] );
		} else {
			$input['exclude_inline_js'] = [];
		}

		// Option: Async CSS.
		$input['async_css'] = ! empty( $input['async_css'] ) ? 1 : 0;

		// Option: Critical CSS.
		$input['critical_css'] = ! empty( $input['critical_css'] ) ? wp_strip_all_tags( str_replace( [ '<style>', '</style>' ], '', $input['critical_css'] ), [ "\'", '\"' ] ) : '';

		// Database options.
		$input['database_revisions']         = ! empty( $input['database_revisions'] ) ? 1 : 0;
		$input['database_auto_drafts']       = ! empty( $input['database_auto_drafts'] ) ? 1 : 0;
		$input['database_trashed_posts']     = ! empty( $input['database_trashed_posts'] ) ? 1 : 0;
		$input['database_spam_comments']     = ! empty( $input['database_spam_comments'] ) ? 1 : 0;
		$input['database_trashed_comments']  = ! empty( $input['database_trashed_comments'] ) ? 1 : 0;
		$input['database_all_transients']    = ! empty( $input['database_all_transients'] ) ? 1 : 0;
		$input['database_optimize_tables']   = ! empty( $input['database_optimize_tables'] ) ? 1 : 0;
		$input['schedule_automatic_cleanup'] = ! empty( $input['schedule_automatic_cleanup'] ) ? 1 : 0;

		$frequency = self::normalize_enum( 'automatic_cleanup_frequency', $input['automatic_cleanup_frequency'] ?? $this->options->get( 'automatic_cleanup_frequency' ) );
		$input['automatic_cleanup_frequency'] = $frequency instanceof WP_Error ? $this->options->get( 'automatic_cleanup_frequency' ) : $frequency;

		$allowed_frequencies = self::get_allowed_values( 'automatic_cleanup_frequency' );

		if ( 1 !== $input['schedule_automatic_cleanup'] && ! in_array( $input['automatic_cleanup_frequency'], $allowed_frequencies, true ) ) {
			$input['automatic_cleanup_frequency'] = $this->options->get( 'automatic_cleanup_frequency' );
		}

		// Options: Activate bot preload.
		$input['manual_preload'] = ! empty( $input['manual_preload'] ) ? 1 : 0;
		$input = noobrocket_sanitize_prerender_options( $input );

		// Options: Sucuri cache. And yeah, there's a typo, but now it's too late to fix ^^'.
		$input['sucury_waf_cache_sync'] = ! empty( $input['sucury_waf_cache_sync'] ) ? 1 : 0;

		if ( defined( 'NOOBROCKET_SUCURI_API_KEY' ) ) {
			$input['sucury_waf_api_key'] = NOOBROCKET_SUCURI_API_KEY;
		} else {
			$input['sucury_waf_api_key'] = isset( $input['sucury_waf_api_key'] ) ? sanitize_text_field( $input['sucury_waf_api_key'] ) : '';
		}

		$input['sucury_waf_api_key'] = trim( $input['sucury_waf_api_key'] );

		if ( ! SucuriSubscriber::is_api_key_valid( $input['sucury_waf_api_key'] ) ) {
			$input['sucury_waf_api_key'] = '';

			if ( $input['sucury_waf_cache_sync'] && empty( $input['ignore'] ) ) {
				add_settings_error( 'general', 'sucuri_waf_api_key_invalid', __( 'Sucuri Add-on: The API key for the Sucuri firewall must be in format <code>{32 characters}/{32 characters}</code>.', 'noobrocket' ), 'error' );
			}
		}

		// Options : Heartbeat.
		$input['control_heartbeat']         = ! empty( $input['control_heartbeat'] ) ? 1 : 0;
		foreach ( [ 'heartbeat_site_behavior', 'heartbeat_admin_behavior', 'heartbeat_editor_behavior' ] as $name ) {
			$behavior = self::normalize_enum( $name, $input[ $name ] ?? '' );
			$input[ $name ] = $behavior instanceof WP_Error ? '' : $behavior;
		}

		$input['cdn'] = ! empty( $input['cdn'] ) ? 1 : 0;

		// Option : CDN Cnames.
		$rejected_cnames = [];
		$rejected_scopes = [];
		$cdn_rows = self::normalize_cdn_rows( $input['cdn_cnames'] ?? [], $input['cdn_zone'] ?? [], $rejected_cnames, $rejected_scopes );
		$input = array_merge( $input, $cdn_rows );

		if ( ! empty( $input['cdn_reject_pages'] ) ) {
			$input['cdn_reject_pages'] = noobrocket_sanitize_textarea_field( 'cdn_reject_pages', $input['cdn_reject_pages'] );
		} else {
			$input['cdn_reject_pages'] = [];
		}

		// Option : Files to exclude from the CDN process.
		if ( ! empty( $input['cdn_reject_files'] ) ) {
			$input['cdn_reject_files'] = noobrocket_sanitize_textarea_field( 'cdn_reject_files', $input['cdn_reject_files'] );
		} else {
			$input['cdn_reject_files'] = [];
		}

		if ( $rejected_cnames || $rejected_scopes ) {
			// Keep the saved CDN configuration intact when a submitted row is rejected.
			foreach ( [ 'cdn' => 0, 'cdn_cnames' => [], 'cdn_zone' => [], 'cdn_reject_files' => [], 'cdn_reject_pages' => [] ] as $name => $default ) {
				$input[ $name ] = $this->options->get( $name, $default );
			}
			foreach ( $rejected_cnames as $row => $value ) {
				add_settings_error(
					'general',
					'cdn_cname_invalid_' . $row,
					sprintf(
						/* translators: 1: CDN row number, 2: rejected input, 3: link to CDN settings. */
						__( 'CDN row %1$s contains an invalid hostname: %2$s. CDN settings were not changed. %3$s', 'noobrocket' ),
						esc_html( number_format_i18n( $row ) ),
						'<code>' . esc_html( $value ) . '</code>',
						'<a href="#page_cdn">' . esc_html__( 'Review CDN settings', 'noobrocket' ) . '</a>'
					),
					'error'
				);
			}
			foreach ( array_keys( $rejected_scopes ) as $row ) {
				add_settings_error(
					'general',
					'cdn_scope_invalid_' . $row,
					sprintf(
						/* translators: 1: CDN row number, 2: allowed resource scopes. */
						__( 'CDN row %1$s has an invalid resource scope. Choose %2$s. CDN settings were not changed.', 'noobrocket' ),
						esc_html( number_format_i18n( $row ) ),
						esc_html( implode( ', ', self::get_cdn_scopes() ) )
					),
					'error'
				);
			}
		}

		$input['varnish_auto_purge'] = ! empty( $input['varnish_auto_purge'] ) ? 1 : 0;

		if ( empty( $input['ignore'] ) ) {
			// Add a "Settings saved." admin notice only if not already added, by NoobRocket ('updated')
			// or by core's options.php ('success').
			$notices = array_merge( (array) $wp_settings_errors, (array) get_transient( 'settings_errors' ) );
			$notices = array_filter(
				$notices,
				function ( $error ) {
					if ( ! $error || ! is_array( $error ) ) {
						return false;
					}
					if ( ! isset( $error['setting'], $error['code'], $error['type'] ) ) {
						return false;
					}
					return 'general' === $error['setting'] && 'settings_updated' === $error['code'] && in_array( $error['type'], [ 'updated', 'success' ], true );
				}
			);

			if ( ! $notices ) {
				$message = __( 'Settings saved.', 'noobrocket' );

				/**
				 * Filters the appended message to the "Settings saved." admin notice.
				 *
				 * @param string   $appended_message    The appended message.
				 */
				$message .= wpm_apply_filters_typed( 'string', 'noobrocket_settings_saved_message', '' );

				add_settings_error( 'general', 'settings_updated', $message, 'updated' );
			}
		}

		unset( $input['ignore'] );

		/**
		 * Filters the sanitized input before returning the array
		 *
		 * @param array    $input    Array of sanitized values after being submitted by the form.
		 * @param Settings $settings Current class instance.
		 */
		$input = apply_filters( 'noobrocket_input_sanitize', $input, $this );

		// NoobRocket has no license credentials or NoobRocket cloud services.
		unset( $input['consumer_key'], $input['consumer_email'], $input['secret_key'], $input['license'] );
		$input['optimize_css_delivery'] = 0;
		$input['remove_unused_css']     = 0;
		$input['async_css']             = 0;
		$input['automatic_performance_tests'] = 0;

		return $input;
	}

	/**
	 * Sanitizes the returned value of a checkbox
	 *
	 * @since 3.0
	 *
	 * @param array  $array Options array.
	 * @param string $key   Array key to check.
	 * @return int
	 */
	public function sanitize_checkbox( $array, $key ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.arrayFound
		$value = self::normalize_boolean( $array[ $key ] ?? false );
		return $value instanceof WP_Error ? 0 : $value;
	}

	/**
	 * Sanitizes the DNS Prefetch sub-option value
	 *
	 * @since 3.5.1
	 *
	 * @param array $input Array of values for the NoobRocket settings option.
	 * @return array Sanitized array for the DNS Prefetch sub-option
	 */
	private function sanitize_dns_prefetch( array $input ) {
		if ( empty( $input['dns_prefetch'] ) ) {
			return [];
		}

		$value = $input['dns_prefetch'];

		if ( ! is_array( $value ) ) {
			$value = explode( "\n", $value );
		}

		$urls = [];

		foreach ( $value as $url ) {
			$url = trim( $url );

			if ( empty( $url ) ) {
				continue;
			}

			$url = preg_replace( '/^(?:https?)?:?\/{3,}/i', 'http://', $url );
			$url = esc_url_raw( $url );

			if ( empty( $url ) ) {
				continue;
			}

			$urls[] = $url;
		}

		if ( empty( $urls ) ) {
			return [];
		}

		return array_unique(
			array_map(
				function ( $url ) {
					return '//' . wp_parse_url( $url, PHP_URL_HOST );
				},
				$urls
			)
		);
	}

	/**
	 * Sets radio buttons sub fields value from wp options.
	 *
	 * @since 3.10
	 *
	 * @param array $sub_fields Array of fields to display..
	 * @return array
	 */
	public function set_radio_buttons_sub_fields_value( $sub_fields ) {

		foreach ( $sub_fields as $id => &$args ) {
			$args['id']    = $id;
			$args['value'] = $this->options->get( $id, $args['default'] );
			$args          = apply_filters( 'noobrocket_before_render_option_extra_field', $args );
		}

		return $sub_fields;
	}

	/**
	 * Checks if the global exclusion pattern is used in the given field
	 *
	 * @since 3.10.3
	 *
	 * @param array $field A field array value.
	 *
	 * @return array
	 */
	private function check_global_exclusion( $field ) {
		if ( ! in_array( '/(.*)', $field, true ) ) {
			return $field;
		}

		add_settings_error( 'general', 'reject_uri_global_exclusion', __( 'Sorry! Adding /(.*) in Advanced Rules > Never Cache URL(s) was not saved because it disables caching and optimizations for every page on your site.', 'noobrocket' ) );

		return array_diff_key( $field, array_flip( array_keys( $field, '/(.*)', true ) ) );
	}

	/**
	 * Sanitizes the CDN cnames values
	 *
	 * @param mixed $cnames   User submitted values for the cnames.
	 * @param array $rejected Rejected values keyed by their displayed row number.
	 *
	 * @return array
	 */
	private static function sanitize_cdn_cnames( $cnames, array &$rejected ) {
		if ( ! is_array( $cnames ) ) {
			$rejected[1] = is_string( $cnames ) ? $cnames : __( 'Unsupported value', 'noobrocket' );
			return [];
		}

		$sanitized = [];
		$row       = 0;
		foreach ( $cnames as $key => $cname ) {
			++$row;
			if ( ! is_string( $cname ) || ! is_int( $key ) || $key < 0 ) {
				$rejected[ $row ] = is_string( $cname ) ? $cname : __( 'Unsupported value', 'noobrocket' );
				continue;
			}
			$cname = trim( $cname );
			if ( '' === $cname ) {
				continue;
			}

			// CDN URLs support HTTP(S), with an optional path, or a bare hostname.
			$raw_parts   = wp_parse_url( $cname );
			$has_scheme  = isset( $raw_parts['scheme'] );
			$invalid_url = $has_scheme && ( ! in_array( strtolower( $raw_parts['scheme'] ), [ 'http', 'https' ], true ) || empty( $raw_parts['host'] ) );
			if ( preg_match( '/\s/', $cname ) || $invalid_url ) {
				$rejected[ $row ] = $cname;
				continue;
			}
			$cname_url = $cname;
			if ( $has_scheme ) {
				$cname_url = strtolower( $raw_parts['scheme'] ) . substr( $cname, strlen( $raw_parts['scheme'] ) );
			}
			$cname_parts = get_noobrocket_parse_url( noobrocket_add_url_protocol( $cname_url ) );
			if ( ! in_array( $cname_parts['scheme'] ?? '', [ 'http', 'https' ], true ) || false === filter_var( $cname_parts['host'] ?? '', FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME ) ) {
				$rejected[ $row ] = $cname;
				continue;
			}
			$sanitized[ $key ] = untrailingslashit( $cname_parts['scheme'] . '://' . $cname_parts['host'] . ( $cname_parts['path'] ?? '' ) );
		}

		return array_unique( $sanitized );
	}

	/**
	 * Available resource scopes for each CDN host.
	 *
	 * @return array
	 */
	public static function get_cdn_scopes(): array {
		return [ 'all', 'images', 'css_and_js', 'css', 'js' ];
	}

	/** Native labels shared by the CDN editor and settings comparisons. */
	public static function get_cdn_scope_labels(): array {
		return [
			'all'        => __( 'All files', 'noobrocket' ),
			'css'        => __( 'CSS', 'noobrocket' ),
			'images'     => __( 'Images', 'noobrocket' ),
			'js'         => __( 'JavaScript', 'noobrocket' ),
			'css_and_js' => __( 'CSS & JavaScript', 'noobrocket' ),
		];
	}

	/**
	 * Normalize CDN hosts and their positional scopes together, preserving row ownership.
	 *
	 * @param mixed $cnames Submitted hosts.
	 * @param mixed $scopes Submitted resource scopes.
	 * @param array $rejected_cnames Rejected hosts keyed by displayed row.
	 * @param array $rejected_scopes Rejected scopes keyed by displayed row.
	 * @return array Normalized cdn_cnames and cdn_zone fields.
	 */
	public static function normalize_cdn_rows( $cnames, $scopes, array &$rejected_cnames, array &$rejected_scopes ): array {
		$hosts = self::sanitize_cdn_cnames( $cnames, $rejected_cnames );
		$rows = is_array( $cnames ) ? array_flip( array_keys( $cnames ) ) : [];
		$zones = [];
		if ( ! is_array( $scopes ) ) {
			$rejected_scopes[1] = __( 'Unsupported value', 'noobrocket' );
			$scopes = [];
		}
		foreach ( $hosts as $key => $host ) {
			$scope = $scopes[ $key ] ?? 'all';
			if ( ! is_string( $scope ) || ! in_array( $scope, self::get_cdn_scopes(), true ) ) {
				$rejected_scopes[ $rows[ $key ] + 1 ] = is_string( $scope ) ? $scope : __( 'Unsupported value', 'noobrocket' );
				$scope = 'all';
			}
			$zones[] = $scope;
		}
		return [ 'cdn_cnames' => array_values( $hosts ), 'cdn_zone' => $zones ];
	}
}
