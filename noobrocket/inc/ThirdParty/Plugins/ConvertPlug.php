<?php

namespace NoobRocket\ThirdParty\Plugins;

use NoobRocket\Event_Management\Subscriber_Interface;
use NoobRocket\ThirdParty\PluginCompatibilityInterface;

/**
 * Subscriber for compatibility with ConvertPlug.
 */
class ConvertPlug implements Subscriber_Interface, PluginCompatibilityInterface {

	/**
	 * Whether the target third-party plugin is active.
	 *
	 * @return bool
	 */
	public static function is_activated(): bool {
		return noobrocket_has_constant( 'CP_VERSION' );
	}

	/**
	 * Subscriber for compatibility with ConvertPlug.
	 *
	 * @return array
	 */
	public static function get_subscribed_events() {
		$events = [];

		$events['noobrocket_rucss_inline_atts_exclusions'] = 'excluded_from_rucss';

		return $events;
	}

	/**
	 * Exclude css from RUCSS.
	 *
	 * @param array $excluded excluded css.
	 * @return array
	 */
	public function excluded_from_rucss( $excluded ) {
		$excluded[] = 'cp-form-css';
		return $excluded;
	}
}
