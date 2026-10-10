<?php
declare(strict_types=1);

namespace NoobRocket\Engine\CDN\Drivers;

use NoobRocket\Engine\CDN\HostedCDN\Database\Queries\HostedCDN;

class HostedCDNFree implements DriverInterface {

	/**
	 * Query instance.
	 *
	 * @var HostedCDN
	 */
	private $query;

	/**
	 * Constructor.
	 *
	 * @param HostedCDN $query Query instance.
	 */
	public function __construct( HostedCDN $query ) {
		$this->query = $query;
	}

	/**
	 * Should rewrite url or not.
	 *
	 * @param string $url Page Url to check.
	 * @return bool
	 */
	public function should_rewrite_url( string $url ): bool {
		return $this->query->is_url_found( $url );
	}
}
