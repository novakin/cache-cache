<?php

defined( 'ABSPATH' ) || exit;

if ( is_admin() && ( function_exists( 'as3cf_init' ) || function_exists( 'as3cf_pro_init' ) ) ) :
	add_action( 'aws_init', 'noobrocket_as3cf_compatibility', 12 );
endif;


/**
 * Compatibility with WP Offload S3.
 *
 * @since 2.10.7
 * @author Remy Perona
 */
function noobrocket_as3cf_compatibility() {
	global $as3cf;

	if ( isset( $as3cf ) && $as3cf->is_plugin_setup() && 1 === (int) $as3cf->get_setting( 'serve-from-s3' ) ) {
			// Remove images option from NoobRocket CDN dropdown settings.
			add_filter( 'noobrocket_allow_cdn_images', '__return_false' );
	}
}
