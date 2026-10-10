<?php
namespace NoobRocket\Engine\Admin\Database;

use NoobRocket\Event_Management\Subscriber_Interface;
use NoobRocket\Admin\Options_Data;

/**
 * Subscriber for the database optimization
 */
class Subscriber implements Subscriber_Interface {

	/**
	 * Optimization process instance.
	 *
	 * @since  3.4
	 *
	 * @var Optimization
	 */
	private $optimize;

	/**
	 * NoobRocket Options instance.
	 *
	 * @since  3.4
	 *
	 * @var Options_Data
	 */
	private $options;

	/**
	 * Constructor
	 *
	 * @param Optimization $optimize Optimize instance.
	 * @param Options_Data $options  NoobRocket options.
	 */
	public function __construct( Optimization $optimize, Options_Data $options ) {
		$this->optimize = $optimize;
		$this->options  = $options;
	}

	/**
	 * Return an array of events that this subscriber wants to listen to.
	 *
	 * @since 3.3
	 *
	 * @return array
	 */
	public static function get_subscribed_events() {
		return [
			'cron_schedules'                          => 'add_cron_schedule',
			'init'                                    => 'database_optimization_scheduled',
			'noobrocket_database_optimization_time_event' => 'cron_optimize',
			'pre_update_option_' . NOOBROCKET_SLUG     => 'save_optimize',
			'admin_post_noobrocket_database_cleanup'   => 'handle_cleanup',
			'noobrocket_deactivation'                  => [ 'deactivate', 10, 2 ],
			'admin_notices'                           => [
				[ 'notice_process_running' ],
				[ 'notice_process_complete' ],
			],
		];
	}

	/**
	 * Add a new interval for the cron job.
	 * This adds a weekly/monthly interval for database optimization.
	 *
	 * @since 3.4
	 *
	 * @param  array $schedules An array of intervals used by cron jobs.
	 * @return array            Updated array of intervals.
	 */
	public function add_cron_schedule( $schedules ) {
		if ( ! $this->options->get( 'schedule_automatic_cleanup', false ) ) {
			return $schedules;
		}

		switch ( $this->options->get( 'automatic_cleanup_frequency', 'weekly' ) ) {
			case 'weekly':
				$schedules['weekly'] = [
					'interval' => 604800,
					'display'  => __( 'weekly', 'noobrocket' ),
				];
				break;
			case 'monthly':
				$schedules['monthly'] = [
					'interval' => 2592000,
					'display'  => __( 'monthly', 'noobrocket' ),
				];
				break;
		}

		return $schedules;
	}

	/**
	 * Plans database optimization cron
	 * If the task is not programmed, it is automatically triggered
	 *
	 * @since 2.8
	 *
	 * @see process_handler()
	 */
	public function database_optimization_scheduled() {
		if ( ! $this->options->get( 'schedule_automatic_cleanup', false ) ) {
			return;
		}

		if ( ! wp_next_scheduled( 'noobrocket_database_optimization_time_event' ) ) {
			$frequency = $this->options->get( 'automatic_cleanup_frequency', 'weekly' );
			$schedules = wp_get_schedules();
			$interval  = (int) ( $schedules[ $frequency ]['interval'] ?? 0 );
			if ( $interval > 0 ) {
				// Reactivation restores the schedule, not an immediate destructive run.
				wp_schedule_event( time() + $interval, $frequency, 'noobrocket_database_optimization_time_event' );
			}
		}
	}

	/**
	 * Database Optimization cron callback
	 *
	 * @since 3.0.4
	 */
	public function cron_optimize() {
		$items = array_filter( array_keys( $this->optimize->get_options() ), [ $this->options, 'get' ] );

		if ( empty( $items ) ) {
			return;
		}

		$this->optimize->process_handler( $items );
	}

	/**
	 * Strips the legacy cleanup intent without dispatching during validation.
	 *
	 * @since 2.8
	 *
	 * @see process_handler()
	 *
	 * @param array $value The new, unserialized option value.
	 * @return array
	 */
	public function save_optimize( $value ) {
		// Compatibility only: validation/saving must never dispatch deletion work.
		if ( is_array( $value ) ) {
			unset( $value['submit_optimize'] );
		}
		return $value;
	}

	/** An immediate cleanup owns its reviewed scope and does not save settings. */
	public function handle_cleanup(): void {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! current_user_can( 'noobrocket_manage_options' ) ) {
			wp_die( esc_html__( 'You cannot run database cleanup.', 'noobrocket' ), '', [ 'response' => 403 ] );
		}
		check_admin_referer( 'noobrocket_database_cleanup' );
		$requested = isset( $_POST['noobrocket_cleanup'] ) ? wp_unslash( $_POST['noobrocket_cleanup'] ) : [];
		$allowed = array_keys( $this->optimize->get_options() );
		$invalid = ! is_array( $requested ) || ! $requested || count( $requested ) > count( $allowed );
		if ( ! $invalid ) {
			foreach ( $requested as $item ) {
				if ( ! is_string( $item ) || ! in_array( $item, $allowed, true ) ) {
					$invalid = true;
					break;
				}
			}
		}
		if ( '1' !== ( $_POST['confirmed'] ?? '' ) || $invalid ) {
			wp_die( esc_html__( 'Review and confirm the selected cleanup categories first.', 'noobrocket' ), '', [ 'response' => 400 ] );
		}
		if ( ! $this->optimize->process_handler( array_values( array_unique( $requested ) ) ) ) {
			wp_die( esc_html__( 'A database cleanup is already queued or running.', 'noobrocket' ), '', [ 'response' => 409 ] );
		}
		wp_safe_redirect( admin_url( 'options-general.php?page=noobrocket' ) . '#database' );
		exit;
	}

	/** Stop the affected sites' queues even though this subscriber was built on one blog. */
	public function deactivate( $sites_number = 0, bool $network_wide = false ): void {
		\NoobRocket\Engine\Deactivation\Deactivation::for_each_deactivated_site(
			function () {
				wp_clear_scheduled_hook( 'noobrocket_database_optimization_time_event' );
				$this->optimize->deactivate();
			},
			$network_wide
		);
		if ( is_multisite() && $network_wide ) {
			// The old network queue has no blog provenance and cannot safely resume.
			Optimization::cancel_site_queue( true );
		}
	}

	/**
	 * This notice is displayed after launching the database optimization process
	 *
	 * @since 2.11
	 */
	public function notice_process_running() {
		$screen = get_current_screen();

		if ( ! current_user_can( 'noobrocket_manage_options' ) ) {
			return;
		}

		if ( 'settings_page_noobrocket' !== $screen->id ) {
			return;
		}

		$notice = get_transient( 'noobrocket_database_optimization_process' );

		if ( ! $notice ) {
			return;
		}

		\noobrocket_notice_html(
			[
				'status'  => 'info',
				'message' => esc_html__( 'Database optimization process is running', 'noobrocket' ),
				'persistent' => true,
			]
		);
	}

	/**
	 * This notice is displayed when the database optimization process is complete
	 *
	 * @since 2.11
	 */
	public function notice_process_complete() {
		$screen = get_current_screen();

		if ( ! current_user_can( 'noobrocket_manage_options' ) ) {
			return;
		}

		if ( 'settings_page_noobrocket' !== $screen->id ) {
			return;
		}

		$optimized = get_transient( 'noobrocket_database_optimization_process_complete' );

		if ( false === $optimized ) {
			return;
		}

		$db_options = $this->optimize->get_options();
		delete_transient( 'noobrocket_database_optimization_process_complete' );
		$failed = isset( $optimized['status'] ) && 'complete' !== $optimized['status'];
		$cancelled = isset( $optimized['status'] ) && 'cancelled' === $optimized['status'];
		$optimized = $optimized['counts'] ?? $optimized;
		$optimized = array_filter( $optimized );

		$message = esc_html__( 'Database optimization process is complete. Everything was already optimized!', 'noobrocket' );

		if ( ! empty( $optimized ) ) {
			$message = esc_html__( 'Database optimization process is complete. List of optimized items below:', 'noobrocket' );
		}
		if ( $failed ) {
			$message = $cancelled
				? esc_html__( 'Database cleanup was cancelled. Completed deletions cannot be undone.', 'noobrocket' )
				: esc_html__( 'Database cleanup finished with failures. Review the selected categories before retrying.', 'noobrocket' );
		}

		if ( ! empty( $optimized ) ) {
			$message .= '<ul>';
			foreach ( $optimized as $key => $number ) {
				$message .= '<li>' .
					/* translators: %1$d = number of items optimized, %2$s = type of optimization */
					sprintf( esc_html__( '%1$d %2$s optimized.', 'noobrocket' ), $number, $db_options[ $key ] )
				. '</li>';
			}
			$message .= '</ul>';
		}

		\noobrocket_notice_html(
			[
				'status' => $failed ? 'error' : 'success',
				'message' => $message,
				'persistent' => $failed,
			]
		);
	}
}
