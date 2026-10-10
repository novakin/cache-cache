<?php
declare( strict_types=1 );

namespace NoobRocket\Engine\Common\PerformanceHints;

use NoobRocket\Engine\Common\Context\ContextInterface;

interface ActivationFactoryInterface {
	/**
	 * Provides a Context interface
	 *
	 * @return ContextInterface
	 */
	public function get_context(): ContextInterface;
}
