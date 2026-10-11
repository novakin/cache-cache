<?php
/** Modeled multisite configuration cleanup; real owned files, no WordPress site. */
declare(strict_types=1);

require dirname( __DIR__ ) . '/noobrocket/vendor/autoload.php';
$temporary = sys_get_temp_dir() . '/cache-cache-network-configs-' . bin2hex( random_bytes( 6 ) );
mkdir( $temporary );
define( 'NOOBROCKET_CONFIG_PATH', $temporary . '/' );
define( 'ABSPATH', $temporary . '/' );
define( 'NOOBROCKET_SLUG', 'noobrocket_settings' );
define( 'COOKIEHASH', 'network-config-fixture' );
define( 'LOGGED_IN_COOKIE', 'wordpress_logged_in_network_config_fixture' );
require dirname( __DIR__ ) . '/noobrocket/inc/functions/files.php';
$fixture = [ 'blog' => 42, 'multisite' => true, 'stack' => [], 'queries' => [], 'fail_blog' => null, 'checks' => 0, 'sites' => array_merge( range( 1, 105 ), [ 201, 202 ] ) ];
function is_multisite() { return $GLOBALS['fixture']['multisite']; }
function get_current_blog_id() { return $GLOBALS['fixture']['blog']; }
function get_sites( $arguments ) {
	$GLOBALS['fixture']['queries'][] = $arguments;
	$limit = $arguments['number'] ?? 100;
	$sites = $GLOBALS['fixture']['sites'];
	if ( isset( $arguments['network_id'] ) ) {
		$sites = array_values( array_filter( $sites, static function ( $id ) use ( $arguments ) {
			return ( $id <= 105 ? 1 : 2 ) === $arguments['network_id'];
		} ) );
	}
	return array_slice( $sites, 0, 0 === $limit ? null : $limit );
}
function switch_to_blog( $id ) { $GLOBALS['fixture']['stack'][] = get_current_blog_id(); $GLOBALS['fixture']['blog'] = $id; }
function restore_current_blog() { $GLOBALS['fixture']['blog'] = array_pop( $GLOBALS['fixture']['stack'] ); }
function home_url() {
	if ( get_current_blog_id() === $GLOBALS['fixture']['fail_blog'] ) { throw new RuntimeException( 'Injected site URL failure' ); }
	return 'https://site' . get_current_blog_id() . '.example.test';
}
function untrailingslashit( $value ) { return rtrim( $value, '/' ); }
function get_noobrocket_parse_url( $url ) { return parse_url( $url ); }
function get_option( $name, $default = false ) { return NOOBROCKET_SLUG === $name ? [ 'cache_mobile' => 0 ] : $default; }
function apply_filters( $hook, $value, ...$args ) { return $value; }
function noobrocket_get_home_url() { return home_url(); }
function get_noobrocket_i18n_subdomains() { return []; }
function noobrocket_get_ignored_parameters() { return []; }
function get_noobrocket_cache_mandatory_cookies() { return []; }
function get_noobrocket_cache_dynamic_cookies() { return []; }
function wp_slash( $value ) { return $value; }
function cache_network_check( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
	++$GLOBALS['fixture']['checks'];
}
class NetworkConfigFilesystem {
	public $listed = true;
	public function dirlist( $directory ) {
		if ( ! $this->listed ) { return false; }
		$files = [];
		foreach ( new DirectoryIterator( $directory ) as $item ) { if ( ! $item->isDot() ) { $files[] = [ 'name' => $item->getFilename() ]; } }
		return $files;
	}
	public function is_file( $path ) { return is_file( $path ); }
	public function get_contents( $path ) { return file_get_contents( $path ); }
	public function delete( $path ) { return unlink( $path ); }
}
class BackgroundCssCleanupSpy {
	public $preserved;
	public function full_clear( $directories ) { $this->preserved = $directories; }
}
$filesystem = new NetworkConfigFilesystem();
$subscriber = ( new ReflectionClass( NoobRocket\Engine\Cache\AdminSubscriber::class ) )->newInstanceWithoutConstructor();
$property = new ReflectionProperty( $subscriber, 'filesystem' );
$property->setAccessible( true );
$property->setValue( $subscriber, $filesystem );
try {
	foreach ( array_merge( $fixture['sites'], [ 999 ] ) as $id ) {
		file_put_contents( $temporary . '/site' . $id . '.example.test.php', '<?php $noobrocket_cookie_hash = "fixture";' );
	}
	file_put_contents( $temporary . '/foreign.php', '<?php $other_cookie_hash = "fixture";' );
	file_put_contents( $temporary . '/owned.txt', '$noobrocket_cookie_hash = "fixture";' );
	mkdir( $temporary . '/directory.php' );
	$subscriber->delete_old_configs();
	cache_network_check( is_file( $temporary . '/site105.example.test.php' ), 'site beyond the default 100 remains configured' );
	cache_network_check( is_file( $temporary . '/site201.example.test.php' ) && is_file( $temporary . '/site202.example.test.php' ), 'shared configuration root preserves live sites on other networks' );
	cache_network_check( 107 === count( glob( $temporary . '/site*.php' ) ), 'only the obsolete site configuration is removed' );
	cache_network_check( is_file( $temporary . '/foreign.php' ) && is_file( $temporary . '/owned.txt' ) && is_dir( $temporary . '/directory.php' ), 'unowned files and directories remain untouched' );
	cache_network_check( 42 === get_current_blog_id() && [] === $fixture['stack'], 'cleanup restores the originating blog' );
	cache_network_check( [ [ 'fields' => 'ids', 'number' => 0 ] ] === $fixture['queries'], 'all global-root site owners are enumerated without a limit' );
	$fixture['fail_blog'] = 37;
	try {
		$subscriber->delete_old_configs();
		throw new LogicException( 'Expected injected URL failure' );
	} catch ( RuntimeException $error ) {
		cache_network_check( 'Injected site URL failure' === $error->getMessage(), 'site discovery failure is reported before configuration deletion' );
	}
	cache_network_check( 42 === get_current_blog_id() && [] === $fixture['stack'], 'site discovery failure still restores the originating blog' );
	$fixture['fail_blog'] = null;
	$fixture['queries'] = [];
	$fixture['multisite'] = false;
	$subscriber->delete_old_configs();
	cache_network_check( [ $temporary . '/site42.example.test.php' ] === glob( $temporary . '/site*.php' ) && [] === $fixture['queries'], 'single-site cleanup preserves only its current configuration without querying sites' );
	$filesystem->listed = false;
	set_error_handler( static function ( $severity, $message ) { throw new RuntimeException( $message ); } );
	try { $subscriber->delete_old_configs(); } finally { restore_error_handler(); }
	cache_network_check( is_file( $temporary . '/site42.example.test.php' ), 'unavailable directory listing leaves configuration intact' );
	$css = ( new ReflectionClass( NoobRocket\Engine\Media\Lazyload\CSS\Admin\Subscriber::class ) )->newInstanceWithoutConstructor();
	$cache = new BackgroundCssCleanupSpy();
	$property = new ReflectionProperty( $css, 'cache' );
	$property->setAccessible( true );
	$property->setValue( $css, $cache );
	$fixture['multisite'] = true;
	$css->clear_background_css_with_upgrade( '3.23.5.1', '3.18' );
	cache_network_check( $fixture['sites'] === $cache->preserved, 'background CSS upgrade cleanup preserves directories above 100 and across networks' );
	$cache->preserved = null;
	$css->clear_background_css_with_upgrade( '3.23.5.1', '3.19' );
	cache_network_check( null === $cache->preserved, 'unrelated upgrades do not run background CSS cleanup' );
	echo 'PASS: ', $fixture['checks'], ' cache network cleanup assertions.', PHP_EOL;
} finally {
	foreach ( new DirectoryIterator( $temporary ) as $item ) { if ( ! $item->isDot() ) { $item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() ); } }
	rmdir( $temporary );
}
