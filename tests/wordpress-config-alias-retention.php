<?php
/** Actual config/i18n/subscriber owners; native core hooks/filesystem, modeled site options. */
$core = isset( $argv[1] ) ? realpath( $argv[1] ) : false;
if ( PHP_SAPI !== 'cli' || ! $core || ! is_file( $core . '/wp-includes/plugin.php' ) ) { throw new RuntimeException( 'Provide WordPress core for config retention proof.' ); }
$plugin = dirname( __DIR__ ) . '/noobrocket/';
$owned = sys_get_temp_dir() . '/noobrocket-config-alias-' . bin2hex( random_bytes( 8 ) );
mkdir( $owned );
define( 'ABSPATH', $core . '/' );
define( 'NOOBROCKET_CONFIG_PATH', $owned . '/' );
define( 'NOOBROCKET_SLUG', 'noobrocket_settings' );
define( 'COOKIEHASH', 'config-alias-fixture' );
define( 'LOGGED_IN_COOKIE', 'wordpress_logged_in_config_alias_fixture' );
define( 'FS_CHMOD_FILE', 0644 );
require ABSPATH . 'wp-includes/plugin.php';
require ABSPATH . 'wp-includes/formatting.php';
require ABSPATH . 'wp-includes/http.php';
require ABSPATH . 'wp-includes/class-wp-error.php';
require $plugin . 'vendor/autoload.php';
require $plugin . 'inc/functions/formatting.php';
require $plugin . 'inc/functions/files.php';
require $plugin . 'inc/functions/i18n.php';

$state = [ 'blog' => 1, 'network' => false, 'stack' => [], 'fail' => 0, 'replace' => false, 'events' => [] ];
function is_multisite() { return $GLOBALS['state']['network']; }
function get_current_blog_id() { return $GLOBALS['state']['blog']; }
function get_sites( $arguments ) { return [ 1, 42, 105, 77 ]; }
function switch_to_blog( $id ) { $GLOBALS['state']['stack'][] = get_current_blog_id(); $GLOBALS['state']['blog'] = $id; }
function restore_current_blog() { $GLOBALS['state']['blog'] = array_pop( $GLOBALS['state']['stack'] ); }
function home_url( $path = '' ) { return 'https://site' . get_current_blog_id() . '.example.test' . ( 42 === get_current_blog_id() ? '/subpath' : '' ) . $path; }
function get_option( $name, $default = false ) {
	if ( NOOBROCKET_SLUG === $name ) {
		if ( $GLOBALS['state']['fail'] === get_current_blog_id() ) { throw new RuntimeException( 'Injected config-path failure.' ); }
		return 77 === get_current_blog_id() ? false : [ 'secret_cache_key' => 'site-' . get_current_blog_id() ];
	}
	return 'permalink_structure' === $name ? '/%postname%/' : $default;
}
function noobrocket_get_constant( $name, $default = null ) { return defined( $name ) ? constant( $name ) : $default; }
function get_noobrocket_cache_dynamic_cookies() { return []; }
function get_noobrocket_cache_mandatory_cookies() { return []; }
function noobrocket_get_ignored_parameters() { return []; }
function wp_normalize_path( $path ) { return str_replace( '\\', '/', $path ); }
function mbstring_binary_safe_encoding() {}
function reset_mbstring_encoding() {}
function flush_noobrocket_htaccess() { $GLOBALS['state']['events'][] = 'rules'; }
class ConfigRetentionSubscriber extends NoobRocket\Engine\Cache\AdminSubscriber {
	public function clear_cache( string $current_url, string $old_url ) { $GLOBALS['state']['events'][] = 'cache'; }
}

add_filter( 'noobrocket_generate_advanced_cache_file', static function () { return false; } );
add_filter( 'noobrocket_has_i18n', static function () { return 42 === get_current_blog_id() ? '' : 'fixture'; } );
add_filter( 'noobrocket_i18n_subdomains', static function () { return [ home_url(), 'https://fr.site' . get_current_blog_id() . '.example.test' ]; } );
add_filter( 'noobrocket_config_files_path', static function ( $paths ) {
	$extra = NOOBROCKET_CONFIG_PATH . 'integration.site' . get_current_blog_id() . '.php';
	return $GLOBALS['state']['replace'] ? [ $extra ] : array_merge( $paths, [ $extra ] );
} );
$filesystem = noobrocket_direct_filesystem();
$subscriber = new ConfigRetentionSubscriber(
	new NoobRocket\Engine\Cache\AdvancedCache( $plugin . 'views/cache/', $filesystem ),
	new NoobRocket\Engine\Cache\WPCache( $filesystem ),
	$filesystem
);
$manager = new NoobRocket\Event_Management\Event_Manager();
$manager->add_subscriber( $subscriber );
$checks = 0;
$check = static function ( $condition, $message ) use ( &$checks ) {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
	++$checks;
};
$seed_stale = static function () use ( $owned ) {
	foreach ( [ 'old-domain.php', 'site77.example.test.php' ] as $name ) { file_put_contents( $owned . '/' . $name, "<?php\n\$noobrocket_cookie_hash = 'stale';\n" ); }
	file_put_contents( $owned . '/foreign.php', '<?php /* unrelated owner */' );
	file_put_contents( $owned . '/owned.txt', '$noobrocket_cookie_hash' );
	if ( ! is_dir( $owned . '/directory.php' ) ) { mkdir( $owned . '/directory.php' ); }
};
try {
	$seed_stale();
	noobrocket_generate_config_file();
	$check( is_file( $owned . '/fr.site1.example.test.php' ), 'actual generator writes language-domain alias before cleanup' );
	do_action( 'noobrocket_domain_changed', home_url(), 'https://old.example.test' );
	foreach ( [ 'site1.example.test.php', 'fr.site1.example.test.php', 'integration.site1.php' ] as $name ) {
		$check( is_file( $owned . '/' . $name ), 'registered domain-change regeneration/pruning retains live path: ' . $name );
	}
	$check( ! is_file( $owned . '/old-domain.php' ) && ! is_file( $owned . '/site77.example.test.php' ), 'stale owned and settings-free dormant policies are pruned' );
	$check( is_file( $owned . '/foreign.php' ) && is_file( $owned . '/owned.txt' ) && is_dir( $owned . '/directory.php' ), 'foreign files, non-PHP files and directories survive' );
	$check( [ 'rules', 'cache' ] === $state['events'], 'registered domain-change callback order retains rule/cache behavior' );
	$state['replace'] = true;
	noobrocket_generate_config_file();
	$subscriber->delete_old_configs();
	$check( is_file( $owned . '/integration.site1.php' ) && ! is_file( $owned . '/site1.example.test.php' ) && ! is_file( $owned . '/fr.site1.example.test.php' ), 'replacement path filter defines complete live set without retaining obsolete canonical aliases' );
	$state['replace'] = false;
	$state['network'] = true;
	$seed_stale();
	foreach ( [ 1, 42, 105 ] as $id ) {
		switch_to_blog( $id );
		try { noobrocket_generate_config_file(); } finally { restore_current_blog(); }
	}
	$check( is_file( $owned . '/site42.example.test.subpath.php' ), 'actual shared path owner generates sibling canonical URL after root memoization' );
	$state['blog'] = 105;
	$subscriber->delete_old_configs();
	foreach ( [ 'site1.example.test.php', 'fr.site1.example.test.php', 'integration.site1.php', 'site42.example.test.subpath.php', 'integration.site42.php', 'site105.example.test.php', 'fr.site105.example.test.php', 'integration.site105.php' ] as $name ) {
		$check( is_file( $owned . '/' . $name ), 'multisite retains every active sibling canonical/language/filter path: ' . $name );
	}
	$check( ! is_file( $owned . '/old-domain.php' ) && ! is_file( $owned . '/site77.example.test.php' ), 'network cleanup prunes stale owned and settings-free dormant files' );
	$check( 105 === get_current_blog_id() && [] === $state['stack'], 'network pruning restores originating blog and complete stack' );
	$state['fail'] = 42;
	try {
		$subscriber->delete_old_configs();
		throw new RuntimeException( 'Config-path failure was not propagated.' );
	} catch ( RuntimeException $error ) {
		$check( 'Injected config-path failure.' === $error->getMessage(), 'complete path owner failure propagates before deleting files' );
	}
	$check( 105 === get_current_blog_id() && [] === $state['stack'] && is_file( $owned . '/fr.site105.example.test.php' ), 'failed sibling path calculation restores context and preserves live files' );
	echo 'PASS config alias retention: ', $checks, " checks\n";
} finally {
	if ( dirname( realpath( $owned ) ) !== realpath( sys_get_temp_dir() ) || 0 !== strpos( basename( $owned ), 'noobrocket-config-alias-' ) ) { throw new RuntimeException( 'Owned config fixture cleanup containment failed.' ); }
	foreach ( new DirectoryIterator( $owned ) as $file ) {
		if ( $file->isDot() ) { continue; }
		$file->isDir() ? rmdir( $file->getPathname() ) : unlink( $file->getPathname() );
	}
	rmdir( $owned );
}
