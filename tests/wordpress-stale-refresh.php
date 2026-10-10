<?php
/**
 * Bounded stale cache, native preload and early HTTP delivery proof.
 * php tests/wordpress-stale-refresh.php /absolute/path/to/disposable/wordpress
 * Requires the marked loopback site running on port 8765. Run mutable fixtures serially.
 */
$wordpress = isset( $argv[1] ) ? realpath( $argv[1] ) : false;
if ( ! $wordpress || ! is_file( $wordpress . '/wp-load.php' ) || ! is_file( $wordpress . '/.noobrocket-acceptance-site' ) ) {
	fwrite( STDERR, "Provide a disposable WordPress installation with the .noobrocket-acceptance-site marker.\n" );
	exit( 2 );
}
putenv( 'NOOBROCKET_TEST_PHASE=stale-refresh' );
$_SERVER['HTTP_HOST'] = '127.0.0.1:8765';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'POST'; // Do not serve an early homepage hit to this CLI fixture.
define( 'WP_ADMIN', true );
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
require $wordpress . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
wp_set_current_user( 1 );

$checks = 0;
function stale_check( $condition, $label ) {
	global $checks;
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $label ); }
	++$checks;
	echo 'PASS: ', $label, PHP_EOL;
}
function stale_request( $url, array $headers = [], $decompress = true ) {
	$proof_origin = getenv( 'NOOBROCKET_STALE_TEST_ORIGIN' );
	if ( $proof_origin ) {
		if ( 'http://127.0.0.1:8877' !== $proof_origin ) { throw new RuntimeException( 'Only the dedicated loopback nginx proof origin is supported.' ); }
		$url = str_replace( 'http://127.0.0.1:8765', $proof_origin, $url );
	}
	$headers = array_merge( [ 'Accept-Encoding' => 'identity' ], $headers );
	if ( ! $decompress ) {
		// Bundled Requests decodes gzip unconditionally; PHP's stream exposes actual wire bytes.
		$lines = [];
		foreach ( $headers as $name => $value ) { $lines[] = $name . ': ' . $value; }
		$context = stream_context_create( [ 'http' => [ 'method' => 'GET', 'header' => implode( "\r\n", $lines ), 'timeout' => 20, 'follow_location' => 0, 'ignore_errors' => true ] ] );
		$body = file_get_contents( $url, false, $context );
		if ( false === $body ) { throw new RuntimeException( 'Raw loopback HTTP request failed.' ); }
		$response = [ 'headers' => [], 'response' => [ 'code' => 0 ], 'body' => $body ];
		foreach ( $http_response_header as $line ) {
			if ( preg_match( '/^HTTP\/\S+ (\d+)/', $line, $match ) ) { $response['response']['code'] = (int) $match[1]; }
			elseif ( false !== strpos( $line, ':' ) ) {
				list( $name, $value ) = explode( ':', $line, 2 );
				$response['headers'][ strtolower( $name ) ] = trim( $value );
			}
		}
		return $response;
	}
	$response = wp_remote_get( $url, [ 'timeout' => 20, 'redirection' => 0, 'headers' => $headers ] );
	if ( is_wp_error( $response ) ) { throw new RuntimeException( $response->get_error_message() ); }
	return $response;
}
function stale_hit( $response ) {
	$status = wp_remote_retrieve_header( $response, 'cache-status' );
	return false !== strpos( is_array( $status ) ? implode( ', ', $status ) : (string) $status, 'NoobRocket; hit' );
}
function stale_child( $wordpress, array $arguments, $input = '' ) {
	$process = proc_open( array_merge( [ PHP_BINARY, __FILE__, $wordpress ], $arguments ), [ 0 => [ 'pipe', 'r' ], 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ], $pipes );
	if ( ! is_resource( $process ) ) { throw new RuntimeException( 'Cannot start a fresh WordPress request.' ); }
	fwrite( $pipes[0], $input );
	fclose( $pipes[0] );
	$output = stream_get_contents( $pipes[1] );
	$errors = stream_get_contents( $pipes[2] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );
	if ( 0 !== proc_close( $process ) ) { throw new RuntimeException( trim( $errors ?: $output ) ); }
	wp_cache_delete( 'alloptions', 'options' );
	wp_cache_delete( 'noobrocket_settings', 'options' );
	return $output;
}
function stale_actions( $url ) {
	return as_get_scheduled_actions( [ 'hook' => 'noobrocket_preload_job_preload_url', 'args' => [ $url ], 'group' => 'noobrocket-preload', 'status' => [ ActionScheduler_Store::STATUS_PENDING, ActionScheduler_Store::STATUS_RUNNING ], 'per_page' => -1 ], 'ids' );
}
function stale_age( array $files, $age ) {
	foreach ( $files as $file ) { if ( is_file( $file ) ) { touch( $file, time() - $age ); } }
	clearstatcache();
}

$home = wp_parse_url( home_url() );
if ( '127.0.0.1' !== $home['host'] || 8765 !== ( $home['port'] ?? 80 ) || 'http' !== $home['scheme'] ) {
	throw new RuntimeException( 'The HTTP fixture requires http://127.0.0.1:8765.' );
}
$phase = $argv[2] ?? '';
if ( 'settings' === $phase ) {
	$options = json_decode( stream_get_contents( STDIN ), true );
	if ( ! is_array( $options ) ) { exit( 2 ); }
	update_option( 'noobrocket_settings', $options );
	exit;
}
if ( $phase ) {
	$id = (int) ( $argv[3] ?? 0 );
	if ( ! get_post_meta( $id, '_noobrocket_stale_fixture', true ) ) { exit( 2 ); }
	$url = get_permalink( $id );
	if ( 'purge' === $phase ) {
		add_filter( 'noobrocket_automatic_cache_purge_urls', static function () use ( $url ) { return [ $url ]; } );
		add_filter( 'noobrocket_refresh_expired_cache', static function ( $retained, $target ) { echo 'REFRESH ', (int) $retained, ' ', $target, PHP_EOL; return $retained; }, PHP_INT_MAX, 2 );
		do_action( 'noobrocket_purge_time_event' );
	} elseif ( 'stage' === $phase ) {
		apply_filters( 'noobrocket_container', null )->get( 'preload_url_controller' )->process_pending_jobs();
	} elseif ( 'update' === $phase ) {
		$result = wp_update_post( [ 'ID' => $id, 'post_content' => 'STALE_REFRESH_UPDATED_CONTENT' ], true );
		if ( is_wp_error( $result ) ) { throw new RuntimeException( $result->get_error_message() ); }
	} elseif ( 'manual' === $phase ) {
		noobrocket_clean_files( $url );
	} elseif ( 'preload' === $phase || 'failure' === $phase ) {
		$attempts = [];
		add_filter( 'noobrocket_preload_delay_between_requests', static function () { return 1; } );
		add_filter( 'pre_http_request', static function ( $pre, $args, $target ) use ( $phase, $url, &$attempts ) {
			if ( $target !== $url ) { return $pre; }
			$attempts[] = $args;
			return 'failure' === $phase ? new WP_Error( 'stale_refresh_fixture_failure', 'Expected refresh failure.' ) : $pre;
		}, PHP_INT_MAX, 3 );
		do_action( 'noobrocket_preload_job_preload_url', $url );
		stale_check( 2 === count( $attempts ), 'native preload requests desktop and mobile variants' );
		foreach ( $attempts as $args ) {
			stale_check( '1' === ( $args['headers']['X-NoobRocket-Refresh'] ?? '' ) && true === $args['blocking'] && 20 === $args['timeout'] && 0 === $args['redirection'], 'native refresh uses a bounded blocking regeneration request' );
		}
	} else { throw new RuntimeException( 'Unknown fixture phase.' ); }
	exit;
}

$original = get_option( 'noobrocket_settings' );
$original_cron = get_option( 'cron' );
$original_pause = get_option( 'noobrocket_preload_paused', null );
$store = ActionScheduler::store();
$original_actions = $store->query_actions( [ 'per_page' => -1 ] );
$mu_file = WP_CONTENT_DIR . '/mu-plugins/noobrocket-stale-refresh-fixture.php';
if ( is_file( $mu_file ) ) { throw new RuntimeException( 'Remove or inspect the previous owned stale fixture before running.' ); }
$post_id = 0;
$url = '';
$owned_dirs = [];
$token = '';
try {
	// Keep AS actions observable; execute only this fixture's native preload hook explicitly.
	file_put_contents( $mu_file, "<?php\nadd_filter( 'action_scheduler_allow_async_request_runner', '__return_false' );\n" );
	$options = array_merge( $original, [
		'cache_stale_refresh' => 1, 'manual_preload' => 1, 'cache_webp' => 0,
		'cache_logged_user' => 0, 'cache_ssl' => 1, 'cache_mobile' => 1, 'do_caching_mobile_files' => 1,
		'purge_cron_interval' => 1, 'purge_cron_unit' => 'HOUR_IN_SECONDS',
		'cache_query_strings' => [ 'noobrocket_asset_preview', 'stale_allowed' ],
		'cache_reject_cookies' => [ 'stale_private_cookie' ], 'cdn' => 0, 'local_css_mode' => 'off',
		'minify_css' => 0, 'minify_js' => 0, 'delay_js' => 0, 'defer_all_js' => 0,
	] );
	delete_option( 'noobrocket_preload_paused' );
	stale_child( $wordpress, [ 'settings' ], wp_json_encode( $options ) );
	stale_check( noobrocket_is_stale_refresh_enabled( $options ), 'stale refresh activates only with supported public preload configuration' );
	foreach ( [ [ 'manual_preload' => 0 ], [ 'cache_webp' => 1 ], [ 'purge_cron_interval' => 0 ], [ 'cache_stale_refresh' => 0 ] ] as $disabled ) {
		stale_check( ! noobrocket_is_stale_refresh_enabled( array_merge( $options, $disabled ) ), 'unsupported or disabled configuration cannot retain stale cache' );
	}
	foreach ( [ 'noobrocket_cache_mandatory_cookies', 'noobrocket_cache_dynamic_cookies' ] as $hook ) {
		$cookie = static function () { return [ 'stale_private_cookie' ]; };
		add_filter( $hook, $cookie );
		stale_check( ! noobrocket_is_stale_refresh_enabled( $options ), 'cookie-dependent caching disables stale refresh: ' . $hook );
		remove_filter( $hook, $cookie );
	}
	$config = get_noobrocket_config_file()[1];
	stale_check( false !== strpos( $config, '$noobrocket_cache_stale_refresh = 1;' ) && false !== strpos( $config, '$noobrocket_cache_lifespan = 3600;' ), 'generated early configuration contains active policy and exact lifespan' );
	stale_check( '' === get_noobrocket_htaccess_mod_rewrite(), 'Apache direct HTML rules are omitted while PHP enforces the stale deadline' );
	stale_child( $wordpress, [ 'settings' ], wp_json_encode( $options ) );
	$post_id = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Stale refresh proof', 'post_name' => 'noobrocket-stale-' . uniqid(), 'post_content' => 'STALE_REFRESH_ORIGINAL_CONTENT' ], true );
	stale_check( ! is_wp_error( $post_id ), 'disposable canonical public page created' );
	update_post_meta( $post_id, '_noobrocket_stale_fixture', 1 );
	$url = get_permalink( $post_id );
	$parts = wp_parse_url( $url );
	$race_queue = new class extends NoobRocket\Engine\Preload\Controller\Queue {
		public $probes = 0;
		public $winner = true;
		public function is_scheduled( $hook, $args = [] ) {
			++$this->probes;
			return $this->winner;
		}
		public function add_job_preload_job_preload_url_async( string $url ) { return 0; }
	};
	$container = apply_filters( 'noobrocket_container', null );
	$refresh_subscriber = new NoobRocket\Engine\Preload\Subscriber(
		new NoobRocket\Admin\Options_Data( $options ), $container->get( 'load_initial_sitemap_controller' ),
		$container->get( 'preload_caches_query' ), $container->get( 'preload_activation' ),
		$container->get( 'mobile_detect' ), $container->get( 'preload_clean_controller' ), $race_queue
	);
	stale_check( $refresh_subscriber->refresh_expired_cache( false, $url ) && 1 === $race_queue->probes, 'already scheduled work retains stale cache without another admission' );
	$race_queue->winner = false;
	$race_queue->probes = 0;
	stale_check( $refresh_subscriber->refresh_expired_cache( false, $url ) && [] === stale_actions( $url ), 'refresh is staged as pending work without bypassing adaptive admission' );
	$dir = NOOBROCKET_CACHE_PATH . $parts['host'] . strtolower( $parts['path'] );
	$owned_dirs[] = $dir;
	$desktop = $dir . 'index.html';
	$mobile = $dir . 'index-mobile.html';
	$mobile_headers = [ 'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 9_1 like Mac OS X) AppleWebKit/601.1.46 Version/9.0 Mobile/13B143 Safari/601.1' ];
	stale_check( ! stale_hit( stale_request( $url ) ) && ! stale_hit( stale_request( $url, $mobile_headers ) ), 'cold requests generate both real anonymous cache variants' );
	if ( getenv( 'NOOBROCKET_STALE_TEST_ORIGIN' ) ) {
		stale_check( 'php' === wp_remote_retrieve_header( stale_request( $url ), 'x-noobrocket-proof-route' ), 'dedicated nginx routes page requests through PHP instead of a static cache rewrite' );
		$direct_url = content_url( '/cache/noobrocket/pages/' . $parts['host'] . strtolower( $parts['path'] ) . 'index.html' );
		stale_check( 404 === wp_remote_retrieve_response_code( stale_request( $direct_url ) ), 'dedicated nginx blocks direct page-cache directory URLs' );
	}
	$files = [ $desktop, $desktop . '_gzip', $mobile, $mobile . '_gzip' ];
	stale_check( count( array_filter( $files, 'is_file' ) ) === 4, 'native writer creates plain and gzip desktop/mobile files' );
	foreach ( [ 'index-https.html', 'index-https.html_gzip', 'index-mobile-https.html', 'index-mobile-https.html_gzip' ] as $name ) {
		copy( false !== strpos( $name, '_gzip' ) ? $desktop . '_gzip' : $desktop, $dir . $name );
		$files[] = $dir . $name;
	}
	$preview_dir = $dir . '#noobrocket_asset_preview=1/';
	$allowed_dir = $dir . '#stale_allowed=1/';
	$user_dir = NOOBROCKET_CACHE_PATH . $parts['host'] . '-stale-fixture-user-' . $options['secret_cache_key'] . strtolower( $parts['path'] );
	foreach ( [ $preview_dir, $allowed_dir, $user_dir ] as $variant_dir ) {
		wp_mkdir_p( $variant_dir );
		$owned_dirs[] = $variant_dir;
		file_put_contents( $variant_dir . 'index.html', 'VARIANT_CACHE_MUST_NOT_LEAK' );
		touch( $variant_dir . 'index.html', time() - 3610 );
	}
	file_put_contents( $dir . 'index-webp.html', 'LEGACY_WEBP_MUST_NOT_RETAIN' );
	touch( $dir . 'index-webp.html', time() - 3610 );
	stale_age( $files, 3610 );
	$mtime = filemtime( $desktop );
	stale_check( stale_hit( stale_request( $url ) ) && stale_hit( stale_request( $url, $mobile_headers ) ), 'expired canonical desktop/mobile HTML remains deliverable during one-hour grace' );
	$gzip = stale_request( $url, [ 'Accept-Encoding' => 'gzip' ], false );
	stale_check( stale_hit( $gzip ) && 'gzip' === wp_remote_retrieve_header( $gzip, 'content-encoding' ) && false !== stripos( (string) wp_remote_retrieve_header( $gzip, 'vary' ), 'Accept-Encoding' ) && wp_remote_retrieve_body( $gzip ) === file_get_contents( $desktop . '_gzip' ) && gzdecode( wp_remote_retrieve_body( $gzip ) ) === file_get_contents( $desktop ), 'stale gzip delivery sends the stored compressed bytes with correct encoding, Vary and decoded HTML' );
	$identity = stale_request( $url, [ 'Accept-Encoding' => 'br, gzip;q=0' ], false );
	stale_check( stale_hit( $identity ) && ! wp_remote_retrieve_header( $identity, 'content-encoding' ) && wp_remote_retrieve_body( $identity ) === file_get_contents( $desktop ), 'gzip q=0 selects the plain stale variant' );
	stale_check( ! stale_hit( stale_request( $url . '?stale_allowed=1' ) ), 'configured query variants have immediate TTL cutoff even before cron' );
	touch( $preview_dir . 'index.html', time() ); // A fresh preview cache would leak if the explicit early bypass were absent.
	$preview = stale_request( $url . '?noobrocket_asset_preview=1', [ 'X-NoobRocket-Refresh' => '1' ] );
	stale_check( ! stale_hit( $preview ) && false === strpos( wp_remote_retrieve_body( $preview ), 'VARIANT_CACHE_MUST_NOT_LEAK' ), 'private preview key bypasses existing cache even when explicitly query-cacheable' );
	stale_check( 'VARIANT_CACHE_MUST_NOT_LEAK' === file_get_contents( $preview_dir . 'index.html' ), 'private preview request cannot overwrite a query cache variant' );
	touch( $preview_dir . 'index.html', time() - 3610 );
	stale_check( ! stale_hit( stale_request( $url . '?stale_unknown=1', [ 'X-NoobRocket-Refresh' => '1' ] ) ), 'refresh header preserves unknown-query protection' );
	stale_check( ! stale_hit( stale_request( $url, [ 'X-NoobRocket-Refresh' => '1', 'Cookie' => 'stale_private_cookie=1' ] ) ), 'refresh header preserves rejected-cookie protection' );
	$token = WP_Session_Tokens::get_instance( 1 )->create( time() + 600 );
	$cookie = LOGGED_IN_COOKIE . '=' . wp_generate_auth_cookie( 1, time() + 600, 'logged_in', $token );
	stale_check( ! stale_hit( stale_request( $url, [ 'X-NoobRocket-Refresh' => '1', 'Cookie' => $cookie ] ) ), 'refresh header preserves authenticated-user protection' );
	clearstatcache();
	stale_check( filemtime( $desktop ) === $mtime, 'bypassed private/query requests do not refresh shared stale bytes' );
	// Re-age the query variant regenerated by its legitimate cacheable request.
	touch( $allowed_dir . 'index.html', time() - 3610 );
	$purge_output = stale_child( $wordpress, [ 'purge', (string) $post_id ] );
	stale_child( $wordpress, [ 'purge', (string) $post_id ] );
	clearstatcache();
	if ( count( array_filter( $files, 'is_file' ) ) !== 8 ) { fwrite( STDERR, 'Retained=' . count( array_filter( $files, 'is_file' ) ) . ' cache=' . NOOBROCKET_CACHE_PATH . ' ' . $purge_output . PHP_EOL ); }
	stale_check( count( array_filter( $files, 'is_file' ) ) === 8 && filemtime( $desktop ) === $mtime, 'TTL purge retains all canonical HTTP/HTTPS desktop/mobile plain/gzip bytes without touching mtime' );
	stale_check( ! is_file( $dir . 'index-webp.html' ) && ! is_file( $preview_dir . 'index.html' ) && ! is_file( $allowed_dir . 'index.html' ) && ! is_file( $user_dir . 'index.html' ), 'TTL purge immediately removes legacy WebP, query and per-user variants' );
	$https_url = set_url_scheme( $url, 'https' );
	stale_check( [] === stale_actions( $url ) && [] === stale_actions( $https_url ), 'expiry scans never directly fan out native scheduler actions' );
	stale_child( $wordpress, [ 'stage', (string) $post_id ] );
	stale_check( 1 === count( stale_actions( $url ) ) && 1 === count( stale_actions( $https_url ) ), 'ordinary adaptive staging deduplicates one native action per URL scheme, covering both device variants' );
	$action_id = stale_actions( $url )[0];
	$store->log_execution( $action_id );
	stale_child( $wordpress, [ 'purge', (string) $post_id ] );
	stale_check( [ $action_id ] === stale_actions( $url ), 'running Action Scheduler refresh prevents duplicate enqueue' );
	$failure_output = stale_child( $wordpress, [ 'failure', (string) $post_id ] );
	clearstatcache();
	stale_check( false !== strpos( $failure_output, 'bounded blocking regeneration request' ) && filemtime( $desktop ) === $mtime && filemtime( $mobile ) === $mtime, 'failed native refresh keeps stale bytes and their original deadline unchanged' );
	$preload_output = stale_child( $wordpress, [ 'preload', (string) $post_id ] );
	clearstatcache();
	stale_check( false !== strpos( $preload_output, 'bounded blocking regeneration request' ) && filemtime( $desktop ) > $mtime && filemtime( $mobile ) > $mtime, 'native preload refresh regenerates both variants through the real early PHP path' );
	$store->mark_complete( $action_id );
	stale_check( stale_hit( stale_request( $url ) ), 'successful regeneration returns to warm early-cache delivery' );
	stale_age( $files, 7201 );
	$cutoff = stale_request( $url, [ 'Accept-Encoding' => 'gzip' ] );
	clearstatcache();
	stale_check( ! stale_hit( $cutoff ) && filemtime( $desktop ) > $mtime && false !== strpos( wp_remote_retrieve_body( $cutoff ), 'STALE_REFRESH_ORIGINAL_CONTENT' ), 'TTL plus one-hour cutoff regenerates before cron runs, including gzip requests' );
	stale_request( $url, $mobile_headers );
	stale_age( $files, 3610 );
	stale_child( $wordpress, [ 'update', (string) $post_id ] );
	clearstatcache();
	stale_check( ! is_file( $desktop ) && ! is_file( $mobile ) && ! is_file( $dir . 'index-https.html' ), 'content change immediately purges retained desktop/mobile and HTTPS bytes' );
	$response = stale_request( $url );
	stale_check( ! stale_hit( $response ) && false !== strpos( wp_remote_retrieve_body( $response ), 'STALE_REFRESH_UPDATED_CONTENT' ), 'content purge delivers the update on the next request' );
	stale_age( [ $desktop, $desktop . '_gzip' ], 3610 );
	stale_child( $wordpress, [ 'manual', (string) $post_id ] );
	clearstatcache();
	stale_check( ! is_file( $desktop ) && ! is_file( $desktop . '_gzip' ), 'manual URL purge removes stale bytes immediately' );
	echo $checks, " stale-refresh assertions passed.\n";
} finally {
	if ( $token ) { WP_Session_Tokens::get_instance( 1 )->destroy( $token ); }
	if ( $post_id && ! is_wp_error( $post_id ) ) { wp_delete_post( $post_id, true ); }
	if ( $url ) { apply_filters( 'noobrocket_container', null )->get( 'preload_caches_query' )->delete_by_url( $url ); }
	foreach ( array_reverse( $owned_dirs ) as $owned_dir ) {
		if ( ! is_dir( $owned_dir ) ) { continue; }
		foreach ( new DirectoryIterator( $owned_dir ) as $file ) { if ( $file->isFile() ) { unlink( $file->getPathname() ); } }
		if ( 2 === count( scandir( $owned_dir ) ) ) { rmdir( $owned_dir ); }
	}
	stale_child( $wordpress, [ 'settings' ], wp_json_encode( $original ) );
	if ( null === $original_pause ) { delete_option( 'noobrocket_preload_paused' ); } else { update_option( 'noobrocket_preload_paused', $original_pause ); }
	update_option( 'cron', $original_cron );
	foreach ( array_diff( $store->query_actions( [ 'per_page' => -1 ] ), $original_actions ) as $action_id ) { $store->delete_action( $action_id ); }
	if ( is_file( $mu_file ) ) { unlink( $mu_file ); }
}
