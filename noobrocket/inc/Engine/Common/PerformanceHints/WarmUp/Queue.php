<?php
declare(strict_types=1);

namespace NoobRocket\Engine\Common\PerformanceHints\WarmUp;

use NoobRocket\Engine\Common\Queue\AbstractASQueue;

class Queue extends AbstractASQueue {
	/**
	 * PerformanceHints queue group
	 *
	 * @var string
	 */
	protected $group = 'noobrocket-performance-hints-warmup';

	/**
	 * Add an async job to warm up home links
	 *
	 * @return int
	 */
	public function add_job_warmup(): int {
		return $this->add_async( 'noobrocket_job_warmup' );
	}

	/**
	 * Add an async job to send URL to SaaS for warmup
	 *
	 * @param string $url URL to warm up.
	 *
	 * @return int
	 */
	public function add_job_warmup_url( string $url ): int {
		return $this->add_async( 'noobrocket_job_warmup_url', [ $url ] );
	}
}
