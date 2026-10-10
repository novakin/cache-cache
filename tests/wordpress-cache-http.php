<?php
/**
 * Real HTTP cache delivery checks. Requires the disposable site server on port 8765.
 * php tests/wordpress-cache-http.php /absolute/path/to/disposable/wordpress
 */
$wordpress = isset( $argv[1] ) ? realpath( $argv[1] ) : false;
if ( ! $wordpress || ! is_file( $wordpress . '/wp-load.php' ) || ! is_file( $wordpress . '/.noobrocket-acceptance-site' ) ) {
	fwrite( STDERR, "Provide a disposable WordPress installation with the .noobrocket-acceptance-site marker.\n" );
	exit( 2 );
}
putenv( 'NOOBROCKET_TEST_PHASE=cache-http' );
$_SERVER['HTTP_HOST'] = '127.0.0.1:8765';
$_SERVER['REQUEST_URI'] = '/';
// Bypass early homepage delivery; actual HTTP fixture requests remain GET.
$_SERVER['REQUEST_METHOD'] = 'POST';
define( 'WP_ADMIN', true );
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
require $wordpress . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
wp_set_current_user( 1 );

function cache_http_check( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
	echo 'PASS: ', $message, PHP_EOL;
}
function cache_http_request( $url, array $headers = [], $decompress = true ) {
	$headers = array_merge( [ 'Accept-Encoding' => 'identity' ], $headers );
	if ( ! $decompress ) {
		// Bundled Requests decodes gzip unconditionally; the stream exposes wire bytes.
		$lines = [];
		foreach ( $headers as $name => $value ) { $lines[] = $name . ': ' . $value; }
		$context = stream_context_create( [ 'http' => [ 'method' => 'GET', 'header' => implode( "\r\n", $lines ), 'timeout' => 15, 'follow_location' => 0, 'ignore_errors' => true ] ] );
		$body = file_get_contents( $url, false, $context );
		if ( false === $body ) { throw new RuntimeException( 'Raw loopback HTTP request failed.' ); }
		$response = [ 'headers' => [], 'response' => [ 'code' => 0 ], 'body' => $body ];
		foreach ( $http_response_header as $line ) {
			if ( preg_match( '/^HTTP\/\S+ (\d+)/', $line, $match ) ) { $response['response']['code'] = (int) $match[1]; }
			elseif ( false !== strpos( $line, ':' ) ) {
				list( $name, $value ) = explode( ':', $line, 2 );
				$response['headers'][ strtolower( $name ) ] = trim( $value );
			}
		}
		return $response;
	}
	$result = wp_remote_get( $url, [
		'timeout' => 15,
		'redirection' => 0,
		'headers' => $headers,
	] );
	if ( is_wp_error( $result ) ) { throw new RuntimeException( $result->get_error_message() ); }
	return $result;
}
function cache_http_is_hit( $response ) {
	$status = wp_remote_retrieve_header( $response, 'cache-status' );
	return strpos( is_array( $status ) ? implode( ', ', $status ) : (string) $status, 'NoobRocket; hit' ) !== false;
}
function cache_http_child( $wordpress, array $arguments, $input = '' ) {
	$process = proc_open( array_merge( [ PHP_BINARY, __FILE__, $wordpress ], $arguments ), [ 0 => [ 'pipe', 'r' ], 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ], $pipes );
	if ( ! is_resource( $process ) ) { throw new RuntimeException( 'Could not start independent WordPress fixture.' ); }
	fwrite( $pipes[0], $input );
	fclose( $pipes[0] );
	$output = stream_get_contents( $pipes[1] );
	$errors = stream_get_contents( $pipes[2] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );
	if ( proc_close( $process ) !== 0 ) {
		throw new RuntimeException( 'Independent WordPress fixture failed: ' . trim( $errors ?: $output ) );
	}
	// The child may change options already cached in this parent request.
	wp_cache_delete( 'alloptions', 'options' );
	wp_cache_delete( 'noobrocket_settings', 'options' );
}

$home = wp_parse_url( home_url() );
if ( $home['host'] !== '127.0.0.1' || ( $home['port'] ?? 80 ) !== 8765 || $home['scheme'] !== 'http' ) {
	throw new RuntimeException( 'HTTP fixtures require http://127.0.0.1:8765.' );
}

if ( ( $argv[2] ?? '' ) === 'settings' ) {
	$settings = json_decode( stream_get_contents( STDIN ), true );
	if ( ! is_array( $settings ) ) { exit( 2 ); }
	update_option( 'noobrocket_settings', $settings );
	exit;
}

if ( ( $argv[2] ?? '' ) === 'update' ) {
	$post_id = (int) ( $argv[3] ?? 0 );
	if ( ! get_post_meta( $post_id, '_noobrocket_http_test', true ) ) { exit( 2 ); }
	$result = wp_update_post( [ 'ID' => $post_id, 'post_content' => 'NoobRocket HTTP cache updated content.' ], true );
	cache_http_check( ! is_wp_error( $result ), 'content update succeeds in a fresh WordPress request' );
	exit;
}

do_action( 'admin_init' );
$original = get_option( 'noobrocket_settings' );
$original_cron = get_option( 'cron' );
$post_id = 0;
$url = '';
$cache_dir = '';
$session_token = '';
$query = apply_filters( 'noobrocket_container', null )->get( 'preload_caches_query' );
try {
	$test_settings = array_merge( $original, [
		'cache_logged_user' => 0, 'cache_mobile' => 0, 'do_caching_mobile_files' => 0,
		'cache_webp' => 0, 'cache_ssl' => 1, 'manual_preload' => 0, 'cdn' => 0,
		'minify_css' => 0, 'minify_js' => 0, 'delay_js' => 0, 'defer_all_js' => 0,
	] );
	// Each save is a real request: drop-in generation is deduplicated per request.
	cache_http_child( $wordpress, [ 'settings' ], wp_json_encode( $test_settings ) );
	$test_settings['cache_mobile'] = 1;
	$test_settings['do_caching_mobile_files'] = 1;
	cache_http_child( $wordpress, [ 'settings' ], wp_json_encode( $test_settings ) );
	$post_id = wp_insert_post( [
		'post_type' => 'page', 'post_status' => 'publish',
		'post_title' => 'NoobRocket HTTP cache proof',
		'post_name' => 'noobrocket-http-proof-' . uniqid(),
		'post_content' => 'NoobRocket HTTP cache original content.',
	], true );
	cache_http_check( ! is_wp_error( $post_id ), 'disposable public page created' );
	update_post_meta( $post_id, '_noobrocket_http_test', 1 );
	$url = get_permalink( $post_id );
	$parts = wp_parse_url( $url );
	cache_http_check( empty( $parts['query'] ), 'fixture uses a cacheable permalink' );
	$cache_dir = NOOBROCKET_CACHE_PATH . $parts['host'] . strtolower( $parts['path'] );
	$cache_file = $cache_dir . 'index.html';
	$mobile_file = $cache_dir . 'index-mobile.html';

	$cold = cache_http_request( $url );
	cache_http_check( wp_remote_retrieve_response_code( $cold ) === 200 && ! cache_http_is_hit( $cold ), 'cold anonymous request renders through WordPress' );
	clearstatcache();
	cache_http_check( is_file( $cache_file ) && is_file( $cache_file . '_gzip' ), 'cold request writes plain and gzip cache files' );
	$hit = cache_http_request( $url );
	cache_http_check( wp_remote_retrieve_response_code( $hit ) === 200 && cache_http_is_hit( $hit ), 'warm anonymous request is served by the early cache' );
	cache_http_check( wp_remote_retrieve_body( $hit ) === file_get_contents( $cache_file ), 'warm response matches the stored HTML' );
	$modified = wp_remote_retrieve_header( $hit, 'last-modified' );
	$conditional = cache_http_request( $url, [ 'If-Modified-Since' => $modified ] );
	cache_http_check( $modified && wp_remote_retrieve_response_code( $conditional ) === 304 && cache_http_is_hit( $conditional ) && wp_remote_retrieve_body( $conditional ) === '', 'conditional cache hit returns 304 without a body' );
	$raw_gzip = cache_http_request( $url, [ 'Accept-Encoding' => 'gzip' ], false );
	cache_http_check( ! is_wp_error( $raw_gzip ) && 'gzip' === wp_remote_retrieve_header( $raw_gzip, 'content-encoding' ) && wp_remote_retrieve_body( $raw_gzip ) === file_get_contents( $cache_file . '_gzip' ), 'PHP sends the precompressed cache bytes directly on the wire' );
	cache_http_check( gzdecode( wp_remote_retrieve_body( $raw_gzip ) ) === wp_remote_retrieve_body( $hit ), 'precompressed response decodes to the exact cached HTML' );
	$vary = wp_remote_retrieve_header( $raw_gzip, 'vary' );
	cache_http_check( false !== stripos( is_array( $vary ) ? implode( ',', $vary ) : (string) $vary, 'Accept-Encoding' ), 'compression varies on accepted encoding' );
	$refused = cache_http_request( $url, [ 'Accept-Encoding' => 'gzip;q=0, *;q=1' ] );
	cache_http_check( cache_http_is_hit( $refused ) && ! wp_remote_retrieve_header( $refused, 'content-encoding' ) && wp_remote_retrieve_body( $refused ) === wp_remote_retrieve_body( $hit ), 'explicit gzip refusal overrides wildcard support without losing the plain cache hit' );
	// Remove the plain fallback while proving the gzip-file delivery branch.
	$plain_backup = $cache_file . '.http-fixture';
	if ( ! rename( $cache_file, $plain_backup ) ) { throw new RuntimeException( 'Could not isolate gzip fixture.' ); }
	try {
		$gzip = cache_http_request( $url, [ 'Accept-Encoding' => 'gzip' ] );
		cache_http_check( wp_remote_retrieve_response_code( $gzip ) === 200 && cache_http_is_hit( $gzip ) && wp_remote_retrieve_body( $gzip ) === wp_remote_retrieve_body( $hit ), 'gzip-only cache delivery preserves content and hit status' );
		$conditional_gzip = cache_http_request( $url, [ 'Accept-Encoding' => 'gzip', 'If-Modified-Since' => wp_remote_retrieve_header( $gzip, 'last-modified' ) ] );
		cache_http_check( wp_remote_retrieve_response_code( $conditional_gzip ) === 304 && cache_http_is_hit( $conditional_gzip ) && wp_remote_retrieve_body( $conditional_gzip ) === '', 'conditional gzip-only cache hit returns 304 without a body' );
	} finally {
		if ( ! rename( $plain_backup, $cache_file ) ) { throw new RuntimeException( 'Could not restore plain cache fixture.' ); }
	}

	$mobile_headers = [ 'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 9_1 like Mac OS X) AppleWebKit/601.1.46 Version/9.0 Mobile/13B143 Safari/601.1' ];
	$mobile = cache_http_request( $url, $mobile_headers );
	clearstatcache();
	cache_http_check( wp_remote_retrieve_response_code( $mobile ) === 200 && ! cache_http_is_hit( $mobile ) && is_file( $mobile_file ), 'first mobile request creates a separate mobile cache' );
	$mobile = cache_http_request( $url, $mobile_headers );
	cache_http_check( cache_http_is_hit( $mobile ) && wp_remote_retrieve_body( $mobile ) === file_get_contents( $mobile_file ), 'warm mobile request uses its own cached variant' );
	$bypass = cache_http_request( add_query_arg( 'noobrocket_http_unknown', '1', $url ) );
	cache_http_check( wp_remote_retrieve_response_code( $bypass ) === 200 && ! cache_http_is_hit( $bypass ), 'unknown query string bypasses shared page cache' );
	$session_token = WP_Session_Tokens::get_instance( 1 )->create( time() + 600 );
	$cookie = LOGGED_IN_COOKIE . '=' . wp_generate_auth_cookie( 1, time() + 600, 'logged_in', $session_token );
	$private = cache_http_request( $url, [ 'Cookie' => $cookie ] );
	cache_http_check( wp_remote_retrieve_response_code( $private ) === 200 && ! cache_http_is_hit( $private ) && strpos( wp_remote_retrieve_body( $private ), 'id="wpadminbar"' ) !== false, 'authenticated request bypasses anonymous cache' );
	cache_http_check( wp_remote_retrieve_body( cache_http_request( $url ) ) === wp_remote_retrieve_body( $hit ), 'authenticated request leaves anonymous cache unchanged' );

	// Post changes need a fresh request: normal purge handlers deduplicate within each request.
	cache_http_child( $wordpress, [ 'update', (string) $post_id ] );
	cache_http_check( true, 'content update runs in an independent WordPress request' );
	clearstatcache();
	cache_http_check( ! is_file( $cache_file ) && ! is_file( $mobile_file ), 'content update invalidates desktop and mobile cache' );
	$fresh = cache_http_request( $url );
	cache_http_check( ! cache_http_is_hit( $fresh ) && strpos( wp_remote_retrieve_body( $fresh ), 'NoobRocket HTTP cache updated content.' ) !== false, 'next response renders updated content' );
	cache_http_check( cache_http_is_hit( cache_http_request( $url ) ), 'updated page becomes an early cache hit' );

	foreach ( glob( $cache_dir . 'index*.html*' ) as $file ) { touch( $file, time() - 7200 ); }
	$only_fixture = function () use ( $url ) { return [ $url ]; };
	add_filter( 'noobrocket_automatic_cache_purge_urls', $only_fixture );
	try {
		( new NoobRocket\Engine\Cache\PurgeExpired\PurgeExpiredCache( NOOBROCKET_CACHE_PATH ) )->purge_expired_files( 3600 );
	} finally {
		remove_filter( 'noobrocket_automatic_cache_purge_urls', $only_fixture );
	}
	clearstatcache();
	cache_http_check( ! is_file( $cache_file ), 'expired cached files are purged' );
	cache_http_check( ! cache_http_is_hit( cache_http_request( $url ) ) && cache_http_is_hit( cache_http_request( $url ) ), 'expired page is regenerated and becomes warm again' );
} finally {
	if ( $session_token ) { WP_Session_Tokens::get_instance( 1 )->destroy( $session_token ); }
	if ( $post_id && ! is_wp_error( $post_id ) ) { wp_delete_post( $post_id, true ); }
	if ( $url ) { $query->delete_by_url( $url ); }
	if ( $cache_dir && is_dir( $cache_dir ) ) {
		// This unique fixture URL owns only these files; never recursively remove shared cache.
		foreach ( new DirectoryIterator( $cache_dir ) as $file ) {
			if ( $file->isFile() ) { unlink( $file->getPathname() ); }
		}
		if ( count( scandir( $cache_dir ) ) === 2 ) { rmdir( $cache_dir ); }
	}
	cache_http_child( $wordpress, [ 'settings' ], wp_json_encode( $original ) );
	update_option( 'cron', $original_cron );
}
