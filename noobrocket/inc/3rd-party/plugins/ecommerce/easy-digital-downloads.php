<?php

defined( 'ABSPATH' ) || exit;

if ( function_exists( 'EDD' ) ) :
	add_filter( 'noobrocket_cache_reject_uri', 'noobrocket_exclude_edd_pages' );
	add_action( 'update_option_edd_settings', 'noobrocket_after_update_array_options', 10, 2 );
endif;

/**
 * Exclude EDD pages from cache
 *
 * @param array $urls Array of URLs to exclude from cache.
 * @return array Updated array of URLs
 */
function noobrocket_exclude_edd_pages( $urls ) {
	$edd_settings = get_option( 'edd_settings' );
	if ( isset( $edd_settings['purchase_page'] ) ) {
		$checkout_urls = get_noobrocket_i18n_translated_post_urls( $edd_settings['purchase_page'], 'page', '(.*)' );
		$urls          = array_merge( $urls, $checkout_urls );
	}

	return $urls;
}

/**
 * Exclude EDD pages from cache on EDD activation.
 */
function noobrocket_activate_edd() {
	add_filter( 'noobrocket_cache_reject_uri', 'noobrocket_exclude_edd_pages' );

	// Update .htaccess file rules.
	flush_noobrocket_htaccess();

	// Regenerate the config file.
	noobrocket_generate_config_file();
}
add_action( 'activate_easy-digital-downloads/easy-digital-downloads.php', 'noobrocket_activate_edd', 11 );

/**
 * Remove EDD pages from cache exclusion on EDD deactivation.
 */
function noobrocket_deactivate_edd() {
	remove_filter( 'noobrocket_cache_reject_uri', 'noobrocket_exclude_edd_pages' );

	// Update .htaccess file rules.
	flush_noobrocket_htaccess();

	// Regenerate the config file.
	noobrocket_generate_config_file();
}
add_action( 'deactivate_easy-digital-downloads/easy-digital-downloads.php', 'noobrocket_deactivate_edd', 11 );
