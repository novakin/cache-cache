<?php
declare( strict_types=1 );

namespace NoobRocket\Engine\Common\PerformanceHints\AJAX;

interface ControllerInterface {
	/**
	 * Initiates the addition of data.
	 *
	 * @return array
	 */
	public function add_data(): array;

	/**
	 * Initiates the checking of data.
	 *
	 * @return array
	 */
	public function check_data(): array;
}
