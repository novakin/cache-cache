<?php

namespace NoobRocket\ThirdParty\Plugins\Optimization;

use NoobRocket\Event_Management\Subscriber_Interface;
use NoobRocket\ThirdParty\PluginCompatibilityInterface;

class RocketLazyLoad implements Subscriber_Interface, PluginCompatibilityInterface {
	/**
	 * Whether the target third-party plugin is active.
	 *
	 * @return bool
	 */
	public static function is_activated(): bool {
		return noobrocket_has_constant( 'ROCKET_LL_VERSION' );
	}

	/**
	 * Return an array of events that this subscriber wants to listen to.
	 *
	 * @return array
	 */
	public static function get_subscribed_events(): array {
		return [
			'noobrocket_delay_js_exclusions' => 'exclude_noobrocket_lazyload_script',
		];
	}

	/**
	 * Excludes rocket lazyload script from delay js.
	 *
	 * @param array $excluded List of excluded files.
	 *
	 * @return array
	 */
	public function exclude_noobrocket_lazyload_script( $excluded ): array {
		$excluded[] = 'rocket-lazy-load/assets/js/\d\d\.\d/lazyload.min.js';
		return $excluded;
	}
}
