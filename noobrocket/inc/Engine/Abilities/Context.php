<?php
declare(strict_types=1);

namespace NoobRocket\Engine\Abilities;

class Context {
	/**
	 * Determines whether NoobRocket abilities are enabled for MCP.
	 *
	 * @return bool True when NoobRocket abilities should be registered; false otherwise.
	 */
	public function is_enabled(): bool {
		/**
		 * Filters whether NoobRocket abilities are enabled for MCP.
		 *
		 * @param bool $enabled Whether abilities are enabled. Default true.
		 * @return bool
		 */
		return wpm_apply_filters_typed( 'boolean', 'noobrocket_enable_abilities', true );
	}
}
