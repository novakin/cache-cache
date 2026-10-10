<?php
declare(strict_types=1);

namespace NoobRocket\Engine\CDN\HostedCDN\APIHandler;

use NoobRocket\Engine\Common\JobManager\APIHandler\AbstractSafeAPIClient;
use NoobRocket\Engine\License\API\User;

/**
 * Class to Interact with the HostedCDN API
 */
class CreateAPIClient extends AbstractSafeAPIClient {

	/**
	 * Free url from user endpoint.
	 *
	 * @var string
	 */
	private $free_url;

	/**
	 * Constructor to get the noobrocketcdn free url from user endpoint to be used later.
	 *
	 * @param User $user User instance.
	 */
	public function __construct( User $user ) {
		$this->free_url = $user->get_noobrocketcdn_free_url();
	}

	/**
	 * Get the transient key for making this API Client calls.
	 *
	 * @return string The transient key for plugin updates.
	 */
	protected function get_transient_key() {
		return 'noobrocket_cdn_create_request';
	}

	/**
	 * Get the API URL for creating noobrocketcdn free account silently.
	 *
	 * @return string The API URL.
	 */
	protected function get_api_url() {
		return $this->free_url;
	}

	/**
	 * Create HostedCDN free account.
	 *
	 * @return array|false
	 */
	public function create() {
		$response = $this->send_post_request( [], true );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$response = wp_remote_retrieve_body( $response );
		if ( empty( $response ) ) {
			return false;
		}

		return json_decode( $response, true );
	}
}
