<?php
/** Native endpoint authorization, with recording operations and no WordPress bootstrap. */
define( 'ABSPATH', __DIR__ . '/' );
define( 'NOOBROCKET_FILE', dirname( __DIR__ ) . '/noobrocket/noobrocket.php' );
$hooks = [];
$capabilities = [ 'noobrocket_manage_options' => true, 'deactivate_plugin' => false ];
$conflicts = [ 'conflict/plugin.php' ];
$checks = 0;
function add_action( $hook, $callback, ...$args ) { $GLOBALS['hooks'][ $hook ] = $callback; }
function add_filter( ...$args ) {}
function plugin_basename( $path ) { return basename( $path ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( $value ) ); }
function wp_unslash( $value ) { return $value; }
function esc_html__( $value, $domain = '' ) { return $value; }
function wp_verify_nonce( $value, $action ) { return 'fixture' === $value && $GLOBALS['nonce_action'] === $action; }
function current_user_can( $capability, ...$args ) { return $GLOBALS['capabilities'][ $capability ] ?? false; }
function apply_filters( $hook, $value ) { return 'noobrocket_plugins_to_deactivate' === $hook ? $GLOBALS['conflicts'] : $value; }
function is_plugin_active( $plugin ) { return 'conflict/plugin.php' === $plugin; }
function wp_nonce_ays( $action ) { throw new RuntimeException( 'nonce' ); }
function wp_die( $message, $title = '', $args = [] ) { throw new RuntimeException( 'denied' ); }
class RecordedDeactivation extends RuntimeException {
	public $plugin;
	public function __construct( $plugin ) { $this->plugin = $plugin; parent::__construct( 'recorded' ); }
}
function deactivate_plugins( $plugin ) { throw new RecordedDeactivation( $plugin ); }
require dirname( __DIR__ ) . '/noobrocket/inc/admin/admin.php';
function deactivate_check( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
	++$GLOBALS['checks'];
}
function request_deactivation( $plugin, $nonce_target ) {
	$_GET = [ 'plugin' => $plugin, '_wpnonce' => 'fixture' ];
	$GLOBALS['nonce_action'] = 'noobrocket_deactivate_plugin:' . $nonce_target;
	try { noobrocket_deactivate_plugin(); } catch ( RuntimeException $result ) { return $result; }
	throw new RuntimeException( 'Expected a denied or recorded operation.' );
}
deactivate_check( isset( $hooks['admin_post_noobrocket_deactivate_plugin'] ) && ! isset( $hooks['admin_post_deactivate_plugin'] ), 'Only the owned action is registered.' );
deactivate_check( 'denied' === request_deactivation( 'conflict/plugin.php', 'conflict/plugin.php' )->getMessage(), 'A delegated performance manager cannot deactivate plugins.' );
$capabilities['deactivate_plugin'] = true;
$result = request_deactivation( 'conflict/plugin.php', 'conflict/plugin.php' );
deactivate_check( $result instanceof RecordedDeactivation && 'conflict/plugin.php' === $result->plugin, 'Core-authorized conflict resolution works.' );
deactivate_check( 'nonce' === request_deactivation( 'unrelated/plugin.php', 'conflict/plugin.php' )->getMessage(), 'A target change invalidates the nonce.' );
deactivate_check( 'denied' === request_deactivation( 'unrelated/plugin.php', 'unrelated/plugin.php' )->getMessage(), 'The endpoint cannot manage unrelated plugins.' );
$capabilities['noobrocket_manage_options'] = false;
deactivate_check( 'denied' === request_deactivation( 'conflict/plugin.php', 'conflict/plugin.php' )->getMessage(), 'The NoobRocket permission is also required.' );
echo "PASS: $checks plugin deactivation checks; recording operations only.\n";
