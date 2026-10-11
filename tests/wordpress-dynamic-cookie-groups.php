<?php
/** Native policy generation and distinct owned cookie cache variants; no third-party cookie plugin. */
$wordpress = isset( $argv[1] ) ? realpath( $argv[1] ) : false;
if ( PHP_SAPI !== 'cli' || ! $wordpress || ! is_file( $wordpress . '/.noobrocket-acceptance-site' ) ) { throw new RuntimeException( 'Provide a marked disposable WordPress installation.' ); }
$owner = '/noobrocket/inc/functions/options.php';
if ( hash_file( 'sha256', dirname( __DIR__ ) . $owner ) !== hash_file( 'sha256', $wordpress . '/wp-content/plugins' . $owner ) ) { throw new RuntimeException( 'Installed cookie owner differs from candidate.' ); }
define( 'WP_ADMIN', true );
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
$reader = isset( $argv[2] ) && 'read-variant' === $argv[2];
$input = $reader ? json_decode( base64_decode( $argv[3] ), true ) : null;
$_SERVER['HTTP_HOST'] = '127.0.0.1';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'POST';
if ( $reader ) { $_SERVER = array_merge( $_SERVER, $input['server'] ); $_COOKIE = $input['cookies']; }
$completed = false;
set_exception_handler( static function ( $error ) { fwrite( STDERR, 'FAIL native cookie fixture: ' . $error->getMessage() . "\n" ); exit( 2 ); } );
register_shutdown_function( static function () use ( &$completed ) { if ( ! $completed ) { fwrite( STDERR, "FAIL: native cookie fixture did not complete.\n" ); exit( 2 ); } } );
require_once $wordpress . '/wp-includes/plugin.php';
// A request retains its initial early configuration; generate then initialize this reader once.
add_filter( 'enable_loading_advanced_cache_dropin', static function () { return false; } );
require $wordpress . '/wp-load.php';
if ( $reader ) {
	$config = new NoobRocket\Buffer\Config( [ 'config_dir_path' => NOOBROCKET_CONFIG_PATH ] );
	$tests = new NoobRocket\Buffer\Tests( $config );
	$cache = new NoobRocket\Buffer\Cache( $tests, $config, [ 'cache_dir_path' => NOOBROCKET_CACHE_PATH ] );
	$completed = true;
	echo json_encode( [ 'groups' => $config->get_config( 'cache_dynamic_cookies' ), 'path' => $cache->get_cache_path() ] );
	exit;
}
$checks = 0;
$check = static function ( $condition, $message ) use ( &$checks ) { if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); } ++$checks; };
$name = 'noobrocket-cookie-' . bin2hex( random_bytes( 6 ) );
$definitions = [ 'session_id', 'geo' => [ 'country' ], 'prefs' => [ 'currency' ], 'also_geo' => [ 'country' ], 'session_id' ];
$expected = [ 0 => 'session_id', 'geo' => [ 'country' ], 'prefs' => [ 'currency' ], 'also_geo' => [ 'country' ] ];
$filter = static function () use ( $definitions ) { return $definitions; };
$saved_cookies = $_COOKIE;
$backups = [];
$owned = null;
add_filter( 'noobrocket_cache_dynamic_cookies', $filter, PHP_INT_MAX );
try {
	$check( $expected === get_noobrocket_cache_dynamic_cookies(), 'native filter preserves each named group and deduplicates scalar cookie' );
	list( $paths, $policy ) = get_noobrocket_config_file();
	$check( ! empty( $paths ) && '' !== $policy, 'native plugin settings exist for generated policy' );
	foreach ( $paths as $path ) {
		$parent = realpath( dirname( $path ) );
		$check( $parent === realpath( NOOBROCKET_CONFIG_PATH ), 'generated policy belongs to configured native directory' );
		$backups[ $path ] = is_file( $path ) ? file_get_contents( $path ) : null;
		$check( false !== $backups[ $path ], 'existing native policy bytes are readable before replacement' );
		$check( false !== file_put_contents( $path, $policy ), 'native executable cookie policy is written' );
	}
	$host = parse_url( home_url(), PHP_URL_HOST );
	$port = parse_url( home_url(), PHP_URL_PORT );
	$host .= $port ? ':' . $port : '';
	$server = [ 'HTTP_HOST' => $host, 'REQUEST_URI' => '/' . $name . '/', 'REQUEST_METHOD' => 'GET', 'HTTPS' => 'on' ];
	// Config retains its initial request policy; each changed cookie is a fresh native request.
	$filename = static function ( $cookies ) use ( $server, $wordpress, $expected, $check ) {
		$payload = base64_encode( json_encode( [ 'server' => array_merge( $server, [ 'REQUEST_METHOD' => 'POST' ] ), 'cookies' => $cookies ] ) );
		$process = proc_open( [ PHP_BINARY, __FILE__, $wordpress, 'read-variant', $payload ], [ 0 => [ 'pipe', 'r' ], 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ], $pipes );
		if ( ! is_resource( $process ) ) { throw new RuntimeException( 'Cannot start owned native cookie reader.' ); }
		fclose( $pipes[0] );
		$output = stream_get_contents( $pipes[1] );
		$error = stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		$check( 0 === proc_close( $process ), 'fresh native cookie reader succeeds: ' . $error );
		$result = json_decode( $output, true );
		$check( is_array( $result ) && $expected === $result['groups'], 'fresh native reader loads all generated groups' );
		return $result['path'];
	};
	$values = [ 'session_id' => 's1', 'geo' => [ 'country' => 'FR' ], 'prefs' => [ 'currency' => 'EUR' ], 'also_geo' => [ 'country' => 'CA' ] ];
	$first = $filename( $values );
	$owned = dirname( $first );
	$check( $name === basename( $owned ) && 0 === strpos( wp_normalize_path( $owned ), trailingslashit( wp_normalize_path( NOOBROCKET_CACHE_PATH ) ) ), 'variant directory is uniquely owned inside native cache' );
	wp_mkdir_p( $owned );
	file_put_contents( $first, 'original-variant' );
	foreach ( [ 'geo' => [ 'country' => 'DE' ], 'prefs' => [ 'currency' => 'USD' ], 'also_geo' => [ 'country' => 'US' ] ] as $key => $value ) {
		$changed = $values;
		$changed[ $key ] = $value;
		$path = $filename( $changed );
		$check( $first !== $path && $owned === dirname( $path ), 'native ' . $key . ' selects a distinct owned variant' );
		file_put_contents( $path, $key . '-variant' );
		$check( $key . '-variant' === file_get_contents( $filename( $changed ) ) && 'original-variant' === file_get_contents( $filename( $values ) ), 'native ' . $key . ' does not reuse the original variant' );
	}
} finally {
	remove_filter( 'noobrocket_cache_dynamic_cookies', $filter, PHP_INT_MAX );
	$_COOKIE = $saved_cookies;
	foreach ( $backups as $path => $bytes ) {
		if ( null === $bytes ) {
			$check( unlink( $path ) && ! file_exists( $path ), 'new native policy is absent after cleanup' );
		} else {
			$check( false !== file_put_contents( $path, $bytes ) && $bytes === file_get_contents( $path ), 'native policy is restored byte for byte' );
		}
	}
	if ( $owned && is_dir( $owned ) ) {
		$resolved = realpath( $owned );
		$cache = realpath( NOOBROCKET_CACHE_PATH );
		if ( ! $resolved || ! $cache || basename( $resolved ) !== $name || 0 !== strpos( wp_normalize_path( $resolved ), trailingslashit( wp_normalize_path( $cache ) ) ) ) { throw new RuntimeException( 'Owned cookie cleanup containment failed.' ); }
		foreach ( new FilesystemIterator( $owned ) as $file ) { if ( ! $file->isFile() || $file->isLink() ) { throw new RuntimeException( 'Unexpected owned cookie entry.' ); } unlink( $file->getPathname() ); }
		rmdir( $owned );
		$check( ! is_dir( $owned ), 'owned cookie variant directory is removed' );
	}
}
$completed = true;
echo 'PASS native dynamic cookie groups: ', $checks, " checks\n";
