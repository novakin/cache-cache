<?php

namespace NoobRocket\ThirdParty\Plugins;

use NoobRocket\Event_Management\Subscriber_Interface;
use NoobRocket\ThirdParty\PluginCompatibilityInterface;

/**
 * Subscriber for compatibility with Unlimited Elements.
 */
class UnlimitedElements implements Subscriber_Interface, PluginCompatibilityInterface {


	/**
	 * Whether the target third-party plugin is active.
	 *
	 * @return bool
	 */
	public static function is_activated(): bool {
		return noobrocket_has_constant( 'UNLIMITED_ELEMENTS_INC' );
	}

	/**
	 * Subscriber for Unlimited Elements.
	 *
	 * @return array
	 */
	public static function get_subscribed_events() {
		return [ 'noobrocket_rucss_inline_content_exclusions' => 'exclude_inline_from_rucss' ];
	}

	/**
	 * Exclude inline style from RUCSS.
	 *
	 * @param array $excluded excluded css.
	 * @return array
	 */
	public function exclude_inline_from_rucss( $excluded ) {
		$excluded[] = '#uc_';

		return $excluded;
	}
}
