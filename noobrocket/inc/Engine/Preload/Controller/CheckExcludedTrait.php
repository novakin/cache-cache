<?php

namespace NoobRocket\Engine\Preload\Controller;

trait CheckExcludedTrait {

	/** Resolve an automatically discovered page inside the selected site's origin/path. */
	protected function get_site_page_url( string $url ): string {
		$home = home_url();
		$input = wp_parse_url( $url );
		if ( ! is_array( $input ) || isset( $input['user'] ) || isset( $input['pass'] ) || false !== strpos( $url, '#' ) ) {
			return '';
		}
		$url = \WP_Http::make_absolute_url( $url, trailingslashit( $home ) );
		$page = wp_parse_url( $url );
		$site = wp_parse_url( $home );
		if ( ! is_array( $page ) || ! is_array( $site ) || isset( $page['user'] ) || isset( $page['pass'] ) ) {
			return '';
		}
		$page_scheme = strtolower( $page['scheme'] ?? '' );
		$site_scheme = strtolower( $site['scheme'] ?? '' );
		$page_port = (int) ( $page['port'] ?? ( 'https' === $page_scheme ? 443 : 80 ) );
		$site_port = (int) ( $site['port'] ?? ( 'https' === $site_scheme ? 443 : 80 ) );
		if ( ! in_array( $page_scheme, [ 'http', 'https' ], true )
			|| $page_scheme !== $site_scheme
			|| strtolower( $page['host'] ?? '' ) !== strtolower( $site['host'] ?? '' )
			|| $page_port !== $site_port ) {
			return '';
		}
		$path = $page['path'] ?? '/';
		$site_path = untrailingslashit( $site['path'] ?? '' );
		if ( preg_match( '#(?:^|/)(?:\.|\.\.)(?:/|$)#', rawurldecode( $path ) )
			|| ( '' !== $site_path && $path !== $site_path && 0 !== strpos( $path, $site_path . '/' ) ) ) {
			return '';
		}
		return $url;
	}

	/**
	 * Add new pattern of excluded uri.
	 *
	 * @param array $regexes regexes used to exclude urls.
	 * @return array
	 */
	public function add_cache_reject_uri_to_excluded( array $regexes ): array {
		$user_added_cache_reject_uri = (array) get_noobrocket_option( 'cache_reject_uri', [] );

		if ( count( $user_added_cache_reject_uri ) === 0 ) {
			return $regexes;
		}

		$altered_user_added_cache_reject_uri = implode( '$|', $user_added_cache_reject_uri );

		$user_added_cache_reject_uri = implode( '|', $user_added_cache_reject_uri );
		$cache_reject_uri            = get_noobrocket_cache_reject_uri();

		$regexes[] = str_replace( $user_added_cache_reject_uri . '|', $altered_user_added_cache_reject_uri . '$|', $cache_reject_uri );

		return $regexes;
	}

	/**
	 * Check if the url is excluded by using a filter.
	 *
	 * @param string $url url to check.
	 * @return bool
	 */
	protected function is_excluded_by_filter( string $url ): bool {
		global $wp_rewrite;

		$pagination_regex = "/$wp_rewrite->pagination_base/\d+";
		$url              = strtok( $url, '?' );
		$url              = user_trailingslashit( $url );

		/**
		 * Check if the url is excluded or not.
		 *
		 * @param bool $isExcluded Default excluded or not.
		 * @param string $url URL to check.
		 */
		$is_excluded = apply_filters( 'noobrocket_preload_exclude', false, $url );

		if ( $is_excluded ) {
			return true;
		}

		/**
		 * Regex to exclude URI from preload.
		 *
		 * @param string[] $regexes Regexes to check.
		 * @param string   $url Current preloading url.
		 */
		$regexes = (array) apply_filters( 'noobrocket_preload_exclude_urls', [ $pagination_regex ], $url );

		if ( empty( $regexes ) ) {
			return false;
		}

		$regexes = array_unique( $regexes );

		foreach ( $regexes as $regex ) {
			if ( ! is_string( $regex ) ) { // @phpstan-ignore-line
				continue;
			}

			$regex = user_trailingslashit( $regex );

			if ( preg_match( "@$regex$@m", $url ) ) {
				return true;
			}
		}
		return false;
	}
}
