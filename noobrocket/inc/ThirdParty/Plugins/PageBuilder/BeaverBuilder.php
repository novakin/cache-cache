<?php
namespace NoobRocket\ThirdParty\Plugins\PageBuilder;

use NoobRocket\Event_Management\Subscriber_Interface;
use NoobRocket\ThirdParty\PluginCompatibilityInterface;

class BeaverBuilder implements Subscriber_Interface, PluginCompatibilityInterface {
	/**
	 * Whether the target third-party plugin is active.
	 *
	 * @return bool
	 */
	public static function is_activated(): bool {
		return noobrocket_has_constant( 'FL_BUILDER_VERSION' );
	}

	/**
	 * Events this subscriber listens to
	 *
	 * @inheritDoc
	 */
	public static function get_subscribed_events() {
		return [
			'fl_builder_before_save_layout' => 'purge_cache',
			'fl_builder_cache_cleared'      => 'purge_cache',
		];
	}

	/**
	 * Purge the cache when the beaver builder layout is updated to update the minified files content & URL
	 *
	 * Previously noobrocket_beaver_builder_clean_domain()
	 *
	 * @since 3.6
	 * @author Remy Perona
	 */
	public function purge_cache() {
		noobrocket_clean_minify();
		noobrocket_clean_domain();
	}
}
