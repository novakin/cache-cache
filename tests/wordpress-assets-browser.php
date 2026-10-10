<?php
/** Prepare/restore a signed browser fixture; only the marked disposable local site is allowed. */
$wordpress = isset( $argv[1] ) ? realpath( $argv[1] ) : false;
$phase     = $argv[2] ?? '';
$statefile = dirname( __DIR__ ) . '/.artifacts/assets-browser-state.json';
if ( ! $wordpress || ! is_file( $wordpress . '/.noobrocket-acceptance-site' ) || ! in_array( $phase, [ 'setup', 'cleanup' ], true ) ) {
	exit( 2 );
}
$_SERVER['HTTP_HOST']      = '127.0.0.1:8765';
$_SERVER['REQUEST_URI']    = '/';
$_SERVER['REQUEST_METHOD'] = 'POST';
define( 'WP_ADMIN', true );
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
require $wordpress . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
wp_set_current_user( 1 );

use NoobRocket\Engine\Optimization\Assets\Rules;
use NoobRocket\Engine\Optimization\Assets\Frontend;

if ( 'http://127.0.0.1:8765' !== home_url() ) {
	throw new RuntimeException( 'Browser fixture requires the disposable 127.0.0.1:8765 site.' );
}
$fixture = WPMU_PLUGIN_DIR . '/noobrocket-asset-browser-fixture.php';

if ( 'cleanup' === $phase ) {
	$state = is_file( $statefile ) ? json_decode( file_get_contents( $statefile ), true ) : null;
	if ( ! is_array( $state ) ) {
		echo "No asset browser fixture to restore.\n";
		exit;
	}
	if ( is_file( $fixture ) ) {
		unlink( $fixture );
	}
	if ( ! empty( $state['post'] ) ) {
		wp_delete_post( (int) $state['post'], true );
	}
	foreach ( $state['options'] as $name => $value ) {
		if ( null === $value ) {
			delete_option( $name );
		} else {
			update_option( $name, $value, false );
		}
	}
	foreach ( $state['meta'] as $name => $value ) {
		if ( '' === $value ) {
			delete_user_meta( 1, $name );
		} else {
			update_user_meta( 1, $name, $value );
		}
	}
	WP_Session_Tokens::get_instance( 1 )->destroy( $state['token'] );
	unlink( $statefile );
	echo "PASS: asset browser fixture restored.\n";
	exit;
}

if ( is_file( $statefile ) || is_file( $fixture ) ) {
	throw new RuntimeException( 'Existing asset browser fixture must be restored first.' );
}
$state = [ 'options' => [], 'meta' => [], 'post' => 0, 'token' => '' ];
foreach ( [ 'noobrocket_settings', Rules::OPTION, 'noobrocket_settings_snapshots' ] as $name ) {
	$state['options'][ $name ] = get_option( $name, null );
}
foreach ( [ Frontend::user_key( 'draft' ), Frontend::user_key( 'inventory' ) ] as $name ) {
	$state['meta'][ $name ] = get_user_meta( 1, $name, true );
}
$state['token'] = WP_Session_Tokens::get_instance( 1 )->create( time() + HOUR_IN_SECONDS );
file_put_contents( $statefile, wp_json_encode( $state ) );

$settings = array_merge( $state['options']['noobrocket_settings'], [
	'manual_preload' => 0,
	'minify_css' => 0,
	'minify_js' => 0,
	'defer_all_js' => 0,
	'delay_js' => 0,
	'local_css_mode' => 'off',
	'cache_logged_user' => 1,
] );
update_option( 'noobrocket_settings', $settings );
update_option( Rules::OPTION, [], false );
$post = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Private Asset Preview Fixture', 'post_content' => '<p>Asset preview test page.</p>' ], true );
if ( is_wp_error( $post ) ) {
	throw new RuntimeException( $post->get_error_message() );
}
$state['post'] = (int) $post;
file_put_contents( $statefile, wp_json_encode( $state ) );

wp_mkdir_p( WPMU_PLUGIN_DIR );
$mu = '<?php' . "\n" . '$asset_fixture_id = ' . (int) $post . ';' . "\n";
$mu .= <<<'PHP'
add_action( 'wp_enqueue_scripts', function () use ( $asset_fixture_id ) {
	if ( ! is_page( $asset_fixture_id ) ) { return; }
	wp_enqueue_style( 'nbr-fixture-base-style', includes_url( 'css/dashicons.min.css' ) );
	wp_enqueue_style( 'nbr-fixture-kept-style', includes_url( 'css/dist/block-library/style.min.css' ), [ 'nbr-fixture-base-style' ] );
	wp_enqueue_style( 'nbr-fixture-unused-style', includes_url( 'css/buttons.min.css' ) );
	wp_enqueue_script( 'nbr-fixture-base-script', includes_url( 'js/jquery/jquery.min.js' ), [], false, true );
	wp_enqueue_script( 'nbr-fixture-kept-script', includes_url( 'js/jquery/jquery-migrate.min.js' ), [ 'nbr-fixture-base-script' ], false, true );
	wp_enqueue_script( 'nbr-fixture-unused-script', includes_url( 'js/wp-embed.min.js' ), [], false, true );
}, 20 );
PHP;
file_put_contents( $fixture, $mu );

$draft = [];
foreach ( [ [ 'style', 'nbr-fixture-unused-style' ], [ 'style', 'nbr-fixture-base-style' ], [ 'script', 'nbr-fixture-unused-script' ], [ 'script', 'nbr-fixture-base-script' ] ] as $asset ) {
	$draft[] = [ 'type' => $asset[0], 'handle' => $asset[1], 'scope' => 'post', 'value' => (string) $post, 'exceptions' => [] ];
}
update_user_meta( 1, Frontend::user_key( 'draft' ), [ 'base' => [], 'rules' => $draft ] );
$expiration   = time() + HOUR_IN_SECONDS;
$loggedin     = wp_generate_auth_cookie( 1, $expiration, 'logged_in', $state['token'] );
$auth         = wp_generate_auth_cookie( 1, $expiration, 'auth', $state['token'] );
$_COOKIE[ LOGGED_IN_COOKIE ] = $loggedin;
$state['url'] = get_permalink( $post );
$state['preview'] = add_query_arg( Frontend::PREVIEW, wp_create_nonce( Frontend::PREVIEW ), $state['url'] );
$state['cookies'] = [
	[ 'name' => LOGGED_IN_COOKIE, 'value' => $loggedin, 'domain' => '127.0.0.1', 'path' => '/', 'httpOnly' => true, 'sameSite' => 'Lax' ],
	[ 'name' => AUTH_COOKIE, 'value' => $auth, 'domain' => '127.0.0.1', 'path' => '/wp-admin', 'httpOnly' => true, 'sameSite' => 'Lax' ],
];
file_put_contents( $statefile, wp_json_encode( $state ) );
echo "PASS: private asset browser fixture prepared; temporary session stored in local artifact.\n";
