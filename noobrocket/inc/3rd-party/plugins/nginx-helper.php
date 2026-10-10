<?php

defined( 'ABSPATH' ) || exit;

global $nginx_helper;

if ( isset( $nginx_helper ) ) :
	global $nginx_purger;

	/**
	 * Clear NoobRocket cache after the NGINX cache is purged from Nginx Helper.
	 *
	 * @since 3.3.0.1
	 *
	 * @return void
	 */
	function noobrocket_clear_cache_after_nginx_helper_purge() {
		if ( ! isset( $_GET['nginx_helper_action'] ) ) {
			return;
		}

		if ( ! check_admin_referer( 'nginx_helper-purge_all' ) ) {
			return;
		}

		if ( 'done' !== sanitize_text_field( wp_unslash( $_GET['nginx_helper_action'] ) ) ) {
			return;
		}

		if ( ! current_user_can( 'noobrocket_purge_cache' ) ) {
			return;
		}

		// Clear all caching files.
		noobrocket_clean_domain();
	}
	add_action( 'admin_init', 'noobrocket_clear_cache_after_nginx_helper_purge' );

	/**
	 * Clear NoobRocket cache for the current page after the NGINX cache is purged from Nginx Helper.
	 *
	 * @since 3.3.0.1
	 *
	 * @return void
	 */
	function noobrocket_clear_current_page_after_nginx_helper_purge() {
		if ( ! isset( $_GET['nginx_helper_action'], $_GET['_wpnonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'nginx_helper-purge_all' ) ) {
			return;
		}

		if ( is_admin() ) {
			return;
		}

		if ( ! current_user_can( 'noobrocket_purge_posts' ) ) {
			return;
		}

		$referer = wp_get_referer();

		if ( 0 !== strpos( $referer, 'http' ) ) {
			$parse_url = get_noobrocket_parse_url( untrailingslashit( home_url() ) );
			$referer   = $parse_url['scheme'] . '://' . $parse_url['host'] . $referer;
		}

		if ( home_url( '/' ) === $referer ) {
			noobrocket_clean_home();
			return;
		}

		noobrocket_clean_files( $referer );
	}
	add_action( 'init', 'noobrocket_clear_current_page_after_nginx_helper_purge' );

	/**
	 * Clears NGINX cache for the homepage URL when using "Purge this URL" from the admin bar on the front end
	 *
	 * @since 3.3.0.1
	 * @author Remy Perona
	 *
	 * @param string $root NoobRocket root cache path.
	 * @param string $lang Current language.
	 * @return void
	 */
	function noobrocket_clean_nginx_cache_home( $root = '', $lang = '' ) {
		global $nginx_purger;

		if ( ! isset( $nginx_purger ) ) {
			return;
		}

		$url = get_noobrocket_i18n_home_url( $lang );

		$nginx_purger->purge_url( $url );
	}
	add_action( 'after_noobrocket_clean_home', 'noobrocket_clean_nginx_cache_home', 10, 2 );

	/**
	 * Clears NGINX cache for a specific URL when using "Purge this URL" from the admin bar on the front end
	 *
	 * @since 3.3.0.1
	 * @author Remy Perona
	 *
	 * @param string $url URL to purge.
	 * @return void
	 */
	function noobrocket_clean_nginx_cache_url( $url ) {
		global $nginx_purger;

		if ( ! isset( $nginx_purger ) ) {
			return;
		}

		if ( ! isset( $_GET['type'], $_GET['_wpnonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( false !== strpos( $url, 'index.html' ) ) {
			return;
		}

		if ( 'page' === substr( $url, -4 ) ) {
			return;
		}

		$url = str_replace( '*', '', $url );

		$nginx_purger->purge_url( $url );
	}
	add_action( 'after_noobrocket_clean_file', 'noobrocket_clean_nginx_cache_url' );

	/**
	 * Clean the NGINX cache using Nginx Helper after NoobRocket's cache is purged.
	 *
	 * @since 3.3.0.1
	 */
	function noobrocket_clean_nginx_helper_cache() {
		if ( isset( $_GET['nginx_helper_action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		do_action( 'rt_nginx_helper_purge_all' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
	}
	add_action( 'noobrocket_after_clean_domain', 'noobrocket_clean_nginx_helper_cache' );

	/**
	 * Clean the NGINX cache after the Used CSS has been generated.
	 *
	 * @since 3.12.3
	 */
	add_action( 'noobrocket_rucss_after_clearing_usedcss', 'noobrocket_clean_nginx_cache_url' );
	add_action( 'noobrocket_saas_complete_job_status', 'noobrocket_clean_nginx_helper_cache' );

endif;
