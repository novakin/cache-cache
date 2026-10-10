<?php
/**
 * Real WordPress local CSS jobs/rendering/invalidation without a browser worker.
 * php tests/wordpress-local-css.php /absolute/path/to/disposable/wordpress
 * Run serially: fixture temporarily changes settings, posts, terms and job rows.
 */
$wordpress = isset( $argv[1] ) ? realpath( $argv[1] ) : false;
if ( ! $wordpress || ! is_file( $wordpress . '/wp-load.php' ) || ! is_file( $wordpress . '/.noobrocket-acceptance-site' ) ) {
	fwrite( STDERR, "Provide a disposable WordPress installation with the .noobrocket-acceptance-site marker.\n" );
	exit( 2 );
}
$fixture = tempnam( sys_get_temp_dir(), 'noobrocket-local-css-test-' );
unlink( $fixture );
mkdir( $fixture );
define( 'NOOBROCKET_CACHE_ROOT_PATH', $fixture . '/' );
define( 'NOOBROCKET_CONFIG_PATH', $fixture . '/config/' );
define( 'WP_ADMIN', false );
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
// Direct frontend assertions opt in after the non-GET bootstrap bypasses caching.
define( 'DONOTNOOBROCKETOPTIMIZE', false );
putenv( 'NOOBROCKET_TEST_PHASE=local-css' );
$_SERVER['HTTP_HOST'] = '127.0.0.1:8765';
$_SERVER['REQUEST_URI'] = '/';
// Boot past an already-warm early cache; individual frontend contexts use GET.
$_SERVER['REQUEST_METHOD'] = 'POST';
require $wordpress . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';

$checks = [];
function local_css_check( $condition, $message ) {
	global $checks;
	if ( ! $condition ) {
		throw new RuntimeException( 'FAIL: ' . $message );
	}
	$checks[] = $message;
}

function local_css_context( $post_id ) {
	$GLOBALS['wp_query'] = new WP_Query( [ 'p' => $post_id, 'post_type' => 'post', 'post_status' => 'any' ] );
	$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
	$GLOBALS['post'] = get_post( $post_id );
	$path = wp_parse_url( get_permalink( $post_id ), PHP_URL_PATH );
	$GLOBALS['wp']->request = trim( $path, '/' );
	$_SERVER['REQUEST_URI'] = $path;
	$_SERVER['REQUEST_METHOD'] = 'GET';
	$_GET = [];
	$_POST = [];
}

function local_css_page( array $styles ) {
	return '<!doctype html><html><head><title>Local CSS fixture</title>' . implode( '', $styles ) . '</head><body><main class="fixture">CSS job rendering proof.</main></body></html>';
}

function local_css_remove_fixture( $path ) {
	if ( ! is_dir( $path ) ) {
		return;
	}
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $iterator as $file ) {
		if ( $file->isDir() ) {
			rmdir( $file->getPathname() );
		} else {
			unlink( $file->getPathname() );
		}
	}
	rmdir( $path );
}

function local_css_results( array $job ) {
	$results = [];
	foreach ( $job['styles'] as $index => $style ) {
		$results[] = [ 'href' => $style['href'], 'css' => '.fixture-' . $index . '{color:' . ( $index ? 'blue' : 'red' ) . '}' ];
	}
	return $results;
}

function local_css_claim( $expected_url ) {
	global $jobs;
	$job = $jobs->claim();
	$claimed = is_array( $job ) && $job['url'] === user_trailingslashit( untrailingslashit( $expected_url ) ) && ! empty( $job['id'] ) && ! empty( $job['token'] );
	local_css_check( $claimed, 'worker claims the expected real database row' );
	return $job;
}

function local_css_settings( $mode, array $safelist = [] ) {
	$settings = get_option( 'noobrocket_settings' );
	$settings['local_css_mode'] = $mode;
	$settings['local_css_safelist'] = $safelist;
	update_option( 'noobrocket_settings', $settings );
}

$container = apply_filters( 'noobrocket_container', null );
$jobs = $container->get( 'local_css_jobs' );
$frontend = $container->get( 'local_css_frontend' );
$table = $container->get( 'rucss_usedcss_table' )->get_name();
$wpdb = $GLOBALS['wpdb'];
$settings_before = get_option( 'noobrocket_settings' );
$cron_before = get_option( 'cron' );
$rows_before = $wpdb->get_results( "SELECT * FROM $table", ARRAY_A );
$globals_before = [ 'wp_query' => $GLOBALS['wp_query'], 'wp_the_query' => $GLOBALS['wp_the_query'], 'post' => $GLOBALS['post'] ?? null ];
$request_before = $GLOBALS['wp']->request;
$user_before = get_current_user_id();
$posts = [];
$terms = [];
$block_http = function () { return new WP_Error( 'local_css_fixture_http', 'HTTP disabled by local CSS fixture.' ); };
add_filter( 'pre_http_request', $block_http, PHP_INT_MAX - 1 );
add_filter( 'noobrocket_generate_advanced_cache_file', '__return_false' );
add_filter( 'noobrocket_disable_htaccess', '__return_true' );
add_filter( 'pre_get_noobrocket_option_manual_preload', '__return_false' );
// Hold any earlier local jobs during this exclusive fixture; restore them in finally.
$wpdb->query( "UPDATE $table SET status='fixture-held' WHERE queue_name IN ('local-critical','local-used')" );

try {
	$prefix = 'nbr-local-css-' . strtolower( wp_generate_password( 8, false, false ) );
	add_filter( 'noobrocket_is_importing', '__return_true' );
	foreach ( [ 'a', 'b', 'unrelated' ] as $name ) {
		$created = wp_insert_term( $prefix . '-' . $name, 'category', [ 'slug' => $prefix . '-' . $name ] );
		local_css_check( ! is_wp_error( $created ), 'create disposable ' . $name . ' term' );
		$terms[ $name ] = (int) $created['term_id'];
	}
	foreach ( [ 'publish', 'private' ] as $status ) {
		$created = wp_insert_post( [ 'post_title' => $prefix . '-' . $status, 'post_name' => $prefix . '-' . $status, 'post_status' => $status, 'post_category' => [ $terms['a'] ] ], true );
		local_css_check( ! is_wp_error( $created ) && $created > 0, 'create disposable ' . $status . ' post' );
		$posts[ $status ] = $created;
	}
	remove_filter( 'noobrocket_is_importing', '__return_true' );
	wp_set_current_user( 0 );
	local_css_settings( 'used' );
	$api_url = home_url( '/' . $prefix . '-api/' );
	$styles = [
		[ 'href' => home_url( '/' . $prefix . '-first.css' ), 'media' => 'screen' ],
		[ 'href' => home_url( '/' . $prefix . '-second.css' ), 'media' => 'all' ],
	];
	$fingerprint = md5( $prefix );
	foreach ( [ 'https://example.invalid/' . $prefix . '/', 'http://127.0.0.1:8766/' . $prefix . '/', 'https://127.0.0.1:8765/' . $prefix . '/', $api_url . '?fixture=1', $api_url . '#fixture', '/' . $prefix . '/' ] as $bad_url ) {
		local_css_check( ! $jobs->enqueue( $bad_url, false, 'used', $fingerprint, $styles, [] ), 'job queue rejects foreign/query/fragment/relative page URL' );
	}
	local_css_check( ! $jobs->enqueue( $api_url, false, 'unknown', $fingerprint, $styles, [] ), 'job queue rejects unknown CSS mode' );
	local_css_check( $jobs->enqueue( $api_url, false, 'used', $fingerprint, $styles, [ '.fixture-keep' ] ), 'job enqueue writes a real local row' );
	$job = local_css_claim( $api_url );
	local_css_check( $job['mode'] === 'used' && $job['mobile'] === false && $job['styles'] === $styles && $job['safelist'] === [ '.fixture-keep' ], 'claim carries exact mode/device/styles/safelist manifest' );
	local_css_check( ! empty( $job['viewports'] ) && $job['viewports'][0]['width'] > 0 && $job['viewports'][0]['height'] > 0, 'claim carries actual browser viewport dimensions' );
	local_css_check( null === $jobs->claim(), 'a claimed row cannot be claimed twice' );
	$results = local_css_results( $job );
	local_css_check( ! $jobs->complete( $job['id'], 'foreign-token', $fingerprint, $results ), 'completion rejects wrong lease token' );
	local_css_check( ! $jobs->complete( $job['id'], $job['token'], 'wrong-fingerprint', $results ), 'completion rejects changed content fingerprint' );
	local_css_check( ! $jobs->fail( $job['id'], 'foreign-token', 'fixture failure' ), 'failure rejects wrong lease token' );
	local_css_check( ! $jobs->complete( $job['id'], $job['token'], $fingerprint, [ $results[0] ] ), 'completion rejects partial stylesheet results' );
	local_css_check( ! $jobs->complete( $job['id'], $job['token'], $fingerprint, [ $results[0], $results[0] ] ), 'completion rejects duplicate stylesheet results' );
	$foreign_results = $results;
	$foreign_results[1]['href'] = home_url( '/unrequested-sheet.css' );
	local_css_check( ! $jobs->complete( $job['id'], $job['token'], $fingerprint, $foreign_results ), 'completion rejects an unrequested stylesheet' );
	foreach ( [ '', '</style><script>fixture()</script>', '@import url("https://example.invalid/css");', str_repeat( 'x', 2 * 1024 * 1024 + 1 ) ] as $bad_css ) {
		$bad_results = $results;
		$bad_results[0]['css'] = $bad_css;
		local_css_check( ! $jobs->complete( $job['id'], $job['token'], $fingerprint, $bad_results ), 'completion rejects empty/HTML/import/oversized CSS' );
	}
	local_css_check( $jobs->complete( $job['id'], $job['token'], $fingerprint, $results ), 'complete exact safe CSS manifest' );
	$completed_row = $jobs->find( $api_url, false );
	local_css_check( $completed_row && $completed_row->status === 'local-completed', 'completed state persists through its page-cache publication purge' );
	$published_results = json_decode( $completed_row->css, true )['results'];
	foreach ( $published_results as $index => $result ) {
		local_css_check( is_file( $result['path'] ) && file_get_contents( $result['path'] ) === $results[ $index ]['css'], 'published stylesheet file contains its exact worker result' );
	}
	local_css_check( ! $jobs->complete( $job['id'], $job['token'], $fingerprint, $results ), 'completed row cannot publish twice' );
	$jobs->invalidate_urls( [ $api_url ] );
	local_css_check( ! $jobs->find( $api_url, false ), 'URL invalidation removes its local artifact row' );
	foreach ( $published_results as $result ) {
		clearstatcache( true, $result['path'] );
		local_css_check( ! is_file( $result['path'] ), 'URL invalidation removes its published stylesheet file' );
	}
	local_css_check( ! $jobs->complete( $job['id'], $job['token'], $fingerprint, $results ), 'invalidated row rejects late worker completion' );
	$jobs->enqueue( $api_url, false, 'used', $fingerprint, $styles, [] );
	$desktop_job = local_css_claim( $api_url );
	$jobs->enqueue( $api_url, true, 'used', $fingerprint, $styles, [] );
	$mobile_job = local_css_claim( $api_url );
	local_css_check( $desktop_job['mobile'] === false && $mobile_job['mobile'] === true && $desktop_job['id'] !== $mobile_job['id'], 'desktop and mobile maintain separate job rows and leases' );
	$changed_fingerprint = md5( $prefix . '-changed-desktop' );
	local_css_check( $jobs->enqueue( $api_url, false, 'used', $changed_fingerprint, $styles, [] ), 'changed desktop fingerprint replaces its own job' );
	$mobile_row = $jobs->find( $api_url, true );
	local_css_check( $mobile_row && (int) $mobile_row->id === $mobile_job['id'] && $mobile_row->job_id === $mobile_job['token'] && $mobile_row->status === 'local-running', 'desktop fingerprint change preserves the existing mobile lease' );
	local_css_check( $jobs->complete( $mobile_job['id'], $mobile_job['token'], $mobile_job['fingerprint'], local_css_results( $mobile_job ) ), 'mobile result still publishes after desktop fingerprint changes' );
	local_css_check( ! $jobs->complete( $desktop_job['id'], $desktop_job['token'], $desktop_job['fingerprint'], local_css_results( $desktop_job ) ), 'replaced desktop fingerprint rejects its old worker result' );
	$changed_desktop_job = local_css_claim( $api_url );
	local_css_check( $changed_desktop_job['mobile'] === false && $jobs->complete( $changed_desktop_job['id'], $changed_desktop_job['token'], $changed_desktop_job['fingerprint'], local_css_results( $changed_desktop_job ) ), 'changed desktop result publishes independently of mobile' );
	$jobs->invalidate_urls( [ $api_url ] );
	local_css_check( ! $jobs->find( $api_url, false ) && ! $jobs->find( $api_url, true ), 'URL invalidation removes both device variants' );

	$jobs->enqueue( $api_url, false, 'used', $fingerprint, $styles, [] );
	$expired_job = local_css_claim( $api_url );
	$wpdb->update( $table, [ 'modified' => gmdate( 'Y-m-d H:i:s', time() - 601 ) ], [ 'id' => $expired_job['id'] ] );
	$renewed_job = local_css_claim( $api_url );
	local_css_check( $expired_job['id'] === $renewed_job['id'] && $expired_job['token'] !== $renewed_job['token'], 'expired lease is reclaimed with a new token' );
	local_css_check( ! $jobs->complete( $expired_job['id'], $expired_job['token'], $fingerprint, $results ), 'expired token cannot publish after lease reclamation' );
	local_css_check( $jobs->fail( $renewed_job['id'], $renewed_job['token'], 'fixture worker outage' ), 'worker failure persists without publishing CSS' );
	local_css_check( $jobs->find( $api_url, false )->status === 'local-failed' && null === $jobs->claim(), 'failed job waits through retry backoff' );
	$wpdb->update( $table, [ 'next_retry_time' => gmdate( 'Y-m-d H:i:s', time() - 1 ) ], [ 'id' => $renewed_job['id'] ] );
	$retry_job = local_css_claim( $api_url );
	local_css_check( $retry_job['token'] !== $renewed_job['token'], 'failed job becomes claimable with a new retry lease' );
	$jobs->invalidate_all();

	local_css_settings( 'used' );
	local_css_context( $posts['publish'] );
	$post_url = get_permalink( $posts['publish'] );
	$first_tag = '<link id="local-first" rel="stylesheet" href="' . esc_attr( $styles[0]['href'] ) . '" media="screen" nonce="fixture-nonce" data-tone="first">';
	$second_tag = '<link id="local-second" rel="stylesheet" href="' . esc_attr( $styles[1]['href'] ) . '" media="all" data-tone="second">';
	$inline_tag = '<style id="local-inline">.fixture{background:white}</style>';
	$excluded_tags = [
		'<link rel="stylesheet" href="' . home_url( '/' . $prefix . '-print.css' ) . '" media="print">',
		'<link rel="alternate stylesheet" href="' . home_url( '/' . $prefix . '-alternate.css' ) . '" title="Alternate">',
		'<link rel="stylesheet" href="' . home_url( '/' . $prefix . '-disabled.css' ) . '" disabled>',
		'<link rel="stylesheet" href="' . home_url( '/' . $prefix . '-integrity.css' ) . '" integrity="sha384-fixture">',
		'<link rel="stylesheet" href="' . home_url( '/' . $prefix . '-onload.css' ) . '" onload="fixture()">',
		'<link rel="stylesheet" href="' . home_url( '/' . $prefix . '-minified.css' ) . '" data-minify="1">',
		'<link rel="stylesheet" href="' . home_url( '/' . $prefix . '-no-minify.css' ) . '" data-no-minify>',
		'<noscript><link rel="stylesheet" href="' . home_url( '/' . $prefix . '-noscript.css' ) . '"></noscript>',
		'<!-- <link rel="stylesheet" href="' . home_url( '/' . $prefix . '-comment.css' ) . '"> -->',
		'<!--noptimize--><link rel="stylesheet" href="' . home_url( '/' . $prefix . '-noptimize.css' ) . '"><!--/noptimize-->',
		'<script>var fixture = "<link rel=\'stylesheet\' href=\'' . home_url( '/' . $prefix . '-script.css' ) . '\'>";</script>',
	];
	$html = local_css_page( array_merge( [ $first_tag, $inline_tag, $second_tag ], $excluded_tags ) );
	local_css_check( $frontend->optimize( $html ) === $html, 'pending local CSS retains original HTML exactly' );
	$render_job = local_css_claim( $post_url );
	local_css_check( $render_job['styles'] === $styles, 'frontend queues only supported linked styles in cascade order' );
	local_css_check( $jobs->fail( $render_job['id'], $render_job['token'], 'fixture browser failure' ) && $frontend->optimize( $html ) === $html, 'failed generation retains original linked and inline CSS' );
	$jobs->invalidate_urls( [ $post_url ] );
	$frontend->optimize( $html );
	$render_job = local_css_claim( $post_url );
	local_css_check( $jobs->complete( $render_job['id'], $render_job['token'], $render_job['fingerprint'], local_css_results( $render_job ) ), 'used CSS rendering fixture completes' );
	$used_row = $jobs->find( $post_url, false );
	$used_data = json_decode( $used_row->css, true );
	foreach ( $used_data['results'] as $result ) {
		local_css_check( ! array_key_exists( 'css', $result ) && is_readable( $result['path'] ) && filesize( $result['path'] ) > 0 && ! empty( $result['uri'] ), 'used CSS persists complete file metadata without the unused body' );
	}
	$used_html = $frontend->optimize( $html );
	local_css_check( $used_html !== $html && strpos( $used_html, $styles[0]['href'] ) === false && strpos( $used_html, $styles[1]['href'] ) === false, 'used mode replaces both processed stylesheet URLs' );
	local_css_check( strpos( $used_html, 'id="local-first"' ) < strpos( $used_html, 'id="local-inline"' ) && strpos( $used_html, 'id="local-inline"' ) < strpos( $used_html, 'id="local-second"' ), 'used CSS preserves link/inline/link cascade positions' );
	local_css_check( strpos( $used_html, 'media="screen"' ) !== false && strpos( $used_html, 'nonce="fixture-nonce"' ) !== false && strpos( $used_html, 'data-tone="first"' ) !== false && strpos( $used_html, $inline_tag ) !== false, 'used CSS preserves media/nonce/custom attributes and inline CSS' );
	foreach ( $excluded_tags as $tag ) {
		local_css_check( strpos( $used_html, $tag ) !== false, 'used CSS preserves unsupported/excluded stylesheet tag' );
	}
	$legacy_data = $used_data;
	foreach ( $legacy_data['results'] as &$result ) {
		$result['css'] = file_get_contents( $result['path'] );
	}
	unset( $result );
	$wpdb->update( $table, [ 'css' => wp_json_encode( $legacy_data ) ], [ 'id' => $used_row->id ] );
	local_css_check( $frontend->optimize( $html ) === $used_html, 'older used CSS rows with stored bodies retain identical delivery' );
	$wpdb->update( $table, [ 'css' => wp_json_encode( $used_data ) ], [ 'id' => $used_row->id ] );
	$edge_first = '<link id="edge-first" data-href="do-not-rewrite.css" data-note="href=&quot;literal.css&quot; media=&quot;print&quot;" rel=stylesheet href=' . $styles[0]['href'] . ' media=screen>';
	$edge_second = '<link id="edge-second" rel=stylesheet href=' . $styles[1]['href'] . ' media=all>';
	$edge_html = local_css_page( [ $edge_first, $inline_tag, $edge_second ] );
	$edge_used = $frontend->optimize( $edge_html );
	$edge_document = new DOMDocument();
	$old_xml_errors = libxml_use_internal_errors( true );
	$edge_document->loadHTML( $edge_used );
	libxml_clear_errors();
	libxml_use_internal_errors( $old_xml_errors );
	$edge_node = $edge_document->getElementById( 'edge-first' );
	local_css_check( $edge_node && $edge_node->getAttribute( 'href' ) !== $styles[0]['href'] && $edge_node->getAttribute( 'media' ) === 'screen', 'unquoted native href/media attributes are rewritten correctly' );
	local_css_check( $edge_node->getAttribute( 'data-href' ) === 'do-not-rewrite.css' && $edge_node->getAttribute( 'data-note' ) === 'href="literal.css" media="print"', 'native href rewrite preserves data-href and attribute text' );
	foreach ( [ 'missing_uri', 'empty_file', 'missing_file' ] as $damage ) {
		$damaged_row = $jobs->find( $post_url, false );
		$damaged_data = json_decode( $damaged_row->css, true );
		if ( 'missing_uri' === $damage ) {
			unset( $damaged_data['results'][0]['uri'] );
		} elseif ( 'empty_file' === $damage ) {
			file_put_contents( $damaged_data['results'][0]['path'], '' );
			clearstatcache( true, $damaged_data['results'][0]['path'] );
		} else {
			unlink( $damaged_data['results'][0]['path'] );
		}
		$wpdb->update( $table, [ 'css' => wp_json_encode( $damaged_data ) ], [ 'id' => $damaged_row->id ] );
		local_css_check( $frontend->optimize( $html ) === $html && ! $jobs->find( $post_url, false ), 'unusable used CSS metadata preserves all originals and invalidates the result: ' . $damage );
		local_css_check( $frontend->optimize( $html ) === $html, 'used CSS recovery queues generation with originals active: ' . $damage );
		$recovery_job = local_css_claim( $post_url );
		local_css_check( $jobs->complete( $recovery_job['id'], $recovery_job['token'], $recovery_job['fingerprint'], local_css_results( $recovery_job ) ), 'used CSS recovery publishes a fresh complete artifact: ' . $damage );
	}

	local_css_settings( 'critical' );
	$csp_html = str_replace( '<head>', '<head><meta http-equiv="Content-Security-Policy" content="default-src \'self\'">', $html );
	local_css_check( $frontend->optimize( $csp_html ) === $csp_html && ! $jobs->find( $post_url, false ), 'critical CSS preserves blocking originals under an enforcing CSP meta policy' );
	local_css_check( ! $jobs->find( $post_url, false ) && $frontend->optimize( $html ) === $html, 'mode change invalidates used result before critical generation' );
	$critical_job = local_css_claim( $post_url );
	local_css_check( $critical_job['mode'] === 'critical' && $critical_job['fingerprint'] !== $render_job['fingerprint'], 'critical mode gets a distinct generation fingerprint' );
	local_css_check( $jobs->complete( $critical_job['id'], $critical_job['token'], $critical_job['fingerprint'], local_css_results( $critical_job ) ), 'critical CSS rendering fixture completes' );
	$critical_data = json_decode( $jobs->find( $post_url, false )->css, true );
	local_css_check( array_column( $critical_data['results'], 'css' ) === array_column( local_css_results( $critical_job ), 'css' ), 'critical results retain exact worker bodies for inline delivery' );
	$critical_html = $frontend->optimize( $html );
	local_css_check( strpos( $critical_html, '.fixture-0{color:red}' ) !== false && strpos( $critical_html, '.fixture-1{color:blue}' ) !== false, 'critical mode inlines both generated stylesheet results' );
	local_css_check( substr_count( $critical_html, '<noscript>' ) === 3 && strpos( $critical_html, $first_tag ) !== false && strpos( $critical_html, $second_tag ) !== false, 'critical CSS retains original styles in per-sheet noscript fallbacks' );
	local_css_check( substr_count( $critical_html, 'media="print"' ) >= 3 && strpos( $critical_html, 'onload=' ) !== false && strpos( $critical_html, 'id="local-first"' ) < strpos( $critical_html, 'id="local-inline"' ) && strpos( $critical_html, 'id="local-inline"' ) < strpos( $critical_html, 'id="local-second"' ), 'critical CSS defers full styles at original cascade positions' );
	foreach ( $excluded_tags as $tag ) {
		local_css_check( strpos( $critical_html, $tag ) !== false, 'critical CSS preserves unsupported/excluded stylesheet tag' );
	}
	local_css_settings( 'critical', [ '.fixture-keep' ] );
	local_css_check( ! $jobs->find( $post_url, false ) && $frontend->optimize( $html ) === $html, 'safelist change invalidates prior result and preserves originals' );
	$safelist_job = local_css_claim( $post_url );
	local_css_check( $safelist_job['safelist'] === [ '.fixture-keep' ] && $safelist_job['fingerprint'] !== $critical_job['fingerprint'], 'changed safelist reaches worker with a new fingerprint' );
	local_css_check( ! $jobs->complete( $critical_job['id'], $critical_job['token'], $critical_job['fingerprint'], local_css_results( $critical_job ) ), 'mode/safelist-invalidated worker result cannot publish late' );
	local_css_check( $jobs->complete( $safelist_job['id'], $safelist_job['token'], $safelist_job['fingerprint'], local_css_results( $safelist_job ) ), 'updated safelist result completes before artifact corruption fixture' );
	$damaged_row = $jobs->find( $post_url, false );
	$damaged_manifest = json_decode( $damaged_row->css, true );
	array_pop( $damaged_manifest['results'] );
	$wpdb->update( $table, [ 'css' => wp_json_encode( $damaged_manifest ) ], [ 'id' => $damaged_row->id ] );
	local_css_check( $frontend->optimize( $html ) === $html && ! $jobs->find( $post_url, false ), 'partial persisted result manifest preserves all original CSS and invalidates artifact' );
	$jobs->invalidate_urls( [ $post_url ] );
	$exclude_sheets = function ( $excluded ) use ( $styles ) { return array_merge( (array) $excluded, array_column( $styles, 'href' ) ); };
	foreach ( [ 'noobrocket_rucss_external_exclusions', 'noobrocket_exclude_async_css' ] as $hook ) {
		add_filter( $hook, $exclude_sheets );
		local_css_check( $frontend->optimize( $html ) === $html && ! $jobs->find( $post_url, false ), 'existing stylesheet exclusion filter prevents generation: ' . $hook );
		remove_filter( $hook, $exclude_sheets );
	}
	$skip_attributes = function ( $attributes ) { $attributes[] = 'data-tone'; return $attributes; };
	add_filter( 'noobrocket_rucss_skip_styles_with_attr', $skip_attributes );
	local_css_check( $frontend->optimize( $html ) === $html && ! $jobs->find( $post_url, false ), 'existing style-attribute exclusion prevents generation' );
	remove_filter( 'noobrocket_rucss_skip_styles_with_attr', $skip_attributes );
	$exclude_minify = function () use ( $styles ) { return array_map( function ( $style ) { return preg_quote( wp_parse_url( $style['href'], PHP_URL_PATH ), '#' ); }, $styles ); };
	add_filter( 'noobrocket_exclude_css', $exclude_minify );
	local_css_check( $frontend->optimize( $html ) === $html && ! $jobs->find( $post_url, false ), 'existing CSS minifier path exclusions prevent local generation' );
	remove_filter( 'noobrocket_exclude_css', $exclude_minify );

	foreach ( [ 'used' => 'remove_unused_css', 'critical' => 'async_css' ] as $mode => $excluded_option ) {
		local_css_settings( $mode );
		local_css_context( $posts['publish'] );
		update_post_meta( $posts['publish'], '_noobrocket_exclude_' . $excluded_option, 1 );
		local_css_check( $frontend->optimize( $html ) === $html && ! $jobs->find( $post_url, false ), $mode . ' respects existing per-post optimization exclusion' );
		delete_post_meta( $posts['publish'], '_noobrocket_exclude_' . $excluded_option );
	}
	local_css_settings( 'used' );
	wp_set_current_user( 1 );
	local_css_check( $frontend->optimize( $html ) === $html && ! $jobs->find( $post_url, false ), 'authenticated frontend does not enqueue or rewrite local CSS' );
	wp_set_current_user( 0 );
	$cookies_before = $_COOKIE;
	foreach ( [ 'noobrocket_cache_dynamic_cookies', 'noobrocket_cache_mandatory_cookies' ] as $hook ) {
		$variant = function () { return [ 'fixture_variant' ]; };
		add_filter( $hook, $variant );
		foreach ( [ [], [ 'fixture_variant' => 'personalized' ] ] as $cookies ) {
			$_COOKIE = $cookies;
			local_css_check( $frontend->optimize( $html ) === $html && ! $jobs->find( $post_url, false ), 'cookie-varying cache configuration retains original CSS: ' . $hook );
		}
		remove_filter( $hook, $variant );
	}
	$reject_session = function ( $cookies ) { $cookies[] = 'wp_woocommerce_session_'; return $cookies; };
	add_filter( 'noobrocket_cache_reject_cookies', $reject_session );
	$_COOKIE = [ 'unrelated' => '1', 'wp_woocommerce_session_fixture' => 'private-cart-state' ];
	local_css_check( $frontend->optimize( $html ) === $html && ! $jobs->find( $post_url, false ), 'private cart/session cookie prevents CSS reuse even after an unrelated cookie' );
	remove_filter( 'noobrocket_cache_reject_cookies', $reject_session );
	$_COOKIE = $cookies_before;
	$_GET = [ 'fixture_unknown' => '1' ];
	$_SERVER['REQUEST_URI'] .= '?fixture_unknown=1';
	local_css_check( $frontend->optimize( $html ) === $html && ! $jobs->find( $post_url, false ), 'unknown query request does not enqueue or rewrite local CSS' );
	local_css_context( $posts['private'] );
	$private_rows_before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE queue_name IN ('local-critical','local-used')" );
	$private_html = $frontend->optimize( $html );
	local_css_check( $private_html === $html && (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE queue_name IN ('local-critical','local-used')" ) === $private_rows_before, 'private post does not enqueue or rewrite local CSS for any URL' );
	local_css_context( $posts['publish'] );
	$_GET = [ 'nonoobrocket' => '1' ];
	local_css_check( $frontend->optimize( $html ) === $html && ! $jobs->find( $post_url, false ), 'worker bypass query prevents recursive local CSS generation' );
	local_css_context( $posts['publish'] );
	$frontend->optimize( $html );
	$content_job = local_css_claim( $post_url );
	wp_update_post( [ 'ID' => $posts['publish'], 'post_content' => 'Updated fixture content invalidates CSS jobs.' ] );
	local_css_check( ! $jobs->find( $post_url, false ), 'real post content mutation invalidates its local CSS job' );
	local_css_check( ! $jobs->complete( $content_job['id'], $content_job['token'], $content_job['fingerprint'], local_css_results( $content_job ) ), 'post mutation rejects in-flight generation result' );
	$jobs->invalidate_all();
	$term_a_url = get_term_link( $terms['a'], 'category' );
	$unrelated_url = get_term_link( $terms['unrelated'], 'category' );
	foreach ( [ $term_a_url, $unrelated_url ] as $url ) {
		$jobs->enqueue( $url, false, 'used', $fingerprint, $styles, [] );
		$term_job = local_css_claim( $url );
		local_css_check( $jobs->complete( $term_job['id'], $term_job['token'], $term_job['fingerprint'], local_css_results( $term_job ) ), 'archive CSS fixture completes' );
	}
	wp_set_post_terms( $posts['publish'], [ $terms['b'] ], 'category' );
	local_css_check( ! $jobs->find( $term_a_url, false ), 'real category replacement invalidates removed archive CSS' );
	local_css_check( $jobs->find( $unrelated_url, false )->status === 'local-completed', 'category replacement preserves unrelated archive CSS' );
	$settings = get_option( 'noobrocket_settings' );
	local_css_check( empty( $settings['remove_unused_css'] ) && empty( $settings['async_css'] ), 'local modes preserve disabled cloud CSS flags' );
	echo 'PASS: ', count( $checks ), ' real WordPress local CSS job/render/invalidation assertions.', PHP_EOL;
} finally {
	add_filter( 'noobrocket_is_importing', '__return_true' );
	foreach ( $posts as $post_id ) {
		wp_delete_post( $post_id, true );
	}
	foreach ( $terms as $term_id ) {
		wp_delete_term( $term_id, 'category' );
	}
	update_option( 'noobrocket_settings', $settings_before );
	update_option( 'cron', $cron_before );
	$known_ids = array_column( $rows_before, 'id' );
	foreach ( $wpdb->get_col( "SELECT id FROM $table" ) as $id ) {
		if ( ! in_array( $id, $known_ids ) ) {
			$wpdb->delete( $table, [ 'id' => $id ] );
		}
	}
	foreach ( $rows_before as $row ) {
		$wpdb->replace( $table, $row );
	}
	remove_filter( 'noobrocket_is_importing', '__return_true' );
	remove_filter( 'pre_get_noobrocket_option_manual_preload', '__return_false' );
	remove_filter( 'noobrocket_generate_advanced_cache_file', '__return_false' );
	remove_filter( 'noobrocket_disable_htaccess', '__return_true' );
	remove_filter( 'pre_http_request', $block_http, PHP_INT_MAX - 1 );
	foreach ( $globals_before as $name => $value ) {
		$GLOBALS[ $name ] = $value;
	}
	$GLOBALS['wp']->request = $request_before;
	wp_set_current_user( $user_before );
	local_css_remove_fixture( $fixture );
}
