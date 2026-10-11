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

/** Optional integration symbols; this file is analysis-only and never boots plugins. */
if ( ! defined( 'ICL_PLUGIN_URL' ) ) {
	define( 'ICL_PLUGIN_URL', 'https://wpml.example.test' );
}

// qTranslate 2.5.39 and qTranslate-X 3.4.6.8 publish these untyped APIs.
if ( ! function_exists( 'qtrans_isEnabled' ) ) {
	function qtrans_isEnabled( $lang ) {
		return false;
	}
}
if ( ! function_exists( 'qtranxf_isEnabled' ) ) {
	function qtranxf_isEnabled( $lang ) {
		return false;
	}
}
if ( ! function_exists( 'qtrans_convertURL' ) ) {
	function qtrans_convertURL( $url = '', $lang = '', $forceadmin = false ) {
		return $url;
	}
}
if ( ! function_exists( 'qtranxf_convertURL' ) ) {
	function qtranxf_convertURL( $url = '', $lang = '', $forceadmin = false, $showDefaultLanguage = false ) {
		return $url;
	}
}

// Polylang's public API and its WPML legacy compatibility API.
if ( ! function_exists( 'pll_home_url' ) ) {
	function pll_home_url( $lang = '' ) {
		return '';
	}
}
if ( ! function_exists( 'pll_languages_list' ) ) {
	function pll_languages_list( $args = [] ) {
		return [];
	}
}
if ( ! function_exists( 'pll_get_post_translations' ) ) {
	function pll_get_post_translations( $post_id ) {
		return [];
	}
}
if ( ! function_exists( 'icl_object_id' ) ) {
	function icl_object_id( $element_id, $element_type = 'post', $return_original_if_missing = false, $ulanguage_code = null ) {
		return null;
	}
}
