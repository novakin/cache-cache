<?php
/** Dedicated Linux/MariaDB/Apache/Nginx fixture. Never run on existing data. */
$root = isset( $argv[1] ) ? realpath( $argv[1] ) : false;
$phase = $argv[2] ?? '';
if ( PHP_SAPI !== 'cli' || ! $root || ! is_file( $root . '/.noobrocket-acceptance-site' ) || ! is_file( $root . '/wp-load.php' ) || getenv( 'NOOBROCKET_LINUX_PROOF' ) !== $root ) {
	fwrite( STDERR, "Dedicated marked Linux proof site and NOOBROCKET_LINUX_PROOF are required.\n" );
	exit( 2 );
}
$_SERVER['HTTP_HOST'] = '127.0.0.1:8875';
$_SERVER['SERVER_PORT'] = '8875';
$_SERVER['SERVER_SOFTWARE'] = 'Apache/2.4';
$_SERVER['DOCUMENT_ROOT'] = $root;
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'POST';
define( 'WP_ADMIN', true );
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
if ( 'install' === $phase ) { define( 'WP_INSTALLING', true ); }
require $root . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
$checks = 0;
$check = static function ( $condition, $label ) use ( &$checks ) {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $label ); }
	++$checks;
};

if ( 'install' === $phase ) {
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	add_filter( 'pre_wp_mail', '__return_true' );
	if ( ! is_blog_installed() ) {
		// The chosen structure is tested once the owned listener is running.
		$install_permalink_structure = static function () { return '/%postname%/'; };
		add_filter( 'pre_option_permalink_structure', $install_permalink_structure );
		try {
			wp_install( 'NoobRocket Linux proof', 'noobrocket', 'proof@example.test', false, '', 'disposable-noobrocket-proof' );
		} finally {
			remove_filter( 'pre_option_permalink_structure', $install_permalink_structure );
		}
	}
	update_option( 'permalink_structure', '/%postname%/' );
	echo "Installed Linux/MariaDB disposable WordPress.\n";
	exit;
}
if ( 'activate' === $phase ) {
	wp_set_current_user( 1 );
	$result = activate_plugin( 'noobrocket/noobrocket.php' );
	$check( ! is_wp_error( $result ), 'plugin activates on MariaDB' );
	do_action( 'admin_init' );
	flush_rewrite_rules();
	flush_noobrocket_htaccess();
	echo "Activated NoobRocket in a fresh WordPress request.\n";
	exit;
}
if ( 'boot' === $phase ) {
	wp_set_current_user( 1 );
	do_action( 'admin_init' );
	$check( is_array( get_option( 'noobrocket_settings' ) ), 'settings initialized in fresh active-plugin request' );
	flush_rewrite_rules();
	flush_noobrocket_htaccess();
	echo "Booted NoobRocket settings and cache configuration.\n";
	exit;
}

if ( 'worker-prepare' === $phase ) {
	wp_set_current_user( 1 );
	$options = get_option( 'noobrocket_settings' );
	update_option( 'noobrocket_settings', array_merge( $options, [ 'local_css_mode' => 'used', 'manual_preload' => 0, 'minify_css' => 0, 'minify_js' => 0, 'cdn' => 0 ] ) );
	$id = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Linux browser worker', 'post_name' => 'linux-worker-' . uniqid(), 'post_content' => '<p class="linux-worker-proof">Linux browser generated CSS.</p>' ] );
	update_post_meta( $id, '_noobrocket_linux_worker', 1 );
	file_put_contents( dirname( $root ) . '/worker-state.json', wp_json_encode( [ 'id' => $id, 'url' => get_permalink( $id ), 'options' => $options ] ) );
	$response = wp_remote_get( get_permalink( $id ), [ 'timeout' => 15, 'redirection' => 0 ] );
	$check( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ), 'worker fixture page delivered' );
	$job = apply_filters( 'noobrocket_container', null )->get( 'local_css_jobs' )->find( get_permalink( $id ), false );
	$check( $job && 'local-pending' === $job->status, 'actual anonymous frontend queued a Linux CSS job' );
	echo "Prepared actual Linux worker CSS job.\n";
	exit;
}
if ( 'worker-check' === $phase ) {
	$state = json_decode( file_get_contents( dirname( $root ) . '/worker-state.json' ), true );
	try {
		$job = apply_filters( 'noobrocket_container', null )->get( 'local_css_jobs' )->find( $state['url'], false );
		$check( $job && 'local-completed' === $job->status, 'actual sandboxed Linux worker published CSS through MariaDB bridge' );
		$response = wp_remote_get( $state['url'], [ 'timeout' => 15, 'redirection' => 0 ] );
		$check( ! is_wp_error( $response ) && false !== strpos( wp_remote_retrieve_body( $response ), '/used-css/' ), 'public page delivers generated Linux CSS' );
		$worker = get_option( 'noobrocket_local_css_worker' );
		$check( ! empty( $worker['processed'] ) && 0 === $worker['failed'] && 'linux' === $worker['platform'] && $worker['sandbox'], 'worker health confirms bounded Linux sandbox generation' );
		echo $checks . " native Linux worker publication assertions passed.\n";
	} finally {
		wp_delete_post( $state['id'], true );
		update_option( 'noobrocket_settings', $state['options'] );
	}
	exit;
}
if ( 'http' !== $phase ) { throw new RuntimeException( 'Unknown fixture phase.' ); }
wp_set_current_user( 1 );
$original = get_option( 'noobrocket_settings' );
$original_cron = get_option( 'cron' );
$ids = [];
$token = '';
try {
	$settings = array_merge( $original, [ 'cache_mobile' => 0, 'do_caching_mobile_files' => 0, 'cache_logged_user' => 0, 'manual_preload' => 0, 'cdn' => 0, 'local_css_mode' => 'off', 'minify_css' => 0, 'minify_js' => 0, 'delay_js' => 0, 'defer_all_js' => 0, 'cache_reject_cookies' => [ 'woocommerce_items_in_cart', 'woocommerce_cart_hash', 'wp_woocommerce_session_' ] ] );
	update_option( 'noobrocket_settings', $settings );
	$is_apache = true;
	flush_noobrocket_htaccess();
	$ids['public'] = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Linux public proof', 'post_name' => 'linux-public-' . uniqid(), 'post_content' => 'PUBLIC_PROOF_BODY' ] );
	$ids['private'] = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'private', 'post_title' => 'Linux private proof', 'post_name' => 'linux-private-' . uniqid(), 'post_content' => 'PRIVATE_PROOF_BODY' ] );
	$ids['password'] = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Linux password proof', 'post_name' => 'linux-password-' . uniqid(), 'post_password' => 'proof-password', 'post_content' => 'PASSWORD_PROOF_BODY' ] );
	$check( ! in_array( 0, $ids, true ), 'disposable content created' );
	$url = get_permalink( $ids['public'] );
	$request = static function ( $target, $cookie = '' ) {
		$result = wp_remote_get( $target, [ 'timeout' => 15, 'redirection' => 0, 'headers' => [ 'Accept-Encoding' => 'identity', 'Cookie' => $cookie ] ] );
		if ( is_wp_error( $result ) ) { throw new RuntimeException( $result->get_error_message() ); }
		return $result;
	};
	$static = static function ( $response ) { return 'hit' === wp_remote_retrieve_header( $response, 'x-noobrocket-static' ); };
	$cold = $request( $url );
	if ( 200 !== wp_remote_retrieve_response_code( $cold ) || false === strpos( wp_remote_retrieve_body( $cold ), 'PUBLIC_PROOF_BODY' ) ) {
		file_put_contents( dirname( $root ) . '/cold-failure.json', wp_json_encode( [ 'url' => $url, 'status' => wp_remote_retrieve_response_code( $cold ), 'headers' => (array) wp_remote_retrieve_headers( $cold ), 'body_start' => substr( wp_remote_retrieve_body( $cold ), 0, 300 ) ], JSON_PRETTY_PRINT ) );
	}
	$check( 200 === wp_remote_retrieve_response_code( $cold ) && false !== strpos( wp_remote_retrieve_body( $cold ), 'PUBLIC_PROOF_BODY' ), 'public cold delivery works' );
	$warm = $request( $url );
	if ( ! $static( $warm ) ) {
		file_put_contents( dirname( $root ) . '/delivery-failure.json', wp_json_encode( [ 'url' => $url, 'cold_headers' => (array) wp_remote_retrieve_headers( $cold ), 'warm_headers' => (array) wp_remote_retrieve_headers( $warm ), 'wp_cache' => defined( 'WP_CACHE' ) && WP_CACHE, 'cache_files' => glob( NOOBROCKET_CACHE_PATH . '*/*/index*' ) ], JSON_PRETTY_PRINT ) );
	}
	$check( $static( $warm ), 'native server delivers cached HTML without PHP' );
	$check( false !== strpos( wp_remote_retrieve_body( $warm ), 'PUBLIC_PROOF_BODY' ), 'static body belongs to public page' );
	$check( ! $static( $request( $url . '?proof=unknown' ) ), 'query bypasses native shared cache' );
	$token = WP_Session_Tokens::get_instance( 1 )->create( time() + 600 );
	$cookie = LOGGED_IN_COOKIE . '=' . wp_generate_auth_cookie( 1, time() + 600, 'logged_in', $token );
	$check( ! $static( $request( $url, $cookie ) ), 'authenticated user bypasses native shared cache' );
	foreach ( [ 'woocommerce_items_in_cart=1', 'woocommerce_cart_hash=proof', 'wp_woocommerce_session_proof=private' ] as $cart ) {
		$check( ! $static( $request( $url, $cart ) ), 'cart/session cookie bypasses native shared cache: ' . explode( '=', $cart )[0] );
	}
	foreach ( [ 'private', 'password' ] as $type ) {
		for ( $visit = 0; $visit < 2; ++$visit ) {
			$response = $request( get_permalink( $ids[ $type ] ) );
			if ( 'private' === $type ) { $check( ! $static( $response ), 'private page never enters shared static cache' ); }
			$check( false === strpos( wp_remote_retrieve_body( $response ), strtoupper( $type ) . '_PROOF_BODY' ), $type . ' content not exposed anonymously' );
		}
	}
	require_once ABSPATH . WPINC . '/class-phpass.php';
	$hasher = new PasswordHash( 8, true );
	$password_cookie = 'wp-postpass_' . COOKIEHASH . '=' . $hasher->HashPassword( 'proof-password' );
	$unlocked = $request( get_permalink( $ids['password'] ), $password_cookie );
	$check( ! $static( $unlocked ) && false !== strpos( wp_remote_retrieve_body( $unlocked ), 'PASSWORD_PROOF_BODY' ), 'valid post-password cookie bypasses shared cache and unlocks only this request' );
	$check( false === strpos( wp_remote_retrieve_body( $request( get_permalink( $ids['password'] ) ) ), 'PASSWORD_PROOF_BODY' ), 'anonymous password form remains safe after an unlocked request' );
	echo $checks . " Linux native-cache/isolation assertions passed.\n";
} finally {
	if ( $token ) { WP_Session_Tokens::get_instance( 1 )->destroy( $token ); }
	foreach ( $ids as $id ) { wp_delete_post( $id, true ); }
	update_option( 'noobrocket_settings', $original );
	update_option( 'cron', $original_cron );
	flush_noobrocket_htaccess();
}
