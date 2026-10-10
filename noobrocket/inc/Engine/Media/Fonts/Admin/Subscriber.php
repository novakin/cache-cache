<?php
declare(strict_types=1);

namespace NoobRocket\Engine\Media\Fonts\Admin;

use NoobRocket\Engine\Admin\Settings\Settings;
use NoobRocket\Engine\Media\Fonts\Admin\Data;
use NoobRocket\Engine\Media\Fonts\Admin\Settings as FontsSettings;
use NoobRocket\Event_Management\Subscriber_Interface;

class Subscriber implements Subscriber_Interface {
	/**
	 * Fonts Settings instance
	 *
	 * @var FontsSettings
	 */
	private $settings;

	/**
	 * Fonts Data instance
	 *
	 * @var Data
	 */
	private $data;

	/**
	 * Instantiate the class
	 *
	 * @param FontsSettings $settings Fonts Settings instance.
	 * @param Data          $data     Fonts Data instance.
	 */
	public function __construct( FontsSettings $settings, Data $data ) {
		$this->settings = $settings;
		$this->data     = $data;
	}

	/**
	 * Returns an array of events this listens to
	 *
	 * @return array
	 */
	public static function get_subscribed_events(): array {
		return [
			'noobrocket_first_install_options' => [ 'add_option', 16 ],
			'noobrocket_input_sanitize'        => [ 'sanitize_option', 10, 2 ],
			'admin_init'                   => 'schedule_data_collection',
			'noobrocket_fonts_data_collection' => 'collect_data',
			'noobrocket_deactivation'          => [ 'unschedule_data_collection', 10, 2 ],
		];
	}

	/**
	 * Add the images dimensions option to the NoobRocket options array
	 *
	 * @param array $options NoobRocket options array.
	 *
	 * @return array
	 */
	public function add_option( array $options ): array {
		return $this->settings->add_option( $options );
	}

	/**
	 * Sanitizes the option value when saving from the settings page
	 *
	 * @param array    $input    Array of sanitized values after being submitted by the form.
	 * @param Settings $settings Settings class instance.
	 *
	 * @return array
	 */
	public function sanitize_option( array $input, Settings $settings ): array {
		return $this->settings->sanitize_option_value( $input, $settings );
	}

	/**
	 * Schedule data collection
	 *
	 * @return void
	 */
	public function schedule_data_collection() {
		$this->data->schedule_data_collection();
	}

	/**
	 * Unschedule data collection
	 *
	 * @return void
	 */
	public function unschedule_data_collection( $sites_number = 0, bool $network_wide = false ) {
		\NoobRocket\Engine\Deactivation\Deactivation::for_each_deactivated_site(
			[ $this->data, 'unschedule_data_collection' ],
			$network_wide
		);
	}

	/**
	 * Collect data
	 *
	 * @return void
	 */
	public function collect_data() {
		$this->data->collect_data();
	}
}
