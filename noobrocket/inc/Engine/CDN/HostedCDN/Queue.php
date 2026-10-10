<?php
declare(strict_types=1);

namespace NoobRocket\Engine\CDN\HostedCDN;

use NoobRocket\Engine\Common\Queue\AbstractASQueue;

/**
 * HostedCDN Queue
 *
 * Manages Action Scheduler jobs for HostedCDN workflow
 */
class Queue extends AbstractASQueue {

	/**
	 * Queue group for HostedCDN.
	 *
	 * @var string
	 */
	protected $group = 'noobrocket-cdn';

	/**
	 * Website creation status task hook.
	 *
	 * @var string
	 */
	private $create_status_job = 'noobrocket_cdnfree_website_create_status';

	/**
	 * Pro detection task hook.
	 *
	 * @var string
	 */
	private $pro_detect_job = 'noobrocket_cdn_auto_detect';

	/**
	 * Cancel create job.
	 */
	public function cancel_create_status_job(): void {
		if ( ! $this->is_scheduled( $this->create_status_job ) ) {
			return;
		}
		$this->cancel( $this->create_status_job );
	}

	/**
	 * Schedule reset task.
	 *
	 * @param string $task_id Task ID to check the status of HostedCDN free website creation.
	 *
	 * @return void
	 */
	public function schedule_create_status_job( string $task_id ): void {
		// Schedule weekly cleanup.
		$this->schedule_single(
			time() + 30, // After 30 seconds from now.
			$this->create_status_job,
			[
				'task_id' => $task_id,
			]
		);
	}

	/**
	 * Schedule the Pro subscription detection job.
	 *
	 * @param int $attempt Number of remaining detection attempts.
	 *
	 * @return void
	 */
	public function schedule_pro_detection_job( int $attempt = 2 ): void {
		// Avoid piling up duplicate pending actions across retries/re-triggers.
		$this->cancel_pro_detection_job();

		$this->schedule_single(
			time() + 30, // After 30 seconds from now.
			$this->pro_detect_job,
			[
				'attempt' => $attempt,
			]
		);
	}

	/**
	 * Cancel the Pro subscription detection job.
	 *
	 * @return void
	 */
	public function cancel_pro_detection_job(): void {
		// Match regardless of the 'attempt' arg the job was scheduled with.
		if ( ! $this->is_scheduled( $this->pro_detect_job, null ) ) {
			return;
		}
		$this->cancel_all( $this->pro_detect_job, null );
	}

	/**
	 * Cancel all scheduled tasks.
	 *
	 * @return void
	 */
	public function cancel_all_tasks() {
		$this->cancel_create_status_job();
		$this->cancel_pro_detection_job();
	}
}
