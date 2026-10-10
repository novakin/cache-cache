<?php
declare( strict_types=1 );

namespace NoobRocket\Engine\Common\PerformanceHints\AJAX;

class Processor {
	/**
	 * Array of Factories.
	 *
	 * @var array
	 */
	private $factories;

	/**
	 * Instantiate the class
	 *
	 * @param array $factories Array of factories.
	 */
	public function __construct( array $factories ) {
		$this->factories = $factories;
	}

	/**
	 * Checks existing data for various performance hints feature using their factories,
	 * then encodes the result in a single instance.
	 *
	 * @return void
	 */
	public function check_data(): void {
		$payload = $this->get_payload( $this->factories, 'check_data' );
		wp_send_json_success( $payload );
	}

	/**
	 * Adds performance hints data to DB.
	 *
	 * @return void
	 */
	public function add_data() {
		// Bound the shared beacon before individual features decode it.
		if ( ! isset( $_POST['results'] ) || ! is_string( $_POST['results'] ) || strlen( $_POST['results'] ) > 65536 // phpcs:ignore WordPress.Security.NonceVerification.Missing
			|| ! is_object( json_decode( wp_unslash( $_POST['results'] ) ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			wp_send_json_error( [ 'message' => __( 'Invalid performance hint payload.', 'noobrocket' ) ], 400 );
			return;
		}
		$payload = $this->get_payload( $this->factories, 'add_data' );
		wp_send_json_success( $payload );
	}

	/**
	 * Gets the response for ajax request.
	 *
	 * @param array  $factories Array of factories.
	 * @param string $method Ajax product method name.
	 * @return array
	 */
	private function get_payload( array $factories, string $method ): array {
		$payload = [];

		foreach ( $factories as $factory ) {
			$payload = array_merge( $payload, $factory->get_ajax_controller()->$method() );
		}

		return $payload;
	}
}
