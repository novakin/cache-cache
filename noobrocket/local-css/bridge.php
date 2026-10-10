<?php
/**
 * Trusted, same-server CLI bridge. This file never exposes a web endpoint.
 */
if ( 'cli' !== PHP_SAPI ) {
	http_response_code( 404 );
	exit;
}
$root = isset( $argv[1] ) ? realpath( $argv[1] ) : false;
if ( ! $root || ! is_file( $root . '/wp-load.php' ) ) {
	fwrite( STDERR, "Provide the absolute WordPress installation path.\n" );
	exit( 2 );
}
define( 'WP_ADMIN', true );
define( 'DOING_CRON', true );
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/';
$site_url = $argv[2] ?? '';
if ( $site_url ) {
	$site = filter_var( $site_url, FILTER_VALIDATE_URL ) ? parse_url( $site_url ) : false;
	if ( ! is_array( $site ) || ! in_array( $site['scheme'] ?? '', [ 'http', 'https' ], true ) || empty( $site['host'] ) || isset( $site['user'] ) || isset( $site['pass'] ) || isset( $site['query'] ) || isset( $site['fragment'] ) ) {
		fwrite( STDERR, "Provide an HTTP(S) site root without credentials, query or fragment.\n" );
		exit( 2 );
	}
	$_SERVER['HTTP_HOST'] = $site['host'] . ( isset( $site['port'] ) ? ':' . $site['port'] : '' );
	$_SERVER['SERVER_NAME'] = $site['host'];
	$_SERVER['SERVER_PORT'] = $site['port'] ?? ( 'https' === $site['scheme'] ? 443 : 80 );
	$_SERVER['HTTPS'] = 'https' === $site['scheme'] ? 'on' : 'off';
	$_SERVER['REQUEST_URI'] = rtrim( $site['path'] ?? '/', '/' ) . '/';
}
// Plugin and WordPress bootstrap output must not corrupt the bridge protocol.
ob_start();
require $root . '/wp-load.php';
ob_end_clean();
if ( $site_url ) {
	$canonical_site = static function ( $url ) {
		$parts = parse_url( $url );
		$scheme = strtolower( $parts['scheme'] ?? '' );
		$port = $parts['port'] ?? ( 'https' === $scheme ? 443 : 80 );
		return [ $scheme, strtolower( $parts['host'] ?? '' ), $port, rtrim( $parts['path'] ?? '', '/' ) ];
	};
	// An SSL request can otherwise upgrade a configured HTTP home during bootstrap.
	$home_scheme = (string) wp_parse_url( get_option( 'home' ), PHP_URL_SCHEME );
	if ( $canonical_site( $site_url ) !== $canonical_site( home_url( '/', $home_scheme ) ) ) {
		fwrite( STDERR, "Requested site URL does not match the selected WordPress site's home URL.\n" );
		exit( 2 );
	}
}
if ( ! defined( 'NOOBROCKET_FILE' ) || realpath( NOOBROCKET_FILE ) !== realpath( __DIR__ . '/../noobrocket.php' ) ) {
	fwrite( STDERR, "Activate this NoobRocket installation first.\n" );
	exit( 2 );
}
$command = json_decode( stream_get_contents( STDIN ), true );
if ( ! is_array( $command ) ) { exit( 2 ); }
try {
	$jobs = apply_filters( 'noobrocket_container', null )->get( 'local_css_jobs' );
	switch ( $command['action'] ?? '' ) {
		case 'worker_start':
			$result = [ 'ok' => true, 'worker' => $jobs->worker_start( (array) ( $command['runtime'] ?? [] ) ) ];
			break;
		case 'worker_update':
			$result = [ 'ok' => $jobs->worker_update( (string) ( $command['worker'] ?? '' ), (string) ( $command['state'] ?? '' ), (array) ( $command['runtime'] ?? [] ) ) ];
			break;
		case 'worker_stop':
			$result = [ 'ok' => $jobs->worker_stop( (string) ( $command['worker'] ?? '' ), (string) ( $command['state'] ?? 'stopped' ), (array) ( $command['runtime'] ?? [] ) ) ];
			break;
		case 'claim':
			if ( isset( $command['worker'] ) && ! $jobs->worker_update( (string) $command['worker'], 'idle', (array) ( $command['runtime'] ?? [] ) ) ) { throw new RuntimeException( 'Local CSS worker lease expired.' ); }
			$job = $jobs->claim();
			if ( $job && isset( $command['worker'] ) ) { $jobs->worker_update( (string) $command['worker'], 'running', [ 'job_id' => $job['id'] ] ); }
			$result = [ 'ok' => true, 'job' => $job, 'paused' => (bool) get_option( 'noobrocket_local_css_paused', false ) ];
			break;
		case 'complete':
			$result = [ 'ok' => $jobs->complete( (int) ( $command['id'] ?? 0 ), (string) ( $command['token'] ?? '' ), (string) ( $command['fingerprint'] ?? '' ), (array) ( $command['results'] ?? [] ) ) ];
			break;
		case 'fail':
			$result = [ 'ok' => $jobs->fail( (int) ( $command['id'] ?? 0 ), (string) ( $command['token'] ?? '' ), (string) ( $command['error'] ?? 'Generation failed.' ), (string) ( $command['category'] ?? 'transient' ) ) ];
			break;
		default:
			throw new RuntimeException( 'Unknown local CSS bridge action.' );
	}
	echo wp_json_encode( $result ), PHP_EOL;
	exit( $result['ok'] ? 0 : 1 );
} catch ( Throwable $error ) {
	fwrite( STDERR, $error->getMessage() . PHP_EOL );
	exit( 1 );
}
