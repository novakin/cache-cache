<?php
declare(strict_types=1);

namespace NoobRocket\Addon\Cloudflare\Auth;

use WP_Error;

class APIKey implements AuthInterface {
	/**
	 * Cloudflare email
	 *
	 * @var string
	 */
	private $email = '';

	/**
	 * Cloudflare API Key
	 *
	 * @var string
	 */
	private $api_key = '';

	/**
	 * Constructor
	 *
	 * @param string $email Cloudflare email.
	 * @param string $api_key Cloudflare API key.
	 */
	public function __construct( string $email = '', string $api_key = '' ) {
		$this->email   = $email;
		$this->api_key = $api_key;
	}

	/**
	 * Gets headers for Cloudflare API request
	 *
	 * @return array
	 */
	public function get_headers(): array {
		return [
			'X-Auth-Email' => $this->email,
			'X-Auth-Key'   => $this->api_key,
		];
	}

	/**
	 * Checks if the credentials are set.
	 *
	 * @return bool|WP_Error true if authorized, false if not, WP_Error if either credential is empty.
	 */
	public function is_valid_credentials() {
		if (
			empty( $this->email )
			||
			empty( $this->api_key )
		) {
			return new WP_Error(
				'cloudflare_credentials_empty',
				sprintf(
					/* translators: %1$s = opening link; %2$s = closing link */
					__( 'Cloudflare email and/or API key are not set. Read the %1$sdocumentation%2$s for further guidance.', 'noobrocket' ),
					// translators: Documentation exists in EN, FR; use localized URL if applicable.
					'<a href="' . esc_url( get_noobrocket_documentation_url() ) . '" rel="noopener noreferrer" target="_blank">',
					'</a>'
				)
			);
		}

		return false !== filter_var( $this->email, FILTER_VALIDATE_EMAIL );
	}
}
