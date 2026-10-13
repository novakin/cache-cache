<?php
namespace NoobRocket\Engine\Optimization\LocalCSS;

use NoobRocket\Engine\Preload\Controller\LoadInitialSitemap;
use NoobRocket\Engine\Preload\Controller\Queue;
use NoobRocket\Engine\Preload\Database\Queries\Cache;
use NoobRocket\Event_Management\Subscriber_Interface;

/** Site-local status and explicit administrator recovery actions. */
class Diagnostics implements Subscriber_Interface {
	/**
	 * Local CSS jobs and worker status.
	 *
	 * @var Jobs
	 */
	private $jobs;

	/**
	 * Native preload cache queries.
	 *
	 * @var Cache
	 */
	private $preload;

	/**
	 * Native sitemap discovery controller.
	 *
	 * @var LoadInitialSitemap
	 */
	private $sitemap;

	/**
	 * Native preload queue.
	 *
	 * @var Queue
	 */
	private $queue;

	public function __construct( Jobs $jobs, Cache $preload, LoadInitialSitemap $sitemap, Queue $queue ) {
		$this->jobs    = $jobs;
		$this->preload = $preload;
		$this->sitemap = $sitemap;
		$this->queue   = $queue;
	}

	public static function get_subscribed_events(): array {
		return [
			'noobrocket_dashboard_heading_actions'              => 'render_regenerate_button',
			'noobrocket_before_dashboard_content'                => [ 'render', 5 ],
			'noobrocket_settings_page_footer'                    => 'render_action_form',
			'admin_post_noobrocket_diagnostics'                  => 'handle',
			'noobrocket_after_clean_domain'                       => 'invalidate_probe',
			'after_noobrocket_clean_home'                         => 'invalidate_probe',
			'noobrocket_preload_cache_pending_jobs_cron_rows_count' => 'limit_preload_during_css',
		];
	}

	public function render(): void {
		if ( ! current_user_can( 'noobrocket_manage_options' ) ) {
			return;
		}

		$data                    = $this->jobs->diagnostics();
		$data['preload_counts']  = $this->preload->get_status_counts();
		$data['preload_failures'] = $this->preload->get_failure_reason_counts( $data['preload_counts']['failed'] );
		$data['preload_enabled'] = (bool) get_noobrocket_option( 'manual_preload', 0 );
		$data['preload_paused']  = (bool) get_option( 'noobrocket_preload_paused', false );
		$data['probe']           = get_transient( 'noobrocket_cache_delivery_probe' );
		$notice                  = get_transient( 'noobrocket_diagnostics_notice' );
		$data['notice']          = self::notice_data( $notice );
		delete_transient( 'noobrocket_diagnostics_notice' );
		require NOOBROCKET_PATH . 'views/settings/partials/local-css-diagnostics.php';
	}

	/** Render the existing reset beside the dashboard's other cache action. */
	public function render_regenerate_button(): void {
		if ( ! current_user_can( 'noobrocket_manage_options' )
			|| ! in_array( get_noobrocket_option( 'local_css_mode', 'off' ), [ 'critical', 'used' ], true )
			|| ! $this->jobs->is_available()
		) {
			return;
		}

		printf(
			'<button type="submit" class="noobrocket-button" form="noobrocket-diagnostics-actions" name="operation" value="regenerate" aria-describedby="noobrocket-regenerate-scope" title="%s">%s</button>',
			esc_attr__( 'Regeneration clears CSS results and page cache. Public visits or preloading queue fresh CSS; original styles remain available.', 'noobrocket' ),
			esc_html__( 'Regenerate CSS and page cache', 'noobrocket' )
		);
	}

	// The dashboard lives inside options.php's form. Buttons target this separate form.
	public function render_action_form(): void {
		if ( ! current_user_can( 'noobrocket_manage_options' ) ) {
			return;
		}

		echo '<form id="noobrocket-diagnostics-actions" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" method="post">';
		echo '<input type="hidden" name="action" value="noobrocket_diagnostics">';
		wp_nonce_field( 'noobrocket_diagnostics', '_noobrocket_diagnostics_nonce' );
		echo '</form>';
	}

	public function handle(): void {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Strict equality validates the server method without changing the POST-only boundary.
			|| ! current_user_can( 'noobrocket_manage_options' )
		) {
			wp_die( esc_html__( 'You cannot manage Cache-Cache diagnostics.', 'noobrocket' ), '', [ 'response' => 403 ] );
		}

		check_admin_referer( 'noobrocket_diagnostics', '_noobrocket_diagnostics_nonce' );
		$operation = isset( $_POST['operation'] ) && is_string( $_POST['operation'] )
			? sanitize_key( wp_unslash( $_POST['operation'] ) )
			: '';
		$notice    = [ 'operation' => $operation ];

		switch ( $operation ) {
			case 'probe':
				$result = $this->probe_cache();
				set_transient( 'noobrocket_cache_delivery_probe', $result, DAY_IN_SECONDS );
				$notice = $result;
				break;
			case 'retry':
				$notice['count'] = $this->jobs->retry_failed();
				break;
			case 'regenerate':
				$this->jobs->invalidate_all();
				noobrocket_clean_domain();
				break;
			case 'pause_css':
				update_option( 'noobrocket_local_css_paused', true, false );
				break;
			case 'resume_css':
				delete_option( 'noobrocket_local_css_paused' );
				break;
			case 'pause_preload':
				update_option( 'noobrocket_preload_paused', true, false );
				$this->sitemap->cancel_preload();
				break;
			case 'resume_preload':
				delete_option( 'noobrocket_preload_paused' );
				if ( get_noobrocket_option( 'manual_preload', 0 ) ) {
					$this->queue->add_job_preload_job_load_initial_sitemap_async();
				}

				break;
			default:
				wp_die( esc_html__( 'Unknown Cache-Cache diagnostics action.', 'noobrocket' ), '', [ 'response' => 400 ] );
		}

		set_transient( 'noobrocket_diagnostics_notice', $notice, MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'options-general.php?page=noobrocket' ) . '#dashboard' );
		exit;
	}

	/**
	 * Discard historical delivery evidence after a domain or homepage purge.
	 */
	public function invalidate_probe(): void {
		delete_transient( 'noobrocket_cache_delivery_probe' );
	}

	/**
	 * Inspect the first anonymous homepage response without a second warming request.
	 */
	public function probe_cache(): array {
		$result = [
			'checked_at' => time(),
			'hit'        => false,
			'status'     => 0,
			'code'       => 'transport_error'
		];
		$args   = [
			'timeout'             => 8,
			'redirection'        => 0,
			'cookies'            => [],
			'limit_response_size' => 1024,
			'user-agent'         => 'NoobRocket/Diagnostics'
		];

		$response = wp_safe_remote_get( home_url( '/' ), $args );
		if ( is_wp_error( $response ) ) {
			return $result;
		}

		$result['status'] = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $result['status'] ) {
			$result['code'] = 'http_error';
			return $result;
		}

		$header            = (string) wp_remote_retrieve_header( $response, 'cache-status' );
		$result['hit']     = (bool) preg_match( '/\bNoobRocket\s*;\s*hit\b/i', $header );
		$result['code']    = $result['hit'] ? 'cache_hit' : 'header_missing';
		return $result;
	}

	/** One code owns the message and severity, including older stored probe records. */
	private static function probe_code( array $result ): string {
		$code = $result['code'] ?? '';
		if ( ! in_array( $code, [ 'transport_error', 'http_error', 'cache_hit', 'header_missing' ], true ) ) {
			$status = (int) ( $result['status'] ?? 0 );
			$code   = ! empty( $result['hit'] ) ? 'cache_hit' : ( 0 === $status ? 'transport_error' : ( 200 === $status ? 'header_missing' : 'http_error' ) );
		}
		return $code;
	}

	public static function probe_severity( array $result ): string {
		return [ 'cache_hit' => 'success', 'header_missing' => 'warning', 'transport_error' => 'error', 'http_error' => 'error' ][ self::probe_code( $result ) ];
	}

	/** Translate recorded evidence in the viewing administrator's locale, including old probe records. */
	public static function probe_message( array $result ): string {
		$code = self::probe_code( $result );

		switch ( $code ) {
			case 'transport_error':
				return __( 'The server could not reach your homepage. Check server connectivity and HTTPS, then run the check again.', 'noobrocket' );
			case 'http_error':
				/* translators: %d: HTTP response status. */
				return sprintf( __( 'Your homepage returned HTTP %d. Check that the page opens without a redirect or error, then try again.', 'noobrocket' ), (int) ( $result['status'] ?? 0 ) );
			case 'cache_hit':
				return __( 'Your homepage was served from the Cache-Cache cache to a logged-out request.', 'noobrocket' );
			default:
				return __( 'Your homepage responded, but its headers did not confirm a Cache-Cache cache hit. Nginx or a proxy may serve cached pages without this header.', 'noobrocket' );
		}
	}

	/** Normalize one-time action feedback without guessing severity from translated prose. */
	public static function notice_data( $notice ): array {
		// Earlier non-probe records were strings and represented successful actions only.
		if ( is_string( $notice ) && '' !== $notice ) {
			return [ 'type' => 'success', 'message' => $notice ];
		}
		if ( ! is_array( $notice ) || ! $notice ) {
			return [];
		}
		if ( ! isset( $notice['operation'] ) ) {
			return [ 'type' => self::probe_severity( $notice ), 'message' => self::probe_message( $notice ) ];
		}
		if ( 'retry' === $notice['operation'] ) {
			/* translators: %d: number of failed CSS jobs queued again. */
			return [ 'type' => 'success', 'message' => sprintf( __( '%d failed CSS jobs queued for retry.', 'noobrocket' ), max( 0, (int) ( $notice['count'] ?? 0 ) ) ) ];
		}
		$messages = [
			'regenerate'     => __( 'CSS results and page cache cleared. Public visits or preloading will enqueue fresh CSS jobs.', 'noobrocket' ),
			'pause_css'      => __( 'CSS generation paused. The current job may finish.', 'noobrocket' ),
			'resume_css'     => __( 'CSS generation resumed. The server worker can claim queued jobs.', 'noobrocket' ),
			'pause_preload'  => __( 'Preloading paused. Pending URLs are preserved; requests already running may finish.', 'noobrocket' ),
			'resume_preload' => __( 'Preloading resumed. The existing queue and sitemap discovery continue when Preload Cache is enabled.', 'noobrocket' ),
		];
		return isset( $messages[ $notice['operation'] ] ) ? [ 'type' => 'success', 'message' => $messages[ $notice['operation'] ] ] : [];
	}

	/**
	 * Reuse the adaptive preload batch; leave configured lower bounds intact.
	 */
	public function limit_preload_during_css( $maximum ): int {
		$worker = get_option( 'noobrocket_local_css_worker', [] );
		if ( is_array( $worker )
			&& 'running' === ( $worker['state'] ?? '' )
			&& (int) ( $worker['last_seen'] ?? 0 ) >= time() - 300
		) {
			return min( (int) $maximum, 5 );
		}

		return (int) $maximum;
	}

	/**
	 * Keep the failure useful without echoing worker command lines or credentials.
	 */
	public static function error_message( string $error ): string {
		$error = strtok( wp_strip_all_tags( $error ), "\r\n" ) ?: '';
		$error = preg_replace_callback(
			'#https?://[^\s<>"\']+#i',
			static function ( $match ) {
				$url = wp_parse_url( $match[0] );
				return isset( $url['scheme'], $url['host'] )
					? $url['scheme'] . '://' . $url['host'] . ( $url['path'] ?? '' )
					: '[URL]';
			},
			$error
		);
		$error = preg_replace( '#[A-Z]:[\\\\/][^\s,;)]*#i', '[server path]', $error );
		$error = preg_replace( '#(?<![a-zA-Z0-9:/])/(?:[^\s/]+/)+[^\s,;)]*#', '[server path]', $error );
		$error = preg_replace( '/\b(?:password|token|secret|api[_-]?key|consumer[_-]?key)\s*[:=]\s*[^\s,;]+/i', '[credential]', $error );
		return substr( $error, 0, 500 );
	}
}
