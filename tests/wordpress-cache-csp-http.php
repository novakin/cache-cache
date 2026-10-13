<?php
/** Native CSP/page-cache proof; marked copied-plugin site and owned loopback listener, serial execution. */
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
foreach ( [ 'inc/classes/Buffer/class-tests.php', 'inc/classes/Buffer/class-cache.php', 'inc/Engine/Optimization/Buffer/Optimization.php' ] as $file ) {
	if ( ! is_file( $installed . '/' . $file ) || hash_file( 'sha256', $installed . '/' . $file ) !== hash_file( 'sha256', $source . '/' . $file ) ) {
		throw new RuntimeException( 'Disposable plugin copy differs from the current cache owner: ' . $file );
	}
}
$suffix = bin2hex( random_bytes( 8 ) );
$probe = $wordpress . '/cache-cache-csp-proof-' . $suffix . '.txt';
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
if ( untrailingslashit( home_url( '/' ) ) !== $origin || ! get_option( 'permalink_structure' ) || ! defined( 'NOOBROCKET_CACHE_PATH' ) ) {
	throw new RuntimeException( 'Native proof requires this exact loopback site, pretty permalinks and an active copied plugin.' );
}
$cache_root = realpath( _noobrocket_get_noobrocket_cache_path() );
if ( ! $cache_root || is_link( $cache_root ) || 0 !== strpos( $cache_root . '/', $wordpress . '/' ) ) {
	throw new RuntimeException( 'Page-cache root must resolve inside this disposable installation.' );
}
$checks = 0;
$check = static function ( $condition, $message ) use ( &$checks ) {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
	++$checks;
};
$request = static function ( $url ) {
	$response = wp_remote_get( $url, [ 'timeout' => 15, 'redirection' => 0, 'headers' => [ 'Accept-Encoding' => 'identity' ] ] );
	if ( is_wp_error( $response ) ) { throw new RuntimeException( 'Owned page request failed: ' . $response->get_error_code() ); }
	return $response;
};
$mu_path = $wordpress . '/wp-content/mu-plugins/cache-cache-csp-proof-' . $suffix . '.php';
$post_ids = [];
$urls = [];
$mu_source = '';
try {
	$policies = [];
	foreach ( [ 'enforcing' => 'Content-Security-Policy', 'report-only' => 'Content-Security-Policy-Report-Only', 'public' => '' ] as $case => $header ) {
		$id = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'nbr-csp-' . $case . '-' . $suffix, 'post_title' => 'Owned CSP cache proof ' . $case, 'post_content' => str_repeat( 'Disposable public cache content. ', 30 ) ], true );
		$check( ! is_wp_error( $id ) && $id > 0, 'Owned public page created: ' . $case );
		$post_ids[] = $id;
		$url = get_permalink( $id );
		$check( null === wp_parse_url( $url, PHP_URL_QUERY ) && 0 === strpos( $url, $origin . '/' ), 'Native page has an owned pretty URL.' );
		$urls[ $case ] = $url;
		$policies[ $id ] = $header;
	}
	$mu_source = '<?php $policies = ' . var_export( $policies, true ) . '; add_action("template_redirect", static function () use ($policies) { $id = get_queried_object_id(); if (!array_key_exists($id, $policies)) { return; } header("X-Cache-Cache-Csp-Proof: ' . $suffix . '-" . bin2hex(random_bytes(8))); if ($policies[$id]) { header($policies[$id] . ": default-src \'self\'; object-src \'none\'"); } }, 0);';
	wp_mkdir_p( dirname( $mu_path ) );
	$check( ! file_exists( $mu_path ) && strlen( $mu_source ) === file_put_contents( $mu_path, $mu_source ), 'Owned exact-page CSP callback installed.' );
	foreach ( $urls as $case => $url ) {
		$directory = $cache_root . '/127.0.0.1' . noobrocket_normalize_cache_url_path( wp_parse_url( $url, PHP_URL_PATH ) );
		$check( ! file_exists( $directory ), 'Unique route starts without a page file.' );
		$witnesses = [];
		for ( $visit = 0; $visit < 3; ++$visit ) {
			$response = $request( $url );
			$check( 200 === wp_remote_retrieve_response_code( $response ) && false !== strpos( wp_remote_retrieve_body( $response ), 'Disposable public cache content.' ), 'Native page renders on visit ' . $visit . ': ' . $case );
			$policy_header = 'report-only' === $case ? 'content-security-policy-report-only' : 'content-security-policy';
			$policy = wp_remote_retrieve_header( $response, $policy_header );
			$witness = wp_remote_retrieve_header( $response, 'x-cache-cache-csp-proof' );
			if ( 'public' !== $case ) {
				$check( "default-src 'self'; object-src 'none'" === $policy, 'PHP policy survives every visit: ' . $case );
				$check( is_string( $witness ) && 0 === strpos( $witness, $suffix . '-' ) && ! in_array( $witness, $witnesses, true ), 'Fresh WordPress callback executes: ' . $case );
				$check( ! is_dir( $directory ) || [] === glob( $directory . '/index*' ), 'CSP response publishes no page file: ' . $case );
				$witnesses[] = $witness;
			} else {
				$check( '' === $policy && is_file( $directory . '/index.html' ), 'Ordinary public response remains cacheable.' );
				if ( $visit > 0 ) {
					$check( '' === $witness, 'Ordinary public hit bypasses the WordPress callback.' );
					$check( hash_file( 'sha256', $directory . '/index.html' ) === hash( 'sha256', wp_remote_retrieve_body( $response ) ), 'Ordinary public hit returns the exact published page file.' );
				}
			}
		}
	}
} finally {
	if ( is_file( $mu_path ) ) {
		if ( file_get_contents( $mu_path ) !== $mu_source ) { throw new RuntimeException( 'Owned callback changed; cleanup refused.' ); }
		unlink( $mu_path );
	}
	if ( $urls ) { noobrocket_clean_files( array_values( $urls ) ); }
	foreach ( $post_ids as $id ) { wp_delete_post( $id, true ); }
}
echo "PASS: $checks native WordPress CSP/public-cache HTTP checks; owned pages/callback removed.\n";
