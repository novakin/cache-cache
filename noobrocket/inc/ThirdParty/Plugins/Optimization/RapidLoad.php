<?php

namespace NoobRocket\ThirdParty\Plugins\Optimization;

use NoobRocket\Event_Management\Subscriber_Interface;

class RapidLoad implements Subscriber_Interface {

	/**
	 * Return an array of events that this subscriber listens to.
	 *
	 * @return array
	 */
	public static function get_subscribed_events() {
		if ( ! defined( 'UUCSS_VERSION' ) ) {
			return [];
		}

		return [
			'noobrocket_disable_rucss_setting'            => 'disable_rucss_setting',
			'pre_get_noobrocket_option_remove_unused_css' => 'maybe_disable_rucss',
			'deactivated_plugin'                      => [ 'noobrocket_clean_cache_on_deactivation', 12 ],
		];
	}

	/**
	 * Disable RUCSS setting.
	 *
	 * @param array $status RUCSS option status.
	 * @return array
	 */
	public function disable_rucss_setting( array $status ): array {
		if ( ! $this->is_rapidload_active() ) {
			return $status;
		}

		return [
			'disable' => true,
			'text'    => __( 'Automated unused CSS removal is currently activated in RapidLoad Power-Up for Autoptimize. If you want to use Cache-Cache\'s Remove Unused CSS feature, disable the  RapidLoad Power-Up for Autoptimize plugin.', 'noobrocket' ),
		];
	}

	/**
	 * Disable RUCSS option.
	 *
	 * @return bool|null
	 */
	public function maybe_disable_rucss() {
		return $this->is_rapidload_active() ? false : null;
	}

	/**
	 * Clean NoobRocket Cache when Rapidload is deactivated.
	 *
	 * @param string $plugin Plugin file.
	 * @return void
	 */
	public function noobrocket_clean_cache_on_deactivation( string $plugin ): void {
		if ( ! $this->is_rapidload_active() ) {
			return;
		}

		if ( 'unusedcss/unusedcss.php' !== $plugin ) {
			return;
		}

		noobrocket_dismiss_box( 'noobrocket_warning_plugin_modification' );
		noobrocket_clean_domain();
	}

	/**
	 * Check if RapidLoad Power-Up for Autoptimize is active.
	 *
	 * @return boolean
	 */
	private function is_rapidload_active(): bool {
		$autoptimize_uucss_settings = get_option( 'autoptimize_uucss_settings' );

		return ( isset( $autoptimize_uucss_settings['uucss_api_key_verified'] ) && 1 === $autoptimize_uucss_settings['uucss_api_key_verified'] && $autoptimize_uucss_settings['valid_domain'] );
	}
}
