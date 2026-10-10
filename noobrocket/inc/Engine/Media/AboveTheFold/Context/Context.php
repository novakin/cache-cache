<?php
declare(strict_types=1);

namespace NoobRocket\Engine\Media\AboveTheFold\Context;

use NoobRocket\Engine\Common\Context\ContextInterface;

class Context implements ContextInterface {
	/**
	 * Determine if the action is allowed.
	 *
	 * @param array $data Data to pass to the context.
	 * @return bool
	 */
	public function is_allowed( array $data = [] ): bool {

		if ( get_option( 'noobrocket_no_licence' ) ) {
			return false;
		}

		/**
		 * Filters to manage above the fold optimization
		 *
		 * @param bool $allow True to allow, false otherwise.
		 */
		return wpm_apply_filters_typed( 'boolean', 'noobrocket_above_the_fold_optimization', true );
	}
}
