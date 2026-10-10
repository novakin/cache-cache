<?php
declare(strict_types=1);

namespace NoobRocket\Engine\Optimization\DynamicLists\DelayJSLists;

use NoobRocket\Engine\Optimization\DynamicLists\AbstractDataManager;

class DataManager extends AbstractDataManager {

	/**
	 * Get cache transient name.
	 *
	 * @return string
	 */
	protected function get_cache_transient_name() {
		return 'nbr_dynamic_lists_delayjs';
	}

	/**
	 * Get lists json filename.
	 *
	 * @return string
	 */
	protected function get_json_filename() {
		return 'dynamic-lists-delayjs';
	}
}
