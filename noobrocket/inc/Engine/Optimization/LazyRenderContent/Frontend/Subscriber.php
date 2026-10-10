<?php
declare(strict_types=1);

namespace NoobRocket\Engine\Optimization\LazyRenderContent\Frontend;

use NoobRocket\Event_Management\Subscriber_Interface;

class Subscriber implements Subscriber_Interface {
	/**
	 * LazyRenderContent controller.
	 *
	 * @var Controller
	 */
	private $controller;

	/**
	 * Subscriber constructor.
	 *
	 * @param Controller $controller LazyRenderContent controller.
	 */
	public function __construct( Controller $controller ) {
		$this->controller = $controller;
	}

	/**
	 * Returns an array of events that this subscriber wants to listen to.
	 *
	 * @return array
	 */
	public static function get_subscribed_events(): array {
		return [
			'noobrocket_buffer'                   => [ 'add_hashes_when_allowed', 16 ],
			'noobrocket_performance_hints_buffer' => [ 'add_hashes', 16 ],
		];
	}

	/**
	 * Add hashes to the HTML elements if allowed
	 *
	 * @param string $html The HTML content.
	 *
	 * @return string
	 */
	public function add_hashes_when_allowed( $html ) {
		return $this->controller->add_hashes_when_allowed( $html );
	}

	/**
	 * Add hashes to the HTML elements
	 *
	 * @param string $html The HTML content.
	 *
	 * @return string
	 */
	public function add_hashes( $html ) {
		return $this->controller->add_hashes( $html );
	}
}
