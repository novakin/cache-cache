<?php

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WpAppKit' ) ) :
	add_filter( 'noobrocket_cache_reject_uri', 'noobrocket_add_appkit_exclude_pages' );
endif;

/**
 * Add WP Appkit path to cache exclusion
 *
 * @since 2.9
 * @author Remy Perona
 *
 * @param array $urls URLs to exclude from cache.
 * @return array Updated URLs array
 */
function noobrocket_add_appkit_exclude_pages( $urls ) {
	$urls[] = '/wp-appkit-api/(.*)';

	return $urls;
}

/**
 * Add WP Appkit path to cache exclusion when activating the plugin
 *
 * @since 2.9
 * @author Remy Perona
 */
function noobrocket_activate_wp_appkit() {
	add_filter( 'noobrocket_cache_reject_uri', 'noobrocket_add_appkit_exclude_pages' );

	// Update the NoobRocket rules on the .htaccess.
	flush_noobrocket_htaccess();

	// Regenerate the config file.
	noobrocket_generate_config_file();
}
add_action( 'activate_wp-appkit/wp-appkit.php', 'noobrocket_activate_wp_appkit', 11 );

/**
 * Remove Appkit path from cache exclusion when deactivating the plugin
 *
 * @since 2.9
 * @author Remy Perona
 */
function noobrocket_deactivate_wp_appkit() {
	remove_filter( 'noobrocket_cache_reject_uri', 'noobrocket_add_appkit_exclude_pages' );

	// Update the NoobRocket rules on the .htaccess.
	flush_noobrocket_htaccess();

	// Regenerate the config file.
	noobrocket_generate_config_file();
}
add_action( 'deactivate_wp-appkit/wp-appkit.php', 'noobrocket_deactivate_wp_appkit', 11 );
