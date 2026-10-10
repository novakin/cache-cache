<?php

namespace NoobRocket\ThirdParty\Plugins;

use NoobRocket\Admin\Options_Data;
use NoobRocket\Event_Management\Subscriber_Interface;
use NoobRocket\ThirdParty\PluginCompatibilityInterface;
use NoobRocket\ThirdParty\ReturnTypesTrait;

class Jetpack implements Subscriber_Interface, PluginCompatibilityInterface {

	use ReturnTypesTrait;

	/**
	 * Option instance.
	 *
	 * @var Options_Data
	 */
	protected $option;

	/**
	 * Instantiate class.
	 *
	 * @param Options_Data $option Option instance.
	 */
	public function __construct( Options_Data $option ) {
		$this->option = $option;
	}

	/**
	 * Whether Jetpack is active.
	 *
	 * @return bool
	 */
	public static function is_activated(): bool {
		return class_exists( 'Jetpack' );
	}

	/**
	 * Subscribed events.
	 */
	public static function get_subscribed_events() {
		$events = [];

		if ( ! class_exists( 'Jetpack' ) ) {
			return $events;
		}

		if ( \Jetpack::is_module_active( 'sitemaps' ) ) {
			$events['noobrocket_sitemap_preload_list'] = 'add_jetpack_sitemap';
		}

		return $events;
	}

	/**
	 * Add Jetpack sitemap to preload list
	 *
	 * @param Array $sitemaps Array of sitemaps to preload.
	 * @return Array Updated Array of sitemaps to preload
	 */
	public function add_jetpack_sitemap( $sitemaps ) {
		if ( ! function_exists( 'jetpack_sitemap_uri' ) ) {
			return $sitemaps;
		}

		$sitemaps['jetpack'] = jetpack_sitemap_uri();

		return $sitemaps;
	}
}
