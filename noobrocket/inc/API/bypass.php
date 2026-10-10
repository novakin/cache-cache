<?php

defined( 'ABSPATH' ) || exit;

/**
 * Indicate to bypass rocket optimizations.
 *
 * Checks for "nonoobrocket" query string in the url to bypass rocket processes.
 *
 * @since 3.7
 *
 * @return bool True to indicate should bypass; false otherwise.
 */
function noobrocket_bypass() {
	static $bypass = null;

	if ( noobrocket_get_constant( 'NOOBROCKET_IS_TESTING', false ) ) {
		$bypass = null;
	}

	if ( ! is_null( $bypass ) ) {
		return $bypass;
	}

	$bypass = isset( $_GET['nonoobrocket'] ) && 0 !== $_GET['nonoobrocket']; // phpcs:ignore WordPress.Security.NonceVerification

	return $bypass;
}
