<?php

namespace NoobRocket\ThirdParty\Plugins;

use NoobRocket\Event_Management\Subscriber_Interface;
use NoobRocket\ThirdParty\PluginCompatibilityInterface;

/**
 * Subscriber for compatibility with the Events Calendar.
 */
class TheEventsCalendar implements Subscriber_Interface, PluginCompatibilityInterface {

	/**
	 * Whether the target third-party plugin is active.
	 *
	 * @return bool
	 */
	public static function is_activated(): bool {
		return noobrocket_has_constant( 'TRIBE_EVENTS_FILE' );
	}

	/**
	 * Subscribed events for The Events Calendar.
	 */
	public static function get_subscribed_events() {
		return [
			'noobrocket_preload_exclude_urls' => 'exclude_from_preload_calendars',
		];
	}

	/**
	 * Exclude calendars from the preload.
	 *
	 * @param array $excluded excluded urls.
	 * @return array
	 */
	public function exclude_from_preload_calendars( $excluded ) {

		if ( ! function_exists( 'tribe_get_option' ) ) {
			return $excluded;
		}

		$uri = tribe_get_option( 'eventsSlug', 'event' );

		$excluded[] = "/$uri/20(.*)";

		return $excluded;
	}
}
