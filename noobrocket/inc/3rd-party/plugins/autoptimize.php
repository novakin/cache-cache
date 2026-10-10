<?php

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'autoptimizeCache' ) ) :
	/**
	 * Deactivate NoobRocket lazyload if Autoptimize lazyload is enabled
	 *
	 * @since 3.3.4
	 *
	 * @param string $old_value Previous autoptimize option value.
	 * @param string $value New autoptimize option value.
	 * @return void
	 */
	function noobrocket_maybe_deactivate_lazyload( $old_value, $value ) {
		if ( empty( $old_value['autoptimize_imgopt_checkbox_field_3'] ) && ! empty( $value['autoptimize_imgopt_checkbox_field_3'] ) ) {
			update_noobrocket_option( 'lazyload', 0 );
		}
	}
	add_action( 'update_option_autoptimize_imgopt_settings', 'noobrocket_maybe_deactivate_lazyload', 10, 2 );

	/**
	 * Improvement with Autoptimize: clear the cache when Autoptimize's cache is cleared
	 *
	 * @since 2.7
	 */
	add_action( 'autoptimize_action_cachepurged', 'noobrocket_clean_domain' );
endif;

if ( class_exists( 'autoptimizeConfig' ) ) :
	/**
	 * Deactivate NoobRocket CSS Minification if Autoptimize CSS minification is enabled
	 *
	 * @since 2.9.5
	 * @author Remy Perona
	 *
	 * @param string $old_value Previous autoptimize option value.
	 * @param string $value New autoptimize option value.
	 */
	function noobrocket_maybe_deactivate_minify_css( $old_value, $value ) {
		if ( $value !== $old_value && 'on' === $value ) {
			update_noobrocket_option( 'minify_css', 0 );
		}
	}
	add_action( 'update_option_autoptimize_css', 'noobrocket_maybe_deactivate_minify_css', 10, 2 );

	/**
	 * Deactivate NoobRocket JS Minification if Autoptimize JS minification is enabled
	 *
	 * @since 2.9.5
	 * @author Remy Perona
	 *
	 * @param string $old_value Previous autoptimize option value.
	 * @param string $value New autoptimize option value.
	 */
	function noobrocket_maybe_deactivate_minify_js( $old_value, $value ) {
		if ( $value !== $old_value && 'on' === $value ) {
			update_noobrocket_option( 'minify_js', 0 );
			update_noobrocket_option( 'minify_concatenate_js', 0 );
		}
	}
	add_action( 'update_option_autoptimize_js', 'noobrocket_maybe_deactivate_minify_js', 10, 2 );

	/**
	 * Deactivate NoobRocket async CSS if Autoptimize async CSS is enabled
	 *
	 * @since 2.10
	 * @author Remy Perona
	 *
	 * @param string $old_value Previous autoptimize option value.
	 * @param string $value New autoptimize option value.
	 */
	function noobrocket_maybe_deactivate_css_defer( $old_value, $value ) {
		if ( $value !== $old_value && 'on' === $value ) {
			update_noobrocket_option( 'autoptimize_css_defer', 0 );
		}
	}
	add_action( 'update_option_autoptimize_css_defer', 'noobrocket_maybe_deactivate_css_defer', 10, 2 );

endif;

/**
 * Disable NoobRocket minification options when activating Autoptimize and values are already in the database.
 *
 * @since 2.9.5
 * @author Remy Perona
 */
function noobrocket_activate_autoptimize() {
	if ( 'on' === get_option( 'autoptimize_css' ) ) {
		update_noobrocket_option( 'minify_css', 0 );
	}

	if ( 'on' === get_option( 'autoptimize_js' ) ) {
		update_noobrocket_option( 'minify_js', 0 );
		update_noobrocket_option( 'minify_concatenate_js', 0 );
	}

	if ( 'on' === get_option( 'autoptimize_css_defer' ) ) {
		update_noobrocket_option( 'async_css', 0 );
	}

	$lazyload = get_option( 'autoptimize_imgopt_settings' );

	if ( ! empty( $lazyload['autoptimize_imgopt_checkbox_field_3'] ) ) {
		update_noobrocket_option( 'lazyload', 0 );
	}
}
add_action( 'activate_autoptimize/autoptimize.php', 'noobrocket_activate_autoptimize', 11 );

/**
 * Disable NoobRocket minification if Autoptimize css /js minification is enabled.
 *
 * @param array $options NoobRocket options array.
 *
 * @since 3.18
 *
 * @return array
 */
function noobrocket_maybe_disable_minification( $options ) {
	// Bail early if plugin is not active.
	if ( ! is_plugin_active( 'autoptimize/autoptimize.php' ) ) {
		return $options;
	}

	if ( 'on' === get_option( 'autoptimize_css' ) ) {
		$options['minify_css'] = 0;
	}

	if ( 'on' === get_option( 'autoptimize_js' ) ) {
		$options['minify_js'] = 0;
	}

	return $options;
}

add_filter( 'noobrocket_first_install_options', 'noobrocket_maybe_disable_minification' );

/**
 * Disable NoobRocket lazyload fields if Autoptimize lazyload is enabled
 *
 * @since 3.3.4
 *
 * @return bool
 */
function noobrocket_maybe_disable_lazyload() {
	$lazyload = get_option( 'autoptimize_imgopt_settings' );

	if ( is_plugin_active( 'autoptimize/autoptimize.php' ) && ! empty( $lazyload['autoptimize_imgopt_checkbox_field_3'] ) ) {
		return true;
	}

	return false;
}

/**
 * Disable NoobRocket CSS minification field if Autoptimize CSS minification is enabled
 *
 * @since 2.9.5
 * @author Remy Perona
 *
 * @return bool|null True if it is active
 */
function noobrocket_maybe_disable_minify_css() {
	if ( is_plugin_active( 'autoptimize/autoptimize.php' ) && 'on' === get_option( 'autoptimize_css' ) ) {
		return true;
	}
}

/**
 * Disable NoobRocket JS minification field if Autoptimize JS minification is enabled
 *
 * @since 2.9.5
 * @author Remy Perona
 *
 * @return bool|null True if it is active
 */
function noobrocket_maybe_disable_minify_js() {
	if ( is_plugin_active( 'autoptimize/autoptimize.php' ) && 'on' === get_option( 'autoptimize_js' ) ) {
		return true;
	}
}

/**
 * Disable NoobRocket async CSS field if Autoptimize async CSS is enabled
 *
 * @since 2.10
 * @author Remy Perona
 *
 * @return bool|null True if it is active
 */
function noobrocket_maybe_disable_async_css() {
	if ( is_plugin_active( 'autoptimize/autoptimize.php' ) && 'on' === get_option( 'autoptimize_css_defer' ) ) {
		return true;
	}
}
