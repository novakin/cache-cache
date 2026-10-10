<?php

defined( 'ABSPATH' ) || exit;

if ( defined( 'WPSHOP_VERSION' ) && class_exists( 'wpshop_tools' ) && method_exists( 'wpshop_tools', 'get_page_id' ) ) :
	add_filter( 'noobrocket_cache_reject_uri', 'noobrocket_exclude_wpshop_pages' );
	add_action( 'update_option_wpshop_cart_page_id', 'noobrocket_after_update_single_options', 10, 2 );
	add_action( 'update_option_wpshop_checkout_page_id', 'noobrocket_after_update_single_options', 10, 2 );
	add_action( 'update_option_wpshop_payment_return_page_id', 'noobrocket_after_update_single_options', 10, 2 );
	add_action( 'update_option_wpshop_payment_return_nok_page_id', 'noobrocket_after_update_single_options', 10, 2 );
	add_action( 'update_option_wpshop_myaccount_page_id', 'noobrocket_after_update_single_options', 10, 2 );
endif;


/**
 * Exclude WP Shop pages from cache.
 *
 * @param array $urls Array of URLs to exclude from cache.
 * @return array Updated array of URLs to exclude from cache
 */
function noobrocket_exclude_wpshop_pages( $urls ) {
	$pages = [
		'wpshop_cart_page_id',
		'wpshop_checkout_page_id',
		'wpshop_payment_return_page_id',
		'wpshop_payment_return_nok_page_id',
		'wpshop_myaccount_page_id',
	];

	foreach ( $pages as $page ) {
		$page_id = wpshop_tools::get_page_id( get_option( $page ) );
		if ( $page_id ) {
			$urls = array_merge( $urls, get_noobrocket_i18n_translated_post_urls( $page_id ) );
		}
	}

	return $urls;
}

/**
 * Exclude WP Shop pages from cache on plugin activation.
 */
function noobrocket_activate_wpshop() {
	add_filter( 'noobrocket_cache_reject_uri', 'noobrocket_exclude_wpshop_pages' );

	// Update .htaccess file rules.
	flush_noobrocket_htaccess();

	// Regenerate the config file.
	noobrocket_generate_config_file();
}
add_action( 'activate_wpshop/wpshop.php', 'noobrocket_activate_wpshop', 11 );

/**
 * Remove WP Shop pages from cache exclusion on plugin deactivation.
 */
function noobrocket_deactivate_wpshop() {
	remove_filter( 'noobrocket_cache_reject_uri', 'noobrocket_exclude_wpshop_pages' );

	// Update .htaccess file rules.
	flush_noobrocket_htaccess();

	// Regenerate the config file.
	noobrocket_generate_config_file();
}
add_action( 'deactivate_wpshop/wpshop.php', 'noobrocket_deactivate_wpshop', 11 );
