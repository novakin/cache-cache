<?php

use WP\MCP\Core\McpAdapter;
use NoobRocket\Addon\Cloudflare\Cloudflare;
use NoobRocket\Engine\Container\IndexedDefinitionAggregate;
use NoobRocket\Dependencies\League\Container\Container;
use NoobRocket\Plugin;

defined( 'ABSPATH' ) || exit;

// Composer autoload.
if ( file_exists( NOOBROCKET_PATH . 'vendor/autoload.php' ) ) {
	require NOOBROCKET_PATH . 'vendor/autoload.php';
}

require_once NOOBROCKET_FUNCTIONS_PATH . 'files.php';

Cloudflare::fix_cf_flexible_ssl();

require_once NOOBROCKET_INC_PATH . 'Dependencies' . DIRECTORY_SEPARATOR . 'ActionScheduler' . DIRECTORY_SEPARATOR . 'action-scheduler.php';

/**
 * Tell WP what to do when plugin is loaded.
 *
 * @since 1.0
 */
function noobrocket_init() {
	// Nothing to do if autosave.
	if ( defined( 'DOING_AUTOSAVE' ) ) {
		return;
	}

	/**
	 * Fires when NoobRocket starts to load.
	 */
	do_action( 'noobrocket_before_load' );

	// Call defines and functions.
	require NOOBROCKET_FUNCTIONS_PATH . 'options.php';

	// Last constants.
	if ( ! defined( 'NOOBROCKET_PLUGIN_NAME' ) ) {
		define( 'NOOBROCKET_PLUGIN_NAME', 'Cache-Cache' );
	}
	if ( ! defined( 'NOOBROCKET_PLUGIN_SLUG' ) ) {
		define( 'NOOBROCKET_PLUGIN_SLUG', 'noobrocket' );
	}

	require NOOBROCKET_INC_PATH . '/API/bypass.php';

	$noobrocket = new Plugin(
		NOOBROCKET_PATH . 'views',
		new Container( new IndexedDefinitionAggregate() )
	);
	$noobrocket->load();

	// Call defines and functions.
	require_once NOOBROCKET_FUNCTIONS_PATH . 'api.php';
	require NOOBROCKET_FUNCTIONS_PATH . 'posts.php';
	require NOOBROCKET_FUNCTIONS_PATH . 'admin.php';
	require NOOBROCKET_FUNCTIONS_PATH . 'formatting.php';
	require NOOBROCKET_FUNCTIONS_PATH . 'i18n.php';
	require NOOBROCKET_FUNCTIONS_PATH . 'htaccess.php';
	require NOOBROCKET_DEPRECATED_PATH . 'deprecated.php';
	require NOOBROCKET_DEPRECATED_PATH . '3.2.php';
	require NOOBROCKET_DEPRECATED_PATH . '3.3.php';
	require NOOBROCKET_DEPRECATED_PATH . '3.4.php';
	require NOOBROCKET_DEPRECATED_PATH . '3.5.php';
	require NOOBROCKET_DEPRECATED_PATH . '3.6.php';
	require NOOBROCKET_DEPRECATED_PATH . '3.7.php';
	require NOOBROCKET_DEPRECATED_PATH . '3.8.php';
	require NOOBROCKET_DEPRECATED_PATH . '3.9.php';
	require NOOBROCKET_DEPRECATED_PATH . '3.10.php';
	require NOOBROCKET_DEPRECATED_PATH . '3.11.php';
	require NOOBROCKET_DEPRECATED_PATH . '3.12.php';
	require NOOBROCKET_DEPRECATED_PATH . '3.13.php';
	require NOOBROCKET_DEPRECATED_PATH . '3.14.php';
	require NOOBROCKET_DEPRECATED_PATH . '3.15.php';
	require NOOBROCKET_DEPRECATED_PATH . '3.20.php';
	require NOOBROCKET_3RD_PARTY_PATH . '3rd-party.php';
	require NOOBROCKET_COMMON_PATH . 'admin-bar.php';

	if ( noobrocket_valid_key() ) {
		require NOOBROCKET_COMMON_PATH . 'purge.php';

		if ( is_multisite() && defined( 'SUNRISE' ) && SUNRISE === 'on' && function_exists( 'domain_mapping_siteurl' ) ) {
			require NOOBROCKET_INC_PATH . '/domain-mapping.php';
		}
	}
	require NOOBROCKET_ADMIN_PATH . 'options.php';

	if ( is_admin() ) {
		require NOOBROCKET_ADMIN_PATH . 'upgrader.php';
		require NOOBROCKET_ADMIN_PATH . 'admin.php';
		require NOOBROCKET_ADMIN_UI_PATH . 'enqueue.php';
		require NOOBROCKET_ADMIN_UI_PATH . 'notices.php';
		require NOOBROCKET_ADMIN_UI_PATH . 'meta-boxes.php';
	} elseif ( noobrocket_valid_key() ) {
		require NOOBROCKET_FRONT_PATH . 'cookie.php';
		require NOOBROCKET_FRONT_PATH . 'dns-prefetch.php';
	}

	// You can hook this to trigger any action when NoobRocket is correctly loaded, so, not in AUTOSAVE mode.
	if ( noobrocket_valid_key() ) {
		/**
		 * Fires when NoobRocket is correctly loaded
		 *
		 * @since 1.0
		*/
		do_action( 'noobrocket_loaded' );
	}
}
add_action( 'plugins_loaded', 'noobrocket_init' );

register_deactivation_hook( NOOBROCKET_FILE, [ 'NoobRocket\Engine\Deactivation\Deactivation', 'deactivate_plugin' ] );
register_activation_hook( NOOBROCKET_FILE, [ 'NoobRocket\Engine\Activation\Activation', 'activate_plugin' ] );
