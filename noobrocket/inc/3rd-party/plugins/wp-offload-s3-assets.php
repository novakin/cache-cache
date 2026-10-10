<?php

defined( 'ABSPATH' ) || exit;

if ( is_admin() && function_exists( 'as3cf_assets_init' ) ) :
	add_action( 'aws_init', 'noobrocket_as3cf_assets_compatibility', 13 );
	add_action( 'update_option_as3cf_assets', 'noobrocket_maybe_deactivate_cdn', 10, 2 );
endif;

/**
 * Compatibility with WP Offload S3 assets addon.
 *
 * @since 2.10.7
 * @author Remy Perona
 */
function noobrocket_as3cf_assets_compatibility() {
	global $as3cf_assets;

	if ( isset( $as3cf_assets ) && $as3cf_assets->is_plugin_setup() && 1 === (int) $as3cf_assets->get_setting( 'enable-addon' ) ) {
			// Disable NoobRocket CDN option.
			add_filter( 'noobrocket_readonly_cdn_option', '__return_true' );
	}
}

/**
 * Deactivate NoobRocket CDN if WP Offload S3 assets addon copy & serve is active.
 *
 * @since 2.10.7
 * @author Remy Perona
 *
 * @param string $old_value Previous assets option value.
 * @param string $new_value New assets option value.
 */
function noobrocket_maybe_deactivate_cdn( $old_value, $new_value ) {
	if ( $old_value['enable-addon'] !== $new_value['enable-addon'] && 1 === (int) $new_value['enable-addon'] ) {
		update_noobrocket_option( 'cdn', 0 );
	}
}
