<?php
/** Native AJAX nonce/storage proof. Marked copied-plugin site and owned loopback listener only; serial execution. */
$wordpress = isset( $argv[1] ) ? realpath( $argv[1] ) : false;
$origin = rtrim( $argv[2] ?? 'http://127.0.0.1:8765', '/' );
$parts = parse_url( $origin );
if ( PHP_SAPI !== 'cli' || ! $wordpress || ! is_file( $wordpress . '/wp-load.php' ) || ! is_file( $wordpress . '/.noobrocket-acceptance-site' )
	|| ! is_array( $parts ) || ( $parts['scheme'] ?? '' ) !== 'http' || ( $parts['host'] ?? '' ) !== '127.0.0.1'
	|| empty( $parts['port'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['path'] ) || isset( $parts['query'] ) || isset( $parts['fragment'] ) ) {
	throw new RuntimeException( 'Provide a marked disposable WordPress root and an explicit loopback HTTP origin.' );
}
$installed = $wordpress . '/wp-content/plugins/noobrocket';
$source = dirname( __DIR__ ) . '/noobrocket';
if ( ! is_file( $installed . '/noobrocket.php' ) || is_link( $installed ) || realpath( $installed ) === realpath( $source ) ) {
	throw new RuntimeException( 'Native proof requires a separate copied plugin.' );
}
foreach ( [ 'inc/Engine/Common/PerformanceHints/AJAX/Subscriber.php', 'inc/Engine/Common/PerformanceHints/AJAX/Processor.php', 'assets/js/noobrocket-beacon.js', 'assets/js/noobrocket-beacon.min.js' ] as $file ) {
	if ( ! is_file( $installed . '/' . $file ) || hash_file( 'sha256', $installed . '/' . $file ) !== hash_file( 'sha256', $source . '/' . $file ) ) {
		throw new RuntimeException( 'Disposable plugin copy differs from the current beacon source: ' . $file );
	}
}
$suffix = bin2hex( random_bytes( 8 ) );
$probe = $wordpress . '/cache-cache-beacon-proof-' . $suffix . '.txt';
try {
	if ( false === file_put_contents( $probe, $suffix ) ) { throw new RuntimeException( 'Cannot create owned listener probe.' ); }
	$context = stream_context_create( [ 'http' => [ 'timeout' => 5, 'follow_location' => 0 ] ] );
	if ( file_get_contents( $origin . '/' . basename( $probe ), false, $context ) !== $suffix ) {
		throw new RuntimeException( 'Loopback listener does not serve the selected disposable installation.' );
	}
} finally {
	if ( is_file( $probe ) ) { unlink( $probe ); }
}
$_SERVER['HTTP_HOST'] = $parts['host'] . ':' . $parts['port'];
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'POST';
define( 'WP_ADMIN', true );
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
require $wordpress . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

$checks = 0;
function beacon_http_check( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
	++$GLOBALS['checks'];
}
function beacon_http_request( $action, array $body, $cookie ) {
	$response = wp_remote_post( $GLOBALS['origin'] . '/wp-admin/admin-ajax.php', [
		'timeout' => 15, 'redirection' => 0, 'headers' => [ 'Cookie' => $cookie ], 'body' => array_merge( [ 'action' => $action ], $body ),
	] );
	if ( is_wp_error( $response ) ) { throw new RuntimeException( 'Owned AJAX request failed: ' . $response->get_error_code() ); }
	return $response;
}
function beacon_http_payload( $response ) {
	$payload = json_decode( wp_remote_retrieve_body( $response ), true );
	beacon_http_check( 200 === wp_remote_retrieve_response_code( $response ) && is_array( $payload ) && true === ( $payload['success'] ?? false ) && is_array( $payload['data'] ?? null ), 'Native AJAX success envelope.' );
	return $payload['data'];
}
function beacon_http_refresh( $cookie ) {
	$response = beacon_http_request( 'noobrocket_beacon_nonce', [], $cookie );
	$payload = beacon_http_payload( $response );
	$cache_control = wp_remote_retrieve_header( $response, 'cache-control' );
	beacon_http_check( is_string( $cache_control ) && false !== stripos( $cache_control, 'no-cache' ) && false !== stripos( $cache_control, 'max-age=0' ), 'Fresh nonce response forbids caching.' );
	beacon_http_check( ! empty( $payload['nonce'] ) && false !== wp_verify_nonce( $payload['nonce'], 'noobrocket_beacon' ), 'Refresh nonce belongs to the current native visitor/session.' );
	return $payload['nonce'];
}
function beacon_http_row( $mobile ) {
	global $wpdb, $url;
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}nbr_above_the_fold WHERE url = %s AND is_mobile = %d", $url, $mobile ), ARRAY_A );
}
function beacon_http_clear( $mobile = null ) {
	global $wpdb, $url, $tables;
	foreach ( $tables as $table ) {
		$where = [ 'url' => $url ];
		if ( null !== $mobile ) { $where['is_mobile'] = $mobile; }
		if ( false === $wpdb->delete( $wpdb->prefix . $table, $where ) ) { throw new RuntimeException( 'Cannot remove owned beacon rows.' ); }
	}
}

$tables = [ 'nbr_above_the_fold', 'nbr_lazy_render_content', 'nbr_preload_fonts', 'nbr_preconnect_external_domains' ];
$url = $origin . '/cache-cache-beacon-' . $suffix;
$settings = get_option( 'noobrocket_settings' );
$original_user = get_current_user_id();
$original_cookies = $_COOKIE;
$owned_user = 0;
$container = apply_filters( 'noobrocket_container', null );
beacon_http_check( $container && $container->get( 'atf_factory' )->get_context()->is_allowed(), 'Native ATF collector is enabled.' );
foreach ( $tables as $table ) {
	$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}{$table} WHERE url = %s", $url ) );
	beacon_http_check( null !== $count && 0 === (int) $count, 'Native collector table exists and fixture URL owns no existing rows.' );
}
try {
	$owned_user = wp_insert_user( [ 'user_login' => 'beacon-proof-' . $suffix, 'user_pass' => wp_generate_password( 32, false ), 'role' => 'subscriber' ] );
	beacon_http_check( ! is_wp_error( $owned_user ), 'Owned authenticated visitor created.' );
	$expiry = time() + HOUR_IN_SECONDS;
	$token = WP_Session_Tokens::get_instance( $owned_user )->create( $expiry );
	$logged_in = wp_generate_auth_cookie( $owned_user, $expiry, 'logged_in', $token );
	$auth = wp_generate_auth_cookie( $owned_user, $expiry, 'auth', $token );
	foreach ( [ 'guest' => 0, 'authenticated' => 1 ] as $visitor => $mobile ) {
		wp_set_current_user( $mobile ? $owned_user : 0 );
		if ( $mobile ) { $_COOKIE[ LOGGED_IN_COOKIE ] = $logged_in; } else { unset( $_COOKIE[ LOGGED_IN_COOKIE ] ); }
		$cookie = $mobile ? AUTH_COOKIE . '=' . $auth . '; ' . LOGGED_IN_COOKIE . '=' . $logged_in : '';
		// Core accepts the current and previous tick; two ticks earlier is genuinely expired.
		$expired = substr( wp_hash( ( wp_nonce_tick( 'noobrocket_beacon' ) - 2 ) . '|noobrocket_beacon|' . get_current_user_id() . '|' . wp_get_session_token(), 'nonce' ), -12, 10 );
		beacon_http_check( false === wp_verify_nonce( $expired, 'noobrocket_beacon' ), $visitor . ': native nonce is expired.' );
		$body = [ 'url' => $url, 'is_mobile' => $mobile ? 'true' : 'false', 'status' => 'success', 'noobrocket_beacon_nonce' => $expired,
			'results' => wp_json_encode( [ 'lcp' => [ [ 'type' => 'img', 'src' => $origin . '/fixture-image.png', 'label' => 'lcp' ] ], 'lrc' => [], 'preload_fonts' => [], 'preconnect_external_domain' => [] ] ) ];
		foreach ( [ 'noobrocket_check_beacon', 'noobrocket_beacon' ] as $expired_action ) {
			beacon_http_clear( $mobile );
			$body['noobrocket_beacon_nonce'] = $expired;
			$response = beacon_http_request( $expired_action, $body, $cookie );
			beacon_http_check( 403 === wp_remote_retrieve_response_code( $response ) && '-1' === trim( wp_remote_retrieve_body( $response ) ) && null === beacon_http_row( $mobile ), $visitor . ': expired ' . $expired_action . ' rejects without a row.' );
			$body['noobrocket_beacon_nonce'] = beacon_http_refresh( $cookie );
			if ( 'noobrocket_check_beacon' === $expired_action ) {
				$checked = beacon_http_payload( beacon_http_request( $expired_action, $body, $cookie ) );
				beacon_http_check( false === ( $checked['lcp'] ?? null ), $visitor . ': refreshed check reads absent native row.' );
			}
			$saved = beacon_http_payload( beacon_http_request( 'noobrocket_beacon', $body, $cookie ) );
			$row = beacon_http_row( $mobile );
			beacon_http_check( is_array( $saved['lcp'] ?? null ) && 'completed' === $saved['lcp']['status'] && is_array( $row ) && 'completed' === $row['status'] && $url === $row['url'] && $mobile === (int) $row['is_mobile'], $visitor . ': retry reports and persists a completed native variant.' );
			beacon_http_check( $origin . '/fixture-image.png' === ( json_decode( $row['lcp'], true )['src'] ?? null ), $visitor . ': stored discovery survives collector validation.' );
			$repeat = beacon_http_payload( beacon_http_request( 'noobrocket_beacon', $body, $cookie ) );
			beacon_http_check( 'item already in the database' === ( $repeat['lcp'] ?? null ) && $row === beacon_http_row( $mobile ), $visitor . ': existing-row response performs no new write.' );
			$checked = beacon_http_payload( beacon_http_request( 'noobrocket_check_beacon', $body, $cookie ) );
			beacon_http_check( true === ( $checked['lcp'] ?? null ), $visitor . ': registered check sees persisted data.' );
		}
	}
} finally {
	try {
		beacon_http_clear();
	} finally {
		$_COOKIE = $original_cookies;
		wp_set_current_user( $original_user );
		if ( get_option( 'noobrocket_settings' ) !== $settings ) { update_option( 'noobrocket_settings', $settings ); }
		if ( is_int( $owned_user ) && $owned_user > 0 ) {
			WP_Session_Tokens::get_instance( $owned_user )->destroy_all();
			if ( ! wp_delete_user( $owned_user ) ) { throw new RuntimeException( 'Cannot remove owned fixture user.' ); }
		}
	}
}
echo "PASS: $checks native beacon AJAX/nonce/storage checks; guest/authenticated, owned rows/user cleaned.\n";
