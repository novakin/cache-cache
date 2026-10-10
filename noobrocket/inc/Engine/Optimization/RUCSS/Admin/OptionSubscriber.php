<?php
declare(strict_types=1);

namespace NoobRocket\Engine\Optimization\RUCSS\Admin;

use NoobRocket\Engine\Admin\Settings\Settings as AdminSettings;
use NoobRocket\Event_Management\Subscriber_Interface;

class OptionSubscriber implements Subscriber_Interface {
	/**
	 * Settings instance
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Instantiate the class
	 *
	 * @param Settings $settings Settings instance.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Return an array of events that this subscriber wants to listen to.
	 *
	 * @return array
	 */
	public static function get_subscribed_events() {
		return [
			'noobrocket_first_install_options' => 'add_options_first_time',
			'noobrocket_input_sanitize'        => [ 'sanitize_options', 14, 2 ],
			'noobrocket_meta_boxes_fields'     => [ 'add_meta_box', 2 ],
		];
	}

	/**
	 * Add the RUCSS options to the NoobRocket options array.
	 *
	 * @since 3.9
	 *
	 * @param array $options NoobRocket options array.
	 *
	 * @return array
	 */
	public function add_options_first_time( $options ): array {
		return $this->settings->add_options( $options );
	}

	/**
	 * Sanitizes RUCSS options values when the settings form is submitted
	 *
	 * @since 3.9
	 *
	 * @param array         $input    Array of values submitted from the form.
	 * @param AdminSettings $settings Settings class instance.
	 *
	 * @return array
	 */
	public function sanitize_options( $input, AdminSettings $settings ): array {
		return $this->settings->sanitize_options( $input, $settings );
	}

	/**
	 * Add the field to the NoobRocket metabox on the post edit page.
	 *
	 * @param string[] $fields Metaboxes fields.
	 *
	 * @return string[]
	 */
	public function add_meta_box( array $fields ) {
		$fields['remove_unused_css'] = __( 'Remove Unused CSS', 'noobrocket' );

		return $fields;
	}
}
