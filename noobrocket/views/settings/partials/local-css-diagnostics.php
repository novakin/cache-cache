<?php
/** Recorded cache checks and live queue state; actions use the separate diagnostics form. */
/** @var array $data Recorded diagnostics supplied by Diagnostics::render(). */
defined( 'ABSPATH' ) || exit;
$worker          = is_array( $data['worker'] ) ? $data['worker'] : [];
$seen            = (int) ( $worker['last_seen'] ?? 0 );
$fresh           = $seen && $seen >= time() - 300;
$worker_state    = $worker['state'] ?? '';
$worker_stale    = $seen && ! $fresh;
$worker_active   = $fresh && in_array( $worker_state, [ 'starting', 'idle', 'running' ], true );
$css_enabled     = in_array( $data['mode'], [ 'critical', 'used' ], true );
$css_active      = $css_enabled && ! $data['paused'];
$worker_needed   = $css_active && ! $worker_active && ( $data['counts']['pending'] || $data['counts']['running'] );
$probe           = is_array( $data['probe'] ) ? $data['probe'] : null;
$probe_status    = (int) ( $probe['status'] ?? 0 );
$probe_time      = (int) ( $probe['checked_at'] ?? 0 );
$probe_hit       = ! empty( $probe['hit'] );
$probe_failed    = $probe && ! $probe_hit && ( 200 !== $probe_status || 'transport_error' === ( $probe['code'] ?? '' ) );
$date_format     = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
$mode_labels     = [ 'off' => __( 'Original CSS', 'noobrocket' ), 'critical' => __( 'Critical CSS', 'noobrocket' ), 'used' => __( 'Used CSS', 'noobrocket' ) ];
$worker_labels   = [ 'starting' => __( 'Starting', 'noobrocket' ), 'idle' => __( 'Idle', 'noobrocket' ), 'running' => __( 'Generating CSS', 'noobrocket' ), 'stopped' => __( 'Last run ended', 'noobrocket' ), 'error' => __( 'Needs attention', 'noobrocket' ), 'ready' => __( 'Environment check recorded', 'noobrocket' ) ];
$preload_running = (int) ( $data['preload_counts']['in-progress'] ?? 0 );
$preload_pending = (int) ( $data['preload_counts']['pending'] ?? 0 );
$preload_failed  = (int) ( $data['preload_counts']['failed'] ?? 0 );
$preload_completed = (int) ( $data['preload_counts']['completed'] ?? 0 );
if ( ! $data['preload_enabled'] ) {
	$preload_label = __( 'Off', 'noobrocket' );
	$preload_message = __( 'Automatic warming is off. Enable Preload Cache to prepare public pages before visitors request them.', 'noobrocket' );
} elseif ( $data['preload_paused'] ) {
	$preload_label = __( 'Paused', 'noobrocket' );
	$preload_message = __( 'Pending preload work is preserved. Resume to continue warming the cache.', 'noobrocket' );
} elseif ( $preload_failed ) {
	$preload_label = __( 'Needs attention', 'noobrocket' );
	/* translators: %d: failed preload URL count. */
	$preload_message = sprintf( __( '%d pages could not be warmed. Check page availability and WordPress scheduled tasks before starting another preload.', 'noobrocket' ), $preload_failed );
} elseif ( $preload_running ) {
	$preload_label = __( 'In progress', 'noobrocket' );
	$preload_message = __( 'Preload work is recorded as in progress. Check WordPress scheduled tasks if it does not advance.', 'noobrocket' );
} elseif ( $preload_pending ) {
	$preload_label = __( 'Pages queued', 'noobrocket' );
	$preload_message = __( 'Pages are waiting for an automatic batch. WordPress scheduled tasks must run to continue.', 'noobrocket' );
} elseif ( $preload_completed ) {
	$preload_label = __( 'No pending pages', 'noobrocket' );
	$preload_message = __( 'The recorded preload queue has finished. New or changed pages may need another run.', 'noobrocket' );
} else {
	$preload_label = __( 'No queued pages', 'noobrocket' );
	$preload_message = __( 'No preload work is recorded yet. Use Preload settings to check automatic warming.', 'noobrocket' );
}
if ( ! $css_enabled ) {
	$css_label   = __( 'Off', 'noobrocket' );
	$css_message = __( 'Original styles are served. Choose Critical CSS or Used CSS in CSS settings to enable generation.', 'noobrocket' );
} elseif ( ! $data['available'] ) {
	$css_label   = __( 'Unavailable', 'noobrocket' );
	$css_message = __( 'CSS queue storage is unavailable. Check plugin activation and the database.', 'noobrocket' );
} elseif ( $data['paused'] ) {
	$css_label   = __( 'Paused', 'noobrocket' );
	$css_message = __( 'Pending work is preserved. Resume generation when you are ready.', 'noobrocket' );
} elseif ( $data['counts']['failed'] ) {
	$css_label   = __( 'Needs attention', 'noobrocket' );
	/* translators: %d: failed CSS job count. */
	$css_message = sprintf( __( '%d CSS jobs failed. Review the failure details, fix the cause, then retry.', 'noobrocket' ), $data['counts']['failed'] );
} elseif ( 'error' === $worker_state ) {
	$css_label   = __( 'Needs attention', 'noobrocket' );
	$css_message = __( 'The last CSS worker run failed. Review CSS worker details before its next run.', 'noobrocket' );
} elseif ( $worker_needed ) {
	$css_label   = __( 'Waiting for server worker', 'noobrocket' );
	$css_message = __( 'Queued CSS needs a worker run on this server. Check worker setup and scheduling; original styles remain available.', 'noobrocket' );
} elseif ( $data['counts']['running'] ) {
	$css_label   = __( 'Generating', 'noobrocket' );
	$css_message = __( 'CSS jobs are in progress. Original styles remain available until a valid result is ready.', 'noobrocket' );
} elseif ( $data['counts']['pending'] ) {
	$css_label   = __( 'Queued', 'noobrocket' );
	$css_message = __( 'Pages are queued. Scheduled worker runs generate their CSS; original styles remain available.', 'noobrocket' );
} elseif ( $data['counts']['ready'] ) {
	$css_label   = __( 'Results available', 'noobrocket' );
	$css_message = __( 'Completed CSS jobs are recorded. New or changed pages need further worker runs.', 'noobrocket' );
} else {
	$css_label   = __( 'No pages queued', 'noobrocket' );
	$css_message = __( 'Open a public page while logged out or enable Preload Cache to enqueue CSS. Generation needs the same-server worker.', 'noobrocket' );
}
$css_attention    = $worker_needed;
$css_error        = $css_enabled && ( ! $data['available'] || ( $css_active && ( $data['counts']['failed'] || 'error' === $worker_state ) ) );
$worker_attention = $css_active && ( 'error' === $worker_state || $worker_needed );
$preload_error    = $data['preload_enabled'] && ! $data['preload_paused'] && $preload_failed;
if ( 'error' === $worker_state ) {
	$worker_badge = __( 'Last run failed', 'noobrocket' );
} elseif ( ! $seen ) {
	$worker_badge = __( 'Not verified', 'noobrocket' );
} elseif ( $worker_stale ) {
	$worker_badge = __( 'Older than 5 min', 'noobrocket' );
} elseif ( 'ready' === $worker_state ) {
	$worker_badge = __( 'Setup check only', 'noobrocket' );
} else {
	$worker_badge = __( 'Recent report', 'noobrocket' );
}
$cache_badge      = __( 'Not checked', 'noobrocket' );
if ( $probe ) {
	$cache_badge = $probe_hit ? __( 'Cache hit confirmed', 'noobrocket' ) : ( $probe_failed ? __( 'Check failed', 'noobrocket' ) : __( 'No cache header', 'noobrocket' ) );
}
$button = static function ( $operation, $label, $disabled = false ) {
	$class = 'noobrocket-button' . ( 'probe' === $operation ? ' noobrocket-button-primary' : '' );
	printf( '<button type="submit" class="%s" form="noobrocket-diagnostics-actions" name="operation" value="%s" %s>%s</button>', esc_attr( $class ), esc_attr( $operation ), disabled( $disabled, true, false ), esc_html( $label ) );
};
?>
<section class="noobrocket-surface noobrocket-diagnostics noobrocket-site-status" aria-labelledby="noobrocket-diagnostics-title">
	<div class="noobrocket-surface-head"><h2 id="noobrocket-diagnostics-title"><?php esc_html_e( 'Page cache and CSS', 'noobrocket' ); ?></h2></div>
	<?php if ( ! empty( $data['notice']['message'] ) ) : ?><div class="notice notice-<?php echo esc_attr( $data['notice']['type'] ); ?>" data-noobrocket-toast><p><?php echo esc_html( $data['notice']['message'] ); ?></p></div><?php endif; ?>
	<div class="noobrocket-status-grid">
		<section class="noobrocket-status-panel noobrocket-cache-status<?php echo $probe_failed ? ' is-error' : ( $probe && ! $probe_hit ? ' is-attention' : '' ); ?>">
			<div class="noobrocket-cache-header">
				<div class="noobrocket-cache-heading">
					<h3 class="noobrocket-status-label"><svg class="noobrocket-icon" aria-hidden="true"><use href="#nbr-icon-cache"/></svg><?php esc_html_e( 'Homepage cache check', 'noobrocket' ); ?></h3>
					<span class="noobrocket-badge <?php echo $probe_hit ? 'is-success' : ( $probe_failed ? 'is-error' : ( $probe ? 'is-warning' : 'is-neutral' ) ); ?>"><?php echo esc_html( $cache_badge ); ?></span>
				</div>
				<div class="noobrocket-status-actions"><?php $button( 'probe', __( 'Check homepage cache', 'noobrocket' ) ); ?></div>
			</div>
			<div class="noobrocket-cache-result">
				<p><?php echo esc_html( $probe ? \NoobRocket\Engine\Optimization\LocalCSS\Diagnostics::probe_message( $probe ) : __( 'Check whether your homepage is served from cache to logged-out visitors.', 'noobrocket' ) ); ?></p>
				<?php if ( $probe_failed ) : ?><p><?php esc_html_e( 'A failed check does not prove visitor caching is broken.', 'noobrocket' ); ?></p><?php endif; ?>
			</div>
			<div class="noobrocket-cache-meta">
				<?php if ( $probe_time ) : ?>
				<p class="noobrocket-helper"><?php esc_html_e( 'Last check', 'noobrocket' ); ?>: <?php
					/* translators: 1: check date and time, 2: elapsed time. */
					echo esc_html( sprintf( __( '%1$s (%2$s ago)', 'noobrocket' ), wp_date( $date_format, $probe_time ), human_time_diff( $probe_time ) ) );
				?></p>
				<?php endif; ?>
				<p class="noobrocket-helper"><?php esc_html_e( 'This check covers the homepage only; it does not measure site speed.', 'noobrocket' ); ?></p>
				<p class="noobrocket-helper"><?php esc_html_e( 'This check makes one logged-out homepage request and may create its cache, even when preloading is off.', 'noobrocket' ); ?></p>
			</div>
			<details class="noobrocket-status-evidence noobrocket-cache-evidence">
				<summary><?php esc_html_e( 'Cache check details', 'noobrocket' ); ?></summary>
				<dl class="noobrocket-key-values"><div><dt><?php esc_html_e( 'Delivery result', 'noobrocket' ); ?></dt><dd><?php echo esc_html( ! $probe ? __( 'Not checked', 'noobrocket' ) : ( $probe_hit ? 'HIT' : ( 0 === $probe_status || 'transport_error' === ( $probe['code'] ?? '' ) ? __( 'No response', 'noobrocket' ) : /* translators: %d: HTTP response status. */ sprintf( __( 'HTTP %d', 'noobrocket' ), $probe_status ) ) ) ); ?></dd></div></dl>
				<p class="noobrocket-helper noobrocket-probe-scope"><?php esc_html_e( 'One anonymous homepage request checks the Cache-Cache PHP cache header. Other pages and site speed are not tested.', 'noobrocket' ); ?></p>
			</details>
		</section>
		<section class="noobrocket-status-panel noobrocket-preload-status<?php echo $preload_error ? ' is-error' : ''; ?>">
			<div class="noobrocket-status-content">
			<div class="noobrocket-status-heading"><h3 class="noobrocket-status-label"><svg class="noobrocket-icon" aria-hidden="true"><use href="#nbr-icon-clock"/></svg><?php esc_html_e( 'Preloading', 'noobrocket' ); ?></h3><span class="noobrocket-badge <?php echo $preload_error ? 'is-error' : 'is-neutral'; ?>"><?php echo esc_html( $preload_label ); ?></span></div>
			<p><?php echo esc_html( $preload_message ); ?></p>
			<?php if ( $data['preload_enabled'] || $data['preload_paused'] ) : ?>
				<dl class="noobrocket-queue-summary"><div><dt><?php esc_html_e( 'Waiting', 'noobrocket' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $preload_pending ) ); ?></dd></div><div><dt><?php esc_html_e( 'In progress', 'noobrocket' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $preload_running ) ); ?></dd></div></dl>
			<?php endif; ?>
			<?php if ( $data['preload_enabled'] && $data['preload_paused'] ) : ?>
				<div class="noobrocket-status-actions"><?php $button( 'resume_preload', __( 'Resume preloading', 'noobrocket' ) ); ?></div>
			<?php endif; ?>
			<details class="noobrocket-status-evidence"><summary><?php esc_html_e( 'Preload queue counts', 'noobrocket' ); ?></summary><div class="noobrocket-status-detail">
				<?php foreach ( [ 'completed' => __( 'Completed', 'noobrocket' ), 'pending' => __( 'Pending', 'noobrocket' ), 'in-progress' => __( 'In progress', 'noobrocket' ), 'failed' => __( 'Failed', 'noobrocket' ) ] as $key => $label ) : ?><span><b><?php echo esc_html( number_format_i18n( (int) ( $data['preload_counts'][ $key ] ?? 0 ) ) ); ?></b><?php echo esc_html( $label ); ?></span><?php endforeach; ?>
			</div><p class="noobrocket-helper"><?php esc_html_e( 'Completed counts describe recorded jobs, not current coverage of every page.', 'noobrocket' ); ?></p>
			<?php if ( $preload_failed ) : ?>
			<p class="noobrocket-helper"><?php esc_html_e( 'Recorded failure reasons', 'noobrocket' ); ?></p>
			<div class="noobrocket-status-detail">
			<?php
			$reason_labels = [ 'transport' => __( 'Request failed', 'noobrocket' ) ];
			$http_statuses = 0;
			foreach ( ( $data['preload_failures'] ?? [] ) as $key => $count ) {
				if ( preg_match( '/^http_[1-5][0-9]{2}$/D', (string) $key ) && ++$http_statuses <= 3 ) {
					/* translators: %d: validated HTTP response status. */
					$reason_labels[ $key ] = sprintf( __( 'HTTP %d', 'noobrocket' ), (int) substr( $key, 5 ) );
				}
			}
			$reason_labels += [ 'http_other' => __( 'Other HTTP responses', 'noobrocket' ), 'no_requests' => __( 'No preload requests', 'noobrocket' ), 'cache_desktop' => __( 'Desktop cache unavailable', 'noobrocket' ), 'cache_mobile' => __( 'Mobile cache unavailable', 'noobrocket' ), 'unavailable' => __( 'Reason unavailable', 'noobrocket' ) ];
			foreach ( $reason_labels as $key => $label ) : ?>
				<?php if ( ! empty( $data['preload_failures'][ $key ] ) ) : ?><span><b><?php echo esc_html( number_format_i18n( (int) $data['preload_failures'][ $key ] ) ); ?></b><?php echo esc_html( $label ); ?></span><?php endif; ?>
			<?php endforeach; ?>
			</div>
			<?php endif; ?>
			<?php if ( $data['preload_enabled'] && ! $data['preload_paused'] ) : ?><div class="noobrocket-status-actions"><?php $button( 'pause_preload', __( 'Pause preloading', 'noobrocket' ) ); ?></div><?php endif; ?>
			</details>
			</div>
			<div class="noobrocket-status-navigation"><a href="#preload"><?php esc_html_e( 'Preload settings', 'noobrocket' ); ?></a></div>
		</section>
		<section class="noobrocket-status-panel noobrocket-css-status<?php echo $css_error ? ' is-error' : ( $css_attention ? ' is-attention' : '' ); ?>">
			<div class="noobrocket-status-content">
			<div class="noobrocket-status-heading"><h3 class="noobrocket-status-label"><svg class="noobrocket-icon" aria-hidden="true"><use href="#nbr-icon-code"/></svg><?php esc_html_e( 'Local CSS generation', 'noobrocket' ); ?></h3><span class="noobrocket-badge <?php echo $css_error ? 'is-error' : ( $css_attention ? 'is-warning' : 'is-neutral' ); ?>"><?php echo esc_html( $css_label ); ?></span></div>
			<p class="noobrocket-helper noobrocket-css-mode"><?php esc_html_e( 'CSS delivery', 'noobrocket' ); ?>: <?php echo esc_html( $mode_labels[ $data['mode'] ] ?? $mode_labels['off'] ); ?></p>
			<p><?php echo esc_html( $css_message ); ?></p>
			<?php if ( $css_enabled ) : ?>
			<dl class="noobrocket-queue-summary"><div><dt><?php esc_html_e( 'Waiting', 'noobrocket' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $data['counts']['pending'] ) ); ?></dd></div><div><dt><?php esc_html_e( 'Running', 'noobrocket' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $data['counts']['running'] ) ); ?></dd></div></dl>
			<?php endif; ?>
			<?php if ( $css_enabled && $data['available'] && ( $data['counts']['failed'] || $data['paused'] ) ) : ?>
			<div class="noobrocket-status-actions">
				<?php if ( $data['counts']['failed'] && ! $data['paused'] ) { $button( 'retry', __( 'Retry failed CSS jobs', 'noobrocket' ) ); } ?>
				<?php if ( $data['paused'] ) { $button( 'resume_css', __( 'Resume CSS generation', 'noobrocket' ) ); } ?>
			</div>
			<?php endif; ?>
			<?php if ( $css_enabled ) : ?>
			<details class="noobrocket-status-evidence"><summary><?php esc_html_e( 'CSS queue counts', 'noobrocket' ); ?></summary><div class="noobrocket-status-detail">
				<?php foreach ( [ 'ready' => __( 'Ready', 'noobrocket' ), 'pending' => __( 'Pending', 'noobrocket' ), 'running' => __( 'Running', 'noobrocket' ), 'failed' => __( 'Failed', 'noobrocket' ) ] as $key => $label ) : ?><span><b><?php echo esc_html( number_format_i18n( (int) ( $data['counts'][ $key ] ?? 0 ) ) ); ?></b><?php echo esc_html( $label ); ?></span><?php endforeach; ?>
			</div><p class="noobrocket-helper"><?php esc_html_e( 'Counts cover the selected CSS mode and describe jobs, not every page on your site. Pausing preserves pending work.', 'noobrocket' ); ?></p>
			<?php if ( $data['available'] && ! $data['paused'] ) : ?><div class="noobrocket-status-actions"><?php $button( 'pause_css', __( 'Pause CSS generation', 'noobrocket' ) ); ?></div><?php endif; ?>
			</details>
			<?php endif; ?>
			</div>
			<div class="noobrocket-status-navigation">
				<?php if ( $worker_attention || ( $css_active && ! $seen ) ) : ?><a href="<?php echo esc_url( plugins_url( 'docs/index.html#css', NOOBROCKET_FILE ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Worker setup and scheduling', 'noobrocket' ); ?></a><?php endif; ?>
				<?php if ( $css_active && ! array_sum( $data['counts'] ) ) : ?><a href="#preload"><?php esc_html_e( 'Preload settings', 'noobrocket' ); ?></a><?php endif; ?>
				<a href="#file_optimization"><?php esc_html_e( 'CSS settings', 'noobrocket' ); ?></a>
			</div>
		</section>
	</div>
	<?php if ( $css_enabled || $seen ) : ?>
	<div class="noobrocket-card-body">
		<?php if ( $css_enabled && $data['failed'] ) : ?>
		<details class="noobrocket-status-evidence">
			<summary><?php esc_html_e( 'Recent CSS failures', 'noobrocket' ); ?></summary>
			<ul>
			<?php foreach ( $data['failed'] as $failed ) : ?>
				<li>
					<strong><?php echo esc_html( $failed['url'] ); ?></strong>
					<p><?php echo esc_html( \NoobRocket\Engine\Optimization\LocalCSS\Diagnostics::error_message( (string) $failed['reason'] ) ); ?></p>
					<p class="noobrocket-helper"><?php echo esc_html( ! empty( $failed['automatic_retry'] ) ? __( 'Automatic retry is available after the delay. A server worker run is required.', 'noobrocket' ) : __( 'Automatic attempts have stopped. Fix the cause before retrying manually.', 'noobrocket' ) ); ?></p>
				</li>
			<?php endforeach; ?>
			</ul>
		</details>
		<?php endif; ?>
			<details class="noobrocket-worker-details"<?php echo $worker_attention ? ' open' : ''; ?>>
				<summary>
					<span class="noobrocket-worker-label"><svg class="noobrocket-icon" aria-hidden="true"><use href="#nbr-icon-server"/></svg><?php esc_html_e( 'CSS worker details', 'noobrocket' ); ?></span>
					<span class="noobrocket-badge <?php echo $css_active && 'error' === $worker_state ? 'is-error' : ( $worker_attention ? 'is-warning' : 'is-neutral' ); ?>"><?php echo esc_html( $css_active || ! $seen ? $worker_badge : __( 'Previous activity', 'noobrocket' ) ); ?></span>
				</summary>
				<div class="noobrocket-worker-body">
			<p><?php
				if ( ! $css_active ) {
					echo esc_html( $seen ? __( 'Generation is off or paused. The recorded worker report below is historical.', 'noobrocket' ) : __( 'Generation is off or paused. No worker report has been recorded.', 'noobrocket' ) );
				} elseif ( ! $seen ) {
					esc_html_e( 'No worker report recorded. Set up Node.js, Chromium and scheduling on this WordPress server.', 'noobrocket' );
				} elseif ( 'ready' === $worker_state ) {
					esc_html_e( 'A setup check passed; it does not show a running or scheduled worker.', 'noobrocket' );
				} elseif ( 'stopped' === $worker_state ) {
					esc_html_e( 'The last run ended. Scheduled runs are needed to process new jobs.', 'noobrocket' );
				} elseif ( 'error' === $worker_state ) {
					esc_html_e( 'The last worker run reported an error. Check its details and worker setup.', 'noobrocket' );
				} elseif ( $worker_stale ) {
					esc_html_e( 'No report in the last five minutes. Check the worker process and schedule.', 'noobrocket' );
				} else {
					esc_html_e( 'Recent worker activity is recorded. This report does not verify future scheduled runs.', 'noobrocket' );
				}
			?></p>
			<dl class="noobrocket-key-values">
				<div><dt><?php esc_html_e( 'Last recorded state', 'noobrocket' ); ?></dt><dd><?php echo esc_html( $seen ? ( $worker_labels[ $worker_state ] ?? __( 'Unknown', 'noobrocket' ) ) : __( 'No worker report', 'noobrocket' ) ); ?></dd></div>
				<?php if ( $seen ) : ?>
				<div><dt><?php esc_html_e( 'Last report', 'noobrocket' ); ?></dt><dd><?php
					/* translators: 1: report date and time, 2: elapsed time. */
					echo esc_html( sprintf( __( '%1$s (%2$s ago)', 'noobrocket' ), wp_date( $date_format, $seen ), human_time_diff( $seen ) ) );
				?></dd></div>
				<?php foreach ( [ 'node' => 'Node.js', 'chromium' => 'Chromium', 'php' => 'PHP CLI' ] as $key => $label ) : ?>
					<?php if ( preg_match( '/^[v\d][\d.a-zA-Z-]{0,40}$/', (string) ( $worker[ $key ] ?? '' ) ) ) : ?>
					<div><dt><?php echo esc_html( $label ); ?></dt><dd><?php echo esc_html( $worker[ $key ] ); ?></dd></div>
					<?php endif; ?>
				<?php endforeach; ?>
				<?php if ( ! empty( $worker['error'] ) ) : ?>
				<div>
					<dt><?php esc_html_e( 'Last recorded error', 'noobrocket' ); ?></dt>
					<dd><?php echo esc_html( \NoobRocket\Engine\Optimization\LocalCSS\Diagnostics::error_message( (string) $worker['error'] ) ); ?></dd>
				</div>
				<?php endif; ?>
				<?php endif; ?>
			</dl>
			<?php if ( $css_enabled && $data['available'] ) : ?><p id="noobrocket-regenerate-scope" class="noobrocket-helper"><?php esc_html_e( 'Regeneration clears CSS results and page cache. Public visits or preloading queue fresh CSS; original styles remain available.', 'noobrocket' ); ?></p><?php endif; ?>
			<?php if ( ! $worker_attention && ! ( $css_active && ! $seen ) ) : ?><div class="noobrocket-status-navigation"><a href="<?php echo esc_url( plugins_url( 'docs/index.html#css', NOOBROCKET_FILE ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Worker setup and scheduling', 'noobrocket' ); ?></a></div><?php endif; ?>
				</div>
			</details>
	</div>
	<?php endif; ?>
</section>
