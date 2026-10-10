<?php
/** Native URL purge on a marked copied-plugin site; all files belong to this fixture. */
$wordpress = isset( $argv[1] ) ? realpath( $argv[1] ) : false;
if ( PHP_SAPI !== 'cli' || ! $wordpress || ! is_file( $wordpress . '/.noobrocket-acceptance-site' ) ) { throw new RuntimeException( 'Provide a marked disposable WordPress installation.' ); }
$owner = 'noobrocket/inc/functions/files.php';
if ( hash_file( 'sha256', dirname( __DIR__ ) . '/' . $owner ) !== hash_file( 'sha256', $wordpress . '/wp-content/plugins/' . $owner ) ) { throw new RuntimeException( 'Installed purge owner differs from candidate.' ); }
$_SERVER['HTTP_HOST'] = '127.0.0.1';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'POST';
define( 'WP_ADMIN', true );
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
$native_completed = false;
register_shutdown_function( static function () use ( &$native_completed ) {
	if ( ! $native_completed ) { fwrite( STDERR, "FAIL: native cache-purge fixture did not complete.\n" ); exit( 2 ); }
} );
require $wordpress . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
wp_set_current_user( 1 );

$checks = 0;
function native_purge_check( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
	++$GLOBALS['checks'];
}
$name = 'noobrocket-purge-' . bin2hex( random_bytes( 8 ) );
$host = get_noobrocket_parse_url( home_url() )['host'];
$bucket = untrailingslashit( NOOBROCKET_CACHE_PATH ) . '/' . $host;
$bucket_created = ! is_dir( $bucket );
$owned = [ $bucket . '/' . $name, untrailingslashit( NOOBROCKET_CACHE_PATH ) . '/' . $name . '-foreign' ];
foreach ( $owned as $directory ) { wp_mkdir_p( $directory ); }
_noobrocket_get_cache_dirs( '', '', true );
class NativePurgeRefusingFilesystem extends WP_Filesystem_Direct {
	public function delete( $file, $recursive = false, $type = false ) { return false; }
}

try {
	$sentinel = $owned[1] . '/index.html';
	file_put_contents( $sentinel, 'owned sibling sentinel' );
	$url = home_url( '/%2e%2e/' . $name . '-foreign/' );
	$ability = new NoobRocket\Engine\Cache\Abilities\ClearUrlCache();
	native_purge_check( $ability->check_permissions(), 'native administrator may use URL-purge ability' );
	$result = $ability->execute( [ 'url' => $url ] );
	native_purge_check( is_file( $sentinel ) && ! $result['success'], 'native ability preserves sibling and reports path rejection' );
	$wildcard_url = home_url( '/../' . $name . '-foreign/.*html' );
	$result = noobrocket_clean_files( $wildcard_url );
	native_purge_check( is_file( $sentinel ) && ! $result[ $wildcard_url ], 'native wildcard search preserves sibling' );

	$file = $owned[0] . '/index-mobile-https.html_gzip';
	file_put_contents( $file, 'owned device variant' );
	$url = home_url( '/' . $name . '/' );
	$result = noobrocket_clean_files( $url, new NativePurgeRefusingFilesystem( null ) );
	native_purge_check( is_file( $file ) && ! $result[ $url ], 'native filesystem refusal reports failure and retains bytes' );
	$result = noobrocket_clean_files( $url );
	native_purge_check( ! is_file( $file ) && $result[ $url ], 'native filesystem removes selected device cache' );

	$assets = $owned[0] . '/file1.css-parent/assets';
	wp_mkdir_p( $assets );
	file_put_contents( $assets . '/file2.css', 'owned match' );
	file_put_contents( $assets . '/keep.js', 'owned unmatched file' );
	$wildcard_url = home_url( '/' . $name . '/file1.css-parent/assets/file.*css' );
	$result = noobrocket_clean_files( $wildcard_url );
	native_purge_check( ! is_file( $assets . '/file2.css' ) && is_file( $assets . '/keep.js' ) && $result[ $wildcard_url ], 'native wildcard matches filename without matching parent path' );

	wp_mkdir_p( $owned[0] );
	$link = $owned[0] . '/linked-directory';
	if ( @symlink( $owned[1], $link ) ) {
		$result = noobrocket_clean_files( $url );
		native_purge_check( ! is_link( $link ) && is_file( $sentinel ) && $result[ $url ], 'native recursion unlinks directory link and preserves target' );
	} else {
		echo "SKIP directory-link native control: OS denied symlink creation; Linux CI required.\n";
	}
} finally {
	foreach ( $owned as $directory ) {
		if ( ! is_dir( $directory ) ) { continue; }
		$resolved = realpath( $directory );
		$cache_root = realpath( NOOBROCKET_CACHE_PATH );
		if ( ! $resolved || ! $cache_root || 0 !== strpos( wp_normalize_path( $resolved ), trailingslashit( wp_normalize_path( $cache_root ) ) ) || 0 !== strpos( basename( $directory ), $name ) ) { throw new RuntimeException( 'Owned cleanup containment failed.' ); }
		$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $iterator as $entry ) {
			$entry->isDir() && ! $entry->isLink() ? rmdir( $entry->getPathname() ) : unlink( $entry->getPathname() );
		}
		rmdir( $directory );
	}
	if ( $bucket_created && is_dir( $bucket ) && 0 === iterator_count( new FilesystemIterator( $bucket ) ) ) { rmdir( $bucket ); }
	_noobrocket_get_cache_dirs( '', '', true );
}
$native_completed = true;
echo 'PASS native cache purge: ', $checks, " checks\n";
