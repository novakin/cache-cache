<?php
/**
 * WebP conversion-to-cache regression checks with temporary files and unchanged settings.
 * php tests/wordpress-webp-cache.php /absolute/path/to/disposable/wordpress
 */
$wordpress = isset( $argv[1] ) ? realpath( $argv[1] ) : false;
if ( ! $wordpress || ! is_file( $wordpress . '/wp-load.php' ) || ! is_file( $wordpress . '/.noobrocket-acceptance-site' ) ) {
	fwrite( STDERR, "Provide a disposable WordPress installation with the .noobrocket-acceptance-site marker.\n" );
	exit( 2 );
}
putenv( 'NOOBROCKET_TEST_PHASE=webp-cache' );
$_SERVER['HTTP_HOST'] = '127.0.0.1:8765';
$_SERVER['REQUEST_URI'] = '/';
// Bypass early homepage delivery; each cache-writer scenario supplies GET.
$_SERVER['REQUEST_METHOD'] = 'POST';
define( 'WP_ADMIN', true );
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
require $wordpress . '/wp-load.php';
http_response_code( 200 );

$checks = [];
function webp_cache_check( $condition, $message ) {
	global $checks;
	if ( ! $condition ) {
		throw new RuntimeException( 'FAIL: ' . $message );
	}
	$checks[] = $message;
}

$fixture = tempnam( sys_get_temp_dir(), 'noobrocket-webp-test-' );
unlink( $fixture );
mkdir( $fixture );
mkdir( $fixture . '/images' );
mkdir( $fixture . '/config' );
file_put_contents( $fixture . '/images/converted.jpg', 'Original image fixture' );
file_put_contents( $fixture . '/images/converted.webp', 'Existing WebP image fixture' );
file_put_contents( $fixture . '/images/original.jpg', 'Original-only image fixture' );
file_put_contents( $fixture . '/config/webp-cache.test.php', '<?php $noobrocket_cache_webp = 1; $noobrocket_cache_reject_cookies = ""; $noobrocket_cache_ignored_parameters = []; $noobrocket_config_complete = true;' );

$associations = function ( $paths ) use ( $fixture ) {
	return [ '//webp-images.test/' => $fixture . '/images/' ] + $paths;
};
add_filter( 'noobrocket_url_to_path_associations', $associations );
$container = apply_filters( 'noobrocket_container', null );
$converter = new \NoobRocket\Addon\WebP\Subscriber(
	new \NoobRocket\Admin\Options_Data( [ 'cache_webp' => 1 ] ),
	$container->get( 'options_api' ),
	$container->get( 'cdn_subscriber' ),
	[ 'HTTP_ACCEPT' => 'text/html,image/webp', 'HTTP_USER_AGENT' => 'Chrome/130.0' ]
);
$image = '<img src="http://webp-images.test/converted.jpg" alt="Converted">';
$original = '<img src="http://webp-images.test/original.jpg" alt="Original">';
$cases = [
	'converted' => [ $image, true, null ],
	'original-only' => [ $original, false, null ],
	'mixed' => [ $image . $original, true, null ],
	'no-images' => [ '<p>No images</p>', false, null ],
	'legacy-has' => [ '<img src="http://webp-images.test/converted.webp" alt="Converted">', true, '<!-- Rocket has webp -->' ],
	'legacy-no' => [ $original, false, '<!-- Rocket no webp -->' ],
];
$original_cache_state = [];
foreach ( [ \NoobRocket\Buffer\Config::class => [ 'memoized', 'config_dir_path', 'server' ], \NoobRocket\Buffer\Tests::class => [ 'memoized' ] ] as $class => $properties ) {
	foreach ( $properties as $name ) {
		$property = new ReflectionProperty( $class, $name );
		$property->setAccessible( true );
		$original_cache_state[] = [ $property, $property->getValue() ];
	}
}

try {
	foreach ( $cases as $name => $case ) {
		list( $images, $has_webp, $legacy_marker ) = $case;
		// Production memoization lasts one request; each fixture is a fresh request.
		foreach ( [ \NoobRocket\Buffer\Config::class, \NoobRocket\Buffer\Tests::class ] as $class ) {
			$property = new ReflectionProperty( $class, 'memoized' );
			$property->setAccessible( true );
			$property->setValue( null, [] );
		}
		foreach ( [ 'config_dir_path', 'server' ] as $property_name ) {
			$property = new ReflectionProperty( \NoobRocket\Buffer\Config::class, $property_name );
			$property->setAccessible( true );
			$property->setValue( null, null );
		}
		$config = new \NoobRocket\Buffer\Config( [
			'config_dir_path' => $fixture . '/config',
			'server' => [ 'HTTP_HOST' => 'webp-cache.test', 'REQUEST_URI' => '/' . $name . '/', 'REQUEST_METHOD' => 'GET', 'HTTP_ACCEPT' => 'text/html,image/webp', 'HTTP_USER_AGENT' => 'Chrome/130.0' ],
		] );
		$tests = new \NoobRocket\Buffer\Tests( $config, [ 'cookies' => [], 'get' => [], 'post' => [] ] );
		$cache = new \NoobRocket\Buffer\Cache( $tests, $config, [ 'cache_dir_path' => $fixture . '/pages' ] );
		$html = '<html><body>' . $images . str_repeat( '<p>WebP cache fixture content.</p>', 12 ) . '</body></html>';
		$buffer = null === $legacy_marker ? $converter->convert_to_webp( $html ) : $html . $legacy_marker;
		$expected_marker = null === $legacy_marker ? '<!-- NoobRocket ' . ( $has_webp ? 'has' : 'no' ) . ' webp -->' : $legacy_marker;
		webp_cache_check( false !== strpos( $buffer, $expected_marker ), $name . ': converter/legacy marker represents image availability' );
		if ( 'converted' === $name || 'mixed' === $name ) {
			webp_cache_check( false !== strpos( $buffer, '/converted.webp' ) && false === strpos( $buffer, '/converted.jpg' ), $name . ': existing WebP file replaces the original image URL' );
		}
		if ( 'original-only' === $name || 'mixed' === $name ) {
			webp_cache_check( false !== strpos( $buffer, '/original.jpg' ), $name . ': original-only image is preserved' );
		}
		$result = $cache->maybe_process_buffer( $buffer );
		$directory = $fixture . '/pages/webp-cache.test/' . $name;
		$expected = $directory . '/index' . ( $has_webp ? '-webp' : '' ) . '.html';
		$other = $directory . '/index' . ( $has_webp ? '' : '-webp' ) . '.html';
		webp_cache_check( is_file( $expected ) && ! is_file( $other ), $name . ': writes only the appropriate WebP/non-WebP cache variant' );
		$cached = file_get_contents( $expected );
		webp_cache_check( ! preg_match( '/<!-- (?:NoobRocket|Rocket) (?:has|no) webp -->/', $cached . $result ), $name . ': internal WebP marker is stripped from response and cache' );
		webp_cache_check( is_file( $directory . '/.no-webp' ) === ! $has_webp, $name . ': no-WebP fallback marker matches actual image availability' );
		if ( function_exists( 'gzencode' ) ) {
			webp_cache_check( is_file( $expected . '_gzip' ) && gzdecode( file_get_contents( $expected . '_gzip' ) ) === $cached, $name . ': gzip cache contains the same selected variant' );
		}
	}
} finally {
	// The real WordPress shutdown buffer must not inherit deleted fixture config.
	foreach ( $original_cache_state as $state ) {
		$state[0]->setValue( null, $state[1] );
	}
	remove_filter( 'noobrocket_url_to_path_associations', $associations );
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $fixture, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $iterator as $file ) {
		if ( $file->isDir() ) {
			rmdir( $file->getPathname() );
		} else {
			unlink( $file->getPathname() );
		}
	}
	rmdir( $fixture );
}
echo 'PASS: ', count( $checks ), ' WebP cache assertions across ', count( $cases ), ' scenarios.', PHP_EOL;
