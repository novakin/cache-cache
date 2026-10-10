<?php
/** Static-analysis values declared at runtime by noobrocket_init() in inc/main.php. */
if ( ! defined( 'NOOBROCKET_PLUGIN_NAME' ) ) {
	define( 'NOOBROCKET_PLUGIN_NAME', 'Cache-Cache' );
}
if ( ! defined( 'NOOBROCKET_PLUGIN_SLUG' ) ) {
	define( 'NOOBROCKET_PLUGIN_SLUG', 'noobrocket' );
}

/** Core declares these site-specific strings in wp_cookie_constants(); analysis never boots a site. */
if ( ! defined( 'COOKIEHASH' ) ) {
	define( 'COOKIEHASH', 'analysis-site' );
}
if ( ! defined( 'LOGGED_IN_COOKIE' ) ) {
	define( 'LOGGED_IN_COOKIE', 'wordpress_logged_in_' . COOKIEHASH );
}

/** Optional Polylang API used only after the integration is detected. */
if ( ! function_exists( 'pll_get_term_language' ) ) {
	/**
	 * @param int    $term_id Term identifier.
	 * @param string $field Language field.
	 * @return string|false
	 */
	function pll_get_term_language( $term_id, $field = 'slug' ) {
		return false;
	}
}
