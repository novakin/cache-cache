<?php
declare(strict_types=1);

namespace NoobRocket\Engine\CDN\Admin;

use NoobRocket\Event_Management\Subscriber_Interface;
use NoobRocket\Engine\CDN\Context;

class Subscriber implements Subscriber_Interface {
	/**
	 * Returns an array of events that this subscriber wants to listen to.
	 *
	 * @return array
	 */
	public static function get_subscribed_events() {
		return [
			'noobrocket_meta_boxes_fields'      => [ 'add_meta_box', 9 ],
			'noobrocket_hidden_settings_fields' => [ 'add_cdn_type', 10 ],
			'noobrocket_input_sanitize'         => 'sanitize_cdn_type_option',
		];
	}

	/**
	 * Add the field to the NoobRocket metabox on the post edit page.
	 *
	 * @param string[] $fields Metaboxes fields.
	 *
	 * @return string[]
	 */
	public function add_meta_box( array $fields ) {
		/*
		 * Hiding the CDN option in the metabox for now.
		 * We will revisit this when handling CDN status for different pages/posts.
		 *
		 * $fields['cdn'] = __( 'CDN', 'noobrocket' );
		 */

		return $fields;
	}

	/**
	 * Preserve CDN mode metadata in the classic form.
	 * The visible manual CDN checkbox submits `cdn`; a duplicate hidden field
	 * would overwrite its checked value when WordPress parses the form.
	 *
	 * @param string[] $fields Hidden settings fields.
	 *
	 * @return string[]
	 */
	public function add_cdn_type( array $fields ) {
		$fields[] = 'cdn_type';
		$fields[] = 'cdn_state';

		return $fields;
	}

	/**
	 * Sanitize the CDN type option.
	 *
	 * Ensure a form save doesn't overwrite toggle REST API.
	 *
	 * @param array $input Input array.
	 *
	 * @return array
	 */
	public function sanitize_cdn_type_option( array $input ) {
		// Set default value if empty.
		if ( empty( $input['cdn_type'] ) ) {
			$input['cdn_type'] = Context::HOSTEDCDN_TYPE;
		}

		$allowed_drivers = [ Context::HOSTEDCDN_TYPE, Context::BYOCDN_TYPE ];

		// Validate that the value is one of the allowed drivers.
		if ( ! in_array( $input['cdn_type'], $allowed_drivers, true ) ) {
			$input['cdn_type'] = Context::HOSTEDCDN_TYPE;
		}

		// Sanitize the value.
		$input['cdn_type'] = sanitize_text_field( $input['cdn_type'] );

		if ( isset( $input['cdn_state'] ) ) {
			$allowed_states = [
				Context::CDN_STATE_NOTHING,
				Context::HOSTEDCDN_FREE_TYPE,
				Context::HOSTEDCDN_PAID_TYPE,
				Context::BYOCDN_TYPE,
			];

			if ( ! in_array( $input['cdn_state'], $allowed_states, true ) ) {
				$input['cdn_state'] = Context::CDN_STATE_NOTHING;
			}

			$input['cdn_state'] = sanitize_text_field( $input['cdn_state'] );
		}

		return $input;
	}
}
