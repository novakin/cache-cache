<?php
/** Standalone activation/deactivation ownership proof; no WordPress site writes. */
declare(strict_types=1);

$fixture = sys_get_temp_dir() . '/noobrocket-ownership-' . bin2hex( random_bytes( 6 ) );
mkdir( $fixture );
define( 'ABSPATH', $fixture . '/' );
define( 'WP_CONTENT_DIR', $fixture );
define( 'NOOBROCKET_FILE', dirname( __DIR__ ) . '/noobrocket/noobrocket.php' );
define( 'FS_CHMOD_FILE', 0644 );
$hooks = [];
function add_filter( $name, $callback ) { $GLOBALS['hooks'][ $name ][] = $callback; }
function register_activation_hook( $file, $callback ) {}
function apply_filters( $name, $value ) { foreach ( $GLOBALS['hooks'][ $name ] ?? [] as $callback ) { $value = $callback( $value ); } return $value; }
function get_option( $name, $default ) { return $default; }
function get_site_option( $name, $default ) { return $default; }
function esc_html__( $value ) { return $value; }
function wp_die( $message ) { throw new RuntimeException( $message ); }
function current_filter() { return 'noobrocket_deactivation'; }
function is_multisite() { return false; }
function is_noobrocket_generate_caching_mobile_files() { return false; }
function noobrocket_get_constant( $name, $default = null ) {
	if ( defined( $name ) ) { return constant( $name ); }
	return $default ?? '';
}
class OwnershipFilesystem {
	public $writes = 0;
	public function get_contents( $path ) { return file_get_contents( $path ); }
	public function put_contents( $path, $value, $mode ) { $this->writes++; return false !== file_put_contents( $path, $value ); }
}
require dirname( __DIR__ ) . '/noobrocket/inc/free.php';
require dirname( __DIR__ ) . '/noobrocket/inc/functions/files.php';
require dirname( __DIR__ ) . '/noobrocket/inc/Engine/Activation/ActivationInterface.php';
require dirname( __DIR__ ) . '/noobrocket/inc/Engine/Deactivation/DeactivationInterface.php';
require dirname( __DIR__ ) . '/noobrocket/inc/Engine/Cache/AdvancedCache.php';
$dropin = $fixture . '/advanced-cache.php';
$filesystem = new OwnershipFilesystem();
$advanced = new NoobRocket\Engine\Cache\AdvancedCache( dirname( __DIR__ ) . '/noobrocket/views/cache/', $filesystem );
$checks = 0;
function ownership_check( bool $condition, string $message ): void { if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); } $GLOBALS['checks']++; }
try {
	ownership_check( noobrocket_can_manage_advanced_cache(), 'missing drop-in allows activation' );
	foreach ( [ "<?php\n// OtherCache supports NoobRocket compatibility\n", "<?php\n// OtherCache checks NOOBROCKET_ADVANCED_CACHE before starting\n" ] as $foreign ) {
		file_put_contents( $dropin, $foreign );
		ownership_check( ! noobrocket_can_manage_advanced_cache(), 'mentioning the plugin or constant never grants drop-in ownership' );
		try { noobrocket_activation_preflight(); throw new LogicException( 'Expected foreign ownership rejection' ); }
		catch ( RuntimeException $error ) { ownership_check( false !== strpos( $error->getMessage(), 'Another cache plugin owns advanced-cache.php' ), 'activation rejects the actual foreign drop-in' ); }
		$advanced->update_advanced_cache();
		ownership_check( $foreign === file_get_contents( $dropin ) && 0 === $filesystem->writes, 'actual deactivation callback preserves a foreign drop-in containing NoobRocket text' );
	}
	$own = file_get_contents( dirname( __DIR__ ) . '/noobrocket/views/cache/advanced-cache.php' );
	file_put_contents( $dropin, $own );
	ownership_check( noobrocket_can_manage_advanced_cache() && ! noobrocket_can_manage_advanced_cache( false ), 'own generated loader is recognized while earlier veto remains authoritative' );
	noobrocket_activation_preflight();
	$advanced->update_advanced_cache();
	ownership_check( '' === file_get_contents( $dropin ) && 1 === $filesystem->writes, 'actual deactivation clears its owned loader' );
	ownership_check( noobrocket_can_manage_advanced_cache(), 'empty shared slot can be reused' );
	echo 'PASS: ', $checks, ' cache ownership assertions.', PHP_EOL;
} finally { if ( is_file( $dropin ) ) { unlink( $dropin ); } rmdir( $fixture ); }
