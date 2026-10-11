<?php
/**
 * Test fixture only. Install as a must-use plugin in a disposable WordPress site.
 * Record destinations without query strings; allow local requests; block remote HTTP.
 */
add_filter( 'action_scheduler_allow_async_request_runner', '__return_false' );
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	if ( false !== $pre ) {
		return $pre;
	}
	$parts = wp_parse_url( $url );
	$host = strtolower( $parts['host'] ?? '' );
	$entry = [ 'phase' => getenv( 'NOOBROCKET_TEST_PHASE' ) ?: 'web', 'host' => $host, 'path' => $parts['path'] ?? '/' ];
	$allowed = defined( 'NOOBROCKET_CHECK_HTTP_ORIGIN' )
		? NOOBROCKET_CHECK_HTTP_ORIGIN && ( $url === NOOBROCKET_CHECK_HTTP_ORIGIN || strpos( $url, NOOBROCKET_CHECK_HTTP_ORIGIN . '/' ) === 0 )
		: in_array( $host, [ 'localhost', '127.0.0.1', '::1' ], true );
	$entry['blocked'] = ! $allowed;
	if ( ! $allowed ) {
		$entry['origin_kind'] = in_array( $host, [ 'localhost', '127.0.0.1', '::1' ], true ) ? 'loopback' : ( 'api.wordpress.org' === $host ? 'wordpress-api' : 'other' );
		$entry['scheme'] = in_array( $parts['scheme'] ?? '', [ 'http', 'https' ], true ) ? $parts['scheme'] : 'other';
		$entry['port'] = $parts['port'] ?? ( 'https' === $entry['scheme'] ? 443 : 80 );
		$entry['owned_origin_ready'] = defined( 'NOOBROCKET_CHECK_HTTP_ORIGIN' ) && (bool) NOOBROCKET_CHECK_HTTP_ORIGIN;
		$entry['caller_functions'] = array_values( array_filter( array_column( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 20 ), 'function' ), static function ( $name ) { return (bool) preg_match( '/^[A-Za-z_][A-Za-z0-9_]*$/D', $name ); } ) );
	}
	file_put_contents( WP_CONTENT_DIR . '/http-audit.jsonl', wp_json_encode( $entry ) . "\n", FILE_APPEND );
	if ( $allowed ) {
		return $pre;
	}
	$receipt = defined( 'NOOBROCKET_CHECK_HTTP_RECEIPT' ) ? NOOBROCKET_CHECK_HTTP_RECEIPT : WP_CONTENT_DIR . '/http-audit-blocked.txt';
	if ( 8 !== file_put_contents( $receipt, "blocked\n", FILE_APPEND | LOCK_EX ) ) {
		throw new RuntimeException( 'Cannot record blocked fixture HTTP.' );
	}
	return new WP_Error( 'local_test_remote_request', 'External request blocked by isolated test fixture.' );
}, PHP_INT_MAX, 3 );
