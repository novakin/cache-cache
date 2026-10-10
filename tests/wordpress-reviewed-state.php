<?php
/** Review conflicts and post-commit history with the isolated native asset fixture. */
ob_start();
require __DIR__ . '/wordpress-assets-second-pass.php';
ob_end_clean();

use NoobRocket\Engine\Optimization\Assets\Frontend;
use NoobRocket\Engine\Optimization\Assets\Rules;
use NoobRocket\Engine\Optimization\Assets\Snapshots;

$review_checks = 0;
function reviewed_check( bool $condition, string $message ): void {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
	++$GLOBALS['review_checks'];
}
$rule_a = [ 'type' => 'script', 'handle' => 'reviewed', 'scope' => 'everywhere', 'value' => '', 'exceptions' => [] ];
$rule_b = array_merge( $rule_a, [ 'handle' => 'replacement' ] );
$state['options'][ Rules::OPTION ] = [];
$state['meta'][ Frontend::user_key( 'draft' ) ] = [ 'base' => [], 'rules' => [ $rule_a ] ];
$reviewed = $admin->draft();
$revision = Rules::draft_revision( $reviewed );
$state['meta'][ Frontend::user_key( 'draft' ) ] = [ 'base' => [], 'rules' => [ $rule_b ] ];
$before_writes = $state['writes'];
$result = $admin->publish( $admin->draft(), true, $revision );
reviewed_check( is_wp_error( $result ) && 'asset_draft_conflict' === $result->get_error_code(), 'A replaced private draft requires a new publication review.' );
reviewed_check( $before_writes === $state['writes'] && [] === $rules->get() && [ $rule_b ] === $admin->draft()['rules'], 'A review conflict preserves current private work and public state.' );
$draft = $admin->draft();
reviewed_check( true === $admin->publish( $draft, true, Rules::draft_revision( $draft ) ) && [ $rule_b ] === $rules->get(), 'A newly reviewed private draft can publish.' );

$state['options'][ Rules::OPTION ] = [];
$snapshot = [ 'id' => 'review-target', 'time' => 1700000000, 'settings' => [ 'minify_css' => 1, 'minify_js' => 0 ], 'rules' => [] ];
$state['options'][ Snapshots::OPTION ] = [ $snapshot ];
$state['options']['noobrocket_settings'] = [ 'minify_css' => 0, 'minify_js' => 0, 'cloudflare_api_key' => 'fixture-current-key' ];
$token = $snapshots->review_token( $snapshot );
$state['options']['noobrocket_settings']['minify_js'] = 1;
$before = $state['options'];
$result = $snapshots->restore( $snapshot['id'], $token );
reviewed_check( is_wp_error( $result ) && 'snapshot_conflict' === $result->get_error_code() && $before === $state['options'], 'Intervening saved changes block the old restore review without a write.' );
$token = $snapshots->review_token( $snapshot );
$state['options'][ Snapshots::OPTION ][0]['settings']['minify_css'] = 0;
$before = $state['options'];
reviewed_check( is_wp_error( $snapshots->restore( $snapshot['id'], $token ) ) && $before === $state['options'], 'A replaced retained target also requires a new comparison.' );
$state['options'][ Snapshots::OPTION ] = [ $snapshot ];
$token = $snapshots->review_token( $snapshot );
reviewed_check( true === $snapshots->restore( $snapshot['id'], $token ), 'Current reviewed restoration succeeds.' );
reviewed_check( 'fixture-current-key' === $state['options']['noobrocket_settings']['cloudflare_api_key'] && 1 === $snapshots->all()[0]['settings']['minify_js'], 'Credentials and a recovery copy of the intervening state remain.' );

$state['options'][ Snapshots::OPTION ] = [];
$old = $state['options']['noobrocket_settings'];
$new = array_merge( $old, [ 'minify_js' => 1 ] );
reviewed_check( $new === $snapshots->before_settings( $new, $old ) && [] === $snapshots->all(), 'Pre-update validation acquires ownership without recording history.' );
$snapshots->release_pending_lock();
reviewed_check( [] === $snapshots->all() && ! $rules->has_edit_lock(), 'A refused write releases ownership on shutdown and leaves history intact.' );
$snapshots->before_settings( $new, $old );
$state['options']['noobrocket_settings'] = $new;
$snapshots->settings_updated( $old, $new );
reviewed_check( 1 === count( $snapshots->all() ) && 0 === $snapshots->all()[0]['settings']['minify_js'] && ! $rules->has_edit_lock(), 'Committed settings record the actual old state and release the lock.' );
$before = $snapshots->all();
$snapshots->before_settings( $new, $new );
$snapshots->settings_updated( $new, $new );
reviewed_check( $before === $snapshots->all(), 'No-op saves do not consume history.' );

$state['meta'][ Frontend::user_key( 'draft' ) ] = [ 'base' => [], 'rules' => [ $rule_a ] ];
$_GET = [ Frontend::PREVIEW => 'fixture', Frontend::REVISION => Rules::draft_revision( $state['meta'][ Frontend::user_key( 'draft' ) ] ) ];
$frontend = new Frontend( $rules );
$frontend->prepare_preview();
$state['meta'][ Frontend::user_key( 'draft' ) ] = [ 'base' => [], 'rules' => [ $rule_b ] ];
$scripts = new WP_Dependencies();
$scripts->add( 'reviewed', '/reviewed.js' );
$scripts->add( 'replacement', '/replacement.js' );
$scripts->queue = [ 'reviewed', 'replacement' ];
$styles = new WP_Dependencies();
$frontend->apply_rules();
reviewed_check( [ 'replacement' ] === $scripts->queue, 'A preview request uses its captured reviewed rules through all enqueue phases.' );
$frontend->save_inventory();
$inventory = $state['meta'][ Frontend::user_key( 'inventory' ) ];
reviewed_check( $_GET[ Frontend::REVISION ] === $inventory['draft_revision'], 'Inventory identifies the private version actually tested.' );
$blocked = false;
try { ( new Frontend( $rules ) )->prepare_preview(); } catch ( RuntimeException $error ) { $blocked = true; }
reviewed_check( $blocked, 'An old preview link cannot silently test a replacement draft.' );
echo "PASS: $review_checks reviewed-state regressions; no WordPress/database/network.\n";
