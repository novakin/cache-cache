<?php
/**
 * Plugin Name: Cache-Cache
 * Description: Free WordPress caching and local performance optimization.
 * Version: 0.1.0-beta.2
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Cache-Cache contributors
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Update URI: false
 *
 * Text Domain: noobrocket
 * Domain Path: /languages
 *
 */

defined( 'ABSPATH' ) || exit;

// Rocket defines.
define( 'NOOBROCKET_VERSION',               '0.1.0-beta.2' );
define( 'NOOBROCKET_ENGINE_VERSION',        '3.23.5.1' );
define( 'NOOBROCKET_WP_VERSION',            '5.8' );
define( 'NOOBROCKET_WP_VERSION_TESTED',     '7.1.3' );
define( 'NOOBROCKET_PHP_VERSION',           '7.4' );
define( 'NOOBROCKET_PRIVATE_KEY',           false );
define( 'NOOBROCKET_SLUG',                  'noobrocket_settings' );
define( 'NOOBROCKET_WEB_MAIN',              plugin_dir_url( __FILE__ ) . 'docs/' );
define( 'NOOBROCKET_WEB_API', '' ); // Remote services are unavailable.
define( 'NOOBROCKET_WEB_CHECK', '' ); // Remote services are unavailable.
define( 'NOOBROCKET_WEB_VALID', '' ); // Remote services are unavailable.
define( 'NOOBROCKET_WEB_INFO', '' ); // Remote services are unavailable.
define( 'NOOBROCKET_FILE',                  __FILE__ );
define( 'NOOBROCKET_PATH',                  realpath( plugin_dir_path( NOOBROCKET_FILE ) ) . '/' );
define( 'NOOBROCKET_INC_PATH',              realpath( NOOBROCKET_PATH . 'inc/' ) . '/' );

require_once NOOBROCKET_INC_PATH . 'constants.php';

define( 'NOOBROCKET_DEPRECATED_PATH',       realpath( NOOBROCKET_INC_PATH . 'deprecated/' ) . '/' );
define( 'NOOBROCKET_FRONT_PATH',            realpath( NOOBROCKET_INC_PATH . 'front/' ) . '/' );
define( 'NOOBROCKET_ADMIN_PATH',            realpath( NOOBROCKET_INC_PATH . 'admin' ) . '/' );
define( 'NOOBROCKET_ADMIN_UI_PATH',         realpath( NOOBROCKET_ADMIN_PATH . 'ui' ) . '/' );
define( 'NOOBROCKET_ADMIN_UI_MODULES_PATH', realpath( NOOBROCKET_ADMIN_UI_PATH . 'modules' ) . '/' );
define( 'NOOBROCKET_COMMON_PATH',           realpath( NOOBROCKET_INC_PATH . 'common' ) . '/' );
define( 'NOOBROCKET_FUNCTIONS_PATH',        realpath( NOOBROCKET_INC_PATH . 'functions' ) . '/' );
define( 'NOOBROCKET_VENDORS_PATH',          realpath( NOOBROCKET_INC_PATH . 'vendors' ) . '/' );
define( 'NOOBROCKET_3RD_PARTY_PATH',        realpath( NOOBROCKET_INC_PATH . '3rd-party' ) . '/' );
if ( ! defined( 'NOOBROCKET_CONFIG_PATH' ) ) {
	define( 'NOOBROCKET_CONFIG_PATH',       WP_CONTENT_DIR . '/noobrocket-config/' );
}
define( 'NOOBROCKET_URL',                   plugin_dir_url( NOOBROCKET_FILE ) );
define( 'NOOBROCKET_INC_URL',               NOOBROCKET_URL . 'inc/' );
define( 'NOOBROCKET_ADMIN_URL',             NOOBROCKET_INC_URL . 'admin/' );
define( 'NOOBROCKET_ASSETS_URL',            NOOBROCKET_URL . 'assets/' );
define( 'NOOBROCKET_ASSETS_PATH',            NOOBROCKET_PATH . 'assets/' );
define( 'NOOBROCKET_ASSETS_JS_URL',         NOOBROCKET_ASSETS_URL . 'js/' );
define( 'NOOBROCKET_ASSETS_JS_PATH',         NOOBROCKET_ASSETS_PATH . 'js/' );
define( 'NOOBROCKET_ASSETS_CSS_URL',        NOOBROCKET_ASSETS_URL . 'css/' );
define( 'NOOBROCKET_ASSETS_IMG_URL',        NOOBROCKET_ASSETS_URL . 'img/' );

if ( ! defined( 'NOOBROCKET_CACHE_ROOT_PATH' ) ) {
	define( 'NOOBROCKET_CACHE_ROOT_PATH', WP_CONTENT_DIR . '/cache/noobrocket/' );
}
define( 'NOOBROCKET_CACHE_PATH',         NOOBROCKET_CACHE_ROOT_PATH . 'pages/' );
define( 'NOOBROCKET_MINIFY_CACHE_PATH',  NOOBROCKET_CACHE_ROOT_PATH . 'min/' );
define( 'NOOBROCKET_CACHE_BUSTING_PATH', NOOBROCKET_CACHE_ROOT_PATH . 'busting/' );
define( 'NOOBROCKET_CRITICAL_CSS_PATH',  NOOBROCKET_CACHE_ROOT_PATH . 'critical-css/' );

define( 'NOOBROCKET_USED_CSS_PATH',  NOOBROCKET_CACHE_ROOT_PATH . 'used-css/' );

if ( ! defined( 'NOOBROCKET_CACHE_ROOT_URL' ) ) {
	define( 'NOOBROCKET_CACHE_ROOT_URL', WP_CONTENT_URL . '/cache/noobrocket/' );
}
define( 'NOOBROCKET_CACHE_URL',         NOOBROCKET_CACHE_ROOT_URL . 'pages/' );
define( 'NOOBROCKET_MINIFY_CACHE_URL',  NOOBROCKET_CACHE_ROOT_URL . 'min/' );
define( 'NOOBROCKET_CACHE_BUSTING_URL', NOOBROCKET_CACHE_ROOT_URL . 'busting/' );

define( 'NOOBROCKET_USED_CSS_URL', NOOBROCKET_CACHE_ROOT_URL . 'used-css/' );

if ( ! defined( 'CHMOD_NOOBROCKET_CACHE_DIRS' ) ) {
	define( 'CHMOD_NOOBROCKET_CACHE_DIRS', 0755 ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
}
if ( ! defined( 'NOOBROCKET_LASTVERSION' ) ) {
	define( 'NOOBROCKET_LASTVERSION', '0.1.0-beta.2' );
}

require_once NOOBROCKET_INC_PATH . 'free.php';
require NOOBROCKET_INC_PATH . 'compat.php';
require NOOBROCKET_INC_PATH . 'classes/class-noobrocket-requirements-check.php';

/**
 * Loads NoobRocket translations
 *
 * @since 3.0
 * @author Remy Perona
 *
 * @return void
 */
function noobrocket_load_textdomain() {
	load_plugin_textdomain( 'noobrocket', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}
add_action( 'init', 'noobrocket_load_textdomain' );

$noobrocket_requirement_checks = new NoobRocket_Requirements_Check(
	[
		'plugin_name'         => 'Cache-Cache',
		'plugin_file'         => NOOBROCKET_FILE,
		'plugin_version'      => NOOBROCKET_VERSION,
		'plugin_last_version' => NOOBROCKET_LASTVERSION,
		'wp_version'          => NOOBROCKET_WP_VERSION,
		'php_version'         => NOOBROCKET_PHP_VERSION,
	]
);

if ( $noobrocket_requirement_checks->check() ) {
	require NOOBROCKET_INC_PATH . 'main.php';
}

unset( $noobrocket_requirement_checks );
