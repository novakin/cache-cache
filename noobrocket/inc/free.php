<?php
/**
 * NoobRocket's free, local edition policy and cache ownership checks.
 */
defined( 'ABSPATH' ) || exit;

/**
 * Normalize the local CSS safelist without treating entries as regular expressions.
 *
 * @param mixed $value Selector substrings, as an array or one entry per line.
 * @return string[]
 */
function noobrocket_sanitize_local_css_safelist( $value ) {
	if ( is_string( $value ) ) {
		$value = [ $value ];
	}
	if ( ! is_array( $value ) ) {
		return [];
	}

	$selectors = [];
	foreach ( $value as $entry ) {
		if ( ! is_string( $entry ) ) {
			continue;
		}
		foreach ( preg_split( '/\r\n|\r|\n/', $entry ) as $line ) {
			$line = trim( sanitize_text_field( $line ) );
			if ( '' !== $line ) {
				$selectors[] = $line;
			}
		}
	}

	return array_values( array_unique( $selectors ) );
}

/**
 * Normalize opt-in prerender settings across saves, imports and stored options.
 *
 * @param array $options Plugin settings.
 * @return array Settings with a strict boolean and a deduplicated URI pattern list.
 */
function noobrocket_sanitize_prerender_options( array $options ): array {
	$options['prerender_links'] = in_array( $options['prerender_links'] ?? 0, [ true, 1, '1' ], true ) ? 1 : 0;
	$options['prerender_links_mode'] = in_array( $options['prerender_links_mode'] ?? null, [ 'intent', 'document' ], true ) ? $options['prerender_links_mode'] : 'intent';
	$options['prerender_links_eagerness'] = in_array( $options['prerender_links_eagerness'] ?? null, [ 'conservative', 'moderate', 'eager' ], true ) ? $options['prerender_links_eagerness'] : 'conservative';
	$value = $options['prerender_excluded_uri'] ?? [];
	if ( is_string( $value ) ) {
		$value = [ $value ];
	}
	$value = is_array( $value ) ? $value : [];
	$excluded = [];
	foreach ( $value as $entry ) {
		if ( ! is_string( $entry ) ) {
			continue;
		}
		foreach ( preg_split( '/\r\n|\r|\n/', $entry ) as $line ) {
			$line = trim( sanitize_text_field( $line ) );
			if ( '' !== $line ) {
				$excluded[] = $line;
			}
		}
	}
	$options['prerender_excluded_uri'] = array_values( array_unique( $excluded ) );
	return $options;
}

/**
 * Imported or previously saved options cannot enable unsupported cloud services.
 * Local CDN rewriting uses the site's own CNAMEs.
 *
 * @param mixed $options Stored options.
 * @return mixed
 */
function noobrocket_free_settings( $options ) {
	if ( ! is_array( $options ) ) {
		return $options;
	}
	$options = noobrocket_sanitize_prerender_options( $options );

	$options['local_css_mode']       = isset( $options['local_css_mode'] ) && in_array( $options['local_css_mode'], [ 'off', 'critical', 'used' ], true ) ? $options['local_css_mode'] : 'off';
	$options['local_css_safelist']   = noobrocket_sanitize_local_css_safelist( $options['local_css_safelist'] ?? [] );
	$options['image_delivery']      = in_array( $options['image_delivery'] ?? 0, [ true, 1, '1' ], true ) ? 1 : 0;
	$options['cache_stale_refresh'] = in_array( $options['cache_stale_refresh'] ?? 0, [ true, 1, '1' ], true ) ? 1 : 0;
	if ( $options['image_delivery'] ) {
		$options['cache_webp'] = 0;
	}
	$options['optimize_css_delivery'] = 0;
	$options['remove_unused_css']    = 0;
	$options['async_css']            = 0;
	$options['performance_monitoring'] = 0;
	$options['analytics_enabled']    = 0;
	$options['cdn_type']             = 'byocdn';
	$options['cdn_state']            = empty( $options['cdn'] ) ? 'nothing' : 'byocdn';
	unset( $options['consumer_key'], $options['consumer_email'], $options['secret_key'], $options['license'] );

	return $options;
}
add_filter( 'option_noobrocket_settings', 'noobrocket_free_settings' );
add_filter( 'pre_update_option_noobrocket_settings', 'noobrocket_free_settings' );
add_filter( 'pre_add_option_noobrocket_settings', 'noobrocket_free_settings' );
add_filter( 'noobrocket_first_install_options', 'noobrocket_free_settings' );

/**
 * WordPress has one advanced-cache.php slot. Never replace another owner's file.
 *
 * @return bool
 */
function noobrocket_can_manage_advanced_cache( $allowed = true ) {
	if ( ! $allowed ) {
		return false;
	}
	$file = WP_CONTENT_DIR . '/advanced-cache.php';
	if ( ! file_exists( $file ) ) {
		return true;
	}
	$content = is_readable( $file ) ? file_get_contents( $file ) : false;
	return '' === $content || ( false !== $content && 1 === preg_match( '/^\s*define\s*\(\s*([\'\"])NOOBROCKET_ADVANCED_CACHE\1\s*,\s*true\s*\)\s*;/m', $content ) );
}
add_filter( 'noobrocket_generate_advanced_cache_file', 'noobrocket_can_manage_advanced_cache' );
add_filter( 'noobrocket_set_wp_cache_constant', 'noobrocket_can_manage_advanced_cache' );

/**
 * Check cache ownership before activation changes configuration or creates files.
 */
function noobrocket_activation_preflight() {
	if ( ! noobrocket_can_manage_advanced_cache() ) {
		wp_die( esc_html__( 'Another cache plugin owns advanced-cache.php. Deactivate that plugin before activating Cache-Cache.', 'noobrocket' ) );
	}
}
register_activation_hook( NOOBROCKET_FILE, 'noobrocket_activation_preflight' );
