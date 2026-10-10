<?php
/** Worker leases, status and operator queue controls on the marked disposable site. */
$root = realpath( $argv[1] ?? '' );
if ( ! $root || ! is_file( $root . '/.noobrocket-acceptance-site' ) ) { exit( 2 ); }
define( 'WP_ADMIN', true );
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
$_SERVER['HTTP_HOST'] = '127.0.0.1:8765';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'POST';
require $root . '/wp-load.php';
$jobs = apply_filters( 'noobrocket_container', null )->get( 'local_css_jobs' );
$checks = 0;
function worker_check( $condition, $message ) {
	global $checks;
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
	$checks++;
}
$before = [];
foreach ( [ 'noobrocket_local_css_worker', 'noobrocket_local_css_worker_lock', 'noobrocket_local_css_paused', 'cron' ] as $key ) { $before[ $key ] = get_option( $key, '__missing__' ); }
worker_check( empty( $before['noobrocket_local_css_worker_lock'] ) || '__missing__' === $before['noobrocket_local_css_worker_lock'], 'no live worker is interrupted by this fixture' );
worker_check( ! array_sum( $jobs->diagnostics()['counts'] ), 'exclusive disposable site has no pre-existing CSS jobs' );
$mode = 'used';
$mode_filter = function () use ( &$mode ) { return $mode; };
add_filter( 'pre_get_noobrocket_option_local_css_mode', $mode_filter );
add_filter( 'pre_get_noobrocket_option_manual_preload', '__return_false' );
$urls = [ home_url( '/worker-control-fixture/' ), home_url( '/worker-control-critical/' ) ];
$styles = [ [ 'href' => home_url( '/worker-fixture.css' ), 'media' => 'all' ] ];
try {
	$token = $jobs->worker_start( [ 'node' => '22.0.0', 'platform' => 'fixture', 'sandbox' => true, 'processed' => 0, 'error' => '' ] );
	worker_check( is_string( $token ) && 36 === strlen( $token ), 'one worker acquires a site lease' );
	worker_check( null === $jobs->worker_start( [] ), 'overlapping worker cannot launch another browser' );
	worker_check( ! $jobs->worker_update( 'invalid', 'running' ), 'invalid worker identity rejected' );
	worker_check( ! $jobs->worker_update( $token, 'unknown' ), 'unknown worker state rejected' );
	worker_check( $jobs->worker_update( $token, 'running', [ 'chromium' => '123', 'processed' => 2, 'unknown' => 'discard' ] ), 'owner updates worker health' );
	$health = $jobs->diagnostics()['worker'];
	worker_check( 'running' === $health['state'] && 2 === $health['processed'] && '123' === $health['chromium'], 'diagnostics exposes real state and runtime versions' );
	worker_check( PHP_VERSION === $health['php'] && time() - $health['last_seen'] < 5 && ! isset( $health['unknown'] ), 'PHP version and heartbeat are server-owned; unknown fields discarded' );
	global $wpdb;
	$wpdb->update( $wpdb->options, [ 'option_value' => ( time() - 1 ) . ':' . $token ], [ 'option_name' => 'noobrocket_local_css_worker_lock' ] );
	wp_cache_delete( 'noobrocket_local_css_worker_lock', 'options' );
	$successor = $jobs->worker_start( [] );
	worker_check( is_string( $successor ) && $successor !== $token, 'expired worker lease becomes reclaimable' );
	worker_check( ! $jobs->worker_update( $token, 'running' ) && ! $jobs->worker_stop( $token, 'stopped' ), 'late worker cannot update or release successor lease' );
	worker_check( null === $jobs->worker_start( [] ), 'successor remains exclusive after rejected late cleanup' );
	worker_check( $jobs->worker_stop( $successor, 'ready' ), 'owner releases its lease and retains ready health' );
	worker_check( false === get_option( 'noobrocket_local_css_worker_lock' ) && 'ready' === $jobs->diagnostics()['worker']['state'], 'finished check releases the browser slot' );
	worker_check( $jobs->enqueue( $urls[0], false, 'used', md5( 'used' ), $styles, [] ), 'operator fixture queues used CSS' );
	update_option( 'noobrocket_local_css_paused', true, false );
	worker_check( null === $jobs->claim() && 1 === $jobs->diagnostics()['counts']['pending'], 'pause keeps queued work without claiming it' );
	worker_check( $jobs->diagnostics()['paused'], 'diagnostics reflects CSS pause state' );
	update_option( 'noobrocket_local_css_paused', false );
	$job = $jobs->claim();
	worker_check( is_array( $job ) && 1 === $jobs->diagnostics()['counts']['running'], 'resume makes pending work claimable' );
	worker_check( $jobs->fail( $job['id'], $job['token'], 'Fixture missing font.' ), 'failed generation records its reason' );
	$data = $jobs->diagnostics();
	worker_check( 1 === $data['counts']['failed'] && 'Fixture missing font.' === $data['failed'][0]['reason'], 'diagnostics exposes failed job and actionable reason' );
	worker_check( 1 === $jobs->retry_failed() && 1 === $jobs->diagnostics()['counts']['pending'], 'retry resets failed current-mode work immediately' );
	worker_check( ! $jobs->complete( $job['id'], $job['token'], $job['fingerprint'], [ [ 'href' => $styles[0]['href'], 'css' => '.fixture{color:red}' ] ] ), 'retry rejects the previous completion token' );
	$job = $jobs->claim();
	worker_check( $jobs->complete( $job['id'], $job['token'], $job['fingerprint'], [ [ 'href' => $styles[0]['href'], 'css' => '.fixture{color:red}' ] ] ), 'retried generation can publish normally' );
	worker_check( 1 === $jobs->diagnostics()['counts']['ready'], 'ready results counted separately from queue work' );
	$mode = 'critical';
	$jobs->enqueue( $urls[1], false, 'critical', md5( 'critical' ), $styles, [] );
	$job = $jobs->claim();
	$jobs->fail( $job['id'], $job['token'], 'Other mode fixture.' );
	$mode = 'used';
	worker_check( 0 === $jobs->retry_failed() && 'local-failed' === $jobs->find( $urls[1] )->status, 'retry preserves other CSS modes' );
	$mode = 'off';
	worker_check( 0 === $jobs->retry_failed(), 'off mode does not queue unsupported retries' );
} finally {
	$jobs->invalidate_urls( $urls );
	remove_filter( 'pre_get_noobrocket_option_local_css_mode', $mode_filter );
	remove_filter( 'pre_get_noobrocket_option_manual_preload', '__return_false' );
	foreach ( $before as $key => $value ) { if ( '__missing__' === $value ) { delete_option( $key ); } else { update_option( $key, $value, false ); } }
}
worker_check( ! array_sum( $jobs->diagnostics()['counts'] ), 'worker fixture removes its jobs and generated files' );
foreach ( $before as $key => $value ) { worker_check( get_option( $key, '__missing__' ) === $value, 'worker fixture restores ' . $key ); }
echo 'PASS: ', $checks, ' real WordPress worker/queue-control assertions.', PHP_EOL;
