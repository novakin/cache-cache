<?php

defined( 'ABSPATH' ) || exit;

if ( function_exists( 'print_link' ) ) :
	/**
	 * Conflict with WP-Print: don't apply LazyLoad on print pages
	 *
	 * @since 2.6.8
	 */
	function noobrocket_deactivate_lazyload_on_print_pages() {
		global $wp_query;

		if ( isset( $wp_query->query_vars['print'] ) ) {
			add_filter( 'do_noobrocket_lazyload', '__return_false' );
		}
	}
	add_action( 'wp', 'noobrocket_deactivate_lazyload_on_print_pages' );
endif;
