<?php
/** Read-only CLI inventory; no cache generation, setting changes, or HTTP requests. */
if ( 'cli' !== PHP_SAPI ) {
	http_response_code( 404 );
	exit;
}

$root = isset( $argv[1] ) ? realpath( $argv[1] ) : false;
if ( ! $root || ! is_file( $root . '/wp-load.php' ) ) {
	fwrite( STDERR, "Provide the WordPress root.\n" );
	exit( 1 );
}

define( 'WP_USE_THEMES', false );
define( 'DOING_CRON', true );
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'POST'; // Boot inventory past any warm early page cache.
$site_url = $argv[2] ?? '';
if ( $site_url ) {
	$site = filter_var( $site_url, FILTER_VALIDATE_URL ) ? parse_url( $site_url ) : false;
	if ( ! is_array( $site ) || ! in_array( $site['scheme'] ?? '', [ 'http', 'https' ], true ) || empty( $site['host'] ) || isset( $site['user'] ) || isset( $site['pass'] ) || isset( $site['query'] ) || isset( $site['fragment'] ) ) {
		fwrite( STDERR, "Provide an HTTP(S) site root without credentials, query or fragment.\n" );
		exit( 2 );
	}
	$_SERVER['HTTP_HOST'] = $site['host'] . ( isset( $site['port'] ) ? ':' . $site['port'] : '' );
	$_SERVER['SERVER_PORT'] = $site['port'] ?? ( 'https' === $site['scheme'] ? 443 : 80 );
	$_SERVER['HTTPS'] = 'https' === $site['scheme'] ? 'on' : 'off';
	$_SERVER['REQUEST_URI'] = rtrim( $site['path'] ?? '/', '/' ) . '/';
}
ob_start();
require $root . '/wp-load.php';
$startup = trim( ob_get_clean() );
global $wpdb, $wp_version;
$checks = array();
$add = static function ( $name, $passed, $evidence ) use ( &$checks ) {
	$checks[] = array( 'name' => $name, 'passed' => (bool) $passed, 'evidence' => $evidence );
};
$add( 'quiet_boot', '' === $startup, '' === $startup ? 'No startup output.' : substr( $startup, 0, 1000 ) );
$add( 'plugin_active', defined( 'NOOBROCKET_VERSION' ), defined( 'NOOBROCKET_VERSION' ) ? NOOBROCKET_VERSION : 'NoobRocket not active on this site.' );
if ( $site_url ) {
	$canonical = static function ( $url ) { $parts = parse_url( $url ); $scheme = strtolower( $parts['scheme'] ?? '' ); return [ $scheme, strtolower( $parts['host'] ?? '' ), $parts['port'] ?? ( 'https' === $scheme ? 443 : 80 ), rtrim( $parts['path'] ?? '', '/' ) ]; };
	$add( 'selected_site', $canonical( $site_url ) === $canonical( home_url( '/', wp_parse_url( get_option( 'home' ), PHP_URL_SCHEME ) ) ), home_url( '/', wp_parse_url( get_option( 'home' ), PHP_URL_SCHEME ) ) );
}
$add( 'php_version', version_compare( PHP_VERSION, '7.4', '>=' ), PHP_VERSION );
$database_class = get_class( $wpdb );
$sqlite = false !== stripos( $database_class, 'sqlite' ) || defined( 'DB_ENGINE' ) && 'sqlite' === DB_ENGINE;
$database_version = $sqlite ? 'SQLite (' . $database_class . ')' : $wpdb->get_var( 'SELECT VERSION()' );
$database = $sqlite ? 'sqlite' : ( false !== stripos( (string) $database_version, 'mariadb' ) ? 'mariadb' : 'mysql' );
$add( 'database_connected', ! empty( $database_version ), $database_version );
$cache_path = defined( 'NOOBROCKET_CACHE_PATH' ) ? NOOBROCKET_CACHE_PATH : WP_CONTENT_DIR . '/cache/noobrocket/';
$ancestor = rtrim( $cache_path, '/\\' );
while ( ! is_dir( $ancestor ) && dirname( $ancestor ) !== $ancestor ) {
	$ancestor = dirname( $ancestor );
}
$add( 'cache_writable', is_writable( $ancestor ), $ancestor );
$dropin = WP_CONTENT_DIR . '/advanced-cache.php';
$owned = is_file( $dropin ) && false !== strpos( file_get_contents( $dropin ), 'NOOBROCKET_' );
$add( 'owned_cache_dropin', $owned, $owned ? 'NoobRocket cache drop-in present.' : 'No owned advanced-cache.php.' );
$add( 'wp_cache', defined( 'WP_CACHE' ) && WP_CACHE, defined( 'WP_CACHE' ) && WP_CACHE ? 'Enabled.' : 'Disabled.' );
$result = array(
	'protocol' => 'noobrocket-production-preflight-v1',
	'php' => PHP_VERSION,
	'wordpress' => $wp_version,
	'product' => defined( 'NOOBROCKET_VERSION' ) ? NOOBROCKET_VERSION : null,
	'database' => $database,
	'database_version' => $database_version,
	'multisite' => is_multisite(),
	'blog_id' => get_current_blog_id(),
	'platform' => PHP_OS_FAMILY,
	'extensions' => get_loaded_extensions(),
	'checks' => $checks,
	'limits' => array( 'Preflight does not prove cache isolation, rewrite delivery, CSS generation, WooCommerce behavior, or worker CPU limits. Run acceptance and browser fixtures separately.' ),
);
echo wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";
exit( in_array( false, array_column( $checks, 'passed' ), true ) ? 1 : 0 );
