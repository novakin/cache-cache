<?php

namespace NoobRocket\Engine\Preload\Controller;

class CrawlHomepage {
	use CheckExcludedTrait;

	/**
	 * Crawl the homepage.
	 *
	 * @return array|false
	 */
	public function crawl() {
		$user_agent = 'NoobRocket/Preload';

		/**
		 * Filters the arguments for the partial preload request.
		 *
		 * @param array $args Request arguments.
		 */
		$args = apply_filters(
			'noobrocket_homepage_preload_url_request_args',
			[
				'timeout'    => 10,
				'user-agent' => $user_agent,
				'sslverify'  => apply_filters( 'https_local_ssl_verify', false ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			]
		);

		$response = wp_remote_get( esc_url_raw( home_url() ), $args );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$response_code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $response_code ) {
			return false;
		}

		$content = wp_remote_retrieve_body( $response );

		preg_match_all( '/<a\s+(?:[^>]+?[\s"\']|)href\s*=\s*(["\'])(?<href>[^"\']+)\1/imU', $content, $urls );

		$urls = array_map(
			function ( $url ) {
				return $this->get_site_page_url( $url );
			},
			$urls['href']
		);
		$urls = array_filter( $urls );

		return array_values( array_unique( $urls ) );
	}
}
