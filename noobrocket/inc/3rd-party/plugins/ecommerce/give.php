<?php

defined( 'ABSPATH' ) || exit;

/**
 * Exclude pages of Give plugin from cache.
 *
 * @since 2.7
 */
if ( defined( 'GIVE_VERSION' ) && function_exists( 'give_get_settings' ) ) {
	add_filter( 'noobrocket_cache_reject_uri', 'noobrocket_add_give_exclude_pages' );

	if ( is_admin() ) {
		add_action( 'update_option_give_settings', 'noobrocket_after_update_single_options', 10, 2 );
	}
}

/**
 * Add give pages to the excluded pages
 *
 * @since 2.7
 *
 * @param Array $urls Array of excluded pages.
 * @return Array Updated array of excluded pages
 */
function noobrocket_add_give_exclude_pages( $urls ) {
	$give_options = give_get_settings();
	$urls         = array_merge( $urls, get_noobrocket_i18n_translated_post_urls( $give_options['success_page'], 'page' ) );
	$urls         = array_merge( $urls, get_noobrocket_i18n_translated_post_urls( $give_options['history_page'], 'page' ) );
	$urls         = array_merge( $urls, get_noobrocket_i18n_translated_post_urls( $give_options['failure_page'], 'page' ) );

	return $urls;
}

/**
 * Add give pages to the excluded pages when activating the plugin
 *
 * @since 2.7
 */
function noobrocket_activate_give() {
	add_filter( 'noobrocket_cache_reject_uri', 'noobrocket_add_give_exclude_pages' );

	// Update the NoobRocket rules on the .htaccess.
	flush_noobrocket_htaccess();

	// Regenerate the config file.
	noobrocket_generate_config_file();
}
add_action( 'activate_give/give.php', 'noobrocket_activate_give', 11 );

/**
 * Remove give pages from the excluded pages when activating the plugin
 *
 * @since 2.7
 */
function noobrocket_remove_give_exclude_pages() {
	remove_filter( 'noobrocket_cache_reject_uri', 'noobrocket_add_give_exclude_pages' );

	// Update the NoobRocket rules on the .htaccess.
	flush_noobrocket_htaccess();

	// Regenerate the config file.
	noobrocket_generate_config_file();
}
add_action( 'deactivate_give/give.php', 'noobrocket_remove_give_exclude_pages', 11 );
