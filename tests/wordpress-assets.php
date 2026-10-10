<?php
/** Native asset rules, dependency safety and settings history on the marked disposable site. */
$wordpress = isset( $argv[1] ) ? realpath( $argv[1] ) : false;
if ( ! $wordpress || ! is_file( $wordpress . '/.noobrocket-acceptance-site' ) ) {
	fwrite( STDERR, "Provide a disposable WordPress installation with its acceptance marker.\n" );
	exit( 2 );
}
$_SERVER['HTTP_HOST']      = '127.0.0.1:8765';
$_SERVER['REQUEST_URI']    = '/';
$_SERVER['REQUEST_METHOD'] = 'POST';
define( 'WP_ADMIN', true );
require $wordpress . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
set_current_screen( 'settings_page_noobrocket' );
wp_set_current_user( 1 );

use NoobRocket\Engine\Optimization\Assets\Rules;
use NoobRocket\Engine\Optimization\Assets\Frontend;

$checks = 0;
function asset_check( $condition, string $message ): void {
	global $checks;
	if ( ! $condition ) {
		throw new RuntimeException( 'FAIL: ' . $message );
	}
	++$checks;
}

$container = apply_filters( 'noobrocket_container', null );
$rules     = $container->get( 'asset_rules' );
$admin     = $container->get( 'asset_admin_subscriber' );
$history   = $container->get( 'settings_snapshots' );
$options   = [];
foreach ( [ 'noobrocket_settings', Rules::OPTION, $history::OPTION ] as $name ) {
	$options[ $name ] = get_option( $name, null );
}
$draft_before = get_user_meta( 1, Frontend::user_key( 'draft' ), true );
$posts        = [];
$rule         = [ 'type' => 'style', 'handle' => 'fixture-unused', 'scope' => 'everywhere', 'value' => '', 'exceptions' => [] ];

try {
	asset_check( ! is_wp_error( $rules->validate( [ $rule ] ) ), 'valid rule accepted' );
	foreach ( [ [ 'scope' => 'invalid' ], [ 'scope' => 'post', 'value' => '' ], [ 'scope' => 'post_type', 'value' => 'missing-type' ], [ 'exceptions' => [ [ 5 ] ] ], [ 'handle' => '<script>' ], [ 'value' => [] ] ] as $bad ) {
		asset_check( is_wp_error( $rules->validate( [ array_merge( $rule, $bad ) ] ) ), 'invalid rule rejected without broadening scope' );
	}
	asset_check( is_wp_error( $rules->validate( array_fill( 0, 101, $rule ) ) ), 'rule count bounded' );
	$normalized = $rules->validate( [ array_merge( $rule, [ 'exceptions' => '12, 14 12' ] ) ] );
	asset_check( [ 12, 14 ] === $normalized[0]['exceptions'], 'exception IDs normalized and deduplicated' );

	$assets = new WP_Dependencies();
	$assets->add( 'base', '/base.css' );
	$assets->add( 'middle', '/middle.css', [ 'base' ] );
	$assets->add( 'consumer', '/consumer.css', [ 'middle' ] );
	$assets->add( 'unused', '/unused.css' );
	$assets->enqueue( [ 'base', 'middle', 'consumer', 'unused' ] );
	$result = $rules->apply( $assets, [ 'base', 'middle', 'unused' ] );
	asset_check( [ 'unused' ] === $result['removed'] && [ 'base', 'middle' ] === $result['protected'], 'retained roots protect recursive dependencies' );
	asset_check( [ 'base', 'middle', 'consumer' ] === array_values( $assets->queue ), 'unrelated enqueued assets retained' );
	$result = $rules->apply( $assets, [ 'base', 'middle', 'consumer' ] );
	asset_check( 3 === count( $result['removed'] ) && ! $assets->queue, 'selected complete dependency tree unloads together' );
	$assets->enqueue( 'base' );
	$assets->done = [ 'base' ];
	asset_check( [ 'base' ] === $rules->apply( $assets, [ 'base' ] )['protected'], 'already printed assets cannot be undone' );
	$assets->add( 'cycle-one', false, [ 'cycle-two' ] );
	$assets->add( 'cycle-two', false, [ 'cycle-one' ] );
	$assets->enqueue( 'cycle-one' );
	asset_check( [ 'cycle-two' ] === $rules->apply( $assets, [ 'cycle-two' ] )['protected'], 'dependency cycles terminate safely' );

	$posts[] = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Asset rules temporary fixture' ], true );
	asset_check( ! is_wp_error( $posts[0] ), 'temporary public page created' );
	$id = (int) $posts[0];
	$GLOBALS['wp_query'] = new WP_Query( [ 'page_id' => $id ] );
	asset_check( $rules->matches( array_merge( $rule, [ 'scope' => 'post', 'value' => (string) $id ] ) ), 'single-page rule matches its ID' );
	asset_check( ! $rules->matches( array_merge( $rule, [ 'scope' => 'post', 'value' => (string) ( $id + 9999 ) ] ) ), 'single-page rule does not match another ID' );
	asset_check( $rules->matches( array_merge( $rule, [ 'scope' => 'post_type', 'value' => 'page' ] ) ), 'post-type rule matches singular page' );
	asset_check( $rules->matches( array_merge( $rule, [ 'scope' => 'template', 'value' => 'default' ] ) ), 'default-template rule matches' );
	asset_check( ! $rules->matches( array_merge( $rule, [ 'exceptions' => [ $id ] ] ) ), 'exception keeps asset on matching page' );
	update_post_meta( $id, '_wp_page_template', 'templates/contact.php' );
	asset_check( $rules->matches( array_merge( $rule, [ 'scope' => 'template', 'value' => 'templates/contact.php' ] ) ), 'named-template rule matches exact path' );

	$invalid_urls = [ 'https://evil.example/', 'javascript:alert(1)', home_url( '/wp-admin/' ), home_url( '/wp-login.php' ), 'http://user:password@127.0.0.1:8765/' ];
	foreach ( $invalid_urls as $url ) {
		asset_check( is_wp_error( $admin->preview_url( $url ) ), 'foreign, credentialed or admin preview URL rejected' );
	}
	asset_check( false !== strpos( $admin->preview_url( get_permalink( $id ) ), Frontend::PREVIEW . '=' ), 'local frontend preview URL signed' );

	delete_option( $history::OPTION );
	delete_option( Rules::OPTION );
	$initial = array_merge( $options['noobrocket_settings'], [ 'manual_preload' => 0, 'minify_css' => 0, 'cloudflare_api_key' => 'fixture-current-key', 'sucury_waf_api_key' => 'fixture-current-sucuri' ] );
	update_option( 'noobrocket_settings', $initial );
	delete_option( $history::OPTION );
	$edited = array_merge( $initial, [ 'minify_css' => 1 ] );
	update_option( 'noobrocket_settings', $edited );
	$snapshots = $history->all();
	asset_check( 1 === count( $snapshots ) && 0 === $snapshots[0]['settings']['minify_css'], 'previous supported settings captured before save' );
	asset_check( ! isset( $snapshots[0]['settings']['cloudflare_api_key'], $snapshots[0]['settings']['sucury_waf_api_key'], $snapshots[0]['settings']['minify_js_key'], $snapshots[0]['settings']['version'] ), 'history excludes credentials, cache keys and versions' );
	$first_snapshot = $snapshots[0]['id'];
	$edited['cloudflare_api_key'] = 'fixture-latest-key';
	update_option( 'noobrocket_settings', $edited );
	asset_check( 1 === count( $history->all() ), 'credentials-only save does not create snapshot' );
	asset_check( true === $history->restore( $first_snapshot ), 'settings snapshot restores through native option path' );
	$restored = get_option( 'noobrocket_settings' );
	asset_check( 0 === $restored['minify_css'] && 'fixture-latest-key' === $restored['cloudflare_api_key'] && $initial['version'] === $restored['version'], 'restore reverts optimization and preserves latest credentials/version' );
	asset_check( 2 === count( $history->all() ), 'pre-restore state retained for undo' );

	$base  = $rules->get();
	$draft = [ 'base' => $base, 'rules' => [ $rule ] ];
	asset_check( is_wp_error( $admin->publish( $draft, false ) ) && $base === $rules->get(), 'publication requires explicit confirmation' );
	asset_check( true === $admin->publish( $draft, true ) && [ $rule ] === $rules->get(), 'confirmed publication writes complete rule list' );
	asset_check( is_wp_error( $admin->publish( $draft, true ) ), 'stale draft cannot overwrite changed published rules' );
	WP_Upgrader::create_lock( 'noobrocket_assets', 5 * MINUTE_IN_SECONDS );
	try {
		asset_check( is_wp_error( $admin->publish( [ 'base' => [ $rule ], 'rules' => [] ], true ) ), 'concurrent rule publication is blocked' );
	} finally {
		WP_Upgrader::release_lock( 'noobrocket_assets' );
	}
	wp_set_current_user( 0 );
	asset_check( is_wp_error( $admin->publish( [ 'base' => [ $rule ], 'rules' => [] ], true ) ), 'anonymous publication rejected' );
	asset_check( is_wp_error( $history->restore( $first_snapshot ) ), 'anonymous settings restore rejected' );
	wp_set_current_user( 1 );
	for ( $i = 0; $i < 6; ++$i ) {
		$settings = get_option( 'noobrocket_settings' );
		$settings['minify_css'] = $i % 2;
		update_option( 'noobrocket_settings', $settings );
	}
	asset_check( 5 === count( $history->all() ), 'settings history bounded to five previous states' );

	// Publication controls appear only when a real private draft differs from published rules.
	update_user_meta( 1, Frontend::user_key( 'draft' ), [ 'base' => $rules->get(), 'rules' => [] ] );
	ob_start();
	$admin->render();
	$html = ob_get_clean();
	asset_check( false !== strpos( $html, 'noobrocket-rule-handle' ) && false !== strpos( $html, 'name="confirm"' ), 'native Tools screen renders labeled draft and publication forms' );
	asset_check( false === strpos( $html, 'fixture-latest-key' ) && false === strpos( $html, 'fixture-current-sucuri' ), 'admin history does not expose credentials' );
} finally {
	wp_set_current_user( 1 );
	foreach ( $posts as $id ) {
		if ( ! is_wp_error( $id ) ) {
			wp_delete_post( (int) $id, true );
		}
	}
	foreach ( $options as $name => $value ) {
		if ( null === $value ) {
			delete_option( $name );
		} else {
			update_option( $name, $value, false );
		}
	}
	if ( '' === $draft_before ) {
		delete_user_meta( 1, Frontend::user_key( 'draft' ) );
	} else {
		update_user_meta( 1, Frontend::user_key( 'draft' ), $draft_before );
	}
}
echo 'PASS: asset rules and settings history (', $checks, " assertions; disposable state restored)\n";
