<?php

use NoobRocket\Logger\Logger;
use NoobRocket\Engine\Cache\AdvancedCache;

defined( 'ABSPATH' ) || exit;

/**
 * Creates the advanced-cache.php file.
 *
 * @since 3.6 Uses AdvancedCache::get_advanced_cache_content().
 * @since 2.0
 *
 * @param AdvancedCache $advanced_cache Optional. Instance of the advanced cache handler.
 */
function noobrocket_generate_advanced_cache_file( $advanced_cache = null ) {
	/**
	 * Filters whether to generate the advanced-cache.php file.
	 *
	 * @since 3.6.3
	 *
	 * @param bool $generate True (default) to go ahead with advanced cache file generation; false to stop generation.
	 */
	if ( ! (bool) apply_filters( 'noobrocket_generate_advanced_cache_file', true ) ) {
		return false;
	}

	static $done = false;

	if ( noobrocket_get_constant( 'NOOBROCKET_IS_TESTING', false ) ) {
		$done = false;
	}

	if ( $done ) {
		return false;
	}
	$done = true;

	if ( is_null( $advanced_cache ) ) {
		$container      = apply_filters( 'noobrocket_container', null );
		$advanced_cache = $container->get( 'advanced_cache' );
	}

	return noobrocket_put_php_content(
		noobrocket_get_constant( 'WP_CONTENT_DIR' ) . '/advanced-cache.php',
		$advanced_cache->get_advanced_cache_content()
	);
}

/**
 * Generates the configuration file for the current domain based on the values of options
 *
 * @since 2.0
 *
 * @return array Names of all config files & The content that will be printed
 */
function noobrocket_get_cache_lifespan( array $options ): int {
	$lifespan = $options['purge_cron_interval'] ?? 0;
	if ( ! $lifespan ) {
		return 0;
	}
	$unit = $options['purge_cron_unit'] ?? '';
	if ( $lifespan < 0 || ! $unit || ! defined( $unit ) ) {
		return 10 * HOUR_IN_SECONDS;
	}
	return (int) ( $lifespan * constant( $unit ) );
}

/**
 * Only public canonical cache variants can use the bounded refresh queue.
 *
 * @param array $options Local cache settings.
 * @return bool
 */
function noobrocket_is_stale_refresh_enabled( array $options ): bool {
	return ! empty( $options['cache_stale_refresh'] )
		&& ! empty( $options['manual_preload'] )
		&& empty( $options['cache_webp'] )
		&& noobrocket_get_cache_lifespan( $options ) > 0
		&& ! get_noobrocket_cache_mandatory_cookies()
		&& ! get_noobrocket_cache_dynamic_cookies();
}

/**
 * Generates the current domain's early-cache configuration.
 *
 * @return array Config filenames and PHP contents.
 */
function get_noobrocket_config_file() { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
	$options = get_option( NOOBROCKET_SLUG );

	if ( ! $options ) {
		return [
			[],
			'',
		];
	}

	$buffer  = "<?php\n";
	$buffer .= "defined( 'ABSPATH' ) || exit;\n\n";

	$buffer .= '$noobrocket_cookie_hash = \'' . COOKIEHASH . "';\n";
	$buffer .= '$noobrocket_logged_in_cookie = \'' . LOGGED_IN_COOKIE . "';\n";
	$buffer .= '$noobrocket_cache_stale_refresh = ' . (int) noobrocket_is_stale_refresh_enabled( $options ) . ";\n";
	$buffer .= '$noobrocket_cache_lifespan = ' . noobrocket_get_cache_lifespan( $options ) . ";\n";

	/**
	 * Filters the activation of the common cache for logged-in users.
	 *
	 * @since 2.10
	 * @author Remy Perona
	 *
	 * @param bool $enabled True to activate the common cache, false to ignore.
	 */
	if ( apply_filters( 'noobrocket_common_cache_logged_users', false ) ) {
		$buffer .= '$noobrocket_common_cache_logged_users = 1;' . "\n";
	}

	if ( ! empty( $options['cache_webp'] ) ) {
		/** This filter is documented in inc/classes/buffer/class-cache.php */
		$disable_webp_cache = apply_filters( 'noobrocket_disable_webp_cache', false );

		if ( $disable_webp_cache ) {
			$options['cache_webp'] = 0;
		}
	}

	/**
	 * Filters the use of the mobile cache version for tablets
	 * 'desktop' will serve desktop to tablets, 'mobile' will serve mobile to tablets
	 *
	 * @since 3.2
	 * @author Remy Perona
	 *
	 * @param string $tablet_version valid values are 'mobile' or 'desktop'
	 */
	$buffer .= '$noobrocket_cache_mobile_files_tablet = \'' . apply_filters( 'noobrocket_cache_mobile_files_tablet', 'desktop' ) . "';\n";

	foreach ( $options as $option => $value ) {
		if ( 'cache_ssl' === $option ) {
			if ( 1 !== (int) $value ) {
				if ( noobrocket_is_ssl_website() ) {
					update_noobrocket_option( 'cache_ssl', 1 );
					$value = 1;
				}
			}

			$buffer .= '$noobrocket_' . $option . ' = ' . (int) $value . ";\n";
		}

		if ( 'cache_mobile' === $option || 'do_caching_mobile_files' === $option || 'cache_webp' === $option ) {
			$buffer .= '$noobrocket_' . $option . ' = ' . (int) $value . ";\n";
		}

		if ( 'secret_cache_key' === $option ) {
			$buffer .= '$noobrocket_' . $option . ' = \'' . sanitize_key( $value ) . "';\n";
		}

		if ( 'cache_reject_uri' === $option ) {
			$buffer .= '$noobrocket_' . $option . ' = \'' . get_noobrocket_cache_reject_uri( true ) . "';\n";
		}

		if ( 'cache_query_strings' === $option ) {
			$buffer .= '$noobrocket_' . $option . ' = ' . call_user_func( 'var_export', get_noobrocket_cache_query_string(), true ) . ";\n";
		}

		if ( 'cache_reject_cookies' === $option ) {
			$cookies = get_noobrocket_cache_reject_cookies();

			if ( $cookies && get_noobrocket_option( 'cache_logged_user' ) ) {
				// Make sure the "logged-in cookies" are not rejected.
				$logged_in_cookie = explode( COOKIEHASH, LOGGED_IN_COOKIE );
				$logged_in_cookie = array_map( 'preg_quote', $logged_in_cookie );
				$logged_in_cookie = implode( '[^|]*', $logged_in_cookie );
				$cookies          = preg_replace( '/\|' . $logged_in_cookie . '\|/', '|', '|' . $cookies . '|' );
				$cookies          = trim( $cookies, '|' );
			}

			$buffer .= '$noobrocket_' . $option . ' = \'' . $cookies . "';\n";
		}

		if ( 'cache_reject_ua' === $option ) {
			$buffer .= '$noobrocket_' . $option . ' = \'' . get_noobrocket_cache_reject_ua() . "';\n";
		}
	}

	$buffer .= '$noobrocket_cache_ignored_parameters = ' . call_user_func( 'var_export', noobrocket_get_ignored_parameters(), true ) . ";\n";
	$buffer .= '$noobrocket_cache_mandatory_cookies = ' . call_user_func( 'var_export', get_noobrocket_cache_mandatory_cookies(), true ) . ";\n";

	$buffer .= '$noobrocket_cache_dynamic_cookies = ' . call_user_func( 'var_export', get_noobrocket_cache_dynamic_cookies(), true ) . ";\n";

	$buffer .= '$noobrocket_permalink_structure = \'' . wp_slash( get_option( 'permalink_structure' ) ) . "';\n";

	/** This filter is documented in inc/front/htaccess.php */
	if ( apply_filters( 'noobrocket_url_no_dots', false ) ) {
		$buffer .= '$noobrocket_url_no_dots = 1;';
	}

	$config_files_path = [];
	$urls              = [ noobrocket_get_home_url() ];

	// Check if a translation plugin is activated and this configuration is in subdomain.
	$subdomains = get_noobrocket_i18n_subdomains();

	if ( $subdomains ) {
		$urls = $subdomains;
	}

	foreach ( $urls as $url ) {
		$file                = get_noobrocket_parse_url( untrailingslashit( $url ) );
		$file['path']        = ( ! empty( $file['path'] ) ) ? str_replace( '/', '.', untrailingslashit( $file['path'] ) ) : '';
		$config_files_path[] = NOOBROCKET_CONFIG_PATH . strtolower( $file['host'] ) . $file['path'] . '.php';
	}

	/**
	 * Filter all config files path
	 *
	 * @since 2.6.5
	 *
	 * @param array $config_files_path  Path of all config files.
	*/
	$config_files_path = apply_filters( 'noobrocket_config_files_path', $config_files_path );

	/**
	 * Filter the content of all config files
	 *
	 * @since 2.1
	 *
	 * @param string $buffer The content that will be printed.
	 * @param array $config_files_path  Names of all config files.
	*/
	$buffer = apply_filters( 'noobrocket_config_file', $buffer, $config_files_path );
	$buffer = preg_replace( '@array\s+\(@i', 'array(', $buffer );
	$buffer = preg_replace( '@array\(\s+\)@i', 'array()', $buffer );
	// Written last: an empty or incomplete executable policy must never use defaults.
	$buffer .= "\n\$noobrocket_config_complete = true;\n";

	return [ $config_files_path, $buffer ];
}

/**
 * Create the current config domain file
 * For example, if home_url() return example.com, the config domain file will be in /config/example.com
 *
 * @since 2.0
 *
 * @return void
 */
function noobrocket_generate_config_file() {
	list( $config_files_path, $buffer ) = get_noobrocket_config_file();

	if ( count( $config_files_path ) ) {
		noobrocket_init_config_dir();

		foreach ( $config_files_path as $file ) {
			noobrocket_put_php_content( $file, $buffer );
		}
	}
}

/**
 * Remove the current config domain file
 *
 * @since 2.6
 *
 * @return void
 */
function noobrocket_delete_config_file() {
	list( $config_files_path ) = get_noobrocket_config_file();
	foreach ( $config_files_path as $config_file ) {
		noobrocket_direct_filesystem()->delete( $config_file );
	}

	// Bail out if NoobRocket is multisite.
	if ( is_multisite() ) {
		return;
	}

	try {
		$config_dir = new FilesystemIterator( (string) noobrocket_get_constant( 'NOOBROCKET_CONFIG_PATH' ) );
	} catch ( Exception $e ) {
		return;
	}

	// Remove all files with php extension in the config folder.
	foreach ( $config_dir as $file ) {
		if ( ! $file->isFile() || 'php' !== strtolower( $file->getExtension() ) ) {
			continue;
		}

		if ( 1 === substr_count( $file->getFilename(), '.' ) ) {
			continue;
		}

		if ( false === strpos( noobrocket_direct_filesystem()->get_contents( $file->getPathname() ), '$noobrocket_cookie_hash' ) ) {
			continue;
		}

		noobrocket_direct_filesystem()->delete( $file->getPathname() );
	}
}

/**
 * Create all cache folders (noobrocket & min)
 *
 * @since 2.6
 *
 * @return void
 */
function noobrocket_init_cache_dir() {
	global $is_apache;

	$filesystem = noobrocket_direct_filesystem();

	// Create cache folder if not exist.
	if ( ! $filesystem->is_dir( NOOBROCKET_CACHE_PATH ) ) {
		noobrocket_mkdir_p( NOOBROCKET_CACHE_PATH );
	}

	if ( ! $filesystem->is_file( NOOBROCKET_CACHE_PATH . 'index.html' ) ) {
		$filesystem->touch( NOOBROCKET_CACHE_PATH . 'index.html' );
	}

	if ( $is_apache ) {
		$htaccess_path = NOOBROCKET_CACHE_PATH . '.htaccess';

		if ( ! $filesystem->is_file( $htaccess_path ) ) {
			$filesystem->touch( $htaccess_path );
			noobrocket_put_content( $htaccess_path, "<IfModule mod_autoindex.c>\nOptions -Indexes\n</IfModule>" );
		}
	}

	// Create minify cache folder if not exist.
	if ( ! $filesystem->is_dir( NOOBROCKET_MINIFY_CACHE_PATH ) ) {
		noobrocket_mkdir_p( NOOBROCKET_MINIFY_CACHE_PATH );
	}

	if ( ! $filesystem->is_file( NOOBROCKET_MINIFY_CACHE_PATH . 'index.html' ) ) {
		$filesystem->touch( NOOBROCKET_MINIFY_CACHE_PATH . 'index.html' );
	}

	// Create busting cache folder if not exist.
	if ( ! $filesystem->is_dir( NOOBROCKET_CACHE_BUSTING_PATH ) ) {
		noobrocket_mkdir_p( NOOBROCKET_CACHE_BUSTING_PATH );
	}

	if ( ! $filesystem->is_file( NOOBROCKET_CACHE_BUSTING_PATH . 'index.html' ) ) {
		$filesystem->touch( NOOBROCKET_CACHE_BUSTING_PATH . 'index.html' );
	}

	// Create critical CSS folder if not exist.
	if ( ! $filesystem->is_dir( NOOBROCKET_CRITICAL_CSS_PATH ) ) {
		noobrocket_mkdir_p( NOOBROCKET_CRITICAL_CSS_PATH );
	}

	if ( ! $filesystem->is_file( NOOBROCKET_CRITICAL_CSS_PATH . 'index.html' ) ) {
		$filesystem->touch( NOOBROCKET_CRITICAL_CSS_PATH . 'index.html' );
	}
}

/**
 * Create the config folder (noobrocket-config)
 *
 * @since 2.6
 *
 * @return void
 */
function noobrocket_init_config_dir() {
	$filesystem = noobrocket_direct_filesystem();

	// Create config domain folder if not exist.
	if ( ! $filesystem->is_dir( NOOBROCKET_CONFIG_PATH ) ) {
		noobrocket_mkdir_p( NOOBROCKET_CONFIG_PATH );
	}

	// Initialize the config directory with index.html to prevent indexing.
	if ( ! $filesystem->is_file( NOOBROCKET_CONFIG_PATH . 'index.html' ) ) {
		$filesystem->touch( NOOBROCKET_CONFIG_PATH . 'index.html' );
	}
}

/**
 * Delete all minify cache files.
 *
 * @since 3.5.3 Replaces glob.
 * @since 2.1
 *
 * @param string|array $extensions Optional. File extensions to minify. Default: js and css.
 */
function noobrocket_clean_minify( $extensions = [ 'js', 'css' ] ) {
	// Bails out if there are no extensions to target.
	if ( empty( $extensions ) ) {
		return;
	}

	if ( is_string( $extensions ) ) {
		$extensions = (array) $extensions;
	}

	$min_cache_path = noobrocket_get_constant( 'NOOBROCKET_MINIFY_CACHE_PATH' );
	$min_path       = $min_cache_path . get_current_blog_id() . '/';
	$iterator       = _noobrocket_get_cache_path_iterator( $min_path );

	if ( false === $iterator ) {
		return;
	}

	$filesystem = noobrocket_direct_filesystem();

	foreach ( $extensions as $ext ) {
		/**
		 * Fires before the minify cache files are deleted.
		 *
		 * @since 2.1
		 *
		 * @param string $ext File extensions to minify.
		 */
		do_action( 'before_noobrocket_clean_minify', $ext ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals

		foreach ( $iterator as $entry ) {
			if ( ! $entry->isFile() ) {
				continue;
			}
			$extension = $entry->getExtension();
			if ( 'gz' === $extension ) {
				$extension = pathinfo( $entry->getBasename( '.gz' ), PATHINFO_EXTENSION );
			}
			if ( $extension === $ext ) {
				$filesystem->delete( $entry->getPathname() );
			}
		}

		/**
		 * Fires after the minify cache files was deleted.
		 *
		 * @since 2.1
		 *
		 * @param string $ext File extensions to minify.
		 */
		do_action( 'after_noobrocket_clean_minify', $ext ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
	}

	// Delete any directories.
	foreach ( $iterator as $item ) {
		if ( $filesystem->is_dir( $item ) ) {
			$filesystem->delete( $item );
		}
	}

	// Clean the cache/min/3rd-party items.
	try {
		$files = new FilesystemIterator( "{$min_cache_path}3rd-party" );

		foreach ( $files as $file ) {
			if ( $filesystem->is_file( $file ) ) {
				$filesystem->delete( $file );
			}
		}
	} catch ( UnexpectedValueException $e ) {
		// No logging yet.
		return;
	}
}

/**
 * Delete all cache busting files.
 *
 * @since 2.9
 *
 * @param  string|array $extensions (default: array('js','css') File extensions to clean.
 * @return void
 */
function noobrocket_clean_cache_busting( $extensions = [ 'js', 'css' ] ) {
	if ( empty( $extensions ) ) {
		return;
	}

	if ( is_string( $extensions ) ) {
		$extensions = (array) $extensions;
	}

	$cache_busting_path = noobrocket_get_constant( 'NOOBROCKET_CACHE_BUSTING_PATH' ) . get_current_blog_id() . '/';
	$iterator           = _noobrocket_get_cache_path_iterator( $cache_busting_path );

	if ( false === $iterator ) {
		return;
	}

	$filesystem         = noobrocket_direct_filesystem();
	$busting_path_regex = str_replace( '/', '\/', $cache_busting_path );

	if ( ! noobrocket_direct_filesystem()->is_dir( $cache_busting_path ) ) {
		noobrocket_mkdir_p( $cache_busting_path );

		Logger::debug(
			'No Cache Busting folder found.',
			[
				'mkdir cache busting folder',
				'cache_busting_path' => $cache_busting_path,
			]
		);

		return;
	}

	foreach ( $extensions as $ext ) {
		/**
		 * Fires before the cache busting files are deleted
		 *
		 * @since 2.9
		 *
		 * @param string $ext File extensions to clean.
		*/
		do_action( 'before_noobrocket_clean_busting', $ext ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals

		try {
			$entries = new RegexIterator( $iterator, "/{$busting_path_regex}.*\.{$ext}/" );
		} catch ( Exception $e ) {
			return;
		}

		foreach ( $entries as $entry ) {
			$filesystem->delete( $entry->getPathname() );
		}

		/**
		 * Fires after the cache busting files was deleted
		 *
		 * @since 2.9
		 *
		 * @param string $ext File extensions to clean.
		*/
		do_action( 'after_noobrocket_clean_cache_busting', $ext ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
	}

	foreach ( $iterator as $item ) {
		if ( $filesystem->is_dir( $item ) ) {
			$filesystem->delete( $item );
		}
	}
}

/**
 * Returns the right path when the post is trashed.
 *
 * @param array $parsed_url current parsed url.
 * @param int   $post_id      ID from the post.
 *
 * @return array
 */
function noobrocket_maybe_find_right_trash_url( array $parsed_url, int $post_id ) {

	$post = get_post( $post_id );

	if ( ! $post || 'trash' !== $post->post_status ) {
		return $parsed_url;
	}

	$post->post_status = 'publish';

	$permalink = get_permalink( $post );

	if ( ! $permalink ) {
		return $parsed_url;
	}

	$new_permalink = str_replace( '__trashed', '', $permalink );
	return get_noobrocket_parse_url( $new_permalink );
}

/**
 * Delete one or several cache files.
 *
 * @since 3.5.5 Optimizes by grabbing root cache dirs once, bailing out when file/dir doesn't exist, & directly
 *        deleting files.
 * @since 3.5.4 Replaces glob and optimizes.
 * @since 2.0   Delete cache files for all users.
 * @since 1.1.0 Add filter noobrocket_clean_files.
 * @since 1.0
 *
 * @param string|array              $urls       URLs of cache files to be deleted.
 * @param WP_Filesystem_Direct|null $filesystem Optional. Instance of filesystem handler.
 * @param bool                      $run_actions Run actions.
 *
 * @return array Associative array of the given URLs and whether their cache was actually cleared.
 */
function noobrocket_clean_files( $urls, $filesystem = null, $run_actions = true ) {
	$urls = (array) $urls;
	if ( empty( $urls ) ) {
		return [];
	}

	$urls = array_filter( $urls );
	if ( empty( $urls ) ) {
		return [];
	}

	/** This filter is documented in inc/front/htaccess.php */
	$url_no_dots = (bool) apply_filters( 'noobrocket_url_no_dots', false );
	$cache_path  = _noobrocket_get_noobrocket_cache_path();

	if ( empty( $filesystem ) ) {
		$filesystem = noobrocket_direct_filesystem();
	}

	if ( $run_actions ) {
		/**
		 * Fires before all cache files are deleted.
		 *
		 * @since  3.2.2
		 *
		 * @param array $urls The URLs corresponding to the deleted cache files.
		 */
		do_action( 'before_noobrocket_clean_files', $urls ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
	}

	$results = [];

	foreach ( $urls as $url_key => $url ) {
		$original_url = $url;
		$cleared      = false;
		$failed       = false;

		if ( $run_actions ) {
			/**
			 * Fires before the cache file is deleted.
			 *
			 * @param string $url The URL that the cache file to be deleted.
			 * @since 1.0
			 */
			do_action( 'before_noobrocket_clean_file', $url ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
		}

		if ( $url_no_dots ) {
			$url = str_replace( '.', '_', $url );
		}

		$parsed_url = get_noobrocket_parse_url( $url );

		if ( ! empty( $parsed_url['host'] ) ) {
			// Decode once, so all public/user buckets use the same cache path.
			$parsed_url['path'] = rawurldecode( $parsed_url['path'] );

			// Encode Non-latin characters if found in url path.
			if ( false !== preg_match_all( '/(?<non_latin>[^\x00-\x7F]+)/', $parsed_url['path'], $matches ) ) {
				$cb_encode_non_latin = function ( $non_latin ) {
					return strtolower( rawurlencode( $non_latin ) );
				};

				$parsed_url['path'] = str_replace( $matches['non_latin'], array_map( $cb_encode_non_latin, $matches['non_latin'] ), $parsed_url['path'] );
			}

			foreach ( _noobrocket_get_cache_dirs( $parsed_url['host'], $cache_path ) as $dir ) {
				$entry = $dir . $parsed_url['path'];

				// For regex we use it for file names only, and it should include the * character.
				if ( str_contains( $entry, '*' ) ) {
					$regex_part    = basename( $entry );
					$search_dir    = dirname( $entry );
					if ( ! $filesystem->is_dir( $search_dir ) ) {
						continue;
					}
					if ( ! noobrocket_cache_path_is_within( $search_dir, $dir ) ) {
						$failed = true;
						continue;
					}
					$matched_files = _noobrocket_get_dir_files_by_regex( $search_dir, '#' . $regex_part . '#i' );
					foreach ( $matched_files as $item ) {
						$current_file = $item->getPath() . DIRECTORY_SEPARATOR . $item->getFilename();
						if ( $filesystem->exists( $current_file ) ) {
							if ( ! noobrocket_cache_path_is_within( $current_file, $dir ) || ! $filesystem->delete( $current_file ) ) {
								$failed = true;
							} else {
								$cleared = true;
							}
						}
					}
					// Remove the regex part from the url.
					$url              = str_replace( $regex_part, '', $url );
					$urls[ $url_key ] = $url;
				}

				// Skip if the dir/file does not exist.
				if ( ! $filesystem->exists( $entry ) ) {
					continue;
				}
				if ( ! noobrocket_cache_path_is_within( $entry, $dir ) ) {
					$failed = true;
					continue;
				}

				if ( $filesystem->is_dir( $entry ) ) {
					noobrocket_rrmdir( $entry, [], $filesystem );
					clearstatcache( true, $entry );
					$deleted = ! $filesystem->exists( $entry );
				} else {
					$deleted = $filesystem->delete( $entry );
				}

				if ( $deleted ) {
					$cleared = true;
				} else {
					$failed = true;
				}
			}
		}

		$results[ $original_url ] = $cleared && ! $failed;

		if ( $run_actions ) {
			/**
			 * Fires after the cache file is deleted.
			 *
			 * @param string $url The URL that the cache file was deleted.
			 * @since 1.0
			 */
			do_action( 'after_noobrocket_clean_file', $url ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
		}
	}

	if ( ! $run_actions ) {
		return $results;
	}

	/**
	 * Fires after all cache files are deleted.
	 *
	 * @since  3.2.2
	 *
	 * @param array $urls The URLs corresponding to the deleted cache files.
	 */
	do_action( 'after_noobrocket_clean_files', $urls ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals

	return $results;
}

/**
 * Checks resolved ownership before URL-derived cache deletion or wildcard scanning.
 *
 * @param string $path Candidate existing path.
 * @param string $root Selected host/user cache bucket.
 * @return bool
 */
function noobrocket_cache_path_is_within( string $path, string $root ): bool {
	if ( false !== strpos( $path, "\0" ) ) {
		return false;
	}
	clearstatcache( true, $path );
	$resolved_path = realpath( $path );
	$resolved_root = realpath( $root );
	if ( false === $resolved_path || false === $resolved_root ) {
		return false;
	}
	$resolved_path = wp_normalize_path( $resolved_path );
	$resolved_root = untrailingslashit( wp_normalize_path( $resolved_root ) );
	return $resolved_path === $resolved_root || 0 === strpos( $resolved_path, $resolved_root . '/' );
}

/**
 * Remove the home cache file and pagination
 *
 * $since 2.2 Add $lang argument
 *
 * @since 2.0 Delete cache files for all users
 * @since 1.0
 *
 * @param string $lang (default: '') The language code.
 * @return void
 */
function noobrocket_clean_home( $lang = '' ) {
	$parse_url = get_noobrocket_parse_url( get_noobrocket_i18n_home_url( $lang ) );

	/** This filter is documented in inc/front/htaccess.php */
	if ( apply_filters( 'noobrocket_url_no_dots', false ) ) {
		$parse_url['host'] = str_replace( '.', '_', $parse_url['host'] );
	}

	$root = NOOBROCKET_CACHE_PATH . $parse_url['host'] . '*' . untrailingslashit( $parse_url['path'] );

	/**
	 * Filter the homepage caching folder root
	 *
	 * @since 2.6.5
	 * @param string    $root The root that will be returned.
	 * @param string    $host The website host.
	 * @param string    $path The website path.
	*/
	$root = apply_filters( 'noobrocket_clean_home_root', $root, $parse_url['host'], $parse_url['path'] );

	/**
	 * Fires before the home cache file is deleted
	 *
	 * @since 1.0
	 *
	 * @param string $root The path of home cache file.
	 * @param string $lang The current lang to purge.
	*/
	do_action( 'before_noobrocket_clean_home', $root, $lang ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
	$root_pattern = $root;
	$owned_roots = array_map(
		static function ( $directory ) use ( $parse_url ) {
			return wp_normalize_path( $directory . untrailingslashit( $parse_url['path'] ) );
		},
		_noobrocket_get_cache_dirs( $parse_url['host'], NOOBROCKET_CACHE_PATH )
	);
	$roots = glob( $root_pattern, GLOB_ONLYDIR | GLOB_NOSORT ) ?: [];
	foreach ( $roots as $root ) {
		if ( ! in_array( wp_normalize_path( $root ), $owned_roots, true ) ) {
			continue;
		}

		// Delete homepage.
		$files = glob( $root . '/*', GLOB_NOSORT );

		if ( $files ) {
			foreach ( $files as $file ) {
				if ( preg_match( '#/index(?:-.+\.|\.)html(?:_gzip)?$#', $file ) ) {
					noobrocket_direct_filesystem()->delete( $file );
				}
			}
		}

		// Delete homepage pagination.
		$dirs = glob( $root . '/' . $GLOBALS['wp_rewrite']->pagination_base, GLOB_NOSORT );
		if ( $dirs ) {
			foreach ( $dirs as $dir ) {
				noobrocket_rrmdir( $dir );
			}
		}

		$param_dirs = glob( $root . '/#*', GLOB_NOSORT );

		if ( $param_dirs ) {
			foreach ( $param_dirs as $dir ) {
				noobrocket_rrmdir( $dir );
			}
		}

		// Remove the hidden empty file for mobile detection on NGINX with the Rocket NGINX configuration.
		$nginx_mobile_detect_files = glob( $root . '/.mobile-active', GLOB_NOSORT );
		if ( $nginx_mobile_detect_files ) {
			foreach ( $nginx_mobile_detect_files as $nginx_mobile_detect_file ) { // no array map to use @.
				noobrocket_direct_filesystem()->delete( $nginx_mobile_detect_file );
			}
		}

		// Remove the hidden empty file for webp.
		$nowebp_detect_files = glob( $root . '/.no-webp', GLOB_NOSORT );
		if ( $nowebp_detect_files ) {
			foreach ( $nowebp_detect_files as $nowebp_detect_file ) { // no array map to use @.
				noobrocket_direct_filesystem()->delete( $nowebp_detect_file );
			}
		}
	}

	/**
	 * Fires after the home cache file was deleted
	 *
	 * @since 1.0
	 *
	 * @param string $root The path of home cache file.
	 * @param string $lang The current lang to purge.
	*/
	do_action( 'after_noobrocket_clean_home', $root_pattern, $lang ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
}

/**
 * Remove the home cache feed
 *
 * @since 2.7
 *
 * @return void
 */
function noobrocket_clean_home_feeds() {

	if ( ! has_filter( 'noobrocket_cache_reject_uri', 'noobrocket_cache_feed' ) ) {
		return;
	}

	$urls   = [];
	$urls[] = get_feed_link();
	$urls[] = get_feed_link( 'comments_' );

	/**
	 * Filter the home feeds urls
	 *
	 * @since 2.7
	 * @param array     $urls The urls of the home feeds.
	*/
	$urls = apply_filters( 'noobrocket_clean_home_feeds', $urls );

	/**
	 * Fires before the home feeds cache is deleted
	 *
	 * @since 2.7
	 *
	 * @param array $urls The urls of the home feeds.
	*/
	do_action( 'before_noobrocket_clean_home_feeds', $urls ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals

	noobrocket_clean_files( $urls );

	/**
	 * Fires after the home feeds cache was deleted
	 *
	 * @since 2.7
	 *
	 * @param array $urls The urls of the home feeds.
	*/
	do_action( 'after_noobrocket_clean_home_feeds', $urls ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
}

/**
 * Remove all cache files for the domain.
 *
 * @since 3.5.5 Optimizes by grabbing root cache dirs once, bailing out when file/dir doesn't exist, & directly
 *        deleting files.
 * @since 3.5.3 Replaces glob with SPL.
 * @since 2.0   Delete domain cache files for all users
 * @since 1.0
 *
 * @param string                    $lang       Optional. The language code. Default: empty string.
 * @param WP_Filesystem_Direct|null $filesystem Optional. Instance of filesystem handler.
 */
function noobrocket_clean_domain( $lang = '', $filesystem = null ) {
	// This public helper is exposed before the remaining purge dependencies load.
	if ( ! function_exists( 'noobrocket_is_importing' ) || ! function_exists( 'get_noobrocket_i18n_uri' ) ) {
		return false;
	}

	if ( did_action( 'noobrocket_after_clean_domain' ) ) {
		return;
	}

	if ( noobrocket_is_importing() ) {
		return;
	}

	$urls = ( ! $lang || is_object( $lang ) || is_array( $lang ) || is_int( $lang ) )
		? (array) get_noobrocket_i18n_uri()
		: (array) get_noobrocket_i18n_home_url( $lang );
	/**
	 * Filter URLs to delete all caching files from a domain.
	 *
	 * @since 2.6.4
	 *
	 * @param array     $urls URLs that will be returned.
	 * @param string    $lang The language code.
	 */
	$urls = (array) apply_filters( 'noobrocket_clean_domain_urls', $urls, $lang );
	$urls = array_filter( $urls );
	if ( empty( $urls ) ) {
		return false;
	}

	/** This filter is documented in inc/front/htaccess.php */
	$url_no_dots      = (bool) apply_filters( 'noobrocket_url_no_dots', false );
	$cache_path       = _noobrocket_get_noobrocket_cache_path();
	$dirs_to_preserve = get_noobrocket_i18n_to_preserve( $lang, $cache_path );

	if ( empty( $filesystem ) ) {
		$filesystem = noobrocket_direct_filesystem();
	}

	foreach ( $urls as $url ) {
		$parsed_url = get_noobrocket_parse_url( $url );

		if ( $url_no_dots ) {
			$parsed_url['host'] = str_replace( '.', '_', $parsed_url['host'] );
		}

		$root = $cache_path . $parsed_url['host'] . $parsed_url['path'];

		/**
		 * Fires before all cache files are deleted.
		 *
		 * @since 1.0
		 *
		 * @param string $root The path of home cache file.
		 * @param string $lang The current lang to purge.
		 * @param string $url  The home url.
		 */
		do_action( 'before_noobrocket_clean_domain', $root, $lang, $url ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals

		foreach ( _noobrocket_get_cache_dirs( $parsed_url['host'], $cache_path ) as $dir ) {
			$entry = $dir . $parsed_url['path'];
			// Skip if the dir/file does not exist.
			if ( ! $filesystem->exists( $entry ) ) {
				continue;
			}

			if ( $filesystem->is_dir( $entry ) ) {
				noobrocket_rrmdir( $entry, $dirs_to_preserve, $filesystem );
			} else {
				$filesystem->delete( $entry );
			}
		}

		/**
		 * Fires after all cache files was deleted.
		 *
		 * @since 1.0
		 *
		 * @param string $root The path of home cache file.
		 * @param string $lang The current lang to purge.
		 * @param string $url  The home url.
		 */
		do_action( 'after_noobrocket_clean_domain', $root, $lang, $url ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
	}

	/**
	 * Fires after all cache files was deleted.
	 *
	 * @since 3.15.5
	 *
	 * @param string $lang The current lang to purge.
	 * @param array|string[] $urls  All urls to clean.
	 */
	do_action( 'noobrocket_after_clean_domain', $lang, $urls );

	return true;
}

/**
 * Delete the caching files of a specific term.
 *
 * $since 2.6.8
 *
 * @param  int    $term_id       The term ID.
 * @param  string $taxonomy_slug The taxonomy slug.
 * @return void
 */
function noobrocket_clean_term( $term_id, $taxonomy_slug ) {
	$purge_urls = [];

	// Get all term infos.
	$term = get_term_by( 'id', $term_id, $taxonomy_slug );

	// Get the term language.
	$i18n_plugin = noobrocket_has_i18n();

	if ( 'wpml' === $i18n_plugin && ! noobrocket_is_plugin_active( 'woocommerce-multilingual/wpml-woocommerce.php' ) ) {
		// WPML.
		$lang = $GLOBALS['sitepress']->get_language_for_element( $term_id, 'tax_' . $taxonomy_slug );
	} elseif ( 'polylang' === $i18n_plugin ) {
		// Polylang.
		$lang = pll_get_term_language( $term_id );
	} else {
		$lang = false;
	}

	// Get permalink.
	$permalink = get_term_link( $term, $taxonomy_slug );

	// Add permalink.
	if ( '/' !== noobrocket_extract_url_component( $permalink, PHP_URL_PATH ) ) {
		array_push( $purge_urls, $permalink );
	}

	/**
	 * Fires before deleted caching files related with the term
	 *
	 * @since 2.6.8
	 * @param WP_Term|false $term       The term object, or false when unavailable.
	 * @param array  $purge_urls URLs cache files to remove.
	 * @param string $lang       The term language.
	*/
	do_action( 'before_noobrocket_clean_term', $term, $purge_urls, $lang ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals

	/**
	 * Filter URLs cache files to remove
	 *
	 * @since 2.6.8
	 * @param array $purge_urls List of URLs cache files to remove.
	 * @param WP_Term|false $term       The term object, or false when unavailable.
	*/
	$purge_urls = apply_filters( 'noobrocket_term_purge_urls', $purge_urls, $term );

	// Purge all files.
	noobrocket_clean_files( $purge_urls );

	// Never forget to purge homepage and their pagination.
	noobrocket_clean_home( $lang );

	/**
	 * Fires before deleted caching files related with the term
	 *
	 * @since 2.6.8
	 * @param WP_Term|false $term       The term object, or false when unavailable.
	 * @param array  $purge_urls URLs cache files to remove.
	 * @param string $lang       The term language.
	*/
	do_action( 'after_noobrocket_clean_term', $term, $purge_urls, $lang ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
}

/**
 * Delete the caching files of a specific user
 *
 * $since 2.6.12
 *
 * @param int    $user_id  The user ID.
 * @param string $lang     The language code.
 * @return void
 */
function noobrocket_clean_user( $user_id, $lang = '' ) {
	$urls = ( ! $lang || is_object( $lang ) ) ? get_noobrocket_i18n_uri() : get_noobrocket_i18n_home_url( $lang );
	$urls = (array) $urls;

	/** This filter is documented in inc/functions/files.php */
	$urls = apply_filters( 'noobrocket_clean_domain_urls', $urls, $lang );
	$urls = array_filter( $urls );
	$user = get_user_by( 'id', $user_id );

	if ( ! $user ) {
		return;
	}

	$user_key = rawurlencode( $user->user_login ) . '-' . get_noobrocket_option( 'secret_cache_key' );

	foreach ( $urls as $url ) {
		$parse_url = get_noobrocket_parse_url( $url );

		/** This filter is documented in inc/front/htaccess.php */
		if ( apply_filters( 'noobrocket_url_no_dots', false ) ) {
			$parse_url['host'] = str_replace( '.', '_', $parse_url['host'] );
		}

		$cache_dir = $parse_url['host'] . '-' . strtolower( $user_key );
		$cache_dir = $cache_dir . $parse_url['path'];
		$root      = noobrocket_get_constant( 'NOOBROCKET_CACHE_PATH' ) . $cache_dir;

		/**
		 * Fires before all caching files are deleted for a specific user
		 *
		 * @since 2.6.12
		 *
		 * @param int     $user_id  The path of home cache file.
		 * @param string  $lang     The language code.
		*/
		do_action( 'before_noobrocket_clean_user', $user_id, $lang ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals

		// Delete cache domain files.
		$dirs = glob( $root . '*', GLOB_NOSORT );
		if ( $dirs ) {
			foreach ( $dirs as $dir ) {
				noobrocket_rrmdir( $dir, get_noobrocket_i18n_to_preserve( $lang ) );
			}
		}

		/**
		 * Fires after all caching files are deleted for a specific user
		 *
		 * @since 2.6.12
		 *
		 * @param int     $user_id  The path of home cache file.
		 * @param string  $lang     The language code.
		*/
		do_action( 'after_noobrocket_clean_user', $user_id, $lang ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
	}
}

/**
 * Remove all caching files in the cache folder
 *
 * @since 2.6.8
 *
 * @return void
 */
function noobrocket_clean_cache_dir() {
	/**
	 * Fires before deleting all caching files in the cache folder
	 *
	 * @since 2.6.8
	*/
	do_action( 'before_noobrocket_clean_cache_dir' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals

	// Delete all caching files.
	$dirs = glob( NOOBROCKET_CACHE_PATH . '*', GLOB_NOSORT );
	if ( $dirs ) {
		foreach ( $dirs as $dir ) {
			noobrocket_rrmdir( $dir );
		}
	}

	/**
	 * Fires after deleting all caching files in the cache folder
	 *
	 * @since 2.6.8
	*/
	do_action( 'after_noobrocket_clean_cache_dir' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
}

/**
 * Remove a single file or a folder recursively.
 *
 * @since 3.5.3 Replaces glob and optimizes.
 * @since 1.0
 * @since 3.5.3 Bails if given dir should be preserved; replaces glob; optimizes.
 *
 * @param string                    $dir              File/Directory to delete.
 * @param array                     $dirs_to_preserve Optional. Dirs that should not be deleted.
 * @param WP_Filesystem_Direct|null $filesystem       Optional. Instance of filesystem handler.
 */
function noobrocket_rrmdir( $dir, array $dirs_to_preserve = [], $filesystem = null ) {
	$dir = untrailingslashit( $dir );

	if ( empty( $filesystem ) ) {
		$filesystem = noobrocket_direct_filesystem();
	}

	/**
	 * Fires before a file/directory cache is deleted
	 *
	 * @since 1.1.0
	 *
	 * @param string $dir              File/Directory to delete.
	 * @param array  $dirs_to_preserve Directories that should not be deleted.
	 */
	do_action( 'before_noobrocket_rrmdir', $dir, $dirs_to_preserve ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals

	// A directory link is an entry to unlink, never a tree we own.
	if ( is_link( $dir ) ) {
		$filesystem->delete( $dir, false, 'f' );
		do_action( 'after_noobrocket_rrmdir', $dir, $dirs_to_preserve ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
		return;
	}

	// Remove the hidden empty file for mobile detection on NGINX with the Rocket NGINX configuration.
	$nginx_mobile_detect_file = $dir . '/.mobile-active';

	if ( $filesystem->is_dir( $dir ) && $filesystem->exists( $nginx_mobile_detect_file ) ) {
		$filesystem->delete( $nginx_mobile_detect_file );
	}

	// Remove the hidden empty file for webp.
	$nowebp_detect_file = $dir . '/.no-webp';

	if ( $filesystem->is_dir( $dir ) && $filesystem->exists( $nowebp_detect_file ) ) {
		$filesystem->delete( $nowebp_detect_file );
	}

	if ( ! $filesystem->is_dir( $dir ) ) {
		$filesystem->delete( $dir );

		return;
	}

	// Get the directory entries.
	$entries = [];
	try {
		foreach ( new FilesystemIterator( $dir ) as $entry ) {
			$entries[] = $entry->getPathname();
		}
	} catch ( Exception $e ) { // phpcs:disable Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		// No action required, as logging not enabled.
	}

	// Exclude directories to preserve from the entries.
	if ( ! empty( $dirs_to_preserve ) && ! empty( $entries ) ) {
		$keys = [];
		foreach ( $dirs_to_preserve as $dir_to_preserve ) {
			$matches = preg_grep( "#^$dir_to_preserve$#", $entries );
			$keys[]  = reset( $matches );
		}

		$keys = array_filter( $keys );
		if ( ! empty( $keys ) ) {
			$entries = array_diff( $entries, $keys );
		}
	}

	foreach ( $entries as $entry ) {
		// If not a directory, delete it.
		if ( is_link( $entry ) ) {
			noobrocket_rrmdir( $entry, $dirs_to_preserve, $filesystem );
		} elseif ( ! $filesystem->is_dir( $entry ) ) {
			$filesystem->delete( $entry );
		} else {
			noobrocket_rrmdir( $entry, $dirs_to_preserve, $filesystem );
		}
	}

	$filesystem->delete( $dir );

	/**
	 * Fires after a file/directory cache was deleted
	 *
	 * @since 1.1.0
	 *
	 * @param string $dir              File/Directory to delete.
	 * @param array  $dirs_to_preserve Dirs that should not be deleted.
	 */
	do_action( 'after_noobrocket_rrmdir', $dir, $dirs_to_preserve ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
}

/**
 * Instantiate the filesystem class
 *
 * @since 2.10
 *
 * @return WP_Filesystem_Direct WP_Filesystem_Direct instance
 */
function noobrocket_direct_filesystem() {
	require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
	return new WP_Filesystem_Direct( new StdClass() );
}

/**
 * Directory creation based on WordPress Filesystem
 *
 * @since 1.3.4
 *
 * @param string $dir The path of directory will be created.
 * @return bool
 */
function noobrocket_mkdir( $dir ) {
	$chmod = noobrocket_get_filesystem_perms( 'dir' );
	return noobrocket_direct_filesystem()->mkdir( $dir, $chmod );
}

/**
 * Recursive directory creation based on full path.
 *
 * @param string                    $target path to the directory we want to create.
 * @param WP_Filesystem_Direct|null $filesystem WordPress filesystem.
 * @return bool True if directory is created/exists, false otherwise
 * @since 1.3.4
 *
 * @source wp_mkdir_p() in /wp-includes/functions.php
 */
function noobrocket_mkdir_p( $target, $filesystem = null ) {
	$wrapper = null;

	$filesystem = $filesystem ?: noobrocket_direct_filesystem();

	if ( noobrocket_is_stream( $target ) ) {
		list( $wrapper, $target ) = explode( '://', $target, 2 );
	}

	// from php.net/mkdir user contributed notes.
	$target = str_replace( '//', '/', $target );

	// Put the wrapper back on the target.
	if ( null !== $wrapper ) {
		$target = $wrapper . '://' . $target;
	}

	// safe mode fails with a trailing slash under certain PHP versions.
	$target = rtrim( $target, '/\\' );
	if ( empty( $target ) ) {
		$target = '/';
	}

	if ( $filesystem->exists( $target ) ) {
		return $filesystem->is_dir( $target );
	}

	// Attempting to create the directory may clutter up our display.
	if ( noobrocket_mkdir( $target ) ) {
		return true;
	} elseif ( $filesystem->is_dir( dirname( $target ) ) ) {
		return false;
	}

	// If the above failed, attempt to create the parent node, then try again.
	if ( ( '/' !== $target ) && ( noobrocket_mkdir_p( dirname( $target ), $filesystem ) ) ) {
		return noobrocket_mkdir_p( $target, $filesystem );
	}

	return false;
}

/**
 * Test if a given path is a stream URL.
 *
 * @since 3.5.3
 *
 * @source wp_is_stream() in /wp-includes/functions.php
 *
 * @param string $path The resource path or URL.
 *
 * @return bool true if the path is a stream URL; else false.
 */
function noobrocket_is_stream( $path ) {
	$scheme_separator = strpos( $path, '://' );

	if ( false === $scheme_separator ) {
		// $path isn't a stream.
		return false;
	}

	$stream = substr( $path, 0, $scheme_separator );

	return in_array( $stream, stream_get_wrappers(), true );
}

/**
 * File creation based on WordPress Filesystem.
 *
 * @since 1.3.5
 *
 * @param string $file    The path of file will be created.
 * @param string $content The content that will be printed in advanced-cache.php.
 *
 * @return bool true on success; else, false on failure.
 */
function noobrocket_put_content( $file, $content ) {
	$chmod = noobrocket_get_filesystem_perms( 'file' );
	return noobrocket_direct_filesystem()->put_contents( $file, $content, $chmod );
}

/**
 * Replace owned executable cache files without exposing truncated PHP to readers.
 *
 * @param string $file Target in an existing local directory.
 * @param string $content Complete generated PHP bytes.
 * @param object|null $filesystem Existing direct filesystem, if supplied by its owner.
 * @return bool
 */
function noobrocket_put_php_content( $file, $content, $filesystem = null ) {
	$temporary = @tempnam( dirname( $file ), '.nbr-php-' );
	if ( false === $temporary || realpath( dirname( $temporary ) ) !== realpath( dirname( $file ) ) ) {
		if ( $temporary ) {
			@unlink( $temporary );
		}
		return false;
	}
	$filesystem = $filesystem ?: noobrocket_direct_filesystem();
	try {
		if ( ! $filesystem->put_contents( $temporary, $content, noobrocket_get_filesystem_perms( 'file' ) )
			|| filesize( $temporary ) !== strlen( $content )
			|| ! @rename( $temporary, $file ) ) {
			return false;
		}
		clearstatcache( true, $file );
		if ( function_exists( 'opcache_invalidate' ) ) {
			@opcache_invalidate( $file, true );
		}
		return true;
	} finally {
		if ( is_file( $temporary ) ) {
			@unlink( $temporary );
		}
	}
}

/**
 * Get the permissions to apply to files and folders.
 *
 * Reminder:
 * `$perm = fileperms( $file );`
 *
 *  WHAT                                         | TYPE   | FILE   | FOLDER |
 * ----------------------------------------------+--------+--------+--------|
 * `$perm`                                       | int    | 33188  | 16877  |
 * `substr( decoct( $perm ), -4 )`               | string | '0644' | '0755' |
 * `substr( sprintf( '%o', $perm ), -4 )`        | string | '0644' | '0755' |
 * `$perm & 0777`                                | int    | 420    | 493    |
 * `decoct( $perm & 0777 )`                      | string | '644'  | '755'  |
 * `substr( sprintf( '%o', $perm & 0777 ), -4 )` | string | '644'  | '755'  |
 *
 * @since  3.2.4
 *
 * @param  string $type The type: 'dir' or 'file'.
 *
 * @return int          Octal integer.
 */
function noobrocket_get_filesystem_perms( $type ) {
	static $perms = [];

	if ( noobrocket_get_constant( 'NOOBROCKET_IS_TESTING', false ) ) {
		$perms = [];
	}

	// Allow variants.
	switch ( $type ) {
		case 'dir':
		case 'dirs':
		case 'folder':
		case 'folders':
			$type = 'dir';
			break;

		case 'file':
		case 'files':
			$type = 'file';
			break;

		default:
			return 0755;
	}

	if ( isset( $perms[ $type ] ) ) {
		return $perms[ $type ];
	}

	// If the constants are not defined, use fileperms() like WordPress does.
	if ( 'dir' === $type ) {
		$fs_chmod_dir   = (int) noobrocket_get_constant( 'FS_CHMOD_DIR', 0 );
		$perms[ $type ] = $fs_chmod_dir > 0
			? $fs_chmod_dir
			: fileperms( noobrocket_get_constant( 'ABSPATH' ) ) & 0777 | 0755;
	} else {
		$fs_chmod_file  = (int) noobrocket_get_constant( 'FS_CHMOD_FILE', 0 );
		$perms[ $type ] = $fs_chmod_file > 0
			? $fs_chmod_file
			: fileperms( noobrocket_get_constant( 'ABSPATH' ) . 'index.php' ) & 0777 | 0644;
	}

	return $perms[ $type ];
}

/**
 * Gets Directory files matches regex.
 *
 * @since 3.6.3
 * @access private
 *
 * @param string $dir   Directory to search for files inside it.
 * @param string $regex Regular expression for files need to be searched for.
 *
 * @return array|RegexIterator List of files matches this regular expression.
 */
function _noobrocket_get_dir_files_by_regex( $dir, $regex ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	try {
		$iterator = new IteratorIterator(
			new FilesystemIterator( $dir, FilesystemIterator::SKIP_DOTS | FilesystemIterator::KEY_AS_FILENAME )
		);

		return new RegexIterator( $iterator, $regex, RegexIterator::MATCH, RegexIterator::USE_KEY );
	} catch ( Exception $e ) {
		return [];
	}
}

/**
 * Get the recursive iterator for the cache path.
 *
 * @since  3.5.4
 * @access private
 *
 * @param string $cache_path Path to the cache directory.
 *
 * @return bool|RecursiveIteratorIterator Iterator on success; else false;
 */
function _noobrocket_get_cache_path_iterator( $cache_path ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	try {
		return new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $cache_path ),
			RecursiveIteratorIterator::SELF_FIRST,
			RecursiveIteratorIterator::CATCH_GET_CHILD
		);
	} catch ( Exception $e ) {
		// No logging yet.
		return false;
	}
}

/**
 * Gets the directories for the given URL host from the cache/noobrocket/ directory or stored memory.
 *
 * @since  3.5.5
 * @access private
 *
 * @param string $url_host   The URL's host.
 * @param string $cache_path Cache's path, e.g. cache/noobrocket/.
 * @param bool   $hard_reset Optional. When true, resets the static domain directories array and bails out.
 *
 * @return array
 */
function _noobrocket_get_cache_dirs( $url_host, $cache_path = '', $hard_reset = false ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	static $domain_dirs = [];

	if ( true === $hard_reset ) {
		$domain_dirs = [];

		return;
	}

	if ( empty( $cache_path ) ) {
		$cache_path = _noobrocket_get_noobrocket_cache_path();
	}
	$key = $cache_path . '|' . $url_host . '|' . get_noobrocket_option( 'secret_cache_key', '' );
	if ( isset( $domain_dirs[ $key ] ) ) {
		return $domain_dirs[ $key ];
	}

	try {
		$iterator = new IteratorIterator(
			new FilesystemIterator( $cache_path )
		);
	} catch ( Exception $e ) {
		return [];
	}

	$domain_dirs[ $key ] = [];
	foreach ( $iterator as $entry ) {
		if ( $entry->isDir() && ! $entry->isLink() && noobrocket_cache_directory_belongs_to_host( $entry->getFilename(), $url_host ) ) {
			$domain_dirs[ $key ][] = $entry->getPathname();
		}
	}

	return $domain_dirs[ $key ];
}

/** Exact public host or a user bucket signed by this site's existing naming secret. */
function noobrocket_cache_directory_belongs_to_host( string $directory, string $host ): bool {
	if ( $directory === $host ) {
		return true;
	}
	$secret = (string) get_noobrocket_option( 'secret_cache_key', '' );
	if ( '' === $secret ) {
		return false;
	}
	$pattern = '/\A' . preg_quote( $host, '/' ) . '-[^\/\\\\]+-' . preg_quote( $secret, '/' ) . '\z/';
	return 1 === preg_match( $pattern, $directory );
}

/**
 * Normalizes the given filesystem path:
 *  - Windows/IIS-based servers: converts all directory separators to "\\" or, when escaping, to "\\\\".
 *  - Linux-based servers: if $forced is true, uses wp_normalize_path(); else, returns the original path.
 *
 * @since  3.5.5
 * @access private
 *
 * @param string $path   Filesystem path (file or directory) to normalize.
 * @param bool   $escape Optional. When true, escapes the directory separator(s).
 * @param bool   $force  Optional. When true, forces normalizing off non-Windows' paths.
 *
 * @return string
 */
function _noobrocket_normalize_path( $path, $escape = false, $force = false ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	if ( _noobrocket_is_windows_fs( $path ) ) {
		$path = str_replace( '/', '\\', $path );

		return $escape
			? str_replace( '\\', '\\\\', $path )
			: $path;
	}

	if ( $escape ) {
		return str_replace( '/', '\/', $path );
	}

	if ( ! $force ) {
		return $path;
	}

	return wp_normalize_path( $path );
}

/**
 * Checks if the filesystem (fs) is for Windows/IIS server.
 *
 * @since  3.5.5
 * @access private
 *
 * @param bool $hard_reset Optional. When true, resets the memoization.
 *
 * @return bool
 */
function _noobrocket_is_windows_fs( $hard_reset = false ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	static $is_windows = null;

	if ( $hard_reset ) {
		$is_windows = null;
	}

	if ( is_null( $is_windows ) ) {
		$is_windows = (
			DIRECTORY_SEPARATOR === '\\'
			&&
			! noobrocket_get_constant( 'NOOBROCKET_RUNNING_VFS', false )
		);
	}

	return $is_windows;
}

/**
 * Gets the normalized cache path, i.e. normalizes constant "NOOBROCKET_CACHE_PATH".
 *
 * @since  3.5.5
 * @access private
 *
 * @return string
 */
function _noobrocket_get_noobrocket_cache_path() { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	return _noobrocket_normalize_path( noobrocket_get_constant( 'NOOBROCKET_CACHE_PATH' ) );
}

/**
 * Gets .php files in a directory as an array of SplFileInfo objects.
 *
 * @since 3.6.3
 *
 * @param string $dir_path Directory to check.
 *
 * @return array .php files in the directory. [...SplFileInfo]
 */
function _noobrocket_get_php_files_in_dir( $dir_path ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	try {
		$config_dir = new FilesystemIterator( (string) $dir_path );
	} catch ( Exception $e ) {
		return [];
	}
	$files = [];

	foreach ( $config_dir as $file ) {
		if ( $file->isFile() && 'php' === $file->getExtension() ) {
			$files[] = $file;
		}
	}

	return $files;
}

/**
 * Get recursive files matched by regex.
 *
 * @since 3.6.3
 *
 * @param string $regex Regular Expression to be applied.
 *
 * @return array|RegexIterator List of files which match the regular expression (SplFileInfo).
 */
function _noobrocket_get_recursive_dir_files_by_regex( $regex ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	try {
		$cache_path = _noobrocket_get_noobrocket_cache_path();
		$iterator   = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $cache_path, FilesystemIterator::SKIP_DOTS )
		);
		return new RegexIterator( $iterator, $regex, RecursiveRegexIterator::MATCH );
	} catch ( Exception $e ) {
		return [];
	}
}
