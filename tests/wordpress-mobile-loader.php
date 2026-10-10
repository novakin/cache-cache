<?php
/** Actual generated loader/config/cache owners; modeled request boundaries, no WP bootstrap. */
$core = isset( $argv[1] ) ? realpath( $argv[1] ) : false;
if ( PHP_SAPI !== 'cli' || ! $core || ! is_file( $core . '/wp-admin/includes/class-wp-filesystem-direct.php' ) ) { throw new RuntimeException( 'Provide WordPress core for the generated-loader fixture.' ); }
$plugin = dirname( __DIR__ ) . '/noobrocket/';
$state = [ 'network' => false, 'mobile' => false ];
function noobrocket_get_constant( $name, $default = null ) { return defined( $name ) ? constant( $name ) : $default; }
function is_noobrocket_generate_caching_mobile_files() { return $GLOBALS['state']['mobile']; }
function is_multisite() { return $GLOBALS['state']['network']; }
function apply_filters( $hook, $value, ...$args ) { return $value; }
function is_admin() { return false; }
function do_action( $hook, ...$args ) {}

if ( '--request' === ( $argv[2] ?? '' ) ) {
	define( 'ABSPATH', $core . '/' );
	define( 'NOOBROCKET_DEBUG', false );
	$agents = [
		'phone' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 Version/16.0 Mobile/15E148 Safari/604.1',
		'tablet' => 'Mozilla/5.0 (iPad; CPU OS 16_0 like Mac OS X) AppleWebKit/605.1.15 Version/16.0 Mobile/15E148 Safari/604.1',
		'desktop' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/143.0.0.0 Safari/537.36',
	];
	$_SERVER = [ 'HTTP_HOST' => 'network.example.test', 'REQUEST_URI' => '/subsite/page/', 'REQUEST_METHOD' => 'POST', 'HTTP_USER_AGENT' => $agents[ $argv[4] ] ];
	if ( '1' === $argv[5] ) { require $plugin . 'inc/Dependencies/Detection/MobileDetect.php'; }
	require $argv[3];
	$config = $noobrocket_config_class;
	$cache = new NoobRocket\Buffer\Cache( new NoobRocket\Buffer\Tests( $config ), $config, [ 'cache_dir_path' => $noobrocket_cache_path ] );
	$filename = new ReflectionMethod( $cache, 'maybe_mobile_filename' );
	$filename->setAccessible( true );
	echo json_encode( [
		'detector' => class_exists( 'NoobRocket\Dependencies\Detection\MobileDetect', false ),
		'filename' => $filename->invoke( $cache, 'index' ),
		'config' => basename( $config->get_config_file_path()['path'] ),
	] ), PHP_EOL;
	exit;
}

$owned = sys_get_temp_dir() . '/noobrocket-mobile-loader-' . bin2hex( random_bytes( 8 ) );
mkdir( $owned );
mkdir( $owned . '/configs' );
mkdir( $owned . '/cache' );
define( 'ABSPATH', $core . '/' );
define( 'WP_CONTENT_DIR', $owned );
define( 'NOOBROCKET_PATH', $plugin );
define( 'NOOBROCKET_CONFIG_PATH', $owned . '/configs/' );
define( 'NOOBROCKET_CACHE_PATH', $owned . '/cache/' );
define( 'NOOBROCKET_PHP_VERSION', '7.4' );
require ABSPATH . 'wp-includes/class-wp-error.php';
require ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
require ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
require $plugin . 'inc/Engine/Activation/ActivationInterface.php';
require $plugin . 'inc/Engine/Deactivation/DeactivationInterface.php';
require $plugin . 'inc/Engine/Cache/AdvancedCache.php';
$owner = new NoobRocket\Engine\Cache\AdvancedCache( $plugin . 'views/cache/', new WP_Filesystem_Direct( null ) );
$checks = 0;
$check = static function ( $condition, $message ) use ( &$checks ) {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
	++$checks;
};

try {
	$state = [ 'network' => true, 'mobile' => false ];
	$disabled_save = $owner->get_advanced_cache_content();
	$state['mobile'] = true;
	$check( $disabled_save === $owner->get_advanced_cache_content(), 'shared network loader does not depend on the saving site mobile settings' );
	$cases = [
		[ false, false, 0, 0, 'desktop', 'phone', false, false, 'index' ],
		[ false, true, 1, 1, 'desktop', 'phone', false, true, 'index-mobile' ],
		[ true, false, 1, 1, 'desktop', 'phone', false, true, 'index-mobile' ],
		[ true, true, 0, 0, 'desktop', 'phone', false, false, 'index' ],
		[ true, false, 1, 0, 'desktop', 'phone', false, false, 'index' ],
		[ true, false, 0, 1, 'desktop', 'phone', false, false, 'index' ],
		[ true, false, 1, 1, 'desktop', 'desktop', false, true, 'index' ],
		[ true, false, 1, 1, 'desktop', 'tablet', false, true, 'index' ],
		[ true, false, 1, 1, 'mobile', 'tablet', false, true, 'index-mobile' ],
		[ true, false, null, null, 'desktop', 'phone', false, false, 'index' ],
		[ true, false, 1, 1, 'desktop', 'phone', true, true, 'index-mobile' ],
	];
	foreach ( $cases as $index => $case ) {
		list( $state['network'], $state['mobile'], $mobile, $separate, $tablet, $agent, $preloaded, $detector, $filename ) = $case;
		$root_config = $owned . '/configs/network.example.test.php';
		$sub_config = $owned . '/configs/network.example.test.subsite.php';
		foreach ( [ $root_config, $sub_config ] as $file ) { if ( is_file( $file ) ) { unlink( $file ); } }
		if ( null !== $mobile ) {
			$root_mobile = 1 === $mobile && 1 === $separate ? 0 : 1;
			file_put_contents( $root_config, "<?php\n\$noobrocket_cache_mobile = $root_mobile;\n\$noobrocket_do_caching_mobile_files = $root_mobile;\n\$noobrocket_config_complete = true;\n" );
			file_put_contents( $sub_config, "<?php\n\$noobrocket_cache_mobile = $mobile;\n\$noobrocket_do_caching_mobile_files = $separate;\n\$noobrocket_cache_mobile_files_tablet = '$tablet';\n\$noobrocket_config_complete = true;\n" );
		}
		$content = $owner->get_advanced_cache_content();
		$check( ( $state['network'] || $state['mobile'] ) === ( false !== strpos( $content, 'Detection/MobileDetect.php' ) ), 'optional detector block follows network/single-site policy: ' . $index );
		$check( false === strpos( $content, '{{' ), 'generated loader contains no unresolved template markers: ' . $index );
		$loader = $owned . '/generated.php';
		file_put_contents( $loader, $content );
		$worker = proc_open( [ PHP_BINARY, __FILE__, $core, '--request', $loader, $agent, $preloaded ? '1' : '0' ], [ 0 => [ 'pipe', 'r' ], 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ], $pipes );
		if ( ! is_resource( $worker ) ) { throw new RuntimeException( 'Cannot start isolated early-loader request.' ); }
		fclose( $pipes[0] );
		$output = stream_get_contents( $pipes[1] );
		$error = stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		$check( 0 === proc_close( $worker ) && '' === $error, 'fresh early-loader process completes without PHP diagnostics: ' . $index );
		$result = json_decode( $output, true );
		$check( is_array( $result ) && $result['detector'] === $detector && $result['filename'] === $filename, 'selected request policy chooses detector/device variant: ' . $index );
		if ( null !== $mobile ) { $check( 'network.example.test.subsite.php' === $result['config'], 'subdirectory configuration wins over root policy: ' . $index ); }
	}
	echo 'PASS generated mobile loader: ', $checks, " checks\n";
} finally {
	if ( dirname( realpath( $owned ) ) !== realpath( sys_get_temp_dir() ) || 0 !== strpos( basename( $owned ), 'noobrocket-mobile-loader-' ) ) { throw new RuntimeException( 'Owned mobile-loader cleanup containment failed.' ); }
	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $owned, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST ) as $file ) {
		$file->isDir() ? rmdir( $file->getPathname() ) : unlink( $file->getPathname() );
	}
	rmdir( $owned );
}
