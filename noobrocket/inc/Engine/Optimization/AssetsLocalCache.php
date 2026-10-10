<?php
namespace NoobRocket\Engine\Optimization;

use WP_Filesystem_Direct;

/**
 * Cache locally 3rd party assets.
 *
 * @since 3.1
 */
class AssetsLocalCache {
	/**
	 * 3rd party assets cache folder path
	 *
	 * @since 3.1
	 *
	 * @var string
	 */
	protected $cache_path;

	/**
	 * Filesystem instance.
	 *
	 * @var WP_Filesystem_Direct
	 */
	private $filesystem;

	/**
	 * Cache contents with url.
	 *
	 * @var array
	 */
	private $url_cache = [];

	/**
	 * Constructor
	 *
	 * @since 3.1
	 *
	 * @param string               $cache_path 3rd party assets cache folder path.
	 * @param WP_Filesystem_Direct $filesystem Filesysten instance.
	 */
	public function __construct( $cache_path, $filesystem ) {
		$this->cache_path = "{$cache_path}3rd-party/";
		$this->filesystem = $filesystem;
	}

	/**
	 * Get remote file contents.
	 *
	 * @param string $url Url of the file to get contents for.
	 *
	 * @return string Raw file contents.
	 */
	private function get_raw_content( $url ) {
		$cache_key = md5( $url );

		if ( ! isset( $this->url_cache[ $cache_key ] ) ) {
			$response = wp_safe_remote_get( $url, [ 'timeout' => 3, 'limit_response_size' => 2 * 1024 * 1024 ] );
			$this->url_cache[ $cache_key ] = ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response )
				? wp_remote_retrieve_body( $response ) : '';
			if ( strlen( $this->url_cache[ $cache_key ] ) >= 2 * 1024 * 1024 ) {
				$this->url_cache[ $cache_key ] = ''; // The HTTP response may have been truncated at the bound.
			}
		}

		return $this->url_cache[ $cache_key ];
	}

	/**
	 * Gets content for the provided URL.
	 * Use the local cache file if it exists, else get it from the 3rd party URL and save it locally for future use.
	 *
	 * @since 3.1
	 *
	 * @param string $url URL to get the content from.
	 *
	 * @return string
	 */
	public function get_content( $url ) {
		$filepath = $this->get_filepath( $url );

		if ( empty( $filepath ) ) {
			return '';
		}

		if ( $this->filesystem->is_readable( $filepath ) ) {
			return $this->filesystem->get_contents( $filepath );
		}

		$content = $this->get_raw_content( $url );

		if ( empty( $content ) ) {
			return '';
		}

		$this->write_file( $content, $filepath );

		return $content;
	}

	/**
	 * Gets the filepath of the local copy for the given URL
	 *
	 * @since 3.7
	 *
	 * @param string $url URL to get filepath for.
	 * @return string
	 */
	public function get_filepath( $url ) {
		$parts = wp_parse_url( $url );

		if ( empty( $parts['path'] ) || empty( $parts['host'] ) ) {
			return '';
		}

		// Keep the complete request identity, including meaningful query arguments.
		$filename = $parts['host'] . '-' . md5( $url ) . '-' . basename( $parts['path'] );

		return $this->cache_path . $filename;
	}

	/**
	 * Writes the content to a file
	 *
	 * @since 3.1
	 *
	 * @param string $content Content to write.
	 * @param string $file    Path to the file to write in.
	 * @return bool
	 */
	protected function write_file( $content, $file ) {
		if ( $this->filesystem->is_readable( $file ) ) {
			return true;
		}

		if ( ! noobrocket_mkdir_p( dirname( $file ) ) ) {
			return false;
		}

		return noobrocket_put_content( $file, $content );
	}

	/**
	 * Check if this link HTML has integrity attribute or not?
	 *
	 * @since 3.7.5
	 *
	 * @param string $asset Link HTML to be tested.
	 *
	 * @return array|false Matched array with integrityhashmethod, integrityhash keys.
	 */
	private function has_integrity( $asset ) {
		if ( ! preg_match( '#\s*integrity\s*=[\'"](?<integrityhashmethod>.*)-(?<integrityhash>.*)[\'"]#Ui', $asset, $integrity_matches ) ) {
			return false;
		}

		return $integrity_matches;
	}

	/**
	 * Validate the integrity attribute if the content matches with the hashed integrity value.
	 *
	 * @param array $asset_matched the matched array which has 0, url keys.
	 *
	 * @return bool|string
	 */
	public function validate_integrity( $asset_matched ) {
		$integrity_matches = $this->has_integrity( $asset_matched[0] );

		if ( false === $integrity_matches ) {
			return $asset_matched[0];
		}

		// validate the hash algorithm.
		if ( ! in_array( $integrity_matches['integrityhashmethod'], hash_algos(), true ) ) {
			return false;
		}

		$content      = $this->get_raw_content( $asset_matched['url'] );
		$content_hash = base64_encode( hash( $integrity_matches['integrityhashmethod'], $content, true ) );// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		if ( $integrity_matches['integrityhash'] !== $content_hash ) {
			return false;
		}

		return str_replace( $integrity_matches[0], '', $asset_matched[0] );
	}
}
