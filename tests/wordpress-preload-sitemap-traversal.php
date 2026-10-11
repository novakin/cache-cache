<?php
/** Registered sitemap parsing and native scheduler jobs on a marked copied-plugin site. */
$wordpress = isset( $argv[1] ) ? realpath( $argv[1] ) : false;
if ( ! $wordpress || ! is_file( $wordpress . '/wp-load.php' ) || ! is_file( $wordpress . '/.noobrocket-acceptance-site' ) ) {
	fwrite( STDERR, "Provide a marked disposable WordPress installation.\n" );
	exit( 2 );
}
$source = dirname( __DIR__ ) . '/noobrocket';
$installed = $wordpress . '/wp-content/plugins/noobrocket';
$normalize = static function ( $path ) { return str_replace( '\\', '/', $path ); };
if ( ! realpath( $installed ) || $normalize( realpath( $installed ) ) !== $normalize( $installed ) || realpath( $installed ) === realpath( $source ) ) {
	fwrite( STDERR, "The site must use a real copied plugin, not a source junction.\n" );
	exit( 2 );
}
foreach ( [ 'inc/Engine/Preload/Frontend/FetchSitemap.php', 'inc/Engine/Preload/Frontend/SitemapParser.php', 'inc/Engine/Preload/Controller/Queue.php' ] as $file ) {
	if ( hash_file( 'sha256', $source . '/' . $file ) !== hash_file( 'sha256', $installed . '/' . $file ) ) {
		fwrite( STDERR, "Copy the candidate sitemap owners before native proof.\n" );
		exit( 2 );
	}
}
$complete = false;
register_shutdown_function( static function () use ( &$complete ) {
	register_shutdown_function( static function () use ( &$complete ) {
		if ( ! $complete ) { fwrite( STDERR, "FAIL: native sitemap fixture did not complete.\n" ); exit( 2 ); }
	} );
} );
define( 'WP_ADMIN', true );
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
$_SERVER['HTTP_HOST'] = '127.0.0.1';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'POST';
// Match the native admin context before core parses PHP_SELF, including on WordPress 5.8.
$_SERVER['SCRIPT_NAME'] = rtrim( $_SERVER['REQUEST_URI'], '/' ) . '/wp-admin/index.php';
$_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
$debug_file = $wordpress . '/wp-content/debug.log';
$debug_before = is_file( $debug_file ) ? file_get_contents( $debug_file ) : '';
require $wordpress . '/wp-load.php';
$container = apply_filters( 'noobrocket_container', null );
$queue = $container->get( 'preload_queue' );
$store = ActionScheduler::store();
$query = $container->get( 'preload_caches_query' );
$checks = 0;
$check = static function ( $condition, $message ) use ( &$checks ) { if ( ! $condition ) { fwrite( STDERR, 'FAIL: ' . $message . "\n" ); throw new RuntimeException( $message ); } ++$checks; };
$base = home_url( '/cache-cache-sitemap-' . wp_generate_uuid4() );
$root = $base . '/index.xml';
$children = [ $base . '/Child.xml', $base . '/child.xml', $base . '/child.xml?lang=EN', $base . '/child.xml?lang=en', 'https://fixture.example.test/' . basename( $base ) . '.xml', $base . '/child.xml#section' ];
$page = $base . '/page';
$excluded = $base . '/private';
$owned_urls = array_merge( [ $root ], $children );
$index = static function ( array $urls ) { return '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . implode( '', array_map( static function ( $url ) { return '<sitemap><loc>' . htmlspecialchars( $url, ENT_XML1 ) . '</loc></sitemap>'; }, $urls ) ) . '</sitemapindex>'; };
$response = static function ( $body, $code = 200 ) { return [ 'response' => [ 'code' => $code ], 'body' => $body, 'headers' => [] ]; };
$responses = [ $root => $response( $index( [ $root, $root ] ) ) ];
$requests = [];
$http = static function ( $pre, $args, $url ) use ( &$responses, &$requests ) {
	$requests[] = $url;
	return $responses[ $url ] ?? new WP_Error( 'sitemap_fixture_unexpected_http', 'No external request is permitted.' );
};
$paused = false;
$pause = static function () use ( &$paused ) { return $paused; };
$exclude = static function ( $value, $url ) use ( $excluded ) { return $excluded === untrailingslashit( $url ) || $value; };
$actions = static function () use ( $store, $queue, $owned_urls ) {
	$ids = $queue->search( [ 'hook' => 'noobrocket_preload_job_parse_sitemap', 'group' => 'noobrocket-preload', 'per_page' => -1 ], 'ids' );
	$owned = [];
	foreach ( $ids as $id ) {
		$args = $store->fetch_action( $id )->get_args();
		if ( count( $args ) === 1 && in_array( $args[0], $owned_urls, true ) ) { $owned[ $id ] = $args[0]; }
	}
	return $owned;
};
add_filter( 'pre_http_request', $http, PHP_INT_MAX - 1, 3 );
add_filter( 'pre_option_noobrocket_preload_paused', $pause );
add_filter( 'noobrocket_preload_exclude', $exclude, 10, 2 );
try {
	$check( false !== has_action( 'noobrocket_preload_job_parse_sitemap' ), 'native sitemap callback is registered' );
	$check( [] === $actions(), 'unique owned sitemap URLs have no prior jobs' );
	do_action( 'noobrocket_preload_job_parse_sitemap', $root );
	$check( $requests === [ $root ] && [] === $actions(), 'self-referencing index finishes without recreating its parse job' );
	$responses[ $root ] = $response( $index( array_merge( [ $root ], $children, $children ) ) );
	do_action( 'noobrocket_preload_job_parse_sitemap', $root );
	$queued = array_values( $actions() );
	sort( $queued, SORT_STRING );
	$expected = $children;
	sort( $expected, SORT_STRING );
	$check( $queued === $expected, 'one native job per exact child preserves path case, query case, external and fragment URLs' );
	foreach ( $actions() as $id => $url ) { $check( $store->get_status( $id ) === ActionScheduler_Store::STATUS_PENDING, 'legitimate child job is pending' ); }
	foreach ( $actions() as $id => $url ) { $store->delete_action( $id ); }
	$responses[ $children[0] ] = $response( '<urlset><url><loc>' . $page . '</loc></url><url><loc>' . $excluded . '</loc></url><url><loc>https://outside.example.test/page</loc></url></urlset>' );
	do_action( 'noobrocket_preload_job_parse_sitemap', $children[0] );
	$check( false !== $query->get_rows_by_url( $page ), 'legitimate child discovery creates native preload storage' );
	$check( false === $query->get_rows_by_url( $excluded ), 'registered page exclusion remains effective' );
	$check( false === $query->get_rows_by_url( 'https://outside.example.test/page' ), 'external pages remain outside site admission' );
	$check( [] === $actions(), 'ordinary URL set creates no child sitemap actions' );
	$paused = true;
	$before = $requests;
	do_action( 'noobrocket_preload_job_parse_sitemap', $root );
	$check( $before === $requests, 'paused native preload performs no sitemap HTTP call' );
	$paused = false;
	foreach ( [ $response( $index( $children ), 500 ), $response( '' ), $response( '<broken' ) ] as $failure ) {
		$responses[ $root ] = $failure;
		do_action( 'noobrocket_preload_job_parse_sitemap', $root );
		$check( [] === $actions(), 'failed, empty and malformed native responses create no jobs' );
	}
	$check( count( $requests ) === 6, 'only six explicitly mocked sitemap requests were made' );
} finally {
	foreach ( $actions() as $id => $url ) { $store->delete_action( $id ); }
	$query->delete_by_url( $page );
	$query->delete_by_url( $excluded );
	remove_filter( 'pre_http_request', $http, PHP_INT_MAX - 1 );
	remove_filter( 'pre_option_noobrocket_preload_paused', $pause );
	remove_filter( 'noobrocket_preload_exclude', $exclude, 10 );
}
$debug_after = is_file( $debug_file ) ? file_get_contents( $debug_file ) : '';
if ( $debug_before !== $debug_after ) {
	$delta = substr( $debug_after, 0, strlen( $debug_before ) ) === $debug_before ? substr( $debug_after, strlen( $debug_before ) ) : $debug_after;
	$lines = preg_split( '/\r\n|\r|\n/', substr( $delta, 0, 4096 ) );
	fwrite( STDERR, "Native sitemap diagnostic delta (" . strlen( $delta ) . " bytes; first 20 lines, at most 4096 bytes):\n" . implode( "\n", array_slice( $lines, 0, 20 ) ) . "\n" );
}
$check( $debug_before === $debug_after, 'native operations add no WordPress database or PHP diagnostics' );
$complete = true;
echo "PASS: $checks native sitemap traversal assertions; registered hook, actual scheduler/storage, six mocked requests, no external HTTP.\n";
