<?php
declare(strict_types=1);

namespace NoobRocket\Engine\License\API;

class RemoteSettings {
	/**
	 * The remote settings data object
	 *
	 * @var object|null
	 */
	private $remote_settings;

	/**
	 * The remote settings API Client
	 *
	 * @var RemoteSettingsClient
	 */
	private $api_client;

	/**
	 * Instantiate the class
	 *
	 * @param RemoteSettingsClient $api_client The remote settings API Client.
	 */
	public function __construct( RemoteSettingsClient $api_client ) {
		$this->api_client = $api_client;
	}

	/**
	 * Determines if the Performance monitoring remote setting is enabled.
	 *
	 * Checks if the 'noobrocket_insights_remote_setting' property exists in the remote settings object.
	 * Returns false if the property is not set, otherwise returns its boolean value.
	 *
	 * @since 3.20.3
	 *
	 * @return bool True only if the remote setting is explicitly enabled.
	 */
	public function is_noobrocket_insights_remote_setting_enabled() {
		$this->remote_settings = $this->api_client->get_remote_settings_data();

		if ( ! isset( $this->remote_settings->noobrocket_insights_display_post_column ) ) {
			return false;
		}

		return (bool) $this->remote_settings->noobrocket_insights_display_post_column;
	}
}
