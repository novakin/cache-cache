<?php
/** Real owned network saves and early cache requests; no HTTP listener or provider requests. */
$wordpress = isset( $argv[1] ) ? realpath( $argv[1] ) : false;
if ( PHP_SAPI !== 'cli' || ! $wordpress || ! is_file( $wordpress . '/.noobrocket-acceptance-site' ) ) { throw new RuntimeException( 'Provide a marked disposable WordPress network.' ); }
$source = dirname( __DIR__ ) . '/noobrocket/';
$installed = $wordpress . '/wp-content/plugins/noobrocket/';
if ( realpath( $source ) === realpath( $installed ) || str_replace( '\\', '/', realpath( $installed ) ) !== str_replace( '\\', '/', rtrim( $installed, '/' ) ) ) { throw new RuntimeException( 'Native mobile-loader proof requires a separate plugin copy.' ); }
foreach ( [ 'inc/Engine/Cache/AdvancedCache.php', 'views/cache/advanced-cache.php', 'inc/classes/Buffer/class-config.php', 'inc/classes/Buffer/class-cache.php' ] as $file ) {
	if ( ! is_file( $installed . $file ) || hash_file( 'sha256', $source . $file ) !== hash_file( 'sha256', $installed . $file ) ) { throw new RuntimeException( 'Installed mobile-loader owner differs from candidate.' ); }
}
$phase = $argv[2] ?? '';
$debug_file = $wordpress . '/wp-content/debug.log';
$debug_before = is_file( $debug_file ) ? file_get_contents( $debug_file ) : '';
$phone = 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 Version/16.0 Mobile/15E148 Safari/604.1';
$desktop = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/143.0.0.0 Safari/537.36';
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8765';
$_SERVER['SERVER_PORT'] = parse_url( 'http://' . $_SERVER['HTTP_HOST'], PHP_URL_PORT ) ?: 80;
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'POST';
if ( in_array( $phase, [ '--save', '--sample', '--cache' ], true ) ) {
	$target = parse_url( '--save' === $phase ? $argv[5] : $argv[3] );
	$_SERVER['HTTP_HOST'] = $target['host'] . ( isset( $target['port'] ) ? ':' . $target['port'] : '' );
	$_SERVER['SERVER_PORT'] = $target['port'] ?? ( 'https' === $target['scheme'] ? 443 : 80 );
	$_SERVER['REQUEST_URI'] = $target['path'];
	$_SERVER['HTTP_USER_AGENT'] = 'phone' === $argv[4] ? $phone : $desktop;
	$_SERVER['HTTPS'] = 'https' === $target['scheme'] ? 'on' : '';
	$_SERVER['REQUEST_METHOD'] = '--cache' === $phase ? 'GET' : 'POST';
	if ( '--sample' === $phase ) { putenv( 'NOOBROCKET_MOBILE_EARLY_SAMPLE=' . $argv[5] ); }
	if ( '--save' !== $phase ) {
		// Parent requires exact early sample/cache bytes, so an unrelated early exit cannot pass.
		define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
		require $wordpress . '/wp-load.php';
		throw new RuntimeException( 'Expected owned early request receipt was not produced.' );
	}
}
define( 'WP_ADMIN', true );
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
$completed = false;
register_shutdown_function( static function () use ( &$completed ) {
	if ( ! $completed ) {
		$error = error_get_last();
		if ( $error && in_array( $error['type'], [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ], true ) ) { fwrite( STDERR, $error['message'] . "\n" ); }
		fwrite( STDERR, "FAIL: native mobile-loader fixture did not complete.\n" );
		exit( 2 );
	}
} );
require $wordpress . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
wp_set_current_user( 1 );
if ( ! is_multisite() || ! is_plugin_active_for_network( 'noobrocket/noobrocket.php' ) ) { throw new RuntimeException( 'Native mobile-loader proof requires a network-active copied plugin.' ); }
if ( '--save' === $phase ) {
	switch_to_blog( (int) $argv[3] );
	try {
		do_action( 'admin_init' );
		update_option( 'permalink_structure', '/%postname%/' );
		$options = get_option( 'noobrocket_settings' );
		$mobile = (int) $argv[4];
		update_option( 'noobrocket_settings', array_merge( $options, [ 'cache_mobile' => $mobile, 'do_caching_mobile_files' => $mobile, 'manual_preload' => 0 ] ) );
		if ( $mobile !== (int) get_noobrocket_option( 'cache_mobile' ) || $mobile !== (int) get_noobrocket_option( 'do_caching_mobile_files' ) ) { throw new RuntimeException( 'Native mobile settings save was not retained.' ); }
		echo json_encode( [ 'saved' => (int) $argv[3], 'mobile' => $mobile ] ), PHP_EOL;
	} finally {
		restore_current_blog();
	}
	$completed = true;
	exit;
}
$checks = 0;
$check = static function ( $condition, $message ) use ( &$checks ) {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
	++$checks;
};
$run = static function ( array $args ) {
	$worker = proc_open( array_merge( [ PHP_BINARY, __FILE__ ], $args ), [ 0 => [ 'pipe', 'r' ], 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ], $pipes );
	if ( ! is_resource( $worker ) ) { throw new RuntimeException( 'Cannot start native mobile-loader worker.' ); }
	fclose( $pipes[0] );
	$output = stream_get_contents( $pipes[1] );
	$error = stream_get_contents( $pipes[2] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );
	if ( 0 !== proc_close( $worker ) || '' !== $error ) { throw new RuntimeException( 'Native mobile-loader worker failed: ' . $error . $output ); }
	return $output;
};
$token = 'noobrocket-mobile-' . bin2hex( random_bytes( 8 ) );
$mu_directory = WP_CONTENT_DIR . '/mu-plugins';
wp_mkdir_p( $mu_directory );
$mu_file = $mu_directory . '/' . $token . '.php';
$dropin = WP_CONTENT_DIR . '/advanced-cache.php';
$original_dropin = file_get_contents( $dropin );
$original_blog = get_current_blog_id();
$sites = $directories = [];
$sample_code = '<?php if (getenv("NOOBROCKET_MOBILE_EARLY_SAMPLE") === ' . var_export( $token, true ) . ') { echo json_encode(["sample" => ' . var_export( $token, true ) . ', "detector" => class_exists("NoobRocket\\Dependencies\\Detection\\MobileDetect", false)]); exit; }';
file_put_contents( $mu_file, $sample_code );
add_filter( 'pre_wp_mail', '__return_true' );
try {
	foreach ( [ 'enabled', 'disabled' ] as $name ) {
		$id = wp_insert_site( [ 'domain' => get_network()->domain, 'path' => trailingslashit( get_network()->path ) . $token . '-' . $name . '/', 'network_id' => get_current_network_id() ] );
		if ( is_wp_error( $id ) || $id <= 0 ) { throw new RuntimeException( 'Cannot create owned network site: ' . $name ); }
		$sites[ $name ] = $id;
		$home = parse_url( get_home_url( $id, '/' ) );
		$directories[] = NOOBROCKET_CACHE_PATH . $home['host'] . strtolower( $home['path'] );
		$check( wp_is_site_initialized( $id ), 'owned network site initialized: ' . $name );
	}
	foreach ( [ 'enabled' => 1, 'disabled' => 0 ] as $name => $mobile ) {
		$saved = json_decode( $run( [ $wordpress, '--save', (string) $sites[ $name ], (string) $mobile, get_home_url( $sites[ $name ], '/' ) ] ), true );
		$check( [ 'saved' => $sites[ $name ], 'mobile' => $mobile ] === $saved, 'registered native save completes in fresh site context: ' . $name );
	}
	$content = file_get_contents( $dropin );
	$check( false !== strpos( $content, 'Detection/MobileDetect.php' ), 'mobile-disabled sibling save retains shared network detector block' );
	foreach ( [ 'enabled' => true, 'disabled' => false ] as $name => $detector ) {
		$url = get_home_url( $sites[ $name ], '/owned-cache/' );
		$result = json_decode( $run( [ $wordpress, '--sample', $url, 'phone', $token ] ), true );
		$check( [ 'sample' => $token, 'detector' => $detector ] === $result, 'native request loads detector before plugins only for selected mobile policy: ' . $name );
		$parts = parse_url( $url );
		$directory = NOOBROCKET_CACHE_PATH . $parts['host'] . strtolower( $parts['path'] );
		wp_mkdir_p( $directory );
		$suffix = 'https' === $parts['scheme'] ? '-https' : '';
		$desktop_bytes = $token . '-' . $name . '-desktop';
		$mobile_bytes = $token . '-' . $name . '-mobile';
		file_put_contents( $directory . 'index' . $suffix . '.html', $desktop_bytes );
		file_put_contents( $directory . 'index-mobile' . $suffix . '.html', $mobile_bytes );
		$check( $desktop_bytes === $run( [ $wordpress, '--cache', $url, 'desktop' ] ), 'native early desktop hit selects owned site cache: ' . $name );
		if ( $detector ) { $check( $mobile_bytes === $run( [ $wordpress, '--cache', $url, 'phone' ] ), 'native early mobile hit retains distinct variant after sibling disabled save' ); }
	}
	$check( $original_blog === get_current_blog_id(), 'native site operations preserve original blog context' );
} finally {
	remove_filter( 'pre_wp_mail', '__return_true' );
	unlink( $mu_file );
	foreach ( $directories as $directory ) {
		if ( ! is_dir( $directory ) ) { continue; }
		$resolved = str_replace( '\\', '/', realpath( $directory ) );
		$root = trailingslashit( str_replace( '\\', '/', realpath( NOOBROCKET_CACHE_PATH ) ) );
		if ( 0 !== strpos( $resolved, $root ) || false === strpos( $resolved, $token ) ) { throw new RuntimeException( 'Owned mobile cache cleanup containment failed.' ); }
		foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST ) as $file ) { $file->isDir() ? rmdir( $file->getPathname() ) : unlink( $file->getPathname() ); }
		rmdir( $directory );
	}
	require_once ABSPATH . 'wp-admin/includes/ms.php';
	foreach ( $sites as $id ) {
		switch_to_blog( $id );
		try { noobrocket_delete_config_file(); } finally { restore_current_blog(); }
		wpmu_delete_blog( $id, true );
	}
	if ( false === file_put_contents( $dropin, $original_dropin ) ) { throw new RuntimeException( 'Could not restore owned shared loader.' ); }
}
$check( $debug_before === ( is_file( $debug_file ) ? file_get_contents( $debug_file ) : '' ), 'native loader/save/site cleanup adds no WordPress database or PHP diagnostics' );
$completed = true;
echo 'PASS native multisite mobile loader: ', $checks, " checks\n";
