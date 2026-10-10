<?php

namespace NoobRocket\Engine\Preload\Controller;

use ActionScheduler_Store;
use NoobRocket\Engine\Common\Queue\AbstractASQueue;

class Queue extends AbstractASQueue {
	/**
	 * Group from the queue.
	 *
	 * @var string
	 */
	protected $group = 'noobrocket-preload';


	/**
	 * Add Async load initial sitemap job.
	 *
	 * @return int
	 */
	public function add_job_preload_job_load_initial_sitemap_async() {
		return $this->add_async( 'noobrocket_preload_job_load_initial_sitemap' );
	}

	/**
	 * Add Async parse sitemap job with url.
	 *
	 * @param string $sitemap_url sitemap url.
	 *
	 * @return int
	 */
	public function add_job_preload_job_parse_sitemap_async( string $sitemap_url ) {
		return $this->add_async(
			'noobrocket_preload_job_parse_sitemap',
			[
				$sitemap_url,
			]
		);
	}

	/**
	 * Add Async preload url job with url.
	 *
	 * @param string $url url to preload.
	 *
	 * @return int
	 */
	public function add_job_preload_job_preload_url_async( string $url ) {
		if ( $this->is_scheduled( 'noobrocket_preload_job_preload_url', [ $url ] ) ) {
			return 0;
		}
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			return as_enqueue_async_action( 'noobrocket_preload_job_preload_url', [ $url ], $this->group, true );
		}
		return $this->add_async(
			'noobrocket_preload_job_preload_url',
			[
				$url,
			]
		);
	}

	/**
	 * Add a job that check if the preload is finished.
	 *
	 * @return int
	 */
	public function add_job_preload_job_check_finished_async() {

		if ( $this->job_preload_job_check_finished_async_exists() ) {
			return 0;
		}

		return $this->schedule_single( time() + MINUTE_IN_SECONDS, 'noobrocket_preload_job_check_finished', [ time() ] );
	}

	/**
	 * Check if a task job_preload_job_check_finished_async_exists already exists.
	 *
	 * @return bool
	 */
	public function job_preload_job_check_finished_async_exists() {
		if ( ! did_action( 'init' ) || doing_action( 'init' ) ) {
			return true;
		}

		$row_found = $this->search(
			[
				'hook'   => 'noobrocket_preload_job_check_finished',
				'status' => ActionScheduler_Store::STATUS_PENDING,
			],
			'ids'
		);

		return count( $row_found ) > 0;
	}

	/**
	 * Check if some task is remaining.
	 *
	 * @return bool
	 */
	public function has_remaining_tasks() {
		foreach ( [ 'noobrocket_preload_job_load_initial_sitemap', 'noobrocket_preload_job_parse_sitemap', 'noobrocket_preload_job_preload_url' ] as $hook ) {
			if ( $this->is_scheduled( $hook ) ) {
				return true;
			}
		}
		return false;
	}

	/** Count active URL work; null means the scheduler cannot safely admit more. */
	public function get_active_preload_count(): ?int {
		if ( ! \ActionScheduler::is_initialized( __METHOD__ ) ) {
			return null;
		}
		$count = 0;
		foreach ( [ ActionScheduler_Store::STATUS_PENDING, ActionScheduler_Store::STATUS_RUNNING ] as $status ) {
			$status_count = \ActionScheduler::store()->query_actions(
				[ 'hook' => 'noobrocket_preload_job_preload_url', 'group' => $this->group, 'status' => $status ],
				'count'
			);
			if ( ! is_numeric( $status_count ) ) {
				return null;
			}
			$count += (int) $status_count;
		}
		return $count;
	}

	/**
	 * Cancel pending jobs.
	 *
	 * @return void
	 */
	public function cancel_pending_jobs() {
		$this->cancel_all( '' );
	}

	/**
	 * Return pending actions inside AS scheduler queue.
	 *
	 * @return array
	 */
	public function get_pending_preload_actions(): array {
		return $this->search(
			[
				'hook'     => 'noobrocket_preload_job_preload_url',
				'group'    => $this->group,
				'status'   => ActionScheduler_Store::STATUS_PENDING,
				'per_page' => -1,
			]
		);
	}
}
