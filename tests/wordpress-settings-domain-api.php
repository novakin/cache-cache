<?php
/** In-memory native domain/API proof. No WordPress bootstrap, database or external HTTP. */
require __DIR__ . '/wordpress-cdn-validation.php';
$plugin = dirname( __DIR__ ) . '/noobrocket/';
foreach ( [
	'inc/classes/admin/class-abstract-options.php',
	'inc/classes/admin/class-options.php',
	'inc/Engine/Abilities/AbilitiesInterface.php',
	'inc/Engine/Tracking/TrackingTrait.php',
	'inc/Engine/Abilities/Options/AllowedOptions.php',
	'inc/Engine/Abilities/Options/SetOption.php',
] as $file ) {
	require $plugin . $file;
}

function do_action( ...$args ) {}
function current_user_can( $capability ) { return $GLOBALS['domain_authorized']; }
function get_option( $name, $default = null ) { return 'noobrocket_settings' === $name ? $GLOBALS['domain_options'] : $default; }
function get_noobrocket_option( $name, $default = false ) { return $GLOBALS['domain_options'][ $name ] ?? $default; }
function update_option( $name, $value ) {
	if ( 'noobrocket_settings' !== $name ) { throw new RuntimeException( 'Unexpected fixture option writer' ); }
	$GLOBALS['domain_writes']++;
	if ( 'refuse' === $GLOBALS['domain_writer'] ) { return false; }
	if ( 'adjust' === $GLOBALS['domain_writer'] ) { $value['local_css_mode'] = 'off'; }
	if ( 'partial_cdn' === $GLOBALS['domain_writer'] ) { $value['cdn_zone'] = $GLOBALS['domain_options']['cdn_zone']; }
	$GLOBALS['domain_options'] = $value;
	return true;
}
function update_noobrocket_option( $name, $value ) {
	$stored = $GLOBALS['domain_options'];
	$stored[ $name ] = $value;
	update_option( 'noobrocket_settings', $stored );
}
function domain_check( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
	$GLOBALS['domain_checks']++;
}
function set_api_option( $name, $value, $mode = 'replace' ) {
	return $GLOBALS['domain_ability']->execute( [ 'option_name' => $name, 'option_value' => $value, 'update_mode' => $mode ] );
}

$domain_checks = 0;
$domain_authorized = true;
$domain_writes = 0;
$domain_writer = 'accept';
$domain_options = [
	'lazyload' => 1,
	'minify_css' => 1,
	'local_css_mode' => 'critical',
	'purge_cron_interval' => 10,
	'purge_cron_unit' => 'HOUR_IN_SECONDS',
	'cdn' => 1,
	'cdn_cnames' => [ 'https://saved.example.test' ],
	'cdn_zone' => [ 'images' ],
	'cdn_reject_pages' => [ '/keep-page/' ],
	'cdn_reject_files' => [ '/keep.css' ],
	'unrelated_plugin_value' => 'retained',
];
$domain_ability = new NoobRocket\Engine\Abilities\Options\SetOption( new NoobRocket\Engine\Abilities\Options\AllowedOptions() );
set_error_handler( function ( $severity, $message, $file, $line ) {
	throw new ErrorException( $message, 0, $severity, $file, $line );
}, E_WARNING | E_NOTICE );

foreach ( [ false, 0, '0', 'false', true, 1, '1', 'true' ] as $value ) {
	$expected = in_array( $value, [ true, 1, '1', 'true' ], true ) ? 1 : 0;
	$api = set_api_option( 'minify_css', $value );
	$native_settings = new NoobRocket\Engine\Admin\Settings\Settings( new NoobRocket\Admin\Options_Data( $domain_options ) );
	$native = $native_settings->sanitize_callback( [ 'minify_css' => $value, 'ignore' => 1 ] );
	domain_check( $api['success'] && $expected === $api['new_value'] && $expected === $native['minify_css'], 'Native/API boolean representations agree' );
	domain_check( 1 === $domain_options['lazyload'] && 'retained' === $domain_options['unrelated_plugin_value'], 'Single boolean update preserves unrelated settings' );
}
foreach ( [ 'on', 'off', '', 'FALSE', 2, -1, 1.0, [ '1' ], null ] as $bad_boolean ) {
	$before = $domain_options; $writes = $domain_writes;
	$result = set_api_option( 'minify_css', $bad_boolean );
	domain_check( ! $result['success'] && 'invalid' === $result['outcome'] && $before === $domain_options && $writes === $domain_writes, 'Invalid API boolean returns an error without writing/coercing' );
}

foreach ( [ 'local_css_mode', 'purge_cron_unit', 'automatic_cleanup_frequency', 'heartbeat_admin_behavior' ] as $name ) {
	foreach ( NoobRocket\Engine\Admin\Settings\Settings::get_allowed_values( $name ) as $value ) {
		$result = set_api_option( $name, $value );
		domain_check( $result['success'] && $value === $result['new_value'], 'Available domain enum saves: ' . $name . '/' . $value );
	}
	$before = $domain_options; $writes = $domain_writes;
	$result = set_api_option( $name, 'unavailable-choice' );
	domain_check( ! $result['success'] && $before === $domain_options && $writes === $domain_writes && false !== strpos( $result['error'], 'Choose an available value' ), 'Unavailable enum has explicit allowed-choice feedback' );
}
$before = $domain_options;
$result = set_api_option( 'purge_cron_unit', 'MINUTE_IN_SECONDS' );
domain_check( ! $result['success'] && $before === $domain_options, 'API lifespan uses native Hours/Days policy' );
foreach ( [ 0, '0', 12, '12' ] as $value ) {
	$result = set_api_option( 'purge_cron_interval', $value );
	domain_check( $result['success'] && (int) $value === $result['new_value'], 'Lifespan accepts nonnegative whole numbers and unlimited zero' );
}
foreach ( [ -1, 'many', true, 1.5, [] ] as $value ) {
	$before = $domain_options;
	$result = set_api_option( 'purge_cron_interval', $value );
	domain_check( ! $result['success'] && $before === $domain_options, 'Invalid lifespan fails explicitly without changing settings' );
}

foreach ( [ [ 'not a hostname' ], [ 'ftp://cdn.example.test' ], [ 'bad-key' => 'cdn.example.test' ], [ [ 'nested' ] ] ] as $bad_hosts ) {
	$before = $domain_options; $writes = $domain_writes;
	$result = set_api_option( 'cdn_cnames', $bad_hosts );
	domain_check( ! $result['success'] && $before === $domain_options && $writes === $domain_writes, 'API rejects the same malformed CDN hosts as native forms' );
}
$writes = $domain_writes;
$result = set_api_option( 'cdn_cnames', [ 'cdn.example.test/', 'https://second.example.test/path/' ], 'update' );
domain_check( $result['success'] && [ 'https://saved.example.test', 'https://cdn.example.test', 'https://second.example.test/path' ] === $domain_options['cdn_cnames'], 'API host append uses native URL normalization' );
domain_check( [ 'images', 'all', 'all' ] === $domain_options['cdn_zone'] && $domain_writes === $writes + 1, 'Host/scope alignment is stored in one existing full-options write' );
domain_check( [ '/keep.css' ] === $domain_options['cdn_reject_files'] && [ '/keep-page/' ] === $domain_options['cdn_reject_pages'] && 'retained' === $domain_options['unrelated_plugin_value'], 'CDN row update preserves other CDN and plugin settings' );
foreach ( [ [ 'arbitrary-scope' ], [ 'all', 'images', 'css', 'js' ], [ 100 => 'images' ], [ [ 'nested' ] ] ] as $bad_scopes ) {
	$before = $domain_options; $writes = $domain_writes;
	$result = set_api_option( 'cdn_zone', $bad_scopes );
	domain_check( ! $result['success'] && $before === $domain_options && $writes === $domain_writes, 'Invalid/excess scope vector cannot corrupt CDN rows' );
}
$result = set_api_option( 'cdn_zone', [ 'images', 'images', 'css' ], 'update' );
domain_check( $result['success'] && [ 'images', 'images', 'css' ] === $domain_options['cdn_zone'], 'Positional scope list retains duplicate values and does not append/unique them' );
$result = set_api_option( 'cdn_cnames', [ '', 'new.example.test/', 'new.example.test' ] );
domain_check( $result['success'] && [ 'https://new.example.test' ] === $domain_options['cdn_cnames'] && [ 'images' ] === $domain_options['cdn_zone'], 'Host blanks/duplicates retain corresponding resource-scope row' );
$result = set_api_option( 'cdn_cnames', [] );
domain_check( $result['success'] && [] === $domain_options['cdn_cnames'] && [] === $domain_options['cdn_zone'], 'Removing hosts also removes dangling resource scopes' );

$domain_writer = 'refuse'; $before = $domain_options;
$result = set_api_option( 'minify_css', 1 - $domain_options['minify_css'] );
domain_check( ! $result['success'] && 'failed' === $result['outcome'] && $before === $domain_options, 'Refused changed writer is failure, not success' );
$writes = $domain_writes;
$result = set_api_option( 'minify_css', $domain_options['minify_css'] );
domain_check( $result['success'] && 'unchanged' === $result['outcome'] && $writes === $domain_writes, 'Valid no-op succeeds without invoking a refusing writer' );
$domain_writer = 'adjust'; $domain_options['local_css_mode'] = 'critical';
$result = set_api_option( 'local_css_mode', 'used' );
domain_check( ! $result['success'] && 'adjusted' === $result['outcome'] && 'off' === $result['new_value'], 'Write-time transformation differs explicitly from requested application' );
$domain_writer = 'accept';
$domain_options['cdn_cnames'] = [ 'https://saved.example.test' ];
$domain_options['cdn_zone'] = [ 'images' ];
$domain_writer = 'partial_cdn';
$result = set_api_option( 'cdn_cnames', [ 'second.example.test' ], 'update' );
domain_check( ! $result['success'] && 'adjusted' === $result['outcome'], 'CDN response verifies the whole normalized host/scope group, including partial writer adjustment' );
$domain_writer = 'accept';
$domain_options['cdn_cnames'] = 'broken-stored-type';
$result = set_api_option( 'cdn_zone', [ 'images' ] );
domain_check( ! $result['success'] && 'invalid' === $result['outcome'], 'Malformed stored CDN type returns an explicit correction error without throwing' );
$domain_options['cdn_cnames'] = [];
$domain_options['cdn_zone'] = [];
$mcp_extensions = [ 'third_party_option' ];
$result = set_api_option( 'third_party_option', 'third-party-defined-value' );
domain_check( $result['success'] && 'third-party-defined-value' === $domain_options['third_party_option'], 'Existing third-party allowlist extension retains its supplied value policy' );
domain_check( ! set_api_option( 'cdn_state', 'ready' )['success'], 'Derived read-only state remains unavailable for API writes' );
foreach ( [ null, [], [ 'option_name' => [] ], [ 'option_name' => 'lazyload' ], [ 'option_name' => 'lazyload', 'option_value' => true, 'update_mode' => [] ], [ 'option_name' => 'lazyload', 'option_value' => true, 'update_mode' => null ] ] as $input ) {
	$before = $domain_options; $writes = $domain_writes;
	$result = $domain_ability->execute( $input );
	domain_check( ! $result['success'] && $before === $domain_options && $writes === $domain_writes, 'Malformed direct API input fails without warnings or writes' );
}
$domain_authorized = false; $writes = $domain_writes;
$result = set_api_option( 'minify_css', 0 );
domain_check( ! $result['success'] && $writes === $domain_writes, 'Direct execute retains the native capability boundary' );
restore_error_handler();
echo 'PASS: ', $domain_checks, ' shared settings/API assertions; isolated in-memory writers only.', PHP_EOL;
