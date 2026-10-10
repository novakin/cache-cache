<?php
/** Actual uninstall owner, native owned files, modeled WordPress deletion boundary; no site boot. */
declare(strict_types=1);
require __DIR__ . '/uninstall-filesystem-helper.php';
$area = sys_get_temp_dir() . '/noobrocket-uninstall-' . bin2hex( random_bytes( 6 ) );
mkdir( $area );
$area = realpath( $area );
$cache = $area . '/cache';
$config = $area . '/config';
$outside = $area . '/cache-foreign';
$links = $selected = [];
$checks = 0;
foreach ( [ $cache, $config, $outside, $cache . '/min/deep', $cache . '/fonts/deep', $cache . '/foreign', $config . '/deep', $outside . '/pages' ] as $directory ) { if ( ! is_dir( $directory ) ) { mkdir( $directory, 0777, true ); } }
file_put_contents( $outside . '/sentinel.txt', 'owned external sentinel' );
file_put_contents( $outside . '/pages/sentinel.txt', 'owned external pages sentinel' );
file_put_contents( $cache . '/foreign/sentinel.txt', 'foreign cache data' );
file_put_contents( $cache . '/min/deep/owned.css', 'owned cache' );
file_put_contents( $cache . '/fonts/deep/owned.woff2', 'owned font' );
file_put_contents( $config . '/deep/owned.php', 'owned config' );
function trailingslashit( $path ) { return rtrim( $path, '/\\' ) . '/'; }
function wp_delete_file( $path ) {
	$path = (string) $path;
	$resolved = realpath( (string) $path );
	$GLOBALS['selected'][] = [ 'path' => (string) $path, 'resolved' => $resolved ];
	// The fail-before control records external selection without deleting its sentinel.
	if ( false !== $resolved && 0 === strpos( str_replace( '\\', '/', $resolved ), str_replace( '\\', '/', $GLOBALS['outside'] ) . '/' ) ) { return; }
	if ( file_exists( $path ) || is_link( $path ) ) { unlink( $path ); }
}
function uninstall_fs_check( bool $condition, string $message ): void {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
	++$GLOBALS['checks'];
}
$owner = getenv( 'NOOBROCKET_UNINSTALL_OWNER' ) ?: dirname( __DIR__ ) . '/noobrocket/inc/Engine/NoobRocketUninstall.php';
require $owner;
$cleanup_cache = new ReflectionMethod( NoobRocketUninstall::class, 'delete_cache_files' );
$cleanup_cache->setAccessible( true );
$cleanup_config = new ReflectionMethod( NoobRocketUninstall::class, 'delete_config_files' );
$cleanup_config->setAccessible( true );
try {
	foreach ( [ $cache . '/pages', $cache . '/fonts/deep/link', $config . '/deep/link' ] as $entry ) { uninstall_create_directory_link( $outside, $entry ); $links[] = [ $outside, $entry ]; }
	$uninstaller = new NoobRocketUninstall( $cache, $config . '/', [] );
	$cleanup_cache->invoke( $uninstaller );
	$cleanup_config->invoke( $uninstaller );
	$outside_selections = array_filter( $selected, static function ( $entry ) use ( $outside ) { return false !== $entry['resolved'] && 0 === strpos( str_replace( '\\', '/', $entry['resolved'] ), str_replace( '\\', '/', $outside ) . '/' ); } );
	uninstall_fs_check( [] === $outside_selections, 'external sentinel never selected through top-level or nested directory links' );
	uninstall_fs_check( is_file( $outside . '/sentinel.txt' ) && is_file( $outside . '/pages/sentinel.txt' ), 'external target bytes survive' );
	uninstall_fs_check( ! is_file( $cache . '/min/deep/owned.css' ) && ! is_dir( $cache . '/min' ), 'ordinary owned cache recursively removed' );
	uninstall_fs_check( ! is_file( $cache . '/fonts/deep/owned.woff2' ) && ! is_file( $config . '/deep/owned.php' ), 'ordinary files removed beside preserved nested links' );
	uninstall_fs_check( is_file( $cache . '/foreign/sentinel.txt' ), 'unlisted cache directory remains' );
	foreach ( $links as $link ) { uninstall_fs_check( is_dir( $link[1] ) && realpath( $link[1] ) === realpath( $outside ), 'redirected directory entry preserved without traversal' ); }
	foreach ( [ [ $area . '/linked-cache', $cleanup_cache ], [ $area . '/linked-config', $cleanup_config ] ] as $control ) {
		uninstall_create_directory_link( $outside, $control[0] ); $links[] = [ $outside, $control[0] ];
		$before = count( $selected );
		$instance = new NoobRocketUninstall( $control[0], $control[0] . '/', [] );
		$control[1]->invoke( $instance );
		uninstall_fs_check( $before === count( $selected ) && is_file( $outside . '/pages/sentinel.txt' ), 'redirected configured root is preserved without descent' );
	}
	echo 'PASS: ', $checks, ' uninstall filesystem assertions; ', 'Windows' === PHP_OS_FAMILY ? 'Windows junction is_link=false' : 'Linux directory symlink', '; real owned files; modeled WordPress boundary.', PHP_EOL;
} finally {
	foreach ( array_reverse( $links ) as $link ) { uninstall_remove_directory_link( $link[0], $link[1] ); }
	uninstall_remove_owned_tree( $area );
}
