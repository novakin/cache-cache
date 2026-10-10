<?php
declare(strict_types=1);

namespace NoobRocket\ThirdParty\Plugins;

use NoobRocket\Event_Management\Subscriber_Interface;
use NoobRocket\ThirdParty\PluginCompatibilityInterface;

class PWA implements Subscriber_Interface, PluginCompatibilityInterface {
	/**
	 * Whether the PWA plugin is active.
	 *
	 * @return bool
	 */
	public static function is_activated(): bool {
		return function_exists( 'wp_get_service_worker_url' );
	}

	/**
	 * Subscribed events.
	 */
	public static function get_subscribed_events() {
		return [
			'noobrocket_cache_reject_uri' => 'exclude_service_worker',
		];
	}

	/**
	 * Excludes the PWA service worker URL
	 *
	 * @param array $excluded Array of excluded URLs.
	 *
	 * @return array
	 */
	public function exclude_service_worker( $excluded ): array {
		if ( ! function_exists( 'wp_get_service_worker_url' ) ) {
			return $excluded;
		}

		$excluded[] = '/wp.serviceworker/?';

		return $excluded;
	}
}
