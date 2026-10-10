<?php
declare(strict_types=1);

namespace NoobRocket\Engine\Common\PerformanceHints\WarmUp;

use NoobRocket\Engine\Common\Utils;
use NoobRocket\Engine\Optimization\RUCSS\APIHandler\APIClient as BaseAPIClient;

class APIClient extends BaseAPIClient {

	/**
	 * Send the link to SaaS.
	 *
	 * @param string $url Url to be sent.
	 * @param string $device Device type.
	 *
	 * @return array
	 */
	public function add_to_performance_hints_queue( string $url, $device = 'desktop' ): array {
		$is_home = Utils::is_home( $url );

		$config = [
			'optimization_list' => [
				'performance_hints', // performance_hints represent atf,lrc.
			],
			'is_home'           => $is_home,
			'is_mobile'         => 'mobile' === $device,
		];

		return $this->add_to_queue( $url, $config );
	}
}
