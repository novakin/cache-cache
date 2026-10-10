<?php

defined( 'ABSPATH' ) || exit;

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
function noobrocket_flywheel_varnish_field( $settings ) {
	$settings['varnish_auto_purge']['title'] = sprintf(
		// Translators: %s = Hosting name.
		__( 'Your site is hosted on %s, we have enabled Varnish auto-purge for compatibility.', 'noobrocket' ),
		'Flywheel'
	);

	return $settings;
}
add_filter( 'noobrocket_varnish_field_settings', 'noobrocket_flywheel_varnish_field' );

add_filter( 'noobrocket_display_input_varnish_auto_purge', '__return_false' );

/**
 * Allow to purge Varnish on Flywheel websites
 *
 * @since 2.6.8
 */
add_filter( 'do_noobrocket_varnish_http_purge', '__return_true' );
add_filter( 'do_noobrocket_generate_caching_files', '__return_false' );

// Prevent mandatory cookies on hosting with server cache.
add_filter( 'noobrocket_cache_mandatory_cookies', '__return_empty_array', PHP_INT_MAX );

/**
 * Set up the right Varnish IP for Flywheel
 *
 * @since 2.6.8
 * @param array $varnish_ip Varnish IP.
 */
function noobrocket_varnish_ip_on_flywheel( $varnish_ip ) {
	$varnish_ip[] = '127.0.0.1';

	return $varnish_ip;
}
add_filter( 'noobrocket_varnish_ip', 'noobrocket_varnish_ip_on_flywheel' );

/**
 * Remove NoobRocket functions on WP core action hooks to prevent triggering a double cache clear.
 *
 * @since  3.3.1
 * @author Remy Perona
 *
 * @return void
 */
function noobrocket_flywheel_remove_partial_purge_hooks() {
	// WP core action hooks noobrocket_clean_post() gets hooked into.
	$clean_post_hooks = [
		// Disables the refreshing of partial cache when content is edited.
		'wp_trash_post',
		'delete_post',
		'clean_post_cache',
		'wp_update_comment_count',
	];

	// Remove noobrocket_clean_post() from core action hooks.
	array_map(
		function ( $hook ) {
			remove_action( $hook, 'noobrocket_clean_post' );
		},
		$clean_post_hooks
	);

	remove_filter( 'noobrocket_clean_files', 'noobrocket_clean_files_users' );
}
add_action( 'noobrocket_loaded', 'noobrocket_flywheel_remove_partial_purge_hooks' );
