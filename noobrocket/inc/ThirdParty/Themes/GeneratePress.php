<?php
declare(strict_types=1);

namespace NoobRocket\ThirdParty\Themes;

use NoobRocket\Event_Management\Subscriber_Interface;

class GeneratePress implements Subscriber_Interface {
	/**
	 * Return an array of events that this subscriber wants to listen to.
	 *
	 * @return array
	 */
	public static function get_subscribed_events() {
		return [
			'generate_footer_class' => 'inject_exclusions_class',
		];
	}

	/**
	 * Injects exclusion class into GeneratePress footer classes.
	 *
	 * @since 3.20.3
	 *
	 * @param array $classes Array of footer classes.
	 *
	 * @return array The modified array of footer classes.
	 */
	public function inject_exclusions_class( array $classes ): array {
		$classes[] = 'no-noobrocket-lazyrender';

		return $classes;
	}
}
