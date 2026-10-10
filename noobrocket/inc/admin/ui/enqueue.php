<?php

defined( 'ABSPATH' ) || exit;

/**
 * Add the CSS and JS files for NoobRocket options page
 *
 * @since 1.0.0
 */
function noobrocket_add_admin_css_js() {
	$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
	// Keep local admin edits fresh without triggering plugin-version migrations.
	$settings_js_version = NOOBROCKET_VERSION . '.' . filemtime( NOOBROCKET_ASSETS_JS_PATH . 'noobrocket-settings' . $suffix . '.js' );
	$settings_css_version = NOOBROCKET_VERSION . '.' . filemtime( NOOBROCKET_ASSETS_PATH . 'css/noobrocket-settings' . $suffix . '.css' );
	$admin_css_version   = NOOBROCKET_VERSION . '.' . filemtime( NOOBROCKET_ASSETS_PATH . 'css/noobrocket-admin.css' );
	$admin_js_version    = NOOBROCKET_VERSION . '.' . filemtime( NOOBROCKET_ASSETS_JS_PATH . 'noobrocket-admin.js' );
	$appearance_version = NOOBROCKET_VERSION . '.' . filemtime( NOOBROCKET_ASSETS_JS_PATH . 'noobrocket-appearance.js' );

	wp_enqueue_style( 'noobrocket-settings', NOOBROCKET_ASSETS_CSS_URL . 'noobrocket-settings' . $suffix . '.css', null, $settings_css_version );
	wp_enqueue_script( 'micromodal', NOOBROCKET_ASSETS_JS_URL . 'micromodal.min.js', null, '0.4.10', true );
	wp_enqueue_script( 'noobrocket-settings', NOOBROCKET_ASSETS_JS_URL . 'noobrocket-settings' . $suffix . '.js', [ 'micromodal', 'wp-api-fetch', 'wp-polyfill', 'wp-url' ], $settings_js_version, true );

	wp_localize_script(
		'noobrocket-settings',
		'noobrocket_ajax_data',
		/**
		 * Filters the data passed to the localize script function for NoobRocket admin JS
		 *
		 * @since 3.7.4
		 *
		 * @param array $data Localize script data.
		 */
		apply_filters(
			'noobrocket_localize_admin_script',
			[
				'nonce'             => wp_create_nonce( 'noobrocket-ajax' ),
				'documentation_url' => get_noobrocket_documentation_url(),
			]
		)
	);

	if ( is_rtl() ) {
		wp_enqueue_style( 'noobrocket-settings-rtl', NOOBROCKET_ASSETS_CSS_URL . 'noobrocket-settings-rtl' . $suffix . '.css', null, NOOBROCKET_VERSION );
	}
	wp_enqueue_style( 'noobrocket-admin', NOOBROCKET_ASSETS_CSS_URL . 'noobrocket-admin.css', [ 'noobrocket-settings' ], $admin_css_version );
	// Apply the browser's display preference before the settings shell is rendered.
	wp_enqueue_script( 'noobrocket-appearance', NOOBROCKET_ASSETS_JS_URL . 'noobrocket-appearance.js', [], $appearance_version, false );
	wp_enqueue_script( 'noobrocket-admin', NOOBROCKET_ASSETS_JS_URL . 'noobrocket-admin.js', [], $admin_js_version, true );
}
add_action( 'admin_print_styles-settings_page_' . NOOBROCKET_PLUGIN_SLUG, 'noobrocket_add_admin_css_js' );

/**
 * Add the CSS and JS files needed by NoobRocket everywhere on admin pages
 *
 * @since 2.1
 */
function noobrocket_add_admin_css_js_everywhere() {
	$common_js_version = NOOBROCKET_VERSION . '.' . filemtime( NOOBROCKET_ASSETS_JS_PATH . 'noobrocket-settings-common.js' );
	wp_enqueue_script( 'noobrocket-settings-common', NOOBROCKET_ASSETS_JS_URL . 'noobrocket-settings-common.js', [ 'jquery' ], $common_js_version, true );
	wp_enqueue_style( 'noobrocket-settings-common', NOOBROCKET_ASSETS_CSS_URL . 'noobrocket-settings-common.css', [], NOOBROCKET_VERSION );
}
add_action( 'admin_enqueue_scripts', 'noobrocket_add_admin_css_js_everywhere', 11 );

/**
 * Adds mixpanel JS code in header when analytics data should be sent
 *
 * @since 2.11
 * @deprecated 3.19.2 Use NoobRocket\Engine\Tracking\Tracking class instead
 * @author Remy Perona
 */
function noobrocket_add_mixpanel_code() {
	_deprecated_function( __FUNCTION__, '3.19.2', 'NoobRocket\Engine\Tracking\Tracking' );

	// This functionality has been moved to the new Tracking system.
	// The Tracking class now handles Mixpanel script injection via the
	// inject_mixpanel_script() method.
}
// Remove the action hook - the new Tracking system handles this.


/**
 * Add CSS & JS files for the Imagify installation call to action
 *
 * @since 2.7
 */
function noobrocket_enqueue_modal_plugin() {
	$boxes = get_user_meta( get_current_user_id(), 'noobrocket_boxes', true );

	if ( defined( 'IMAGIFY_VERSION' ) || in_array( 'noobrocket_imagify_notice', (array) $boxes, true ) || 1 === get_option( 'noobrocket_dismiss_imagify_notice' ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	wp_enqueue_style( 'plugin-install' );

	wp_enqueue_script( 'plugin-install' );
	wp_enqueue_script( 'updates' );
	add_thickbox();
}
add_action( 'admin_print_styles-media-new.php', 'noobrocket_enqueue_modal_plugin' );
add_action( 'admin_print_styles-upload.php', 'noobrocket_enqueue_modal_plugin' );
add_action( 'admin_print_styles-settings_page_' . NOOBROCKET_PLUGIN_SLUG, 'noobrocket_enqueue_modal_plugin' );
