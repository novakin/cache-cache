<?php

defined( 'ABSPATH' ) || exit;

/**
 * Link to the configuration page of the plugin, support & documentation
 *
 * @since 1.0
 *
 * @param array $actions Array of links to display.
 * @return array Updated array of links
 */
function noobrocket_settings_action_links( $actions ) {
	if ( ! current_user_can( 'noobrocket_manage_options' ) ) {
		return $actions;
	}

	array_unshift( $actions, sprintf( '<a href="%s">%s</a>', get_noobrocket_documentation_url(), __( 'Docs', 'noobrocket' ) ) );

	array_unshift( $actions, sprintf( '<a href="%s">%s</a>', get_noobrocket_faq_url(), __( 'FAQ', 'noobrocket' ) ) );

	array_unshift( $actions, sprintf( '<a href="%s">%s</a>', admin_url( 'options-general.php?page=' . NOOBROCKET_PLUGIN_SLUG ), __( 'Settings', 'noobrocket' ) ) );

	return $actions;
}
add_filter( 'plugin_action_links_' . plugin_basename( NOOBROCKET_FILE ), 'noobrocket_settings_action_links' );

/**
 * Add a link "Purge this cache" in the post edit area
 *
 * @since 1.0
 *
 * @param array  $actions An array of row action links.
 * @param object $post The post object.
 * @return array Updated array of row action links
 */
function noobrocket_post_row_actions( $actions, $post ) {

	if ( ! noobrocket_can_display_options() ) {
		return $actions;
	}

	if ( ! current_user_can( 'noobrocket_purge_posts' ) ) {
		return $actions;
	}

	$cpts = get_post_types(
		[
			'public' => true,
		],
		'objects'
	);

	/**
	 * Filters the post type on row actions.
	 *
	 * @since 3.11.4
	 *
	 * @param array $cpts Post Types.
	 */
	$cpts = apply_filters( 'noobrocket_skip_post_row_actions', $cpts );

	if ( ! isset( $cpts[ $post->post_type ] ) ) {
		return $actions;
	}

	$url                     = wp_nonce_url( admin_url( 'admin-post.php?action=purge_cache&type=post-' . $post->ID ), 'purge_cache_post-' . $post->ID );
	$actions['noobrocket_purge'] = sprintf( '<a href="%s">%s</a>', $url, __( 'Clear this cache', 'noobrocket' ) );

	return $actions;
}
add_filter( 'page_row_actions', 'noobrocket_post_row_actions', 10, 2 );
add_filter( 'post_row_actions', 'noobrocket_post_row_actions', 10, 2 );

/**
 * Add a link "Purge this cache" in the user edit area
 *
 * @since 2.6.12
 * @param array  $actions An array of row action links.
 * @param object $user The user object.
 * @return array Updated array of row action links
 */
function noobrocket_user_row_actions( $actions, $user ) {
	if ( ! current_user_can( 'noobrocket_purge_users' ) || ! get_noobrocket_option( 'cache_logged_user', false ) ) {
		return $actions;
	}

	$url                     = wp_nonce_url( admin_url( 'admin-post.php?action=purge_cache&type=user-' . $user->ID ), 'purge_cache_user-' . $user->ID );
	$actions['noobrocket_purge'] = sprintf( '<a href="%s">%s</a>', $url, __( 'Clear this cache', 'noobrocket' ) );

	return $actions;
}
add_filter( 'user_row_actions', 'noobrocket_user_row_actions', 10, 2 );

/**
 * Manage the dismissed boxes.
 *
 * @since 3.6   Reverse dependency with noobrocket_dismiss_box().
 * @since 2.4   Add a delete_transient on function name (box name).
 * @since 1.3.0 $args can replace $_GET when called internally.
 * @since 1.1.10
 *
 * @param array $args An array of query args. Should not be used: see noobrocket_dismiss_box().
 */
function noobrocket_dismiss_boxes( $args = [] ) {
	global $pagenow;

	$args = empty( $args ) ? $_GET : $args; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( ! isset( $args['box'], $args['action'], $args['_wpnonce'] ) ) {
		return;
	}

	if ( ! wp_verify_nonce( $args['_wpnonce'], "{$args['action']}_{$args['box']}" ) ) {
		if ( noobrocket_get_constant( 'DOING_AJAX' ) ) {
			wp_send_json( [ 'error' => 1 ] );
		} else {
			wp_nonce_ays( '' );
		}
		return;
	}

	if ( ! current_user_can( 'noobrocket_manage_options' ) ) {
		wp_nonce_ays( '' );
	}

	noobrocket_dismiss_box( $args['box'] );

	if ( 'admin-post.php' === $pagenow ) {
		if ( noobrocket_get_constant( 'DOING_AJAX' ) ) {
			wp_send_json( [ 'error' => 0 ] );
		} else {
			$redirect = ! empty( $args['redirect'] )
				? wp_validate_redirect( esc_url_raw( wp_unslash( $args['redirect'] ) ), admin_url() )
				: esc_url_raw( wp_get_referer() );

			wp_safe_redirect( $redirect );
			noobrocket_get_constant( 'NOOBROCKET_IS_TESTING', false ) ? wp_die() : exit;
		}
	}
}
add_action( 'wp_ajax_noobrocket_ignore', 'noobrocket_dismiss_boxes' );
add_action( 'admin_post_noobrocket_ignore', 'noobrocket_dismiss_boxes' );

/**
 * Renew the plugin modification warning on plugin de/activation
 *
 * @since 1.3.0
 *
 * @param string $plugin plugin name.
 */
function noobrocket_dismiss_plugin_box( $plugin ) {
	if ( plugin_basename( NOOBROCKET_FILE ) !== $plugin ) {
		noobrocket_renew_box( 'noobrocket_warning_plugin_modification' );
	}
}
add_action( 'activated_plugin', 'noobrocket_dismiss_plugin_box' );
add_action( 'deactivated_plugin', 'noobrocket_dismiss_plugin_box' );

/**
 * Display a prevention message when enabling or disabling a plugin can be in conflict with NoobRocket
 *
 * @since 1.3.0
 */
function noobrocket_deactivate_plugin() {
	if ( ! isset( $_GET['plugin'], $_GET['_wpnonce'] ) || ! is_string( $_GET['plugin'] ) || ! is_string( $_GET['_wpnonce'] ) ) {
		return;
	}
	$plugin = sanitize_text_field( wp_unslash( $_GET['plugin'] ) );
	if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'noobrocket_deactivate_plugin:' . $plugin ) ) {
		wp_nonce_ays( '' );
	}

	$conflicts = (array) apply_filters( 'noobrocket_plugins_to_deactivate', [] );
	if ( ! current_user_can( 'noobrocket_manage_options' ) || ! current_user_can( 'deactivate_plugin', $plugin )
		|| ! in_array( $plugin, $conflicts, true ) || ! is_plugin_active( $plugin ) ) {
		wp_die( esc_html__( 'You cannot deactivate this plugin through Cache-Cache.', 'noobrocket' ), '', [ 'response' => 403 ] );
	}

	deactivate_plugins( $plugin );

	wp_safe_redirect( wp_get_referer() ?: admin_url( 'plugins.php' ) );
	die();
}
add_action( 'admin_post_noobrocket_deactivate_plugin', 'noobrocket_deactivate_plugin' );

/**
 * This function will force the direct download of the plugin's options, compressed.
 *
 * @since 2.2
 */
function noobrocket_do_options_export() {
	if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'noobrocket_export' ) ) {
		wp_nonce_ays( '' );
	}

	if ( ! current_user_can( 'noobrocket_manage_options' ) ) {
		wp_nonce_ays( '' );
	}

	list( $filename, $options ) = noobrocket_export_options();
	nocache_headers();
	@header( 'Content-Type: application/json' );
	@header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
	@header( 'Content-Transfer-Encoding: binary' );
	@header( 'Content-Length: ' . strlen( $options ) );
	@header( 'Connection: close' );
	echo $options; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit();
}
add_action( 'admin_post_noobrocket_export', 'noobrocket_do_options_export' );

if ( ! defined( 'DOING_AJAX' ) && ! defined( 'DOING_AUTOSAVE' ) ) {
	add_action( 'admin_init', 'noobrocket_init_cache_dir' );
	add_action( 'admin_init', 'noobrocket_maybe_generate_advanced_cache_file' );
	add_action( 'admin_init', 'noobrocket_maybe_generate_config_files' );
}

/**
 * Regenerate the advanced-cache.php file if an issue is detected.
 *
 * @since 2.6
 */
function noobrocket_maybe_generate_advanced_cache_file() {
	if ( ! defined( 'NOOBROCKET_ADVANCED_CACHE' ) || ( defined( 'NOOBROCKET_ADVANCED_CACHE_PROBLEM' ) && NOOBROCKET_ADVANCED_CACHE_PROBLEM ) ) {
		noobrocket_generate_advanced_cache_file();
	}
}

/**
 * Regenerate config file if an issue is detected.
 *
 * @since 2.6.5
 */
function noobrocket_maybe_generate_config_files() {
	$home = get_noobrocket_parse_url( noobrocket_get_home_url() );

	$path = ( ! empty( $home['path'] ) ) ? str_replace( '/', '.', untrailingslashit( $home['path'] ) ) : '';

	$config_file = NOOBROCKET_CONFIG_PATH . strtolower( $home['host'] ) . $path . '.php';
	$content = is_readable( $config_file ) ? noobrocket_direct_filesystem()->get_contents( $config_file ) : false;
	if ( false === $content || false === strpos( $content, '$noobrocket_config_complete = true;' ) ) {
		noobrocket_generate_config_file();
	}
}

/**
 * Gets all data to send to the analytics system
 *
 * @since 3.0 Send CDN zones, sitemaps paths, and count the number of CDN URLs used
 * @since 2.11
 * @author Remy Perona
 *
 * @return mixed An array of data, or false if NoobRocket options is not an array
 */
function noobrocket_analytics_data() {
	global $wp_version, $is_nginx, $is_apache, $is_iis7, $is_IIS;

	if ( ! is_array( get_option( NOOBROCKET_SLUG ) ) ) {
		return false;
	}

	$untracked_noobrocket_options = [
		'license'                 => 1,
		'consumer_email'          => 1,
		'consumer_key'            => 1,
		'secret_key'              => 1,
		'secret_cache_key'        => 1,
		'minify_css_key'          => 1,
		'minify_js_key'           => 1,
		'cloudflare_email'        => 1,
		'cloudflare_api_key'      => 1,
		'cloudflare_zone_id'      => 1,
		'cloudflare_old_settings' => 1,
		'submit_optimize'         => 1,
		'analytics_enabled'       => 1,
	];

	$theme              = wp_get_theme();
	$data               = array_diff_key( get_option( NOOBROCKET_SLUG ), $untracked_noobrocket_options );
	$locale             = explode( '_', get_locale() );
	$data['web_server'] = 'Unknown';

	if ( $is_nginx ) {
		$data['web_server'] = 'NGINX';
	} elseif ( $is_apache ) {
		$data['web_server'] = 'Apache';
	} elseif ( $is_iis7 ) {
		$data['web_server'] = 'IIS 7';
	} elseif ( $is_IIS ) {
		$data['web_server'] = 'IIS';
	}

	$data['php_version']       = preg_replace( '@^(\d\.\d+).*@', '\1', phpversion() );
	$data['wordpress_version'] = preg_replace( '@^(\d\.\d+).*@', '\1', $wp_version );
	$data['current_theme']     = $theme->get( 'Name' );
	$data['active_plugins']    = noobrocket_get_active_plugins();
	$data['locale']            = $locale[0];
	$data['multisite']         = is_multisite();

	if ( ! empty( $data['cdn_cnames'] ) && is_array( $data['cdn_cnames'] ) ) {
		$data['cdn_cnames'] = count( $data['cdn_cnames'] );
	} else {
		$data['cdn_cnames'] = 0;
	}

	$customer_data        = get_transient( 'noobrocket_customer_data' );
	$data['license_type'] = '';
	if ( false !== $customer_data ) {
		$data['license_type'] = noobrocket_get_license_type( $customer_data );
	}

	$media_font_data = get_transient( 'noobrocket_fonts_data_collection' );

	if ( false !== $media_font_data ) {
		$data = array_merge( $data, $media_font_data );
	}

	return $data;
}

/**
 * Handles the analytics opt-in notice selection and prevent further display
 *
 * @since 2.11
 * @author Remy Perona
 */
function noobrocket_analytics_optin() {
	if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'analytics_optin' ) ) {
		wp_nonce_ays( '' );
	}

	if ( ! current_user_can( 'noobrocket_manage_options' ) ) {
		wp_safe_redirect( wp_get_referer() );
		die();
	}

	if ( isset( $_GET['value'] ) && 'yes' === $_GET['value'] ) {
		update_option( 'noobrocket_mixpanel_optin', 1 );
		set_transient( 'noobrocket_analytics_optin', 1 );

		/**
		 * Fires when the Mixpanel opt-in status changes to enabled.
		 *
		 * @param bool $status The opt-in status.
		 */
		do_action( 'noobrocket_mixpanel_optin_changed', true );
	}

	update_option( 'noobrocket_analytics_notice_displayed', 1 );

	wp_safe_redirect( wp_get_referer() );
	die();
}

/**
 * Handle NoobRocket settings import.
 *
 * @since 3.10 disable async_css if both async_css and remove_unused_css are enabled
 * @since 3.0 Hooked on admin_post now
 * @since 2.10.7
 * @author Remy Perona
 *
 * @return void
 */
function noobrocket_handle_settings_import() {
	check_ajax_referer( 'noobrocket_import_settings', 'noobrocket_import_settings_nonce' );

	if ( ! current_user_can( 'noobrocket_manage_options' ) ) {
		noobrocket_settings_import_redirect( __( 'Settings import failed: you do not have the permissions to do this.', 'noobrocket' ), 'error' );
	}

	$options_api = new NoobRocket\Admin\Options( 'noobrocket_' );
	$current_options = $options_api->get( 'settings', [] );
	$review_key = 'noobrocket_import_review_' . get_current_user_id();
	if ( isset( $_POST['noobrocket_import_review'] ) ) {
		$review = get_transient( $review_key );
		$token = sanitize_text_field( wp_unslash( $_POST['noobrocket_import_review'] ) );
		if ( ! is_array( $review ) || ! isset( $review['token'] ) || ! hash_equals( $review['token'], $token ) ) {
			noobrocket_settings_import_redirect( __( 'Import review expired or was replaced. Upload the file again to review it.', 'noobrocket' ), 'error' );
		}
		delete_transient( $review_key );
		if ( isset( $_POST['noobrocket_import_cancel'] ) ) {
			noobrocket_settings_import_redirect( __( 'Import canceled. Saved settings were not changed.', 'noobrocket' ), 'updated' );
		}
		if ( $current_options !== $review['current'] ) {
			noobrocket_settings_import_redirect( __( 'Saved settings changed after this review. Upload the file again to review the current differences.', 'noobrocket' ), 'error' );
		}
		$validated = $review['settings'];
		$validated['ignore'] = 1;
		$validated = noobrocket_free_settings( sanitize_option( NOOBROCKET_SLUG, $validated ) );
		if ( $validated !== $review['settings'] ) {
			noobrocket_settings_import_redirect( __( 'Import validation changed after this review. Upload the file again to review it.', 'noobrocket' ), 'error' );
		}
		$options_api->set( 'settings', $review['settings'] );
		if ( $options_api->get( 'settings', [] ) !== $review['settings'] ) {
			noobrocket_settings_import_redirect( __( 'Settings import could not be saved. Upload the file again to review the current state.', 'noobrocket' ), 'error' );
		}
		do_action( 'noobrocket_after_save_import', $review['regenerate_configs'] );
		noobrocket_settings_import_redirect( __( 'Settings imported and saved.', 'noobrocket' ), 'updated' );
	}

	if ( ! isset( $_FILES['import'] ) || ( isset( $_FILES['import']['size'] ) && 0 === $_FILES['import']['size'] ) ) {
		noobrocket_settings_import_redirect( __( 'Settings import failed: no file uploaded.', 'noobrocket' ), 'error' );
	}

	if ( isset( $_FILES['import']['name'] ) && ! preg_match( '/noobrocket-settings(?:-.*)?-20\d{2}-\d{2}-\d{2}-[a-f0-9]{13}\.json/', sanitize_file_name( $_FILES['import']['name'] ) ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		noobrocket_settings_import_redirect( __( 'Settings import failed: incorrect filename. Only JSON format is supported.', 'noobrocket' ), 'error' );
	}

	add_filter( 'mime_types', 'noobrocket_allow_json_mime_type' );
	add_filter( 'wp_check_filetype_and_ext', 'noobrocket_check_json_filetype', 10, 4 );

	$mimes     = get_allowed_mime_types();
	$mimes     = noobrocket_allow_json_mime_type( $mimes );
	$file_data = wp_check_filetype_and_ext( $_FILES['import']['tmp_name'], sanitize_file_name( $_FILES['import']['name'] ), $mimes ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotValidated

	if ( 'application/json' !== $file_data['type'] ) {
		noobrocket_settings_import_redirect( __( 'Settings import failed: only JSON format is supported for security reasons.', 'noobrocket' ), 'error' );
	}

	$_post_action       = isset( $_POST['action'] ) ? wp_unslash( sanitize_key( $_POST['action'] ) ) : '';
	$_POST['action']    = 'wp_handle_sideload';
	$overrides          = [];
	$overrides['mimes'] = $mimes;
	$file               = wp_handle_sideload( $_FILES['import'], $overrides ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash

	if ( isset( $file['error'] ) ) {
		noobrocket_settings_import_redirect( __( 'Settings import failed: ', 'noobrocket' ) . $file['error'], 'error' );
	}

	$_POST['action'] = $_post_action;
	$settings        = noobrocket_direct_filesystem()->get_contents( $file['file'] );
	remove_filter( 'mime_types', 'noobrocket_allow_json_mime_type' );
	remove_filter( 'wp_check_filetype_and_ext', 'noobrocket_check_json_filetype', 10 );

	$settings = json_decode( $settings, true );
	noobrocket_put_content( $file['file'], '' );
	noobrocket_direct_filesystem()->delete( $file['file'] );
	if ( ! is_array( $settings ) || [] === $settings || array_keys( $settings ) === range( 0, count( $settings ) - 1 ) ) {
		noobrocket_settings_import_redirect( __( 'Settings import failed: unexpected file content.', 'noobrocket' ), 'error' );
	}

	if ( is_array( $settings ) ) {
		$regenerate_configs = false;

		foreach ( [ 'secret_cache_key', 'minify_css_key', 'minify_js_key', 'version', 'engine_version' ] as $preserved ) {
			if ( array_key_exists( $preserved, $current_options ) ) {
				$settings[ $preserved ] = $current_options[ $preserved ];
			} else {
				unset( $settings[ $preserved ] );
			}
		}
		if (
			isset( $settings['async_css'] ) && $settings['async_css'] &&
			isset( $settings['remove_unused_css'] ) && $settings['remove_unused_css']
		) {
			$settings['async_css'] = 0;
		}
		if ( ! empty( $settings['cache_webp'] ) && apply_filters( 'noobrocket_disable_webp_cache', false ) ) {
			$settings['cache_webp'] = 0;
		}

		if ( ! empty( $settings['cache_mobile'] ) && empty( $settings['do_caching_mobile_files'] ) ) {
			$settings['do_caching_mobile_files'] = 1;
			$regenerate_configs                  = true;
		}

		// Import settings only; the cleanup submit button is not an importable setting.
		unset( $settings['submit_optimize'] );
		// Reuse native validation before review, without reporting a save that did not occur.
		$settings['ignore'] = 1;
		$settings = noobrocket_free_settings( sanitize_option( NOOBROCKET_SLUG, $settings ) );
		set_transient( $review_key, [
			'token'              => wp_generate_uuid4(),
			'filename'           => sanitize_file_name( $_FILES['import']['name'] ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
			'current'            => $current_options,
			'settings'           => $settings,
			'regenerate_configs' => $regenerate_configs,
		], 10 * MINUTE_IN_SECONDS );
		$redirect = admin_url( 'options-general.php?page=' . NOOBROCKET_PLUGIN_SLUG . '#tools' );
		$errors   = get_settings_errors();
		if ( $errors ) {
			set_transient( 'settings_errors', $errors, 30 );
			$redirect = add_query_arg( 'settings-updated', 'true', $redirect );
		}
		wp_safe_redirect( $redirect );
		exit;
	}
}
add_action( 'admin_post_noobrocket_import_settings', 'noobrocket_handle_settings_import' );
