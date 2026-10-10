<?php

defined( 'ABSPATH' ) || exit;

add_action( 'admin_init', 'noobrocket_clear_cache_after_studiopress_accelerator' );
/**
 * Clear NoobRocket cache after purged the StudioPress Accelerator cache
 *
 * @since 2.5.5
 *
 * @return void
 */
function noobrocket_clear_cache_after_studiopress_accelerator() {
	if ( ! current_user_can( 'noobrocket_manage_options' ) ) {
		return;
	}

	if ( isset( $GLOBALS['sp_accel_nginx_proxy_cache_purge'] ) && is_a( $GLOBALS['sp_accel_nginx_proxy_cache_purge'], 'SP_Accel_Nginx_Proxy_Cache_Purge' ) && isset( $_REQUEST['_wpnonce'] ) ) {
		$nonce = $_REQUEST['_wpnonce']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.NonceVerification.Recommended
		if ( wp_verify_nonce( $nonce, 'sp-accel-purge-url' ) && ! empty( $_REQUEST['cache-purge-url'] ) ) {
			$submitted_url = sanitize_text_field( wp_unslash( $_REQUEST['cache-purge-url'] ) );

			// Clear the URL.
			noobrocket_clean_files( [ $submitted_url ] );
		} elseif ( wp_verify_nonce( $nonce, 'sp-accel-purge-theme' ) ) {
			// Clear all caching files.
			noobrocket_clean_domain();

			// Preload cache.
			run_noobrocket_bot();
			run_noobrocket_sitemap_preload();
		}
	}
}

add_action( 'noobrocket_after_clean_domain', 'noobrocket_clean_studiopress_accelerator' );
/**
 * Call the cache server to purge the cache with StudioPress Accelerator.
 *
 * @since 2.5.5
 *
 * @return void
 */
function noobrocket_clean_studiopress_accelerator() {
	if ( isset( $GLOBALS['sp_accel_nginx_proxy_cache_purge'] ) && is_a( $GLOBALS['sp_accel_nginx_proxy_cache_purge'], 'SP_Accel_Nginx_Proxy_Cache_Purge' ) ) {
		$GLOBALS['sp_accel_nginx_proxy_cache_purge']->cache_flush_theme();
	}
}
