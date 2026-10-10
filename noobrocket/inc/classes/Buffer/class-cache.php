<?php
namespace NoobRocket\Buffer;

/**
 * Handle page cache.
 *
 * @since  3.3
 */
class Cache extends Abstract_Buffer {

	/**
	 * Legacy companion cookie prefix. This cookie never substitutes for native authentication.
	 *
	 * Defined here rather than in UserCacheKeySubscriber because this class also runs from
	 * advanced-cache.php, before WordPress and the Composer autoloader are loaded.
	 *
	 * @var string
	 */
	const USER_CACHE_COOKIE_PREFIX = 'noobrocket_ucc_';

	/**
	 * Process identifier used by the logger.
	 *
	 * @var    string
	 * @since  3.3
	 */
	protected $process_id = 'caching process';

	/**
	 * Tests instance
	 *
	 * @var Tests
	 */
	protected $tests;

	/**
	 * Config instance
	 *
	 * @var Config
	 */
	private $config;

	/**
	 * Path to the directory containing the cache files.
	 *
	 * @var    string
	 * @since  3.3
	 */
	private $cache_dir_path;

	/**
	 * Exclude urls from wp canonical redirect.
	 *
	 * @var array Array of url patterns to exclude from wp canonical redirect.
	 */
	private $wp_redirect_exclusions = [
		'(.*)wp\-json(/.*|$)',
	];

	/**
	 * Constructor.
	 *
	 * @since  3.3
	 *
	 * @param Tests  $tests Tests instance.
	 * @param Config $config Config instance.
	 * @param array  $args  {
	 *     An array of arguments.
	 *
	 *     @type string $cache_dir_path  Path to the directory containing the cache files.
	 * }
	 */
	public function __construct( Tests $tests, Config $config, array $args ) {
		$this->config         = $config;
		$this->cache_dir_path = rtrim( $args['cache_dir_path'], '/' ) . '/';

		parent::__construct( $tests );
	}

	/** ----------------------------------------------------------------------------------------- */
	/** CACHE =================================================================================== */
	/** ----------------------------------------------------------------------------------------- */

	/**
	 * Serve the cache file if it exists. If not, init the buffer.
	 *
	 * @since  3.3
	 */
	public function maybe_init_process() {
		if ( ! $this->tests->can_init_process() ) {
			if ( 'No config file found.' !== $this->tests->get_last_error()['message'] ) {
				$this->define_donotoptimize_true();
			}
			$this->log_last_test_error();
			return;
		}

		if ( $this->maybe_allow_wp_redirect() ) {
			return;
		}

		/**
		 * Serve the cache file if it exists.
		 */
		$cache_filepath = $this->get_cache_path();

		if ( '' === $cache_filepath ) {
			// Logged-in request that could not be verified: neither serve nor generate a cache file.
			$this->log( 'Logged-in request could not be verified: cache bypassed.' );
			return;
		}

		$this->log(
			'Looking for cache file.',
			[
				'path' => $cache_filepath,
			]
		);

		$cache_filepath_gzip = $cache_filepath . '_gzip';
		$accept_encoding     = $this->config->get_server_input( 'HTTP_ACCEPT_ENCODING' );
		$accept_gzip         = $this->accepts_gzip( (string) $accept_encoding );

		// Check if cache file exist.
		if ( $accept_gzip && $this->can_serve_cache_file( $cache_filepath_gzip ) ) {
			$this->serve_cache_file( $cache_filepath_gzip, true );
		}

		if ( $this->can_serve_cache_file( $cache_filepath ) ) {
			$this->serve_cache_file( $cache_filepath );
		}

		// Maybe we're looking for a webp file.
		$cache_filename = basename( $cache_filepath );

		if ( strpos( $cache_filename, '-webp' ) !== false ) {
			// We're looking for a webp file that doesn't exist: try to locate any `.no-webp` file.
			$cache_dir_path = rtrim( dirname( $cache_filepath ), '/\\' ) . DIRECTORY_SEPARATOR;

			if ( file_exists( $cache_dir_path . '.no-webp' ) ) {
				// We have a `.no-webp` file: try to deliver a non-webp cache file.
				$cache_filepath      = $cache_dir_path . str_replace( '-webp', '', $cache_filename );
				$cache_filepath_gzip = $cache_filepath . '_gzip';

				$this->log(
					'Looking for non-webp cache file.',
					[
						'path' => $cache_filepath,
					]
				);

				// Try to deliver the non-webp version instead.
				if ( $accept_gzip && $this->can_serve_cache_file( $cache_filepath_gzip ) ) {
					$this->serve_cache_file( $cache_filepath_gzip, true );
				}

				if ( $this->can_serve_cache_file( $cache_filepath ) ) {
					$this->serve_cache_file( $cache_filepath );
				}
			}
		}

		/**
		 * No cache file yet: launch caching process.
		 */
		$this->log(
			'Start buffer.',
			[
				'path' => $cache_filepath,
			]
		);

		ob_start( [ $this, 'maybe_process_buffer' ] );
	}

	/** Respect explicit gzip refusal, including when a wildcard also appears. */
	private function accepts_gzip( string $header ): bool {
		$wildcard = false;
		foreach ( explode( ',', strtolower( $header ) ) as $encoding ) {
			$parts = array_map( 'trim', explode( ';', $encoding ) );
			$coding = array_shift( $parts );
			if ( ! in_array( $coding, [ 'gzip', '*' ], true ) ) {
				continue;
			}
			$quality = 1.0;
			foreach ( $parts as $parameter ) {
				if ( preg_match( '/^q\s*=\s*(.*)$/', $parameter, $matches ) ) {
					$quality = is_numeric( $matches[1] ) ? (float) $matches[1] : 0.0;
				}
			}
			$accepted = $quality > 0 && $quality <= 1;
			if ( 'gzip' === $coding ) {
				return $accepted;
			}
			$wildcard = $accepted;
		}
		return $wildcard;
	}

	/**
	 * Bound stale delivery independently of cron and let queued refreshes regenerate it.
	 * All request exclusions have already run before this file is considered.
	 *
	 * @param string $cache_filepath Selected cache variant.
	 * @return bool
	 */
	private function can_serve_cache_file( $cache_filepath, $modified = null ) {
		if ( ! is_readable( $cache_filepath ) ) {
			return false;
		}
		if ( ! $this->config->get_config( 'cache_stale_refresh' ) ) {
			return true;
		}
		$lifespan = (int) $this->config->get_config( 'cache_lifespan' );
		if ( $lifespan <= 0 ) {
			return true;
		}
		$host = $this->config->get_host();
		if ( $this->config->get_config( 'url_no_dots' ) ) {
			$host = str_replace( '.', '_', $host );
		}
		$public_path = $this->cache_dir_path . $host . '/';
		$grace = 0 === strpos( $cache_filepath, $public_path )
			&& false === strpos( $cache_filepath, '#' )
			&& preg_match( '/^index(?:-mobile)?(?:-https)?\.html(?:_gzip)?$/', basename( $cache_filepath ) )
			? 3600 : 0;
		$modified = $modified ?? @filemtime( $cache_filepath ); // The file can disappear during a purge.
		if ( false === $modified || $modified <= time() - $lifespan - $grace ) {
			return false;
		}
		return ! ( '1' === $this->config->get_server_input( 'HTTP_X_NOOBROCKET_REFRESH' ) && $modified <= time() - $lifespan );
	}

	/**
	 * Serve a cache file.
	 *
	 * @since  3.3
	 *
	 * @param string $cache_filepath Path to the cache file.
	 */
	private function serve_cache_file( $cache_filepath, bool $gzip = false ) {
		$stream = @fopen( $cache_filepath, 'rb' );
		if ( ! $stream ) {
			return;
		}
		$stat = fstat( $stream );
		if ( ! $stat || $stat['size'] <= 0 || ! $this->can_serve_cache_file( $cache_filepath, $stat['mtime'] ) ) {
			fclose( $stream );
			return;
		}
		if ( $gzip && "\x1f\x8b" !== fread( $stream, 2 ) ) {
			fclose( $stream );
			return;
		}
		rewind( $stream );
		header( 'Cache-Status: NoobRocket; hit', false );
		header( 'Vary: Accept-Encoding', false );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', $stat['mtime'] ) . ' GMT' );

		$if_modified_since = $this->get_if_modified_since();
		if ( $if_modified_since && strtotime( $if_modified_since ) === $stat['mtime'] ) {
			fclose( $stream );
			header( $this->config->get_server_input( 'SERVER_PROTOCOL', '' ) . ' 304 Not Modified', true, 304 );
			header( 'Expires: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
			header( 'Cache-Control: no-cache, must-revalidate' );
			exit;
		}

		if ( $gzip ) {
			// Send the already compressed bytes. Do not inflate and recompress every cache hit.
			@ini_set( 'zlib.output_compression', '0' );
			if ( ini_get( 'zlib.output_compression' ) || in_array( 'ob_gzhandler', ob_list_handlers(), true ) ) {
				// An existing PHP compression handler owns delivery; give it plain bytes instead.
				stream_filter_append( $stream, 'zlib.inflate', STREAM_FILTER_READ, [ 'window' => 31 ] );
			} else {
				header( 'Content-Encoding: gzip' );
				header( 'Content-Length: ' . $stat['size'] );
			}
		}
		fpassthru( $stream );
		fclose( $stream );
		exit;
	}

	/**
	 * Maybe cache the page content.
	 *
	 * @since  3.3
	 *
	 * @param  string $buffer The buffer content.
	 * @return string         The buffered content.
	 */
	public function maybe_process_buffer( $buffer ) {
		if ( ! $this->tests->can_process_buffer( $buffer ) || ! $this->tests->can_cache_response() ) {
			$this->log_last_test_error();
			return $buffer;
		}

		$footprint = '';
		$is_html   = $this->is_html( $buffer );

		if ( ! static::can_generate_caching_files() ) {
			// Not allowed to generate cache files.
			if ( $is_html ) {
				$footprint = $this->get_noobrocket_footprint();
			}

			$this->log(
				'Page not cached by filter.',
				[
					'filter' => 'do_noobrocket_generate_caching_files',
				]
			);
			return $buffer . $footprint;
		}

		$webp_enabled   = preg_match( '@<!-- (?:NoobRocket|Rocket) (has|no) webp -->@', $buffer, $webp_tag );
		$has_webp       = ! empty( $webp_tag ) ? 'has' === $webp_tag[1] : false;
		$cache_filepath = $this->get_cache_path( [ 'webp' => $has_webp ] );

		if ( '' === $cache_filepath ) {
			// Logged-in request that could not be verified: never write it to any cache folder.
			return $buffer . ( $is_html ? $this->get_noobrocket_footprint() : '' );
		}

		$cache_dir_path = dirname( $cache_filepath );

		// Create cache folders.
		noobrocket_mkdir_p( $cache_dir_path );

		if ( $is_html ) {
			$footprint = $this->get_noobrocket_footprint( time() );
		}

		// Webp request.
		if ( $webp_enabled ) {
			$buffer = str_replace( $webp_tag[0], '', $buffer );

			if ( ! $has_webp ) {
				// The buffer doesn’t contain webp files.
				$cache_dir_path = rtrim( dirname( $cache_filepath ), '/\\' );

				$this->maybe_create_nowebp_file( $cache_dir_path );
			}
		}

		if ( ! $this->write_cache_file( $cache_filepath, $buffer . $footprint ) ) {
			$this->log( 'Cache file publication failed.', [ 'path' => $cache_filepath ] );
			return $buffer . ( $is_html ? $this->get_noobrocket_footprint() : '' );
		}
		do_action( 'noobrocket_cache_file_published', $cache_filepath );
		$this->maybe_create_nginx_mobile_file( $cache_dir_path );

		// Send headers with the last modified time of the cache file.
		if ( file_exists( $cache_filepath ) ) {
			header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', filemtime( $cache_filepath ) ) . ' GMT' );
		}

		if ( $is_html ) {
			$footprint = $this->get_noobrocket_footprint();
		}

		$this->log(
			'Page cached.',
			[
				'path' => $cache_filepath,
			],
			'info'
		);

		return $buffer . $footprint;
	}

	/**
	 * Writes the cache file(s)
	 *
	 * @since 3.5
	 *
	 * @param string $cache_filepath Absolute path to the cache file.
	 * @param string $content Content to write in the cache file.
	 * @return bool Whether complete plain HTML was published. Gzip is optional.
	 */
	private function write_cache_file( $cache_filepath, $content ) {
		// Locks live outside host directories so TTL purges never unlink an active mutex.
		$lock_directory = $this->cache_dir_path . '.locks/';
		noobrocket_mkdir_p( $lock_directory );
		$lock = @fopen( $lock_directory . md5( $cache_filepath ) . '.lock', 'c' );
		if ( ! $lock ) {
			return false;
		}
		if ( ! flock( $lock, LOCK_EX | LOCK_NB ) ) {
			fclose( $lock );
			return false;
		}
		$temp_filepath = tempnam( dirname( $cache_filepath ), '.nbr-' );
		$temp_gzip_filepath = false;
		$gzip_filepath = $cache_filepath . '_gzip';
		$plain_published = false;
		$gzip_published = false;
		try {
			if ( ! $temp_filepath || ! noobrocket_put_content( $temp_filepath, $content ) || ! @rename( $temp_filepath, $cache_filepath ) ) {
				return false;
			}
			$plain_published = true;
			if ( ! function_exists( 'gzencode' ) ) {
				@unlink( $gzip_filepath );
				return true;
			}
			$level = (int) apply_filters( 'noobrocket_gzencode_level_compression', 6 );
			$compressed = gzencode( $content, max( -1, min( 9, $level ) ) );
			$temp_gzip_filepath = tempnam( dirname( $cache_filepath ), '.nbr-' );
			if ( ! $compressed || ! $temp_gzip_filepath || ! noobrocket_put_content( $temp_gzip_filepath, $compressed ) || ! @rename( $temp_gzip_filepath, $gzip_filepath ) ) {
				// Never serve a gzip version belonging to older HTML after a partial publication failure.
				@unlink( $gzip_filepath );
			} else {
				$gzip_published = true;
			}
			return true;
		} finally {
			if ( $plain_published && ! $gzip_published ) {
				@unlink( $gzip_filepath );
			}
			foreach ( [ $temp_filepath, $temp_gzip_filepath ] as $temporary ) {
				if ( $temporary && is_file( $temporary ) ) {
					@unlink( $temporary );
				}
			}
			flock( $lock, LOCK_UN );
			fclose( $lock );
		}
	}

	/**
	 * Get the path to the cache file.
	 *
	 * @since  3.3
	 *
	 * @param  array $args {
	 *     A list of arguments.
	 *
	 *     @type bool $webp Set to false to prevent adding the part related to webp.
	 * }
	 * @return string Empty string when the request must not be served from or written to the cache.
	 */
	public function get_cache_path( $args = [] ) {
		$args             = array_merge(
			[
				'webp' => true,
			],
			$args
		);
		$cookies          = $this->tests->get_cookies();
		$request_uri_path = $this->get_request_cache_path( $cookies );

		if ( '' === $request_uri_path ) {
			return '';
		}

		$filename = 'index';

		$filename = $this->maybe_mobile_filename( $filename );

		// Rename the caching filename for SSL URLs.
		if ( is_ssl() && $this->config->get_config( 'cache_ssl' ) ) {
			$filename .= '-https';
		}

		if ( $args['webp'] ) {
			$filename = $this->maybe_webp_filename( $filename );
		}

		$filename = $this->maybe_dynamic_cookies_filename( $filename, $cookies );

		// Ensure proper formatting of the path.
		$request_uri_path = preg_replace_callback( '/%[0-9A-F]{2}/', [ $this, 'reset_lowercase' ], $request_uri_path );
		// Directories in Windows can't contain question marks.
		$request_uri_path = str_replace( '?', '#', $request_uri_path );
		// Limit filename max length to 255 characters.
		$request_uri_path .= '/' . substr( $filename, 0, 250 ) . '.html';

		return $request_uri_path;
	}

	/** ----------------------------------------------------------------------------------------- */
	/** VARIOUS TOOLS =========================================================================== */
	/** ----------------------------------------------------------------------------------------- */
	/**
	 * Declares and sets value of constant preventing Optimizations.
	 *
	 * @since  3.3
	 */
	private function define_donotoptimize_true() {
		if ( ! defined( 'DONOTNOOBROCKETOPTIMIZE' ) ) {
			define( 'DONOTNOOBROCKETOPTIMIZE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
		}
	}

	/**
	 * Gets If-modified-since header value
	 *
	 * @since 3.3
	 * @return string
	 */
	private function get_if_modified_since() {
		if ( function_exists( 'apache_request_headers' ) ) {
			$headers = apache_request_headers();

			return isset( $headers['If-Modified-Since'] ) ? $headers['If-Modified-Since'] : '';
		}

		return $this->config->get_server_input( 'HTTP_IF_MODIFIED_SINCE', '' );
	}

	/**
	 * Get NoobRocket footprint
	 *
	 * @since 3.0.5 White label footprint if NOOBROCKET_WHITE_LABEL_FOOTPRINT is defined.
	 * @since 2.0
	 *
	 * @param int $time UNIX timestamp when the cache file was saved.
	 * @return string The footprint that will be printed
	 */
	private function get_noobrocket_footprint( $time = '' ) {
		$footprint = defined( 'NOOBROCKET_WHITE_LABEL_FOOTPRINT' ) ?
						"\n" . '<!-- Cached for great performance' :
						"\n" . '<!-- Performance optimized by ' . NOOBROCKET_PLUGIN_NAME;
		if ( ! empty( $time ) ) {
			$footprint .= ' - Debug: cached@' . $time;
		}
		$footprint .= ' -->';
		return $footprint;
	}

	/**
	 * Create a hidden empty file for mobile detection on NGINX with the Rocket NGINX configuration.
	 *
	 * @param string $cache_dir_path Path to the current cache directory.
	 * @return void
	 */
	private function maybe_create_nginx_mobile_file( $cache_dir_path ) {
		global $is_nginx;

		if ( ! $this->config->get_config( 'do_caching_mobile_files' ) ) {
			return;
		}

		if ( ! $is_nginx ) {
			return;
		}

		$nginx_mobile_detect = $cache_dir_path . '/.mobile-active';

		if ( noobrocket_direct_filesystem()->exists( $nginx_mobile_detect ) ) {
			return;
		}

		noobrocket_direct_filesystem()->touch( $nginx_mobile_detect );
	}

	/**
	 * Create a hidden empty file when webp is enabled but the buffer doesn’t contain webp files.
	 *
	 * @since  3.4
	 *
	 * @param string $cache_dir_path Path to the current cache directory (without trailing slah).
	 */
	private function maybe_create_nowebp_file( $cache_dir_path ) {
		$nowebp_filepath = $cache_dir_path . DIRECTORY_SEPARATOR . '.no-webp';

		if ( noobrocket_direct_filesystem()->exists( $nowebp_filepath ) ) {
			return;
		}

		noobrocket_direct_filesystem()->touch( $nowebp_filepath );
	}

	/**
	 * Tell if generating cache files is allowed.
	 *
	 * @since  3.3
	 *
	 * @return bool
	 */
	public static function can_generate_caching_files() {
		/**
		 * Allow to the generate the caching file.
		 *
		 * @since 2.5
		 *
		 * @param bool True will force the cache file generation.
		 */
		return (bool) apply_filters( 'do_noobrocket_generate_caching_files', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
	}

	/**
	 * Gets the base cache path for the current request
	 *
	 * @since 3.3
	 *
	 * @param array $cookies Cookies for the current request.
	 * @return string Empty string when the request carries a logged-in cookie that could not be verified.
	 */
	private function get_request_cache_path( $cookies ) {
		$host = $this->config->get_host();

		if ( $this->config->get_config( 'url_no_dots' ) ) {
			$host = str_replace( '.', '_', $host );
		}

		$request_uri              = $this->tests->get_clean_request_uri();
		$cookie_hash              = $this->config->get_config( 'cookie_hash' );
		$logged_in_cookie         = $this->config->get_config( 'logged_in_cookie' );
		$logged_in_cookie_no_hash = str_replace( $cookie_hash, '', $logged_in_cookie );

		// Get cache folder of host name.
		if ( $logged_in_cookie && isset( $cookies[ $logged_in_cookie ] ) && ! $this->tests->has_rejected_cookie( $logged_in_cookie_no_hash ) ) {
			// advanced-cache.php runs before native authentication. Never trust a companion
			// cookie alone for private delivery, including copied or revoked sessions.
			if ( ! function_exists( 'wp_validate_auth_cookie' ) || ! function_exists( 'wp_get_current_user' ) ) {
				return '';
			}
			$user_id = wp_validate_auth_cookie( $cookies[ $logged_in_cookie ], 'logged_in' );
			$user = wp_get_current_user();
			if ( ! $user_id || (int) $user->ID !== (int) $user_id ) {
				return '';
			}
			$user_key = explode( '|', $cookies[ $logged_in_cookie ] );
			$user_key = reset( $user_key );

			if ( $user->user_login !== $user_key ) {
				// Could not confirm who this user is. WordPress may still render the page as logged in,
				// so it must not land in the public folder nor in any per-user folder: bypass the cache.
				return '';
			}

			if ( $this->config->get_config( 'common_cache_logged_users' ) ) {
				return $this->cache_dir_path . $host . '-loggedin-' . $this->config->get_config( 'secret_cache_key' ) . rtrim( $request_uri, '/' );
			}

			$user_key = $this->sanitize_user( $user_key ) . '-' . $this->config->get_config( 'secret_cache_key' );

			// Get cache folder of host name.
			return $this->cache_dir_path . $host . '-' . $user_key . rtrim( $request_uri, '/' );
		}

		return $this->cache_dir_path . $host . rtrim( $request_uri, '/' );
	}

	/**
	 * Gets the name of the companion cookie validating the per-user cache username.
	 *
	 * The name is site-specific: on multisite `COOKIEHASH` is shared network-wide while each site
	 * signs the cookie with its own secret cache key, so a shared name would make sites overwrite
	 * each other's cookie.
	 *
	 * @param string $cookie_hash Cookie hash (`COOKIEHASH`).
	 * @param string $secret      Secret cache key of the current site.
	 * @return string
	 */
	public static function get_user_cache_cookie_name( string $cookie_hash, string $secret ): string {
		return self::USER_CACHE_COOKIE_PREFIX . $cookie_hash . '_' . substr( hash_hmac( 'sha256', 'cookie_name', $secret ), 0, 12 );
	}

	/**
	 * Gets the value of the companion cookie for a username and expiration.
	 *
	 * @param string $username   Username segment of the logged-in cookie.
	 * @param int    $expiration The time the logged-in cookie expires as a UNIX timestamp.
	 * @param string $secret     Secret cache key of the current site.
	 * @return string
	 */
	public static function get_user_cache_cookie_value( string $username, int $expiration, string $secret ): string {
		return $expiration . '|' . hash_hmac( 'sha256', $username . '|' . $expiration, $secret );
	}

	/**
	 * Checks a companion cookie value against a username.
	 *
	 * @param string $value    Companion cookie value.
	 * @param string $username Username segment of the logged-in cookie.
	 * @param string $secret   Secret cache key of the current site.
	 * @return bool
	 */
	public static function is_valid_user_cache_cookie_value( string $value, string $username, string $secret ): bool {
		if ( '' === $username || '' === $secret ) {
			return false;
		}

		// Cookie value shape is "<expiration>|<hmac>", see get_user_cache_cookie_value().
		$parts = explode( '|', $value, 2 );

		if ( 2 !== count( $parts ) ) {
			return false;
		}

		list( $expiration, $mac ) = $parts;

		// Not ctype_digit(): this runs from advanced-cache.php and ctype is an optional PHP extension.
		// `\z` (not `$`) so a trailing newline is rejected too.
		if ( ! preg_match( '/^\d+\z/', $expiration ) || (int) $expiration <= time() ) {
			// Expired (or malformed, non-numeric expiration): do not trust it, even if the HMAC below
			// would otherwise match.
			return false;
		}

		return hash_equals( self::get_user_cache_cookie_value( $username, (int) $expiration, $secret ), $value );
	}

	/**
	 * Modifies the filename if the request is from a mobile device.
	 *
	 * @since 3.3
	 *
	 * @param string $filename Cache filename.
	 * @return string
	 */
	private function maybe_mobile_filename( $filename ) {
		$cache_mobile_files_tablet = $this->config->get_config( 'cache_mobile_files_tablet' );

		if ( ! ( $this->config->get_config( 'cache_mobile' ) && $this->config->get_config( 'do_caching_mobile_files' ) ) ) {
			return $filename;
		}

		if ( ! $cache_mobile_files_tablet ) {
			return $filename;
		}

		if ( ! class_exists( 'NoobRocket\Dependencies\Detection\MobileDetect' ) ) {
			return $filename;
		}

		$detect = new \NoobRocket\Dependencies\Detection\MobileDetect();

		if (
			( $detect->isMobile() && ! $detect->isTablet() && 'desktop' === $cache_mobile_files_tablet )
			||
			(
				( $detect->isMobile() || $detect->isTablet() )
				&&
				'mobile' === $cache_mobile_files_tablet
			)
		) {
				return $filename .= '-mobile';
		}

		return $filename;
	}

	/**
	 * Modifies the filename if the request is WebP compatible
	 *
	 * @since 3.4
	 *
	 * @param string $filename Cache filename.
	 * @return string
	 */
	private function maybe_webp_filename( $filename ) {
		if ( ! $this->config->get_config( 'cache_webp' ) ) {
			return $filename;
		}

		/**
		 * Force NoobRocket to disable its webp cache.
		 *
		 * @since  3.4
		 *
		 * @param bool $disable_webp_cache Set to true to disable the webp cache.
		 */
		$disable_webp_cache = apply_filters( 'noobrocket_disable_webp_cache', false );

		if ( $disable_webp_cache ) {
			return $filename;
		}

		if ( ! $this->is_browser_webp_compatible() ) {
			return $filename;
		}

		return $filename . '-webp';
	}

	/**
	 * Checks if the browser is WebP compatible
	 *
	 * @since 3.12.6
	 *
	 * @return bool
	 */
	private function is_browser_webp_compatible(): bool {
		// Only to supporting browsers.
		$http_accept = $this->config->get_server_input( 'HTTP_ACCEPT', '' );

		if (
			empty( $http_accept )
			&&
			function_exists( 'apache_request_headers' )
		) {
			$headers     = apache_request_headers();
			$http_accept = isset( $headers['Accept'] ) ? $headers['Accept'] : '';
		}

		if (
			! empty( $http_accept )
			&&
			false !== strpos( $http_accept, 'webp' )
		) {
			return true;
		}

		return $this->is_user_agent_compatible();
	}

	/**
	 * Check the User Agent if the Accept headers is missing the WebP info
	 *
	 * @since 3.12.6
	 *
	 * @return bool
	 */
	private function is_user_agent_compatible(): bool {
		$user_agent = $this->config->get_server_input( 'HTTP_USER_AGENT' );

		if ( empty( $user_agent ) ) {
			return false;
		}

		if ( preg_match( '#Firefox/(?<version>[0-9]{2,})#i', $user_agent, $matches ) ) {
			if ( 66 >= (int) $matches['version'] ) {
				return false;
			}
		}

		if ( preg_match( '#(?:iPad|iPhone)(.*)Version/(?<version>[0-9]{2,})#i', $user_agent, $matches ) ) {
			if ( 14 > (int) $matches['version'] ) {
				return false;
			}

			return true;
		}

		if ( preg_match( '#Version/(?<version>[0-9]{2,})(?:.*)Safari#i', $user_agent, $matches ) ) {
			if ( 16 > (int) $matches['version'] ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Modifies the filename if dynamic cookies are set
	 *
	 * @param string $filename Cache filename.
	 * @param array  $cookies  Cookies for the request.
	 * @return string
	 */
	private function maybe_dynamic_cookies_filename( $filename, $cookies ) {
		$cache_dynamic_cookies = $this->config->get_config( 'cache_dynamic_cookies' );

		if ( ! $cache_dynamic_cookies ) {
			return $filename;
		}

		foreach ( $cache_dynamic_cookies as $key => $cookie_name ) {
			if ( is_array( $cookie_name ) ) {
				if ( isset( $_COOKIE[ $key ] ) ) {
					foreach ( $cookie_name as $cookie_key ) {
						if ( '' !== $cookies[ $key ][ $cookie_key ] ) {
							$cache_key = $cookies[ $key ][ $cookie_key ];
							$cache_key = preg_replace( '/[^a-z0-9_\-]/i', '-', $cache_key );
							$filename .= '-' . $cache_key;
						}
					}
				}
				continue;
			}

			if ( isset( $cookies[ $cookie_name ] ) && '' !== $cookies[ $cookie_name ] ) {
				$cache_key = $cookies[ $cookie_name ];
				$cache_key = preg_replace( '/[^a-z0-9_\-]/i', '-', $cache_key );
				$filename .= '-' . $cache_key;
			}
		}

		return $filename;
	}

	/**
	 * Force lowercase on encoded url strings from different alphabets to prevent issues on some hostings.
	 *
	 * @since  3.3
	 *
	 * @param  array $matches Cache path.
	 * @return string         Cache path in lowercase.
	 */
	protected function reset_lowercase( $matches ) {
		return strtolower( $matches[0] );
	}

	/**
	 * Sanitizes a string username.
	 *
	 * @param string $user String username.
	 *
	 * @return string
	 */
	private function sanitize_user( string $user = '' ): string {
		return strtolower( rawurlencode( $user ) );
	}

	/**
	 * Check if permalink structure and url match.
	 *
	 * @return bool
	 */
	private function maybe_allow_wp_redirect(): bool {

		$exclusions = implode( '|', $this->wp_redirect_exclusions );

		// Return early for excluded urls.
		if ( preg_match( '#' . $exclusions . '#', $this->tests->get_request_uri_base() )
		) {
			return false;
		}

		$permalink_structure = $this->config->get_config( 'permalink_structure' );

		// Last character of permalink.
		$permalink_last_char = '/' !== substr( $permalink_structure, -1 ) ? '' : '/';

		// Request uri without protocol & domain name.
		$request_uri = $this->tests->get_request_uri_base();

		// Last character of request uri.
		$request_uri_last_char = '/' !== substr( $request_uri, -1 ) ? '' : '/';

		// In cases where we have the home with a trailng slash (visible or invisible)
		// and permalink is without trailing slash.
		if ( '' === $permalink_last_char ) {
			// Check for root installation.
			$request_uri_last_char = '/' === $request_uri ? '' : $request_uri_last_char;

			/**
			 * Check for subdir installation.
			 * Use config file name to get home request_uri.
			 */
			$home = str_replace( $this->config->get_host(), '', basename( $this->config->get_config_file_path()['path'] ) );
			$home = str_replace( '.', '/', str_replace( '.php', '', $home ) );

			if ( '/' !== $request_uri && rtrim( $request_uri, '/' ) === $home ) {
				$request_uri_last_char = '';
			}
		}

		return $permalink_last_char !== $request_uri_last_char;
	}
}
