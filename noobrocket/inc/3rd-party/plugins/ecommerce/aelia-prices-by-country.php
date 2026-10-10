<?php

defined( 'ABSPATH' ) || exit;

/**
 * Compatibility with Aelia Prices by Country.
 *
 * @since 2.8.15
 */
if ( class_exists( 'Aelia\WC\PricesByCountry\WC_Aelia_Prices_By_Country' ) ) :
	/**
	 * Generate a caching file depending on the country cookie value
	 */
	add_filter( 'noobrocket_htaccess_mod_rewrite', '__return_false', 66 );
	add_filter( 'noobrocket_cache_dynamic_cookies', 'noobrocket_add_aelia_prices_by_country_dynamic_cookies' );
endif;

/**
 * Add cookie when we activate the plugin
 *
 * @since 2.8.15
 * @author Remy Perona
 */
function noobrocket_activate_aelia_prices_by_country() {
	add_filter( 'noobrocket_htaccess_mod_rewrite', '__return_false', 66 );
	add_filter( 'noobrocket_cache_dynamic_cookies', 'noobrocket_add_aelia_prices_by_country_dynamic_cookies' );

	// Update the NoobRocket rules on the .htaccess file.
	flush_noobrocket_htaccess();

	// Regenerate the config file.
	noobrocket_generate_config_file();
}
add_action( 'activate_woocommerce-prices-by-country/woocommerce-prices-by-country.php', 'noobrocket_activate_aelia_prices_by_country', 11 );

/**
 * Remove cookies when we deactivate the plugin
 *
 * @since 2.8.15
 * @author Remy Perona
 */
function noobrocket_deactivate_aelia_prices_by_country() {
	remove_filter( 'noobrocket_htaccess_mod_rewrite', '__return_false', 66 );
	remove_filter( 'noobrocket_cache_dynamic_cookies', 'noobrocket_add_aelia_prices_by_country_dynamic_cookies' );

	// Update the NoobRocket rules on the .htaccess file.
	flush_noobrocket_htaccess();

	// Regenerate the config file.
	noobrocket_generate_config_file();
}
add_action( 'deactivate_woocommerce-prices-by-country/woocommerce-prices-by-country.php', 'noobrocket_deactivate_aelia_prices_by_country', 11 );

/**
 * Add the Aelia Prices by Country cookie to generate caching files depending on its value
 *
 * @since 2.8.15
 * @author Remy Perona
 *
 * @param array $cookies Cookies list to use for dynamic caching.
 * @return array Updated cookies list
 */
function noobrocket_add_aelia_prices_by_country_dynamic_cookies( $cookies ) {
	$cookies[] = 'aelia_customer_country';
	return $cookies;
}
