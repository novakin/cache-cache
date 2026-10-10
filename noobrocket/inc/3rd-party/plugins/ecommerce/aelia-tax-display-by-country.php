<?php

defined( 'ABSPATH' ) || exit;

/**
 * Compatibility with Aelia Tax Display by Country.
 *
 * @since 2.8.15
 */
if ( class_exists( 'Aelia\WC\TaxDisplayByCountry\WC_Aelia_Tax_Display_By_Country' ) ) :
	/**
	 * Generate a caching file depending to the tax display cookie values
	 */
	add_filter( 'noobrocket_htaccess_mod_rewrite', '__return_false', 68 );
	add_filter( 'noobrocket_cache_dynamic_cookies', 'noobrocket_add_aelia_tax_display_by_country_dynamic_cookies' );
endif;

/**
 * Add cookies when we activate the plugin
 *
 * @since 2.8.15
 * @author Remy Perona
 */
function noobrocket_activate_aelia_tax_display_by_country() {
	add_filter( 'noobrocket_htaccess_mod_rewrite', '__return_false', 68 );
	add_filter( 'noobrocket_cache_dynamic_cookies', 'noobrocket_add_aelia_tax_display_by_country_dynamic_cookies' );

	// Update the NoobRocket rules on the .htaccess file.
	flush_noobrocket_htaccess();

	// Regenerate the config file.
	noobrocket_generate_config_file();
}
add_action( 'activate_woocommerce-tax-display-by-country/woocommerce-tax-display-by-country.php', 'noobrocket_activate_aelia_tax_display_by_country', 11 );

/**
 * Remove cookies when we deactivate the plugin
 *
 * @since 2.8.15
 * @author Remy Perona
 */
function noobrocket_deactivate_aelia_tax_display_by_country() {
	remove_filter( 'noobrocket_htaccess_mod_rewrite', '__return_false', 68 );
	remove_filter( 'noobrocket_cache_dynamic_cookies', 'noobrocket_add_aelia_tax_display_by_country_dynamic_cookies' );

	// Update the NoobRocket rules on the .htaccess file.
	flush_noobrocket_htaccess();

	// Regenerate the config file.
	noobrocket_generate_config_file();
}
add_action( 'deactivate_woocommerce-tax-display-by-country/woocommerce-tax-display-by-country.php', 'noobrocket_deactivate_aelia_tax_display_by_country', 11 );

/**
 * Add the Aelia Tax Display by Country cookies to generate caching files depending on their values
 *
 * @since 2.8.15
 * @author Remy Perona
 *
 * @param array $cookies Cookies list to use for dynamic caching.
 * @return array Updated cookies list
 */
function noobrocket_add_aelia_tax_display_by_country_dynamic_cookies( $cookies ) {
	$cookies[] = 'aelia_customer_country';
	$cookies[] = 'aelia_customer_state';
	$cookies[] = 'aelia_tax_exempt';

	return $cookies;
}
