<?php
/** Registered Abilities schema and native input/permission controls; no option writes or external HTTP. */
$wordpress = isset( $argv[1] ) ? realpath( $argv[1] ) : false;
if ( PHP_SAPI !== 'cli' || ! $wordpress || ! is_file( $wordpress . '/wp-load.php' ) || ! is_file( $wordpress . '/.noobrocket-acceptance-site' ) ) {
	fwrite( STDERR, "Provide a marked disposable WordPress installation with Abilities API and a copied plugin.\n" );
	exit( 2 );
}
$source = dirname( __DIR__ ) . '/noobrocket';
$installed = $wordpress . '/wp-content/plugins/noobrocket';
$normalize = static function ( $path ) { return str_replace( '\\', '/', $path ); };
if ( ! realpath( $installed ) || $normalize( realpath( $installed ) ) !== $normalize( $installed ) || realpath( $source ) === realpath( $installed ) ) {
	fwrite( STDERR, "The disposable site must contain a real plugin copy, not a source junction.\n" );
	exit( 2 );
}
foreach ( [ '/inc/Engine/Abilities/Options/SetOption.php', '/inc/Engine/Abilities/Options/AllowedOptions.php' ] as $owner ) {
	if ( hash_file( 'sha256', $source . $owner ) !== hash_file( 'sha256', $installed . $owner ) ) {
		fwrite( STDERR, "Copy the current Abilities owners before this check.\n" );
		exit( 2 );
	}
}
define( 'WP_ADMIN', true );
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
$_SERVER['SERVER_PORT'] = parse_url( 'http://' . $_SERVER['HTTP_HOST'], PHP_URL_PORT ) ?: 80;
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'POST';
require_once $wordpress . '/wp-includes/plugin.php';
add_filter( 'pre_http_request', static function () { return new WP_Error( 'ability_fixture_http', 'Unowned HTTP is blocked by this fixture.' ); }, PHP_INT_MAX - 1 );
add_filter( 'action_scheduler_allow_async_request_runner', '__return_false' );
add_filter( 'noobrocket_generate_advanced_cache_file', '__return_false' );
add_filter( 'noobrocket_disable_htaccess', '__return_true' );
$sparse = isset( $argv[2] ) && 'sparse' === $argv[2];
if ( $sparse ) {
	// Run after plugin allowlist extensions so the registered owner receives sparse keys.
	add_filter( 'noobrocket_mcp_options_allowlist', static function () { return [ 4 => 'minify_css', 12 => 'cdn_state', 19 => 'lazyload' ]; }, PHP_INT_MAX );
}
$complete = false;
register_shutdown_function( static function () use ( &$complete ) {
	if ( ! $complete ) { fwrite( STDERR, "FAIL: native Abilities schema fixture did not complete.\n" ); exit( 2 ); }
} );
require $wordpress . '/wp-load.php';
if ( ! function_exists( 'wp_get_ability' ) ) { throw new RuntimeException( 'WordPress Abilities API is required.' ); }
$checks = 0;
$check = static function ( $condition, $message ) use ( &$checks ) {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
	++$checks;
};
$saved = get_option( 'noobrocket_settings' );
$ability = wp_get_ability( 'noobrocket/set-option' );
$check( $ability instanceof WP_Ability, 'plugin subscriber registers the native ability' );
$schema = $ability->get_input_schema();
$enum = json_decode( wp_json_encode( $schema ) )->properties->option_name->enum;
$check( is_array( $enum ), 'registered schema emits a JSON enum array' );
$check( in_array( 'minify_css', $enum, true ) && ! in_array( 'cdn_state', $enum, true ), 'writable values remain available and read-only state stays excluded' );
if ( $sparse ) { $check( [ 'minify_css', 'lazyload' ] === $enum, 'native sparse filter preserves value order' ); }
$input = [ 'option_name' => 'minify_css', 'option_value' => true ];
$check( true === rest_validate_value_from_schema( $input, $schema ), 'native schema validator accepts a supported writable option' );
foreach ( [ 'cdn_state', 'unknown_option' ] as $name ) {
	$invalid = [ 'option_name' => $name, 'option_value' => true ];
	$check( is_wp_error( rest_validate_value_from_schema( $invalid, $schema ) ), 'native schema validator rejects ' . $name );
}
wp_set_current_user( 1 );
$check( true === $ability->check_permissions( $input ), 'administrator passes native capability callback' );
$check( is_wp_error( $ability->execute( [ 'option_name' => 'cdn_state', 'option_value' => true ] ) ), 'registered ability rejects read-only writes before execution' );
wp_set_current_user( 0 );
$check( true !== $ability->check_permissions( $input ), 'anonymous caller fails native capability callback' );
$check( is_wp_error( $ability->execute( $input ) ), 'registered ability denies an anonymous mutation' );
$check( $saved === get_option( 'noobrocket_settings' ), 'rejected calls preserve saved options' );
echo "PASS: $checks native Abilities schema assertions; registered JSON enum, input validation and permission/read-only controls.\n";
$complete = true;
