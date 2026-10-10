<?php
/** PageSpeed request, credential and result contracts on a marked disposable WordPress site. */
$wordpress = isset( $argv[1] ) ? realpath( $argv[1] ) : false;
if ( ! $wordpress || ! is_file( $wordpress . '/wp-load.php' ) || ! is_file( $wordpress . '/.noobrocket-acceptance-site' ) ) {
	fwrite( STDERR, "Provide a disposable WordPress install with .noobrocket-acceptance-site.\n" );
	exit( 2 );
}
$_SERVER['HTTP_HOST']      = '127.0.0.1:8765';
$_SERVER['REQUEST_URI']    = '/';
$_SERVER['REQUEST_METHOD'] = 'POST';
define( 'WP_ADMIN', true );
define( 'DOING_AJAX', true );
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
require $wordpress . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';

use NoobRocket\Engine\Admin\PageSpeed;
use NoobRocket\Engine\Optimization\Assets\Snapshots;
use NoobRocket\Engine\Optimization\Assets\Rules;

class PageSpeedRequestTerminated extends RuntimeException {}
$checks = 0;
function pagespeed_check( $condition, string $message ): void {
	global $checks;
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
	++$checks;
}
function pagespeed_response( array $body, int $status = 200 ): array {
	return [ 'response' => [ 'code' => $status, 'message' => 'Fixture' ], 'headers' => [], 'body' => wp_json_encode( $body ) ];
}
function pagespeed_capture( callable $handler ): array {
	ob_start();
	try { $handler(); }
	catch ( PageSpeedRequestTerminated $exception ) {
		return [ 'termination' => $exception->getMessage(), 'output' => ob_get_clean() ];
	}
	ob_end_clean();
	throw new RuntimeException( 'PageSpeed route did not terminate.' );
}

$key           = 'AIzaSySyntheticFixtureKeyForNoobRocket12345';
$key_option    = 'noobrocket_pagespeed_api_key';
$result_option = 'noobrocket_pagespeed_results';
$lock_option   = 'noobrocket_pagespeed_lock';
$notice        = 'noobrocket_pagespeed_notice_1';
$names         = [ $key_option, $result_option, $lock_option, 'noobrocket_settings', Snapshots::OPTION, Rules::OPTION,
	'cron', '_transient_' . $notice, '_transient_timeout_' . $notice ];
$rows          = [];
foreach ( $names as $name ) {
	$rows[ $name ] = $wpdb->get_row( $wpdb->prepare( "SELECT option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name = %s", $name ), ARRAY_A );
}
$settings_before = get_option( 'noobrocket_settings' );
$history_before  = get_option( Snapshots::OPTION );
$rules_before    = get_option( Rules::OPTION );
$cron_before     = get_option( 'cron' );
$user_before     = get_current_user_id();
$sessions_before = get_user_meta( 1, 'session_tokens', true );
$request_before  = [ $_POST, $_REQUEST, $_SERVER['REQUEST_METHOD'] ];
$homepage        = 'https://www.noobunbox.net/';
$requests        = [];
$response        = null;
$http_callback   = null;
$deny_permission = false;
$refuse_write    = static function ( $value, $old_value ) { return $old_value; };
$same_result     = null;
$home_filter     = static function () use ( &$homepage ) { return $homepage; };
$redirect_hosts  = static function ( $hosts ) { $hosts[] = '127.0.0.1'; return $hosts; };
$permission      = static function ( $caps ) use ( &$deny_permission ) {
	if ( $deny_permission ) { $caps['noobrocket_manage_options'] = false; }
	return $caps;
};
$die = static function () { return static function () { throw new PageSpeedRequestTerminated( 'denied' ); }; };
$redirect = static function ( $url ) { throw new PageSpeedRequestTerminated( 'redirect:' . $url ); };
$http = static function ( $pre, $args, $url ) use ( &$requests, &$response, &$http_callback ) {
	$requests[] = [ 'url' => $url, 'args' => $args ];
	if ( $http_callback ) { $http_callback(); }
	return null === $response ? new WP_Error( 'fixture_unexpected_http', 'Unmocked HTTP is forbidden.' ) : $response;
};
add_filter( 'home_url', $home_filter );
add_filter( 'allowed_redirect_hosts', $redirect_hosts );
add_filter( 'user_has_cap', $permission );
add_filter( 'wp_die_handler', $die );
add_filter( 'wp_die_ajax_handler', $die );
add_filter( 'wp_redirect', $redirect );
add_filter( 'pre_http_request', $http, 999, 3 );

try {
	$container = apply_filters( 'noobrocket_container', null );
	$pagespeed = $container->get( 'pagespeed_subscriber' );
	pagespeed_check( $pagespeed instanceof PageSpeed, 'native container owns PageSpeed subscriber' );
	pagespeed_check( false !== has_action( 'admin_post_noobrocket_pagespeed_key' ), 'dedicated key POST route is registered' );
	pagespeed_check( false !== has_action( 'wp_ajax_noobrocket_pagespeed_run' ), 'authenticated run AJAX route is registered' );
	pagespeed_check( false === has_action( 'wp_ajax_nopriv_noobrocket_pagespeed_run' ), 'no anonymous analysis endpoint is registered' );
	$key_request = static function ( string $operation, $input = '', bool $nonce = true ) use ( $pagespeed ) {
		$_POST = [ 'operation' => $operation, 'api_key' => $input,
			'_noobrocket_pagespeed_nonce' => $nonce ? wp_create_nonce( 'noobrocket_pagespeed_key' ) : 'invalid' ];
		$_REQUEST = $_POST;
		return pagespeed_capture( [ $pagespeed, 'handle_key' ] );
	};
	$run_request = static function ( $strategy = 'mobile', bool $nonce = true ) use ( $pagespeed ) {
		$_POST = [ 'strategy' => $strategy, 'nonce' => $nonce ? wp_create_nonce( 'noobrocket_pagespeed_run' ) : 'invalid' ];
		$_REQUEST = $_POST;
		return pagespeed_capture( [ $pagespeed, 'run_ajax' ] );
	};

	delete_option( $key_option ); delete_option( $result_option ); delete_option( $lock_option );
	wp_set_current_user( 0 );
	pagespeed_check( 'denied' === $key_request( 'save_key', $key )['termination'] && false === get_option( $key_option ), 'anonymous users cannot save a key' );
	pagespeed_check( '' !== $run_request()['termination'] && [] === $requests, 'anonymous users cannot trigger external analysis' );
	ob_start(); $pagespeed->render(); $pagespeed->render_key_form(); $anonymous_html = ob_get_clean();
	pagespeed_check( '' === $anonymous_html, 'analysis controls and key form stay hidden from anonymous users' );
	wp_set_current_user( 1 );
	$deny_permission = true;
	pagespeed_check( 'denied' === $key_request( 'save_key', $key )['termination'] && false === get_option( $key_option ), 'NoobRocket capability is required to change key' );
	pagespeed_check( '' !== $run_request()['termination'] && [] === $requests, 'NoobRocket capability is required to analyze' );
	$deny_permission = false;
	pagespeed_check( 'denied' === $key_request( 'save_key', $key, false )['termination'] && false === get_option( $key_option ), 'invalid key nonce prevents storage' );
	pagespeed_check( '' !== $run_request( 'mobile', false )['termination'] && [] === $requests, 'invalid run nonce prevents HTTP requests' );
	$_SERVER['REQUEST_METHOD'] = 'GET';
	pagespeed_check( 'denied' === $key_request( 'save_key', $key )['termination'] && false === get_option( $key_option ), 'GET cannot change key' );
	pagespeed_check( '' !== $run_request()['termination'] && [] === $requests, 'GET cannot trigger analysis' );
	$_SERVER['REQUEST_METHOD'] = 'POST';
	foreach ( [ '', '<script>bad-key</script>', str_repeat( 'a', 201 ), [ $key ] ] as $invalid ) {
		$key_request( 'save_key', $invalid );
		pagespeed_check( false === get_option( $key_option ), 'malformed key does not reach storage' );
	}
	$key_request( 'unknown', $key );
	pagespeed_check( false === get_option( $key_option ), 'unknown key operation has no write side effect' );
	$saved = $key_request( 'save_key', $key );
	pagespeed_check( $key === get_option( $key_option ), 'valid key is saved independently' );
	pagespeed_check( str_contains( $saved['termination'], '#dashboard' ), 'successful key action returns to Overview (' . $saved['termination'] . ')' );
	$autoload = $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", $key_option ) );
	pagespeed_check( in_array( $autoload, [ 'no', 'off', 'auto-off' ], true ), 'API key is not autoloaded' );
	pagespeed_check( false !== get_transient( $notice ), 'key action uses feedback scoped to current administrator' );
	add_filter( 'pre_update_option_' . $key_option, $refuse_write, 999, 2 );
	$key_request( 'save_key', $key . '_replacement' );
	pagespeed_check( $key === get_option( $key_option ) && 'store_failed' === get_transient( $notice ), 'refused key replacement preserves credential and reports storage failure' );
	$key_request( 'save_key', $key );
	pagespeed_check( $key === get_option( $key_option ) && 'saved' === get_transient( $notice ), 'saving an unchanged key succeeds despite native update returning false' );
	remove_filter( 'pre_update_option_' . $key_option, $refuse_write, 999 );
	ob_start(); $pagespeed->render(); $panel = ob_get_clean();
	ob_start(); $pagespeed->render_key_form(); $form = ob_get_clean();
	pagespeed_check( ! str_contains( $panel . $form, $key ), 'saved key is absent from rendered HTML' );
	pagespeed_check( ! str_contains( $panel, '<form' ) && str_contains( $form, 'method="post"' ), 'key mutation uses an independent form outside native settings form' );
	pagespeed_check( str_contains( $panel . $form, 'https://developers.google.com/speed/docs/insights/v5/get-started' ) || str_contains( $panel . $form, 'https://console.cloud.google.com/apis/credentials' ), 'UI links to official key setup' );
	$export = noobrocket_export_options();
	pagespeed_check( ! str_contains( $export[1], $key ) && ! isset( get_option( 'noobrocket_settings' )['pagespeed_api_key'] ), 'independent credential is absent from settings and exports' );
	$key_request( 'save_key', 'invalid-key' );
	pagespeed_check( $key === get_option( $key_option ), 'malformed replacement preserves existing saved credential' );
	$key_request( 'remove_key' );
	pagespeed_check( false === get_option( $key_option ), 'explicit remove deletes saved key' );
	pagespeed_check( is_wp_error( $pagespeed->analyze( 'mobile' ) ) && [] === $requests, 'missing key fails before external request' );
	$key_request( 'save_key', $key );
	foreach ( [ 'tablet', '', 'MOBILE' ] as $invalid ) {
		pagespeed_check( is_wp_error( $pagespeed->analyze( $invalid ) ) && [] === $requests, 'invalid strategy fails before HTTP' );
	}
	$invalid_strategy = $run_request( 'tablet' );
	$invalid_json = json_decode( $invalid_strategy['output'], true );
	pagespeed_check( false === ( $invalid_json['success'] ?? null ) && [] === $requests, 'AJAX rejects an unsupported device before HTTP' );
	foreach ( [ 'http://127.0.0.1:8765/', 'https://localhost/', 'http://10.0.0.1/', 'http://192.168.1.1/', 'http://172.16.0.1/',
		'https://[::1]/', 'https://[fd00::1]/', 'https://[fe80::1]/', 'http://169.254.169.254/', 'https://fixture.test/', 'https://fixture.local/',
		'https://user:secret@www.noobunbox.net/', 'file:///etc/passwd' ] as $invalid_url ) {
		pagespeed_check( ! $pagespeed->is_public_url( $invalid_url ), 'local, credentialed or non-HTTP URL is rejected' );
		$homepage = $invalid_url;
		pagespeed_check( is_wp_error( $pagespeed->analyze( 'mobile' ) ) && [] === $requests, 'non-public homepage fails before HTTP' );
	}
	$homepage = 'https://www.noobunbox.net/';
	pagespeed_check( $pagespeed->is_public_url( $homepage ), 'normal public HTTPS homepage is accepted' );
	pagespeed_check( $pagespeed->is_public_url( 'https://[2001:4860:4860::8888]/' ), 'public IPv6 homepage is accepted without IPv4 resolution' );
	$valid_body = [ 'lighthouseResult' => [
		'categories' => [ 'performance' => [ 'score' => 0 ], 'accessibility' => [ 'score' => 0.964 ],
			'best-practices' => [ 'score' => 1 ], 'seo' => [ 'score' => null ] ],
		'audits' => [ 'first-contentful-paint' => [ 'numericValue' => 2100.2 ], 'largest-contentful-paint' => [ 'numericValue' => 3124 ],
			'total-blocking-time' => [ 'numericValue' => 0 ], 'cumulative-layout-shift' => [ 'numericValue' => 0.012 ],
			'speed-index' => [ 'numericValue' => 2999.1 ] ],
		'finalUrl' => 'https://raw-response.example/?secret=' . $key, 'runtimeError' => null,
	], 'raw-secret' => $key ];
	$response = pagespeed_response( $valid_body );
	$result = $pagespeed->analyze( 'mobile' );
	pagespeed_check( ! is_wp_error( $result ) && [ 'performance' => 0, 'accessibility' => 96, 'best-practices' => 100, 'seo' => null ] === $result['scores'], 'API category grades convert 0–1 to 0–100 without losing zero or unscored values' );
	pagespeed_check( 2100.2 === $result['metrics']['fcp'] && 3124 == $result['metrics']['lcp'] && 0 == $result['metrics']['tbt'] && 0.012 === $result['metrics']['cls'] && 2999.1 === $result['metrics']['speed_index'], 'numeric Lighthouse lab metrics remain in API units' );
	pagespeed_check( $homepage === $result['url'] && abs( time() - $result['checked_at'] ) < 5, 'result records canonical homepage and recent check time' );
	pagespeed_check( [ 'url', 'checked_at', 'scores', 'metrics' ] === array_keys( $result ) && ! str_contains( wp_json_encode( $result ), $key ), 'stored successful result has only sanitized grades and metrics' );
	pagespeed_check( $result === get_option( $result_option )['mobile'] && false === get_option( $lock_option ), 'mobile evidence is saved and lock is released' );
	$autoload = $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", $result_option ) );
	pagespeed_check( in_array( $autoload, [ 'no', 'off', 'auto-off' ], true ), 'saved lab results are not autoloaded into every visitor request' );
	$first = $requests[0];
	$parts = wp_parse_url( $first['url'] );
	pagespeed_check( 'https' === $parts['scheme'] && 'pagespeedonline.googleapis.com' === $parts['host'] && '/pagespeedonline/v5/runPagespeed' === $parts['path'], 'analysis uses only fixed official API endpoint' );
	parse_str( $parts['query'], $query );
	preg_match_all( '/(?:^|&)category=([^&]+)/', $parts['query'], $matches );
	$categories = array_map( static function ( $value ) { return str_replace( '_', '-', strtolower( rawurldecode( $value ) ) ); }, $matches[1] );
	sort( $categories );
	pagespeed_check( $homepage === $query['url'] && 'mobile' === $query['strategy'] && [ 'accessibility', 'best-practices', 'performance', 'seo' ] === $categories, 'request asks canonical URL, chosen device and all four categories' );
	$headers = array_change_key_case( $first['args']['headers'], CASE_LOWER );
	pagespeed_check( $key === $headers['x-goog-api-key'] && ! str_contains( $first['url'], $key ) && ! isset( $query['key'] ), 'key travels only in request header rather than query string' );
	pagespeed_check( 90 === $first['args']['timeout'] && 0 === $first['args']['redirection'], 'long-running API call has finite timeout and cannot redirect credentials' );
	$desktop = $pagespeed->analyze( 'desktop' );
	pagespeed_check( ! is_wp_error( $desktop ) && $result === get_option( $result_option )['mobile'] && $desktop === get_option( $result_option )['desktop'], 'desktop analysis preserves separate mobile evidence' );
	$baseline = get_option( $result_option );
	$changed_body = $valid_body;
	$changed_body['lighthouseResult']['categories']['performance']['score'] = 0.81;
	$response = pagespeed_response( $changed_body );
	add_filter( 'pre_update_option_' . $result_option, $refuse_write, 999, 2 );
	$not_saved = $pagespeed->analyze( 'mobile' );
	pagespeed_check( is_wp_error( $not_saved ) && 'storage' === $not_saved->get_error_code() && ! str_contains( $not_saved->get_error_message(), $key ), 'refused result write returns safe storage error instead of a false success' );
	pagespeed_check( $baseline === get_option( $result_option ) && false === get_option( $lock_option ), 'refused result write preserves both recorded devices and releases lock' );
	remove_filter( 'pre_update_option_' . $result_option, $refuse_write, 999 );
	$response = pagespeed_response( $valid_body );
	$unchanged = false;
	$same_result = static function ( $value, $old_value ) use ( &$unchanged ) { $unchanged = $value === $old_value; return $value; };
	add_filter( 'pre_update_option_' . $result_option, $same_result, 999, 2 );
	$http_callback = static function () use ( $result_option ) {
		$saved_results = get_option( $result_option );
		$saved_results['mobile']['checked_at'] = time();
		update_option( $result_option, $saved_results, false );
	};
	// A second-boundary rollover changes checked_at; retry only to prove the unchanged branch.
	for ( $attempt = 0; $attempt < 3 && ! $unchanged; ++$attempt ) { $same = $pagespeed->analyze( 'mobile' ); }
	pagespeed_check( $unchanged && ! is_wp_error( $same ) && $same === get_option( $result_option )['mobile'], 'unchanged stored result remains successful when native update returns false' );
	$http_callback = null;
	remove_filter( 'pre_update_option_' . $result_option, $same_result, 999 );
	$baseline = get_option( $result_option );
	foreach ( [ null, 'invalid', '0.9', -0.1, 1.1, true, [] ] as $score ) {
		$body = $valid_body;
		$body['lighthouseResult']['categories']['seo']['score'] = $score;
		$body['lighthouseResult']['audits']['first-contentful-paint']['numericValue'] = -1;
		$body['lighthouseResult']['audits']['cumulative-layout-shift']['numericValue'] = 'invalid';
		unset( $body['lighthouseResult']['audits']['speed-index'] );
		$response = pagespeed_response( $body );
		$parsed = $pagespeed->analyze( 'mobile' );
		pagespeed_check( ! is_wp_error( $parsed ) && null === $parsed['scores']['seo'] && null === $parsed['metrics']['fcp'] && null === $parsed['metrics']['cls'] && null === $parsed['metrics']['speed_index'], 'malformed or unavailable numeric fields stay null without invented scores' );
	}
	$baseline = get_option( $result_option );
	$failures = [
		'transport' => new WP_Error( 'fixture_http', 'Raw transport secret: ' . $key ),
		'quota' => pagespeed_response( [ 'error' => [ 'code' => 429, 'message' => 'Raw quota secret: ' . $key, 'status' => 'RESOURCE_EXHAUSTED' ] ], 429 ),
		'disabled' => pagespeed_response( [ 'error' => [ 'code' => 403, 'message' => 'Raw disabled secret: ' . $key, 'details' => [ [ 'reason' => 'SERVICE_DISABLED' ] ] ] ], 403 ),
		'restricted' => pagespeed_response( [ 'error' => [ 'code' => 403, 'message' => 'Raw restriction secret: ' . $key, 'details' => [ [ 'reason' => 'API_KEY_HTTP_REFERRER_BLOCKED' ] ] ] ], 403 ),
		'invalid key' => pagespeed_response( [ 'error' => [ 'code' => 400, 'message' => 'Raw invalid secret: ' . $key, 'details' => [ [ 'reason' => 'API_KEY_INVALID' ] ] ] ], 400 ),
		'empty response' => pagespeed_response( [] ),
		'no usable score' => pagespeed_response( [ 'lighthouseResult' => [ 'categories' => [ 'performance' => [ 'score' => null ], 'seo' => [ 'score' => 'invalid' ] ] ] ] ),
		'runtime failure' => pagespeed_response( [ 'lighthouseResult' => [ 'categories' => $valid_body['lighthouseResult']['categories'], 'runtimeError' => [ 'code' => 'ERRORED_DOCUMENT_REQUEST', 'message' => 'Raw runtime secret: ' . $key ] ] ] ),
		'invalid JSON' => [ 'response' => [ 'code' => 200 ], 'headers' => [], 'body' => 'not JSON secret:' . $key ],
	];
	foreach ( $failures as $name => $failure ) {
		$response = $failure;
		$failed = $pagespeed->analyze( 'mobile' );
		pagespeed_check( is_wp_error( $failed ) && ! str_contains( $failed->get_error_message(), $key ) && ! str_contains( $failed->get_error_message(), 'Raw ' ), $name . ' maps to safe actionable error rather than raw provider details' );
		pagespeed_check( $baseline === get_option( $result_option ) && false === get_option( $lock_option ), $name . ' preserves previous grades and releases lock' );
		$error_codes = [ 'transport' => 'transport', 'quota' => 'quota', 'disabled' => 'api_disabled', 'restricted' => 'key_restricted', 'invalid key' => 'invalid_key', 'runtime failure' => 'analysis_failed' ];
		if ( isset( $error_codes[ $name ] ) ) { pagespeed_check( $error_codes[ $name ] === $failed->get_error_code(), $name . ' retains its distinct recovery category' ); }
	}
	$response = pagespeed_response( $valid_body );
	$before_requests = count( $requests );
	$other_owner = time();
	update_option( $lock_option, $other_owner, false );
	$busy = $pagespeed->analyze( 'mobile' );
	pagespeed_check( is_wp_error( $busy ) && count( $requests ) === $before_requests && $other_owner === (int) get_option( $lock_option ), 'busy lock prevents duplicate request and preserves lock ownership' );
	update_option( $lock_option, time() - 121, false );
	$after_stale = $pagespeed->analyze( 'mobile' );
	pagespeed_check( ! is_wp_error( $after_stale ) && count( $requests ) === $before_requests + 1 && false === get_option( $lock_option ), 'expired lock can be recovered and is released after success' );
	$replacement_owner = time() + 1;
	$http_callback = static function () use ( $lock_option, $replacement_owner ) { update_option( $lock_option, $replacement_owner, false ); };
	$pagespeed->analyze( 'desktop' );
	pagespeed_check( $replacement_owner === (int) get_option( $lock_option ), 'completion never releases a replacement owner lock' );
	$http_callback = null; delete_option( $lock_option );
	$ajax = $run_request( 'desktop' );
	$json = json_decode( $ajax['output'], true );
	pagespeed_check( true === ( $json['success'] ?? null ) && isset( $json['data']['result']['scores'] ) && ! str_contains( $ajax['output'], $key ), 'AJAX returns sanitized grades rather than credentials or raw report' );
	$response = $failures['quota'];
	$ajax = $run_request();
	$json = json_decode( $ajax['output'], true );
	pagespeed_check( false === ( $json['success'] ?? null ) && ! empty( $json['data']['message'] ) && ! str_contains( $ajax['output'], $key ), 'AJAX surfaces safe failure message' );
	$retained = get_option( $result_option );
	$key_request( 'remove_key' );
	pagespeed_check( false === get_option( $key_option ) && $retained === get_option( $result_option ), 'removing key retains both previously recorded device grades' );
	pagespeed_check( get_option( 'noobrocket_settings' ) === $settings_before && get_option( Snapshots::OPTION ) === $history_before && get_option( Rules::OPTION ) === $rules_before, 'key and analysis actions preserve pending-independent settings, history and asset rules' );
	pagespeed_check( get_option( 'cron' ) === $cron_before && get_user_meta( 1, 'session_tokens', true ) === $sessions_before, 'manual analysis creates no cron or login session side effects' );
	echo 'PASS: PageSpeed security/storage/API/results (', $checks, " assertions; synthetic key; no external HTTP)\n";
} finally {
	remove_filter( 'home_url', $home_filter ); remove_filter( 'user_has_cap', $permission );
	remove_filter( 'allowed_redirect_hosts', $redirect_hosts );
	remove_filter( 'wp_die_handler', $die ); remove_filter( 'wp_die_ajax_handler', $die );
	remove_filter( 'wp_redirect', $redirect ); remove_filter( 'pre_http_request', $http, 999 );
	remove_filter( 'pre_update_option_' . $key_option, $refuse_write, 999 );
	remove_filter( 'pre_update_option_' . $result_option, $refuse_write, 999 );
	if ( $same_result ) { remove_filter( 'pre_update_option_' . $result_option, $same_result, 999 ); }
	// Restore exact option values and autoload flags without settings hooks or new history.
	foreach ( $rows as $name => $row ) {
		$wpdb->delete( $wpdb->options, [ 'option_name' => $name ] );
		if ( $row ) { $wpdb->insert( $wpdb->options, $row ); }
		wp_cache_delete( $name, 'options' );
	}
	wp_cache_delete( 'alloptions', 'options' ); wp_cache_delete( 'notoptions', 'options' );
	if ( get_user_meta( 1, 'session_tokens', true ) !== $sessions_before ) {
		if ( '' === $sessions_before ) { delete_user_meta( 1, 'session_tokens' ); }
		else { update_user_meta( 1, 'session_tokens', $sessions_before ); }
	}
	wp_set_current_user( $user_before );
	[ $_POST, $_REQUEST, $_SERVER['REQUEST_METHOD'] ] = $request_before;
}
