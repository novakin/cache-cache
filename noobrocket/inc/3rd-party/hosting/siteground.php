<?php

defined( 'ABSPATH' ) || exit;

/**
 * Returns the current version of the SG Optimizer plugin.
 *
 * @since  3.2.3.1
 * @author Remy Perona
 *
 * @return string version number.
 */
function noobrocket_get_sg_optimizer_version() {
	static $version;

	if ( isset( $version ) ) {
		return $version;
	}

	$sg_optimizer = get_file_data( WP_PLUGIN_DIR . '/sg-cachepress/sg-cachepress.php', [ 'Version' => 'Version' ] );
	$version      = $sg_optimizer['Version'];

	return $version;
}

/**
 * Checks if SG Optimizer Supercache is active.
 *
 * @since  3.2.3.1
 * @author Remy Perona
 *
 * @return bool
 */
function noobrocket_is_supercacher_active() {
	if ( ! version_compare( noobrocket_get_sg_optimizer_version(), '5.0' ) < 0 ) {
		global $sg_cachepress_environment;

		return isset( $sg_cachepress_environment ) && $sg_cachepress_environment instanceof SG_CachePress_Environment && $sg_cachepress_environment->cache_is_enabled();
	}

	return (bool) get_option( 'siteground_optimizer_enable_cache', 0 );
}

/**
 * Call the cache server to purge the cache with SuperCacher (SiteGround).
 *
 * @since 2.3
 *
 * @return void
 */
function noobrocket_clean_supercacher() {
	if ( ! noobrocket_is_supercacher_active() ) {
		return;
	}

	if ( ! version_compare( noobrocket_get_sg_optimizer_version(), '5.0' ) < 0 ) {
		SiteGround_Optimizer\Supercacher\Supercacher::purge_cache();
	} elseif ( isset( $sg_cachepress_supercacher ) && $sg_cachepress_supercacher instanceof SG_CachePress_Supercacher ) {
		$sg_cachepress_supercacher->purge_cache();
	}
}

/**
 * Clean NoobRocket cache when cleaning SG cache
 *
 * @return void
 */
function noobrocket_sg_clear_cache() {
	if ( empty( $_GET['_wpnonce'] ) ) {
		return;
	}

	if ( ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'sg-cachepress-purge' ) ) {
		return;
	}

	if ( ! current_user_can( 'noobrocket_purge_cache' ) ) {
		return;
	}

	noobrocket_clean_domain();
}

if ( noobrocket_is_supercacher_active() ) {
	add_action( 'admin_post_sg-cachepress-purge', 'noobrocket_sg_clear_cache', 0 );
	add_action( 'noobrocket_after_clean_domain', 'noobrocket_clean_supercacher' );
	add_filter( 'noobrocket_display_varnish_options_tab', '__return_false' );
	// Prevent mandatory cookies on hosting with server cache.
	add_filter( 'noobrocket_cache_mandatory_cookies', '__return_empty_array', PHP_INT_MAX );

	/**
	 * Force NoobRocket caching on SG Optimizer versions before 4.0.5.
	 *
	 * @since  3.0.4
	 * @author Arun Basil Lal
	 *
	 */
	if ( version_compare( noobrocket_get_sg_optimizer_version(), '4.0.5' ) < 0 ) {
		add_filter( 'do_noobrocket_generate_caching_files', '__return_true', 11 );
	}

	if ( version_compare( noobrocket_get_sg_optimizer_version(), '5.0' ) < 0 ) {
		add_action( 'wp_ajax_sg-cachepress-purge', 'noobrocket_sg_clear_cache', 0 );
	} else {
		add_action( 'wp_ajax_admin_bar_purge_cache', 'noobrocket_sg_clear_cache', 0 );
	}
}
