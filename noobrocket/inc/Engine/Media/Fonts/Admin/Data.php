<?php
declare( strict_types=1 );

namespace NoobRocket\Engine\Media\Fonts\Admin;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use NoobRocket\Admin\Options_Data;
use NoobRocket\Engine\Common\Queue\AbstractASQueue;
use NoobRocket\Engine\Media\Fonts\Filesystem;
use NoobRocket\Engine\Media\Fonts\FontsTrait;
use NoobRocket\Logger\Logger;
use Exception;

class Data extends AbstractASQueue {
	use FontsTrait;

	/** @var Filesystem */
	private $filesystem;

	/** Font preparation uses the already registered Action Scheduler runner. */
	private const PREPARE_HOOK = 'noobrocket_prepare_local_font';

	/**
	 * Options data instance.
	 *
	 * @var Options_Data
	 */
	private $options;

	/**
	 * Base path.
	 *
	 * @var string
	 */
	private $base_path;

	/**
	 * Constructor.
	 *
	 * @param Options_Data $options Options data instance.
	 * @param Filesystem   $filesystem Local font publication owner.
	 */
	public function __construct( Options_Data $options, Filesystem $filesystem ) {
		$this->options    = $options;
		$this->filesystem = $filesystem;
		$this->base_path  = noobrocket_get_constant( 'NOOBROCKET_CACHE_ROOT_PATH', '' ) . 'fonts/' . get_current_blog_id() . '/';
	}

	/** Keep cold font downloads out of visitor requests; each waiting page is invalidated. */
	public function schedule_font_preparation( string $css_url, string $page_url ): void {
		$args = [ $css_url, $page_url ];
		if ( get_transient( 'noobrocket_font_failure_' . md5( $css_url ) ) || $this->is_scheduled( self::PREPARE_HOOK, $args ) ) {
			return;
		}
		try {
			if ( function_exists( 'as_enqueue_async_action' ) ) {
				as_enqueue_async_action( self::PREPARE_HOOK, $args, $this->group, true );
				return;
			}
			$this->schedule_single( time() + MINUTE_IN_SECONDS, self::PREPARE_HOOK, $args );
		} catch ( Exception $exception ) {
			Logger::error( $exception->getMessage(), [ 'Action Scheduler Queue' ] );
		}
	}

	/** Prepare a complete sheet in the background, preserving original delivery on failure. */
	public function prepare_font( string $css_url, string $page_url, int $attempt = 0 ): void {
		if ( ! $this->options->get( 'host_fonts_locally', 0 ) || $this->is_excluded( $css_url, $this->get_exclusions() ) ) {
			return;
		}
		$remote_url = html_entity_decode( $css_url, ENT_QUOTES );
		$remote_url = 0 === strpos( $remote_url, '//' ) ? 'https:' . $remote_url : $remote_url;
		$remote = wp_parse_url( $remote_url );
		$page = wp_parse_url( $page_url );
		$home = wp_parse_url( home_url() );
		$page_path = rawurldecode( $page['path'] ?? '/' );
		$home_path = rtrim( $home['path'] ?? '', '/' ) . '/';
		if ( ! is_array( $remote ) || ! in_array( $remote['scheme'] ?? '', [ 'http', 'https' ], true )
			|| 'fonts.googleapis.com' !== strtolower( $remote['host'] ?? '' )
			|| ! in_array( $remote['path'] ?? '', [ '/css', '/css2' ], true )
			|| ! is_array( $page ) || strtolower( $page['host'] ?? '' ) !== strtolower( $home['host'] ?? '' )
			|| ! in_array( $page['scheme'] ?? '', [ 'http', 'https' ], true )
			|| isset( $page['query'] ) || isset( $page['fragment'] )
			|| ( $page['port'] ?? null ) !== ( $home['port'] ?? null )
			|| 0 !== strpos( $page_path . '/', $home_path )
			|| preg_match( '#(?:^|/)\.\.?(/|$)|[\x00\\\\*]#', $page_path ) ) {
			return;
		}
		$path = $this->base_path . 'google-fonts/css/' . $this->filesystem->hash_to_path( md5( $css_url ) ) . '.css';
		if ( ! $this->filesystem->exists( $path ) && ! $this->filesystem->write_font_css( $css_url, 'google-fonts' ) ) {
			// Retry transient provider/write failures even when the cold HTML is cached.
			if ( $attempt < 2 ) {
				$this->schedule_single( time() + 5 * MINUTE_IN_SECONDS + 1, self::PREPARE_HOOK, [ $css_url, $page_url, $attempt + 1 ] );
			}
			return;
		}
		// A cached cold response still references the provider. Refresh only that page.
		do_action( 'noobrocket_font_css_prepared', $page_url );
		noobrocket_clean_files( [ $page_url ] );
	}

	/**
	 * Schedule data collection.
	 *
	 * @return void
	 */
	public function schedule_data_collection() {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$this->schedule_recurring( time(), WEEK_IN_SECONDS, 'noobrocket_fonts_data_collection' );
	}

	/**
	 * Unschedule data collection.
	 *
	 * @return void
	 */
	public function unschedule_data_collection() {
		$this->cancel( 'noobrocket_fonts_data_collection' );
		$this->cancel_all( self::PREPARE_HOOK );
	}

	/**
	 * Collect data.
	 *
	 * @return void
	 */
	public function collect_data() {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$fonts_data = get_transient( 'noobrocket_fonts_data_collection' );

		// If data has been populated, bail out early.
		if ( false !== $fonts_data ) {
			return;
		}

		try {
			$fonts = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $this->base_path . 'google-fonts/fonts/' ) );
		} catch ( Exception $exception ) {
			return;
		}

		$allowed_extensions = [
			'woff',
			'woff2',
			'ttf',
			'otf',
		];

		$total_font_count = 0;
		$total_font_size  = 0;

		foreach ( $fonts as $file ) {
			// check file is not a directory.
			if ( $file->isDir() ) {
				continue;
			}

			$extension = strtolower( pathinfo( $file->getFilename(), PATHINFO_EXTENSION ) );

			if ( in_array( $extension, $allowed_extensions, true ) ) {
				++$total_font_count;
				$total_font_size += $file->getSize();
			}
		}

		set_transient(
			'noobrocket_fonts_data_collection',
			[
				'fonts_total_number' => $total_font_count,
				'fonts_total_size'   => size_format( $total_font_size ),
			],
			WEEK_IN_SECONDS
		);
	}

	/**
	 * Check if the feature & analytics are enabled.
	 *
	 * @return bool
	 */
	private function is_enabled(): bool {
		return $this->options->get( 'host_fonts_locally', 0 ) && $this->options->get( 'analytics_enabled', 0 );
	}
}
