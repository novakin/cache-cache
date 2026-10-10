<?php
/**
 * Third-party protocol regression checks; all HTTP responses are local fixtures.
 * php tests/wordpress-external-contracts.php /absolute/path/to/disposable/wordpress
 */
$wordpress = isset( $argv[1] ) ? realpath( $argv[1] ) : false;
if ( ! $wordpress || ! is_file( $wordpress . '/wp-load.php' ) || ! is_file( $wordpress . '/.noobrocket-acceptance-site' ) ) {
	fwrite( STDERR, "Provide a disposable WordPress installation with the .noobrocket-acceptance-site marker.\n" );
	exit( 2 );
}
putenv( 'NOOBROCKET_TEST_PHASE=external-contracts' );
$_SERVER['HTTP_HOST']      = '127.0.0.1:8765';
$_SERVER['REQUEST_URI']    = '/';
// Bypass early homepage delivery so the contract fixtures always execute.
$_SERVER['REQUEST_METHOD'] = 'POST';
define( 'WP_ADMIN', true );
require $wordpress . '/wp-load.php';

function external_contract_check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( 'FAIL: ' . $message );
	}
	echo 'PASS: ', $message, PHP_EOL;
}

$requests = [];
$fixture_error = null;
add_filter( 'pre_http_request', function ( $pre, $args, $url ) use ( &$requests, &$fixture_error ) {
	if ( 'api.cloudflare.com' !== wp_parse_url( $url, PHP_URL_HOST ) ) {
		return new WP_Error( 'external_contract_http_blocked', 'HTTP outside the Cloudflare fixture is blocked.' );
	}
	$requests[] = [ 'url' => $url, 'method' => $args['method'], 'body' => $args['body'] ];
	if ( 'https://api.cloudflare.com/client/v4/zones/test-zone/settings/rocket_loader' === $url && 'PATCH' === $args['method'] ) {
		$result = [ 'id' => 'rocket_loader', 'value' => 'off' ];
	} elseif ( 'https://api.cloudflare.com/client/v4/zones/test-zone/settings' === $url && 'GET' === $args['method'] ) {
		$result = [
			[ 'id' => 'browser_cache_ttl', 'value' => 14400 ],
			[ 'id' => 'cache_level', 'value' => 'aggressive' ],
			[ 'id' => 'rocket_loader', 'value' => 'on' ],
			[ 'id' => 'minify', 'value' => [ 'js' => 'on', 'css' => 'off', 'html' => 'on' ] ],
		];
	} else {
		throw new RuntimeException( 'Unexpected HTTP request blocked: ' . $url );
	}
	if ( $fixture_error ) {
		return $fixture_error;
	}
	return [
		'headers'  => [],
		'body'     => wp_json_encode( [ 'success' => true, 'result' => $result ] ),
		'response' => [ 'code' => 200, 'message' => 'OK' ],
		'cookies'  => [],
	];
}, PHP_INT_MAX, 3 );

$options = new \NoobRocket\Admin\Options_Data( [ 'cloudflare_zone_id' => 'test-zone' ] );
$client = new \NoobRocket\Addon\Cloudflare\API\Client( new \NoobRocket\Addon\Cloudflare\Auth\APIKey( 'test@example.test', 'fixture-only' ) );
$cloudflare = new \NoobRocket\Addon\Cloudflare\Cloudflare( $options, new \NoobRocket\Addon\Cloudflare\API\Endpoints( $client ) );
external_contract_check( 'off' === $cloudflare->set_rocket_loader( 'off' ), 'Cloudflare Rocket Loader update succeeds with the original API setting name' );
external_contract_check( 1 === count( $requests ) && [ 'value' => 'off' ] === json_decode( $requests[0]['body'], true ), 'Cloudflare PATCH sends only the requested setting value' );

$settings = $cloudflare->get_settings();
external_contract_check( [ 'cache_level' => 'aggressive', 'minify' => 'off', 'rocket_loader' => 'on', 'browser_cache_ttl' => 14400 ] === $settings, 'Cloudflare settings parser preserves external IDs and values' );
external_contract_check( 'aggressive,off,on,14400' === implode( ',', array_filter( $settings ) ), 'Cloudflare previous settings retain Rocket Loader for later restoration' );

$fixture_error = new WP_Error( 'cloudflare_fixture_error', 'Fixture API unavailable.' );
external_contract_check( $fixture_error === $cloudflare->set_rocket_loader( 'off' ) && $fixture_error === $cloudflare->get_settings(), 'Cloudflare update and read errors remain visible to callers' );
external_contract_check( 4 === count( $requests ), 'all four Cloudflare HTTP requests were served by local fixtures' );

$geotargeting = new \NoobRocket\ThirdParty\Plugins\WPGeotargeting();
external_contract_check( [ 'existing_cookie', 'geot_rocket_country' ] === $geotargeting->add_geot_cookies( [ 'existing_cookie' ] ), 'GeotargetingWP uses its existing country cookie' );
add_filter( 'noobrocket_geotargetingwp_enabled_cookies', function () { return [ 'country', 'state' ]; } );
external_contract_check( [ 'geot_rocket_country', 'geot_rocket_state' ] === $geotargeting->add_geot_cookies( [ 'geot_rocket_country' ] ), 'GeotargetingWP additional cookies use the external prefix without duplicates' );
add_filter( 'deprecated_function_trigger_error', '__return_false' );
external_contract_check( [ 'geot_rocket_country', 'geot_rocket_state' ] === noobrocket_add_geot_cookies( [] ), 'deprecated GeotargetingWP helper preserves the same cookie contract' );
remove_filter( 'deprecated_function_trigger_error', '__return_false' );

$bundled_lists = json_decode( file_get_contents( NOOBROCKET_PATH . 'dynamic-lists.json' ), true );
$bundled_delay = json_decode( file_get_contents( NOOBROCKET_PATH . 'dynamic-lists-delayjs.json' ), true );
external_contract_check( in_array( 'berocket_aapf_time_to_fix_products_style', $bundled_lists['js_move_after_combine'], true ), 'BeRocket product-filter integration keeps its actual runtime identifier' );
external_contract_check( in_array( '.wprm-advanced-list-', $bundled_lists['rucss_inline_content_exclusions'], true ), 'WP Recipe Maker keeps its own CSS prefix' );
external_contract_check( in_array( 'wprp.zemanta.com', $bundled_lists['js_minify_external'], true ), 'external advertising domain is preserved literally' );
external_contract_check( 'royal-elementor-addons/wpr-addons.php' === $bundled_delay['plugins']['0b4e061a-b366-4d5a-a00e-bded4b107133']['condition'], 'Royal Elementor Addons keeps its installed plugin basename' );
