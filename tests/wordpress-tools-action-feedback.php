<?php
/** Registered Tools AJAX callbacks, native nonce/capability/options, with refused/adjusted writes in a disposable copied-plugin site. */
$wordpress = isset( $argv[1] ) ? realpath( $argv[1] ) : false;
if ( ! $wordpress || ! is_file( $wordpress . '/.noobrocket-acceptance-site' ) || ! is_file( $wordpress . '/wp-load.php' ) ) {
	fwrite( STDERR, "Provide a marked disposable WordPress installation.\n" );
	exit( 2 );
}
$source = dirname( __DIR__ ) . '/noobrocket';
$installed = $wordpress . '/wp-content/plugins/noobrocket';
$normalize = static function ( $path ) { return str_replace( '\\', '/', $path ); };
if ( ! realpath( $installed ) || $normalize( realpath( $installed ) ) !== $normalize( $installed ) || realpath( $installed ) === realpath( $source ) ) {
	fwrite( STDERR, "The selected site must use a real plugin copy, not a source junction.\n" );
	exit( 2 );
}
foreach ( [ 'inc/Engine/Admin/Settings/Page.php', 'inc/Engine/Optimization/GoogleFonts/Admin/Settings.php' ] as $file ) {
	if ( hash_file( 'sha256', $source . '/' . $file ) !== hash_file( 'sha256', $installed . '/' . $file ) ) {
		fwrite( STDERR, "Copy the current Tools AJAX owners before running native proof.\n" );
		exit( 2 );
	}
}
$complete = false;
register_shutdown_function( static function () use ( &$complete ) {
	// Let WordPress release request-owned locks before enforcing completion.
	register_shutdown_function( static function () use ( &$complete ) {
		if ( ! $complete ) { fwrite( STDERR, "FAIL: native Tools fixture did not complete.\n" ); exit( 2 ); }
	} );
} );
define( 'WP_ADMIN', true );
define( 'DOING_AJAX', true );
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
$_SERVER['HTTP_HOST'] = '127.0.0.1';
$_SERVER['REQUEST_URI'] = '/wp-admin/admin-ajax.php';
$_SERVER['REQUEST_METHOD'] = 'POST';
require $wordpress . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
wp_set_current_user( 1 );
do_action( 'admin_init' );

class NativeToolsAjaxEnd extends RuntimeException {}
$die_handler = static function () { return static function () { throw new NativeToolsAjaxEnd(); }; };
add_filter( 'wp_die_ajax_handler', $die_handler );
$checks = 0;
$check = static function ( $condition, $message ) use ( &$checks ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
	++$checks;
};
$invoke = static function ( $action ) {
	// Each HTTP request constructs a new options snapshot. Refresh that boundary between CLI cases.
	$owner_property = 'noobrocket_enable_mobile_cache' === $action ? 'page' : 'settings';
	foreach ( $GLOBALS['wp_filter'][ 'wp_ajax_' . $action ]->callbacks as $callbacks ) {
		foreach ( $callbacks as $callback ) {
			$subscriber = $callback['function'][0];
			$property = new ReflectionProperty( $subscriber, $owner_property );
			$property->setAccessible( true );
			$owner = $property->getValue( $subscriber );
			$options = new ReflectionProperty( $owner, 'options' );
			$options->setAccessible( true );
			$options->setValue( $owner, new NoobRocket\Admin\Options_Data( get_option( 'noobrocket_settings' ) ) );
		}
	}
	$_POST = [ 'action' => $action, '_ajax_nonce' => wp_create_nonce( 'noobrocket-ajax' ) ];
	$_REQUEST = $_POST;
	ob_start();
	try { do_action( 'wp_ajax_' . $action ); } catch ( NativeToolsAjaxEnd $end ) {} finally { $output = ob_get_clean(); }
	$response = json_decode( $output, true );
	if ( ! is_array( $response ) || ! isset( $response['success'] ) ) { throw new RuntimeException( 'Registered AJAX callback did not return native JSON.' ); }
	return $response['success'];
};
$saved = get_option( 'noobrocket_settings' );
$saved_errors = get_transient( 'settings_errors' );
$baseline = array_merge( $saved, [ 'cache_mobile' => 0, 'do_caching_mobile_files' => 0, 'minify_google_fonts' => 0 ] );
$writer = null;
try {
	$check( current_user_can( 'noobrocket_manage_options' ), 'Disposable admin has the native Tools capability' );
	foreach ( [
		[ 'noobrocket_enable_mobile_cache', [ 'cache_mobile', 'do_caching_mobile_files' ] ],
		[ 'noobrocket_enable_google_fonts', [ 'minify_google_fonts' ] ],
	] as $case ) {
		list( $action, $fields ) = $case;
		$check( false !== has_action( 'wp_ajax_' . $action ), 'Native Tools action is registered: ' . $action );
		update_option( 'noobrocket_settings', $baseline );
		$before = get_option( 'noobrocket_settings' );
		foreach ( [ 'cache_mobile', 'do_caching_mobile_files', 'minify_google_fonts' ] as $field ) { $check( 0 === $before[ $field ], 'Native test baseline is disabled: ' . $field ); }
		$writer = static function ( $new, $old ) { return $old; };
		add_filter( 'pre_update_option_noobrocket_settings', $writer, PHP_INT_MAX, 2 );
		$check( false === $invoke( $action ), 'Refused native write reports failure: ' . $action );
		$check( $before === get_option( 'noobrocket_settings' ), 'Refusal preserves raw native settings' );
		remove_filter( 'pre_update_option_noobrocket_settings', $writer, PHP_INT_MAX );
		$writer = null;
		$check( true === $invoke( $action ), 'The registered action supports successful retry' );
		$persisted = get_option( 'noobrocket_settings' );
		foreach ( $fields as $field ) { $check( 1 === $persisted[ $field ], 'Retry persists ' . $field ); }
		$check( true === $invoke( $action ), 'Native already-enabled no-op reports success' );
		$check( array_diff_key( $before, array_flip( $fields ) ) === array_diff_key( $persisted, array_flip( $fields ) ), 'Unrelated native saved settings are retained' );
		update_option( 'noobrocket_settings', $baseline );
		$adjusted = 'noobrocket_enable_mobile_cache' === $action ? 'do_caching_mobile_files' : 'minify_google_fonts';
		$writer = static function ( $new ) use ( $adjusted ) { $new[ $adjusted ] = 0; return $new; };
		add_filter( 'pre_update_option_noobrocket_settings', $writer, PHP_INT_MAX, 1 );
		$check( false === $invoke( $action ), 'Partial/adjusted native write reports failure' );
		$check( 0 === get_option( 'noobrocket_settings' )[ $adjusted ], 'Native readback observes the adjusted saved value' );
		remove_filter( 'pre_update_option_noobrocket_settings', $writer, PHP_INT_MAX );
		$writer = null;
		wp_set_current_user( 0 );
		$before = get_option( 'noobrocket_settings' );
		$check( false === $invoke( $action ), 'Valid nonce alone does not authorize a Tools mutation' );
		$check( $before === get_option( 'noobrocket_settings' ), 'Authorization denial preserves native settings' );
		wp_set_current_user( 1 );
	}
} finally {
	if ( $writer ) { remove_filter( 'pre_update_option_noobrocket_settings', $writer, PHP_INT_MAX ); }
	wp_set_current_user( 1 );
	update_option( 'noobrocket_settings', $saved );
	if ( false === $saved_errors ) { delete_transient( 'settings_errors' ); } else { set_transient( 'settings_errors', $saved_errors ); }
	remove_filter( 'wp_die_ajax_handler', $die_handler );
}
$check( $saved === get_option( 'noobrocket_settings' ), 'Fixture restores the exact raw saved options' );
$complete = true;
echo 'PASS: native Tools AJAX persistence/feedback, ', $checks, " assertions; copied plugin and isolated database, no provider HTTP.\n";
