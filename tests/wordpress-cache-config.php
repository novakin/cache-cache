<?php
/**
 * Cache configuration fixtures. Does not load or change a WordPress installation.
 * php tests/wordpress-cache-config.php
 */
require __DIR__ . '/../noobrocket/inc/classes/traits/trait-memoize.php';
require __DIR__ . '/../noobrocket/inc/classes/Buffer/class-config.php';
require __DIR__ . '/../noobrocket/inc/classes/Buffer/class-tests.php';

// The cache gate runs before WordPress on a frontend request.
function is_admin() {
	return false;
}

function cache_config_check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( 'FAIL: ' . $message );
	}
	echo 'PASS: ', $message, PHP_EOL;
}

set_error_handler( function ( $severity, $message, $file, $line ) {
	if ( error_reporting() & $severity ) {
		throw new ErrorException( $message, 0, $severity, $file, $line );
	}
	return true;
} );

$fixture_dir = tempnam( sys_get_temp_dir(), 'noobrocket-config-test-' );
unlink( $fixture_dir );
mkdir( $fixture_dir );
$fixture_file = $fixture_dir . '/example.test.php';

try {
	foreach ( [ 'loaded', 'missing', 'removed', 'empty', 'partial', 'incomplete' ] as $scenario ) {
		// Each fixture represents a fresh request; production keeps these values for one request.
		foreach ( [ 'config_dir_path' => null, 'server' => null, 'memoized' => [] ] as $name => $value ) {
			$property = new ReflectionProperty( \NoobRocket\Buffer\Config::class, $name );
			$property->setAccessible( true );
			$property->setValue( null, $value );
		}
		if ( 'missing' !== $scenario ) {
			$bytes = '<?php $noobrocket_cache_ssl = 1; $noobrocket_cache_reject_cookies = "private_session"; $noobrocket_config_complete = true;';
			if ( 'empty' === $scenario ) { $bytes = ''; }
			if ( 'partial' === $scenario ) { $bytes = '<?php $noobrocket_cache_ssl = '; }
			if ( 'incomplete' === $scenario ) { $bytes = '<?php $noobrocket_cache_ssl = 1;'; }
			file_put_contents( $fixture_file, $bytes );
		}
		$config = new \NoobRocket\Buffer\Config( [
			'config_dir_path' => $fixture_dir,
			'server' => [ 'HTTP_HOST' => 'example.test', 'REQUEST_URI' => '/', 'REQUEST_METHOD' => 'GET' ],
		] );

		if ( 'loaded' === $scenario ) {
			cache_config_check( 1 === $config->get_config( 'cache_ssl' ) && 'private_session' === $config->get_config( 'cache_reject_cookies' ) && [] === $config->get_config( 'cache_query_strings' ), 'existing configuration loads explicit values and preserves defaults' );
			unlink( $fixture_file );
			cache_config_check( 1 === $config->get_config( 'cache_ssl' ) && 'private_session' === $config->get_config( 'cache_reject_cookies' ), 'successfully loaded settings remain consistent for the current request' );
		} elseif ( 'missing' === $scenario ) {
			cache_config_check( false === $config->get_configs() && null === $config->get_config( 'cache_ssl' ), 'missing configuration returns failure without default settings or warnings' );
			$cache_tests = new \NoobRocket\Buffer\Tests( $config );
			$cache_tests->set_tests( [] );
			cache_config_check( ! $cache_tests->can_init_process(), 'missing configuration cannot initialize even with optional checks disabled' );
		} elseif ( 'removed' === $scenario ) {
			cache_config_check( $config->get_config_file_path()['success'], 'configuration path is discovered before lifecycle removal' );
			unlink( $fixture_file );
			clearstatcache( true, $fixture_file );
			$cache_tests = new \NoobRocket\Buffer\Tests( $config );
			cache_config_check( ! $cache_tests->can_init_process() && 'No config file found.' === $cache_tests->get_last_error()['message'], 'cache initialization itself refuses a configuration removed after path discovery' );
			cache_config_check( false === $config->get_configs() && null === $config->get_config( 'cache_ssl' ), 'removed configuration returns failure without default settings or warnings' );
			cache_config_check( ! $config->get_config_file_path()['success'], 'failed configuration load invalidates the cached path success' );
			$cache_tests = new \NoobRocket\Buffer\Tests( $config );
			cache_config_check( ! $cache_tests->can_init_process() && 'No config file found.' === $cache_tests->get_last_error()['message'], 'cache initialization refuses the configuration that disappeared' );
		} else {
			cache_config_check( false === $config->get_configs(), $scenario . ' policy fails closed without permissive defaults or fatal errors' );
			$cache_tests = new \NoobRocket\Buffer\Tests( $config, [ 'cookies' => [ 'private_session' => 'fixture' ] ] );
			cache_config_check( ! $cache_tests->can_init_process(), $scenario . ' policy cannot initialize page caching' );
		}
	}
	// Explicit previews stay private even if an administrator allows their query key.
	$preview_tests = new \NoobRocket\Buffer\Tests( $config, [ 'get' => [ 'noobrocket_asset_preview' => '1' ] ] );
	cache_config_check( ! $preview_tests->can_process_any_buffer() && 'Asset preview bypasses caching.' === $preview_tests->get_last_error()['message'], 'asset preview query always bypasses early cache and buffer initialization' );
} finally {
	if ( is_file( $fixture_file ) ) {
		unlink( $fixture_file );
	}
	rmdir( $fixture_dir );
	restore_error_handler();
}
