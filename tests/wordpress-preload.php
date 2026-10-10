<?php
/**
 * Preload cache-hit fixtures. Reads WordPress classes; never loads the site or its database.
 * php tests/wordpress-preload.php /absolute/path/to/disposable/wordpress
 */
$wordpress = isset( $argv[1] ) ? realpath( $argv[1] ) : false;
if ( ! $wordpress || ! is_file( $wordpress . '/wp-load.php' ) || ! is_file( $wordpress . '/.noobrocket-acceptance-site' ) ) {
	fwrite( STDERR, "Provide a disposable WordPress installation with the .noobrocket-acceptance-site marker.\n" );
	exit( 2 );
}
define( 'ABSPATH', $wordpress . '/' );
require ABSPATH . 'wp-includes/plugin.php';
require ABSPATH . 'wp-includes/load.php';
require ABSPATH . 'wp-includes/http.php';
require ABSPATH . 'wp-includes/formatting.php';
require ABSPATH . 'wp-includes/class-wp-error.php';
require ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
require ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
require __DIR__ . '/../noobrocket/vendor/autoload.php';
require __DIR__ . '/../noobrocket/inc/constants.php';
require __DIR__ . '/../noobrocket/inc/functions/formatting.php';
require __DIR__ . '/../noobrocket/inc/functions/files.php';

// These state APIs remain in memory; the disposable site's options/database are not loaded.
function current_time( $type, $gmt = false ) {
	return gmdate( 'Y-m-d H:i:s' );
}
function get_transient( $name ) {
	return true;
}
$fixture_options = [];
function get_option( $name, $default = false ) {
	global $fixture_options;
	return $fixture_options[ $name ] ?? $default;
}

class PreloadFixtureQuery extends \NoobRocket\Engine\Preload\Database\Queries\Cache {
	public $row;
	public function __construct( string $url ) {
		$this->row = (object) [ 'id' => 1, 'url' => $url, 'status' => 'in-progress' ];
	}
	public function query( $query = [], bool $use_cache = true ) {
		return isset( $query['url'] ) && $query['url'] === $this->row->url ? [ $this->row ] : [];
	}
	public function update_item( $item_id = 0, $data = [] ) {
		foreach ( $data as $name => $value ) {
			$this->row->$name = $value;
		}
		return true;
	}
}

function preload_check( $condition, $message ) {
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
$fixture_dir = tempnam( sys_get_temp_dir(), 'noobrocket-preload-test-' );
unlink( $fixture_dir );
mkdir( $fixture_dir );
define( 'NOOBROCKET_CACHE_PATH', $fixture_dir . '/pages/' );
$fixture_files = [];
$fixture_directories = [ $fixture_dir ];

try {
	foreach ( [ '/config', '/pages', '/pages/example.test', '/pages/example.test/News', '/pages/example.test/News/preload-fixture', '/pages/example.test/News/preload-fixture/%c3%a9' ] as $directory ) {
		mkdir( $fixture_dir . $directory );
		$fixture_directories[] = $fixture_dir . $directory;
	}
	$config_file = $fixture_dir . '/config/example.test.php';
	file_put_contents( $config_file, '<?php $noobrocket_cache_ssl = 1; $noobrocket_config_complete = true;' );
	$fixture_files[] = $config_file;
	$_SERVER = [ 'HTTP_HOST' => 'example.test', 'REQUEST_URI' => '/News/preload-fixture/%C3%A9/', 'REQUEST_METHOD' => 'GET', 'HTTPS' => 'on' ];
	$config = new \NoobRocket\Buffer\Config( [ 'config_dir_path' => $fixture_dir . '/config', 'server' => $_SERVER ] );
	$buffer_tests = new \NoobRocket\Buffer\Tests( $config, [ 'cookies' => [], 'get' => [], 'post' => [] ] );
	$cache = new \NoobRocket\Buffer\Cache( $buffer_tests, $config, [ 'cache_dir_path' => NOOBROCKET_CACHE_PATH ] );
	$https_file = $cache->get_cache_path();
	file_put_contents( $https_file, 'fixture HTTPS cache' );
	$fixture_files[] = $https_file;
	preload_check( str_ends_with( $https_file, '/index-https.html' ) && is_file( $https_file ), 'native cache layout generated a real HTTPS cache fixture' );
	preload_check( str_ends_with( $https_file, '/News/preload-fixture/%c3%a9/index-https.html' ), 'writer preserves literal path case and normalizes escaped hexadecimal digits' );

	$_SERVER['HTTPS'] = 'off';
	$_SERVER['SERVER_PORT'] = '80';
	$url = 'https://example.test/News/preload-fixture/%C3%A9';
	$query = new PreloadFixtureQuery( $url );
	$filesystem = new WP_Filesystem_Direct( null );
	$options = new \NoobRocket\Admin\Options_Data( [ 'cache_ssl' => 1 ] );
	$preload = new \NoobRocket\Engine\Preload\Controller\PreloadUrl( $options, new \NoobRocket\Engine\Preload\Controller\Queue(), $query, $filesystem );
	$attempted_requests = 0;
	add_filter( 'noobrocket_preload_before_preload_url', function () use ( &$attempted_requests ) {
		++$attempted_requests;
		return []; // No real HTTP request can leave this fixture.
	} );
	preload_check( $preload->is_already_cached( $url ), 'HTTPS URL cache hit works from an HTTP/CLI cron context' );
	$fixture_options['noobrocket_preload_paused'] = true;
	$preload->preload_url( $url );
	preload_check( 'in-progress' === $query->row->status && 0 === $attempted_requests, 'paused preloading preserves the row without HTTP or completion' );
	unset( $fixture_options['noobrocket_preload_paused'] );
	$preload->preload_url( $url );
	preload_check( 'completed' === $query->row->status && 0 === $attempted_requests, 'existing HTTPS cache completes the in-progress row without redundant HTTP' );

	$http_url = 'http://example.test/News/preload-fixture/%C3%A9';
	preload_check( ! $preload->is_already_cached( $http_url ), 'HTTPS cache does not satisfy an HTTP URL in the same process' );
	$http_file = dirname( $https_file ) . '/index.html';
	file_put_contents( $http_file, 'fixture HTTP cache' );
	$fixture_files[] = $http_file;
	preload_check( $preload->is_already_cached( $http_url ) && $preload->is_already_cached( $url ), 'HTTP and HTTPS variants are detected independently' );

	$query->row->status = 'in-progress';
	$options->set( 'do_caching_mobile_files', true );
	$preload->preload_url( $url );
	preload_check( 'failed' === $query->row->status && 1 === $attempted_requests, 'desktop cache alone cannot complete a separate-mobile preload with no usable HTTP request' );
	$mobile_file = dirname( $https_file ) . '/index-mobile-https.html';
	file_put_contents( $mobile_file, 'fixture mobile HTTPS cache' );
	$fixture_files[] = $mobile_file;
	$preload->preload_url( $url );
	preload_check( 'completed' === $query->row->status && 1 === $attempted_requests, 'desktop and mobile HTTPS cache files complete preload without HTTP' );
} finally {
	foreach ( array_reverse( $fixture_files ) as $file ) {
		if ( is_file( $file ) ) {
			unlink( $file );
		}
	}
	foreach ( array_reverse( $fixture_directories ) as $directory ) {
		rmdir( $directory );
	}
	restore_error_handler();
}
