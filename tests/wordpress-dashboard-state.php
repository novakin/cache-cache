<?php
/** Isolated native template status/ownership proof; no WordPress bootstrap or writes. */
declare(strict_types=1);
namespace NoobRocket\Engine\Optimization\LocalCSS {
	class Diagnostics {
		public static function probe_message( $probe ) { return 'Recorded homepage result.'; }
		public static function error_message( $reason ) { return $reason; }
	}
}
namespace {
	define( 'ABSPATH', '/isolated/' );
	define( 'NOOBROCKET_FILE', '/isolated/noobrocket.php' );
	$checks = 0;
	function __( $text ) { return $text; }
	function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $text ) { return esc_html( $text ); }
	function esc_url( $text ) { return esc_attr( $text ); }
	function esc_html_e( $text ) { echo esc_html( $text ); }
	function get_option( $name ) { return 'date_format' === $name ? 'Y-m-d' : 'H:i'; }
	function plugins_url( $path ) { return '/plugins/noobrocket/' . $path; }
	function disabled( $value ) { return $value ? 'disabled' : ''; }
	function wp_date( $format, $time ) { return gmdate( $format, $time ); }
	function human_time_diff() { return 'one minute'; }
	function number_format_i18n( $number ) { return number_format( $number ); }
	function current_user_can() { return true; }
	function dashboard_check( $value, $message ) {
		if ( ! $value ) { throw new \RuntimeException( 'FAIL: ' . $message ); }
		++$GLOBALS['checks'];
	}
	function render_dashboard( array $overrides = [], string $template = 'local-css-diagnostics' ): array {
		$data = array_replace( [
			'worker' => [], 'probe' => null, 'notice' => [], 'available' => true,
			'mode' => 'off', 'paused' => false, 'counts' => [ 'pending' => 0, 'running' => 0, 'ready' => 0, 'failed' => 0 ], 'failed' => [],
			'preload_enabled' => true, 'preload_paused' => false,
			'preload_counts' => [ 'pending' => 0, 'in-progress' => 0, 'completed' => 0, 'failed' => 0 ],
		], $overrides );
		ob_start();
		require dirname( __DIR__ ) . '/noobrocket/views/settings/partials/' . $template . '.php';
		$html = ob_get_clean();
		$document = new \DOMDocument();
		@$document->loadHTML( $html );
		return [ $html, new \DOMXPath( $document ) ];
	}
	[ $html, $xpath ] = render_dashboard();
	dashboard_check( false !== strpos( $html, 'No queued pages' ) && false === strpos( $html, 'Queue complete' ), 'zero-count preload cannot claim completion' );
	dashboard_check( 0 === $xpath->query( '//details[contains(@class,"noobrocket-recovery")]' )->length, 'Original CSS has no unexplained disabled recovery action' );
	dashboard_check( 0 === $xpath->query( '//section[contains(@class,"noobrocket-css-status")]//details[contains(@class,"noobrocket-worker-details")]' )->length, 'deep worker evidence cannot stretch the neighboring preload summary' );
	dashboard_check( 0 === $xpath->query( '//a[contains(@href,"docs/index.html#css")]' )->length && false === strpos( $html, 'README.md' ), 'CSS off without history does not request worker setup' );
	[ $html ] = render_dashboard( [ 'preload_enabled' => false ] );
	dashboard_check( false !== strpos( $html, 'Automatic warming is off.' ) && false === strpos( $html, 'Queued pages load in small, automatic batches.' ), 'off preloading never claims batches are running' );
	[ $html ] = render_dashboard( [ 'preload_paused' => true ] );
	dashboard_check( false !== strpos( $html, 'Pending preload work is preserved.' ) && false !== strpos( $html, 'Resume preloading' ), 'paused warming explains recovery without claiming active work' );
	[ $html ] = render_dashboard( [ 'preload_counts' => [ 'completed' => 3 ] ] );
	dashboard_check( false !== strpos( $html, 'No pending pages' ) && false !== strpos( $html, 'New or changed pages may need another run.' ), 'completed jobs do not imply all visitor pages are cached' );
	[ $html, $xpath ] = render_dashboard( [ 'preload_counts' => [ 'failed' => 2 ] ] );
	dashboard_check( 1 === $xpath->query( '//section[contains(@class,"noobrocket-preload-status")]//span[contains(@class,"is-error")]' )->length, 'failed preload URLs use error severity' );
	[ $html, $xpath ] = render_dashboard( [ 'mode' => 'used', 'counts' => [ 'pending' => 1, 'running' => 0, 'failed' => 0, 'ready' => 0 ] ] );
	dashboard_check( false !== strpos( $html, 'Waiting for server worker' ) && 1 === $xpath->query( '//details[contains(@class,"noobrocket-worker-details") and @open]' )->length, 'waiting work exposes actionable missing-worker evidence' );
	[ $html, $xpath ] = render_dashboard( [ 'mode' => 'used', 'worker' => [ 'state' => 'stopped', 'last_seen' => time() - 600 ], 'counts' => [ 'pending' => 0, 'running' => 0, 'failed' => 0, 'ready' => 1 ] ] );
	dashboard_check( 0 === $xpath->query( '//section[contains(@class,"noobrocket-css-status")]//span[contains(@class,"is-warning")]' )->length, 'ready CSS does not imply an idle worker must currently be running' );
	[ $html, $xpath ] = render_dashboard( [ 'mode' => 'used', 'worker' => [ 'state' => 'error', 'last_seen' => time() ], 'counts' => [ 'pending' => 0, 'running' => 0, 'failed' => 1, 'ready' => 0 ], 'failed' => [ [ 'url' => 'https://site.test/failed/', 'reason' => 'Unsupported stylesheet', 'automatic_retry' => false ] ] ] );
	dashboard_check( 1 === $xpath->query( '//section[contains(@class,"noobrocket-css-status")]//span[contains(@class,"is-error")]' )->length, 'failed CSS jobs use error severity' );
	dashboard_check( 1 === $xpath->query( '//details[contains(@class,"noobrocket-worker-details")]//span[contains(@class,"is-error")]' )->length && false !== strpos( $html, 'Automatic attempts have stopped.' ), 'worker failure and manual recovery are truthful' );
	foreach ( [ [ 'mode' => 'off' ], [ 'mode' => 'used', 'paused' => true ] ] as $inactive ) {
		[ $html, $xpath ] = render_dashboard( array_merge( $inactive, [ 'worker' => [ 'state' => 'error', 'last_seen' => time() ], 'counts' => [ 'pending' => 2, 'running' => 0, 'failed' => 1, 'ready' => 0 ] ] ) );
		dashboard_check( 0 === $xpath->query( '//details[contains(@class,"noobrocket-worker-details") and @open]' )->length && 0 === $xpath->query( '//section[contains(@class,"noobrocket-css-status") and contains(@class,"is-error")]' )->length, 'inactive CSS retains collapsed history without an active error' );
		dashboard_check( false !== strpos( $html, 'Previous activity' ) && 0 === $xpath->query( '//button[@value="retry"]' )->length, 'inactive CSS offers no ineffective retry action' );
		dashboard_check( false !== strpos( $html, 'The recorded worker report below is historical.' ) && false === strpos( $html, 'Check its details and worker setup.' ), 'inactive reports use historical context instead of active recovery instructions' );
	}
	[ $html, $xpath ] = render_dashboard( [ 'mode' => 'critical', 'paused' => true, 'worker' => [ 'state' => 'stopped', 'last_seen' => time() - 600, 'node' => 'v22.0.0', 'chromium' => '123.0', 'php' => '8.3.35' ] ] );
	dashboard_check( false === strpos( $html, 'Scheduled runs are needed to process new jobs.' ) && false !== strpos( $html, 'The recorded worker report below is historical.' ), 'paused successful history does not request an active schedule' );
	dashboard_check( 1 === $xpath->query( '//details[contains(@class,"noobrocket-worker-details")]//dl/div[dt="Last recorded state" and dd="Last run ended"]' )->length && 1 === $xpath->query( '//details[contains(@class,"noobrocket-worker-details")]//dl/div[dt="Last report"]' )->length, 'recorded worker outcome and timestamp stay grouped in labeled evidence' );
	dashboard_check( 1 === $xpath->query( '//details[contains(@class,"noobrocket-worker-details")]//dl/div[dt="Node.js" and dd="v22.0.0"]' )->length && 0 === $xpath->query( '//details[contains(@class,"noobrocket-worker-details")]//details' )->length, 'runtime versions use the existing disclosure rather than nested evidence' );
	[ $html ] = render_dashboard( [ 'mode' => 'critical', 'paused' => true ] );
	dashboard_check( false !== strpos( $html, 'No worker report has been recorded.' ) && false === strpos( $html, 'Set up Node.js' ), 'paused generation without evidence avoids active setup instructions' );
	[ $html, $xpath ] = render_dashboard( [ 'mode' => 'used', 'worker' => [ 'state' => 'error', 'last_seen' => time() ], 'counts' => [ 'pending' => 2, 'running' => 0, 'failed' => 0, 'ready' => 0 ] ] );
	dashboard_check( false !== strpos( $html, 'The last CSS worker run failed.' ) && false === strpos( $html, 'Waiting for server worker' ), 'known worker failure takes precedence over a generic waiting state' );
	[ $html, $xpath ] = render_dashboard( [ 'preload_counts' => [ 'pending' => 2, 'in-progress' => 1, 'failed' => 0, 'completed' => 7 ] ] );
	dashboard_check( 1 === $xpath->query( '//section[contains(@class,"noobrocket-preload-status")]//dl[not(ancestor::details)]/div[dt="Waiting" and dd="2"]' )->length && 1 === $xpath->query( '//section[contains(@class,"noobrocket-preload-status")]//dl[not(ancestor::details)]/div[dt="Running" and dd="1"]' )->length, 'current waiting/running work is visible without expanding evidence' );
	dashboard_check( 1 === $xpath->query( '//section[contains(@class,"noobrocket-preload-status")]//details//button[@value="pause_preload"]' )->length && 0 === $xpath->query( '//section[contains(@class,"noobrocket-preload-status")]//button[@value="pause_preload" and not(ancestor::details)]' )->length, 'routine pause action stays secondary to useful status and settings' );
	[ $html, $xpath ] = render_dashboard( [ 'object_cache' => [ 'health' => false, 'external' => true, 'provider' => 'Redis', 'connected' => false, 'message' => 'Native health check failed.' ] ], 'object-cache-diagnostics' );
	dashboard_check( 1 === $xpath->query( '//span[contains(@class,"is-error")]' )->length && 1 === $xpath->query( '//div[contains(@class,"noobrocket-status-navigation")]/a' )->length, 'object-cache failure severity and help placement use shared components' );
	echo 'PASS: dashboard status ownership (', $checks, " assertions; isolated native templates)\n";
}
