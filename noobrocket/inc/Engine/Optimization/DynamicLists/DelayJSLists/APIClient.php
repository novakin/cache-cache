<?php
declare(strict_types=1);

namespace NoobRocket\Engine\Optimization\DynamicLists\DelayJSLists;

use NoobRocket\Engine\Optimization\DynamicLists\AbstractAPIClient;

class APIClient extends AbstractAPIClient {

	/**
	 * Specify API endpoint path.
	 *
	 * @return string
	 */
	protected function get_api_path() {
		return 'delay-js-exclusions/list';
	}
}
