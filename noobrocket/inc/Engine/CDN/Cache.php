<?php
declare(strict_types=1);

namespace NoobRocket\Engine\CDN;

use NoobRocket\Engine\CDN\HostedCDN\Database\Queries\HostedCDN;
use NoobRocket\Engine\Common\Utils;

class Cache {

	/**
	 * HostedCDN Query instance.
	 *
	 * @var HostedCDN
	 */
	private $query;

	/**
	 * Constructor.
	 *
	 * @param HostedCDN $query HostedCDN Query instance.
	 */
	public function __construct( HostedCDN $query ) {
		$this->query = $query;
	}

	/**
	 * Clear all site's cache.
	 *
	 * @return void
	 */
	public function clear_all_cache(): void {
		noobrocket_clean_domain();
	}

	/**
	 * Clear noobrocketCDN free pages cache.
	 *
	 * @return void
	 */
	public function clear_noobrocketcdn_free_pages_cache(): void {
		$pages = $this->query->get_all();

		foreach ( $pages as $page ) {
			if ( empty( $page->url ) ) {
				continue;
			}

			Utils::clean_url( $page->url );
		}
	}
}
