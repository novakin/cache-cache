<?php
/** Actual audit callback, modeled WordPress APIs and owned files; no HTTP or site bootstrap. */
$directory = sys_get_temp_dir() . '/cache-cache-http-audit-' . uniqid( '', true );
if ( ! mkdir( $directory, 0700 ) ) { throw new RuntimeException( 'Owned audit directory unavailable.' ); }
define( 'WP_CONTENT_DIR', $directory );
define( 'NOOBROCKET_CHECK_HTTP_ORIGIN', 'http://127.0.0.1:8875' );
$callbacks = [];
function add_filter( $hook, $callback ) { global $callbacks; $callbacks[ $hook ] = $callback; }
function wp_parse_url( $url ) { return parse_url( $url ); }
function wp_json_encode( $value ) { return json_encode( $value ); }
class WP_Error {
	public $code;
	public function __construct( $code, $message ) { $this->code = $code; }
}
require __DIR__ . '/wordpress-http-audit.php';
$checks = 0;
function audit_check( $condition ) { global $checks; if ( ! $condition ) { throw new RuntimeException( 'Audit boundary regression.' ); } ++$checks; }
try {
	$callback = $callbacks['pre_http_request'];
	foreach ( [ '', '/', '/owned.css', '/?owned=query' ] as $path ) {
		audit_check( false === $callback( false, [], NOOBROCKET_CHECK_HTTP_ORIGIN . $path ) );
	}
	audit_check( ! file_exists( $directory . '/http-audit-blocked.txt' ) );
	$denied = [ 'http://localhost:8875/', 'http://127.0.0.1:88750/', 'https://127.0.0.1:8875/', 'http://127.0.0.1:8875.evil.invalid/', 'http://127.0.0.1:8875@other.invalid/', 'http://other.invalid/' ];
	foreach ( $denied as $url ) {
		$result = $callback( false, [], $url );
		audit_check( $result instanceof WP_Error && 'local_test_remote_request' === $result->code );
	}
	audit_check( file_get_contents( $directory . '/http-audit-blocked.txt' ) === str_repeat( "blocked\n", count( $denied ) ) );
	$preempted = new stdClass();
	$before = file_get_contents( $directory . '/http-audit.jsonl' );
	audit_check( $preempted === $callback( $preempted, [], 'http://other.invalid/' ) );
	audit_check( $before === file_get_contents( $directory . '/http-audit.jsonl' ) );
	echo $checks, " audit boundary assertions passed; modeled WordPress, no HTTP.\n";
} finally {
	foreach ( [ 'http-audit.jsonl', 'http-audit-blocked.txt' ] as $name ) { if ( is_file( $directory . '/' . $name ) ) { unlink( $directory . '/' . $name ); } }
	rmdir( $directory );
}
