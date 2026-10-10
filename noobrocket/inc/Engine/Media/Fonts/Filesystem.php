<?php
declare(strict_types=1);

namespace NoobRocket\Engine\Media\Fonts;

use WP_Filesystem_Direct;
use NoobRocket\Engine\Common\AbstractFileSystem;
use NoobRocket\Logger\Logger;

class Filesystem extends AbstractFileSystem {
	/**
	 * WP Filesystem instance
	 *
	 * @var WP_Filesystem_Direct
	 */
	protected $filesystem;

	/**
	 * Path to the fonts storage
	 *
	 * @var string
	 */
	private $path;

	/** One cold-download budget for the sheets handled by this frontend owner. */
	private $download_deadline;

	/**
	 * Instantiate the class
	 *
	 * @param WP_Filesystem_Direct $filesystem WP Filesystem instance.
	 */
	public function __construct( $filesystem = null ) {
		parent::__construct( is_null( $filesystem ) ? noobrocket_direct_filesystem() : $filesystem );

		$this->path = noobrocket_get_constant( 'NOOBROCKET_CACHE_ROOT_PATH', '' ) . 'fonts/' . get_current_blog_id() . '/';
	}

	/**
	 * Hashes the url
	 *
	 * @param string $url URL to get the hash from.
	 *
	 * @return string
	 */
	private function hash_url( string $url ): string {
		return md5( $url );
	}

	/**
	 * Checks if the file exists
	 *
	 * @param string $file Absolute path to the file.
	 *
	 * @return bool
	 */
	public function exists( string $file ): bool {
		return $this->filesystem->is_readable( $file ) && $this->filesystem->size( $file ) > 0;
	}

	/**
	 * Writes CSS & fonts locally
	 *
	 * @param string $css_url The CSS url to save locally.
	 * @param string $provider The font provider.
	 *
	 * @return bool
	 */
	public function write_font_css( string $css_url, string $provider ): bool {
		$cooldown = 'noobrocket_font_failure_' . md5( $css_url );
		if ( get_transient( $cooldown ) ) {
			return false;
		}
		if ( null === $this->download_deadline ) {
			$this->download_deadline = microtime( true ) + 3;
		}
		$font_provider_path = $this->get_font_provider_path( $provider );
		$css_filepath       = $this->get_absolute_path( $font_provider_path, 'css/' . $this->hash_to_path( $this->hash_url( $css_url ) ) . '.css' );
		$fonts_basepath     = $this->get_absolute_path( $font_provider_path, 'fonts' );

		if ( ! noobrocket_mkdir_p( dirname( $css_filepath ) ) ) {
			return false;
		}

		$start_time = microtime( true );

		$css_content = $this->get_remote_content( html_entity_decode( $css_url ) );

		if ( ! $css_content ) {
			set_transient( $cooldown, 1, 300 );
			return false;
		}

		preg_match_all( '/url\(\s*["\']?(https:\/\/[^)"\'\s]+)["\']?\s*\)/i', $css_content, $matches );
		$font_urls = array_unique( $matches[1] );
		$local_css = $css_content;

		$count_fonts      = 0;
		$download_average = 0;
		$complete         = true;

		foreach ( $font_urls as $font_url ) {
			$font_path = wp_parse_url( $font_url, PHP_URL_PATH );

			if ( ! $font_path ) {
				$complete = false;
				continue;
			}
			$extension = strtolower( pathinfo( $font_path, PATHINFO_EXTENSION ) );
			if ( ! in_array( $extension, [ 'woff', 'woff2', 'ttf', 'otf', 'eot' ], true ) ) {
				$complete = false;
				continue;
			}
			// A remote stylesheet cannot choose a filesystem path. Full URL identity
			// also keeps differing font hosts/query variants from sharing wrong bytes.
			$font_path = '/' . $this->hash_url( $font_url ) . '.' . $extension;

			$local_path = $fonts_basepath . $font_path;
			$local_dir  = dirname( $local_path );

			if ( ! noobrocket_mkdir_p( $local_dir ) ) {
				$complete = false;
				continue;
			}

			if ( ! $this->filesystem->is_readable( $local_path ) || ! $this->filesystem->size( $local_path ) ) {
				$download_start = microtime( true );

				$font_content = $this->get_remote_content( $font_url );

				if ( ! $font_content ) {
					$complete = false;
					Logger::debug( 'Font download was not successful', [ 'Host Fonts Locally' ] );
					continue;
				}

				if ( ! $this->publish_file( $local_path, $font_content )
					|| ! $this->filesystem->is_readable( $local_path )
					|| ! $this->filesystem->size( $local_path ) ) {
					$complete = false;
					continue;
				}

				$download_end  = microtime( true );
				$download_time = $download_end - $download_start;

				$download_average += $download_time;

				++$count_fonts;

				Logger::debug( "Font $font_url download duration -- $download_time", [ 'Host Fonts Locally' ] );
			}

			$local_url = content_url( $this->get_fonts_relative_path( $font_provider_path, $font_path ) );
			$local_css = str_replace( $font_url, $local_url, $local_css );
		}

		if ( ! $complete ) {
			// Keep the original provider CSS active until every referenced font is usable.
			set_transient( $cooldown, 1, 300 );
			return false;
		}

		// This filter is documented in inc/Engine/Optimization/CSSTrait.php.
		$local_css = wpm_apply_filters_typed( 'string', 'noobrocket_css_content', $local_css );

		$end_time = microtime( true );
		$duration = $end_time - $start_time;

		// Add for test purpose.
		Logger::debug( "Font download and optimization duration in seconds -- $duration", [ 'Host Fonts Locally' ] );
		Logger::debug( "Number of fonts downloaded for $css_url -- $count_fonts", [ 'Host Fonts Locally' ] );
		Logger::debug( 'Average download time per font -- ' . ( $count_fonts ? $download_average / $count_fonts : 0 ), [ 'Host Fonts Locally' ] );

		$written = $this->publish_file( $css_filepath, $local_css );
		if ( ! $written ) {
			set_transient( $cooldown, 1, 300 );
		}
		return $written;
	}

	/** Only expose complete font/CSS bytes; another completed publisher can win. */
	private function publish_file( string $path, string $content ): bool {
		$temp = $path . '.' . wp_generate_uuid4() . '.tmp';
		try {
			if ( ! $this->write_file( $temp, $content ) || ! $this->filesystem->is_readable( $temp ) || $this->filesystem->size( $temp ) !== strlen( $content ) ) {
				return false;
			}
			if ( $this->filesystem->exists( $path ) && ! $this->exists( $path ) ) {
				$this->delete_file( $path );
			}
			// WP_Filesystem_Direct::move() can fall back to a visible partial copy.
			// Both paths share a directory: publication must be a complete rename.
			return $this->exists( $path ) || @rename( $temp, $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Failure keeps original font delivery.
		} finally {
			$this->delete_file( $temp );
		}
	}

	/**
	 * Gets the remote content of the URL
	 *
	 * @param string $url URL to request content for.
	 *
	 * @return string
	 */
	private function get_remote_content( string $url ): string {
		$remaining = $this->download_deadline - microtime( true );
		if ( $remaining <= 0 ) {
			return '';
		}
		$response = wp_safe_remote_get(
			$url,
			[
				'timeout'     => min( 2, $remaining ),
				'limit_response_size' => 2 * 1024 * 1024,
				'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
				'httpversion' => '2.0',
			]
		);

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return '';
		}

		$body = wp_remote_retrieve_body( $response );
		return strlen( $body ) < 2 * 1024 * 1024 ? $body : '';
	}

	/**
	 * Get the absolute path for a file
	 *
	 * @param string $font_provider_path Font provider path.
	 * @param string $path               Path to the file.
	 *
	 * @return string
	 */
	private function get_absolute_path( string $font_provider_path, string $path ): string {
		return $this->path . $font_provider_path . $path;
	}

	/**
	 * Get the fonts relative paths
	 *
	 * @param string $font_provider_path Font provider path.
	 * @param string $path               Path to the file.
	 *
	 * @return string
	 */
	private function get_fonts_relative_path( string $font_provider_path, string $path ): string {
		$base_path      = $this->path . $font_provider_path . 'fonts';
		$wp_content_dir = noobrocket_get_constant( 'WP_CONTENT_DIR', '' );
		$relative_path  = str_replace( $wp_content_dir, '', $base_path );

		return $relative_path . $path;
	}

	/**
	 * Get the fonts provider path
	 *
	 * @param string $provider The font provider.
	 *
	 * @return string
	 */
	private function get_font_provider_path( string $provider ): string {
		$provider = str_replace( '_', '-', $provider );

		return $provider . '/';
	}

	/**
	 * Deletes the locally stored fonts for the corresponding url
	 *
	 * @since 3.18
	 *
	 * @param string $url The url of the page to be deleted.
	 *
	 * @return bool
	 */
	public function delete_font_css( string $url ): bool {
		$dir = $this->get_absolute_path( $this->get_font_provider_path( $url ), $url );

		return $this->delete_file( $dir );
	}
}
