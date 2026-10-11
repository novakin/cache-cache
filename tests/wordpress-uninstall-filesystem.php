<?php
/** Registered native uninstall in a marked copied-plugin site; owned roots and sentinels only. */
declare(strict_types=1);
$wordpress = isset( $argv[1] ) ? realpath( $argv[1] ) : false;
if ( PHP_SAPI !== 'cli' || ! $wordpress || ! is_file( $wordpress . '/.noobrocket-acceptance-site' ) ) { throw new RuntimeException( 'Provide a marked disposable WordPress installation.' ); }
foreach ( [ 'noobrocket/inc/Engine/NoobRocketUninstall.php', 'noobrocket/uninstall.php' ] as $owner ) {
	if ( hash_file( 'sha256', dirname( __DIR__ ) . '/' . $owner ) !== hash_file( 'sha256', $wordpress . '/wp-content/plugins/' . $owner ) ) { throw new RuntimeException( 'Installed uninstall owner differs from candidate.' ); }
}
require __DIR__ . '/uninstall-filesystem-helper.php';
$area = $wordpress . '/wp-content/.noobrocket-uninstall-' . bin2hex( random_bytes( 6 ) );
mkdir( $area );
$area = realpath( $area );
$cache = $area . '/cache';
$config = $area . '/config';
$outside = $area . '/cache-foreign';
foreach ( [ $cache . '/min/deep', $cache . '/fonts/deep', $config . '/deep', $outside ] as $directory ) { mkdir( $directory, 0777, true ); }
define( 'NOOBROCKET_CACHE_ROOT_PATH', $cache . '/' );
define( 'NOOBROCKET_CONFIG_PATH', $config . '/' );
$_SERVER['HTTP_HOST'] = '127.0.0.1:8765';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'POST';
define( 'WP_ADMIN', true );
$completed = false;
register_shutdown_function( static function () use ( &$completed ) {
	if ( ! $completed ) { fwrite( STDERR, "FAIL: native uninstall fixture did not complete.\n" ); exit( 2 ); }
} );
require $wordpress . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
wp_set_current_user( 1 );
$checks = 0;
function native_uninstall_check( bool $condition, string $message ): void {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
	++$GLOBALS['checks'];
}
$links = [];
try {
	$plugin = 'noobrocket/noobrocket.php';
	deactivate_plugins( $plugin );
	foreach ( [ $cache . '/min/deep', $cache . '/fonts/deep', $config . '/deep' ] as $directory ) { if ( ! is_dir( $directory ) ) { mkdir( $directory, 0777, true ); } }
	file_put_contents( $outside . '/sentinel.txt', 'owned external sentinel' );
	file_put_contents( $cache . '/min/deep/owned.css', 'owned cache' );
	file_put_contents( $cache . '/fonts/deep/owned.woff2', 'owned font' );
	file_put_contents( $config . '/deep/owned.php', 'owned config' );
	foreach ( [ $cache . '/pages', $cache . '/fonts/deep/link', $config . '/deep/link' ] as $entry ) { uninstall_create_directory_link( $outside, $entry ); $links[] = [ $outside, $entry ]; }
	$foreign = [ 'uninstall_boundary_sentinel' => true ];
	update_option( 'wp_rocket_settings', $foreign );
	update_option( 'noobrocket_settings', [ 'uninstall_boundary_sentinel' => true ] );
	$dropin = WP_CONTENT_DIR . '/advanced-cache.php';
	file_put_contents( $dropin, '<?php /* OtherCache uninstall boundary sentinel */' );
	uninstall_plugin( $plugin );
	native_uninstall_check( false === get_option( 'noobrocket_settings', false ), 'registered uninstall removes its persisted settings' );
	native_uninstall_check( $foreign === get_option( 'wp_rocket_settings' ) && false !== strpos( file_get_contents( $dropin ), 'OtherCache uninstall boundary sentinel' ), 'registered uninstall preserves foreign settings and drop-in' );
	native_uninstall_check( 'owned external sentinel' === file_get_contents( $outside . '/sentinel.txt' ), 'native deletion never reaches external directory-link target' );
	native_uninstall_check( ! is_file( $cache . '/min/deep/owned.css' ) && ! is_dir( $cache . '/min' ), 'native ordinary cache cleanup succeeds' );
	native_uninstall_check( ! is_file( $cache . '/fonts/deep/owned.woff2' ) && ! is_file( $config . '/deep/owned.php' ), 'native ordinary files beside links are removed' );
	foreach ( $links as $link ) { native_uninstall_check( is_dir( $link[1] ) && realpath( $link[1] ) === realpath( $outside ), 'native redirected directory entry remains without traversal' ); }
	$completed = true;
	echo 'PASS: ', $checks, ' native registered-uninstall assertions; WordPress ', $wp_version, '; ', 'Windows' === PHP_OS_FAMILY ? 'Windows junction is_link=false' : 'Linux directory symlink', '.', PHP_EOL;
} finally {
	foreach ( array_reverse( $links ) as $link ) { uninstall_remove_directory_link( $link[0], $link[1] ); }
	uninstall_remove_owned_tree( $area );
}
