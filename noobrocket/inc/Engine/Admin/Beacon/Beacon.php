<?php

namespace NoobRocket\Engine\Admin\Beacon;

use NoobRocket\Abstract_Render;
use NoobRocket\Admin\Options_Data;
use NoobRocket\Engine\Support\Data;
use NoobRocket\Event_Management\Subscriber_Interface;

/** Local documentation links using the existing settings help contract. */
class Beacon extends Abstract_Render implements Subscriber_Interface {
	/**
	 * Keep the settings container's constructor contract; no account data is used.
	 *
	 * @param Options_Data $options Existing options argument.
	 * @param string       $template_path Settings template directory.
	 * @param Data         $support_data Existing support argument.
	 */
	public function __construct( Options_Data $options, $template_path, Data $support_data ) {
		parent::__construct( $template_path );
	}

	/** @return array No remote support widget is registered. */
	public static function get_subscribed_events() {
		return [];
	}

	/** Compatibility entry point; local documentation needs no injected script. */
	public function insert_script() {
	}

	/**
	 * @param string $doc_id Settings help identifier.
	 * @return string|array Local help in the native settings renderer's expected shape.
	 */
	public function get_suggest( $doc_id ) {
		$id = sanitize_key( $doc_id );
		if ( in_array( $id, [
			'user_cache_section',
			'mobile_cache_section',
			'cache_lifespan_section',
			'css_section',
			'js_section',
			'lazyload_section',
			'fonts_section',
			'sitemap_preload',
			'preload_bot',
			'cdn_section',
		], true ) ) {
			return $id;
		}

		if ( 'faq' === $id ) {
			return [
				[
					'id'    => 'troubleshooting',
					'url'   => get_noobrocket_documentation_url() . '#troubleshooting',
					'title' => __( 'Troubleshooting', 'noobrocket' ),
				],
			];
		}

		return [
			'id'  => $id,
			'url' => get_noobrocket_documentation_url(),
		];
	}
}
