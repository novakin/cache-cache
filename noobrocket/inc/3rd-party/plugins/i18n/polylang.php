<?php

defined( 'ABSPATH' ) || exit;

if ( defined( 'POLYLANG_VERSION' ) && POLYLANG_VERSION ) :
	/**
	 * Conflict with Polylang: Clear the whole cache when the "The language is set from content" option is activated.
	 *
	 * @since 2.6.8
	 */
	function noobrocket_force_clean_domain_on_polylang() {
		$pll = function_exists( 'PLL' ) ? PLL() : $GLOBALS['polylang'];

		if ( isset( $pll ) && 0 === $pll->options['force_lang'] ) {
			noobrocket_clean_cache_dir();
		}
	}
	add_action( 'noobrocket_after_clean_domain', 'noobrocket_force_clean_domain_on_polylang' );

	// Filter mandatory cookies and NoobRocket rewrite rules if Polylang module 'Detect browser language' is enabled.
	if ( function_exists( 'PLL' ) && PLL()->options['browser'] ) {

		// Add Polylang's language cookie as a mandatory cookie.
		add_filter( 'noobrocket_cache_mandatory_cookies', 'noobrocket_add_polylang_mandatory_cookie' );

		// Remove NoobRocket rewrite rules from .htaccess file.
		add_filter( 'noobrocket_htaccess_mod_rewrite', '__return_false', 74 );
	}
endif;

/**
 * Add Polylang's language cookie to the mandatory cookies of NoobRocket.
 *
 * Polylang saves the users preferred language in this cookie by detecting browser language or by user choice
 * Adding this as a mandatory cookie prevents NoobRocket from serving the cache when the cookie is not set.
 *
 * @param array $cookies Array with mandatory cookies.
 * @return (array) Array of mandatory cookies with the Polylang cookie appended
 *
 * @author Arun Basil Lal
 * @since 3.0.5
 */
function noobrocket_add_polylang_mandatory_cookie( $cookies ) {
	$cookies[] = defined( 'PLL_COOKIE' ) ? PLL_COOKIE : 'pll_language';

	return $cookies;
}

/**
 * Add mandatory cookie to NoobRocket config and remove rewrite rules from .htaccess on Polylang activation.
 *
 * Add mandatory cookie only if the Polylang module 'Detect browser language' is active.
 * Also purge the homepage cache.
 *
 * @author Arun Basil Lal
 * @since 3.0.5
 */
function noobrocket_activate_polylang() {
	// Read Polylang settings from db.
	$polylang_settings = get_option( 'polylang' );

	if ( isset( $polylang_settings['browser'] ) && ( 1 === (int) $polylang_settings['browser'] ) ) {
		// Add Polylang's language cookie as a mandatory cookie.
		add_filter( 'noobrocket_cache_mandatory_cookies', 'noobrocket_add_polylang_mandatory_cookie' );

		// Remove NoobRocket rewrite rules from .htaccess file.
		add_filter( 'noobrocket_htaccess_mod_rewrite', '__return_false', 74 );

		// Regenerate the config file.
		noobrocket_generate_config_file();

		// Regenerate .htaccess file.
		flush_noobrocket_htaccess();

		// Purge homepage cache.
		noobrocket_clean_home();
	}
}
add_action( 'activate_polylang/polylang.php', 'noobrocket_activate_polylang', 11 );

/**
 * Remove mandatory cookie and add rewrite rules back to .htaccess when Polylang is deactivated.
 *
 * @author Arun Basil Lal
 * @since 3.0.5
 */
function noobrocket_deactivate_polylang() {
	// Remove Polylang's language cookie as a mandatory cookie.
	remove_filter( 'noobrocket_cache_mandatory_cookies', 'noobrocket_add_polylang_mandatory_cookie' );

	// Add back NoobRocket rewrite rules from .htaccess file.
	remove_filter( 'noobrocket_htaccess_mod_rewrite', '__return_false', 74 );

	// Regenerate the config file.
	noobrocket_generate_config_file();

	// Regenerate .htaccess file.
	flush_noobrocket_htaccess();
}
add_action( 'deactivate_polylang/polylang.php', 'noobrocket_deactivate_polylang', 11 );

/**
 * Update mandatory cookie in NoobRocket config file and remove rewrite rules from .htaccess
 * when Detect browser language module is enabled / disabled.
 *
 * @param array $value Array containing Polylang settings before its written to db.
 * @return array
 *
 * @author Arun Basil Lal
 * @since 3.0.5
 */
function noobrocket_detect_browser_language_status_change( $value ) {
	if ( function_exists( 'PLL' ) && PLL()->options['browser'] ) {

		// Add Polylang's language cookie as a mandatory cookie.
		add_filter( 'noobrocket_cache_mandatory_cookies', 'noobrocket_add_polylang_mandatory_cookie' );

		// Remove NoobRocket rewrite rules from .htaccess file.
		add_filter( 'noobrocket_htaccess_mod_rewrite', '__return_false', 74 );

		// Regenerate the config file.
		noobrocket_generate_config_file();

		// Regenerate .htaccess file.
		flush_noobrocket_htaccess();

		// Purge homepage cache.
		noobrocket_clean_home();
	} else {
		// Remove Polylang's language cookie as a mandatory cookie.
		remove_filter( 'noobrocket_cache_mandatory_cookies', 'noobrocket_add_polylang_mandatory_cookie' );

		// Add back NoobRocket rewrite rules from .htaccess file.
		remove_filter( 'noobrocket_htaccess_mod_rewrite', '__return_false', 74 );

		// Regenerate the config file.
		noobrocket_generate_config_file();

		// Regenerate .htaccess file.
		flush_noobrocket_htaccess();
	}

	return $value;
}
add_filter( 'pre_update_option_polylang', 'noobrocket_detect_browser_language_status_change' );
