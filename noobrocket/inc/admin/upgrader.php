<?php
use NoobRocket\Logger\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Tell WP what to do when admin is loaded aka upgrader
 *
 * @since 1.0
 */
function noobrocket_upgrader() {
	// Grab some infos.
	$actual_version = (string) get_noobrocket_option( 'version', '' );
	$actual_engine_version = (string) get_noobrocket_option( 'engine_version', NOOBROCKET_ENGINE_VERSION );
	// You can hook the upgrader to trigger any action when NoobRocket is upgraded.
	// first install.
	if ( ! $actual_version ) {
		do_action( 'noobrocket_first_install' );
	}
	// already installed but got updated.
	elseif ( NOOBROCKET_ENGINE_VERSION !== $actual_engine_version ) {
		do_action( 'noobrocket_upgrade', NOOBROCKET_ENGINE_VERSION, $actual_engine_version );
	}

	// If any upgrade has been done, we flush and update version number.
	if ( did_action( 'noobrocket_first_install' ) || did_action( 'noobrocket_upgrade' ) || NOOBROCKET_VERSION !== $actual_version || ! get_noobrocket_option( 'engine_version', '' ) ) {
		flush_noobrocket_htaccess();

		$options = get_option( NOOBROCKET_SLUG ); // do not use get_noobrocket_option() here.

		$options['version']          = NOOBROCKET_VERSION;
		$options['previous_version'] = $actual_version;
		$options['engine_version']   = NOOBROCKET_ENGINE_VERSION;

		update_option( NOOBROCKET_SLUG, $options );

		if ( $actual_version && NOOBROCKET_VERSION !== $actual_version ) {
			// Cached pages can still reference assets or markup replaced by this release.
			noobrocket_clean_domain();
			noobrocket_clean_minify();
		}
	}

	$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if (
		'noobrocket' === $page
		&&
		did_action( 'noobrocket_upgrade' )
	) {
		wp_safe_redirect( esc_url_raw( admin_url( 'options-general.php?page=noobrocket' ) ) );
		exit;
	}

}
add_action( 'admin_init', 'noobrocket_upgrader' );

/**
 * Keeps this function up to date at each version
 *
 * @since 1.0
 */
function noobrocket_first_install() {
	// Generate an random key for cache dir of user.
	$secret_cache_key = create_noobrocket_uniqid();

	// Generate an random key for minify md5 filename.
	$minify_css_key = create_noobrocket_uniqid();
	$minify_js_key  = create_noobrocket_uniqid();

	// Create Option.
	add_option(
		noobrocket_get_constant( 'NOOBROCKET_SLUG' ),
		/**
		 * Filters the default rocket options array
		 *
		 * @since 2.8
		 *
		 * @param array Array of default rocket options
		 */
		apply_filters(
			'noobrocket_first_install_options',
			[
				'secret_cache_key'            => $secret_cache_key,
				'cache_mobile'                => 1,
				'do_caching_mobile_files'     => 1,
				'cache_webp'                  => 0,
				'image_delivery'              => 0,
				'cache_stale_refresh'         => 0,
				'remove_dashicons'            => 0,
				'remove_embed_assets'         => 0,
				'remove_block_library'        => 0,
				'cache_logged_user'           => 0,
				'cache_ssl'                   => 1,
				'emoji'                       => 1,
				'cache_reject_uri'            => [],
				'cache_reject_cookies'        => [],
				'cache_reject_ua'             => [],
				'cache_query_strings'         => [],
				'cache_purge_pages'           => [],
				'purge_cron_interval'         => 10,
				'purge_cron_unit'             => 'HOUR_IN_SECONDS',
				'exclude_css'                 => [],
				'exclude_js'                  => [],
				'exclude_inline_js'           => [],
				'defer_all_js'                => 0,
				'async_css'                   => 0,
				'critical_css'                => '',
				'lazyload'                    => 0,
				'lazyload_iframes'            => 0,
				'lazyload_youtube'            => 0,
				'minify_css'                  => 1,
				'minify_css_key'              => $minify_css_key,
				'minify_js'                   => 1,
				'minify_js_key'               => $minify_js_key,
				'minify_concatenate_js'       => 0,
				'minify_google_fonts'         => 1,
				'manual_preload'              => 1,
				'dns_prefetch'                => 0,
				'preload_fonts'               => [],
				'database_revisions'          => 0,
				'database_auto_drafts'        => 0,
				'database_trashed_posts'      => 0,
				'database_spam_comments'      => 0,
				'database_trashed_comments'   => 0,
				'database_all_transients'     => 0,
				'database_optimize_tables'    => 0,
				'schedule_automatic_cleanup'  => 0,
				'automatic_cleanup_frequency' => 'daily',
				'cdn'                         => 0,
				'cdn_cnames'                  => [],
				'cdn_zone'                    => [],
				'cdn_reject_files'            => [],
				'cdn_state'                   => 'nothing',
				'do_cloudflare'               => 0,
				'cloudflare_email'            => '',
				'cloudflare_api_key'          => '',
				'cloudflare_zone_id'          => '',
				'cloudflare_devmode'          => 0,
				'cloudflare_protocol_rewrite' => 0,
				'cloudflare_auto_settings'    => 0,
				'cloudflare_old_settings'     => '',
				'control_heartbeat'           => 1,
				'heartbeat_site_behavior'     => 'reduce_periodicity',
				'heartbeat_admin_behavior'    => 'reduce_periodicity',
				'heartbeat_editor_behavior'   => 'reduce_periodicity',
				'varnish_auto_purge'          => 0,
				'analytics_enabled'           => 0,
				'sucury_waf_cache_sync'       => 0,
				'sucury_waf_api_key'          => '',
				'noobrocket-js-tips'                 => 1,
			]
		)
	);
	noobrocket_dismiss_box( 'noobrocket_warning_plugin_modification' );
}
add_action( 'noobrocket_first_install', 'noobrocket_first_install' );

/**
 * What to do when Rocket is updated, depending on versions
 *
 * @since 1.0
 *
 * @param string $noobrocket_version Latest NoobRocket version.
 * @param string $actual_version Installed NoobRocket version.
 */
function noobrocket_new_upgrade( $noobrocket_version, $actual_version ) {
	if ( version_compare( $actual_version, '2.4.1', '<' ) ) {
		delete_transient( 'noobrocket_ask_for_update' );
	}

	if ( version_compare( $actual_version, '2.8', '<' ) ) {
		$options                              = get_option( NOOBROCKET_SLUG );
		$options['manual_preload']            = 1;
		$options['automatic_preload']         = 1;
		$options['sitemap_preload_url_crawl'] = '500000';

		update_option( NOOBROCKET_SLUG, $options );
	}

	// Deactivate CloudFlare completely if PHP Version is lower than 5.4.
	if ( version_compare( $actual_version, '2.8.16', '<' ) ) {
		$options                                = get_option( NOOBROCKET_SLUG );
		$options['do_cloudflare']               = 0;
		$options['cloudflare_email']            = '';
		$options['cloudflare_api_key']          = '';
		$options['cloudflare_devmode']          = 0;
		$options['cloudflare_protocol_rewrite'] = 0;
		$options['cloudflare_auto_settings']    = 0;
		$options['cloudflare_old_settings']     = '';

		update_option( NOOBROCKET_SLUG, $options );
	}

	// Disable minification options if they're active in Autoptimize.
	if ( version_compare( $actual_version, '2.9.5', '<' ) ) {
		if ( is_plugin_active( 'autoptimize/autoptimize.php' ) ) {
			if ( 'on' === get_option( 'autoptimize_css' ) ) {
				update_noobrocket_option( 'minify_css', 0 );
			}

			if ( 'on' === get_option( 'autoptimize_js' ) ) {
				update_noobrocket_option( 'minify_js', 0 );
			}
		}
	}

	// Delete old transients.
	if ( version_compare( $actual_version, '2.9.7', '<' ) ) {
		delete_transient( 'noobrocket_check_licence_30' );
		delete_transient( 'noobrocket_check_licence_1' );
	}

	if ( version_compare( $actual_version, '2.11', '<' ) ) {
		noobrocket_clean_minify();
	}

	if ( version_compare( $actual_version, '3.2', '<' ) ) {
		// Default Heartbeat settings.
		$options                              = get_option( NOOBROCKET_SLUG, [] );
		$options['heartbeat_site_behavior']   = 'reduce_periodicity';
		$options['heartbeat_admin_behavior']  = 'reduce_periodicity';
		$options['heartbeat_editor_behavior'] = 'reduce_periodicity';

		if ( ! empty( $options['automatic_preload'] ) || ! empty( $options['sitemap_preload'] ) ) {
			$options['manual_preload'] = 1;
		}

		update_option( NOOBROCKET_SLUG, $options );
		noobrocket_generate_config_file();

		// Create a .htaccess file in the log folder.
		$handler = Logger::get_stream_handler();

		if ( method_exists( $handler, 'create_htaccess_file' ) ) {
			try {
				$success = $handler->create_htaccess_file();
			} catch ( \Exception $e ) {
				$success = false;
			}

			if ( ! $success ) {
				Logger::delete_log_file();
			}
		}
	}

	if ( version_compare( $actual_version, '3.2.0.1', '<' ) ) {
		wp_safe_remote_get( esc_url( home_url() ) );
	}

	if ( version_compare( $actual_version, '3.12.6', '<' ) ) {
		do_action( 'noobrocket_preload_unlock_all_urls' );
	}

	if ( version_compare( $actual_version, '3.3.6', '<' ) ) {
		delete_site_transient( 'update_noobrocket' );
		delete_site_transient( 'update_noobrocket_response' );

		if ( get_noobrocket_option( 'do_cloudflare' ) && get_noobrocket_option( 'cloudflare_auto_settings' ) ) {
			if ( function_exists( 'set_noobrocket_cloudflare_browser_cache_ttl' ) ) {
				set_noobrocket_cloudflare_browser_cache_ttl( '31536000' );
			}
		}
	}

	if ( noobrocket_is_ssl_website() ) {
		if ( 1 !== (int) get_noobrocket_option( 'cache_ssl' ) ) {
			update_noobrocket_option( 'cache_ssl', 1 );
			noobrocket_generate_config_file();
		}
	}

	if ( version_compare( $actual_version, '3.4', '<' ) ) {
		wp_clear_scheduled_hook( 'noobrocket_purge_time_event' );
	}

	if ( version_compare( $actual_version, '3.6', '<' ) ) {
		noobrocket_clean_cache_busting();
		noobrocket_clean_domain();
	}

	if ( version_compare( $actual_version, '3.7', '<' ) ) {
		noobrocket_clean_minify( 'css' );
		noobrocket_generate_advanced_cache_file();
	}

	if ( version_compare( $actual_version, '3.8.1', '<' ) ) {
		$options = get_option( noobrocket_get_constant( 'NOOBROCKET_SLUG' ) );
		unset( $options['dequeue_jquery_migrate'] );
		update_option( noobrocket_get_constant( 'NOOBROCKET_SLUG' ), $options );
	}

	if ( version_compare( $actual_version, '3.9', '<' ) ) {
		$busting_path = noobrocket_get_constant( 'NOOBROCKET_CACHE_BUSTING_PATH' );

		noobrocket_rrmdir( $busting_path . 'facebook-tracking' );
		noobrocket_rrmdir( $busting_path . 'google-tracking' );
		wp_clear_scheduled_hook( 'noobrocket_facebook_tracking_cache_update' );
		wp_clear_scheduled_hook( 'noobrocket_google_tracking_cache_update' );
	}

	if ( version_compare( $actual_version, '3.10', '<' ) ) {
		$options = get_option( noobrocket_get_constant( 'NOOBROCKET_SLUG' ) );
		if (
			isset( $options['async_css'] ) && $options['async_css'] &&
			isset( $options['remove_unused_css'] ) && $options['remove_unused_css']
		) {
			$options['async_css'] = 0;
			$cache_path           = noobrocket_get_constant( 'NOOBROCKET_CACHE_ROOT_PATH' );
			noobrocket_rrmdir( $cache_path . 'used-css' );
			update_option( noobrocket_get_constant( 'NOOBROCKET_SLUG' ), $options );
		}
	}

	if ( version_compare( $actual_version, '3.11.1', '<' ) ) {
		noobrocket_generate_config_file();
	}

	if ( version_compare( $actual_version, '3.12.4', '<' ) ) {
		delete_transient( 'noobrocket_pricing' );
	}

	if ( version_compare( $actual_version, '3.23', '<' ) ) {
		flush_rewrite_rules();
	}
}
add_action( 'noobrocket_upgrade', 'noobrocket_new_upgrade', 10, 2 );
