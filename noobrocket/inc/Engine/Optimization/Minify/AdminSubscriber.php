<?php
declare(strict_types=1);

namespace NoobRocket\Engine\Optimization\Minify;

use NoobRocket\Admin\Options_Data;
use NoobRocket\Event_Management\Subscriber_Interface;

class AdminSubscriber implements Subscriber_Interface {
	/**
	 * NoobRocket Options
	 *
	 * @var Options_Data
	 */
	private $options;

	/**
	 * Instantiate the class
	 *
	 * @param Options_Data $options NoobRocket Options Instance.
	 */
	public function __construct( Options_Data $options ) {
		$this->options = $options;
	}

	/**
	 * Return an array of events that this subscriber wants to listen to.
	 *
	 * @return array
	 */
	public static function get_subscribed_events() {
		return [
			'switch_theme'             => 'clean_minify_all',
			'noobrocket_meta_boxes_fields' => [ 'add_meta_box', 4 ],
		];
	}

	/**
	 * Delete all minified cache files
	 *
	 * @return void
	 */
	public function clean_minify_all() {
		// Bail out if minify_js or minify_css is not enabled.
		if (
			! (bool) $this->options->get( 'minify_js', 0 )
			&&
			! (bool) $this->options->get( 'minify_css', 0 )
		) {
			return;
		}

		// Delete all minify cache files.
		noobrocket_clean_minify();
	}

	/**
	 * Add the field to the NoobRocket metabox on the post edit page.
	 *
	 * @param string[] $fields Metaboxes fields.
	 *
	 * @return string[]
	 */
	public function add_meta_box( array $fields ) {
		$fields['minify_js'] = __( 'Minify/combine JavaScript', 'noobrocket' );
		return $fields;
	}
}
