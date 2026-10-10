<?php
/** Read-only Asset Manager/history rendering and form normalization on the marked local site. */
$wordpress = isset( $argv[1] ) ? realpath( $argv[1] ) : false;
if ( ! $wordpress || ! is_file( $wordpress . '/.noobrocket-acceptance-site' ) ) {
	fwrite( STDERR, "Provide the marked disposable WordPress installation.\n" );
	exit( 2 );
}
$_SERVER['HTTP_HOST'] = '127.0.0.1:8765';
$_SERVER['REQUEST_URI'] = '/';
define( 'WP_ADMIN', true );
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
require $wordpress . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
set_current_screen( 'settings_page_noobrocket' );
wp_set_current_user( 1 );
if ( 'http://127.0.0.1:8765' !== home_url() || ! current_user_can( 'noobrocket_manage_options' ) ) {
	exit( 2 );
}

use NoobRocket\Engine\Optimization\Assets\Rules;
use NoobRocket\Engine\Optimization\Assets\Frontend;

$checks = 0;
function asset_ux_check( bool $condition, string $message ): void {
	global $checks;
	if ( ! $condition ) {
		throw new RuntimeException( 'FAIL: ' . $message );
	}
	++$checks;
}

global $wpdb;
$read_saved_state = static function () use ( $wpdb ): array {
	return [
		$wpdb->get_results( "SELECT option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name IN ('noobrocket_settings', 'noobrocket_asset_rules', 'noobrocket_settings_snapshots') ORDER BY option_name", ARRAY_A ),
		$wpdb->get_results( "SELECT user_id, meta_key, meta_value FROM {$wpdb->usermeta} WHERE meta_key LIKE 'noobrocket_asset_%' ORDER BY user_id, meta_key", ARRAY_A ),
	];
};
$before = $read_saved_state();
$container = apply_filters( 'noobrocket_container', null );
$admin = $container->get( 'asset_admin_subscriber' );
$history = $container->get( 'settings_snapshots' );
$rule = [ 'type' => 'style', 'handle' => 'fixture-current', 'scope' => 'everywhere', 'value' => '', 'exceptions' => [] ];
$target_rule = array_merge( $rule, [ 'handle' => 'fixture-previous', 'scope' => 'post', 'value' => '12', 'exceptions' => [ 14 ] ] );
$snapshot = [ 'id' => 'fixture-history', 'time' => time() - 60, 'settings' => [ 'minify_css' => 0, 'local_css_mode' => 'off', 'cloudflare_api_key' => 'fixture-history-secret' ], 'rules' => [ $target_rule ] ];
$settings = [ 'minify_css' => 1, 'local_css_mode' => 'critical', 'cloudflare_api_key' => 'fixture-current-secret' ];
$settings_filter = static function () use ( $settings ) { return $settings; };
$fixture_published = [ $rule ];
$rules_filter = static function () use ( &$fixture_published ) { return $fixture_published; };
$history_filter = static function () use ( $snapshot ) { return [ $snapshot ]; };
$notice_filter = static function () { return []; };
add_filter( 'pre_option_noobrocket_settings', $settings_filter );
add_filter( 'pre_option_noobrocket_asset_rules', $rules_filter );
add_filter( 'pre_option_noobrocket_settings_snapshots', $history_filter );
add_filter( 'pre_transient_noobrocket_asset_notice_1', $notice_filter );
$assets = [];
for ( $index = 0; $index < 27; ++$index ) {
	$assets[] = [ 'type' => $index % 2 ? 'script' : 'style', 'handle' => 'fixture-asset-' . $index, 'src' => '/fixture-asset-' . $index . '.css', 'deps' => [], 'status' => 0 === $index ? 'required dependency' : 'loaded' ];
}
$fixture_draft = [ 'rules' => [ $rule ], 'base' => [ $rule ] ];
$fixture_entry = [];
$meta_filter = static function ( $value, $user, $key ) use ( $assets, &$fixture_draft, &$fixture_entry ) {
	if ( 1 !== (int) $user ) { return $value; }
	if ( Frontend::user_key( 'inventory' ) === $key ) { return [ [ 'url' => home_url( '/' ), 'time' => time(), 'assets' => $assets ] ]; }
	if ( Frontend::user_key( 'draft' ) === $key ) { return [ $fixture_draft ]; }
	if ( Frontend::user_key( 'entry' ) === $key ) { return [ $fixture_entry ]; }
	return $value;
};
add_filter( 'get_user_metadata', $meta_filter, 10, 3 );

try {
	$changes = $history->changes( $snapshot );
	asset_ux_check( [ 'minify_css', 'local_css_mode' ] === array_keys( $changes['settings'] ), 'review shows supported changed settings only' );
	asset_ux_check( 1 === $changes['settings']['minify_css']['current'] && 0 === $changes['settings']['minify_css']['snapshot'], 'review retains current and target values' );
	asset_ux_check( [ $rule ] === array_values( $changes['removed_rules'] ) && [ $target_rule ] === array_values( $changes['added_rules'] ), 'review distinguishes current rules removed and snapshot rules restored' );
	$identical = $history->changes( [ 'settings' => $settings, 'rules' => [ $rule ] ] );
	asset_ux_check( ! $identical['settings'] && ! $identical['removed_rules'] && ! $identical['added_rules'], 'same saved state has no false changes' );
	$normalize = new ReflectionMethod( $admin, 'rule_from_form' );
	foreach ( [ 'post' => [ 'rule_page', '12' ], 'post_type' => [ 'rule_post_type', 'page' ], 'template' => [ 'rule_template', 'default' ] ] as $scope => $choice ) {
		$selected = $normalize->invoke( $admin, array_merge( $rule, [ 'scope' => $scope, 'value' => 'ignored' ] ), [ $choice[0] => $choice[1] ] );
		asset_ux_check( $choice[1] === $selected['value'], 'recognizable scope choice uses canonical rule value: ' . $scope );
	}
	$expert = $normalize->invoke( $admin, array_merge( $rule, [ 'scope' => 'post', 'value' => '77' ] ), [ 'rule_expert' => '1', 'rule_page' => '12' ] );
	asset_ux_check( '77' === $expert['value'], 'expert ID takes precedence over the chooser' );
	$legacy = $normalize->invoke( $admin, array_merge( $rule, [ 'scope' => 'post', 'value' => '88' ] ), [] );
	asset_ux_check( '88' === $legacy['value'], 'existing canonical rule submission remains compatible' );
	$invalid = $normalize->invoke( $admin, array_merge( $rule, [ 'scope' => [ 'bad' ] ] ), [] );
	asset_ux_check( is_wp_error( $container->get( 'asset_rules' )->validate( [ $invalid ] ) ), 'invalid scope remains rejected without broadening or a type error' );
	$admin->remember_setting_field( [ 'id' => 'minify_css', 'label' => 'Minify CSS files', 'sanitize_callback' => 'sanitize_checkbox' ] );
	$admin->remember_setting_field( [ 'id' => 'local_css_mode', 'label' => 'Local CSS generation', 'choices' => [ 'off' => 'Off', 'critical' => 'Local critical CSS' ] ] );
	ob_start();
	$admin->render();
	$html = ob_get_clean();
	asset_ux_check( 27 === preg_match_all( '/<a\b[^>]*\bclass="[^"]*\bnoobrocket-asset-create\b[^"]*"[^>]*>/', $html ), 'all inspected assets retain a create-draft action' );
	asset_ux_check( 1 === preg_match( '/<details\b([^>]*)>\s*<summary>Choose an inspected asset \(27\)/s', $html, $inventory_disclosure ) && 0 === preg_match( '/\bopen(?:\s|=|$)/', $inventory_disclosure[1] ), 'populated inventory starts collapsed and reports its asset count' );
	asset_ux_check( 14 === substr_count( $html, 'data-asset-type="style"' ) && 13 === substr_count( $html, 'data-asset-type="script"' ) && 1 === substr_count( $html, '>Required dependency</td>' ), 'inventory preserves every CSS/JavaScript action and dependency result' );
	asset_ux_check( 1 === preg_match( '/<form\b(?=[^>]*\bid="noobrocket-rule-editor")(?=[^>]*\bdata-noobrocket-unfinished)[^>]*>/', $html ) && 1 === preg_match( '/id=[\'"]noobrocket-rule-page[\'"]/', $html ), 'editor has native page chooser and unfinished-input boundary' );
	preg_match_all( '/<form\b[^>]*>(.*?)<\/form>/s', $html, $rendered_forms );
	$preview_forms = array_values( array_filter( $rendered_forms[1], static function ( $form ) { return false !== strpos( $form, 'name="command" value="preview"' ); } ) );
	asset_ux_check( 1 === count( $preview_forms ) && false !== strpos( $preview_forms[0], 'name="url" type="url" required' ), 'unchanged draft retains required inspection without a redundant publication preview' );
	asset_ux_check( 1 === substr_count( $html, 'id="noobrocket-preview-url"' ) && false === strpos( $html, 'id="noobrocket-publish-preview-url"' ), 'unchanged draft renders one uniquely identified preview input' );
	asset_ux_check( false === strpos( $html, 'name="command" value="publish"' ) && false !== strpos( $html, 'No unpublished rule changes. Inspect a page and edit your draft before publishing.' ), 'unchanged draft has no live publication form and explains the next step' );
	asset_ux_check( false === strpos( $html, 'name="command" value="discard"' ) && false === strpos( $html, 'noobrocket-asset-change-list' ), 'unchanged saved draft has neither a destructive discard action nor false publication changes' );
	asset_ux_check( false === strpos( $html, 'id="history"' ), 'history is a separate destination' );
	$fixture_draft['rules'] = [ $target_rule ];
	ob_start();
	$admin->render();
	$changed_draft = ob_get_clean();
	preg_match_all( '/<form\b[^>]*>(.*?)<\/form>/s', $changed_draft, $changed_forms );
	$changed_previews = array_values( array_filter( $changed_forms[1], static function ( $form ) { return false !== strpos( $form, 'name="command" value="preview"' ); } ) );
	asset_ux_check( 2 === count( $changed_previews ) && false !== strpos( $changed_previews[0], 'name="url" type="url" required' ) && false !== strpos( $changed_previews[1], 'name="url" type="url" required' ), 'changed draft inspection and publication each own a native required preview URL form' );
	asset_ux_check( 1 === substr_count( $changed_draft, 'id="noobrocket-preview-url"' ) && 1 === substr_count( $changed_draft, 'id="noobrocket-publish-preview-url"' ), 'changed draft inspection and publication preview inputs have unique IDs' );
	preg_match_all( '/<ul class="noobrocket-asset-change-list">(.*?)<\/ul>/s', $changed_draft, $publication_lists );
	asset_ux_check( 2 === count( $publication_lists[1] ) && false !== strpos( $publication_lists[1][0], 'fixture-previous' ) && false !== strpos( $publication_lists[1][1], 'fixture-current' ), 'publication review identifies the draft rule added and published rule removed' );
	asset_ux_check( false !== strpos( $changed_draft, 'name="command" value="discard"' ) && false !== strpos( $changed_draft, 'data-noobrocket-confirm-discard=' ) && false !== strpos( $changed_draft, 'name="confirm" value="1" required' ), 'changed draft exposes confirmed discard and retains required publication acknowledgement' );
	$fixture_draft['rules'] = [];
	ob_start();
	$admin->render();
	$empty_draft = ob_get_clean();
	preg_match_all( '/<ul class="noobrocket-asset-change-list">(.*?)<\/ul>/s', $empty_draft, $empty_lists );
	asset_ux_check( 1 === count( $empty_lists[1] ) && false !== strpos( $empty_lists[1][0], 'fixture-current' ) && false !== strpos( $empty_draft, 'name="command" value="discard"' ), 'intentionally empty draft reviews published-rule removal and remains discardable' );
	$fixture_draft['rules'] = [ $rule, $rule ];
	ob_start();
	$admin->render();
	$duplicate_added = ob_get_clean();
	preg_match_all( '/<ul class="noobrocket-asset-change-list">(.*?)<\/ul>/s', $duplicate_added, $duplicate_add_lists );
	asset_ux_check( 1 === count( $duplicate_add_lists[1] ) && 1 === substr_count( $duplicate_add_lists[1][0], '<li>' ) && false !== strpos( $duplicate_added, 'Rules to add' ) && false === strpos( $duplicate_added, 'Rules to remove' ) && false !== strpos( $duplicate_added, '1 rule added; 0 rules removed.' ), 'adding a duplicate counts exactly one added occurrence without a false removal' );
	$fixture_published = [ $rule, $rule ];
	$fixture_draft = [ 'rules' => [ $rule ], 'base' => $fixture_published ];
	ob_start();
	$admin->render();
	$duplicate_removed = ob_get_clean();
	preg_match_all( '/<ul class="noobrocket-asset-change-list">(.*?)<\/ul>/s', $duplicate_removed, $duplicate_remove_lists );
	asset_ux_check( 1 === count( $duplicate_remove_lists[1] ) && 1 === substr_count( $duplicate_remove_lists[1][0], '<li>' ) && false !== strpos( $duplicate_removed, 'Rules to remove' ) && false === strpos( $duplicate_removed, 'Rules to add' ) && false !== strpos( $duplicate_removed, '0 rules added; 1 rule removed.' ), 'removing a duplicate counts exactly one removed occurrence without a false addition' );
	$fixture_published = [ $rule, $target_rule ];
	$fixture_draft = [ 'rules' => [ $target_rule, $rule ], 'base' => $fixture_published ];
	ob_start();
	$admin->render();
	$reordered_draft = ob_get_clean();
	asset_ux_check( false === strpos( $reordered_draft, 'noobrocket-asset-change-list' ) && false !== strpos( $reordered_draft, 'Rule order changed.' ), 'reordering the same rules reports an order change without inventing membership deltas' );
	$fixture_published = [ $rule ];
	$fixture_draft['rules'] = [ $rule ];
	$fixture_draft['base'] = [];
	ob_start();
	$admin->render();
	$stale_draft = ob_get_clean();
	asset_ux_check( false !== strpos( $stale_draft, 'data-noobrocket-confirm-discard=' ) && false === strpos( $stale_draft, 'noobrocket-asset-change-list' ), 'stale base is discardable without inventing rule membership changes' );
	preg_match_all( '/<form\b[^>]*>(.*?)<\/form>/s', $stale_draft, $stale_forms );
	$stale_publish = array_values( array_filter( $stale_forms[1], static function ( $form ) { return false !== strpos( $form, 'name="command" value="publish"' ); } ) );
	asset_ux_check( 1 === count( $stale_publish ) && 1 === preg_match( '/<button\b(?=[^>]*\btype="submit")(?=[^>]*\bdisabled(?:\s|=|>))[^>]*>/', $stale_publish[0] ) && false !== strpos( $stale_publish[0], 'name="confirm" value="1" required' ), 'stale draft disables native publication while retaining its explicit acknowledgement' );
	asset_ux_check( false !== strpos( $stale_draft, 'Published rules changed while you edited. Discard your draft and start from the current rules.' ) && false === strpos( $stale_draft, 'No unpublished rule changes.' ), 'stale draft explains recovery and suppresses misleading unchanged status' );
	$fixture_draft['base'] = [ $rule ];
	$fixture_entry = [ 'handle' => 'fixture-rejected' ];
	ob_start();
	$admin->render();
	$rejected_entry = ob_get_clean();
	asset_ux_check( false !== strpos( $rejected_entry, 'value="fixture-rejected"' ) && false !== strpos( $rejected_entry, 'data-noobrocket-confirm-discard=' ), 'rejected private entry remains visible and can be explicitly discarded' );
	$fixture_entry = [ 'handle' => '', 'scope' => 'post', 'value' => 'abc', 'expert' => true ];
	ob_start();
	$admin->render();
	$rejected_target = ob_get_clean();
	asset_ux_check( false !== strpos( $rejected_target, 'value="abc"' ) && false !== strpos( $rejected_target, 'data-noobrocket-confirm-discard=' ), 'rejected target remains discardable even when the registered handle was empty' );
	$fixture_entry = [];
	$_GET = [ 'asset_type' => 'script', 'asset_handle' => 'fixture-prefilled', 'asset_page' => '12' ];
	ob_start();
	$admin->render();
	$prefilled = ob_get_clean();
	asset_ux_check( false !== strpos( $prefilled, 'value="fixture-prefilled"' ) && false !== strpos( $prefilled, 'value="script" selected=' ), 'read-only inventory link prefills asset type and handle' );
	$_GET = [];
	ob_start();
	$admin->render_history();
	$history_html = ob_get_clean();
	asset_ux_check( false !== strpos( $history_html, 'id="history"' ) && false !== strpos( $history_html, 'Current saved value' ) && false !== strpos( $history_html, 'Value after restore' ), 'history shows restoration values in its own route' );
	asset_ux_check( false !== strpos( $history_html, 'Minify CSS files' ) && false !== strpos( $history_html, 'Enabled' ) && false !== strpos( $history_html, 'Disabled' ) && false !== strpos( $history_html, 'Local critical CSS' ), 'history uses settings labels and human-readable choices' );
	asset_ux_check( false !== strpos( $history_html, 'fixture-current' ) && false !== strpos( $history_html, 'fixture-previous' ) && false !== strpos( $history_html, 'name="confirm" value="1" required' ), 'rule changes and required restore review remain explicit' );
	asset_ux_check( false === strpos( $history_html, 'fixture-history-secret' ) && false === strpos( $history_html, 'fixture-current-secret' ), 'history excludes both old and current credentials' );
} finally {
	remove_filter( 'pre_option_noobrocket_settings', $settings_filter );
	remove_filter( 'pre_option_noobrocket_asset_rules', $rules_filter );
	remove_filter( 'pre_option_noobrocket_settings_snapshots', $history_filter );
	remove_filter( 'pre_transient_noobrocket_asset_notice_1', $notice_filter );
	remove_filter( 'get_user_metadata', $meta_filter, 10 );
	$_GET = [];
	asset_ux_check( $before === $read_saved_state(), 'saved settings, rules, history and all private asset metadata remain exactly unchanged' );
}
echo 'PASS: asset/history UX (', $checks, " read-only assertions; saved state unchanged)\n";
