<?php

defined( 'ABSPATH' ) || exit;

/**
 * Compatibility with Aelia Currency Switcher.
 *
 * @since 2.7
 */
if ( class_exists( 'WC_Aelia_CurrencySwitcher' ) ) :
	/**
	 * Update .htaccess & config files when the Geolocation option is updated
	 *
	 * @since 2.7
	 *
	 * @param array $old_value Previous setting values.
	 * @param array $value Submitted settings values.
	 */
	function noobrocket_after_update_aelia_currencyswitcher_options( $old_value, $value ) {
		if ( ( ! isset( $old_value['ipgeolocation_enabled'] ) && isset( $value['ipgeolocation_enabled'] ) ) || ( isset( $old_value['ipgeolocation_enabled'], $value['ipgeolocation_enabled'] ) && $old_value['ipgeolocation_enabled'] !== $value['ipgeolocation_enabled'] )
		) {
			// Update the NoobRocket rules on the .htaccess file.
			flush_noobrocket_htaccess();

			// Update the config file.
			noobrocket_generate_config_file();
		}
	}
	add_action( 'update_option_wc_aelia_currency_switcher', 'noobrocket_after_update_aelia_currencyswitcher_options', 10, 2 );

	/**
	 * Generate a caching file depending on the currency cookie value
	 */
	add_filter( 'noobrocket_htaccess_mod_rewrite', '__return_false', 64 );
	add_filter( 'noobrocket_cache_dynamic_cookies', 'noobrocket_add_aelia_currencyswitcher_dynamic_cookies' );
	add_filter( 'noobrocket_cache_mandatory_cookies', 'noobrocket_add_aelia_currencyswitcher_mandatory_cookie' );
endif;

/**
 * Add the Aelia Currency Switcher cookies when activating the plugin
 *
 * @since 2.7
 */
function noobrocket_activate_aelia_currencyswitcher() {
	add_filter( 'noobrocket_htaccess_mod_rewrite', '__return_false', 64 );
	add_filter( 'noobrocket_cache_dynamic_cookies', 'noobrocket_add_aelia_currencyswitcher_dynamic_cookies' );
	add_filter( 'noobrocket_cache_mandatory_cookies', 'noobrocket_add_aelia_currencyswitcher_mandatory_cookie' );

	// Update the NoobRocket rules on the .htaccess file.
	flush_noobrocket_htaccess();

	// Regenerate the config file.
	noobrocket_generate_config_file();
}
add_action( 'activate_woocommerce-aelia-currencyswitcher/woocommerce-aelia-currencyswitcher.php', 'noobrocket_activate_aelia_currencyswitcher', 11 );

/**
 * Remove the Aelia Currency Switcher cookies when deactivating the plugin
 *
 * @since 2.7
 */
function noobrocket_deactivate_aelia_currencyswitcher() {
	remove_filter( 'noobrocket_htaccess_mod_rewrite', '__return_false', 64 );
	remove_filter( 'noobrocket_cache_dynamic_cookies', 'noobrocket_add_aelia_currencyswitcher_dynamic_cookies' );
	remove_filter( 'noobrocket_cache_mandatory_cookies', 'noobrocket_add_aelia_currencyswitcher_mandatory_cookie' );

	// Update the NoobRocket rules on the .htaccess file.
	flush_noobrocket_htaccess();

	// Regenerate the config file.
	noobrocket_generate_config_file();
}
add_action( 'deactivate_woocommerce-aelia-currencyswitcher/woocommerce-aelia-currencyswitcher.php', 'noobrocket_deactivate_aelia_currencyswitcher', 11 );

/**
 * Add the Aelia Currency Switcher cookies to the dynamic cookies list
 *
 * @since 2.7
 *
 * @param array $cookies Cookies to use for dynamic caching.
 * @return array Updated cookies list
 */
function noobrocket_add_aelia_currencyswitcher_dynamic_cookies( $cookies ) {
	$cookies[] = 'aelia_cs_recalculate_cart_totals';
	$cookies[] = 'aelia_cs_selected_currency';
	$cookies[] = 'aelia_customer_country';
	return $cookies;
}

/**
 * Add the Aelia Currency Switcher to the list of mandatory cookies before generating caching files
 *
 * @since 2.7
 *
 * @param array $cookies Mandatory cookies to serve the cache.
 * @return array Updated cookies list
 */
function noobrocket_add_aelia_currencyswitcher_mandatory_cookie( $cookies ) {
	$acs_options = get_option( 'wc_aelia_currency_switcher' );

	if ( ! empty( $acs_options['ipgeolocation_enabled'] ) ) {
		$cookies[] = 'aelia_cs_selected_currency';
	}

	return $cookies;
}
