<?php
/**
 * Real local CSS/native font ownership and delivery regression.
 * php tests/wordpress-local-css-fonts.php /absolute/path/to/disposable/wordpress
 * Run serially; restores fixture posts, job rows, settings and cron.
 */
$wordpress = isset( $argv[1] ) ? realpath( $argv[1] ) : false;
if ( ! $wordpress || ! is_file( $wordpress . '/wp-load.php' ) || ! is_file( $wordpress . '/.noobrocket-acceptance-site' ) ) {
	fwrite( STDERR, "Provide a disposable WordPress installation with the .noobrocket-acceptance-site marker.\n" );
	exit( 2 );
}
$fixture = tempnam( sys_get_temp_dir(), 'noobrocket-local-fonts-' );
unlink( $fixture );
mkdir( $fixture );
define( 'NOOBROCKET_CACHE_ROOT_PATH', $fixture . '/' );
define( 'NOOBROCKET_CONFIG_PATH', $fixture . '/config/' );
define( 'WP_ADMIN', false );
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
define( 'DONOTNOOBROCKETOPTIMIZE', false );
define( 'NOOBROCKET_IS_TESTING', true );
$_SERVER['HTTP_HOST'] = '127.0.0.1:8765';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'POST';
require $wordpress . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';

$checks = 0;
function local_fonts_check( $condition, $message ) {
	global $checks;
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
	$checks++;
}
function local_fonts_remove_fixture( $directory ) {
	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST ) as $file ) {
		$file->isDir() ? rmdir( $file->getPathname() ) : unlink( $file->getPathname() );
	}
	rmdir( $directory );
}
function local_fonts_complete( $html ) {
	global $frontend, $jobs, $url;
	$frontend->optimize( $html );
	$job = $jobs->claim();
	local_fonts_check( $job && untrailingslashit( $job['url'] ) === untrailingslashit( $url ), 'claim the fixture URL' );
	$results = array_map( function ( $sheet ) { return [ 'href' => $sheet['href'], 'css' => '.fixture{color:red}' ]; }, $job['styles'] );
	local_fonts_check( $jobs->complete( $job['id'], $job['token'], $job['fingerprint'], $results ), 'publish the exact fixture manifest' );
	return $frontend->optimize( $html );
}

$container = apply_filters( 'noobrocket_container', null );
$jobs = $container->get( 'local_css_jobs' );
$frontend = $container->get( 'local_css_frontend' );
$fonts = $container->get( 'media_fonts_frontend_controller' );
$filesystem = $container->get( 'media_fonts_filesystem' );
$table = $container->get( 'rucss_usedcss_table' )->get_name();
$rows_before = $wpdb->get_results( "SELECT * FROM $table", ARRAY_A );
$font_hint_table = $container->get( 'preload_fonts_table' )->get_name();
$font_hints_before = $wpdb->get_results( "SELECT * FROM $font_hint_table", ARRAY_A );
$font_hint_query = $container->get( 'preload_fonts_query' );
$font_queue_store = ActionScheduler_Store::instance();
$font_actions_before = $font_queue_store->query_actions( [ 'hook' => 'noobrocket_prepare_local_font', 'per_page' => -1 ] );
$settings_before = get_option( 'noobrocket_settings' );
$cron_before = get_option( 'cron' );
$globals_before = [ 'wp_query' => $GLOBALS['wp_query'], 'wp_the_query' => $GLOBALS['wp_the_query'], 'post' => $GLOBALS['post'] ?? null ];
$request_before = $GLOBALS['wp']->request;
$user_before = get_current_user_id();
$post_id = 0;
$mode = 'critical';
$hosting = true;
$mode_filter = function () use ( &$mode ) { return $mode; };
$hosting_filter = function () use ( &$hosting ) { return $hosting; };
$font_http = [];
$font_http_calls = [];
$block_http = function ( $preempt, $args, $request_url ) use ( &$font_http, &$font_http_calls ) {
	$font_http_calls[] = $request_url;
	if ( isset( $font_http[ $request_url ] ) ) {
		return [ 'headers' => [], 'response' => [ 'code' => 200 ], 'body' => $font_http[ $request_url ] ];
	}
	return new WP_Error( 'local_font_fixture_http', 'Network disabled by local font fixture.' );
};
$font_exclusion = function () { return [ 'family=NoobFixture' ]; };
add_filter( 'pre_get_noobrocket_option_local_css_mode', $mode_filter );
add_filter( 'pre_get_noobrocket_option_host_fonts_locally', $hosting_filter );
add_filter( 'pre_http_request', $block_http, PHP_INT_MAX - 1, 3 );
add_filter( 'noobrocket_generate_advanced_cache_file', '__return_false' );
add_filter( 'noobrocket_disable_htaccess', '__return_true' );
add_filter( 'pre_get_noobrocket_option_manual_preload', '__return_false' );
$wpdb->query( "UPDATE $table SET status='fixture-held' WHERE queue_name IN ('local-critical','local-used')" );

try {
	add_filter( 'noobrocket_is_importing', '__return_true' );
	$post_id = wp_insert_post( [ 'post_title' => 'Native local font integration fixture', 'post_name' => 'nbr-fonts-' . strtolower( wp_generate_password( 8, false, false ) ), 'post_status' => 'publish' ], true );
	local_fonts_check( ! is_wp_error( $post_id ) && $post_id > 0, 'create disposable public post' );
	remove_filter( 'noobrocket_is_importing', '__return_true' );
	wp_set_current_user( 0 );
	$GLOBALS['wp_query'] = new WP_Query( [ 'p' => $post_id ] );
	$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
	$GLOBALS['post'] = get_post( $post_id );
	$url = get_permalink( $post_id );
	$GLOBALS['wp']->request = trim( wp_parse_url( $url, PHP_URL_PATH ), '/' );
	$_SERVER['REQUEST_URI'] = wp_parse_url( $url, PHP_URL_PATH );
	$_SERVER['REQUEST_METHOD'] = 'GET';
	$_GET = [];
	$font_url = 'https://fonts.googleapis.com/css2?family=NoobFixture';
	$font_tag = '<link id="fixture-google-font" rel="stylesheet" href="' . $font_url . '" media="all">';
	$main_tag = '<link id="fixture-main-css" rel="stylesheet" href="' . home_url( '/fixture-main.css' ) . '" media="all">';
	$html = '<html><head>' . $font_tag . $main_tag . '</head><body class="fixture">Native local fonts.</body></html>';
	$font_css = '@font-face{font-family:"NoobFixture";font-weight:400;src:url("' . home_url( '/fixture-local-font.woff2' ) . '") format("woff2");font-display:swap}';
	$font_path = $fixture . '/fonts/' . get_current_blog_id() . '/google-fonts/css/' . $filesystem->hash_to_path( md5( $font_url ) ) . '.css';
	wp_mkdir_p( dirname( $font_path ) );
	file_put_contents( $font_path, $font_css );

	foreach ( [ $font_tag, '<link href="//fonts.googleapis.com/css?family=NoobFixture" rel="stylesheet">', "<link rel='stylesheet' href='http://fonts.googleapis.com/css?family=NoobFixture'>" ] as $tag ) {
		local_fonts_check( $fonts->owns_font_link( $tag ), 'native quoted css/css2 and protocol-relative font link is owned' );
	}
	foreach ( [ '<link rel="stylesheet" href=https://fonts.googleapis.com/css2?family=NoobFixture>', '<link rel="stylesheet" href="https://fonts.googleapis.com/css3?family=NoobFixture">', '<link rel="stylesheet" href="/css2?family=NoobFixture">', '<link rel="stylesheet" href="https://example.invalid/css2?family=NoobFixture">' ] as $tag ) {
		local_fonts_check( ! $fonts->owns_font_link( $tag ), 'native unsupported font link is not claimed' );
	}
	local_fonts_check( $frontend->optimize( $html ) === $html && ! apply_filters( 'noobrocket_host_fonts_locally_inline_css', false ), 'pending critical generation preserves originals and native inline default' );
	$manifest = json_decode( $jobs->find( $url )->css, true )['styles'];
	local_fonts_check( count( $manifest ) === 1 && $manifest[0]['href'] === home_url( '/fixture-main.css' ), 'native Google sheet stays outside the local CSS manifest' );
	$critical = local_fonts_complete( $html );
	local_fonts_check( $frontend->has_critical_css() && substr_count( $critical, 'data-noobrocket-local-css="critical"' ) === 1 && strpos( $critical, $font_tag ) !== false, 'ready critical CSS leaves original Google link native-owned' );
	$hosted = $fonts->rewrite_fonts_for_optimizations( $critical );
	local_fonts_check( substr_count( $hosted, $font_css ) === 1 && strpos( $hosted, '<style data-noobrocket-hosted-gf-parameters=' ) !== false, 'actual native controller inlines exact locally cached font CSS once' );
	local_fonts_check( strpos( $hosted, 'fonts.googleapis.com' ) === false && strpos( $hosted, 'fonts.gstatic.com' ) === false && strpos( $hosted, '/google-fonts/css/' ) === false, 'integrated delivery has local font URLs without remote faces or an extra font stylesheet' );
	local_fonts_check( strpos( $hosted, 'id="fixture-main-css"' ) !== false && strpos( $hosted, 'media="print"' ) !== false && strpos( $hosted, '<noscript>' . $main_tag ) !== false, 'font hosting preserves deferred main CSS and its noscript fallback' );
	add_filter( 'noobrocket_host_fonts_locally_inline_css', '__return_false', 10 );
	$opted_out = $fonts->rewrite_fonts_for_optimizations( $critical );
	local_fonts_check( ! apply_filters( 'noobrocket_host_fonts_locally_inline_css', false ) && strpos( $opted_out, '/google-fonts/css/' ) !== false && strpos( $opted_out, $font_css ) === false, 'explicit native inline opt-out retains a local blocking font stylesheet' );
	remove_filter( 'noobrocket_host_fonts_locally_inline_css', '__return_false', 10 );
	$csp = str_replace( '<head>', '<head><meta http-equiv="Content-Security-Policy" content="default-src \'self\'">', $html );
	local_fonts_check( $frontend->optimize( $csp ) === $csp && ! $frontend->has_critical_css() && ! apply_filters( 'noobrocket_host_fonts_locally_inline_css', false ), 'CSP early return resets prior critical state and does not force inline fonts' );
	local_fonts_check( strpos( $fonts->rewrite_fonts_for_optimizations( $csp ), $font_css ) === false, 'CSP fallback retains native default font delivery' );
	$frontend->optimize( $html );
	$_GET['nonoobrocket'] = 1;
	local_fonts_check( ! $fonts->owns_font_link( $font_tag ) && $frontend->optimize( $html ) === $html && ! apply_filters( 'noobrocket_host_fonts_locally_inline_css', false ), 'bypass context resets critical state and native ownership' );
	local_fonts_check( $fonts->rewrite_fonts_for_optimizations( $html ) === $html, 'actual native controller preserves bypass output' );
	$_GET = [];
	$frontend->optimize( $html );
	$changed = str_replace( '</head>', '<link rel="stylesheet" href="' . home_url( '/fixture-added.css' ) . '"></head>', $html );
	local_fonts_check( $frontend->optimize( $changed ) === $changed && ! apply_filters( 'noobrocket_host_fonts_locally_inline_css', false ), 'changed pending manifest resets the earlier successful critical state' );
	$jobs->invalidate_urls( [ $url ] );
	add_filter( 'noobrocket_exclude_locally_host_fonts', $font_exclusion );
	local_fonts_check( ! $fonts->owns_font_link( $font_tag ) && $fonts->rewrite_fonts_for_optimizations( $html ) === $html, 'native font exclusion is respected by ownership and actual controller' );
	$frontend->optimize( $html );
	local_fonts_check( count( json_decode( $jobs->find( $url )->css, true )['styles'] ) === 2, 'explicitly excluded font sheet remains available to local CSS generation' );
	remove_filter( 'noobrocket_exclude_locally_host_fonts', $font_exclusion );
	$jobs->invalidate_urls( [ $url ] );
	$hosting = false;
	local_fonts_check( ! $fonts->owns_font_link( $font_tag ) && $fonts->rewrite_fonts_for_optimizations( $html ) === $html, 'disabled native hosting retains ownership and rendering opt-out' );
	$frontend->optimize( $html );
	local_fonts_check( count( json_decode( $jobs->find( $url )->css, true )['styles'] ) === 2, 'hosting-disabled font sheet remains in local CSS generation' );
	$jobs->invalidate_urls( [ $url ] );
	$hosting = true;
	$mode = 'used';
	$used = local_fonts_complete( $html );
	local_fonts_check( ! $frontend->has_critical_css() && ! apply_filters( 'noobrocket_host_fonts_locally_inline_css', false ) && strpos( $used, 'data-noobrocket-local-css="critical"' ) === false, 'used CSS preserves its native font inline default' );
	local_fonts_check( strpos( $used, $font_tag ) !== false && strpos( $fonts->rewrite_fonts_for_optimizations( $used ), '/google-fonts/css/' ) !== false, 'used CSS leaves Google hosting to the native controller' );
	$mode = 'off';
	local_fonts_check( $frontend->optimize( $html ) === $html && ! apply_filters( 'noobrocket_host_fonts_locally_inline_css', false ), 'off mode retains original HTML and native font inline default' );

	// Exercise the real queue registration and runner, with provider HTTP entirely mocked.
	$async_url = 'https://fonts.googleapis.com/css2?family=NoobAsyncFixture&site=' . $post_id;
	$async_font_url = 'https://fonts.gstatic.com/noob-async-fixture-' . $post_id . '.woff2';
	$async_tag = '<link rel="stylesheet" id="async-fixture" href="' . $async_url . '" media="print" onload="this.media=\'all\'" nonce="fixture-nonce">';
	$async_html = '<html><head>' . $async_tag . '</head><body>Queued fonts.</body></html>';
	$queue_args = [ $async_url, home_url( add_query_arg( [], $GLOBALS['wp']->request ) ) ];
	$font_http_calls = [];
	local_fonts_check( $fonts->rewrite_fonts_for_optimizations( $async_html ) === $async_html && [] === $font_http_calls, 'native cold rendering retains original delivery without provider HTTP' );
	$fonts->rewrite_fonts_for_optimizations( $async_html );
	$font_action_ids = as_get_scheduled_actions( [ 'hook' => 'noobrocket_prepare_local_font', 'args' => $queue_args, 'group' => 'noobrocket', 'status' => ActionScheduler_Store::STATUS_PENDING, 'per_page' => -1 ], 'ids' );
	local_fonts_check( 1 === count( $font_action_ids ), 'actual Action Scheduler stores one pending job for repeated cold rendering' );
	$font_http = [ $async_url => '@font-face{font-family:NoobAsyncFixture;src:url(' . $async_font_url . ')}', $async_font_url => 'native-font-fixture-bytes' ];
	$hint_id = $font_hint_query->add_item( [ 'url' => untrailingslashit( $url ), 'is_mobile' => 0, 'status' => 'completed', 'fonts' => wp_json_encode( [ home_url( '/wp-content/cache/noobrocket/fonts/1/google-fonts/fonts/legacy.woff2' ) ] ) ] );
	local_fonts_check( $hint_id && $font_hint_query->get_row( untrailingslashit( $url ), false ), 'seed a stale native font-hint row' );
	$mobile_hint_id = $font_hint_query->add_item( [ 'url' => untrailingslashit( $url ), 'is_mobile' => 1, 'status' => 'completed', 'fonts' => wp_json_encode( [ $async_font_url ] ) ] );
	$neighbor_url = untrailingslashit( $url ) . '-other';
	$neighbor_hint_id = $font_hint_query->add_item( [ 'url' => $neighbor_url, 'is_mobile' => 0, 'status' => 'completed', 'fonts' => wp_json_encode( [ $async_font_url ] ) ] );
	local_fonts_check( $mobile_hint_id && $neighbor_hint_id && $font_hint_query->get_row( untrailingslashit( $url ), true ) && $font_hint_query->get_row( $neighbor_url, false ), 'seed cached mobile and unrelated-page hint rows' );
	$parsed = get_noobrocket_parse_url( $url );
	$cache_file = _noobrocket_get_noobrocket_cache_path() . $parsed['host'] . $parsed['path'] . '/index.html';
	local_fonts_check( 0 === strpos( wp_normalize_path( $cache_file ), wp_normalize_path( $fixture . '/' ) ), 'native page-cache proof remains inside the owned temporary root' );
	wp_mkdir_p( dirname( $cache_file ) );
	file_put_contents( $cache_file, $async_html );
	_noobrocket_get_cache_dirs( $parsed['host'], '', true );
	ActionScheduler::runner()->process_action( $font_action_ids[0], 'font-delivery-fixture' );
	local_fonts_check( ActionScheduler_Store::STATUS_COMPLETE === $font_queue_store->get_status( $font_action_ids[0] ) && [ $async_url, $async_font_url ] === $font_http_calls, 'registered native queue callback publishes complete local CSS/font bytes using mocked HTTP' );
	clearstatcache();
	local_fonts_check( ! is_file( $cache_file ) && ! $font_hint_query->get_row( untrailingslashit( $url ), false ) && ! $font_hint_query->get_row( untrailingslashit( $url ), true ) && $font_hint_query->get_row( $neighbor_url, false ), 'background completion invalidates waiting page-cache and both device hints while preserving unrelated pages' );
	$prepared_async = $fonts->rewrite_fonts_for_optimizations( $async_html );
	local_fonts_check( strpos( $prepared_async, '/google-fonts/css/' ) !== false && strpos( $prepared_async, 'media="print" onload="this.media=\'all\'" nonce="fixture-nonce"' ) !== false, 'prepared native link changes URL while retaining conditional loading and CSP nonce' );
	$font_hint_query->add_item( [ 'url' => untrailingslashit( $url ), 'is_mobile' => 0, 'status' => 'completed', 'fonts' => wp_json_encode( [ $async_font_url ] ) ] );
	$container->get( 'media_fonts_clean' )->clean_fonts_css();
	local_fonts_check( ! $font_hint_query->get_row( untrailingslashit( $url ), false ), 'native local CSS invalidation also invalidates collected font hints for recollection' );
	echo 'PASS: ', $checks, ' real WordPress local CSS/native font integration assertions.', PHP_EOL;
} finally {
	add_filter( 'noobrocket_is_importing', '__return_true' );
	if ( $post_id && ! is_wp_error( $post_id ) ) { wp_delete_post( $post_id, true ); }
	remove_filter( 'noobrocket_is_importing', '__return_true' );
	remove_filter( 'pre_get_noobrocket_option_local_css_mode', $mode_filter );
	remove_filter( 'pre_get_noobrocket_option_host_fonts_locally', $hosting_filter );
	remove_filter( 'noobrocket_exclude_locally_host_fonts', $font_exclusion );
	remove_filter( 'noobrocket_host_fonts_locally_inline_css', '__return_false', 10 );
	update_option( 'noobrocket_settings', $settings_before );
	update_option( 'cron', $cron_before );
	$known_ids = array_column( $rows_before, 'id' );
	foreach ( $wpdb->get_col( "SELECT id FROM $table" ) as $id ) { if ( ! in_array( $id, $known_ids ) ) { $wpdb->delete( $table, [ 'id' => $id ] ); } }
	foreach ( $rows_before as $row ) { $wpdb->replace( $table, $row ); }
	$wpdb->query( "DELETE FROM $font_hint_table" );
	foreach ( $font_hints_before as $row ) { $wpdb->replace( $font_hint_table, $row ); }
	foreach ( array_diff( $font_queue_store->query_actions( [ 'hook' => 'noobrocket_prepare_local_font', 'per_page' => -1 ] ), $font_actions_before ) as $action_id ) { $font_queue_store->delete_action( $action_id ); }
	remove_filter( 'pre_http_request', $block_http, PHP_INT_MAX - 1 );
	remove_filter( 'noobrocket_generate_advanced_cache_file', '__return_false' );
	remove_filter( 'noobrocket_disable_htaccess', '__return_true' );
	remove_filter( 'pre_get_noobrocket_option_manual_preload', '__return_false' );
	foreach ( $globals_before as $name => $value ) { $GLOBALS[ $name ] = $value; }
	$GLOBALS['wp']->request = $request_before;
	wp_set_current_user( $user_before );
	local_fonts_remove_fixture( $fixture );
}
