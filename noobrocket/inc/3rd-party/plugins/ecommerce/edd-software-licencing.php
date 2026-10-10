<?php

defined( 'ABSPATH' ) || exit;

/**
 * Compatibility with Easy Digital Downloads Software Licensing addon.
 *
 * @since 2.7
 */
if ( class_exists( 'EDD_Software_Licensing' ) && defined( 'EDD_SL_VERSION' ) ) :
	// Exclude EDD SL endpoint from cache on NoobRocket activation.
	add_filter( 'noobrocket_cache_reject_uri', 'noobrocket_exclude_edd_sl_endpoint' );
endif;

/**
 * Exclude EDD SL endpoint from cache when activating the plugin
 *
 * @since 2.7
 */
function noobrocket_activate_edd_software_licensing() {
	add_filter( 'noobrocket_cache_reject_uri', 'noobrocket_exclude_edd_sl_endpoint' );

	// Update the NoobRocket rules on the .htaccess file.
	flush_noobrocket_htaccess();

	// Regenerate the config file.
	noobrocket_generate_config_file();
}
add_action( 'activate_edd-software-licensing/edd-software-licenses.php', 'noobrocket_activate_edd_software_licensing', 11 );

/**
 * Remove exclusion of EDD SL endpoint from cache when deactivating the plugin
 *
 * @since 2.7
 */
function noobrocket_deactivate_edd_software_licensing() {
	remove_filter( 'noobrocket_cache_reject_uri', 'noobrocket_exclude_edd_sl_endpoint' );

	// Update the NoobRocket rules on the .htaccess file.
	flush_noobrocket_htaccess();

	// Regenerate the config file.
	noobrocket_generate_config_file();
}
add_action( 'deactivate_edd-software-licensing/edd-software-licenses.php', 'noobrocket_deactivate_edd_software_licensing', 11 );

/**
 * Exclude EDD SL endpoint from caching
 *
 * @since 2.7
 *
 * @param array $uri URLs to exclude from caching.
 * @return array Updated list of URLs to exclude
 */
function noobrocket_exclude_edd_sl_endpoint( $uri ) {
	$uri[] = '/edd-sl/(.*)';
	return $uri;
}
