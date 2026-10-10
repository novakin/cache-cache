<?php
declare(strict_types=1);

namespace NoobRocket\Engine\Common\PerformanceHints\AJAX;

trait AJAXControllerTrait {
	/**
	 * Get status code and message to be saved into the database
	 *
	 * @param string $status Current status code from $_POST.
	 * @return array
	 */
	protected function get_status_code_message( string $status ): array {
		$status_code    = 'success' !== $status ? 'failed' : 'completed';
		$status_message = '';

		switch ( $status ) {
			case 'script_error':
				$status_message = esc_html__( 'Script error', 'noobrocket' );
				break;
			case 'timeout':
				$status_message = esc_html__( 'Script timeout', 'noobrocket' );
				break;
		}

		return [
			$status_code,
			$status_message,
		];
	}
}
