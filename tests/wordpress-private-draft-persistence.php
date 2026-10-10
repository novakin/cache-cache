<?php
/** Private metadata refusal and exact history differences; no WordPress/database/network. */
define( 'MINUTE_IN_SECONDS', 60 );
ob_start();
require __DIR__ . '/wordpress-assets-second-pass.php';
ob_end_clean();
require NOOBROCKET_PATH . 'inc/Engine/Admin/Settings/Settings.php';

use NoobRocket\Engine\Optimization\Assets\Rules;
use NoobRocket\Engine\Optimization\Assets\Frontend;
use NoobRocket\Engine\Optimization\Assets\Snapshots;

class PrivateDraftRedirect extends RuntimeException {
	public $url;
	public function __construct( string $url ) { $this->url = $url; parent::__construct( $url ); }
}
function check_admin_referer( string $action ): void {}
function metadata_exists( string $type, int $id, string $key ): bool { return array_key_exists( $key, $GLOBALS['state']['meta'] ); }
function set_transient( string $key, $value, int $ttl ): bool { $GLOBALS['state']['notices'][ $key ] = $value; return true; }
function add_query_arg( array $args, string $url ): string { return $url . '?' . http_build_query( $args ); }
function wp_safe_redirect( string $url ): void { throw new PrivateDraftRedirect( $url ); }
function private_draft_check( bool $condition, string $message ): void {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
	++$GLOBALS['private_checks'];
}
function private_draft_request( string $command, array $fields = [] ): array {
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$draft = $GLOBALS['admin']->draft();
	$revision = 'discard' === $command ? ( new ReflectionMethod( $GLOBALS['admin'], 'discard_revision' ) )->invoke( $GLOBALS['admin'], $draft ) : Rules::draft_revision( $draft );
	$_POST = array_merge( [ 'command' => $command, 'draft_revision' => $revision ], $fields );
	try {
		$GLOBALS['admin']->handle_request();
	} catch ( PrivateDraftRedirect $redirect ) {
		parse_str( parse_url( $redirect->url, PHP_URL_QUERY ), $result );
		private_draft_check( ! $GLOBALS['rules']->has_edit_lock(), 'Every redirect releases the request-owned edit lock.' );
		return $result;
	}
	throw new RuntimeException( 'Expected the native redirect.' );
}
function private_draft_seed( array $draft, array $entry ): void {
	$GLOBALS['state']['meta'] = [ Frontend::user_key( 'draft' ) => $draft, Frontend::user_key( 'entry' ) => $entry ];
	$GLOBALS['state']['meta_refuse'] = [];
	$GLOBALS['state']['meta_delete_refuse'] = [];
	$GLOBALS['state']['notices'] = [];
}

$private_checks = 0;
$draft_key = Frontend::user_key( 'draft' );
$entry_key = Frontend::user_key( 'entry' );
$base_rule = [ 'type' => 'script', 'handle' => 'first', 'scope' => 'everywhere', 'value' => '', 'exceptions' => [] ];
$second_rule = array_merge( $base_rule, [ 'handle' => 'second' ] );
$draft = [ 'base' => [], 'rules' => [ $base_rule ] ];
$entry = [ 'handle' => 'recover-me' ];
$state['options'][ Rules::OPTION ] = [];
$before_public = $state['options'];

private_draft_seed( $draft, $entry );
$state['meta_refuse'] = [ $draft_key ];
$result = private_draft_request( 'add', [ 'rule' => $second_rule ] );
private_draft_check( '0' === $result['asset_updated'] && $draft === $admin->draft() && $entry === get_user_meta( 1, $entry_key, true ), 'A refused draft add reports failure and preserves both draft and recoverable entry.' );
private_draft_check( false !== strpos( $state['notices']['noobrocket_asset_notice_1']['message'], 'could not be saved' ), 'Refused draft save has concrete private feedback.' );

private_draft_seed( $draft, $entry );
$result = private_draft_request( 'add', [ 'rule' => $second_rule ] );
private_draft_check( '1' === $result['asset_updated'] && [ $base_rule, $second_rule ] === $admin->draft()['rules'] && ! metadata_exists( 'user', 1, $entry_key ), 'A verified add clears its prior rejected entry only after persistence.' );

private_draft_seed( $draft, $entry );
$state['meta_delete_refuse'] = [ $entry_key ];
$result = private_draft_request( 'add', [ 'rule' => $second_rule ] );
private_draft_check( '0' === $result['asset_updated'] && [ $base_rule, $second_rule ] === $admin->draft()['rules'] && $entry === get_user_meta( 1, $entry_key, true ), 'A committed add with refused entry cleanup retains the entry and reports the partial outcome.' );

private_draft_seed( $draft, $entry );
$state['meta_refuse'] = [ $draft_key ];
$result = private_draft_request( 'remove', [ 'index' => 0 ] );
private_draft_check( '0' === $result['asset_updated'] && $draft === $admin->draft(), 'A refused remove cannot report success or alter the saved draft.' );
private_draft_seed( $draft, $entry );
$result = private_draft_request( 'remove', [ 'index' => 0 ] );
private_draft_check( '1' === $result['asset_updated'] && [] === $admin->draft()['rules'] && $entry === get_user_meta( 1, $entry_key, true ), 'A verified remove changes only draft rules.' );

private_draft_seed( $draft, $entry );
$state['meta_delete_refuse'] = [ $draft_key ];
$result = private_draft_request( 'discard' );
private_draft_check( '0' === $result['asset_updated'] && $draft === $admin->draft() && $entry === get_user_meta( 1, $entry_key, true ), 'A refused draft discard keeps its recovery entry.' );
private_draft_seed( $draft, $entry );
$state['meta_delete_refuse'] = [ $entry_key ];
$result = private_draft_request( 'discard' );
private_draft_check( '0' === $result['asset_updated'] && ! metadata_exists( 'user', 1, $draft_key ) && $entry === get_user_meta( 1, $entry_key, true ), 'Partial discard reports its retained rejected input.' );
private_draft_seed( $draft, $entry );
$result = private_draft_request( 'discard' );
private_draft_check( '1' === $result['asset_updated'] && ! metadata_exists( 'user', 1, $draft_key ) && ! metadata_exists( 'user', 1, $entry_key ), 'Successful discard verifies removal of both private records.' );
$result = private_draft_request( 'discard' );
private_draft_check( '1' === $result['asset_updated'], 'Discarding already absent private records is a successful no-op.' );

private_draft_seed( $draft, $entry );
$discard_revision = ( new ReflectionMethod( $admin, 'discard_revision' ) )->invoke( $admin, $draft );
$newer_entry = [ 'handle' => 'newer-rejected-work' ];
$state['meta'][ $entry_key ] = $newer_entry;
$result = private_draft_request( 'discard', [ 'draft_revision' => $discard_revision ] );
private_draft_check( '0' === $result['asset_updated'] && $draft === $admin->draft() && $newer_entry === get_user_meta( 1, $entry_key, true ), 'An older discard review preserves rejected work changed in another tab even when rules stayed identical.' );

private_draft_seed( $draft, $entry );
$state['meta_refuse'] = [ $entry_key ];
$invalid = array_merge( $second_rule, [ 'scope' => 'post', 'value' => 'bad' ] );
$result = private_draft_request( 'add', [ 'rule' => $invalid ] );
private_draft_check( '0' === $result['asset_updated'] && $entry === get_user_meta( 1, $entry_key, true ) && false !== strpos( $state['notices']['noobrocket_asset_notice_1']['message'], 'could not be kept' ), 'Refused rejected-input persistence reports failure while preserving prior recoverable work.' );
private_draft_seed( $draft, [ 'type' => 'script', 'handle' => 'second', 'scope' => 'post', 'value' => 'bad', 'exceptions' => '', 'expert' => false ] );
$state['meta_refuse'] = [ $entry_key ];
$result = private_draft_request( 'add', [ 'rule' => $invalid ] );
private_draft_check( '0' === $result['asset_updated'] && false === strpos( $state['notices']['noobrocket_asset_notice_1']['message'], 'could not be kept' ), 'An unchanged rejected entry is retained successfully even when its update is a native no-op.' );
private_draft_check( $before_public === $state['options'], 'All private editing outcomes preserve public rules, settings and history.' );

$state['options']['noobrocket_settings'] = [ 'minify_css' => 0 ];
$snapshot = [ 'id' => 'history-rule-differences', 'time' => 1700000000, 'settings' => $state['options']['noobrocket_settings'], 'rules' => [ $base_rule, $second_rule ] ];
$state['options'][ Rules::OPTION ] = [ $second_rule, $base_rule ];
$changes = $snapshots->changes( $snapshot );
private_draft_check( $changes['rules_changed'] && ! $changes['added_rules'] && ! $changes['removed_rules'], 'History distinguishes a rule order change from membership deltas.' );
$state['options'][ Snapshots::OPTION ] = [ $snapshot ];
ob_start();
$admin->render_history();
$history_html = ob_get_clean();
private_draft_check( false !== strpos( $history_html, 'Rule order changed.' ) && false !== strpos( $history_html, 'name="command" value="restore"' ) && false === strpos( $history_html, 'Matches saved state' ), 'Order-only history offers a reviewed restore without claiming state matches.' );
$state['options'][ Rules::OPTION ] = [ $base_rule, $base_rule, $second_rule ];
$changes = $snapshots->changes( $snapshot );
private_draft_check( [ $base_rule ] === $changes['removed_rules'] && [] === $changes['added_rules'], 'History removes exactly one excess duplicate occurrence.' );
$state['options'][ Rules::OPTION ] = [ $base_rule ];
$snapshot['rules'] = [ $base_rule, $base_rule ];
$changes = $snapshots->changes( $snapshot );
private_draft_check( [] === $changes['removed_rules'] && [ $base_rule ] === $changes['added_rules'], 'History restores exactly one missing duplicate occurrence.' );
$snapshot['rules'] = [ $base_rule ];
$changes = $snapshots->changes( $snapshot );
private_draft_check( ! $changes['rules_changed'] && ! $changes['added_rules'] && ! $changes['removed_rules'], 'Identical saved rules still have no restore difference.' );

$admin->remember_setting_field( [ 'id' => 'cdn_cnames', 'type' => 'cnames', 'label' => 'CDN hostnames' ] );
$label = new ReflectionMethod( $admin, 'setting_label' );
$value = new ReflectionMethod( $admin, 'setting_value' );
private_draft_check( 'Files to serve' === $label->invoke( $admin, 'cdn_zone' ) && "Images\nCSS & JavaScript" === $value->invoke( $admin, 'cdn_zone', [ 'images', 'css_and_js' ] ), 'CDN positional scope history uses the existing translated field label and choices.' );
echo "PASS: $private_checks private metadata/history regressions; no WordPress/database/network.\n";
