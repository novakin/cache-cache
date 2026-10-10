<?php

namespace NoobRocket\ThirdParty\Plugins\Optimization;

use NoobRocket\Event_Management\Subscriber_Interface;
use NoobRocket\ThirdParty\PluginCompatibilityInterface;

class Perfmatters implements Subscriber_Interface, PluginCompatibilityInterface {

	/**
	 * Whether the target third-party plugin is active.
	 *
	 * @return bool
	 */
	public static function is_activated(): bool {
		return noobrocket_has_constant( 'PERFMATTERS_VERSION' );
	}

	/**
	 * Return an array of events that this subscriber listens to.
	 *
	 * @return array
	 */
	public static function get_subscribed_events() {
		return [
			'noobrocket_disable_rucss_setting'            => 'disable_rucss_setting',
			'pre_get_noobrocket_option_remove_unused_css' => 'maybe_disable_rucss',
		];
	}

	/**
	 * Disable RUCSS setting.
	 *
	 * @param array $status RUCSS option status.
	 * @return array
	 */
	public function disable_rucss_setting( array $status ): array {
		if ( ! $this->is_perfmatters_rucss_active() ) {
			return $status;
		}

		return [
			'disable' => true,
			'text'    => __( 'Remove Unused CSS is currently activated in Perfmatters. If you want to use Cache-Cache\'s Remove Unused CSS feature, disable this option in Perfmatters.', 'noobrocket' ),
		];
	}

	/**
	 * Disable RUCSS option.
	 *
	 * @return bool|null
	 */
	public function maybe_disable_rucss() {
		return $this->is_perfmatters_rucss_active() ? false : null;
	}

	/**
	 * Check if perfmatters rucss is active.
	 *
	 * @return bool
	 */
	private function is_perfmatters_rucss_active(): bool {
		$perfmatters_options = get_option( 'perfmatters_options' );

		return ! empty( $perfmatters_options['assets']['remove_unused_css'] );
	}
}
