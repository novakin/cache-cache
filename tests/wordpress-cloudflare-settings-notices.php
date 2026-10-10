<?php
/** Native Cloudflare settings-notice proof. No WordPress state, provider calls, or network. */
define( 'ABSPATH', __DIR__ );

$cloudflare_notice_checks = 0;
$wp_settings_errors      = [];

class WP_Error {
	private $message;
	public function __construct( $code, $message ) { $this->message = $message; }
	public function get_error_message() { return $this->message; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function __( $value, $domain = null ) { return $value; }
function add_settings_error( $setting, $code, $message, $type = 'error' ) {
	$GLOBALS['wp_settings_errors'][] = compact( 'setting', 'code', 'message', 'type' );
}
function notice_check( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
	$GLOBALS['cloudflare_notice_checks']++;
}

$plugin_root = dirname( __DIR__ ) . '/noobrocket/';
require $plugin_root . 'inc/classes/event-management/subscriber-interface.php';
require $plugin_root . 'inc/classes/admin/class-abstract-options.php';
require $plugin_root . 'inc/classes/admin/class-options.php';
require $plugin_root . 'inc/classes/admin/class-options-data.php';
require $plugin_root . 'inc/Addon/Cloudflare/Auth/AuthInterface.php';
require $plugin_root . 'inc/Addon/Cloudflare/Auth/AuthFactoryInterface.php';
require $plugin_root . 'inc/Addon/Cloudflare/Auth/APIKeyFactory.php';
require $plugin_root . 'inc/Addon/Cloudflare/Cloudflare.php';
require $plugin_root . 'inc/Addon/Cloudflare/Subscriber.php';

class NoticeConnectionProbe extends NoobRocket\Addon\Cloudflare\Cloudflare {
	public $calls  = [];
	public $result;
	public function __construct() { $this->result = new WP_Error( 'cloudflare_no_zone_id', 'Missing Cloudflare Zone ID.' ); }
	public function check_connection( string $zone_id = '' ) {
		$this->calls[] = $zone_id;
		return $this->result;
	}
}

$saved = [
	'do_cloudflare'      => 1,
	'cloudflare_email'   => 'owner@example.test',
	'cloudflare_api_key' => 'synthetic-key',
	'cloudflare_zone_id' => '',
	'lazyload'           => 0,
];
$options    = new NoobRocket\Admin\Options_Data( $saved );
$connection = new NoticeConnectionProbe();
$subscriber = new NoobRocket\Addon\Cloudflare\Subscriber(
	$connection,
	$options,
	new NoobRocket\Admin\Options(),
	new NoobRocket\Addon\Cloudflare\Auth\APIKeyFactory( $options )
);

set_error_handler( function ( $severity, $message, $file, $line ) {
	throw new ErrorException( $message, 0, $severity, $file, $line );
}, E_WARNING | E_NOTICE );

$input = array_merge( $saved, [ 'do_cloudflare' => 0, 'cloudflare_email' => 'changed@example.test' ] );
notice_check( $input === $subscriber->display_settings_notice( $input, $saved ), 'Disabling integration preserves submitted settings' );
notice_check( [] === $connection->calls && [] === $wp_settings_errors, 'Disabling integration neither probes nor emits stale missing-zone errors' );

$input = array_merge( $saved, [ 'lazyload' => 1 ] );
notice_check( $input === $subscriber->display_settings_notice( $input, $saved ), 'Unrelated Save preserves settings' );
notice_check( [] === $connection->calls && [] === $wp_settings_errors, 'Unrelated Save does not revalidate an unchanged incomplete connection' );

$subscriber->display_settings_notice( array_merge( $saved, [ 'cloudflare_devmode' => 1, 'cloudflare_auto_settings' => 1 ] ), $saved );
notice_check( [] === $connection->calls && [] === $wp_settings_errors, 'Provider behavior choices keep their own action feedback' );

$subscriber->display_settings_notice( $saved, array_merge( $saved, [ 'do_cloudflare' => 0 ] ) );
notice_check( [ '' ] === $connection->calls, 'Enabling connection validates even unchanged blank Zone ID' );
notice_check( 1 === count( $wp_settings_errors ) && 'cloudflare_api_key_invalid' === $wp_settings_errors[0]['code'] && 'error' === $wp_settings_errors[0]['type'], 'Enabled invalid connection retains native Settings API error ownership' );
notice_check( false !== strpos( $wp_settings_errors[0]['message'], 'Missing Cloudflare Zone ID.' ), 'Original provider error survives native feedback' );

foreach ( [ 'cloudflare_email' => 'changed@example.test', 'cloudflare_api_key' => 'changed-synthetic-key', 'cloudflare_zone_id' => 'changed-synthetic-zone' ] as $field => $new_value ) {
	$connection->calls  = [];
	$wp_settings_errors = [];
	$input              = array_merge( $saved, [ $field => $new_value ] );
	notice_check( $input === $subscriber->display_settings_notice( $input, $saved ), 'Credential edit preserves ' . $field );
	notice_check( [ $input['cloudflare_zone_id'] ] === $connection->calls && 1 === count( $wp_settings_errors ), 'Credential edit validates ' . $field );
}

$connection->calls  = [];
$wp_settings_errors = [];
$subscriber->display_settings_notice( $saved, array_merge( $saved, [ 'do_cloudflare' => '1' ] ) );
notice_check( [] === $connection->calls && [] === $wp_settings_errors, 'Equivalent enabled checkbox representations do not revalidate credentials' );

$subscriber->display_settings_notice( array_diff_key( $saved, [ 'cloudflare_zone_id' => true ] ), $saved );
notice_check( [] === $connection->calls && [] === $wp_settings_errors, 'Missing Zone ID key retains prior no-probe behavior' );

$connection->result = true;
$input              = array_merge( $saved, [ 'cloudflare_zone_id' => 'working-synthetic-zone' ] );
notice_check( $input === $subscriber->display_settings_notice( $input, $saved ), 'Valid edited connection preserves settings' );
notice_check( [ 'working-synthetic-zone' ] === $connection->calls && [] === $wp_settings_errors, 'Valid edited connection emits no failure' );

restore_error_handler();
echo 'PASS: ', $cloudflare_notice_checks, ' native Cloudflare settings-notice assertions.', PHP_EOL;
