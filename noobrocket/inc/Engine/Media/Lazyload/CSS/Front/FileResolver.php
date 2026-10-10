<?php

namespace NoobRocket\Engine\Media\Lazyload\CSS\Front;

class FileResolver {

	/**
	 * Resolves the name from the file from its URL.
	 *
	 * @param string $url URL from the file to resolve.
	 * @return string
	 */
	public function resolve( string $url ): string {
		$path = noobrocket_url_to_path( strtok( $url, '?' ) );

		// PHP stylesheets must execute through their original URL and query.
		if ( ! $path || 'php' === strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) ) {
			return '';
		}

		return $path;
	}
}
