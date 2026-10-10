<?php
/** Isolated asset/history regressions. No WordPress bootstrap, database or network. */
define( 'ABSPATH', __DIR__ . '/' );
define( 'NOOBROCKET_PATH', dirname( __DIR__ ) . '/noobrocket/' );

class WP_Error {
	private $code;
	private $message;
	public function __construct( string $code, string $message ) { $this->code = $code; $this->message = $message; }
	public function get_error_code(): string { return $this->code; }
	public function get_error_message(): string { return $this->message; }
}
class WP_Dependencies {
	public $registered = [];
	public $queue = [];
	public $done = [];
	public function add( string $handle, $src, array $deps = [] ): void {
		$this->registered[ $handle ] = (object) [ 'src' => $src, 'deps' => $deps ];
	}
	public function dequeue( string $handle ): void {
		$this->queue = array_values( array_diff( $this->queue, [ $handle ] ) );
	}
}

$state = [ 'options' => [], 'meta' => [], 'authorized' => true, 'nonce' => true, 'writes' => 0, 'meta_writes' => 0, 'purges' => 0, 'uuids' => 0 ];
function __( string $message, string $domain = '' ): string { return $message; }
function _n( string $single, string $plural, int $number, string $domain = '' ): string { return 1 === $number ? $single : $plural; }
function esc_html__( string $message, string $domain = '' ): string { return $message; }
function esc_html( $value ): string { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ): string { return esc_html( $value ); }
function esc_url( string $value ): string { return $value; }
function esc_html_e( string $message, string $domain = '' ): void { echo esc_html( $message ); }
function esc_attr_e( string $message, string $domain = '' ): void { echo esc_attr( $message ); }
function esc_url_raw( string $url ): string { return $url; }
function wp_unslash( $value ) { return $value; }
function sanitize_text_field( string $value ): string { return trim( strip_tags( $value ) ); }
function sanitize_key( string $value ): string { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ); }
function wp_strip_all_tags( string $value ): string { return strip_tags( $value ); }
function wp_json_encode( $value, int $flags = 0 ): string { return json_encode( $value, $flags ); }
function is_wp_error( $value ): bool { return $value instanceof WP_Error; }
function is_admin(): bool { return false; }
function wp_doing_ajax(): bool { return false; }
function wp_doing_cron(): bool { return false; }
function is_feed(): bool { return false; }
function is_embed(): bool { return false; }
function is_singular(): bool { return false; }
function current_user_can( string $capability ): bool { return $GLOBALS['state']['authorized']; }
function get_current_blog_id(): int { return 1; }
function get_current_user_id(): int { return 1; }
function wp_cache_delete( $key, $group = '' ): void {}
function add_settings_error( $setting, $code, $message, $type = 'error' ): void {}
function wp_verify_nonce( string $nonce, string $action ): bool { return $GLOBALS['state']['nonce']; }
function nocache_headers(): void {}
function wp_die( string $message, string $title = '', array $args = [] ): void { throw new RuntimeException( $message ); }
function home_url( string $path = '' ): string { return 'https://example.test' . $path; }
function wp_parse_url( string $url ) { return parse_url( $url ); }
function remove_query_arg( string $key, string $url ): string {
	return preg_replace( '/([?&])' . preg_quote( $key, '/' ) . '=[^&]*(&|$)/', '$1', $url );
}
function get_option( string $key, $default = false ) { return $GLOBALS['state']['options'][ $key ] ?? $default; }
function update_option( string $key, $value, $autoload = null ): bool {
	if ( array_key_exists( $key, $GLOBALS['state']['options'] ) && $GLOBALS['state']['options'][ $key ] === $value ) {
		return false;
	}
	$GLOBALS['state']['options'][ $key ] = $value;
	++$GLOBALS['state']['writes'];
	if ( 'noobrocket_asset_rules' === $key && isset( $GLOBALS['admin'] ) ) {
		$GLOBALS['admin']->rules_changed();
	}
	return true;
}
function get_user_meta( int $user, string $key, bool $single = false ) { return $GLOBALS['state']['meta'][ $key ] ?? []; }
function update_user_meta( int $user, string $key, $value ): void {
	if ( in_array( $key, $GLOBALS['state']['meta_refuse'] ?? [], true ) ) { return; }
	$GLOBALS['state']['meta'][ $key ] = $value;
	++$GLOBALS['state']['meta_writes'];
}
function delete_user_meta( int $user, string $key ): void {
	if ( in_array( $key, $GLOBALS['state']['meta_delete_refuse'] ?? [], true ) ) { return; }
	unset( $GLOBALS['state']['meta'][ $key ] );
}
function noobrocket_clean_domain(): void { ++$GLOBALS['state']['purges']; }
function do_action( string $action ): void {}
function noobrocket_free_settings( array $settings ): array { return $settings; }
function wpm_apply_filters_typed( string $type, string $filter, array $value ): array { return $value; }
function wp_generate_uuid4(): string { return 'fixture-' . ++$GLOBALS['state']['uuids']; }
function wp_styles(): WP_Dependencies { return $GLOBALS['styles']; }
function wp_scripts(): WP_Dependencies { return $GLOBALS['scripts']; }
function wp_date( string $format, int $timestamp ): string { return gmdate( $format, $timestamp ); }
function admin_url( string $path ): string { return 'https://example.test/wp-admin/' . $path; }
function wp_nonce_field( string $action ): void { echo '<input name="_wpnonce" value="fixture">'; }
function get_transient( string $key ) { return false; }
function submit_button( string $text, string $type = 'primary' ): void { echo '<button type="submit">', esc_html( $text ), '</button>'; }

require NOOBROCKET_PATH . 'inc/classes/event-management/subscriber-interface.php';
require NOOBROCKET_PATH . 'inc/Engine/Abilities/Options/AllowedOptions.php';
require NOOBROCKET_PATH . 'inc/Engine/Optimization/Assets/Rules.php';
require NOOBROCKET_PATH . 'inc/Engine/Optimization/Assets/Frontend.php';
require NOOBROCKET_PATH . 'inc/Engine/Optimization/Assets/Snapshots.php';
require NOOBROCKET_PATH . 'inc/Engine/Optimization/Assets/Admin.php';

use NoobRocket\Engine\Optimization\Assets\Rules;
use NoobRocket\Engine\Optimization\Assets\Frontend;
use NoobRocket\Engine\Optimization\Assets\Snapshots;
use NoobRocket\Engine\Optimization\Assets\Admin;

class IsolatedAssetRules extends Rules {
	public $locks = 0;
	private $holding = false;
	public function has_edit_lock(): bool { return $this->holding; }
	public function acquire_edit_lock() { $this->holding = true; return true; }
	public function release_edit_lock(): void { $this->holding = false; }
	public function with_edit_lock( callable $operation ) {
		if ( $this->holding ) { return $operation(); }
		++$this->locks;
		$this->holding = true;
		try { return $operation(); } finally { $this->holding = false; }
	}
}
$checks = 0;
function asset_second_check( bool $condition, string $message ): void {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
	++$GLOBALS['checks'];
}

$rules = new IsolatedAssetRules();
$snapshots = new Snapshots( $rules );
$admin = new Admin( $rules, $snapshots );
$rule = [ 'type' => 'script', 'handle' => 'base', 'scope' => 'everywhere', 'value' => '', 'exceptions' => [] ];
$assets = new WP_Dependencies();
$assets->add( 'base', '/base.js' );
$assets->add( 'middle', '/middle.js', [ 'base' ] );
$assets->add( 'consumer', '/consumer.js', [ 'middle' ] );
$assets->add( 'second-consumer', '/second.js', [ 'base' ] );
$assets->add( 'unused', '/unused.js' );
$assets->queue = [ 'base', 'middle', 'consumer', 'second-consumer', 'unused' ];
$result = $rules->apply( $assets, [ 'base', 'middle', 'unused' ] );
asset_second_check( [ 'unused' ] === $result['removed'] && [ 'base', 'middle' ] === $result['protected'], 'dependency protection unchanged' );
$consumers = $result['retained_by']['base'];
sort( $consumers );
asset_second_check( [ 'middle', 'second-consumer' ] === $consumers && [ 'consumer' ] === $result['retained_by']['middle'], 'retaining consumers are distinct from outgoing dependencies' );
$assets->add( 'cycle-one', false, [ 'cycle-two' ] );
$assets->add( 'cycle-two', false, [ 'cycle-one' ] );
$assets->queue = [ 'cycle-one' ];
$result = $rules->apply( $assets, [ 'cycle-two' ] );
asset_second_check( [ 'cycle-two' ] === $result['protected'] && [ 'cycle-one' ] === $result['retained_by']['cycle-two'], 'consumer explanation terminates on cycles' );
$assets->queue = [ 'base' ];
$assets->done = [ 'base' ];
asset_second_check( [ 'base' ] === $rules->apply( $assets, [ 'base' ] )['protected'], 'already printed asset remains protected' );

// A new authenticated page inspection must replace a previous page, including zero assets.
$state['options'][ Rules::OPTION ] = [ $rule ];
$styles = new WP_Dependencies();
$scripts = new WP_Dependencies();
$scripts->add( 'base', '/base.js' );
$scripts->add( 'consumer', '/consumer.js', [ 'base' ] );
$scripts->queue = [ 'base', 'consumer' ];
$_GET[ Frontend::PREVIEW ] = 'fixture';
$_GET[ Frontend::REVISION ] = Rules::draft_revision( $state['meta'][ Frontend::user_key( 'draft' ) ] ?? [ 'base' => $rules->get(), 'rules' => $rules->get() ] );
$_SERVER['REQUEST_URI'] = '/populated';
$frontend = new Frontend( $rules );
$frontend->prepare_preview();
$frontend->apply_rules();
$frontend->save_inventory();
$inventory = $state['meta'][ Frontend::user_key( 'inventory' ) ];
$base = array_values( array_filter( $inventory['assets'], static function ( $asset ) { return 'base' === $asset['handle']; } ) )[0];
asset_second_check( 'required dependency' === $base['status'] && [ 'consumer' ] === $base['retained_by'], 'preview records protecting consumer beside original dependencies' );
$scripts = new WP_Dependencies();
$_SERVER['REQUEST_URI'] = '/empty';
$frontend = new Frontend( $rules );
$frontend->prepare_preview();
$frontend->apply_rules();
$frontend->save_inventory();
$inventory = $state['meta'][ Frontend::user_key( 'inventory' ) ];
asset_second_check( [] === $inventory['assets'] && 'https://example.test/empty' === $inventory['url'] && $inventory['time'] > 0, 'empty page records its own URL/time and clears stale assets' );
$before_meta = $state['meta_writes'];
unset( $_GET[ Frontend::PREVIEW ] );
( new Frontend( $rules ) )->save_inventory();
asset_second_check( $before_meta === $state['meta_writes'], 'normal request cannot store inspection metadata' );
$_GET[ Frontend::PREVIEW ] = 'fixture';
foreach ( [ 'authorized', 'nonce' ] as $gate ) {
	$state[ $gate ] = false;
	$frontend = new Frontend( $rules );
	$blocked = false;
	try { $frontend->prepare_preview(); } catch ( RuntimeException $error ) { $blocked = true; }
	$frontend->save_inventory();
	asset_second_check( $blocked && $before_meta === $state['meta_writes'], 'failed ' . $gate . ' gate cannot replace inventory' );
	$state[ $gate ] = true;
}

// Existing identical options invoke no changed-option hook; do not claim/purge a change.
$state['options'][ Rules::OPTION ] = [ $rule ];
$state['meta'][ Frontend::user_key( 'draft' ) ] = [ 'base' => [ $rule ], 'rules' => [ $rule ] ];
$before_writes = $state['writes'];
asset_second_check( false === $admin->publish( [ 'base' => [ $rule ], 'rules' => [ $rule ] ], true ), 'unchanged publication returns its own outcome' );
asset_second_check( $before_writes === $state['writes'] && 0 === $state['purges'] && ! isset( $state['meta'][ Frontend::user_key( 'draft' ) ] ), 'unchanged publication clears private draft without writing rules or purging cache' );
asset_second_check( true === $admin->publish( [ 'base' => [ $rule ], 'rules' => [] ], true ) && [] === $rules->get() && 1 === $state['purges'], 'intentional empty publication removes existing rules and triggers changed hook' );
asset_second_check( is_wp_error( $admin->publish( [ 'base' => [ $rule ], 'rules' => [] ], true ) ), 'stale unchanged draft still rejected' );
asset_second_check( is_wp_error( $admin->publish( [ 'base' => [], 'rules' => [ $rule ] ], false ) ), 'confirmation gate preserved' );
$state['authorized'] = false;
asset_second_check( is_wp_error( $admin->publish( [ 'base' => [], 'rules' => [ $rule ] ], true ) ), 'capability gate preserved' );
$state['authorized'] = true;
asset_second_check( 3 === $rules->locks, 'changed/unchanged/conflict decisions run within existing lock' );

// Use actual metadata shape, translated section titles and readable list/empty values.
$admin->remember_settings( [ 'advanced_cache' => [ 'sections' => [ 'exclude' => [ 'title' => 'URLs à ne jamais mettre en cache', 'fields' => [ 'cache_reject_uri' => [ 'label' => '', 'type' => 'textarea' ] ] ] ] ] ] );
$admin->remember_setting_field( [ 'id' => 'heartbeat_site_behavior', 'label' => '<strong>Heartbeat</strong>', 'choices' => [ 'disable' => 'Disabled' ] ] );
$label = new ReflectionMethod( $admin, 'setting_label' );
$value = new ReflectionMethod( $admin, 'setting_value' );
asset_second_check( 'URLs à ne jamais mettre en cache' === $label->invoke( $admin, 'cache_reject_uri' ), 'empty field label falls back to registered translated section title' );
asset_second_check( "/first\n/second" === $value->invoke( $admin, 'cache_reject_uri', [ '/first', '/second' ] ), 'simple list values do not expose JSON punctuation' );
asset_second_check( 'None' === $value->invoke( $admin, 'cache_reject_uri', [] ) && 'None' === $value->invoke( $admin, 'cache_reject_uri', '' ) && 'Default (not stored)' === $value->invoke( $admin, 'cache_reject_uri', null ), 'empty versus unstored values remain distinct' );
asset_second_check( 'Disabled' === $value->invoke( $admin, 'heartbeat_site_behavior', [ 'disable' ] ), 'list entries use registered translated choices' );

// Retention metadata mirrors the actual bounded native history path.
$history = [];
for ( $index = 0; $index < Snapshots::LIMIT; ++$index ) {
	$history[] = [ 'id' => 'snapshot-' . $index, 'time' => 1700000000 - $index, 'settings' => [ 'minify_css' => 0 ], 'rules' => [] ];
}
$state['options'][ Snapshots::OPTION ] = $history;
asset_second_check( $snapshots->save_may_evict( 'snapshot-4' ) && ! $snapshots->save_may_evict( 'snapshot-0' ) && ! $snapshots->save_may_evict( 'missing' ), 'Save first metadata warns only targets past next retention boundary' );
$state['options']['date_format'] = 'Y-m-d';
$state['options']['time_format'] = 'H:i';
$form = new ReflectionMethod( $admin, 'open_form' );
ob_start();
$form->invoke( $admin, 'restore', $history[4] );
$form_html = ob_get_clean();
asset_second_check( false !== strpos( $form_html, 'data-snapshot-save-evicts="1"' ) && false !== strpos( $form_html, 'data-noobrocket-operation-name="Restore 2023-11-14 22:13"' ), 'restore operation carries selected snapshot name and retention warning' );
$state['options']['noobrocket_settings']['minify_css'] = 0;
$old_settings = $state['options']['noobrocket_settings'];
$new_settings = array_merge( $old_settings, [ 'minify_css' => 1 ] );
$snapshots->before_settings( $new_settings, $old_settings );
asset_second_check( in_array( 'snapshot-4', array_column( $snapshots->all(), 'id' ), true ), 'pre-update validation does not evict history' );
$state['options']['noobrocket_settings'] = $new_settings;
$snapshots->settings_updated( $old_settings, $new_settings );
asset_second_check( Snapshots::LIMIT === count( $snapshots->all() ) && ! in_array( 'snapshot-4', array_column( $snapshots->all(), 'id' ), true ), 'metadata predicts native Save first eviction without extending history' );
$state['options'][ Snapshots::OPTION ] = array_slice( $history, 0, 4 );
asset_second_check( ! $snapshots->save_may_evict( 'snapshot-3' ), 'four retained snapshots leave room for changed save' );

$render_snapshot = $history[0];
$render_snapshot['settings'] = [ 'cache_reject_uri' => [ '/first', '/second' ] ];
$state['options'][ Snapshots::OPTION ] = [ $render_snapshot ];
$state['options']['noobrocket_settings'] = [ 'cache_reject_uri' => [] ];
ob_start();
$admin->render_history();
$history_html = ob_get_clean();
asset_second_check( 2 === substr_count( $history_html, 'class="noobrocket-history-value" tabindex="0" role="region" aria-label=' ), 'both rendered comparison columns use named keyboard regions' );
asset_second_check( false !== strpos( $history_html, 'URLs à ne jamais mettre en cache — Current saved value' ) && false !== strpos( $history_html, "/first\n/second" ) && false !== strpos( $history_html, '>None</pre>' ), 'history renders registered translated labels and readable before/after values' );
echo 'PASS: asset/history second audit (', $checks, " isolated assertions; no WordPress/database/network)\n";
