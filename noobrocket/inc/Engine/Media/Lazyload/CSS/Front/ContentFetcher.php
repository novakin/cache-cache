<?php

namespace NoobRocket\Engine\Media\Lazyload\CSS\Front;

use WP_Filesystem_Direct;
use NoobRocket\Engine\Optimization\CSSTrait;

class ContentFetcher {

	use CSSTrait;

	/**
	 * WordPress filesystem.
	 *
	 * @var WP_Filesystem_Direct
	 */
	protected $filesystem;

	/**
	 * Instance.
	 *
	 * @param WP_Filesystem_Direct|null $filesystem WordPress filesystem.
	 */
	public function __construct( ?WP_Filesystem_Direct $filesystem = null ) {
		$this->filesystem = $filesystem ?: noobrocket_direct_filesystem();
	}

	/**
	 * Fetch content from the resource.
	 *
	 * @param string $path Path from the resource.
	 * @param string $destination Destination path.
	 * @return false|string
	 */
	public function fetch( string $path, string $destination ) {
		$content = $this->fetch_content( $path );

		if ( ! $content ) {
			return false;
		}

		$content = $this->move( $this->get_converter( $path, $destination ), $content, $path );

		$this->set_cached_import( $path );

		$content = $this->combine_imports( $content, $destination );

		return $content;
	}

	/**
	 * Fetch the content from the file.
	 *
	 * @param string $path Path to fetch.
	 *
	 * @return false|string
	 */
	protected function fetch_content( string $path ) {
		$scheme = wp_parse_url( $path, PHP_URL_SCHEME );
		if ( in_array( strtolower( (string) $scheme ), [ 'http', 'https' ], true ) ) {
			$response = wp_safe_remote_get( $path );
			if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
				return false;
			}
			return wp_remote_retrieve_body( $response );
		}

		// Drive letters are local paths; every other scheme is unsupported.
		if ( false === $scheme || ( $scheme && ! preg_match( '#^[a-z]:[\\\\/]#i', $path ) )
			|| 0 === strpos( $path, '//' ) || 'php' === strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) ) {
			return false;
		}

		return $this->filesystem->get_contents( $path );
	}
}
