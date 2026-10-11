<?php
/** Diagnostics security, recovery and workload checks on a disposable install. */
$wordpress = isset( $argv[1] ) ? realpath( $argv[1] ) : false;
if ( ! $wordpress || ! is_file( $wordpress . '/wp-load.php' ) || ! is_file( $wordpress . '/.noobrocket-acceptance-site' ) ) {
	fwrite( STDERR, "Provide a disposable WordPress install with .noobrocket-acceptance-site.\n" ); exit( 2 );
}
$_SERVER['HTTP_HOST'] = '127.0.0.1:8765';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'POST';
define( 'WP_ADMIN', true );
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
require $wordpress . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';

use NoobRocket\Engine\Optimization\LocalCSS\Diagnostics;
use NoobRocket\Engine\Optimization\LocalCSS\Jobs;
use NoobRocket\Engine\Preload\Controller\LoadInitialSitemap;
use NoobRocket\Engine\Preload\Controller\PreloadUrl;
use NoobRocket\Engine\Preload\Controller\Queue;
use NoobRocket\Engine\Preload\Database\Queries\Cache;

class DiagnosticsJobsFixture extends Jobs {
	public $retried = 0;
	public function __construct() {}
	public function retry_failed(): int { ++$this->retried; return 3; }
	public function diagnostics(): array {
		return [ 'available' => true, 'mode' => 'used', 'counts' => [ 'pending' => 2, 'running' => 1, 'failed' => 3, 'ready' => 4 ],
			'paused' => (bool) get_option( 'noobrocket_local_css_paused', false ), 'worker' => get_option( 'noobrocket_local_css_worker', [] ),
			'failed' => [ [ 'id' => 42, 'url' => home_url( '/fixture/' ), 'reason' => 'Launch /usr/bin/chromium failed <script>unsafe</script>' ] ] ];
	}
}
class DiagnosticsCacheFixture extends Cache {
	public function __construct() {}
	public function get_status_counts(): array { return [ 'pending' => 4, 'in-progress' => 1, 'completed' => 9, 'failed' => 2 ]; }
}
class DiagnosticsSitemapFixture extends LoadInitialSitemap {
	public $canceled = 0;
	public function __construct() {}
	public function cancel_preload() { ++$this->canceled; }
}
class DiagnosticsQueueFixture extends Queue {
	public $resumed = 0;
	public function add_job_preload_job_load_initial_sitemap_async() { ++$this->resumed; return 1; }
}
class DiagnosticsSlowCacheFixture extends Cache {
	public $fixture_url;
	public $delay;
	public $batch;
	public function get_outdated_in_progress_jobs( int $delay = 3 ) {
		$this->delay = $delay;
		return array_values( array_filter( parent::get_outdated_in_progress_jobs( $delay ), function ( $row ) {
			return $this->fixture_url === $row->url;
		} ) );
	}
	public function get_pending_jobs( int $total = 45 ) { $this->batch = $total; return []; }
}
class DiagnosticsDispatchedQueueFixture extends Queue {
	// No pending AS action: the request was dispatched and its row awaits a response.
	public function get_pending_preload_actions(): array { return []; }
	public function get_active_preload_count(): int { return 0; }
	public function is_scheduled( $hook, $args = [] ) { return false; }
}
class DiagnosticsRequestException extends RuntimeException {}
$checks = 0;
function diagnostics_check( $condition, $message ) {
	global $checks;
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
	++$checks;
}
$keys = [ 'noobrocket_preload_paused', 'noobrocket_local_css_paused', 'noobrocket_local_css_worker',
	'_transient_noobrocket_diagnostics_notice', '_transient_timeout_noobrocket_diagnostics_notice',
	'_transient_noobrocket_cache_delivery_probe', '_transient_timeout_noobrocket_cache_delivery_probe' ];
$before = [];
foreach ( $keys as $key ) { $before[ $key ] = get_option( $key, null ); }
$cron_before = get_option( 'cron' );
$settings_before = get_option( 'noobrocket_settings' );
$jobs = new DiagnosticsJobsFixture();
$cache = new DiagnosticsCacheFixture();
$sitemap = new DiagnosticsSitemapFixture();
$queue = new DiagnosticsQueueFixture();
$diagnostics = new Diagnostics( $jobs, $cache, $sitemap, $queue );
$slow_query = null;
$outdated_override = static function () { return 0; };
$die = static function () { return static function ( $message ) { throw new DiagnosticsRequestException( 'denied' ); }; };
$redirect = static function ( $url ) { throw new DiagnosticsRequestException( 'redirect:' . $url ); };
add_filter( 'wp_die_handler', $die );
add_filter( 'wp_redirect', $redirect );
$request = static function ( $operation, $valid_nonce = true ) use ( $diagnostics ) {
	$_POST = [ 'operation' => $operation, '_noobrocket_diagnostics_nonce' => $valid_nonce ? wp_create_nonce( 'noobrocket_diagnostics' ) : 'invalid' ];
	$_REQUEST = $_POST;
	try { $diagnostics->handle(); }
	catch ( DiagnosticsRequestException $exception ) { return $exception->getMessage(); }
	throw new RuntimeException( 'Unexpected diagnostics request termination.' );
};
try {
	$container = apply_filters( 'noobrocket_container', null );
	$registered_diagnostics = $container->get( 'local_css_diagnostics' );
	diagnostics_check( $registered_diagnostics instanceof Diagnostics, 'native container registers diagnostics' );
	diagnostics_check( false !== has_action( 'admin_post_noobrocket_diagnostics' ), 'administrator POST route registered' );
	foreach ( [ 'noobrocket_after_clean_domain', 'after_noobrocket_clean_home' ] as $hook ) {
		diagnostics_check( 10 === has_action( $hook, [ $registered_diagnostics, 'invalidate_probe' ] ), $hook . ' registers the native diagnostics invalidator' );
	}
	wp_set_current_user( 0 );
	diagnostics_check( 'denied' === $request( 'pause_preload' ), 'anonymous action rejected' );
	diagnostics_check( 0 === $sitemap->canceled, 'unauthorized action has no side effects' );
	ob_start(); $diagnostics->render(); $diagnostics->render_action_form(); $hidden = ob_get_clean();
	diagnostics_check( '' === $hidden, 'diagnostics and action nonce hidden from visitors' );
	wp_set_current_user( 1 );
	diagnostics_check( 'denied' === $request( 'pause_preload', false ), 'invalid nonce rejected' );
	diagnostics_check( 0 === $sitemap->canceled, 'bad nonce has no side effects' );
	$_SERVER['REQUEST_METHOD'] = 'GET';
	diagnostics_check( 'denied' === $request( 'pause_preload' ), 'GET mutation rejected' );
	$_SERVER['REQUEST_METHOD'] = 'POST';
	diagnostics_check( 'denied' === $request( 'unknown' ), 'unknown operation rejected' );
	diagnostics_check( str_starts_with( $request( 'pause_preload' ), 'redirect:' ), 'successful pause redirects to native settings' );
	diagnostics_check( get_option( 'noobrocket_preload_paused' ) && 1 === $sitemap->canceled, 'pause stores state and invokes native queue cancellation' );
	$request( 'resume_preload' );
	diagnostics_check( ! get_option( 'noobrocket_preload_paused' ), 'resume clears pause state' );
	diagnostics_check( $queue->resumed === ( get_noobrocket_option( 'manual_preload', 0 ) ? 1 : 0 ), 'resume honors existing Preload Cache option' );
	$request( 'pause_css' ); diagnostics_check( get_option( 'noobrocket_local_css_paused' ), 'CSS pause stores site state' );
	$request( 'resume_css' ); diagnostics_check( ! get_option( 'noobrocket_local_css_paused' ), 'CSS resume clears pause state' );
	$request( 'retry' ); diagnostics_check( 1 === $jobs->retried, 'retry delegates to job owner' );
	diagnostics_check( [ 'operation' => 'retry', 'count' => 3 ] === get_transient( 'noobrocket_diagnostics_notice' ), 'action stores retry evidence without translated prose' );
	update_option( 'noobrocket_local_css_worker', [ 'state' => 'running', 'last_seen' => time(), 'node' => 'v22.0.0', 'chromium' => '145.0.0.0', 'php' => '8.3.35', 'error' => 'Launch C:\\secret\\chromium.exe failed' ], false );
	diagnostics_check( 5 === $diagnostics->limit_preload_during_css( 45 ), 'active browser job reduces preload batch' );
	diagnostics_check( 3 === $diagnostics->limit_preload_during_css( 3 ), 'lower configured preload bound preserved' );
	update_option( 'noobrocket_local_css_worker', [ 'state' => 'running', 'last_seen' => time() - 301 ], false );
	diagnostics_check( 45 === $diagnostics->limit_preload_during_css( 45 ), 'stale heartbeat does not throttle preload indefinitely' );
	update_option( 'noobrocket_local_css_worker', [ 'state' => 'idle', 'last_seen' => time() ], false );
	diagnostics_check( 45 === $diagnostics->limit_preload_during_css( 45 ), 'idle worker leaves native adaptive maximum intact' );
	update_option( 'noobrocket_local_css_worker', [ 'state' => 'running', 'last_seen' => time() ], false );
	$slow_query = new DiagnosticsSlowCacheFixture( $container->get( 'logger' ) );
	$slow_query->fixture_url = untrailingslashit( home_url( '/nbr-slow-preload-' . strtolower( wp_generate_password( 8, false, false ) ) ) );
	$slow_id = $slow_query->create_or_update( [ 'url' => $slow_query->fixture_url, 'status' => 'in-progress' ] );
	diagnostics_check( (bool) $slow_id, 'isolated real preload row created' );
	$slow_query->update_item( $slow_id, [ 'modified' => gmdate( 'Y-m-d H:i:s', time() - 30 ) ] );
	$slow_preload = new PreloadUrl( new NoobRocket\Admin\Options_Data( $settings_before ), new DiagnosticsDispatchedQueueFixture(), $slow_query, noobrocket_direct_filesystem() );
	$slow_preload->process_pending_jobs();
	diagnostics_check( 5 === $slow_query->batch && 3 === $slow_query->delay, 'active CSS caps native batch without shortening request grace period' );
	$slow_rows = $slow_query->get_rows_by_url( $slow_query->fixture_url );
	diagnostics_check( 'in-progress' === reset( $slow_rows )->status, '30-second dispatched request remains in progress under CSS load cap' );
	add_filter( 'noobrocket_preload_outdated', $outdated_override );
	$slow_preload->process_pending_jobs();
	remove_filter( 'noobrocket_preload_outdated', $outdated_override );
	$slow_rows = $slow_query->get_rows_by_url( $slow_query->fixture_url );
	diagnostics_check( 0 === $slow_query->delay && 'failed' === reset( $slow_rows )->status, 'explicit request-age filter override remains authoritative' );
	foreach ( [ 'Launch /usr/bin/chromium failed', 'Launch C:\\private\\chrome.exe failed', 'fetch https://user:secret@example.test/a.css?token=secret failed', "Node failed\ncommand: /private/node token=secret" ] as $error ) {
		$public = Diagnostics::error_message( $error );
		diagnostics_check( ! str_contains( $public, 'secret' ) && ! str_contains( $public, '/usr/bin' ) && ! str_contains( $public, 'private' ), 'error details hide credentials, command lines and server paths' );
	}
	$responses = [];
	$mock_http = static function ( $pre, $args, $url ) use ( &$responses ) {
		if ( 'NoobRocket/Diagnostics' !== ( $args['user-agent'] ?? '' ) ) { return $pre; }
		$responses[] = [ 'url' => $url, 'args' => $args ];
		return [ 'response' => [ 'code' => 200, 'message' => 'OK' ], 'headers' => [ 'cache-status' => count( $responses ) === 2 ? 'NoobRocket; hit' : '' ], 'body' => '' ];
	};
	add_filter( 'pre_http_request', $mock_http, 999, 3 );
	foreach ( [ 'header_missing', 'cache_hit' ] as $attempt => $expected_code ) {
		$_POST = [ 'operation' => 'probe', '_noobrocket_diagnostics_nonce' => wp_create_nonce( 'noobrocket_diagnostics' ) ];
		$_REQUEST = $_POST;
		try { do_action( 'admin_post_noobrocket_diagnostics' ); }
		catch ( DiagnosticsRequestException $exception ) {
			diagnostics_check( str_starts_with( $exception->getMessage(), 'redirect:' ), 'registered probe route redirects to native settings' );
		}
		$probe = get_transient( 'noobrocket_cache_delivery_probe' );
		diagnostics_check( $expected_code === $probe['code'] && 200 === $probe['status'] && ( 1 === $attempt ) === $probe['hit'], 'separate registered checks report their own first response' );
		diagnostics_check( $attempt + 1 === count( $responses ), 'each registered probe dispatch requests the homepage once' );
	}
	diagnostics_check( home_url( '/' ) === $responses[0]['url'] && home_url( '/' ) === $responses[1]['url'], 'separate checks only request the canonical local homepage' );
	diagnostics_check( [] === $responses[0]['args']['cookies'] && 0 === $responses[0]['args']['redirection'], 'probe sends no administrator cookies and never follows redirects' );
	$requests_before_render = count( $responses );
	ob_start(); $registered_diagnostics->render(); $recorded_panel = ob_get_clean();
	diagnostics_check( count( $responses ) === $requests_before_render && str_contains( $recorded_panel, 'HIT' ), 'rendering saved native probe evidence sends no request' );
	remove_filter( 'pre_http_request', $mock_http, 999 );
	foreach ( [ 'noobrocket_after_clean_domain', 'after_noobrocket_clean_home' ] as $hook ) {
		set_transient( 'noobrocket_cache_delivery_probe', $probe, DAY_IN_SECONDS );
		set_transient( 'noobrocket_diagnostics_notice', [ 'operation' => 'retry', 'count' => 3 ], MINUTE_IN_SECONDS );
		// Isolate the registered owner: other purge subscribers can enqueue background work.
		$callbacks = $GLOBALS['wp_filter'][ $hook ];
		$GLOBALS['wp_filter'][ $hook ] = new WP_Hook();
		add_action( $hook, [ $registered_diagnostics, 'invalidate_probe' ] );
		try { do_action( $hook, '' ); }
		finally { $GLOBALS['wp_filter'][ $hook ] = $callbacks; }
		diagnostics_check( false === get_transient( 'noobrocket_cache_delivery_probe' ), $hook . ' native dispatch discards saved probe evidence' );
		diagnostics_check( [ 'operation' => 'retry', 'count' => 3 ] === get_transient( 'noobrocket_diagnostics_notice' ), $hook . ' preserves unrelated native feedback' );
		ob_start(); $registered_diagnostics->render(); $reset_panel = ob_get_clean();
		diagnostics_check( str_contains( $reset_panel, esc_html__( 'Not checked', 'noobrocket' ) ) && ! str_contains( $reset_panel, esc_html__( 'Last check', 'noobrocket' ) ), 'purge invalidation renders an unchecked panel without the old check date' );
	}
	$mock_error = static function ( $pre, $args ) { return 'NoobRocket/Diagnostics' === ( $args['user-agent'] ?? '' ) ? new WP_Error( 'fixture', 'secret internal details' ) : $pre; };
	add_filter( 'pre_http_request', $mock_error, 999, 2 );
	$probe = $diagnostics->probe_cache();
	diagnostics_check( ! $probe['hit'] && 'transport_error' === $probe['code'] && ! str_contains( Diagnostics::probe_message( $probe ), 'secret' ), 'probe errors provide recovery guidance without raw transport details' );
	remove_filter( 'pre_http_request', $mock_error, 999 );
	$mock_redirect = static function ( $pre, $args ) { return 'NoobRocket/Diagnostics' === ( $args['user-agent'] ?? '' ) ? [ 'response' => [ 'code' => 301 ], 'headers' => [], 'body' => '' ] : $pre; };
	add_filter( 'pre_http_request', $mock_redirect, 999, 2 );
	$probe = $diagnostics->probe_cache();
	diagnostics_check( ! $probe['hit'] && 301 === $probe['status'], 'redirect response never reported as cache hit' );
	remove_filter( 'pre_http_request', $mock_redirect, 999 );
	ob_start(); $diagnostics->render(); $panel = ob_get_clean();
	diagnostics_check( str_contains( $panel, 'id="noobrocket-diagnostics-title"' ) && str_contains( $panel, esc_html__( 'Recent CSS failures', 'noobrocket' ) ), 'native panel renders queue and failure details' );
	diagnostics_check( ! str_contains( $panel, '<script>unsafe' ) && ! str_contains( $panel, '/usr/bin/chromium' ), 'panel escapes failures and hides server paths' );
	diagnostics_check( ! str_contains( $panel, '<form' ) && str_contains( $panel, 'form="noobrocket-diagnostics-actions"' ), 'panel buttons avoid nesting forms in settings form' );
	ob_start(); $diagnostics->render_action_form(); $form = ob_get_clean();
	diagnostics_check( str_contains( $form, 'method="post"' ) && str_contains( $form, '_noobrocket_diagnostics_nonce' ), 'external action form includes POST method and dedicated nonce' );
	update_option( 'noobrocket_preload_paused', true, false );
	$paused_http = 0;
	$count_paused_http = static function ( $pre ) use ( &$paused_http ) { ++$paused_http; return new WP_Error( 'fixture', 'Paused preload must not request HTTP.' ); };
	add_filter( 'pre_http_request', $count_paused_http, 999 );
	$pending_before = $container->get( 'preload_caches_query' )->get_status_counts();
	$actions_before = count( $container->get( 'preload_queue' )->get_pending_preload_actions() );
	$native_preload = $container->get( 'preload_url_controller' );
	$native_preload->preload_url( home_url( '/never-preloaded-by-this-fixture/' ) );
	$native_preload->process_pending_jobs();
	$container->get( 'load_initial_sitemap_controller' )->load_initial_sitemap();
	$container->get( 'fetch_sitemap_controller' )->parse_sitemap( home_url( '/never-requested-sitemap.xml' ) );
	remove_filter( 'pre_http_request', $count_paused_http, 999 );
	diagnostics_check( 0 === $paused_http, 'paused native preload entry points send no HTTP requests' );
	diagnostics_check( $container->get( 'preload_caches_query' )->get_status_counts() === $pending_before, 'paused native entry points preserve queue row statuses' );
	diagnostics_check( count( $container->get( 'preload_queue' )->get_pending_preload_actions() ) === $actions_before, 'paused native entry points enqueue no new URL actions' );
	diagnostics_check( get_option( 'noobrocket_settings' ) === $settings_before, 'diagnostics actions preserve optimization settings' );
	echo 'PASS: diagnostics security/recovery/workload (', $checks, " assertions)\n";
} finally {
	remove_filter( 'noobrocket_preload_outdated', $outdated_override );
	if ( $slow_query ) { $slow_query->delete_by_url( $slow_query->fixture_url ); }
	remove_filter( 'wp_die_handler', $die ); remove_filter( 'wp_redirect', $redirect );
	foreach ( $keys as $key ) { if ( null === $before[ $key ] ) { delete_option( $key ); } else { update_option( $key, $before[ $key ], false ); } }
	update_option( 'cron', $cron_before );
	wp_set_current_user( 0 );
}
