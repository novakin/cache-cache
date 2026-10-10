<?php
declare(strict_types=1);

namespace NoobRocket\Engine\Media\Lazyload;

use NoobRocket\Event_Management\Subscriber_Interface;

class AdminSubscriber implements Subscriber_Interface {
	/**
	 * Returns an array of events this listens to
	 *
	 * @return array
	 */
	public static function get_subscribed_events(): array {
		return [
			'noobrocket_first_install_options' => [ 'add_option', 15 ],
			'noobrocket_input_sanitize'        => 'sanitize_exclude_lazyload',
			'noobrocket_meta_boxes_fields'     => [ 'add_meta_box', 7 ],
		];
	}

	/**
	 * Adds the exclude lazyload option to NoobRocket options array
	 *
	 * @since 3.8
	 *
	 * @param array $options NoobRocket options array.
	 * @return array
	 */
	public function add_option( array $options ): array {
		$options['exclude_lazyload'] = [];

		return $options;
	}

	/**
	 * Sanitizes the exclude lazyload input when saving the option
	 *
	 * @since 3.8
	 *
	 * @param array $input Input array.
	 * @return array
	 */
	public function sanitize_exclude_lazyload( array $input ): array {
		if ( empty( $input['exclude_lazyload'] ) ) {
			$input['exclude_lazyload'] = [];
		}

		$input['exclude_lazyload'] = noobrocket_sanitize_textarea_field( 'exclude_lazyload', $input['exclude_lazyload'] );

		return $input;
	}

	/**
	 * Add the field to the NoobRocket metabox on the post edit page.
	 *
	 * @param string[] $fields Metaboxes fields.
	 *
	 * @return string[]
	 */
	public function add_meta_box( array $fields ) {
		$fields['lazyload']         = __( 'LazyLoad for images', 'noobrocket' );
		$fields['lazyload_iframes'] = __( 'LazyLoad for iframes/videos', 'noobrocket' );

		return $fields;
	}
}
