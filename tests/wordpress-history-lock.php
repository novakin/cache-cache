<?php
/** Native Rules/Snapshots ownership and recovery. Mocked WordPress hooks/options only. */
define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
class WP_Error {
	private $code;
	private $message;
	public function __construct( $code, $message ) { $this->code = $code; $this->message = $message; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}
class WP_Upgrader {
	public static $held = false;
	public static $creates = 0;
	public static $releases = 0;
	public static function create_lock( $name, $timeout ) {
		++self::$creates;
		if ( self::$held ) { return false; }
		self::$held = true;
		if ( $GLOBALS['history_change_on_lock'] ) {
			$GLOBALS['history_options']['noobrocket_settings']['minify_js'] = 1;
			$GLOBALS['history_change_on_lock'] = false;
		}
		return true;
	}
	public static function release_lock( $name ) { ++self::$releases; self::$held = false; return true; }
}
function __( $text, $domain = '' ) { return $text; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function current_user_can( $capability ) { return true; }
function get_current_user_id() { return 9; }
function noobrocket_free_settings( array $settings ) { return $settings; }
function wpm_apply_filters_typed( $type, $hook, $value ) { return $value; }
function wp_json_encode( $value ) { return json_encode( $value ); }
function wp_generate_uuid4() { return 'fixture-' . ++$GLOBALS['history_uuid']; }
function wp_cache_delete( $key, $group = '' ) { $GLOBALS['history_cache_deletes'][] = [ $key, $group ]; }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( $value ) ); }
function post_type_exists( $value ) { return 'post' === $value; }
function add_settings_error( $setting, $code, $message, $type = 'error' ) { $GLOBALS['history_errors'][] = compact( 'setting', 'code', 'message', 'type' ); }
function set_transient( $key, $value, $ttl ) { $GLOBALS['history_notices'][ $key ] = $value; return true; }
function get_option( $key, $default = false ) { return $GLOBALS['history_options'][ $key ] ?? $default; }
function update_option( $key, $value, $autoload = null ) {
	$old = get_option( $key );
	$snapshots = $GLOBALS['active_history'];
	if ( 'noobrocket_settings' === $key ) { $value = $snapshots->before_settings( $value, $old ); }
	if ( NoobRocket\Engine\Optimization\Assets\Rules::OPTION === $key ) { $value = $snapshots->before_rules( $value, $old ); }
	if ( $old === $value || in_array( $key, $GLOBALS['history_refuse'], true ) ) { return false; }
	if ( $GLOBALS['history_refuse_metadata'] && 'noobrocket_settings' === $key && '' === ( $value['cloudflare_old_settings'] ?? null ) && '' !== ( $old['cloudflare_old_settings'] ?? '' ) ) { return false; }
	$GLOBALS['history_options'][ $key ] = $value;
	$GLOBALS['history_writes'][] = $key;
	if ( 'noobrocket_settings' === $key ) {
		if ( $GLOBALS['history_nested_metadata'] ) {
			$GLOBALS['history_nested_metadata'] = false;
			$metadata = $value;
			$metadata['cloudflare_old_settings'] = '';
			update_option( $key, $metadata );
		}
		$snapshots->settings_updated( $old, $value );
	}
	if ( NoobRocket\Engine\Optimization\Assets\Rules::OPTION === $key ) {
		if ( false === $old ) { $snapshots->first_rules_added( $key, $value ); } else { $snapshots->rules_updated( $old, $value ); }
	}
	return true;
}
function history_check( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
	++$GLOBALS['history_checks'];
}

$plugin = dirname( __DIR__ ) . '/noobrocket/';
foreach ( [ 'inc/classes/event-management/subscriber-interface.php', 'inc/Engine/Abilities/Options/AllowedOptions.php', 'inc/Engine/Optimization/Assets/Rules.php', 'inc/Engine/Optimization/Assets/Snapshots.php' ] as $file ) { require $plugin . $file; }
use NoobRocket\Engine\Optimization\Assets\Rules;
use NoobRocket\Engine\Optimization\Assets\Snapshots;
$history_checks = 0;
$history_options = [];
$history_uuid = 0;
$history_refuse = [];
$history_errors = [];
$history_notices = [];
$history_writes = [];
$history_cache_deletes = [];
$history_nested_metadata = false;
$history_refuse_metadata = false;
$history_change_on_lock = false;
$native_rules = new Rules();
$active_history = new Snapshots( $native_rules );
set_error_handler( function ( $severity, $message, $file, $line ) { throw new ErrorException( $message, 0, $severity, $file, $line ); }, E_WARNING | E_NOTICE );

// The actual Rules owner calls one native platform lock; separate owners cannot enter together.
$other_rules = new Rules();
history_check( true === $native_rules->acquire_edit_lock() && $native_rules->has_edit_lock(), 'Native lock owner acquires once' );
history_check( is_wp_error( $other_rules->acquire_edit_lock() ) && ! $other_rules->has_edit_lock(), 'Another owner cannot enter an occupied platform lock' );
$releases = WP_Upgrader::$releases;
$other_rules->release_edit_lock();
history_check( $releases === WP_Upgrader::$releases && WP_Upgrader::$held, 'A nonowner cannot release another operation lock' );
history_check( count( $history_cache_deletes ) === 5, 'Acquiring owner invalidates settings/rules/history option caches before review' );
$native_rules->release_edit_lock();
history_check( ! WP_Upgrader::$held && ! $native_rules->has_edit_lock(), 'Owner releases platform lock' );

// Ordinary save includes an early provider metadata write, then records its original prior state once.
$old = [ 'minify_css' => 0, 'cloudflare_auto_settings' => 1, 'cloudflare_old_settings' => 'private-backup', 'cloudflare_api_key' => 'private-key' ];
$history_options = [ 'noobrocket_settings' => $old, Rules::OPTION => [], Snapshots::OPTION => [] ];
$new = array_merge( $old, [ 'minify_css' => 1, 'cloudflare_auto_settings' => 0 ] );
$creates = WP_Upgrader::$creates; $releases = WP_Upgrader::$releases;
$history_nested_metadata = true;
update_option( 'noobrocket_settings', $new );
$records = $active_history->all();
history_check( 1 === count( $records ) && 0 === $records[0]['settings']['minify_css'] && 1 === $records[0]['settings']['cloudflare_auto_settings'], 'Nested provider metadata retains one original ordinary recovery state' );
history_check( '' === $history_options['noobrocket_settings']['cloudflare_old_settings'] && ! isset( $records[0]['settings']['cloudflare_old_settings'], $records[0]['settings']['cloudflare_api_key'] ), 'Provider metadata/credentials stay out of history while the metadata clear persists' );
history_check( WP_Upgrader::$creates === $creates + 1 && WP_Upgrader::$releases === $releases + 1 && ! $native_rules->has_edit_lock(), 'Nested metadata shares ownership and releases only after outer history completion' );

$before = $active_history->all(); $writes = count( $history_writes );
update_option( 'noobrocket_settings', $history_options['noobrocket_settings'] );
history_check( $before === $active_history->all() && $writes === count( $history_writes ) && ! $native_rules->has_edit_lock(), 'No-op does not create history or leave a lock' );
$history_refuse = [ 'noobrocket_settings' ];
$refused = array_merge( $history_options['noobrocket_settings'], [ 'minify_css' => 0 ] );
update_option( 'noobrocket_settings', $refused );
history_check( $native_rules->has_edit_lock() && $before === $active_history->all(), 'Refused native settings write has no post-commit snapshot' );
$active_history->release_pending_lock();
history_check( ! WP_Upgrader::$held && ! $native_rules->has_edit_lock(), 'Shutdown releases request-owned failed-write lock' );

// Failure to record history after an ordinary commit reports loss of recovery, without inventing rollback.
$history_refuse = [ Snapshots::OPTION ]; $history_errors = [];
update_option( 'noobrocket_settings', $refused );
history_check( 0 === $history_options['noobrocket_settings']['minify_css'] && $before === $active_history->all(), 'History failure does not roll back an already committed setting' );
history_check( 1 === count( $history_errors ) && 'noobrocket_history_failed' === $history_errors[0]['code'] && false !== strpos( $history_errors[0]['message'], 'change was saved' ), 'Ordinary history failure feedback states actual saved outcome' );
history_check( ! $native_rules->has_edit_lock() && ! WP_Upgrader::$held, 'History refusal still releases ordinary ownership' );

// A restore cannot begin without a verified current-state recovery record.
$target_rule = [ 'type' => 'script', 'handle' => 'restored', 'scope' => 'everywhere', 'value' => '', 'exceptions' => [] ];
$snapshot = [ 'id' => 'restore-target', 'settings' => [ 'minify_css' => 1 ], 'rules' => [ $target_rule ] ];
$history_options[ Snapshots::OPTION ] = [ $snapshot ];
$before = $history_options; $writes = count( $history_writes );
$result = $active_history->restore( 'restore-target', $active_history->review_token( $snapshot ) );
history_check( is_wp_error( $result ) && 'snapshot_history_write' === $result->get_error_code(), 'Refused recovery record aborts restoration explicitly' );
history_check( $before === $history_options && $writes === count( $history_writes ), 'Recovery refusal preserves settings, public rules and retained target without a write' );
history_check( ! $native_rules->has_edit_lock() && ! WP_Upgrader::$held, 'Aborted restore releases operation ownership' );
$history_refuse = [ Rules::OPTION ];
$result = $active_history->restore( 'restore-target', $active_history->review_token( $snapshot ) );
history_check( is_wp_error( $result ) && 'snapshot_write' === $result->get_error_code(), 'Multi-option partial restore remains a reported incomplete operation' );
history_check( 0 === $active_history->all()[0]['settings']['minify_css'] && [] === $active_history->all()[0]['rules'], 'Incomplete restore retains the verified original recovery state' );

$history_refuse = []; $history_options[ Snapshots::OPTION ] = [ $snapshot ];
$result = $active_history->restore( 'restore-target', $active_history->review_token( $snapshot ) );
history_check( true === $result && [ $target_rule ] === $native_rules->get() && ! $native_rules->has_edit_lock(), 'Successful restore saves rules and releases ownership' );

// An occupied lock blocks a stale ordinary write and must never be released by the rejected owner.
WP_Upgrader::$held = true; $history_errors = [];
$before = $history_options['noobrocket_settings'];
update_option( 'noobrocket_settings', array_merge( $before, [ 'minify_css' => 0 ] ) );
history_check( $before === $history_options['noobrocket_settings'] && WP_Upgrader::$held && ! $native_rules->has_edit_lock(), 'Busy native boundary refuses a changed write without disturbing external owner' );
history_check( 'noobrocket_settings_busy' === $history_errors[0]['code'], 'Busy ordinary write retains visible native correction feedback' );
WP_Upgrader::$held = false;

$history_options['noobrocket_settings']['minify_js'] = 0;
$old = $history_options['noobrocket_settings']; $before = $active_history->all();
$history_change_on_lock = true;
update_option( 'noobrocket_settings', array_merge( $old, [ 'minify_css' => 0 ] ) );
history_check( 1 === $history_options['noobrocket_settings']['minify_js'] && $old['minify_css'] === $history_options['noobrocket_settings']['minify_css'] && $before === $active_history->all(), 'Fresh locked read rejects a stale proposal and preserves intervening saved settings' );
history_check( ! WP_Upgrader::$held && ! $native_rules->has_edit_lock(), 'Conflict detection releases only its own newly acquired lock' );

$history_options = [ 'noobrocket_settings' => [ 'minify_css' => 0, 'cloudflare_old_settings' => 'pending-provider-backup' ], Rules::OPTION => [], Snapshots::OPTION => [] ];
$history_nested_metadata = true; $history_refuse_metadata = true;
update_option( 'noobrocket_settings', array_merge( $history_options['noobrocket_settings'], [ 'minify_css' => 1 ] ) );
history_check( 1 === count( $active_history->all() ) && 0 === $active_history->all()[0]['settings']['minify_css'] && 'pending-provider-backup' === $history_options['noobrocket_settings']['cloudflare_old_settings'], 'A refused nested metadata write preserves provider recovery and records the outer saved change once' );
$active_history->release_pending_lock();
history_check( ! WP_Upgrader::$held && ! $native_rules->has_edit_lock(), 'Shutdown also releases ownership when a failed nested write has no post hook' );
$history_refuse_metadata = false;

$history_refuse = [ Snapshots::OPTION ]; $before = $active_history->all();
update_option( Rules::OPTION, [ $target_rule ] );
history_check( [ $target_rule ] === $native_rules->get() && $before === $active_history->all(), 'Rule publication remains committed if its post-commit history writer refuses' );
history_check( 'history' === $history_notices['noobrocket_asset_notice_9']['route'] && false !== strpos( $history_notices['noobrocket_asset_notice_9']['message'], 'change was saved' ), 'Rule history failure survives admin-post redirect through the existing private notice owner' );
$history_refuse = [];

unset( $history_options[ Rules::OPTION ] ); $history_options[ Snapshots::OPTION ] = [];
update_option( Rules::OPTION, [ $target_rule ] );
history_check( 1 === count( $active_history->all() ) && [] === $active_history->all()[0]['rules'] && ! WP_Upgrader::$held, 'First rule write uses native pre-update and post-add lifecycle to retain its empty prior state once' );
history_check( ! isset( Snapshots::get_subscribed_events()['pre_add_option_noobrocket_asset_rules'] ), 'No nonexistent WordPress pre-add hook is advertised as a protection boundary' );
restore_error_handler();
echo 'PASS: ', $history_checks, ' native lock/history recovery assertions; no WordPress bootstrap/database/network.', PHP_EOL;
