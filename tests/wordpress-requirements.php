<?php
/** Standalone requirements failure proof; no WordPress site or network writes. */
declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );
$wp_version = '7.1.3';
$hooks = [];
$option_reads = 0;
$can_manage = true;
$checks = 0;
function add_action( $name, $callback ) { $GLOBALS['hooks'][ $name ][] = $callback; }
function add_filter( $name, $callback ) { $GLOBALS['hooks'][ $name ][] = $callback; }
function get_option( $name ) { $GLOBALS['option_reads']++; return [ 'consumer_key' => 'private-key', 'consumer_email' => 'private@example.invalid' ]; }
function current_user_can( $capability ) { return $GLOBALS['can_manage'] && 'manage_options' === $capability; }
function __( $value ) { return $value; }
function wp_kses_post( $value ) { return $value; }
function requirements_check( bool $condition, string $message ): void {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
	$GLOBALS['checks']++;
}
function requirements_instance( string $php = '7.4', string $wp = '5.8' ): NoobRocket_Requirements_Check {
	return new NoobRocket_Requirements_Check( [
		'plugin_name' => 'Cache-Cache',
		'plugin_version' => '0.1.0-beta.2',
		'php_version' => $php,
		'wp_version' => $wp,
		// Bootstrap compatibility: obsolete rollback arguments must be harmless.
		'plugin_file' => '/noobrocket/noobrocket.php',
		'plugin_last_version' => '1.2.0',
	] );
}
function requirements_notice( NoobRocket_Requirements_Check $instance ): string {
	ob_start();
	try { $instance->notice(); return ob_get_contents(); }
	finally { ob_end_clean(); }
}
require dirname( __DIR__ ) . '/noobrocket/inc/classes/class-noobrocket-requirements-check.php';

$supported = requirements_instance();
requirements_check( 0 === $option_reads, 'requirements checking never reads credentials or plugin options' );
requirements_check( $supported->check(), 'supported PHP and WordPress pass' );
requirements_check( [] === $hooks, 'supported runtime adds no failure hooks' );
requirements_check( ! method_exists( $supported, 'rollback' ) && ! method_exists( $supported, 'add_own_ua' ), 'remote rollback and credential-bearing HTTP callbacks are absent' );

$unsupported_php = requirements_instance( '99.0' );
requirements_check( ! $unsupported_php->check(), 'unsupported PHP stops bootstrap' );
requirements_check( [ 'admin_notices' ] === array_keys( $hooks ), 'only the native requirements notice is registered' );
requirements_check( [ $unsupported_php, 'notice' ] === $hooks['admin_notices'][0], 'registered notice belongs to failed check' );
$message = requirements_notice( $unsupported_php );
requirements_check( false !== strpos( $message, 'Cache-Cache 0.1.0-beta.2 requires at least:' ) && false !== strpos( $message, 'PHP 99.0' ), 'notice states product and required PHP version' );
requirements_check( false === strpos( $message, '<li>WordPress' ), 'passing WordPress is omitted from failure list' );
requirements_check( false !== strpos( $message, 'compatible Cache-Cache ZIP from your own backup' ), 'notice gives truthful local recovery guidance' );
requirements_check( false === strpos( $message, 'href=' ) && false === strpos( $message, 'admin-post' ) && false === strpos( $message, 'private-key' ), 'notice exposes no rollback control, endpoint or credential' );
$can_manage = false;
requirements_check( '' === requirements_notice( $unsupported_php ), 'users without manage_options see no notice' );
$can_manage = true;
$hooks = [];
$unsupported_wp = requirements_instance( '7.4', '99.0' );
requirements_check( ! $unsupported_wp->check(), 'unsupported WordPress stops bootstrap' );
$message = requirements_notice( $unsupported_wp );
requirements_check( false !== strpos( $message, '<li>WordPress 99.0' ) && false === strpos( $message, '<li>PHP' ), 'WordPress failure lists only its own unmet requirement' );
requirements_check( [ 'admin_notices' ] === array_keys( $hooks ) && 0 === $option_reads, 'every failure keeps remote hooks and credential reads absent' );
echo 'PASS: ', $checks, ' requirements assertions.', PHP_EOL;
