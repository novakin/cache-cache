<?php
declare(strict_types=1);

namespace NoobRocket\Engine\Optimization\DynamicLists\IncompatiblePluginsLists;

use NoobRocket\Engine\Optimization\DynamicLists\AbstractAPIClient;

class APIClient extends AbstractAPIClient {

	/**
	 * Specify API endpoint path.
	 *
	 * @return string
	 */
	protected function get_api_path() {
		return 'incompatible-plugins/list';
	}
}
