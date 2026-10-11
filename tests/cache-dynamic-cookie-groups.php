<?php
/** Actual dynamic-cookie list, generated policy and reader filenames; modeled WordPress boundaries. */
declare(strict_types=1);

require dirname( __DIR__ ) . '/noobrocket/vendor/autoload.php';
$temporary = sys_get_temp_dir() . '/cache-cache-cookie-groups-' . bin2hex( random_bytes( 6 ) );
mkdir( $temporary );
define( 'ABSPATH', $temporary . '/' );
define( 'NOOBROCKET_CONFIG_PATH', $temporary . '/' );
define( 'NOOBROCKET_SLUG', 'noobrocket_settings' );
define( 'COOKIEHASH', 'cookie-groups-fixture' );
define( 'LOGGED_IN_COOKIE', 'wordpress_logged_in_cookie_groups_fixture' );
$definitions = [];
$checks = 0;
$warnings = [];
function apply_filters( $hook, $value, ...$args ) { return 'noobrocket_cache_dynamic_cookies' === $hook ? $GLOBALS['definitions'] : $value; }
function get_option( $name, $default = false ) {
	if ( NOOBROCKET_SLUG === $name ) { return [ 'cache_ssl' => 1, 'cache_mobile' => 0, 'do_caching_mobile_files' => 0, 'cache_webp' => 0 ]; }
	return 'permalink_structure' === $name ? '/%postname%/' : $default;
}
function noobrocket_get_home_url() { return 'https://cookies.example.test/'; }
function get_noobrocket_i18n_subdomains() { return []; }
function get_noobrocket_parse_url( $url ) { return array_merge( [ 'path' => '' ], parse_url( $url ) ); }
function untrailingslashit( $value ) { return rtrim( $value, '/' ); }
function wp_slash( $value ) { return addslashes( $value ); }
function is_ssl() { return true; }
function cookie_group_check( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
	++$GLOBALS['checks'];
}
require dirname( __DIR__ ) . '/noobrocket/inc/functions/options.php';
require dirname( __DIR__ ) . '/noobrocket/inc/functions/files.php';
set_error_handler( static function ( $severity, $message ) { $GLOBALS['warnings'][] = $message; return true; } );
try {
	$definitions = [ 2 => 'session_id', 4 => 'session_id', 7 => 'currency', 9 => '', 11 => false ];
	cookie_group_check( [ 2 => 'session_id', 7 => 'currency' ] === get_noobrocket_cache_dynamic_cookies(), 'plain cookie deduplication preserves original keys/order and filters empty values' );
	$definitions = [ 2 => 'session_id', 'geo' => [ 'country' ], 'prefs' => [ 'currency' ], 'also_geo' => [ 'country' ], 4 => 'session_id', 'empty' => [] ];
	$expected = [ 2 => 'session_id', 'geo' => [ 'country' ], 'prefs' => [ 'currency' ], 'also_geo' => [ 'country' ] ];
	cookie_group_check( $expected === get_noobrocket_cache_dynamic_cookies(), 'every named group survives, including equal child arrays under different parent cookies' );
	cookie_group_check( [] === $warnings, 'valid nested declarations produce no array-to-string diagnostics' );
	$definitions = [ 'first' => [ 'country', 'region' ], 'session_id', 'second' => [ 'country', 'region' ], 'session_id' ];
	cookie_group_check( [ 'first' => [ 'country', 'region' ], 0 => 'session_id', 'second' => [ 'country', 'region' ] ] === get_noobrocket_cache_dynamic_cookies(), 'mixed group/name order and each group child order remain intact' );
	$definitions = [ 1, '1', 'session_id' ];
	cookie_group_check( [ 0 => 1, 2 => 'session_id' ] === get_noobrocket_cache_dynamic_cookies(), 'legacy scalar string-comparison deduplication is preserved' );
	$definitions = $expected;
	list( $paths, $policy ) = get_noobrocket_config_file();
	cookie_group_check( [ $temporary . '/cookies.example.test.php' ] === $paths, 'native policy owner selects the fixture host configuration' );
	file_put_contents( $paths[0], $policy );
	$config = new NoobRocket\Buffer\Config( [ 'config_dir_path' => $temporary, 'server' => [ 'HTTP_HOST' => 'cookies.example.test', 'REQUEST_URI' => '/article/', 'REQUEST_METHOD' => 'GET', 'HTTPS' => 'on' ] ] );
	cookie_group_check( $expected === $config->get_config( 'cache_dynamic_cookies' ), 'all definitions survive generated executable policy and the actual early reader' );
	$values = [ 'session_id' => 'session-a', 'geo' => [ 'country' => 'FR' ], 'prefs' => [ 'currency' => 'EUR' ], 'also_geo' => [ 'country' => 'CA' ] ];
	$filename = static function ( $cookies ) use ( $config, $temporary ) {
		$_COOKIE = $cookies;
		$tests = new NoobRocket\Buffer\Tests( $config, [ 'cookies' => $cookies, 'get' => [], 'post' => [] ] );
		return ( new NoobRocket\Buffer\Cache( $tests, $config, [ 'cache_dir_path' => $temporary . '/pages/' ] ) )->get_cache_path();
	};
	$original = $filename( $values );
	cookie_group_check( $temporary . '/pages/cookies.example.test/article/index-https-session-a-FR-EUR-CA.html' === $original, 'actual filename owner varies every scalar and nested cookie in declaration order' );
	foreach ( [ 'geo' => [ 'country' => 'DE' ], 'prefs' => [ 'currency' => 'USD' ], 'also_geo' => [ 'country' => 'US' ] ] as $name => $value ) {
		$changed = $values;
		$changed[ $name ] = $value;
		cookie_group_check( $original !== $filename( $changed ), 'changing only ' . $name . ' selects a different cache filename' );
	}
	cookie_group_check( [] === $warnings, 'generated policy and filename reading remain diagnostic-free' );
	echo 'PASS: ', $checks, ' dynamic-cookie group assertions.', PHP_EOL;
} finally {
	restore_error_handler();
	if ( isset( $paths[0] ) && is_file( $paths[0] ) ) { unlink( $paths[0] ); }
	rmdir( $temporary );
}
