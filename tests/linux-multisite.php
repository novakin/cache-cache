<?php
/** Dedicated network proof. The runner owns and discards its entire site/database. */
$root = isset( $argv[1] ) ? realpath( $argv[1] ) : false;
$phase = $argv[2] ?? '';
if ( PHP_SAPI !== 'cli' || ! $root || ! is_file( $root . '/.noobrocket-acceptance-site' ) || getenv( 'NOOBROCKET_LINUX_PROOF' ) !== $root ) { exit( 2 ); }
$_SERVER['HTTP_HOST'] = '127.0.0.1:8875';
$_SERVER['SERVER_PORT'] = 8875;
$_SERVER['REQUEST_URI'] = 'subsite' === ( $argv[3] ?? '' ) ? '/subsite/' : '/';
$_SERVER['REQUEST_METHOD'] = 'POST';
define( 'WP_ADMIN', true );
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
if ( 'network-install' === $phase ) { define( 'WP_INSTALLING_NETWORK', true ); }
require $root . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
wp_set_current_user( 1 );
$checks = 0;
$check = static function ( $condition, $label ) use ( &$checks ) { if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $label ); } ++$checks; };
if ( 'network-install' === $phase ) {
	// Match wp-admin/network.php: wpdb does not assign these on a single site.
	global $wpdb;
	foreach ( $wpdb->tables( 'ms_global' ) as $table => $prefixed_table ) { $wpdb->$table = $prefixed_table; }
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	install_network();
	$result = populate_network( 1, '127.0.0.1:8875', 'proof@example.test', 'NoobRocket proof network', '/', false );
	$check( ! is_wp_error( $result ), 'disposable network populated' );
	$config = file_get_contents( $root . '/wp-config.php' );
	$config = preg_replace( "/define\('WP_HOME',.*?\);\s*define\('WP_SITEURL',.*?\);/", '', $config );
	$config = str_replace( '$table_prefix=', "define('MULTISITE',true); define('SUBDOMAIN_INSTALL',false); define('DOMAIN_CURRENT_SITE','127.0.0.1:8875'); define('PATH_CURRENT_SITE','/'); define('SITE_ID_CURRENT_SITE',1); define('BLOG_ID_CURRENT_SITE',1);\n\$table_prefix=", $config );
	file_put_contents( $root . '/wp-config.php', $config );
	echo "Created dedicated WordPress network.\n";
	exit;
}
if ( 'network-activate' === $phase ) {
	$check( is_multisite(), 'real network active' );
	$result = activate_plugin( 'noobrocket/noobrocket.php', '', true );
	$check( ! is_wp_error( $result ), 'plugin network activation succeeds' );
	$debug_path = WP_CONTENT_DIR . '/debug.log';
	$debug_before = is_file( $debug_path ) ? file_get_contents( $debug_path ) : '';
	$id = wp_insert_site( [ 'domain' => '127.0.0.1:8875', 'path' => '/subsite/', 'network_id' => 1 ] );
	$check( ! is_wp_error( $id ) && $id > 1, 'secondary blog initialized' );
	$check( wp_is_site_initialized( $id ), 'native site initialization creates its tables' );
	$debug_after = is_file( $debug_path ) ? file_get_contents( $debug_path ) : '';
	$check( $debug_before === $debug_after, 'site birth adds no database warnings or PHP errors' );
	switch_to_blog( $id );
	update_option( 'home', 'http://127.0.0.1:8875/subsite' );
	update_option( 'siteurl', 'http://127.0.0.1:8875/subsite' );
	restore_current_blog();
	echo $checks . " network activation and site-birth assertions passed.\n";
	exit;
}
if ( 'prepare' === $phase ) {
	do_action( 'admin_init' );
	$subsite = 'subsite' === ( $argv[3] ?? '' );
	$check( get_current_blog_id() === ( $subsite ? 2 : 1 ), 'CLI bootstrap selects intended blog' );
	$options = get_option( 'noobrocket_settings' );
	$check( is_array( $options ), 'site-local options exist' );
	update_option( 'noobrocket_settings', array_merge( $options, [ 'local_css_mode' => 'used', 'local_css_safelist' => [ $subsite ? '.subsite-only' : '.main-only' ] ] ) );
	update_option( 'noobrocket_local_css_paused', $subsite );
	$jobs = apply_filters( 'noobrocket_container', null )->get( 'local_css_jobs' );
	$check( $jobs->enqueue( home_url( '/' ), false, 'used', md5( $subsite ? 'subsite' : 'main' ), [ [ 'href' => home_url( '/proof.css' ), 'media' => 'all' ] ], [ $subsite ? '.subsite-only' : '.main-only' ] ), 'site-local job inserted' );
	echo $checks . ' multisite ' . ( $subsite ? 'subsite' : 'main' ) . " preparation assertions passed.\n";
	exit;
}
if ( 'check' === $phase ) {
	$jobs = apply_filters( 'noobrocket_container', null )->get( 'local_css_jobs' );
	$main = $jobs->find( home_url( '/' ), false );
	$check( $main && md5( 'main' ) === $main->hash, 'main queue contains only own job' );
	$check( [ '.main-only' ] === get_noobrocket_option( 'local_css_safelist' ) && ! get_option( 'noobrocket_local_css_paused' ), 'main settings and pause remain independent' );
	$lease = json_decode( file_get_contents( dirname( $root ) . '/main-worker-lease.json' ), true );
	$main_health = get_option( 'noobrocket_local_css_worker' );
	$check( 'lease-proof' === ( $main_health['platform'] ?? '' ) && false !== strpos( (string) get_option( 'noobrocket_local_css_worker_lock' ), $lease['token'] ), 'subsite check leaves active main worker lease and health untouched' );
	switch_to_blog( 2 );
	$subsite = $jobs->find( home_url( '/' ), false );
	$check( $subsite && md5( 'subsite' ) === $subsite->hash, 'BerlinDB switch_blog resolves secondary queue' );
	$check( [ '.subsite-only' ] === get_noobrocket_option( 'local_css_safelist' ) && get_option( 'noobrocket_local_css_paused' ), 'secondary settings and pause remain independent' );
	$sub_health = get_option( 'noobrocket_local_css_worker' );
	$check( 'ready' === ( $lease['health']['state'] ?? '' ) && 'ready' === ( $sub_health['state'] ?? '' ), 'actual worker --site-url checks recorded per-site health' );
	$check( ! get_option( 'noobrocket_local_css_worker_lock' ), 'subsite check released own worker lease' );
	restore_current_blog();
	$check( $jobs->worker_stop( $lease['token'], 'stopped' ), 'main worker releases only its own lease' );
	$check( ! get_option( 'noobrocket_local_css_worker_lock' ), 'main check released own worker lease' );
	echo $checks . " actual multisite queue/settings/worker isolation assertions passed.\n";
	exit;
}
if ( 'hold-main' === $phase ) {
	$jobs = apply_filters( 'noobrocket_container', null )->get( 'local_css_jobs' );
	$health = get_option( 'noobrocket_local_css_worker' );
	$token = $jobs->worker_start( [ 'platform' => 'lease-proof' ] );
	$check( is_string( $token ) && $token, 'main worker lease held while secondary worker checks' );
	file_put_contents( dirname( $root ) . '/main-worker-lease.json', wp_json_encode( [ 'token' => $token, 'health' => $health ] ) );
	echo "Held dedicated main-site worker lease.\n";
	exit;
}
if ( 'invalid-site' === $phase ) {
	$jobs = apply_filters( 'noobrocket_container', null )->get( 'local_css_jobs' );
	$before = $jobs->diagnostics();
	foreach ( [ 'https://127.0.0.1:8875/', 'http://127.0.0.1:8875/wrong-path/', 'http://wrong.example.test:8875/' ] as $url ) {
		$process = proc_open( [ PHP_BINARY, WP_PLUGIN_DIR . '/noobrocket/local-css/bridge.php', $root, $url ], [ 0 => [ 'pipe', 'r' ], 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ], $pipes );
		fwrite( $pipes[0], '{"action":"claim"}' ); fclose( $pipes[0] );
		$output = stream_get_contents( $pipes[1] ); $error = stream_get_contents( $pipes[2] );
		fclose( $pipes[1] ); fclose( $pipes[2] );
		$code = proc_close( $process );
		$decoded = json_decode( $output, true );
		$check( 0 !== $code || ! is_array( $decoded ) || empty( $decoded['ok'] ), 'wrong scheme/path/host rejected before claim: ' . $url );
		$check( $before['counts'] === $jobs->diagnostics()['counts'] && ! get_option( 'noobrocket_local_css_worker_lock' ), 'rejected site selection preserves queue and worker lease' );
	}
	echo $checks . " invalid site-selection preservation assertions passed.\n";
	exit;
}
throw new RuntimeException( 'Unknown phase.' );
