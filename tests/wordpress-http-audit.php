<?php
/**
 * Test fixture only. Install as a must-use plugin in a disposable WordPress site.
 * Record destinations without query strings; allow local requests; block remote HTTP.
 */
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	$parts = wp_parse_url( $url );
	$host = strtolower( $parts['host'] ?? '' );
	$entry = [ 'phase' => getenv( 'NOOBROCKET_TEST_PHASE' ) ?: 'web', 'host' => $host, 'path' => $parts['path'] ?? '/' ];
	file_put_contents( WP_CONTENT_DIR . '/http-audit.jsonl', wp_json_encode( $entry ) . "\n", FILE_APPEND );
	if ( in_array( $host, [ 'localhost', '127.0.0.1', '::1' ], true ) ) {
		return $pre;
	}
	return new WP_Error( 'local_test_remote_request', 'External request blocked by isolated test fixture.' );
}, PHP_INT_MIN, 3 );
