<?php
/** Native option creation and malformed old/new payloads with actual WPML callback; WPML itself is not installed. */
$wordpress = isset( $argv[1] ) ? realpath( $argv[1] ) : false;
if ( PHP_SAPI !== 'cli' || ! $wordpress || ! is_file( $wordpress . '/.noobrocket-acceptance-site' ) ) { throw new RuntimeException( 'Provide a marked disposable WordPress installation.' ); }
$owner = '/noobrocket/inc/ThirdParty/Plugins/I18n/WPML.php';
if ( hash_file( 'sha256', dirname( __DIR__ ) . $owner ) !== hash_file( 'sha256', $wordpress . '/wp-content/plugins' . $owner ) ) { throw new RuntimeException( 'Installed WPML owner differs from candidate.' ); }
define( 'WP_ADMIN', true );
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
$_SERVER['HTTP_HOST'] = '127.0.0.1';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'POST';
$completed = false;
register_shutdown_function( static function () use ( &$completed ) { if ( ! $completed ) { fwrite( STDERR, "FAIL: native WPML persistence fixture did not complete.\n" ); exit( 2 ); } } );
require $wordpress . '/wp-load.php';
$absent = new stdClass();
if ( $absent !== get_option( 'icl_sitepress_settings', $absent ) || defined( 'ICL_SITEPRESS_VERSION' ) ) { throw new RuntimeException( 'This fixture requires no existing WPML configuration or installation.' ); }
$checks = 0;
$check = static function ( $condition, $message ) use ( &$checks ) { if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); } ++$checks; };
$subscriber = new NoobRocket\ThirdParty\Plugins\I18n\WPML();
$callback = [ $subscriber, 'on_change_directory_for_default_language_clean_cache' ];
add_filter( 'pre_update_option_icl_sitepress_settings', $callback, 10, 2 );
$purges = did_action( 'noobrocket_after_clean_domain' );
$resets = did_action( 'noobrocket_reset_preload' );
try {
	$value = [ 'urls' => [ 'directory_for_default_language' => false ] ];
	$check( update_option( 'icl_sitepress_settings', $value ) && $value === get_option( 'icl_sitepress_settings' ), 'native missing old=false permits first option creation and preserves payload' );
	foreach ( [ [ 'urls' => 'malformed' ], 'malformed', [ 'urls' => null ], [ 'urls' => [ 'directory_for_default_language' => true ] ] ] as $value ) {
		$check( update_option( 'icl_sitepress_settings', $value ) && $value === get_option( 'icl_sitepress_settings' ), 'native malformed old/new structures persist unchanged without TypeError' );
	}
	$check( ! update_option( 'icl_sitepress_settings', $value ), 'native unchanged option remains a no-op' );
	$check( $purges === did_action( 'noobrocket_after_clean_domain' ) && $resets === did_action( 'noobrocket_reset_preload' ), 'missing/malformed/unchanged settings do not reset or purge' );
} finally {
	remove_filter( 'pre_update_option_icl_sitepress_settings', $callback, 10 );
	delete_option( 'icl_sitepress_settings' );
	$check( $absent === get_option( 'icl_sitepress_settings', $absent ), 'owned WPML option is absent after cleanup' );
}
$completed = true;
echo 'PASS native WPML settings persistence: ', $checks, " checks\n";
