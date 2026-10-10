<?php
/** Cache privacy, executable publication and host ownership; owned temporary files only. */
declare(strict_types=1);

define( 'ABSPATH', rtrim( getenv( 'NOOBROCKET_WORDPRESS_SOURCE' ) ?: dirname( __DIR__ ) . '/.artifacts/wordpress', '/\\' ) . '/' );
define( 'FS_CHMOD_FILE', 0644 );
define( 'NOOBROCKET_PLUGIN_NAME', 'NoobRocket' );
define( 'HOUR_IN_SECONDS', 3600 );
require ABSPATH . 'wp-includes/class-wp-error.php';
require dirname( __DIR__ ) . '/noobrocket/inc/classes/traits/trait-memoize.php';
require dirname( __DIR__ ) . '/noobrocket/inc/classes/Buffer/class-config.php';
require dirname( __DIR__ ) . '/noobrocket/inc/classes/Buffer/class-tests.php';
require dirname( __DIR__ ) . '/noobrocket/inc/classes/Buffer/class-abstract-buffer.php';
require dirname( __DIR__ ) . '/noobrocket/inc/classes/Buffer/class-cache.php';
require dirname( __DIR__ ) . '/noobrocket/inc/functions/files.php';
require dirname( __DIR__ ) . '/noobrocket/inc/Engine/Cache/PurgeExpired/PurgeExpiredCache.php';

function noobrocket_get_constant( $name, $default = null ) { return defined( $name ) ? constant( $name ) : $default; }
function get_noobrocket_option( $name, $default = false ) { return 'secret_cache_key' === $name ? 'site-secret' : $default; }
function is_admin() { return false; }
function is_ssl() { return false; }
function mbstring_binary_safe_encoding() {}
function reset_mbstring_encoding() {}
function wp_normalize_path( $path ) { return str_replace( '\\', '/', $path ); }
function untrailingslashit( $value ) { return rtrim( $value, '/\\' ); }
function apply_filters( $hook, $value, ...$args ) {
	if ( 'do_noobrocket_generate_caching_files' === $hook ) { return $GLOBALS['generate_allowed'] ?? true; }
	if ( 'noobrocket_refresh_expired_cache' === $hook ) { ++$GLOBALS['refresh_calls']; return $GLOBALS['refresh_allowed']; }
	return $value;
}
function set_url_scheme( $url, $scheme ) { return preg_replace( '/^https?:/', $scheme . ':', $url ); }
function do_action( $hook, ...$args ) { $GLOBALS['policy_events'][] = $hook; }
function get_noobrocket_i18n_home_url( $lang ) { return 'https://example.test/'; }
function get_noobrocket_parse_url( $url ) { return [ 'host' => 'example.test', 'path' => '/' ]; }
class PolicyFilesystem {
	public $mode = 'normal';
	public function put_contents( $path, $bytes, $permissions ) {
		if ( 'fail' === $this->mode ) { return false; }
		if ( 'partial' === $this->mode ) { file_put_contents( $path, substr( $bytes, 0, 12 ) ); return true; }
		if ( 'throw' === $this->mode ) { throw new RuntimeException( 'Injected disk failure' ); }
		file_put_contents( $path, $bytes );
		return true;
	}
	public function exists( $path ) { return file_exists( $path ); }
	public function delete( $path ) { return is_dir( $path ) ? rmdir( $path ) : unlink( $path ); }
}
class PublicationTests extends NoobRocket\Buffer\Tests {
	public function can_process_buffer( $buffer ) { return true; }
}
class PublicationCache extends NoobRocket\Buffer\Cache {
	protected function log( $message, $data = [], $type = 'debug' ) {}
}
$filesystem = new PolicyFilesystem();
$checks = 0;
function policy_check( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
	++$GLOBALS['checks'];
}
$temporary = sys_get_temp_dir() . '/noobrocket-policy-' . bin2hex( random_bytes( 6 ) );
mkdir( $temporary );
define( 'NOOBROCKET_CACHE_PATH', $temporary . '/pages/' );
mkdir( NOOBROCKET_CACHE_PATH );
$GLOBALS['wp_rewrite'] = (object) [ 'pagination_base' => 'page' ];
try {
	$target = $temporary . '/example.test.php';
	$old = '<?php $noobrocket_cache_ssl = 1; $noobrocket_config_complete = true;';
	$new = '<?php $noobrocket_cache_ssl = 0; $noobrocket_config_complete = true;';
	file_put_contents( $target, $old );
	policy_check( noobrocket_put_php_content( $target, $new, $filesystem ) && $new === file_get_contents( $target ), 'atomic executable replacement publishes complete new bytes' );
	foreach ( [ 'fail', 'partial', 'throw' ] as $mode ) {
		$filesystem->mode = $mode;
		try { $result = noobrocket_put_php_content( $target, $old, $filesystem ); }
		catch ( RuntimeException $error ) { $result = false; }
		policy_check( ! $result && $new === file_get_contents( $target ), $mode . ' write preserves prior executable policy' );
		policy_check( [] === glob( $temporary . '/.nbr-php-*' ), $mode . ' failure removes temporary bytes' );
	}
	$filesystem->mode = 'normal';
	$directory_target = $temporary . '/directory-target';
	mkdir( $directory_target );
	policy_check( ! noobrocket_put_php_content( $directory_target, $old, $filesystem ) && is_dir( $directory_target ), 'failed rename preserves target and removes temporary bytes' );
	rmdir( $directory_target );

	$names = [ 'example.test', 'example.test-alice-site-secret', 'example.test-loggedin-site-secret', 'example.test.evil', 'exampleXtest', 'example.test-evil', 'example.test-alice-foreign-secret' ];
	foreach ( $names as $name ) {
		mkdir( NOOBROCKET_CACHE_PATH . $name );
		file_put_contents( NOOBROCKET_CACHE_PATH . $name . '/index.html', 'fixture' );
	}
	$owned = _noobrocket_get_cache_dirs( 'example.test', NOOBROCKET_CACHE_PATH );
	policy_check( 3 === count( $owned ), 'manual purge selector chooses exact host and current-secret user buckets only' );
	$purger = new NoobRocket\Engine\Cache\PurgeExpired\PurgeExpiredCache( NOOBROCKET_CACHE_PATH );
	$property = new ReflectionProperty( $purger, 'filesystem' );
	$property->setAccessible( true );
	$property->setValue( $purger, $filesystem );
	$method = new ReflectionMethod( $purger, 'get_cache_files_in_dir' );
	$method->setAccessible( true );
	$matches = iterator_to_array( $method->invoke( $purger, [ 'host' => 'example.test', 'path' => '/' ] ) );
	policy_check( 3 === count( $matches ), 'expiry selector uses the same exact host ownership' );
	noobrocket_clean_home();
	foreach ( $names as $index => $name ) {
		policy_check( ( $index >= 3 ) === is_file( NOOBROCKET_CACHE_PATH . $name . '/index.html' ), 'homepage purge preserves sibling/foreign ownership for ' . $name );
	}
	policy_check( ! noobrocket_cache_directory_belongs_to_host( 'specialXhost', 'special.host' ), 'host comparison treats regex metacharacters literally' );
	$refresh_dir = NOOBROCKET_CACHE_PATH . 'example.test/refresh';
	mkdir( $refresh_dir );
	$refresh_files = [];
	foreach ( [ 'index-https.html', 'index-https.html_gzip', 'index-mobile-https.html', 'index-mobile-https.html_gzip' ] as $name ) {
		$refresh_files[] = $refresh_dir . '/' . $name;
		file_put_contents( end( $refresh_files ), 'stale fixture' );
		touch( end( $refresh_files ), time() - 70 );
	}
	$refresh_calls = 0;
	$refresh_allowed = true;
	$purge = new ReflectionMethod( $purger, 'purge_dir' );
	$purge->setAccessible( true );
	$purge->invoke( $purger, $refresh_dir, time() - 60, true, 'https://example.test/', NOOBROCKET_CACHE_PATH . 'example.test' );
	policy_check( 1 === $refresh_calls && 4 === count( array_filter( $refresh_files, 'is_file' ) ), 'plain/gzip/mobile pairs share one refresh admission and preserve original stale bytes' );
	foreach ( $refresh_files as $path ) { touch( $path, time() - 3700 ); }
	clearstatcache();
	$purge->invoke( $purger, $refresh_dir, time() - 60, true, 'https://example.test/', NOOBROCKET_CACHE_PATH . 'example.test' );
	policy_check( 1 === $refresh_calls && 0 === count( array_filter( $refresh_files, 'is_file' ) ), 'hard stale deadline deletes old variants without trying to extend their lifetime' );

	$config_bytes = '<?php $noobrocket_logged_in_cookie = "wordpress_logged_in_fixture"; $noobrocket_cookie_hash = "fixture"; $noobrocket_secret_cache_key = "site-secret"; $noobrocket_cache_reject_cookies = ""; $noobrocket_config_complete = true;';
	file_put_contents( $target, $config_bytes );
	$config = new NoobRocket\Buffer\Config( [ 'config_dir_path' => $temporary, 'server' => [ 'HTTP_HOST' => 'example.test', 'REQUEST_URI' => '/', 'REQUEST_METHOD' => 'GET' ] ] );
	$cookie_name = NoobRocket\Buffer\Cache::get_user_cache_cookie_name( 'fixture', 'site-secret' );
	$cookies = [ 'wordpress_logged_in_fixture' => 'alice|1|invalid-session|invalid-mac', $cookie_name => NoobRocket\Buffer\Cache::get_user_cache_cookie_value( 'alice', time() + 600, 'site-secret' ) ];
	$tests = new NoobRocket\Buffer\Tests( $config, [ 'cookies' => $cookies, 'get' => [], 'post' => [] ] );
	$cache = new NoobRocket\Buffer\Cache( $tests, $config, [ 'cache_dir_path' => NOOBROCKET_CACHE_PATH ] );
	policy_check( '' === $cache->get_cache_path(), 'a valid companion cannot authorize invalid native authentication at early bootstrap' );
	$anonymous = new NoobRocket\Buffer\Tests( $config, [ 'cookies' => [], 'get' => [], 'post' => [] ] );
	$public = new NoobRocket\Buffer\Cache( $anonymous, $config, [ 'cache_dir_path' => NOOBROCKET_CACHE_PATH ] );
	policy_check( false !== strpos( $public->get_cache_path(), '/example.test/index.html' ), 'anonymous public cache path remains available without native authentication' );
	$publication_tests = new PublicationTests( $config, [ 'cookies' => [], 'get' => [], 'post' => [] ] );
	$publication = new PublicationCache( $publication_tests, $config, [ 'cache_dir_path' => NOOBROCKET_CACHE_PATH ] );
	$policy_events = [];
	$generate_allowed = false;
	$publication->maybe_process_buffer( '<html>' . str_repeat( 'fixture ', 40 ) . '</html>' );
	policy_check( ! in_array( 'noobrocket_cache_file_published', $policy_events, true ), 'generation veto never emits successful publication' );
	$generate_allowed = true;
	$publication->maybe_process_buffer( '<html>' . str_repeat( 'fixture ', 40 ) . '</html>' );
	policy_check( in_array( 'noobrocket_cache_file_published', $policy_events, true ) && is_file( $public->get_cache_path() ), 'verified outer writer emits successful publication only after cache bytes exist' );
	echo 'PASS: ', $checks, ' cache policy assertions.', PHP_EOL;
} finally {
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $temporary, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $iterator as $item ) { $item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() ); }
	rmdir( $temporary );
}
