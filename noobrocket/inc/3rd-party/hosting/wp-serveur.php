<?php

defined( 'ABSPATH' ) || exit;

/**
 * Allow to purge Varnish on WP Serveur websites.
 *
 * @since 2.6.11
 */
add_filter( 'do_noobrocket_varnish_http_purge', '__return_true' );
// Prevent mandatory cookies on hosting with server cache.
add_filter( 'noobrocket_cache_mandatory_cookies', '__return_empty_array', PHP_INT_MAX );

/**
 * Changes the text on the Varnish one-click block.
 *
 * @since  3.0
 * @author Remy Perona
 *
 * @param array $settings Field settings data.
 *
 * @return array modified field settings data.
 */
function noobrocket_wpserveur_varnish_field( $settings ) {
	$settings['varnish_auto_purge']['title'] = sprintf(
		// Translators: %s = Hosting name.
		__( 'Your site is hosted on %s, we have enabled Varnish auto-purge for compatibility.', 'noobrocket' ),
		'WP Serveur'
	);

	return $settings;
}
add_filter( 'noobrocket_varnish_field_settings', 'noobrocket_wpserveur_varnish_field' );

add_filter( 'noobrocket_display_input_varnish_auto_purge', '__return_false' );
