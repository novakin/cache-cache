<?php
/**
 * Compatibility with GDPR by Trew Knowledge
 *
 * @link https://github.com/trewknowledge/GDPR/
 * @link https://wordpress.org/plugins/gdpr/
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'GDPR' ) ) {
	add_filter( 'noobrocket_htaccess_mod_rewrite', '__return_false', 60 );
	// Create cache version based on value set in gdpr[] cookies.
	add_filter( 'noobrocket_cache_dynamic_cookies', 'noobrocket_get_gdpr_dynamic_cookies' );
}

/**
 * Return the cookie names set by GDPR plugin
 *
 * @since 3.1.4
 * @author jorditarrida
 *
 * @param array $cookies array List of dynamic cookies.
 * @return array List of dynamic cookies with the GDPR cookie appended
 */
function noobrocket_get_gdpr_mandatory_cookies( $cookies ) {
	$cookies[] = 'allowed_cookies';
	$cookies[] = 'consent_types';
	return $cookies;
}

/**
 * Return the cookie names set by GDPR plugin
 *
 * @since 3.1.4
 * @author jorditarrida
 *
 * @param array $cookies array List of dynamic cookies.
 * @return array List of dynamic cookies with the GDPR cookie appended
 */
function noobrocket_get_gdpr_dynamic_cookies( $cookies ) {
	$cookies['gdpr'] = [
		'allowed_cookies',
		'consent_types',
	];
	return $cookies;
}

/**
 * Add dynamic cookie when GDPR plugin is activated
 *
 * @since 3.1.4
 * @author jorditarrida
 */
function noobrocket_add_gdpr_mandatory_cookies() {
	add_filter( 'noobrocket_htaccess_mod_rewrite', '__return_false', 60 );

	// Create cache version based on value set in GDPR cookies.
	add_filter( 'noobrocket_cache_dynamic_cookies', 'noobrocket_get_gdpr_dynamic_cookies' );
	// Update the NoobRocket rules on the .htaccess file.
	flush_noobrocket_htaccess();
	// Regenerate the config file.
	noobrocket_generate_config_file();
	// Clear NoobRocket cache.
	noobrocket_clean_domain();
}
add_action( 'activate_gdpr/gdpr.php', 'noobrocket_add_gdpr_mandatory_cookies', 11 );

/**
 * Remove dynamic cookie when GDPR plugin is deactivated.
 *
 * @since 3.1.4
 * @author jorditarrida
 */
function noobrocket_remove_gdpr_mandatory_cookies() {
	remove_filter( 'noobrocket_htaccess_mod_rewrite', '__return_false', 60 );

	// Delete the dynamic cookie filter.
	remove_filter( 'noobrocket_cache_dynamic_cookies', 'noobrocket_get_gdpr_dynamic_cookies' );
	// Update the NoobRocket rules on the .htaccess file.
	flush_noobrocket_htaccess();
	// Regenerate the config file.
	noobrocket_generate_config_file();
	// Clear NoobRocket cache.
	noobrocket_clean_domain();
}
add_action( 'deactivate_gdpr/gdpr.php', 'noobrocket_remove_gdpr_mandatory_cookies', 11 );
