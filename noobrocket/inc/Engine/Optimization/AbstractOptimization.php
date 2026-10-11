<?php
namespace NoobRocket\Engine\Optimization;

use NoobRocket\Admin\Options_Data;

/**
 * Base abstract class for files optimization
 *
 * @since 3.1
 * @author Remy Perona
 */
abstract class AbstractOptimization {
	use RegexTrait;

	/**
	 * Plugin options.
	 *
	 * @var Options_Data
	 */
	protected $options;

	/**
	 * Minify key.
	 *
	 * @var mixed
	 */
	protected $minify_key;

	/**
	 * Concatenated list of excluded files.
	 *
	 * @var string
	 */
	protected $excluded_files;

	/**
	 * Minify base path.
	 *
	 * @var string
	 */
	protected $minify_base_path;

	/**
	 * Minify base URL.
	 *
	 * @var string
	 */
	protected $minify_base_url;

	/**
	 * Initializes the minify base path and URL.
	 */
	protected function init_base_path_and_url() {
		$site_id                = get_current_blog_id() . '/';
		$this->minify_base_path = noobrocket_get_constant( 'NOOBROCKET_MINIFY_CACHE_PATH' ) . $site_id;
		$this->minify_base_url  = noobrocket_get_constant( 'NOOBROCKET_MINIFY_CACHE_URL' ) . $site_id;
	}

	/**
	 * Finds nodes matching the pattern in the HTML.
	 *
	 * @since 3.1
	 * @author Remy Perona
	 *
	 * @param string $pattern Pattern to match.
	 * @param string $html HTML content.
	 * @return bool|array
	 */
	protected function find( string $pattern, string $html ) {
		preg_match_all( '/' . $pattern . '/Umsi', $html, $matches, PREG_SET_ORDER );

		if ( empty( $matches ) ) {
			return false;
		}

		return $matches;
	}

	/**
	 * Determines if the file is external.
	 *
	 * @since 2.11
	 * @author Remy Perona
	 *
	 * @param string $url URL of the file.
	 * @return bool True if external, false otherwise
	 */
	protected function is_external_file( $url ) {
		$file = get_noobrocket_parse_url( $url );

		if ( empty( $file['path'] ) ) {
			return true;
		}

		$wp_content = wp_parse_url( content_url() );

		if ( empty( $wp_content['path'] ) ) {
			return true;
		}

		/**
		 * Filters the allowed hosts for optimization
		 *
		 * @since  3.4
		 * @author Remy Perona
		 *
		 * @param array $hosts Allowed hosts.
		 * @param array $zones Zones to check available hosts.
		 */
		$cdn_hosts = (array) apply_filters( 'noobrocket_cdn_hosts', [], $this->get_zones() );
		$hosts     = $cdn_hosts;
		$site      = wp_parse_url( site_url() );
		$origins   = [ $wp_content, $site ];

		if ( ! empty( $wp_content['host'] ) ) {
			$hosts[] = $wp_content['host'];
		}

		$langs = get_noobrocket_i18n_uri();

		// Get host for all langs.
		foreach ( $langs as $lang ) {
			$origin   = wp_parse_url( $lang );
			$url_host = $origin['host'] ?? null;

			if ( ! isset( $url_host ) ) {
				continue;
			}

			$hosts[] = $url_host;
			$origins[] = $origin;
		}

		$hosts = array_unique( $hosts );

		if ( empty( $hosts ) ) {
			return true;
		}
		// URL has domain and domain is part of the internal domains.
		if ( ! empty( $file['host'] ) ) {
			$default_port = 'http' === ( $file['scheme'] ?? $site['scheme'] ?? $wp_content['scheme'] ?? 'https' ) ? 80 : 443;
			$file_port    = (int) ( $file['port'] ?? $default_port );
			foreach ( $hosts as $host ) {
				if ( ! is_string( $host ) || 0 !== strcasecmp( rtrim( $file['host'], '.' ), rtrim( $host, '.' ) ) ) {
					continue;
				}
				// Bare CDN hosts retain ordinary HTTP/HTTPS aliases, not unrelated service ports.
				if ( in_array( $host, $cdn_hosts, true ) && $file_port === $default_port ) {
					return false;
				}
				foreach ( $origins as $origin ) {
					if ( empty( $origin['host'] ) || 0 !== strcasecmp( rtrim( $file['host'], '.' ), rtrim( $origin['host'], '.' ) ) ) {
						continue;
					}
					$origin_default_port = 'http' === ( $origin['scheme'] ?? 'https' ) ? 80 : 443;
					$origin_port         = (int) ( $origin['port'] ?? $origin_default_port );
					if ( $origin_port !== $origin_default_port ? $file_port === $origin_port : $file_port === $default_port ) {
						return false;
					}
				}
			}

			return true;
		}

		if ( empty( $file['host'] ) ) {
			return false;
		}

		// URL has no domain and doesn't contain the WP_CONTENT path or wp-includes.
		return ! preg_match( '#(' . $wp_content['path'] . '|wp-includes)#', $file['path'] );
	}

	/**
	 * Writes the content to a file
	 *
	 * @since 3.1
	 * @author Remy Perona
	 *
	 * @param string $content       Content to write.
	 * @param string $file          Path to the file to write in.
	 * @return bool
	 */
	protected function write_file( $content, $file ) {
		if ( noobrocket_direct_filesystem()->is_readable( $file ) ) {
			return true;
		}

		if ( ! noobrocket_mkdir_p( dirname( $file ) ) ) {
			return false;
		}

		if ( function_exists( 'gzencode' ) ) {
			// This filter is documented in inc/classes/Buffer/class-cache.php.
			$gzip_content = gzencode( $content, apply_filters( 'noobrocket_gzencode_level_compression', 6 ) );

			if ( $gzip_content ) {
				noobrocket_put_content( $file . '.gz', $gzip_content );
			}
		}

		return noobrocket_put_content( $file, $content );
	}

	/**
	 * Gets the file path from an URL
	 *
	 * @since 3.1
	 * @author Remy Perona
	 *
	 * @param string $url File URL.
	 * @return bool|string
	 */
	protected function get_file_path( $url ) {
		return noobrocket_url_to_path( strtok( $url, '?' ), $this->get_zones() );
	}

	/**
	 * Gets content of a file
	 *
	 * @since 3.1
	 * @author Remy Perona
	 *
	 * @param string $file File path.
	 * @return string
	 */
	protected function get_file_content( $file ) {
		return noobrocket_direct_filesystem()->get_contents( $file );
	}

	/**
	 * Hides unwanted blocks from the HTML to be parsed for optimization
	 *
	 * @since 3.1.4
	 * @author Remy Perona
	 *
	 * @param string $html HTML content.
	 * @return string
	 */
	protected function hide_comments( $html ) {
		$html = preg_replace( '#<!--\s*noptimize\s*-->.*?<!--\s*/\s*noptimize\s*-->#is', '', $html );
		$html = preg_replace( '/<!--(.*)-->/Uis', '', $html );

		return $html;
	}

	/**
	 * Get full minified url with ?ver query string.
	 *
	 * @param string $minified_path Path of minified file.
	 * @param string $minified_url Url of minified file.
	 *
	 * @return string
	 */
	protected function get_full_minified_url( $minified_path, $minified_url ) {
		$file_mtime = noobrocket_direct_filesystem()->mtime( $minified_path );

		$version = $file_mtime ? $file_mtime : md5( $minified_url . $this->minify_key );

		return add_query_arg( 'ver', $version, $minified_url );
	}

	/**
	 * Gets the CDN zones.
	 *
	 * @since  3.1
	 *
	 * @return array
	 */
	abstract public function get_zones();
}
