<?php
/**
 * Compatibility with Premium SEO Pack
 *
 * @link http://premiumseopack.com
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'psp' ) ) {

	/**
	 * Dequeue the stylesheet of Premium SEO Pack on NoobRocket settings page.
	 *
	 * @since 2.11.6
	 * @author Arun Basil Lal
	 */
	function noobrocket_dequeue_premium_seo_pack_stylesheet() {

		// Return on all pages but NoobRocket settings page.
		$screen = get_current_screen();
		if ( 'settings_page_noobrocket' !== $screen->id ) {
			return;
		}

		// Dequeueing this stylesheet unfreezes NoobRocket.
		wp_dequeue_style( 'psp-main-style' );
	}
	add_action( 'admin_print_styles', 'noobrocket_dequeue_premium_seo_pack_stylesheet', 11 );
}
