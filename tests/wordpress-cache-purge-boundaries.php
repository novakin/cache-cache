<?php
/** Native URL purge on a marked copied-plugin site; all files belong to this fixture. */
$wordpress = isset( $argv[1] ) ? realpath( $argv[1] ) : false;
if ( PHP_SAPI !== 'cli' || ! $wordpress || ! is_file( $wordpress . '/.noobrocket-acceptance-site' ) ) { throw new RuntimeException( 'Provide a marked disposable WordPress installation.' ); }
foreach ( [ 'inc/functions/files.php', 'inc/common/purge.php', 'inc/Engine/Cache/Purge.php' ] as $owner ) {
	if ( hash_file( 'sha256', dirname( __DIR__ ) . '/noobrocket/' . $owner ) !== hash_file( 'sha256', $wordpress . '/wp-content/plugins/noobrocket/' . $owner ) ) { throw new RuntimeException( 'Installed purge owner differs from candidate.' ); }
}
$_SERVER['HTTP_HOST'] = '127.0.0.1';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'POST';
define( 'WP_ADMIN', true );
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
$early_domain_checked = false;
require_once $wordpress . '/wp-includes/plugin.php';
add_action( 'plugins_loaded', static function () use ( &$early_domain_checked ) {
	if ( ! function_exists( 'noobrocket_clean_domain' ) || function_exists( 'noobrocket_is_importing' ) ) { throw new RuntimeException( 'Expected public purge before API initialization at priority 8.' ); }
	if ( false !== noobrocket_clean_domain() || did_action( 'noobrocket_after_clean_domain' ) ) { throw new RuntimeException( 'Early native domain purge must decline without completion hooks.' ); }
	$early_domain_checked = true;
}, 8 );
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
$post_id = null;
$post_link = null;
foreach ( $owned as $directory ) { wp_mkdir_p( $directory ); }
_noobrocket_get_cache_dirs( '', '', true );
class NativePurgeRefusingFilesystem extends WP_Filesystem_Direct {
	public function delete( $file, $recursive = false, $type = false ) { return false; }
}

try {
	native_purge_check( $early_domain_checked, 'native plugins_loaded priority 8 returns safely before API initialization' );
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
	// Initial creation shares this process; preserve the purge owner's once-per-post request guard for the edit.
	add_filter( 'noobrocket_is_importing', '__return_true' );
	try { $post_id = wp_insert_post( [ 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => $name, 'post_content' => 'Native query-cache original.' ], true ); }
	finally { remove_filter( 'noobrocket_is_importing', '__return_true' ); }
	if ( is_wp_error( $post_id ) ) { throw new RuntimeException( 'Native query-cache post creation failed.' ); }
	$post_link = static function ( $permalink, $post ) use ( $post_id, $name ) { return $post_id === $post->ID ? home_url( '/' . $name ) : $permalink; };
	add_filter( 'post_link', $post_link, 10, 2 );
	$query_files = [];
	foreach ( [ '#test=1', '#test=2', '-neighbor#test=1' ] as $suffix ) {
		$directory = $bucket . '/' . $name . $suffix;
		$owned[] = $directory;
		wp_mkdir_p( $directory );
		$query_files[] = $directory . '/index-mobile-https.html_gzip';
		file_put_contents( end( $query_files ), 'Native query-cache original.' );
	}
	wp_mkdir_p( $owned[0] );
	file_put_contents( $file, 'Native canonical original.' );
	$edited = wp_update_post( [ 'ID' => $post_id, 'post_content' => 'Native query-cache updated.' ], true );
	native_purge_check( ! is_wp_error( $edited ) && ! is_file( $file ) && ! is_file( $query_files[0] ) && ! is_file( $query_files[1] ) && is_file( $query_files[2] ) && is_file( $sentinel ), 'native post update purges nontrailing query siblings while preserving adjacent slugs and foreign buckets' );
	remove_filter( 'post_link', $post_link, 10 );
	$post_link = null;
	wp_delete_post( $post_id, true );
	$post_id = null;
	wp_mkdir_p( $owned[0] );

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
		if ( 'Windows' !== PHP_OS_FAMILY ) { throw new RuntimeException( 'Native directory-link control requires symlink support on this platform.' ); }
		echo "SKIP directory-link native control: OS denied symlink creation; Linux CI required.\n";
	}
	wp_mkdir_p( $owned[0] );
	$domain_file = $owned[0] . '/index.html';
	file_put_contents( $domain_file, 'owned domain cache' );
	$domain_urls = static function () use ( $name ) { return [ home_url( '/' . $name . '/' ) ]; };
	add_filter( 'noobrocket_clean_domain_urls', $domain_urls );
	add_filter( 'noobrocket_is_importing', '__return_true' );
	noobrocket_clean_domain();
	remove_filter( 'noobrocket_is_importing', '__return_true' );
	native_purge_check( is_file( $domain_file ), 'native importing guard retains domain cache' );
	_noobrocket_get_cache_dirs( '', '', true );
	native_purge_check( true === noobrocket_clean_domain() && ! is_file( $domain_file ) && is_file( $sentinel ), 'native ready domain purge removes selected cache and preserves sibling' );
	remove_filter( 'noobrocket_clean_domain_urls', $domain_urls );
} finally {
	if ( $post_link ) { remove_filter( 'post_link', $post_link, 10 ); }
	if ( $post_id ) { wp_delete_post( $post_id, true ); }
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
