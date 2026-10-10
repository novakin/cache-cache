<?php

defined( 'ABSPATH' ) || exit;

/**
 * This warnings are displayed when the plugin can not be deactivated correctly
 *
 * @since 2.0.0
 */
function noobrocket_bad_deactivations() {
	global $current_user;

	$screen = get_current_screen();

	if ( 'plugins' !== $screen->id ) {
		return;
	}

	$msgs = get_transient( $current_user->ID . '_donotdeactivatenoobrocket' );
	if ( current_user_can( 'noobrocket_manage_options' ) && $msgs ) {

		delete_transient( $current_user->ID . '_donotdeactivatenoobrocket' );
		$errors = [];

		foreach ( $msgs as $msg ) {
			switch ( $msg ) {
				case 'wpconfig':
					$errors['wpconfig'] = '<p>' . sprintf(
						// translators: %1$s NoobRocket plugin name; %2$s = file name.
						__(
							'<strong>%1$s</strong> has not been deactivated due to missing writing permissions.<br>
Make <strong>%2$s</strong> writeable and retry deactivation, or force deactivation now:',
							'noobrocket'
							),
						NOOBROCKET_PLUGIN_NAME,
						'wp-config.php'
					) . '</p>';
					break;

				case 'htaccess':
					$errors['htaccess'] = '<p>' . sprintf(
						// translators: %1$s NoobRocket plugin name; %2$s = file name.
						__(
							'<strong>%1$s</strong> has not been deactivated due to missing writing permissions.<br>
Make <strong>%2$s</strong> writeable and retry deactivation, or force deactivation now:',
							'noobrocket'
							),
						NOOBROCKET_PLUGIN_NAME,
						'.htaccess'
					) . '</p>';
					break;
			}

			/**
			 * Filter the output messages for each bad deactivation attempt.
			 *
			 * @since 2.0.0
			 *
			 * @param array $errors Contains the error messages to be filtered
			 * @param string $msg Contains the error type (wpconfig or htaccess)
			 */
			$errors = apply_filters( 'noobrocket_bad_deactivations', $errors, $msg );

		}

		noobrocket_notice_html(
			[
				'status'      => 'error',
				'dismissible' => '',
				'message'     => implode( '', $errors ),
				'action'      => 'force_deactivation',
			]
		);
	}
}
add_action( 'admin_notices', 'noobrocket_bad_deactivations' );

/**
 * This warning is displayed to inform the user that a plugin de/activation can be followed by a cache clear
 *
 * @since 1.3.0
 */
function noobrocket_warning_plugin_modification() {
	if ( current_user_can( 'noobrocket_manage_options' ) && noobrocket_valid_key() ) {

		$boxes = get_user_meta( get_current_user_id(), 'noobrocket_boxes', true );

		if ( in_array( __FUNCTION__, (array) $boxes, true ) ) {
			return;
		}

		noobrocket_notice_html(
			[
				'status'         => 'warning',
				'dismissible'    => '',
				// translators: %s is NoobRocket plugin name.
				'message'        => sprintf( __( '<strong>%s</strong>: One or more plugins have been enabled or disabled, clear the cache if they affect the front end of your site.', 'noobrocket' ), NOOBROCKET_PLUGIN_NAME ),
				'action'         => 'clear_cache',
				'dismiss_button' => __FUNCTION__,
			]
		);
	}
}
add_action( 'admin_notices', 'noobrocket_warning_plugin_modification' );

/**
 * This warning is displayed when some plugins may conflict with NoobRocket
 *
 * @since 1.3.0
 */
function noobrocket_plugins_to_deactivate() {
	$plugins              = [];
	$plugins_explanations = [];

	/**
	 * Filter the recommended plugins to deactivate to prevent conflicts
	 *
	 * @since 2.6.4
	 *
	 * @param array $plugins List of recommended plugins to deactivate.
	 */
	$plugins = apply_filters( 'noobrocket_plugins_to_deactivate', $plugins );

	/**
	 * Filter the recommended plugins to deactivate explanations
	 *
	 * @since 3.10.5
	 *
	 * @param array $plugins List of recommended plugins to deactivate explanations.
	 */
	$plugins_explanations = apply_filters( 'noobrocket_plugins_to_deactivate_explanations', $plugins_explanations );

	$plugins = array_filter( $plugins, 'is_plugin_active' );

	if ( current_user_can( 'noobrocket_manage_options' )
		&& count( $plugins )
		&& noobrocket_valid_key()
	) {

		// translators: %s is NoobRocket plugin name.
		$warning = '<p>' . sprintf( __( '<strong>%s</strong>: The following plugins are not compatible with this plugin and may cause unexpected results:', 'noobrocket' ), NOOBROCKET_PLUGIN_NAME ) . '</p>';

		$warning .= '<ul class="noobrocket-plugins-error">';

		foreach ( $plugins as $k => $plugin ) {
			$plugin_data = get_plugin_data( WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $plugin );
			$warning    .= '<li><b>' . esc_html( $plugin_data['Name'] ) . '</b>' . ( isset( $plugins_explanations[ $k ] ) ? ' - ' . $plugins_explanations[ $k ] : '' );
			if ( current_user_can( 'deactivate_plugin', $plugin ) ) {
				$warning .= ' <a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=noobrocket_deactivate_plugin&plugin=' . rawurlencode( $plugin ) ), 'noobrocket_deactivate_plugin:' . $plugin ) ) . '" class="button-secondary alignright">' . esc_html__( 'Deactivate', 'noobrocket' ) . '</a>';
			}
			$warning .= '</li>';
		}

		$warning .= '</ul>';

		noobrocket_notice_html(
			[
				'status'      => 'error',
				'dismissible' => '',
				'message'     => $warning,
			]
		);
	}
}
add_action( 'admin_notices', 'noobrocket_plugins_to_deactivate' );

/**
 * Displays a warning if Rocket Footer JS plugin is active
 *
 * @since 3.2.3
 * @author Remy Perona
 *
 * @return void
 */
function noobrocket_warning_footer_js_plugin() {
	$screen = get_current_screen();

	if ( ! current_user_can( 'noobrocket_manage_options' ) ) {
		return;
	}

	if ( 'settings_page_noobrocket' !== $screen->id ) {
		return;
	}

	if ( ! is_plugin_active( 'rocket-footer-js/rocket-footer-js.php' ) ) {
		return;
	}

	noobrocket_notice_html(
		[
			'status'         => 'warning',
			'message'        => __( 'Rocket Footer JS is not an official add-on. It prevents some options in Cache-Cache from working correctly. Please deactivate it if you have problems.', 'noobrocket' ),
			'dismiss_button' => true,
		]
	);
}
add_action( 'admin_notices', 'noobrocket_warning_footer_js_plugin' );

/**
 * Display a warning if Endurance Cache is not disabled
 *
 * @since 3.3.7
 * @author Remy Perona
 *
 * @return void
 */
function noobrocket_warning_endurance_cache() {
	$screen = get_current_screen();

	// This filter is documented in inc/admin-bar.php.
	if ( ! current_user_can( apply_filters( 'noobrocket_capacity', 'manage_options' ) ) ) {
		return;
	}

	if ( 'settings_page_noobrocket' !== $screen->id ) {
		return;
	}

	if ( ! class_exists( 'Endurance_Page_Cache' ) ) {
		return;
	}

	if ( 0 === (int) get_option( 'endurance_cache_level' ) ) {
		return;
	}

	noobrocket_notice_html(
		[
			'status'  => 'error',
			'message' => sprintf(
				// translators: %1$s = opening link tag, %2$s = closing link tag.
				__( 'Endurance Cache is currently enabled, which will conflict with Cache-Cache Cache. Please set the Endurance Cache cache level to Off (Level 0) on the %1$sSettings > General%2$s page to prevent any issues.', 'noobrocket' ),
				'<a href="' . admin_url( 'options-general.php#epc_settings' ) . '">',
				'</a>'
			),
		]
	);
}
add_action( 'admin_notices', 'noobrocket_warning_endurance_cache' );

/**
 * This warning is displayed when there is no permalink structure in the configuration.
 *
 * @since 1.0
 */
function noobrocket_warning_using_permalinks() {
	if ( current_user_can( 'noobrocket_manage_options' )
		&& ! $GLOBALS['wp_rewrite']->using_permalinks()
		&& noobrocket_valid_key()
	) {
		$message = sprintf(
			/* translators: %1$s NoobRocket plugin name; %2$s = opening link; %3$s = closing link */
			__( '%1$s: A custom permalink structure is required for the plugin to work properly. %2$sGo to permalinks settings%3$s', 'noobrocket' ),
			'<strong>' . NOOBROCKET_PLUGIN_NAME . '</strong>',
			'<a href="' . admin_url( 'options-permalink.php' ) . '">',
			'</a>'
		);

		noobrocket_notice_html(
			[
				'status'      => 'error',
				'dismissible' => '',
				'message'     => $message,
			]
		);
	}
}
add_action( 'admin_notices', 'noobrocket_warning_using_permalinks' );

/**
 * This warning is displayed when the .htaccess file doesn't exist or isn't writeable
 *
 * @since 1.0
 */
function noobrocket_warning_htaccess_permissions() {
	global $is_apache;
	$htaccess_file = get_home_path() . '.htaccess';

	if ( ! current_user_can( 'noobrocket_manage_options' )
		|| ( noobrocket_direct_filesystem()->is_writable( $htaccess_file ) )
		|| ! $is_apache
		// This filter is documented in inc/functions/htaccess.php.
		|| apply_filters( 'noobrocket_disable_htaccess', false )
		|| ! noobrocket_valid_key() ) {
			return;
	}

	if ( noobrocket_check_htaccess_rules() ) {
		return;
	}

	$boxes = get_user_meta( get_current_user_id(), 'noobrocket_boxes', true );

	if ( in_array( __FUNCTION__, (array) $boxes, true ) ) {
		return;
	}

	$message = sprintf(
		// translators: %s = plugin name.
		__( '%s could not modify the .htaccess file due to missing writing permissions.', 'noobrocket' ),
		'<strong>' . NOOBROCKET_PLUGIN_NAME . '</strong>'
	);

	$message .= '<br>' . sprintf(
		/* translators: This is a doc title! %1$s = opening link; %2$s = closing link */
		__( 'Troubleshoot: %1$sHow to make system files writeable%2$s', 'noobrocket' ),
		/* translators: Documentation exists in EN, DE, FR, ES, IT; use loaclised URL if applicable */
		'<a href="' . get_noobrocket_documentation_url() . '" target="_blank">',
		'</a>'
	);

	add_filter( 'noobrocket_htaccess_mod_rewrite', '__return_false', 42 );

	$message .= '<p>' . __( 'Don’t worry, Cache-Cache’s page caching and settings will still function correctly.', 'noobrocket' ) . '<br>' . __( 'For optimal performance, adding the following lines into your .htaccess is recommended (not required):', 'noobrocket' ) . '<br><textarea readonly="readonly" id="noobrocket_htaccess_rules" name="noobrocket_htaccess_rules" class="large-text readonly" rows="6">' . esc_textarea( get_noobrocket_htaccess_marker() ) . '</textarea></p>';

	remove_filter( 'noobrocket_htaccess_mod_rewrite', '__return_false', 42 );

	noobrocket_notice_html(
		[
			'status'         => 'warning',
			'dismissible'    => '',
			'message'        => $message,
			'dismiss_button' => __FUNCTION__,
		]
	);
}
add_action( 'admin_notices', 'noobrocket_warning_htaccess_permissions' );

/**
 * This warning is displayed when the config dir isn't writeable
 *
 * @since 2.0.2
 */
function noobrocket_warning_config_dir_permissions() {
	if ( current_user_can( 'noobrocket_manage_options' )
		&& ( ! noobrocket_direct_filesystem()->is_writable( NOOBROCKET_CONFIG_PATH ) )
		&& noobrocket_valid_key() ) {

		$boxes = get_user_meta( get_current_user_id(), 'noobrocket_boxes', true );

		if ( in_array( __FUNCTION__, (array) $boxes, true ) ) {
			return;
		}

		$message = noobrocket_notice_writing_permissions( trim( str_replace( ABSPATH, '', NOOBROCKET_CONFIG_PATH ), '/' ) );

		noobrocket_notice_html(
			[
				'status'      => 'error',
				'dismissible' => '',
				'message'     => $message,
			]
		);

	}
}
add_action( 'admin_notices', 'noobrocket_warning_config_dir_permissions' );

/**
 * This warning is displayed when the cache dir isn't writeable
 *
 * @since 1.0
 */
function noobrocket_warning_cache_dir_permissions() {
	if ( current_user_can( 'noobrocket_manage_options' )
		&& ( ! noobrocket_direct_filesystem()->is_writable( NOOBROCKET_CACHE_PATH ) )
		&& noobrocket_valid_key() ) {

		$boxes = get_user_meta( get_current_user_id(), 'noobrocket_boxes', true );

		if ( in_array( __FUNCTION__, (array) $boxes, true ) ) {
			return;
		}

		$message = noobrocket_notice_writing_permissions( trim( str_replace( ABSPATH, '', NOOBROCKET_CACHE_PATH ), '/' ) );

		noobrocket_notice_html(
			[
				'status'      => 'error',
				'dismissible' => '',
				'message'     => $message,
			]
		);
	}
}
add_action( 'admin_notices', 'noobrocket_warning_cache_dir_permissions' );

/**
 * This warning is displayed when the minify cache dir isn't writeable
 *
 * @since 2.1
 */
function noobrocket_warning_minify_cache_dir_permissions() {
	if ( current_user_can( 'noobrocket_manage_options' )
		&& ( ! noobrocket_direct_filesystem()->is_writable( NOOBROCKET_MINIFY_CACHE_PATH ) )
		&& ( get_noobrocket_option( 'minify_css', false ) || get_noobrocket_option( 'minify_js', false ) )
		&& noobrocket_valid_key() ) {

		$boxes = get_user_meta( get_current_user_id(), 'noobrocket_boxes', true );

		if ( in_array( __FUNCTION__, (array) $boxes, true ) ) {
			return;
		}

		$message = noobrocket_notice_writing_permissions( trim( str_replace( ABSPATH, '', NOOBROCKET_MINIFY_CACHE_PATH ), '/' ) );

		noobrocket_notice_html(
			[
				'status'      => 'error',
				'dismissible' => '',
				'message'     => $message,
			]
		);
	}
}
add_action( 'admin_notices', 'noobrocket_warning_minify_cache_dir_permissions' );

/**
 * This warning is displayed when the busting cache dir isn't writeable
 *
 * @since 2.9
 */
function noobrocket_warning_busting_cache_dir_permissions() {
	if ( current_user_can( 'noobrocket_manage_options' )
		&& ( ! noobrocket_direct_filesystem()->is_writable( NOOBROCKET_CACHE_BUSTING_PATH ) )
		&& noobrocket_valid_key() ) {

		$boxes = get_user_meta( get_current_user_id(), 'noobrocket_boxes', true );

		if ( in_array( __FUNCTION__, (array) $boxes, true ) ) {
			return;
		}

		$message = noobrocket_notice_writing_permissions( trim( str_replace( ABSPATH, '', NOOBROCKET_CACHE_BUSTING_PATH ), '/' ) );

		noobrocket_notice_html(
			[
				'status'      => 'error',
				'dismissible' => '',
				'message'     => $message,
			]
		);
	}
}
add_action( 'admin_notices', 'noobrocket_warning_busting_cache_dir_permissions' );


/**
 * Displays a notice for analytics opt-in
 *
 * @since 2.11
 * @author Remy Perona
 */
function noobrocket_analytics_optin_notice() {

	$screen = get_current_screen();

	if ( ! current_user_can( 'noobrocket_manage_options' ) ) {
		return;
	}

	if ( 'settings_page_noobrocket' !== $screen->id ) {
		return;
	}

	if ( 1 === (int) get_option( 'noobrocket_analytics_notice_displayed' ) ) {
		return;
	}

	if ( get_option( 'noobrocket_mixpanel_optin' ) ) {
		return;
	}

	$analytics_notice = sprintf(
		// Opening <p> provided by noobrocket_notice_html().
		'<strong>%1$s</strong><br>%2$s</p>',
		__( 'Would you allow Cache-Cache to collect non-sensitive diagnostic data from this website?', 'noobrocket' ),
		__( 'This would help us to improve Cache-Cache for you in the future.', 'noobrocket' )
	);

	$analytics_notice .= sprintf(
		'<p><button class="hide-if-no-js button-noobrocket-reveal noobrocket-preview-analytics-data">%s</button></p>',
		/* translators: button text, click will expand data collection preview */
		__( 'What info will we collect?', 'noobrocket' )
	);

	$analytics_notice .= sprintf(
		'<div class="noobrocket-analytics-data-container"><p class="description">%1$s</p>%2$s</div>',
		__( 'Below is a detailed view of all data Cache-Cache will collect if granted permission. Cache-Cache will never transmit any domain names or email addresses (except for license validation), IP addresses, or third-party API keys.', 'noobrocket' ),
		noobrocket_data_collection_preview_table()
	);

	$analytics_notice .= sprintf(
		'<p><a href="%1$s" class="button button-primary">%2$s</a> <a href="%3$s" class="button button-secondary">%4$s</a>',
		// Closing </p> provided by noobrocket_notice_html().
		wp_nonce_url( admin_url( 'admin-post.php?action=noobrocket_analytics_optin&value=yes' ), 'analytics_optin' ),
		/* translators: button text for data collection opt-in */
		__( 'Yes, allow', 'noobrocket' ),
		wp_nonce_url( admin_url( 'admin-post.php?action=noobrocket_analytics_optin&value=no' ), 'analytics_optin' ),
		/* translators: button text for data collection opt-in */
		__( 'No, thanks', 'noobrocket' )
	);

	// Status should be as neutral as possible; nothing has happened yet.
	noobrocket_notice_html(
		[
			'status'  => 'info',
			'message' => $analytics_notice,
		]
	);
}

/**
 * Displays a notice after analytics opt-in
 *
 * @since 2.11
 * @author Remy Perona
 */
function noobrocket_analytics_optin_thankyou_notice() {
	$screen = get_current_screen();

	if ( ! current_user_can( 'noobrocket_manage_options' ) ) {
		return;
	}

	if ( 'settings_page_noobrocket' !== $screen->id ) {
		return;
	}

	$analytics_optin = get_transient( 'noobrocket_analytics_optin' );

	if ( ! $analytics_optin ) {
		return;
	}

	$thankyou_message = sprintf(
		/* translators: %1$s = opening strong tag; %2$s = closing strong tag; %3$s = plugin name */
		esc_html__( '%1$sThank you!%2$s The data you share helps us improve %3$s.', 'noobrocket' ),
		'<strong>',
		'</strong>',
		noobrocket_get_constant( 'NOOBROCKET_PLUGIN_NAME' ),
	);

	noobrocket_notice_html(
		[
			'message' => $thankyou_message,
		]
	);

	delete_transient( 'noobrocket_analytics_optin' );
}

/**
 * Displays a notice after clearing the cache
 *
 * @since 2.11
 * @author Remy Perona
 */
function noobrocket_clear_cache_notice() {
	$cleared_cache = get_transient( 'noobrocket_clear_cache' );

	if ( ! $cleared_cache ) {
		return;
	}

	delete_transient( 'noobrocket_clear_cache' );

	$notice = '';

	switch ( $cleared_cache ) {
		case 'all':
			if ( current_user_can( 'noobrocket_purge_cache' ) ) {
				// translators: %s = plugin name.
				$notice  = sprintf( __( '%s: Cache cleared.', 'noobrocket' ), '<strong>' . NOOBROCKET_PLUGIN_NAME . '</strong>' );
				$notice .= '<em> (' . date_i18n( get_option( 'date_format' ) ) . ' @ ' . date_i18n( get_option( 'time_format' ) ) . ') </em>';
			}
			break;
		case 'post':
			if ( current_user_can( 'noobrocket_purge_posts' ) ) {
				// translators: %s = plugin name.
				$notice  = sprintf( __( '%s: Post cache cleared.', 'noobrocket' ), '<strong>' . NOOBROCKET_PLUGIN_NAME . '</strong>' );
				$notice .= '<em> (' . date_i18n( get_option( 'date_format' ) ) . ' @ ' . date_i18n( get_option( 'time_format' ) ) . ') </em>';
			}
			break;
		case 'term':
			if ( current_user_can( 'noobrocket_purge_terms' ) ) {
				// translators: %s = plugin name.
				$notice  = sprintf( __( '%s: Term cache cleared.', 'noobrocket' ), '<strong>' . NOOBROCKET_PLUGIN_NAME . '</strong>' );
				$notice .= '<em> (' . date_i18n( get_option( 'date_format' ) ) . ' @ ' . date_i18n( get_option( 'time_format' ) ) . ') </em>';
			}
			break;
		case 'user':
			if ( current_user_can( 'noobrocket_purge_users' ) ) {
				// translators: %s = plugin name).
				$notice  = sprintf( __( '%s: User cache cleared.', 'noobrocket' ), '<strong>' . NOOBROCKET_PLUGIN_NAME . '</strong>' );
				$notice .= '<em> (' . date_i18n( get_option( 'date_format' ) ) . ' @ ' . date_i18n( get_option( 'time_format' ) ) . ') </em>';
			}
			break;
		default:
			break;
	}

	if ( empty( $notice ) ) {
		return;
	}

	noobrocket_notice_html(
		[
			'message' => $notice,
		]
	);
}
add_action( 'admin_notices', 'noobrocket_clear_cache_notice' );

/**
 * Outputs notice HTML
 *
 * @since 2.11
 * @author Remy Perona
 *
 * @param array $args An array of arguments used to determine the notice output.
 * @return void
 */
function noobrocket_notice_html( $args ) {
	$defaults = [
		'status'                 => 'success',
		'dismissible'            => 'is-dismissible',
		'message'                => '',
		'action'                 => '',
		'dismiss_button'         => false,
		'dismiss_button_message' => __( 'Dismiss this notice', 'noobrocket' ),
		'readonly_content'       => '',
		'id'                     => '',
		'class_prefix'           => '',
		'persistent'             => false,
	];

	$args = wp_parse_args( $args, $defaults );

	switch ( $args['action'] ) {
		case 'clear_cache':
			$args['action'] = '<a class="wp-core-ui button" href="' . wp_nonce_url( admin_url( 'admin-post.php?action=purge_cache&type=all' ), 'purge_cache_all' ) . '">' . __( 'Clear cache', 'noobrocket' ) . '</a>';
			break;
		case 'clear_used_css':
			$params = [
				'action' => 'noobrocket_clean_saas',
			];

			if ( ! empty( $_SERVER['REQUEST_URI'] ) ) {
				$referer_url                = filter_var( wp_unslash( $_SERVER['REQUEST_URI'] ), FILTER_SANITIZE_URL );
				$params['_wp_http_referer'] = rawurlencode( $referer_url );
			}
			$args['action'] = '<a class="wp-core-ui button" href="' . add_query_arg( $params, wp_nonce_url( admin_url( 'admin-post.php' ), $params['action'] ) ) . '">' . __( 'Clear Used CSS', 'noobrocket' ) . '</a>';
			break;
		case 'stop_preload':
			$args['action'] = '<a class="wp-core-ui button" href="' . wp_nonce_url( admin_url( 'admin-post.php?action=noobrocket_stop_preload&type=all' ), 'noobrocket_stop_preload' ) . '">' . __( 'Stop Preload', 'noobrocket' ) . '</a>';
			break;
		case 'switch_to_rucss':
			$params         = [
				'action' => 'switch_to_rucss',
			];
			$args['action'] = '<a class="wp-core-ui button" href="' . add_query_arg( $params, wp_nonce_url( admin_url( 'admin-post.php' ), 'rucss_switch' ) ) . '">' . __( 'Turn on Remove Unused CSS', 'noobrocket' ) . '</a>';
			break;
		case 'enable_separate_mobile_cache':
			$params         = [
				'action' => 'noobrocket_enable_separate_mobile_cache',
			];
			$args['action'] = '<a class="wp-core-ui button" href="' . add_query_arg( $params, wp_nonce_url( admin_url( 'admin-post.php' ), 'noobrocket_enable_separate_mobile_cache' ) ) . '">' . __( 'Enable “Separate Cache Files for Mobile Devices” now', 'noobrocket' ) . '</a>';
			break;
		case 'force_deactivation':
			/**
			 * Allow a "force deactivation" link to be printed, use at your own risks
			 *
			 * @since 2.0.0
			 *
			 * @param bool $permit_force_deactivation true will print the link.
			 */
			$permit_force_deactivation = apply_filters( 'noobrocket_permit_force_deactivation', true );

			// We add a link to permit "force deactivation", use at your own risks.
			if ( $permit_force_deactivation ) {
				global $status, $page, $s;
				$plugin_file  = 'noobrocket/noobrocket.php';
				$noobrocket_nonce = wp_create_nonce( 'force_deactivation' );

				$args['action'] = '<a href="' . wp_nonce_url( 'plugins.php?action=deactivate&amp;noobrocket_nonce=' . $noobrocket_nonce . '&amp;plugin=' . $plugin_file . '&amp;plugin_status=' . $status . '&amp;paged=' . $page . '&amp;s=' . $s, 'deactivate-plugin_' . $plugin_file ) . '">' . __( 'Force deactivation ', 'noobrocket' ) . '</a>';
			}
			break;
		case 'noobrocket_insights_page':
			$args['action'] = '<a class="button button-primary" href="' . admin_url( 'options-general.php?page=' . NOOBROCKET_PLUGIN_SLUG . '&noobrocket_source=notice_insights_promotion_notice#noobrocket_insights' ) . '">' . __( 'Run the test now!', 'noobrocket' ) . '</a>';
			break;
		case 'noobrocketcdn_upgrade_page':
			$dismiss_key    = 'noobrocket_update_notice';
			$redirect_url   = admin_url( 'options-general.php?page=' . NOOBROCKET_PLUGIN_SLUG . '&noobrocket_source=notice_noobrocketcdn_upgrade#page_cdn' );
			$dismiss_url    = wp_nonce_url(
				admin_url(
					'admin-post.php?action=noobrocket_ignore&box=' . $dismiss_key
					. '&redirect=' . rawurlencode( $redirect_url )
				),
				'noobrocket_ignore_' . $dismiss_key
			);
			$args['action'] = '<a class="button button-primary" href="' . esc_url( $dismiss_url ) . '">'
				. __( 'Add your pages now', 'noobrocket' )
				. '</a>';
			break;
		case 'noobrocketcdn_install_page':
			$dismiss_key    = 'noobrocketcdn_install_notice';
			$redirect_url   = admin_url( 'options-general.php?page=' . NOOBROCKET_PLUGIN_SLUG . '&noobrocket_source=notice_noobrocketcdn_promotion#page_cdn' );
			$dismiss_url    = wp_nonce_url(
				admin_url(
					'admin-post.php?action=noobrocket_ignore&box=' . $dismiss_key
					. '&redirect=' . rawurlencode( $redirect_url )
				),
				'noobrocket_ignore_' . $dismiss_key
			);
			$args['action'] = '<a class="button button-primary" href="' . esc_url( $dismiss_url ) . '">'
				. __( 'Start with my homepage', 'noobrocket' )
				. '</a>';
			break;
	}
	/**
	 * Notice arguments.
	 *
	 * @param array $args arguments from the notice.
	 * @return array
	 */
	$filtered_args = apply_filters( 'noobrocket_notice_args', $args );

	if ( is_array( $filtered_args ) ) {
		$args = wp_parse_args( $filtered_args, $defaults );
	}

	$notice_id = '';

	if ( ! empty( $args['id'] ) ) {
		$notice_id = ' id="' . esc_attr( $args['id'] ) . '"';
	}

	?>
	<div data-noobrocket-notice data-noobrocket-dismissible="<?php echo empty( $args['dismissible'] ) ? 'false' : 'true'; ?>"<?php echo ! empty( $args['persistent'] ) ? ' data-noobrocket-persistent' : ''; ?> class="<?php echo esc_attr( $args['class_prefix'] ); ?>notice notice-<?php echo esc_attr( $args['status'] ); ?> <?php echo esc_attr( $args['dismissible'] ); ?>"<?php echo $notice_id; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<?php
			$tag = 0 !== strpos( $args['message'], '<p' ) && 0 !== strpos( $args['message'], '<ul' );

			echo ( $tag ? '<p>' : '' ) . $args['message'] . ( $tag ? '</p>' : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view.
		?>
		<?php if ( ! empty( $args['readonly_content'] ) ) : ?>
		<?php $code_id = wp_unique_id( 'noobrocket-notice-code-' ); ?>
		<p><label for="<?php echo esc_attr( $code_id ); ?>"><?php esc_html_e( 'The following code should have been written to this file:', 'noobrocket' ); ?></label>
			<br><textarea readonly="readonly" id="<?php echo esc_attr( $code_id ); ?>" name="rules" class="large-text readonly" rows="6"><?php echo esc_textarea( $args['readonly_content'] ); ?></textarea>
		</p>
			<?php
		endif;
		if ( $args['action'] || $args['dismiss_button'] ) :
			?>
		<p>
			<?php echo $args['action']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php if ( $args['dismiss_button'] ) : ?>
			<a class="noobrocket-dismiss <?php echo $args['dismiss_button_class'] ?? ''; ?>" href="<?php echo wp_nonce_url( admin_url( 'admin-post.php?action=noobrocket_ignore&box=' . $args['dismiss_button'] ), 'noobrocket_ignore_' . $args['dismiss_button'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"><?php echo esc_html( $args['dismiss_button_message'] ); ?></a>
			<?php endif; ?>
		</p>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Outputs formatted notice about issues with writing permissions
 *
 * @since  2.11
 * @author Caspar Hübinger
 *
 * @param  string $file File or folder name.
 * @return string       Message HTML
 */
function noobrocket_notice_writing_permissions( $file ) {

	$message = sprintf(
		// translators: %s = plugin name.
		__( '%s cannot configure itself due to missing writing permissions.', 'noobrocket' ),
		'<strong>' . NOOBROCKET_PLUGIN_NAME . '</strong>'
	);

	$message .= '<br>' . sprintf(
		/* translators: %s = file/folder name */
		__( 'Affected file/folder: %s', 'noobrocket' ),
		'<code>' . $file . '</code>'
	);

	$message .= '<br>' . sprintf(
		/* translators: This is a doc title! %1$s = opening link; %2$s = closing link */
		__( 'Troubleshoot: %1$sHow to make system files writeable%2$s', 'noobrocket' ),
		/* translators: Documentation exists in EN, DE, FR, ES, IT; use loaclised URL if applicable */
		'<a href="' . get_noobrocket_documentation_url() . '" target="_blank">',
		'</a>'
	);

	return $message;
}
