<?php
declare(strict_types=1);

namespace NoobRocket\Engine\Admin\PerformanceMonitoring\Context;

use NoobRocket\Engine\Common\Context\ContextInterface;

/**
 * Performance monitoring Saas Context.
 *
 * Provides context for Performance monitoring SaaS operations.
 */
class SaasContext implements ContextInterface {
	/**
	 * Check if Performance monitoring is enabled.
	 *
	 * @param array $data Context data.
	 * @return bool
	 */
	public function is_allowed( array $data = [] ): bool {

		// This filter is documented in inc/Engine/Admin/PerformanceMonitoring/Context/Context.php.
		$enabled = wpm_apply_filters_typed( 'boolean', 'noobrocket_noobrocket_insights_enabled', true );

		// Block for non-live installations.
		if ( $enabled && ! noobrocket_is_live_site() ) {
			return false;
		}

		return $enabled;
	}
}
