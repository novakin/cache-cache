<?php
/**
 * Real HTTP -> Chromium worker -> PHP bridge -> public CSS/cache delivery proof.
 * php tests/wordpress-local-css-http.php WORDPRESS_ROOT CHROMIUM_PATH [NODE_PATH]
 * Requires the disposable WordPress HTTP server on 127.0.0.1:8765; run serially.
 */
$wordpress = isset( $argv[1] ) ? realpath( $argv[1] ) : false;
$browser = $argv[2] ?? '';
$node = $argv[3] ?? 'node';
if ( ! $wordpress || ! is_file( $wordpress . '/wp-load.php' ) || ! is_file( $wordpress . '/.noobrocket-acceptance-site' ) || ! is_file( $browser ) ) {
	fwrite( STDERR, "Provide a marked disposable WordPress installation and the Chromium executable.\n" );
	exit( 2 );
}
putenv( 'NOOBROCKET_TEST_PHASE=local-css-http' );
$_SERVER['HTTP_HOST'] = '127.0.0.1:8765';
$_SERVER['REQUEST_URI'] = '/';
// Bypass early homepage delivery; actual worker and HTTP requests remain GET.
$_SERVER['REQUEST_METHOD'] = 'POST';
define( 'WP_ADMIN', true );
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
require $wordpress . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
wp_set_current_user( 1 );

$checks = 0;
function local_css_http_check( $condition, $message ) {
	global $checks;
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
	$checks++;
	echo 'PASS: ', $message, PHP_EOL;
}
function local_css_http_process( array $command, string $input = '' ): string {
	$process = proc_open( $command, [ 0 => [ 'pipe', 'r' ], 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ], $pipes );
	if ( ! is_resource( $process ) ) { throw new RuntimeException( 'Could not start fixture subprocess.' ); }
	fwrite( $pipes[0], $input );
	fclose( $pipes[0] );
	$output = stream_get_contents( $pipes[1] );
	$errors = stream_get_contents( $pipes[2] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );
	$exit = proc_close( $process );
	if ( 0 !== $exit ) {
		$detail = implode( "\n", array_slice( preg_split( '/\R/', trim( $errors ?: $output ) ), 0, 40 ) );
		throw new RuntimeException( 'Fixture subprocess failed (' . $exit . '): ' . substr( $detail, 0, 4000 ) );
	}
	return $output;
}
function local_css_http_settings( array $settings, bool $restore = false ): void {
	global $wordpress, $browser, $node;
	local_css_http_process( [ PHP_BINARY, __FILE__, $wordpress, $browser, $node, $restore ? 'restore' : 'settings' ], wp_json_encode( $settings ) );
	wp_cache_delete( 'alloptions', 'options' );
	wp_cache_delete( 'noobrocket_settings', 'options' );
}
function local_css_http_request( string $url ) {
	$response = wp_remote_get( $url, [ 'timeout' => 20, 'redirection' => 0, 'headers' => [ 'Accept-Encoding' => 'identity' ] ] );
	if ( is_wp_error( $response ) ) { throw new RuntimeException( $response->get_error_message() ); }
	return $response;
}
function local_css_http_hit( $response ): bool {
	$status = wp_remote_retrieve_header( $response, 'cache-status' );
	return false !== strpos( is_array( $status ) ? implode( ', ', $status ) : (string) $status, 'NoobRocket; hit' );
}
function local_css_http_links( string $html ): array {
	$document = new DOMDocument();
	$before = libxml_use_internal_errors( true );
	$document->loadHTML( $html );
	libxml_clear_errors();
	libxml_use_internal_errors( $before );
	$links = [];
	foreach ( $document->getElementsByTagName( 'link' ) as $link ) {
		if ( 'stylesheet' !== $link->getAttribute( 'rel' ) ) { continue; }
		$noscript = false;
		for ( $parent = $link->parentNode; $parent; $parent = $parent->parentNode ) {
			if ( 'noscript' === $parent->nodeName ) { $noscript = true; break; }
		}
		$links[] = [ 'href' => $link->getAttribute( 'href' ), 'media' => $link->getAttribute( 'media' ), 'onload' => $link->getAttribute( 'onload' ), 'noscript' => $noscript ];
	}
	return $links;
}
function local_css_http_worker(): string {
	global $wordpress, $browser, $node;
	return local_css_http_process( [ $node, NOOBROCKET_PATH . 'local-css/worker.mjs', '--wordpress', $wordpress, '--php', PHP_BINARY, '--browser', $browser, '--once', '--limit', '10' ] );
}

$home = wp_parse_url( home_url() );
if ( 'http' !== ( $home['scheme'] ?? '' ) || '127.0.0.1' !== ( $home['host'] ?? '' ) || 8765 !== ( $home['port'] ?? 80 ) ) {
	throw new RuntimeException( 'HTTP fixtures require http://127.0.0.1:8765.' );
}
if ( in_array( $argv[4] ?? '', [ 'settings', 'restore' ], true ) ) {
	$settings = json_decode( stream_get_contents( STDIN ), true );
	if ( ! is_array( $settings ) ) { exit( 2 ); }
	if ( 'restore' === $argv[4] ) {
		// Restore the snapshot's cache keys too; post-save actions still refresh config.
		remove_all_filters( 'pre_update_option_noobrocket_settings' );
	}
	update_option( 'noobrocket_settings', $settings );
	exit;
}

$container = apply_filters( 'noobrocket_container', null );
$jobs = $container->get( 'local_css_jobs' );
$table = $container->get( 'rucss_usedcss_table' )->get_name();
$preload = $container->get( 'preload_caches_query' );
do_action( 'admin_init' );
local_css_http_check( 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE queue_name IN ('local-used','local-critical')" ), 'exclusive disposable site starts with an empty local CSS queue' );
$original_settings = get_option( 'noobrocket_settings' );
$original_cron = get_option( 'cron' );
$post_id = 0;
$url = '';
$cache_dir = '';
$mu_file = '';
$asset_files = [];
try {
	$settings = array_merge( $original_settings, [
		'local_css_mode' => 'used', 'local_css_safelist' => [],
		'cache_logged_user' => 0, 'cache_mobile' => 0, 'do_caching_mobile_files' => 0,
		'cache_webp' => 0, 'cache_ssl' => 1, 'manual_preload' => 0, 'cdn' => 0,
		'minify_css' => 0, 'minify_js' => 0, 'delay_js' => 0, 'defer_all_js' => 0,
		'remove_unused_css' => 0, 'async_css' => 0, 'optimize_css_delivery' => 0,
	] );
	local_css_http_settings( $settings );
	$bridge = local_css_http_process( [ PHP_BINARY, NOOBROCKET_PATH . 'local-css/bridge.php', $wordpress ], '{"action":"claim"}' );
	local_css_http_check( [ 'ok' => true, 'job' => null, 'paused' => false ] === json_decode( $bridge, true ), 'real CLI bridge boots WordPress and returns clean empty-queue JSON with pause state' );
	$http_bridge = local_css_http_request( NOOBROCKET_URL . 'local-css/bridge.php' );
	local_css_http_check( 404 === wp_remote_retrieve_response_code( $http_bridge ) && '' === wp_remote_retrieve_body( $http_bridge ), 'bridge is unavailable over HTTP' );

	$prefix = 'noobrocket-css-http-' . strtolower( wp_generate_password( 10, false, false ) );
	$post_id = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'NoobRocket local CSS worker proof', 'post_name' => $prefix, 'post_content' => 'Disposable local CSS HTTP proof.' ], true );
	local_css_http_check( ! is_wp_error( $post_id ) && $post_id > 0, 'unique public fixture page created' );
	$meta = '_' . $prefix;
	update_post_meta( $post_id, $meta, 1 );
	$url = get_permalink( $post_id );
	$parts = wp_parse_url( $url );
	local_css_http_check( empty( $parts['query'] ), 'fixture uses a public cacheable permalink' );
	$cache_dir = NOOBROCKET_CACHE_PATH . $parts['host'] . strtolower( $parts['path'] );
	$cache_file = $cache_dir . 'index.html';
	$source_css = [
		'body{margin:0;font-family:sans-serif}.hero{background:#0b1830;color:#ffffff;padding:32px;font-size:24px}.hidden-menu{display:none}.below-fold{background:#fafafa;padding:24px}.unused-noobrocket-fixture{color:magenta}',
		'.hero{border-left:8px solid #7cdfa0;color:#7cdfa0}.below-fold{color:#263238}.unused-secondary-fixture{outline:20px solid red}',
	];
	$hrefs = [];
	foreach ( $source_css as $index => $css ) {
		$asset_files[] = $wordpress . '/' . $prefix . '-' . $index . '.css';
		if ( false === file_put_contents( $asset_files[ $index ], $css ) ) { throw new RuntimeException( 'Could not create fixture stylesheet.' ); }
		$hrefs[] = home_url( '/' . $prefix . '-' . $index . '.css' );
	}
	$markup = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>NoobRocket worker proof</title>'
		. '<link rel="stylesheet" href="' . esc_url( $hrefs[0] ) . '" media="screen"><link rel="stylesheet" href="' . esc_url( $hrefs[1] ) . '" media="all">'
		. '<style id="fixture-inline">.fixture-spacer{height:1900px}</style></head><body><main><section class="hero">NoobRocket local CSS: real HTTP and browser worker.</section>'
		. '<nav class="hidden-menu">Hidden navigation keeps its initial hiding rule.</nav><div class="fixture-spacer"></div><footer class="below-fold">Below-fold content remains styled after scrolling.</footer></main></body></html>';
	$mu_file = WP_CONTENT_DIR . '/mu-plugins/' . $prefix . '.php';
	$mu = "<?php\nadd_action( 'template_redirect', function () {\n"
		. "\tif ( ! is_page() || ! get_post_meta( get_queried_object_id(), " . var_export( $meta, true ) . ", true ) ) { return; }\n"
		. "\tstatus_header( 200 );\n\theader( 'Content-Type: text/html; charset=UTF-8' );\n\techo " . var_export( $markup, true ) . ";\n\texit;\n}, 99 );\n";
	if ( false === file_put_contents( $mu_file, $mu ) ) { throw new RuntimeException( 'Could not create unique fixture template.' ); }

	foreach ( [ 'used', 'critical' ] as $mode ) {
		if ( 'critical' === $mode ) {
			$settings['local_css_mode'] = 'critical';
			local_css_http_settings( $settings );
		}
		$cold = local_css_http_request( $url );
		$original = wp_remote_retrieve_body( $cold );
		local_css_http_check( 200 === wp_remote_retrieve_response_code( $cold ) && ! local_css_http_hit( $cold ), $mode . ': cold response renders through WordPress' );
		$links = local_css_http_links( $original );
		local_css_http_check( 2 === count( $links ) && array_column( $links, 'href' ) === $hrefs && '' === $links[0]['onload'] && 'screen' === $links[0]['media'] && 'all' === $links[1]['media'], $mode . ': pending work preserves blocking original stylesheets and cascade order' );
		local_css_http_check( false !== strpos( $original, '<style id="fixture-inline">' ) && false === strpos( $original, 'data-noobrocket-local-css="critical"' ), $mode . ': pending work preserves inline styles without premature critical CSS' );
		$row = $jobs->find( $url );
		local_css_http_check( $row && 'local-pending' === $row->status && 'local-' . $mode === $row->queue_name, $mode . ': real anonymous HTTP response enqueues the matching local job' );
		$input = json_decode( $row->css, true );
		local_css_http_check( array_column( $input['styles'], 'href' ) === $hrefs && [] === $input['safelist'], $mode . ': stored worker manifest contains the exact original sheets' );
		$pending_hit = local_css_http_request( $url );
		local_css_http_check( local_css_http_hit( $pending_hit ) && is_file( $cache_file ) && wp_remote_retrieve_body( $pending_hit ) === file_get_contents( $cache_file ) && array_column( local_css_http_links( wp_remote_retrieve_body( $pending_hit ) ), 'href' ) === $hrefs, $mode . ': pending fallback becomes a real early-cache hit' );
		$log = local_css_http_worker();
		local_css_http_check( false !== strpos( $log, '(' . $mode . ', 2 stylesheets)' ) && false !== strpos( $log, 'Processed 1 jobs; 0 failed.' ), $mode . ': actual Node/Chromium worker completes exactly one job via the CLI bridge' );
		$row = $jobs->find( $url );
		local_css_http_check( $row && 'local-completed' === $row->status, $mode . ': worker publication persists the completed database row' );
		$results = json_decode( $row->css, true )['results'];
		local_css_http_check( 2 === count( $results ) && array_column( $results, 'href' ) === $hrefs, $mode . ': publication preserves per-sheet manifest and cascade order' );
		foreach ( $results as $index => $result ) {
			local_css_http_check( is_file( $result['path'] ) && file_get_contents( $result['path'] ) === $result['css'], $mode . ': published sheet ' . $index . ' contains the actual browser result' );
			$public = local_css_http_request( $result['uri'] );
			local_css_http_check( 200 === wp_remote_retrieve_response_code( $public ) && wp_remote_retrieve_body( $public ) === $result['css'], $mode . ': sheet ' . $index . ' is publicly served at its generated URI' );
			local_css_http_check( false !== strpos( $result['css'], '.hero' ) && false === strpos( $result['css'], $index ? '.unused-secondary-fixture' : '.unused-noobrocket-fixture' ) && strlen( $result['css'] ) < strlen( $source_css[ $index ] ), $mode . ': sheet ' . $index . ' retains hero styling and removes its unused selector' );
		}
		local_css_http_check( false !== strpos( $results[0]['css'], '.hidden-menu' ), $mode . ': hidden navigation retains its initial hiding rule' );
		local_css_http_check( ( 'used' === $mode ) === ( false !== strpos( $results[0]['css'], '.below-fold' ) ), $mode . ': below-fold rules follow the selected whole-page or initial-viewport mode' );
		clearstatcache( true, $cache_file );
		local_css_http_check( ! is_file( $cache_file ), $mode . ': worker publication purges the cached original fallback' );
		$fresh = local_css_http_request( $url );
		$html = wp_remote_retrieve_body( $fresh );
		local_css_http_check( 200 === wp_remote_retrieve_response_code( $fresh ) && ! local_css_http_hit( $fresh ), $mode . ': next public request renders the published result' );
		$links = local_css_http_links( $html );
		if ( 'used' === $mode ) {
			local_css_http_check( 2 === count( $links ) && array_column( $links, 'href' ) === array_column( $results, 'uri' ) && 'screen' === $links[0]['media'] && 'all' === $links[1]['media'], 'used: HTTP output links both generated public files in original cascade/media order' );
		} else {
			$deferred = array_values( array_filter( $links, function ( $link ) { return ! $link['noscript']; } ) );
			$fallback = array_values( array_filter( $links, function ( $link ) { return $link['noscript']; } ) );
			local_css_http_check( 2 === substr_count( $html, 'data-noobrocket-local-css="critical"' ) && false !== strpos( $html, $results[0]['css'] ) && false !== strpos( $html, $results[1]['css'] ), 'critical: HTTP output inlines both browser-generated critical sheets' );
			local_css_http_check( 2 === count( $deferred ) && array_column( $deferred, 'href' ) === $hrefs && [ 'print', 'print' ] === array_column( $deferred, 'media' ) && false !== strpos( $deferred[0]['onload'], "this.media='screen'" ) && false !== strpos( $deferred[1]['onload'], "this.media='all'" ), 'critical: original sheets defer and restore their exact media on load' );
			local_css_http_check( 2 === count( $fallback ) && array_column( $fallback, 'href' ) === $hrefs && [ 'screen', 'all' ] === array_column( $fallback, 'media' ), 'critical: noscript fallback preserves both blocking original sheets' );
		}
		local_css_http_check( false !== strpos( $html, '<style id="fixture-inline">.fixture-spacer{height:1900px}</style>' ), $mode . ': publication keeps unrelated inline styles untouched' );
		$hit = local_css_http_request( $url );
		local_css_http_check( 200 === wp_remote_retrieve_response_code( $hit ) && local_css_http_hit( $hit ) && is_file( $cache_file ) && wp_remote_retrieve_body( $hit ) === file_get_contents( $cache_file ) && local_css_http_links( wp_remote_retrieve_body( $hit ) ) === $links, $mode . ': optimized public HTML becomes a real early-cache hit' );
	}
} finally {
	if ( $url ) { $jobs->invalidate_urls( [ $url ] ); $preload->delete_by_url( $url ); }
	if ( $post_id && ! is_wp_error( $post_id ) ) { wp_delete_post( $post_id, true ); }
	if ( $mu_file && is_file( $mu_file ) ) { unlink( $mu_file ); }
	foreach ( $asset_files as $file ) { if ( is_file( $file ) ) { unlink( $file ); } }
	if ( $cache_dir && is_dir( $cache_dir ) ) {
		foreach ( new DirectoryIterator( $cache_dir ) as $file ) { if ( $file->isFile() ) { unlink( $file->getPathname() ); } }
		if ( 2 === count( scandir( $cache_dir ) ) ) { rmdir( $cache_dir ); }
	}
	local_css_http_settings( $original_settings, true );
	update_option( 'cron', $original_cron );
}
local_css_http_check( $original_settings === get_option( 'noobrocket_settings' ), 'fixture restores the original plugin settings' );
local_css_http_check( $original_cron === get_option( 'cron' ), 'fixture restores the original scheduled events' );
local_css_http_check( ! get_post( $post_id ) && ! $jobs->find( $url ), 'fixture removes its public page and local CSS row' );
clearstatcache();
local_css_http_check( ! is_file( $mu_file ) && ! is_dir( $cache_dir ) && ! array_filter( $asset_files, 'is_file' ), 'fixture removes its unique template, stylesheet sources and page cache' );
echo 'Verified ', $checks, " real HTTP/Chromium/CLI assertions; fixture cleaned and settings restored.\n";
