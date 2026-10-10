<?php
/**
 * Read-only local CSS settings checks against a disposable WordPress install.
 * php tests/wordpress-local-css-settings.php /absolute/path/to/wordpress
 */
$wordpress = isset( $argv[1] ) ? realpath( $argv[1] ) : false;
if ( ! $wordpress || ! is_file( $wordpress . '/wp-load.php' ) || ! is_file( $wordpress . '/.noobrocket-acceptance-site' ) ) {
	fwrite( STDERR, "Provide a disposable WordPress install with .noobrocket-acceptance-site.\n" );
	exit( 2 );
}
$_SERVER['HTTP_HOST'] = '127.0.0.1:8765';
$_SERVER['REQUEST_URI'] = '/';
// A warm homepage must not terminate bootstrap before these read-only checks.
$_SERVER['REQUEST_METHOD'] = 'POST';
define( 'WP_ADMIN', true );
// Option filters can record history even when the test never saves settings.
// Preinitialized native hooks protect bootstrap and every subsequent assertion.
$local_css_blocked_writes = 0;
$GLOBALS['wp_filter']['query'][PHP_INT_MAX][] = [
	'function'      => static function ( $sql ) use ( &$local_css_blocked_writes ) {
		if ( preg_match( '/^\s*(INSERT|UPDATE|DELETE|REPLACE|ALTER|CREATE|DROP|TRUNCATE)\b/i', $sql ) ) {
			++$local_css_blocked_writes;
			return 'SELECT 1';
		}
		return $sql;
	},
	'accepted_args' => 1,
];
$GLOBALS['wp_filter']['pre_http_request'][PHP_INT_MAX - 1][] = [
	'function'      => static function () {
		return new WP_Error( 'local_css_settings_read_only', 'External HTTP is disabled during read-only verification.' );
	},
	'accepted_args' => 1,
];
require $wordpress . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';

$checks = 0;
function local_css_settings_check( $condition, $message ) {
	global $checks;
	if ( ! $condition ) {
		throw new RuntimeException( 'FAIL: ' . $message );
	}
	++$checks;
}

$protected_rows = static function () use ( $wpdb ) {
	return $wpdb->get_results( "SELECT option_id, option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name IN ('noobrocket_settings', 'noobrocket_asset_rules', 'noobrocket_settings_snapshots') ORDER BY option_name", ARRAY_A );
};
$before_rows = $protected_rows();
$stored = get_option( 'noobrocket_settings' );
local_css_settings_check( is_array( $stored ), 'NoobRocket is installed and active' );
$defaults = noobrocket_free_settings( [] );
local_css_settings_check( 'off' === $defaults['local_css_mode'] && [] === $defaults['local_css_safelist'], 'local CSS defaults off with no safelist' );

$unsafe = [
	'local_css_mode'       => 'used',
	'local_css_safelist'   => " .menu-open\r\n.modal\r.is-active\n.menu-open\n(.*)\n",
	'optimize_css_delivery' => 1,
	'remove_unused_css'    => 1,
	'async_css'            => 1,
	'consumer_key'         => 'unused-import-key',
	'consumer_email'       => 'unused@example.test',
	'secret_key'           => 'unused-import-secret',
	'license'              => 1,
];
$expected_safelist = [ '.menu-open', '.modal', '.is-active', '(.*)' ];
foreach ( [ 'option_noobrocket_settings', 'pre_update_option_noobrocket_settings', 'pre_add_option_noobrocket_settings', 'noobrocket_first_install_options' ] as $filter ) {
	$filtered = apply_filters( $filter, $unsafe, $stored, 'noobrocket_settings' );
	local_css_settings_check( 'used' === $filtered['local_css_mode'] && $expected_safelist === $filtered['local_css_safelist'], $filter . ' retains local choices and literal wildcard text' );
	local_css_settings_check( 0 === $filtered['optimize_css_delivery'] && 0 === $filtered['remove_unused_css'] && 0 === $filtered['async_css'], $filter . ' cannot enable cloud CSS' );
	local_css_settings_check( ! array_intersect( [ 'consumer_key', 'consumer_email', 'secret_key', 'license' ], array_keys( $filtered ) ), $filter . ' removes imported credentials' );
}
foreach ( [ 'cloud', '', true, 1, [], null ] as $invalid_mode ) {
	$filtered = noobrocket_free_settings( [ 'local_css_mode' => $invalid_mode ] );
	local_css_settings_check( 'off' === $filtered['local_css_mode'], 'invalid imported mode falls back to off' );
}
local_css_settings_check( [ '.dialog', '0' ] === noobrocket_sanitize_local_css_safelist( [ '.dialog', [ '.nested' ], true, null, 0, '0', '.dialog' ] ), 'malformed imported entries ignored without losing literal zero' );

$settings = new NoobRocket\Engine\Admin\Settings\Settings( new NoobRocket\Admin\Options_Data( $stored ) );
$submitted = array_merge( $stored, $unsafe, [
	'ignore'       => 1,
	'minify_css'   => 1,
	'exclude_css'  => "/wp-content/themes/example/keep.css\nhttps://styles.example.test/preserve.css",
] );
$sanitized = $settings->sanitize_callback( $submitted );
local_css_settings_check( 'used' === $sanitized['local_css_mode'] && $expected_safelist === $sanitized['local_css_safelist'], 'registered settings sanitizer preserves local CSS settings' );
local_css_settings_check( 1 === $sanitized['minify_css'] && noobrocket_sanitize_textarea_field( 'exclude_css', $submitted['exclude_css'] ) === $sanitized['exclude_css'], 'existing minification and CSS exclusions preserved' );
local_css_settings_check( 0 === $sanitized['remove_unused_css'] && 0 === $sanitized['async_css'] && 0 === $sanitized['optimize_css_delivery'], 'form cannot enable old cloud generators' );
$invalid = $settings->sanitize_callback( array_merge( $submitted, [ 'local_css_mode' => 'cloud' ] ) );
local_css_settings_check( 'off' === $invalid['local_css_mode'], 'invalid form mode falls back to off' );

$allowed = new NoobRocket\Engine\Abilities\Options\AllowedOptions();
$allowlist = $allowed->get();
local_css_settings_check( in_array( 'local_css_mode', $allowlist, true ) && in_array( 'local_css_safelist', $allowlist, true ), 'Abilities allow local CSS fields' );
local_css_settings_check( ! in_array( 'remove_unused_css', $allowlist, true ) && ! in_array( 'async_css', $allowlist, true ), 'Abilities continue rejecting cloud fields' );
$ability = new NoobRocket\Engine\Abilities\Options\SetOption( $allowed );
$method = new ReflectionMethod( $ability, 'sanitize_value' );
foreach ( [ 'off', 'critical', 'used' ] as $mode ) {
	local_css_settings_check( $mode === $method->invoke( $ability, 'local_css_mode', $mode, 'replace', 'off' ), 'Abilities accepts ' . $mode . ' mode' );
}
local_css_settings_check( is_wp_error( $method->invoke( $ability, 'local_css_mode', 'cloud', 'replace', 'critical' ) ), 'Abilities invalid mode returns an explicit error before writing' );
local_css_settings_check( [ '.old', '.dialog', '(.*)' ] === $method->invoke( $ability, 'local_css_safelist', ".dialog\n(.*)\n.old", 'update', [ '.old' ] ), 'Abilities merges literal safelist entries' );
local_css_settings_check( [ '.dialog' ] === $method->invoke( $ability, 'local_css_safelist', [ '.dialog' ], 'replace', [ '.old' ] ), 'Abilities replaces literal safelist entries' );
local_css_settings_check( [] === $method->invoke( $ability, 'local_css_safelist', '', 'replace', [ '.old' ] ), 'Abilities can clear safelist' );
wp_set_current_user( 0 );
local_css_settings_check( ! $ability->check_permissions(), 'anonymous users cannot modify settings through Abilities' );
wp_set_current_user( 1 );
local_css_settings_check( $ability->check_permissions(), 'site administrator keeps native NoobRocket settings capability' );
local_css_settings_check( get_option( 'noobrocket_settings' ) === $stored, 'verification does not change saved plugin settings' );
local_css_settings_check( $local_css_blocked_writes > 0, 'read-only guard blocks native history side effects' );
local_css_settings_check( $before_rows === $protected_rows(), 'saved settings, published rules and history rows remain exactly unchanged' );
echo 'PASS: local CSS settings (', $checks, " assertions; read-only)\n";
