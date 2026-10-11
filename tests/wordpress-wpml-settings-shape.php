<?php
/** Actual registered WPML subscriber; native core hooks, modeled options and purge sinks. */
declare(strict_types=1);

$core = isset( $argv[1] ) ? realpath( $argv[1] ) : false;
if ( PHP_SAPI !== 'cli' || ! $core || ! is_file( $core . '/wp-includes/plugin.php' ) ) { throw new RuntimeException( 'Provide WordPress core for WPML settings-shape proof.' ); }
define( 'ABSPATH', $core . '/' );
require ABSPATH . 'wp-includes/plugin.php';
require ABSPATH . 'wp-includes/class-wp-error.php';
require ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
require ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
require dirname( __DIR__ ) . '/noobrocket/vendor/autoload.php';

$events = [];
$option = false;
$checks = 0;
function get_option( $name, $default = false ) { return 'icl_sitepress_settings' === $name ? $GLOBALS['option'] : $default; }
function noobrocket_clean_domain() { $GLOBALS['events'][] = 'domain'; }
function noobrocket_clean_cache_dir() { $GLOBALS['events'][] = 'directory'; }
$check = static function ( $condition, $message ) use ( &$checks ) {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
	++$checks;
};
$subscriber = new NoobRocket\ThirdParty\Plugins\I18n\WPML( new WP_Filesystem_Direct( null ) );
$manager = new NoobRocket\Event_Management\Event_Manager();
$check( ! isset( $subscriber::get_subscribed_events()['pre_update_option_icl_sitepress_settings'] ), 'settings callback remains inactive without WPML version registration' );
define( 'ICL_SITEPRESS_VERSION', 'fixture' );
$manager->add_subscriber( $subscriber );
$check( 10 === has_filter( 'pre_update_option_icl_sitepress_settings', [ $subscriber, 'on_change_directory_for_default_language_clean_cache' ] ), 'actual subscriber registers the settings filter at its existing priority' );
add_action( 'noobrocket_reset_preload', static function () { $GLOBALS['events'][] = 'reset'; } );
set_error_handler( static function ( $severity, $message, $file, $line ) { throw new ErrorException( $message, 0, $severity, $file, $line ); } );
$settings = static function ( $flag ) { return [ 'urls' => [ 'directory_for_default_language' => $flag ], 'other' => 'preserved' ]; };
$valid = $settings( true );
$unchanged = [
	[ $valid, false ],
	[ false, $valid ],
	[ null, $valid ],
	[ 'malformed', $valid ],
	[ (object) [ 'urls' => [] ], $valid ],
	[ $valid, (object) [ 'urls' => [] ] ],
	[ [], $valid ],
	[ $valid, [] ],
	[ [ 'urls' => false ], $valid ],
	[ [ 'urls' => 'malformed' ], $valid ],
	[ [ 'urls' => null ], $valid ],
	[ [ 'urls' => (object) [] ], $valid ],
	[ $valid, [ 'urls' => false ] ],
	[ $valid, [ 'urls' => 'malformed' ] ],
	[ $valid, [ 'urls' => null ] ],
	[ $valid, [ 'urls' => (object) [] ] ],
	[ [ 'urls' => [] ], $valid ],
	[ $valid, [ 'urls' => [] ] ],
	[ $valid, $valid ],
	[ $settings( null ), $settings( null ) ],
];
try {
	foreach ( $unchanged as $index => $case ) {
		$events = [];
		$result = apply_filters( 'pre_update_option_icl_sitepress_settings', $case[0], $case[1], 'icl_sitepress_settings' );
		$check( $result === $case[0], 'unchanged/malformed case ' . $index . ' preserves exact new filter payload' );
		$check( [] === $events, 'unchanged/malformed case ' . $index . ' performs no reset or purge' );
	}
	foreach ( [ [ true, false ], [ false, true ], [ null, true ], [ true, null ], [ 1, true ] ] as $index => $flags ) {
		$events = [];
		$new = $settings( $flags[0] );
		$result = apply_filters( 'pre_update_option_icl_sitepress_settings', $new, $settings( $flags[1] ), 'icl_sitepress_settings' );
		$check( $new === $result, 'valid changed flags preserve settings case ' . $index );
		$check( [ 'reset', 'domain' ] === $events, 'valid changed flags reset then purge exactly once case ' . $index );
	}
	foreach ( [ 'activate_sitepress-multilingual-cms/sitepress.php', 'deactivate_sitepress-multilingual-cms/sitepress.php' ] as $hook ) {
		foreach ( [ false, null, 'malformed', (object) [], [], [ 'urls' => false ], [ 'urls' => 'malformed' ], [ 'urls' => null ], [ 'urls' => (object) [] ], [ 'urls' => [] ], $settings( false ) ] as $index => $value ) {
			$option = $value;
			$events = [];
			do_action( $hook );
			$check( [] === $events, $hook . ' malformed/disabled case ' . $index . ' performs no reset or purge' );
		}
		foreach ( [ true, 1, null ] as $flag ) {
			$option = $settings( $flag );
			$events = [];
			do_action( $hook );
			$check( [ 'reset', 'directory', 'domain' ] === $events, $hook . ' preserves existing non-false flag cleanup and order' );
		}
	}
	echo 'PASS: ', $checks, ' WPML settings-shape assertions using native core hooks.', PHP_EOL;
} finally {
	restore_error_handler();
	$manager->remove_subscriber( $subscriber );
}
