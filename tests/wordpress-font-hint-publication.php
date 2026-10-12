<?php
/** Registered guest beacon/font publication and native page-cache isolation on a disposable copied-plugin site. */
$wordpress = isset( $argv[1] ) ? realpath( $argv[1] ) : false;
if ( ! $wordpress || ! is_file( $wordpress . '/.noobrocket-acceptance-site' ) || ! is_file( $wordpress . '/wp-load.php' ) ) {
	fwrite( STDERR, "Provide a marked disposable WordPress installation.\n" );
	exit( 2 );
}
$source = dirname( __DIR__ ) . '/noobrocket';
$installed = $wordpress . '/wp-content/plugins/noobrocket';
$normalize = static function ( $path ) { return str_replace( '\\', '/', $path ); };
if ( ! realpath( $installed ) || $normalize( realpath( $installed ) ) !== $normalize( $installed ) || realpath( $installed ) === realpath( $source ) ) {
	fwrite( STDERR, "The selected site must use a real plugin copy, not a source junction.\n" );
	exit( 2 );
}
foreach ( [ 'inc/Engine/Media/PreloadFonts/AJAX/Controller.php', 'inc/Engine/Media/PreloadFonts/ServiceProvider.php', 'inc/Engine/Cache/Purge.php' ] as $file ) {
	if ( hash_file( 'sha256', $source . '/' . $file ) !== hash_file( 'sha256', $installed . '/' . $file ) ) {
		fwrite( STDERR, "Copy the current font publication owners before native proof.\n" );
		exit( 2 );
	}
}
$fixture = tempnam( sys_get_temp_dir(), 'nbr-font-hints-' );
unlink( $fixture );
mkdir( $fixture );
define( 'NOOBROCKET_CACHE_ROOT_PATH', $fixture . '/' );
define( 'NOOBROCKET_CONFIG_PATH', $fixture . '/config/' );
define( 'WP_ADMIN', true );
define( 'DOING_AJAX', true );
define( 'DISABLE_WP_CRON', true );
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
define( 'NOOBROCKET_IS_TESTING', true );
$_SERVER['HTTP_HOST'] = '127.0.0.1:8765';
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Mobile';
$_SERVER['HTTP_SEC_CH_UA_MOBILE'] = '?1';
$_SERVER['REQUEST_URI'] = '/wp-admin/admin-ajax.php';
$_SERVER['REQUEST_METHOD'] = 'POST';
require_once $wordpress . '/wp-includes/plugin.php';
$enabled = true;
$font_enabled = static function () use ( &$enabled ) { return (int) $enabled; };
add_filter( 'pre_get_noobrocket_option_auto_preload_fonts', $font_enabled );
add_filter( 'pre_get_noobrocket_option_cache_mobile', '__return_true' );
add_filter( 'pre_get_noobrocket_option_do_caching_mobile_files', '__return_true' );
// A fresh site need not have enabled user caching or created its naming secret yet.
add_filter( 'pre_get_noobrocket_option_secret_cache_key', static function () { return 'owned-font-hint-fixture-secret'; } );
foreach ( [ 'noobrocket_above_the_fold_optimization', 'noobrocket_lrc_optimization', 'noobrocket_preconnect_external_domains_optimization' ] as $hook ) { add_filter( $hook, '__return_false' ); }
require $wordpress . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
wp_set_current_user( 0 );

class NativeFontHintAjaxEnd extends RuntimeException {}
$die_handler = static function () { return static function () { throw new NativeFontHintAjaxEnd(); }; };
add_filter( 'wp_die_ajax_handler', $die_handler );
$checks = 0;
$check = static function ( $condition, $message ) use ( &$checks ) {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
	++$checks;
};
$invoke = static function ( $url, $fonts, $status = 'success' ) {
	$_POST = [ 'action' => 'noobrocket_beacon', 'noobrocket_beacon_nonce' => wp_create_nonce( 'noobrocket_beacon' ), 'url' => $url, 'is_mobile' => 'true', 'status' => $status, 'results' => wp_slash( wp_json_encode( [ 'preload_fonts' => $fonts ] ) ) ];
	$_REQUEST = $_POST;
	ob_start();
	try { do_action( 'wp_ajax_nopriv_noobrocket_beacon' ); } catch ( NativeFontHintAjaxEnd $end ) {} finally { $output = ob_get_clean(); }
	$response = json_decode( $output, true );
	if ( ! is_array( $response ) || ! isset( $response['success'] ) ) { throw new RuntimeException( 'Native beacon did not return JSON.' ); }
	return $response;
};
$container = apply_filters( 'noobrocket_container', null );
$query = $container->get( 'preload_fonts_query' );
$table = $container->get( 'preload_fonts_table' )->get_name();
$home = untrailingslashit( home_url( '/' ) );
$original_home_rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE url=%s", $home ), ARRAY_A );
$settings = get_option( 'noobrocket_settings' );
$request = $GLOBALS['wp']->request;
$post_ids = [];
$owned_urls = [ $home ];
$original_target_rows = [];
$path = rtrim( _noobrocket_get_noobrocket_cache_path(), '/' );
$check( $path === $fixture . '/pages', 'native cache root stays inside the owned fixture' );
$host = get_noobrocket_parse_url( $home )['host'];
$bucket = $path . '/' . $host;
$foreign = $path . '/unrelated.invalid';
$user_bucket = $path . '/' . $host . '-1-' . get_noobrocket_option( 'secret_cache_key' );
$variants = [ 'index.html', 'index-mobile.html', 'index-https.html', 'index-mobile-https.html', 'index.html_gzip', 'index-mobile.html_gzip', 'index-https.html_gzip', 'index-mobile-https.html_gzip' ];
$seed = static function ( $url ) use ( $bucket, $variants ) {
	$directory = $bucket . noobrocket_normalize_cache_url_path( get_noobrocket_parse_url( $url )['path'] );
	wp_mkdir_p( $directory );
	foreach ( $variants as $name ) { file_put_contents( $directory . '/' . $name, 'owned native cache variant' ); }
	return $directory;
};
$retained = static function ( $directory ) use ( $variants ) {
	foreach ( $variants as $name ) { if ( ! is_file( $directory . '/' . $name ) ) { return false; } }
	return true;
};
try {
	$check( false !== has_action( 'wp_ajax_nopriv_noobrocket_beacon' ), 'native guest beacon callback is registered' );
	$query->delete_by_url( $home );
	$query->invalidate_cache();
	wp_mkdir_p( $foreign );
	file_put_contents( $foreign . '/index.html', 'foreign site sentinel' );
	wp_mkdir_p( $user_bucket );
	$check( noobrocket_cache_directory_belongs_to_host( basename( $user_bucket ), $host ), 'authenticated fixture bucket uses the native signed ownership name' );
	file_put_contents( $user_bucket . '/index.html', 'authenticated cache sentinel' );
	wp_mkdir_p( $bucket . '/child/' );
	file_put_contents( $bucket . '/child/index.html', 'child page sentinel' );
	$font = home_url( '/fixture-font.woff2' );
	$home_directory = $seed( $home );
	$reply = $invoke( $home, [ $font ] );
	$check( true === $reply['success'] && 'completed' === $reply['data']['preload_fonts']['status'], 'guest collection completes through the registered native owner' );
	$check( ! $retained( $home_directory ) && is_dir( $home_directory ), 'homepage publication removes its page variants without removing the host bucket' );
	foreach ( $variants as $name ) { $check( ! file_exists( $home_directory . '/' . $name ), 'native homepage variant removed: ' . $name ); }
	$check( 'child page sentinel' === file_get_contents( $bucket . '/child/index.html' ) && 'foreign site sentinel' === file_get_contents( $foreign . '/index.html' ) && 'authenticated cache sentinel' === file_get_contents( $user_bucket . '/index.html' ), 'homepage publication preserves child pages, a sibling host and authenticated cache' );
	$row = $query->get_row( $home, true );
	$check( $row && 'completed' === $row->status && [ $font ] === json_decode( $row->fonts, true ), 'native collected mobile hints remain available after invalidation' );
	$seed( $home );
	$reply = $invoke( $home, [ $font ] );
	$check( 'item already in the database' === $reply['data']['preload_fonts'] && $retained( $home_directory ), 'duplicate collection preserves regenerated page variants' );

	$id = wp_insert_post( [ 'post_title' => 'Native font hint publication', 'post_name' => 'nbr-hints-' . strtolower( wp_generate_password( 8, false, false ) ), 'post_status' => 'publish' ], true );
	$check( ! is_wp_error( $id ), 'create an owned public post' );
	$post_ids[] = $id;
	$url = untrailingslashit( get_permalink( $id ) );
	$check( null === wp_parse_url( $url, PHP_URL_QUERY ), 'native proof requires configured pretty permalinks' );
	$owned_urls[] = $url;
	// Native post creation legitimately invalidates the homepage before this collection.
	$seed( $home );
	$directory = $seed( $url );
	wp_mkdir_p( $directory . '/child/' );
	file_put_contents( $directory . '/child/index.html', 'descendant sentinel' );
	$invoke( $url, [ $font ] );
	foreach ( $variants as $name ) { $check( ! file_exists( $directory . '/' . $name ), 'native post variant removed: ' . $name ); }
	$check( is_file( $directory . '/child/index.html' ) && $retained( $home_directory ), 'post publication preserves descendants and unrelated homepage variants' );
	$GLOBALS['wp']->request = trim( wp_parse_url( $url, PHP_URL_PATH ), '/' );
	$head = apply_filters( 'noobrocket_head', '' );
	$check( false !== strpos( $head, 'as="font"' ) && false !== strpos( $head, esc_attr( $font ) ), 'regenerated native head exposes the collected font preload' );

	foreach ( [ [ 'empty', [], 'success' ], [ 'failed', [ $font ], 'timeout' ] ] as $case ) {
		$target = $url . '/' . $case[0];
		$owned_urls[] = $target;
		$directory = $seed( $target );
		$invoke( $target, $case[1], $case[2] );
		$check( $retained( $directory ), 'empty/failed collection preserves native page variants' );
	}
	$enabled = false;
	$target = $url . '/disabled';
	$owned_urls[] = $target;
	$directory = $seed( $target );
	$reply = $invoke( $target, [ $font ] );
	$check( 'not allowed' === $reply['data']['preload_fonts'] && $retained( $directory ), 'disabled feature cannot invalidate through the existing registered callback' );
	$enabled = true;

	foreach ( [ 'private', 'draft', 'password' ] as $kind ) {
		$id = wp_insert_post( [ 'post_title' => 'Protected font hint fixture', 'post_name' => 'nbr-protected-' . $kind . '-' . strtolower( wp_generate_password( 8, false, false ) ), 'post_status' => 'password' === $kind ? 'publish' : $kind, 'post_password' => 'password' === $kind ? 'fixture-password' : '' ], true );
		$check( ! is_wp_error( $id ), 'create an owned protected post' );
		$post_ids[] = $id;
		$target = untrailingslashit( get_permalink( $id ) );
		$owned_urls[] = $target;
		$directory = $seed( $target );
		$invoke( $target, [ $font ] );
		$check( $retained( $directory ), 'native private/draft/password page variants remain intact' );
	}
	foreach ( [ $url . '?action=save', $url . '#fragment', 'https://unrelated.invalid/' . basename( $url ), admin_url(), rest_url(), home_url( '/feed/' ), $url . '/embed/' ] as $target ) {
		$owned_urls[] = $target;
		$key = untrailingslashit( $target );
		$original_target_rows[ $key ] = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE url=%s", $key ), ARRAY_A );
		$query->delete_by_url( $key );
		$query->invalidate_cache();
		$directory = $seed( get_noobrocket_parse_url( $target )['host'] === $host ? $target : $url );
		wp_mkdir_p( $foreign . '/' . basename( $url ) );
		file_put_contents( $foreign . '/' . basename( $url ) . '/index.html', 'foreign page sentinel' );
		$reply = $invoke( $target, [ $font ] );
		$check( 'completed' === $reply['data']['preload_fonts']['status'], 'refusal proof reaches a new completed collection rather than a duplicate' );
		$check( $retained( $directory ) && is_file( $foreign . '/' . basename( $url ) . '/index.html' ), 'query/fragment/foreign target cannot grant guest purge authority' );
	}
	$check( $settings === get_option( 'noobrocket_settings' ), 'native collection preserves saved settings' );
} finally {
	foreach ( $post_ids as $id ) { wp_delete_post( $id, true ); }
	foreach ( $owned_urls as $url ) { $query->delete_by_url( untrailingslashit( $url ) ); }
	foreach ( $original_home_rows as $row ) { $wpdb->insert( $table, $row ); }
	foreach ( $original_target_rows as $rows ) { foreach ( $rows as $row ) { $wpdb->insert( $table, $row ); } }
	$query->invalidate_cache();
	$check( $original_home_rows === $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE url=%s", $home ), ARRAY_A ), 'fixture restores exact previous homepage hint rows' );
	foreach ( $original_target_rows as $url => $rows ) {
		$check( $rows === $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE url=%s", $url ), ARRAY_A ), 'fixture restores previous fixed-target hint rows' );
	}
	$GLOBALS['wp']->request = $request;
	remove_filter( 'wp_die_ajax_handler', $die_handler );
	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $fixture, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST ) as $entry ) {
		if ( $entry->isLink() ) { unlink( $entry->getPathname() ); } elseif ( $entry->isDir() ) { rmdir( $entry->getPathname() ); } else { unlink( $entry->getPathname() ); }
	}
	rmdir( $fixture );
}
echo 'PASS: native font-hint publication, ', $checks, " assertions; copied plugin, isolated database/cache root, no provider requests.\n";
