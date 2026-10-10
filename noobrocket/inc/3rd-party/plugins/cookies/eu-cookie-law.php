<?php

defined( 'ABSPATH' ) || exit;

/**
 * Compatibility with EU Cookie Law
 * https://wordpress.org/plugins/eu-cookie-law/
 *
 * @since 2.7
 */
if ( function_exists( 'eucookie_start' ) ) :
	add_filter( 'noobrocket_cache_mandatory_cookies', 'noobrocket_add_eu_cookie_law_mandatory_cookie' );

	/**
	 * Update .htaccess & config files when the "Activate" and "Autoblock" options are turned on
	 *
	 * @since 2.7
	 *
	 * @param Array $old_value Array of previous values.
	 * @param Array $value Array of submitted values.
	 */
	function noobrocket_after_update_eu_cookie_law_options( $old_value, $value ) {
		if ( ( isset( $old_value['enabled'], $value['enabled'] ) && ( $old_value['enabled'] === $value['enabled'] ) ) && isset( $old_value['autoblock'], $value['autoblock'] ) && $old_value['autoblock'] === $value['autoblock'] ) {
			return;
		}

		// Update the NoobRocket rules on the .htaccess file.
		flush_noobrocket_htaccess();

		// Update the config file.
		noobrocket_generate_config_file();
	}
	add_action( 'update_option_peadig_eucookie', 'noobrocket_after_update_eu_cookie_law_options', 10, 2 );

	// Don't add the NoobRocket rewrite rules to avoid issues.
	add_filter( 'noobrocket_htaccess_mod_rewrite', '__return_false', 58 );
endif;

/**
 * Add mandatory cookie when we activate the plugin
 *
 * @since 2.7
 */
function noobrocket_activate_eu_cookie_law() {
	add_filter( 'noobrocket_htaccess_mod_rewrite', '__return_false', 58 );
	add_filter( 'noobrocket_cache_mandatory_cookies', 'noobrocket_add_eu_cookie_law_mandatory_cookie' );

	// Update the NoobRocket rules on the .htaccess file.
	flush_noobrocket_htaccess();

	// Regenerate the config file.
	noobrocket_generate_config_file();
}
add_action( 'activate_eu-cookie-law/eu-cookie-law.php', 'noobrocket_activate_eu_cookie_law', 11 );

/**
 * Remove mandatory cookie when we deactivate the plugin
 *
 * @since 2.7
 */
function noobrocket_deactivate_eu_cookie_law() {
	remove_filter( 'noobrocket_htaccess_mod_rewrite', '__return_false', 58 );
	remove_filter( 'noobrocket_cache_mandatory_cookies', 'noobrocket_add_eu_cookie_law_mandatory_cookie' );

	// Update the NoobRocket rules on the .htaccess file.
	flush_noobrocket_htaccess();

	// Regenerate the config file.
	noobrocket_generate_config_file();
}
add_action( 'deactivate_eu-cookie-law/eu-cookie-law.php', 'noobrocket_deactivate_eu_cookie_law', 11 );

/**
 * Add the EU Cookie Law to the list of mandatory cookies before generating caching files.
 *
 * @since 2.7
 *
 * @param Array $cookies Array of mandatory cookies.
 * @return Array Updated array of mandatory cookies
 */
function noobrocket_add_eu_cookie_law_mandatory_cookie( $cookies ) {
	$options = get_option( 'peadig_eucookie' );

	if ( ! empty( $options['enabled'] ) && ! empty( $options['autoblock'] ) ) {
		$cookies['eu-cookie-law'] = 'euCookie';
	}

	return $cookies;
}
