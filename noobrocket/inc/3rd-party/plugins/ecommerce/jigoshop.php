<?php

defined( 'ABSPATH' ) || exit;

if ( defined( 'JIGOSHOP_VERSION' ) && function_exists( 'jigoshop_get_page_id' ) ) :
	add_filter( 'noobrocket_cache_reject_uri', 'noobrocket_exclude_jigoshop_pages' );
	add_action( 'update_option_jigoshop_options', 'noobrocket_after_update_array_options', 10, 2 );
endif;

/**
 * Exclude Jigoshop pages from cache.
 *
 * @param array $urls Array of URLs to exclude from cache.
 * @return array Updated array of URLs to exclude from cache
 */
function noobrocket_exclude_jigoshop_pages( $urls ) {
	if ( jigoshop_get_page_id( 'checkout' ) && jigoshop_get_page_id( 'checkout' ) !== '-1' ) {
		$checkout_urls = get_noobrocket_i18n_translated_post_urls( jigoshop_get_page_id( 'checkout' ), 'page', '(.*)' );
		$urls          = array_merge( $urls, $checkout_urls );
	}

	if ( jigoshop_get_page_id( 'cart' ) && jigoshop_get_page_id( 'cart' ) !== '-1' ) {
		$cart_urls = get_noobrocket_i18n_translated_post_urls( jigoshop_get_page_id( 'cart' ) );
		$urls      = array_merge( $urls, $cart_urls );
	}

	if ( jigoshop_get_page_id( 'myaccount' ) && jigoshop_get_page_id( 'myaccount' ) !== '-1' ) {
		$cart_urls = get_noobrocket_i18n_translated_post_urls( jigoshop_get_page_id( 'myaccount' ), 'page', '(.*)' );
		$urls      = array_merge( $urls, $cart_urls );
	}

	return $urls;
}

/**
 * Exclude Jigoshop pages from cache on plugin activation.
 */
function noobrocket_activate_jigoshop() {
	add_filter( 'noobrocket_cache_reject_uri', 'noobrocket_exclude_jigoshop_pages' );

	// Update .htaccess file rules.
	flush_noobrocket_htaccess();

	// Regenerate the config file.
	noobrocket_generate_config_file();
}
add_action( 'activate_jigoshop/jigoshop.php', 'noobrocket_activate_jigoshop', 11 );

/**
 * Remove Jigoshop pages from cache exclusion on plugin deactivation.
 */
function noobrocket_deactivate_jigoshop() {
	remove_filter( 'noobrocket_cache_reject_uri', 'noobrocket_exclude_jigoshop_pages' );

	// Update .htaccess file rules.
	flush_noobrocket_htaccess();

	// Regenerate the config file.
	noobrocket_generate_config_file();
}
add_action( 'deactivate_jigoshop/jigoshop.php', 'noobrocket_deactivate_jigoshop', 11 );
