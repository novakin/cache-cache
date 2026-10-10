<?php
/**
 * Real WordPress acceptance checks. Use only with an isolated, disposable site.
 * php tests/wordpress-acceptance.php /absolute/path/to/wordpress activate|boot|hooks|settings|import|import-validation|render|cdn|minify-purge|release-update|health|abilities|branding|veto|deactivate|uninstall|collision|restore
 */
$wordpress = isset( $argv[1] ) ? realpath( $argv[1] ) : false;
$phase = $argv[2] ?? '';
if ( ! $wordpress || ! is_file( $wordpress . '/wp-load.php' ) ) {
	fwrite( STDERR, "Provide an isolated WordPress installation path.\n" );
	exit( 2 );
}
if ( ! is_file( $wordpress . '/.noobrocket-acceptance-site' ) ) {
	fwrite( STDERR, "Refusing lifecycle tests without the disposable-site marker .noobrocket-acceptance-site.\n" );
	exit( 2 );
}
putenv( 'NOOBROCKET_TEST_PHASE=' . $phase );
$_SERVER['HTTP_HOST'] = '127.0.0.1:8765';
$_SERVER['REQUEST_URI'] = '/';
// Bypass early homepage delivery so a warm cache cannot skip the assertions.
$_SERVER['REQUEST_METHOD'] = 'POST';
define( 'WP_ADMIN', true );
require $wordpress . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
wp_set_current_user( 1 );
function acceptance_check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( 'FAIL: ' . $message );
	}
	echo 'PASS: ', $message, PHP_EOL;
}
function acceptance_original_preload_status() {
	global $wpdb;
	return $wpdb->get_var( $wpdb->prepare(
		"SELECT a.status FROM {$wpdb->prefix}actionscheduler_actions a INNER JOIN {$wpdb->prefix}actionscheduler_groups g ON a.group_id = g.group_id WHERE a.hook = %s AND g.slug = %s ORDER BY a.action_id DESC LIMIT 1",
		'acceptance_original_rocket_preload',
		'rocket-preload'
	) );
}
function acceptance_run_import_worker( $wordpress, $phase ) {
	$php = getenv( 'NOOBROCKET_TEST_PHP' ) ?: PHP_BINARY;
	$worker = proc_open( [ $php, __FILE__, $wordpress, $phase ], [ 0 => [ 'pipe', 'r' ], 1 => [ 'pipe', 'w' ], 2 => [ 'redirect', 1 ] ], $pipes );
	if ( ! is_resource( $worker ) ) {
		throw new RuntimeException( 'Cannot start native import worker.' );
	}
	fclose( $pipes[0] );
	echo stream_get_contents( $pipes[1] );
	fclose( $pipes[1] );
	acceptance_check( 0 === proc_close( $worker ), 'native import worker completes: ' . $phase );
}
$plugin = 'noobrocket/noobrocket.php';
$sentinel_settings = [ 'acceptance_sentinel' => 'original-wp-rocket' ];
$sentinel_cache = WP_CONTENT_DIR . '/cache/wp-rocket/acceptance-sentinel.txt';
$sentinel_promo = 'original-plugin-family-promotion-sentinel';
$sentinel_cron_args = [];
$sentinel_cron_context = [ 'WP Cron' ];
switch ( $phase ) {
	case 'activate':
		update_option( 'wp_rocket_settings', $sentinel_settings );
		update_option( 'plugin_family_dismiss_promote_imagify', $sentinel_promo );
		if ( ! wp_next_scheduled( 'action_scheduler_run_queue_rucss', $sentinel_cron_args ) ) {
			wp_schedule_single_event( time() + 3600, 'action_scheduler_run_queue_rucss', $sentinel_cron_args );
		}
		if ( ! wp_next_scheduled( 'action_scheduler_run_queue_rucss', $sentinel_cron_context ) ) {
			wp_schedule_single_event( time() + 3600, 'action_scheduler_run_queue_rucss', $sentinel_cron_context );
		}
		wp_mkdir_p( dirname( $sentinel_cache ) );
		file_put_contents( $sentinel_cache, 'original-wp-rocket' );
		$result = activate_plugin( $plugin );
		acceptance_check( ! is_wp_error( $result ), 'plugin activation succeeds' );
		acceptance_check( is_plugin_active( $plugin ), 'NoobRocket active' );
		if ( function_exists( 'as_schedule_single_action' ) && acceptance_original_preload_status() !== 'pending' ) {
			as_schedule_single_action( time() + 3600, 'acceptance_original_rocket_preload', [], 'rocket-preload' );
		}
		acceptance_check( acceptance_original_preload_status() === 'pending', 'original noobrocket-preload action sentinel pending' );
		acceptance_check( get_option( 'wp_rocket_settings' ) === $sentinel_settings, 'activation preserves WP Rocket settings' );
		acceptance_check( file_get_contents( $sentinel_cache ) === 'original-wp-rocket', 'activation preserves WP Rocket cache' );
		acceptance_check( get_option( 'plugin_family_dismiss_promote_imagify' ) === $sentinel_promo && wp_next_scheduled( 'action_scheduler_run_queue_rucss', $sentinel_cron_args ) !== false && wp_next_scheduled( 'action_scheduler_run_queue_rucss', $sentinel_cron_context ) !== false, 'activation preserves shared plugin-family option and WP Rocket RUCSS events' );
		break;
	case 'boot':
		do_action( 'admin_init' );
		acceptance_check( function_exists( 'noobrocket_valid_key' ) && noobrocket_valid_key(), 'local optimization enabled without license' );
		acceptance_check( ! function_exists( 'rocket_valid_key' ) && ! defined( 'WP_ROCKET_VERSION' ), 'upstream globals not registered' );
		acceptance_check( NOOBROCKET_VERSION === '0.1.0-beta.2' && NOOBROCKET_ENGINE_VERSION === '3.23.5.1', 'beta product and engine versions separated' );
		$settings = get_option( 'noobrocket_settings' );
		acceptance_check( is_array( $settings ), 'NoobRocket settings initialized' );
		acceptance_check( empty( $settings['consumer_key'] ) && empty( $settings['secret_key'] ), 'no license credentials required' );
		acceptance_check( is_file( WP_CONTENT_DIR . '/advanced-cache.php' ), 'cache drop-in generated' );
		acceptance_check( strpos( file_get_contents( WP_CONTENT_DIR . '/advanced-cache.php' ), 'NoobRocket' ) !== false, 'cache drop-in owned by NoobRocket' );
		acceptance_check( is_dir( NOOBROCKET_CACHE_PATH ) && is_dir( NOOBROCKET_CONFIG_PATH ), 'isolated cache and config directories created' );
		break;
	case 'hooks':
		$registered = [];
		foreach ( $GLOBALS['wp_filter'] as $hook => $dispatcher ) {
			foreach ( $dispatcher->callbacks as $callbacks ) {
				foreach ( $callbacks as $callback ) {
					$function = $callback['function'];
					if ( is_array( $function ) ) {
						$class = is_object( $function[0] ) ? get_class( $function[0] ) : $function[0];
						if ( strpos( $class, 'NoobRocket\\' ) === 0 ) {
							$registered[] = $hook . ' => ' . $class . '::' . $function[1];
						}
					}
				}
			}
		}
		$container = apply_filters( 'noobrocket_container', null );
		acceptance_check( ! $container->has( 'saas_admin_subscriber' ), 'unused SaaS admin provider is no longer registered' );
		acceptance_check( $container->has( 'local_css_subscriber' ) && $container->has( 'performance_hints_admin_subscriber' ), 'local CSS and performance-hint services remain registered' );
		foreach ( [ 'Engine\\Plugin\\Updater', 'Engine\\License\\', 'Engine\\Optimization\\RUCSS\\', 'Engine\\Admin\\PerformanceMonitoring\\', 'Engine\\Tracking\\', 'Engine\\Saas\\', 'Engine\\CDN\\HostedCDN\\' ] as $prefix ) {
			$matches = array_filter( $registered, function ( $callback ) use ( $prefix ) { return strpos( $callback, 'NoobRocket\\' . $prefix ) !== false && strpos( $callback, '\\Database\\' ) === false; } );
			acceptance_check( ! $matches, 'vendor/cloud/telemetry callbacks absent: ' . $prefix . ( $matches ? '; registered: ' . implode( ', ', array_slice( $matches, 0, 6 ) ) : '' ) );
		}
		foreach ( [ 'schedule_lists_update', 'update_lists', 'register_rest_route', 'display_update_lists_section' ] as $method ) {
			$matches = array_filter( $registered, function ( $callback ) use ( $method ) { return strpos( $callback, 'NoobRocket\\Engine\\Optimization\\DynamicLists\\Subscriber::' . $method ) !== false && substr( $callback, -strlen( '::' . $method ) ) === '::' . $method; } );
			acceptance_check( ! $matches, 'remote dynamic list callback absent: ' . $method );
		}
		$routes = rest_get_server()->get_routes();
		acceptance_check( isset( $routes['/noobrocket/v1/options/export'] ), 'local export REST route registered' );
		acceptance_check( ! isset( $routes['/noobrocket/v1/dynamic_lists/update'] ) && ! isset( $routes['/rocket/v1/options/export'] ), 'remote dynamic list and upstream export routes absent' );
		echo 'NoobRocket class callbacks checked: ', count( $registered ), PHP_EOL;
		break;
	case 'settings':
		$settings = get_option( 'noobrocket_settings' );
		$settings['remove_unused_css'] = 1;
		$settings['async_css'] = 1;
		$settings['performance_monitoring'] = 1;
		$settings['analytics_enabled'] = 1;
		$settings['consumer_key'] = 'test-import-key';
		$settings['consumer_email'] = 'test-import@example.test';
		$settings['secret_key'] = 'test-import-secret';
		$settings['license'] = 1;
		$settings['cdn_state'] = 'rocketcdn';
		$settings['acceptance_setting'] = 'isolated';
		update_option( 'noobrocket_settings', $settings );
		$saved = get_option( 'noobrocket_settings' );
		foreach ( [ 'remove_unused_css', 'async_css', 'performance_monitoring', 'analytics_enabled' ] as $key ) {
			acceptance_check( empty( $saved[ $key ] ), 'imported cloud setting disabled: ' . $key );
		}
		foreach ( [ 'consumer_key', 'consumer_email', 'secret_key', 'license' ] as $key ) {
			acceptance_check( ! isset( $saved[ $key ] ), 'imported credential removed: ' . $key );
		}
		acceptance_check( $saved['cdn_state'] !== 'rocketcdn' && $saved['cdn_state'] !== 'noobrocketcdn', 'import cannot enable vendor CDN' );
		acceptance_check( $saved['acceptance_setting'] === 'isolated' && get_option( 'wp_rocket_settings' ) === $sentinel_settings, 'settings writes isolated from WP Rocket' );
		break;
	case 'render':
		require_once ABSPATH . 'wp-admin/includes/admin.php';
		$_GET['page'] = 'noobrocket';
		set_current_screen( 'settings_page_noobrocket' );
		do_action( 'admin_init' );
		$container = apply_filters( 'noobrocket_container', null );
		ob_start();
		$container->get( 'settings_page' )->render_page();
		$html = ob_get_clean();
		$help = $container->get( 'beacon' );
		acceptance_check( is_string( $help->get_suggest( 'css_section' ) ), 'section help keeps the native string identifier contract' );
		acceptance_check( $help->get_suggest( 'webp' )['url'] === get_noobrocket_documentation_url(), 'field help uses bundled documentation' );
		$faq = $help->get_suggest( 'faq' );
		acceptance_check( isset( $faq[0]['title'], $faq[0]['url'] ) && strpos( $faq[0]['url'], NOOBROCKET_URL ) === 0, 'FAQ help keeps a translated list of local links' );
		acceptance_check( strpos( $html, 'noobrocket_settings[cdn_cnames]' ) !== false, 'settings renders manual CDN CNAME fields' );
		acceptance_check( substr_count( $html, 'name="noobrocket_settings[cdn]"' ) === 1, 'manual CDN value is submitted once' );
		acceptance_check( strpos( $html, 'noobrocket_settings[engine_version]' ) !== false, 'settings preserves engine migration metadata' );
		acceptance_check( strpos( $html, 'noobrocket_settings[consumer_key]' ) === false && strpos( $html, 'noobrocket_settings[secret_key]' ) === false, 'settings has no license fields' );
		acceptance_check( strpos( $html, 'Cache-Cache' ) !== false && strlen( $html ) > 10000, 'complete Cache-Cache settings page renders' );
		echo 'Rendered settings bytes: ', strlen( $html ), PHP_EOL;
		break;
	case 'import':
		do_action( 'admin_init' );
		$original = get_option( 'noobrocket_settings' );
		list( $filename, $json ) = noobrocket_export_options();
		$exported = json_decode( $json, true );
		acceptance_check( is_array( $exported ) && ! isset( $exported['consumer_key'], $exported['secret_key'] ), 'export produces local settings without license credentials' );
		$input = $exported;
		$input['lazyload'] = empty( $original['lazyload'] ) ? 1 : 0;
		$input['version'] = '0.1.0';
		$input['engine_version'] = '0.1.0';
		$input['secret_cache_key'] = 'foreign-site-key';
		$input['remove_unused_css'] = 1;
		$input['consumer_key'] = 'foreign-license';
		$input['submit_optimize'] = 1;
		$review_key = 'noobrocket_import_review_' . get_current_user_id();
		$tmp_file = wp_tempnam( $filename );
		file_put_contents( $tmp_file, wp_json_encode( $input ) );
		$_FILES['import'] = [ 'name' => $filename, 'tmp_name' => $tmp_file, 'size' => filesize( $tmp_file ), 'type' => 'application/json', 'error' => UPLOAD_ERR_OK ];
		$_POST['action'] = 'noobrocket_import_settings';
		$_REQUEST['noobrocket_import_settings_nonce'] = wp_create_nonce( 'noobrocket_import_settings' );
		$_SERVER['HTTP_REFERER'] = admin_url( 'options-general.php?page=noobrocket' );
		$stop_redirect = function () { throw new LogicException( 'acceptance-import-redirect' ); };
		add_filter( 'wp_redirect', $stop_redirect );
		set_error_handler( function ( $severity, $message, $file, $line ) {
			throw new ErrorException( $message, 0, $severity, $file, $line );
		}, E_WARNING | E_NOTICE );
		try {
			try {
				noobrocket_handle_settings_import();
			} catch ( LogicException $redirect ) {
				if ( $redirect->getMessage() !== 'acceptance-import-redirect' ) { throw $redirect; }
			}
			$review = get_transient( $review_key );
			acceptance_check( is_array( $review ) && get_option( 'noobrocket_settings' ) === $original, 'JSON upload creates a review without replacing saved settings' );
			acceptance_check( ! isset( $review['settings']['submit_optimize'] ), 'JSON import cannot carry a cleanup action' );
			$_FILES = [];
			$_POST['noobrocket_import_review'] = $review['token'];
			try {
				noobrocket_handle_settings_import();
			} catch ( LogicException $redirect ) {
				if ( $redirect->getMessage() !== 'acceptance-import-redirect' ) { throw $redirect; }
			}
			$errors = get_settings_errors();
			acceptance_check( end( $errors )['type'] === 'updated', 'real JSON upload/import succeeds without warnings' );
			$saved = get_option( 'noobrocket_settings' );
			acceptance_check( $saved['lazyload'] === $input['lazyload'], 'JSON import applies local optimization setting' );
			foreach ( [ 'version', 'engine_version', 'secret_cache_key', 'minify_css_key', 'minify_js_key' ] as $key ) {
				acceptance_check( $saved[ $key ] === $original[ $key ], 'JSON import preserves site metadata: ' . $key );
			}
			acceptance_check( empty( $saved['remove_unused_css'] ) && ! isset( $saved['consumer_key'] ), 'real JSON import strips unsupported cloud/account settings' );
			acceptance_check( get_option( 'wp_rocket_settings' ) === $sentinel_settings, 'real JSON import preserves WP Rocket settings' );
		} finally {
			restore_error_handler();
			remove_filter( 'wp_redirect', $stop_redirect );
			update_option( 'noobrocket_settings', $original );
			if ( is_file( $tmp_file ) ) { unlink( $tmp_file ); }
			delete_transient( 'settings_errors' );
			delete_transient( $review_key );
		}
		break;
	case 'import-validation':
		do_action( 'admin_init' );
		$original = get_option( 'noobrocket_settings' );
		$review_key = 'noobrocket_import_review_' . get_current_user_id();
		$seeded = array_merge( $original, [
			'dns_prefetch'     => [ '//saved.example.test' ],
			'exclude_css'      => [ '/native-saved.css' ],
			'cache_reject_uri' => [ '/native-saved/' ],
		] );
		try {
			update_option( 'noobrocket_settings', $seeded );
			$saved = get_option( 'noobrocket_settings' );
			foreach ( [ 'dns_prefetch', 'exclude_css', 'cache_reject_uri' ] as $field ) {
				acceptance_check( $seeded[ $field ] === $saved[ $field ], 'native saved list sentinel seeded: ' . $field );
			}
			delete_transient( 'settings_errors' );
			delete_transient( $review_key );
			// New boots load the persisted sentinels into the injected Options_Data snapshot.
			acceptance_run_import_worker( $wordpress, 'import-validation-stage' );
			acceptance_run_import_worker( $wordpress, 'import-validation-consume' );
		} finally {
			// Restore only this owned row; a normal save rotates CSS keys and the parent's cache predates the workers.
			global $wpdb;
			if ( false === $wpdb->update( $wpdb->options, [ 'option_value' => maybe_serialize( $original ) ], [ 'option_name' => 'noobrocket_settings' ] ) ) {
				throw new RuntimeException( 'Native import fixture could not restore its owned settings value.' );
			}
			foreach ( [ 'alloptions', 'notoptions', 'noobrocket_settings' ] as $cache_key ) { wp_cache_delete( $cache_key, 'options' ); }
			flush_noobrocket_htaccess( ! noobrocket_valid_key() );
			noobrocket_generate_config_file();
			delete_transient( 'settings_errors' );
			delete_transient( $review_key );
			delete_transient( 'noobrocket_acceptance_import_redirect' );
		}
		acceptance_check( $original === get_option( 'noobrocket_settings' ), 'native import proof restores original settings' );
		break;
	case 'import-validation-stage':
		do_action( 'admin_init' );
		$original = get_option( 'noobrocket_settings' );
		$review_key = 'noobrocket_import_review_' . get_current_user_id();
		acceptance_check( false !== has_action( 'admin_post_noobrocket_import_settings', 'noobrocket_handle_settings_import' ), 'native import handler remains registered' );
		list( $filename, $json ) = noobrocket_export_options();
		$input = json_decode( $json, true );
		foreach ( [ 'dns_prefetch', 'exclude_css', 'cache_reject_uri' ] as $field ) {
			$input[ $field ] = [ [ 'nested' ] ];
		}
		$input['heartbeat_admin_behavior'] = 'disable' === $original['heartbeat_admin_behavior'] ? 'reduce_periodicity' : 'disable';
		foreach ( [ 'version', 'engine_version', 'secret_cache_key', 'minify_css_key', 'minify_js_key' ] as $key ) {
			$input[ $key ] = 'foreign-import-value';
		}
		$tmp_file = wp_tempnam( $filename );
		file_put_contents( $tmp_file, wp_json_encode( $input ) );
		$_FILES['import'] = [ 'name' => $filename, 'tmp_name' => $tmp_file, 'size' => filesize( $tmp_file ), 'type' => 'application/json', 'error' => UPLOAD_ERR_OK ];
		$_POST['action'] = 'noobrocket_import_settings';
		$_REQUEST['noobrocket_import_settings_nonce'] = wp_create_nonce( 'noobrocket_import_settings' );
		$_SERVER['HTTP_REFERER'] = admin_url( 'options-general.php?page=noobrocket' );
		$redirect_url = null;
		$stop_redirect = function ( $location ) use ( &$redirect_url ) {
			$redirect_url = $location;
			throw new LogicException( 'acceptance-import-validation-redirect' );
		};
		add_filter( 'wp_redirect', $stop_redirect );
		set_error_handler( function ( $severity, $message, $file, $line ) {
			throw new ErrorException( $message, 0, $severity, $file, $line );
		}, E_WARNING | E_NOTICE );
		try {
			try {
				do_action( 'admin_post_noobrocket_import_settings' );
			} catch ( LogicException $redirect ) {
				if ( 'acceptance-import-validation-redirect' !== $redirect->getMessage() ) { throw $redirect; }
			}
			acceptance_check( is_string( $redirect_url ) && false !== strpos( $redirect_url, 'settings-updated=true' ) && '#tools' === substr( $redirect_url, -6 ), 'native malformed import redirects to review with notice consumption enabled' );
			$review = get_transient( $review_key );
			acceptance_check( is_array( $review ) && $original === get_option( 'noobrocket_settings' ) && $original === $review['current'], 'native malformed import stages review without changing saved settings' );
			foreach ( [ 'dns_prefetch', 'exclude_css', 'cache_reject_uri', 'version', 'engine_version', 'secret_cache_key', 'minify_css_key', 'minify_js_key' ] as $key ) {
				acceptance_check( $original[ $key ] === $review['settings'][ $key ], 'native malformed import retains saved field or protected identity: ' . $key );
			}
			acceptance_check( $input['heartbeat_admin_behavior'] === $review['settings']['heartbeat_admin_behavior'], 'native malformed import retains valid sibling change' );
			$errors = get_transient( 'settings_errors' );
			acceptance_check( is_array( $errors ) && 3 === count( $errors ), 'native field errors persist for the next request' );
			acceptance_check( set_transient( 'noobrocket_acceptance_import_redirect', $redirect_url, MINUTE_IN_SECONDS ), 'owned native redirect receipt persists' );
		} finally {
			restore_error_handler();
			remove_filter( 'wp_redirect', $stop_redirect );
			if ( is_file( $tmp_file ) ) { unlink( $tmp_file ); }
		}
		break;
	case 'import-validation-consume':
		do_action( 'admin_init' );
		$original = get_option( 'noobrocket_settings' );
		$review_key = 'noobrocket_import_review_' . get_current_user_id();
		$review = get_transient( $review_key );
		$redirect_url = get_transient( 'noobrocket_acceptance_import_redirect' );
		acceptance_check( is_string( $redirect_url ) && is_array( $review ), 'next native request reads staged import and its redirect' );
		parse_str( wp_parse_url( $redirect_url, PHP_URL_QUERY ), $_GET );
		ob_start();
		include ABSPATH . 'wp-admin/options-head.php';
		$html = ob_get_clean();
		$errors = get_settings_errors();
		acceptance_check( 3 === count( $errors ) && false === get_transient( 'settings_errors' ), 'native notice reader consumes persisted field errors once' );
		$codes = array_column( $errors, 'code' );
		sort( $codes );
		acceptance_check( [ 'invalid_list_cache_reject_uri', 'invalid_list_dns_prefetch', 'invalid_list_exclude_css' ] === $codes && [ 'error' ] === array_values( array_unique( array_column( $errors, 'type' ) ) ), 'next native request receives precise field errors without a saved notice' );
		foreach ( $codes as $code ) {
			acceptance_check( false !== strpos( $html, "id='setting-error-" . $code . "'" ), 'native notice renderer exposes retained validation feedback: ' . $code );
		}
		acceptance_check( $original === get_option( 'noobrocket_settings' ), 'native notice consumption does not save the staged proposal' );
		$GLOBALS['wp_settings_errors'] = [];
		unset( $_GET['settings-updated'] );
		$_FILES = [];
		$_POST = [ 'action' => 'noobrocket_import_settings', 'noobrocket_import_review' => $review['token'] ];
		$_REQUEST['noobrocket_import_settings_nonce'] = wp_create_nonce( 'noobrocket_import_settings' );
		$_SERVER['HTTP_REFERER'] = admin_url( 'options-general.php?page=noobrocket' );
		$stop_redirect = function () { throw new LogicException( 'acceptance-import-validation-confirm' ); };
		add_filter( 'wp_redirect', $stop_redirect );
		try {
			try {
				do_action( 'admin_post_noobrocket_import_settings' );
			} catch ( LogicException $redirect ) {
				if ( 'acceptance-import-validation-confirm' !== $redirect->getMessage() ) { throw $redirect; }
			}
			acceptance_check( $review['settings'] === get_option( 'noobrocket_settings' ) && false === get_transient( $review_key ), 'native confirmation saves exactly the reviewed proposal' );
			$errors = get_settings_errors();
			acceptance_check( 'updated' === end( $errors )['type'], 'native confirmation reports successful persistence' );
		} finally {
			remove_filter( 'wp_redirect', $stop_redirect );
		}
		break;
	case 'cdn':
		do_action( 'admin_init' );
		$original = get_option( 'noobrocket_settings' );
		$input = $original;
		$input['cdn'] = 1;
		$input['cdn_type'] = 'rocketcdn';
		$input['cdn_cnames'] = [ 'https://cdn.example.test/' ];
		$input['cdn_zone'] = [ 'all' ];
		update_option( 'noobrocket_settings', $input );
		$saved = get_option( 'noobrocket_settings' );
		acceptance_check( $saved['cdn'] === 1 && $saved['cdn_type'] === 'byocdn' && $saved['cdn_state'] === 'byocdn', 'CDN save enables only manual CDN rewriting' );
		acceptance_check( $saved['cdn_cnames'] === [ 'https://cdn.example.test' ] && $saved['cdn_zone'] === [ 'all' ], 'CDN CNAME and zone sanitized and saved' );
		acceptance_check( get_option( 'wp_rocket_settings' ) === $sentinel_settings, 'CDN save preserves WP Rocket settings' );
		update_option( 'noobrocket_settings', $original );
		break;
	case 'minify-purge':
		$fixture_dir = NOOBROCKET_MINIFY_CACHE_PATH . get_current_blog_id() . '/acceptance.minify-purge/';
		$other_dir = NOOBROCKET_MINIFY_CACHE_PATH . 'acceptance-other-site/';
		wp_mkdir_p( $fixture_dir );
		wp_mkdir_p( $other_dir );
		$fixtures = [ $fixture_dir . 'keep.css', $fixture_dir . 'purge.js', $fixture_dir . 'keep.txt', $other_dir . 'keep.js', $fixture_dir . 'keep.css.gz', $fixture_dir . 'purge.js.gz' ];
		try {
			foreach ( $fixtures as $file ) {
				file_put_contents( $file, 'acceptance minify purge fixture' );
			}
			noobrocket_clean_minify( 'js' );
			acceptance_check( ! file_exists( $fixtures[1] ), 'minify purge removes selected JS extension on native filesystem paths' );
			acceptance_check( ! file_exists( $fixtures[5] ) && file_exists( $fixtures[4] ), 'minify purge removes selected JS gzip companion and preserves CSS gzip' );
			acceptance_check( file_exists( $fixtures[0] ) && file_exists( $fixtures[2] ), 'minify purge preserves CSS and unrelated files' );
			acceptance_check( file_exists( $fixtures[3] ), 'minify purge preserves another site directory' );
			noobrocket_clean_minify( 'css' );
			acceptance_check( ! file_exists( $fixtures[0] ) && file_exists( $fixtures[2] ), 'minify purge removes CSS only when selected' );
			acceptance_check( ! file_exists( $fixtures[4] ), 'minify purge removes selected CSS gzip companion' );
		} finally {
			foreach ( $fixtures as $file ) {
				if ( is_file( $file ) ) {
					unlink( $file );
				}
			}
			rmdir( $fixture_dir );
			rmdir( $other_dir );
		}
		break;
	case 'release-update':
		$original = get_option( 'noobrocket_settings' );
		$input = $original;
		$input['version'] = '1.3.1';
		$input['engine_version'] = NOOBROCKET_ENGINE_VERSION;
		$custom = [ 'manual_preload' => 0, 'minify_css' => 0, 'minify_js' => 0, 'lazyload' => 1, 'defer_all_js' => 1 ];
		// Model settings persisted by an older release without running this release's save callbacks.
		global $wpdb;
		$wpdb->update( $wpdb->options, [ 'option_value' => maybe_serialize( array_merge( $input, $custom ) ) ], [ 'option_name' => 'noobrocket_settings' ] );
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'noobrocket_settings', 'options' );
		$upgrades_before = did_action( 'noobrocket_upgrade' );
		$list_caches = [ 'dynamic-lists' => 'nbr_dynamic_lists', 'dynamic-lists-delayjs' => 'nbr_dynamic_lists_delayjs', 'dynamic-lists-incompatible-plugins' => 'nbr_dynamic_lists_incompatible_plugins' ];
		foreach ( $list_caches as $name => $transient ) {
			file_put_contents( NOOBROCKET_CONFIG_PATH . $name . '.json', '{"acceptance_stale":true}' );
			set_transient( $transient, (object) [ 'acceptance_stale' => true ] );
		}
		$site_url = get_noobrocket_parse_url( home_url( '/' ) );
		$release_page = NOOBROCKET_CACHE_PATH . $site_url['host'] . $site_url['path'] . 'release-asset-sentinel/index.html';
		$release_minify = NOOBROCKET_MINIFY_CACHE_PATH . get_current_blog_id() . '/release-asset-sentinel.js';
		$release_minify_gzip = $release_minify . '.gz';
		wp_mkdir_p( dirname( $release_page ) );
		wp_mkdir_p( dirname( $release_minify ) );
		file_put_contents( $release_page, '<script src="retired-release-asset.js"></script>' );
		file_put_contents( $release_minify, 'retired release asset' );
		file_put_contents( $release_minify_gzip, gzencode( 'retired release asset' ) );
		noobrocket_upgrader();
		$saved = get_option( 'noobrocket_settings' );
		acceptance_check( ! file_exists( $release_page ) && ! file_exists( $release_minify ) && ! file_exists( $release_minify_gzip ), 'release update invalidates cached pages and obsolete plain/gzip minified assets' );
		acceptance_check( did_action( 'noobrocket_upgrade' ) === $upgrades_before, 'public release update does not run engine migrations' );
		acceptance_check( $saved['version'] === NOOBROCKET_VERSION && $saved['engine_version'] === NOOBROCKET_ENGINE_VERSION, 'public release and engine metadata updated separately' );
		foreach ( $custom as $key => $value ) {
			acceptance_check( $saved[ $key ] === $value, 'release update preserves user setting: ' . $key );
		}
		foreach ( $list_caches as $name => $transient ) {
			acceptance_check( file_get_contents( NOOBROCKET_CONFIG_PATH . $name . '.json' ) === file_get_contents( NOOBROCKET_PATH . $name . '.json' ) && empty( get_transient( $transient )->acceptance_stale ), 'release update refreshes bundled compatibility list: ' . $name );
		}
		update_option( 'noobrocket_settings', $original );
		break;
	case 'health':
		set_current_screen( 'settings_page_noobrocket' );
		$original_cron = get_option( 'cron' );
		$original_dismissed = get_user_meta( 1, 'noobrocket_boxes', true );
		delete_user_meta( 1, 'noobrocket_boxes' );
		$checker = new NoobRocket\Engine\HealthCheck\HealthCheck( new NoobRocket\Admin\Options_Data( [ 'manual_preload' => 1 ] ) );
		try {
			wp_clear_scheduled_hook( 'noobrocket_preload_process_pending' );
			wp_schedule_single_event( time() - 2 * HOUR_IN_SECONDS, 'noobrocket_preload_process_pending' );
			ob_start();
			$checker->missed_cron();
			$notice = ob_get_clean();
			acceptance_check( strpos( $notice, '<li>Preload</li>' ) !== false, 'health notice detects overdue active preload event' );
			wp_clear_scheduled_hook( 'noobrocket_preload_process_pending' );
			wp_schedule_single_event( time() + HOUR_IN_SECONDS, 'noobrocket_preload_process_pending' );
			ob_start();
			$checker->missed_cron();
			$notice = ob_get_clean();
			acceptance_check( strpos( $notice, '<li>Preload</li>' ) === false, 'health notice accepts future preload event' );
		} finally {
			update_option( 'cron', $original_cron );
			update_user_meta( 1, 'noobrocket_boxes', $original_dismissed );
		}
		break;
	case 'abilities':
		$original = get_option( 'noobrocket_settings' );
		$allowlist = new NoobRocket\Engine\Abilities\Options\AllowedOptions();
		$setter = new NoobRocket\Engine\Abilities\Options\SetOption( $allowlist );
		try {
			foreach ( [ 'remove_unused_css', 'remove_unused_css_safelist', 'performance_monitoring', 'performance_monitoring_schedule_frequency' ] as $key ) {
				$result = $setter->execute( [ 'option_name' => $key, 'option_value' => 1 ] );
				acceptance_check( ! in_array( $key, $allowlist->get(), true ) && $result['success'] === false, 'ability rejects unavailable cloud option: ' . $key );
			}
			$value = empty( $original['lazyload'] ) ? 1 : 0;
			$result = $setter->execute( [ 'option_name' => 'lazyload', 'option_value' => $value ] );
			acceptance_check( $result['success'] && $result['new_value'] === get_noobrocket_option( 'lazyload' ) && get_noobrocket_option( 'lazyload' ) === $value, 'ability saves and reports supported local option' );
			wp_set_current_user( 0 );
			acceptance_check( ! $setter->check_permissions(), 'option ability retains capability check' );
		} finally {
			wp_set_current_user( 1 );
			update_option( 'noobrocket_settings', $original );
		}
		break;
	case 'branding':
		$links = apply_filters( 'plugin_action_links_' . $plugin, [] );
		acceptance_check( strpos( implode( ' ', $links ), 'wp-rocket.me/support' ) === false, 'plugin action links do not advertise upstream paid support' );
		$updates = get_site_transient( 'update_plugins' );
		$mock_updates = is_object( $updates ) ? clone $updates : new stdClass();
		$mock_updates->response[ $plugin ] = (object) [ 'package' => '' ];
		set_site_transient( 'update_plugins', $mock_updates );
		try {
			$meta = apply_filters( 'plugin_row_meta', [], $plugin );
			acceptance_check( strpos( implode( ' ', $meta ), 'Renew your licence' ) === false, 'plugin row has no license renewal upsell' );
		} finally {
			set_site_transient( 'update_plugins', $updates );
		}
		if ( ! class_exists( 'WP_CLI' ) ) {
			class WP_CLI {
				public static $commands = [];
				public static function add_command( $name, $callback ) { self::$commands[ $name ] = $callback; }
			}
		}
		$command = new NoobRocket\Engine\Abilities\CLI\Command( new NoobRocket\Engine\Abilities\Catalog() );
		$subscriber = new NoobRocket\Engine\Abilities\CLI\Subscriber( $command );
		$subscriber->register_commands();
		acceptance_check( isset( WP_CLI::$commands['noobrocket abilities-catalog'] ) && ! isset( WP_CLI::$commands['rocket abilities-catalog'] ), 'CLI catalog uses NoobRocket command name' );
		break;
	case 'veto':
		$dropin = WP_CONTENT_DIR . '/advanced-cache.php';
		$original_dropin = file_get_contents( $dropin );
		add_filter( 'noobrocket_generate_advanced_cache_file', '__return_false', 5 );
		add_filter( 'noobrocket_set_wp_cache_constant', '__return_false', 5 );
		acceptance_check( apply_filters( 'noobrocket_generate_advanced_cache_file', true ) === false && apply_filters( 'noobrocket_set_wp_cache_constant', true ) === false, 'cache ownership policy honors earlier filter vetoes' );
		noobrocket_generate_advanced_cache_file();
		acceptance_check( file_get_contents( $dropin ) === $original_dropin, 'veto prevents cache drop-in generation' );
		break;
	case 'deactivate':
		file_put_contents( WP_CONTENT_DIR . '/advanced-cache.php', "<?php\n// OtherCache acceptance sentinel; compatible with NoobRocket\n" );
		deactivate_plugins( $plugin );
		acceptance_check( ! is_plugin_active( $plugin ), 'deactivation succeeds' );
		acceptance_check( strpos( file_get_contents( WP_CONTENT_DIR . '/advanced-cache.php' ), 'OtherCache acceptance sentinel' ) !== false, 'deactivation preserves another plugin cache drop-in' );
		acceptance_check( get_option( 'wp_rocket_settings' ) === $sentinel_settings && file_get_contents( $sentinel_cache ) === 'original-wp-rocket', 'deactivation preserves WP Rocket settings and cache' );
		acceptance_check( get_option( 'plugin_family_dismiss_promote_imagify' ) === $sentinel_promo && wp_next_scheduled( 'action_scheduler_run_queue_rucss', $sentinel_cron_args ) !== false && wp_next_scheduled( 'action_scheduler_run_queue_rucss', $sentinel_cron_context ) !== false, 'deactivation preserves shared plugin-family option and WP Rocket RUCSS events' );
		acceptance_check( acceptance_original_preload_status() === 'pending', 'deactivation preserves original noobrocket-preload action' );
		break;
	case 'uninstall':
		uninstall_plugin( $plugin );
		acceptance_check( get_option( 'noobrocket_settings', false ) === false, 'uninstall removes NoobRocket settings' );
		acceptance_check( get_option( 'wp_rocket_settings' ) === $sentinel_settings && file_get_contents( $sentinel_cache ) === 'original-wp-rocket', 'uninstall preserves WP Rocket settings and cache' );
		acceptance_check( strpos( file_get_contents( WP_CONTENT_DIR . '/advanced-cache.php' ), 'OtherCache acceptance sentinel' ) !== false, 'uninstall preserves another plugin cache drop-in' );
		acceptance_check( get_option( 'plugin_family_dismiss_promote_imagify' ) === $sentinel_promo && wp_next_scheduled( 'action_scheduler_run_queue_rucss', $sentinel_cron_args ) !== false && wp_next_scheduled( 'action_scheduler_run_queue_rucss', $sentinel_cron_context ) !== false, 'uninstall preserves shared plugin-family option and WP Rocket RUCSS events' );
		acceptance_check( acceptance_original_preload_status() === 'pending', 'uninstall preserves original noobrocket-preload action' );
		break;
	case 'collision':
		$dropin = WP_CONTENT_DIR . '/advanced-cache.php';
		$original_dropin = file_get_contents( $dropin );
		$active_plugins = get_option( 'active_plugins', [] );
		add_filter( 'wp_die_handler', function () {
			return function ( $message ) { throw new RuntimeException( wp_strip_all_tags( $message ) ); };
		} );
		$buffer_level = ob_get_level();
		try {
			try {
				activate_plugin( $plugin );
				throw new RuntimeException( 'Expected cache collision rejection.' );
			} catch ( RuntimeException $error ) {
				while ( ob_get_level() > $buffer_level ) { ob_end_clean(); }
				acceptance_check( strpos( $error->getMessage(), 'Another cache plugin owns advanced-cache.php' ) !== false, 'activation rejects another plugin cache drop-in' );
			}
			acceptance_check( ! is_plugin_active( $plugin ) && file_get_contents( $dropin ) === $original_dropin, 'rejected activation leaves drop-in and plugin state unchanged' );
			update_option( 'active_plugins', array_merge( $active_plugins, [ 'other-cache/other-cache.php' ] ) );
			try {
				activate_plugin( $plugin );
				throw new RuntimeException( 'Expected active cache owner collision rejection.' );
			} catch ( RuntimeException $error ) {
				while ( ob_get_level() > $buffer_level ) { ob_end_clean(); }
				acceptance_check( strpos( $error->getMessage(), 'Another cache plugin owns advanced-cache.php' ) !== false, 'activation rejects another active cache owner regardless of vendor' );
			}
		} finally {
			update_option( 'active_plugins', $active_plugins );
		}
		acceptance_check( get_option( 'wp_rocket_settings' ) === $sentinel_settings && file_get_contents( $sentinel_cache ) === 'original-wp-rocket', 'rejected activation preserves WP Rocket settings and cache' );
		break;
	case 'restore':
		$dropin = WP_CONTENT_DIR . '/advanced-cache.php';
		$other_cache_sentinel = "<?php\n// OtherCache acceptance sentinel; compatible with NoobRocket\n";
		if ( is_file( $dropin ) && file_get_contents( $dropin ) === $other_cache_sentinel ) {
			unlink( $dropin );
		}
		$result = activate_plugin( $plugin );
		acceptance_check( ! is_wp_error( $result ) && is_plugin_active( $plugin ), 'owned collision sentinel removed and plugin reactivated' );
		do_action( 'admin_init' );
		$settings = get_option( 'noobrocket_settings' );
		$settings['cdn'] = 0;
		$settings['cdn_cnames'] = [];
		$settings['cdn_zone'] = [];
		update_option( 'noobrocket_settings', $settings );
		noobrocket_clean_domain();
		acceptance_check( is_plugin_active( $plugin ) && ! get_noobrocket_option( 'cdn' ), 'browser runtime restored with NoobRocket active and CDN disabled' );
		acceptance_check( acceptance_original_preload_status() === 'pending', 'local cache purge preserves original noobrocket-preload action' );
		break;
	default:
		throw new RuntimeException( 'Unknown acceptance phase.' );
}
echo 'Acceptance phase complete: ', $phase, PHP_EOL;
