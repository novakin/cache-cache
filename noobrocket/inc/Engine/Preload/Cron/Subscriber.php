<?php

namespace NoobRocket\Engine\Preload\Cron;

use NoobRocket\Engine\Common\Queue\Cleaner;
use NoobRocket\Engine\Preload\Admin\Settings;
use NoobRocket\Engine\Preload\Controller\PreloadUrl;
use NoobRocket\Engine\Preload\Database\Queries\Cache;
use NoobRocket\Event_Management\Subscriber_Interface;

class Subscriber implements Subscriber_Interface {

	/**
	 * Preload settings.
	 *
	 * @var Settings
	 */
	protected $settings;

	/**
	 * Db query.
	 *
	 * @var Cache
	 */
	protected $query;

	/**
	 * Preload url controller.
	 *
	 * @var PreloadUrl
	 */
	protected $preload_controller;

	/**
	 * Creates an instance of the class.
	 *
	 * @param Settings   $settings Preload settings.
	 * @param Cache      $query Db query.
	 * @param PreloadUrl $preload_controller Preload url controller.
	 */
	public function __construct( Settings $settings, Cache $query, PreloadUrl $preload_controller ) {
		$this->settings           = $settings;
		$this->query              = $query;
		$this->preload_controller = $preload_controller;
	}

	/**
	 * Return an array of events that this subscriber listens to.
	 *
	 * @return array
	 */
	public static function get_subscribed_events() {
		return [
			'noobrocket_preload_clean_rows_time_event'  => 'remove_old_rows',
			'noobrocket_preload_process_pending'        => [
				[ 'process_pending_urls' ],
				[ 'clean_preload_jobs' ],
			],
			'noobrocket_preload_revert_old_failed_rows' => 'revert_old_failed_rows',
			'cron_schedules'                        => [
				[ 'add_interval' ],
				[ 'add_revert_old_failed_interval' ],
			],
			'init'                                  => [
				[ 'schedule_clean_not_commonly_used_rows' ],
				[ 'schedule_pending_jobs' ],
				[ 'schedule_revert_old_failed_rows' ],
			],
		];
	}

	/**
	 * Schedule clean from removing of old urls.
	 *
	 * @return void
	 */
	public function schedule_clean_not_commonly_used_rows() {
		/**
		 * Delay before the not accessed row is deleted.
		 *
		 * @param string $delay delay before the not accessed row is deleted.
		 */
		$delay = (string) apply_filters( 'noobrocket_preload_delay_delete_non_accessed', '1 month' );

		if ( '' === $delay || wp_next_scheduled( 'noobrocket_preload_clean_rows_time_event' ) ) {
			return;
		}

		wp_schedule_event( time() + 10 * MINUTE_IN_SECONDS, 'weekly', 'noobrocket_preload_clean_rows_time_event' );
	}

	/**
	 * Preload Url jobs.
	 *
	 * @return void
	 */
	public function process_pending_urls() {
		if ( ! $this->settings->is_enabled() ) {
			return;
		}

		$this->preload_controller->process_pending_jobs();
	}

	/**
	 * Clean Action Scheduler jobs for preload.
	 *
	 * @return void
	 */
	public function clean_preload_jobs() {
		$clean_batch_size = (int) apply_filters( 'noobrocket_action_scheduler_clean_batch_size', 100, 'noobrocket-preload' );
		$cleaner          = new Cleaner( null, $clean_batch_size, 'noobrocket-preload' );
		$cleaner->clean();
	}

	/**
	 * Add the interval for the cron.
	 *
	 * @param array $schedules Cron schedules.
	 * @return mixed
	 */
	public function add_interval( $schedules ) {
		if ( ! $this->settings->is_enabled() ) {
			return $schedules;
		}

		/**
		 * Filters the cron interval.
		 *
		 * @since 3.11
		 *
		 * @param int $interval Interval in seconds.
		 */
		$interval = apply_filters( 'noobrocket_preload_pending_jobs_cron_interval', 1 * noobrocket_get_constant( 'MINUTE_IN_SECONDS', 60 ) );

		$schedules['noobrocket_preload_process_pending'] = [
			'interval' => $interval,
			'display'  => esc_html__( 'Cache-Cache Preload pending jobs', 'noobrocket' ),
		];

		return $schedules;
	}

	/**
	 * Add the interval for the cron.
	 *
	 * @param array $schedules Cron schedules.
	 * @return mixed
	 */
	public function add_revert_old_failed_interval( $schedules ) {
		if ( ! $this->settings->is_enabled() ) {
			return $schedules;
		}

		/**
		 * Filters the cron interval.
		 *
		 * @since 3.11
		 *
		 * @param int $interval Interval in seconds.
		 */
		$interval = apply_filters( 'noobrocket_preload_revert_old_failed_rows_cron_interval', 12 * noobrocket_get_constant( 'HOUR_IN_SECONDS', 60 * 60 ) );

		$schedules['noobrocket_revert_old_failed_rows'] = [
			'interval' => $interval,
			'display'  => esc_html__( 'Cache-Cache Preload revert stuck failed jobs', 'noobrocket' ),
		];

		return $schedules;
	}

	/**
	 * Schedule pending preload urls.
	 *
	 * @return void
	 */
	public function schedule_pending_jobs() {

		if (
			! $this->settings->is_enabled()
			&&
			wp_next_scheduled( 'noobrocket_preload_process_pending' )
		) {
			wp_clear_scheduled_hook( 'noobrocket_preload_process_pending' );

			return;
		}

		if ( ! $this->settings->is_enabled() ) {
			return;
		}

		if ( wp_next_scheduled( 'noobrocket_preload_process_pending' ) ) {
			return;
		}

		wp_schedule_event( time() + MINUTE_IN_SECONDS, 'noobrocket_preload_process_pending', 'noobrocket_preload_process_pending' );
	}

	/**
	 * Schedule revert stuck failed row cron.
	 *
	 * @return void
	 */
	public function schedule_revert_old_failed_rows() {
		if (
			! $this->settings->is_enabled()
			&&
			wp_next_scheduled( 'noobrocket_preload_revert_old_failed_rows' )
		) {
			wp_clear_scheduled_hook( 'noobrocket_preload_revert_old_failed_rows' );

			return;
		}

		if ( ! $this->settings->is_enabled() ) {
			return;
		}

		if ( wp_next_scheduled( 'noobrocket_preload_revert_old_failed_rows' ) ) {
			return;
		}

		wp_schedule_event( time() + MINUTE_IN_SECONDS, 'noobrocket_revert_old_failed_rows', 'noobrocket_preload_revert_old_failed_rows' );
	}

	/**
	 * Remove old urls.
	 *
	 * @return void
	 */
	public function remove_old_rows() {
		/**
		 * Delay before the not accessed row is deleted.
		 *
		 * @param string $delay delay before the not accessed row is deleted.
		 */
		$delay = (string) apply_filters( 'noobrocket_preload_delay_delete_non_accessed', '1 month' );

		$parts = explode( ' ', $delay );

		if ( '' === $delay || '0' === $delay ) {
			return;
		}

		$value = 1;
		$unit  = 'month';

		if ( count( $parts ) === 2 && $parts[0] >= 0 ) {
			$value = $parts[0];
			$unit  = $parts[1];
		}

		$this->query->remove_all_not_accessed_rows( $value, $unit );
	}

	/**
	 * Remove old failed urls.
	 *
	 * @return void
	 */
	public function revert_old_failed_rows() {
		$this->query->revert_old_failed();
	}
}
