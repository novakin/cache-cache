<?php
/** Native config generation/pruning in an owned temporary config root; no listener. */
$wordpress = isset( $argv[1] ) ? realpath( $argv[1] ) : false;
if ( PHP_SAPI !== 'cli' || ! $wordpress || ! is_file( $wordpress . '/.noobrocket-acceptance-site' ) ) { throw new RuntimeException( 'Provide a marked disposable copied-plugin site.' ); }
$source = dirname( __DIR__ ) . '/noobrocket/';
$installed = $wordpress . '/wp-content/plugins/noobrocket/';
if ( ! realpath( $installed ) || realpath( $source ) === realpath( $installed ) || str_replace( '\\', '/', realpath( $installed ) ) !== str_replace( '\\', '/', rtrim( $installed, '/' ) ) ) { throw new RuntimeException( 'Native config retention requires a real separate plugin copy.' ); }
foreach ( [ 'inc/Engine/Cache/AdminSubscriber.php', 'inc/functions/i18n.php', 'inc/functions/files.php' ] as $file ) {
	if ( hash_file( 'sha256', $source . $file ) !== hash_file( 'sha256', $installed . $file ) ) { throw new RuntimeException( 'Installed config retention owner differs from candidate.' ); }
}
$phase = $argv[2] ?? '';
$worker = in_array( $phase, [ '--activate', '--prepare' ], true );
$token = $worker ? $argv[6] : 'noobrocket-alias-' . bin2hex( random_bytes( 8 ) );
$owned = $worker ? $argv[5] : $wordpress . '/wp-content/' . $token;
if ( ! $worker && ! mkdir( $owned ) ) { throw new RuntimeException( 'Cannot create owned config root.' ); }
if ( realpath( dirname( $owned ) ) !== realpath( $wordpress . '/wp-content' ) || basename( $owned ) !== $token || ! preg_match( '/^noobrocket-alias-[a-f0-9]{16}$/', $token ) ) { throw new RuntimeException( 'Owned config root containment failed.' ); }
define( 'NOOBROCKET_CONFIG_PATH', str_replace( '\\', '/', $owned ) . '/' );
define( 'WP_ADMIN', true );
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
$_SERVER['SERVER_PORT'] = parse_url( 'http://' . $_SERVER['HTTP_HOST'], PHP_URL_PORT ) ?: 80;
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'POST';
if ( $worker ) {
	$target = parse_url( $argv[4] );
	$_SERVER['HTTP_HOST'] = $target['host'] . ( isset( $target['port'] ) ? ':' . $target['port'] : '' );
	$_SERVER['SERVER_PORT'] = $target['port'] ?? 80;
	$_SERVER['REQUEST_URI'] = $target['path'];
}
require_once $wordpress . '/wp-includes/plugin.php';
add_filter( 'noobrocket_generate_advanced_cache_file', '__return_false' );
add_filter( 'noobrocket_disable_htaccess', '__return_true' );
add_filter( 'action_scheduler_allow_async_request_runner', '__return_false' );
$root_id = $worker ? (int) $argv[7] : 0;
$replace = false;
$has_i18n = static function () use ( &$root_id ) { return get_current_blog_id() === $root_id ? 'config-retention-fixture' : ''; };
$language_urls = static function () use ( $token ) { return [ home_url(), 'https://fr-' . $token . '.example.test' ]; };
$path_filter = static function ( $paths ) use ( $token, &$replace, &$root_id ) {
	$integration = NOOBROCKET_CONFIG_PATH . 'integration-' . $token . '-' . get_current_blog_id() . '.php';
	return $replace && get_current_blog_id() === $root_id ? [ $integration ] : array_merge( $paths, [ $integration ] );
};
add_filter( 'noobrocket_has_i18n', $has_i18n );
add_filter( 'noobrocket_i18n_subdomains', $language_urls );
add_filter( 'noobrocket_config_files_path', $path_filter );
$completed = false;
register_shutdown_function( static function () use ( &$completed ) {
	if ( ! $completed ) {
		$error = error_get_last();
		if ( $error && in_array( $error['type'], [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ], true ) ) { fwrite( STDERR, $error['message'] . "\n" ); }
		fwrite( STDERR, "FAIL: native config retention fixture did not complete.\n" );
		exit( 2 );
	}
} );
$debug_file = $wordpress . '/wp-content/debug.log';
$debug_before = is_file( $debug_file ) ? file_get_contents( $debug_file ) : '';
require $wordpress . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
wp_set_current_user( 1 );
// Initialize the plugin's real settings callbacks without unrelated core update HTTP.
foreach ( [ '_maybe_update_core', '_maybe_update_plugins', '_maybe_update_themes' ] as $callback ) { remove_action( 'admin_init', $callback ); }
if ( $worker ) {
	if ( get_current_blog_id() !== (int) $argv[3] ) { throw new RuntimeException( 'Fresh worker selected the wrong owned site.' ); }
	$result = activate_plugin( 'noobrocket/noobrocket.php', '', false );
	if ( is_wp_error( $result ) || ! is_plugin_active( 'noobrocket/noobrocket.php' ) ) { throw new RuntimeException( 'Owned sibling plugin activation failed.' ); }
	if ( '--activate' === $phase ) {
		echo json_encode( [ 'activated' => get_current_blog_id() ] ), PHP_EOL;
		$completed = true;
		exit;
	}
	do_action( 'admin_init' );
	$options = get_option( 'noobrocket_settings' );
	update_option( 'noobrocket_settings', array_merge( $options, [ 'manual_preload' => 0 ] ) );
	noobrocket_generate_config_file();
	echo json_encode( [ 'prepared' => get_current_blog_id(), 'site_active' => in_array( 'noobrocket/noobrocket.php', get_option( 'active_plugins', [] ), true ) ] ), PHP_EOL;
	$completed = true;
	exit;
}
$root_id = get_current_blog_id();
if ( ! is_plugin_active( 'noobrocket/noobrocket.php' ) || ( 'multisite' === $phase && ! is_multisite() ) ) { throw new RuntimeException( 'Native config retention requires an active plugin and requested network context.' ); }
do_action( 'admin_init' );
$checks = 0;
$check = static function ( $condition, $message ) use ( &$checks ) {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
	++$checks;
};
$run = static function ( array $args ) {
	$process = proc_open( array_merge( [ PHP_BINARY, __FILE__ ], $args ), [ 0 => [ 'pipe', 'r' ], 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ], $pipes );
	if ( ! is_resource( $process ) ) { throw new RuntimeException( 'Cannot start fresh native config worker.' ); }
	fclose( $pipes[0] );
	$output = stream_get_contents( $pipes[1] ); $error = stream_get_contents( $pipes[2] );
	fclose( $pipes[1] ); fclose( $pipes[2] );
	if ( 0 !== proc_close( $process ) || '' !== $error ) { throw new RuntimeException( 'Native config worker failed: ' . $error . $output ); }
	return json_decode( $output, true );
};
$subscriber = apply_filters( 'noobrocket_container', null )->get( 'admin_cache_subscriber' );
$original_blog = get_current_blog_id();
$original_stack = $GLOBALS['_wp_switched_stack'] ?? [];
$sites = [];
$alias = NOOBROCKET_CONFIG_PATH . 'fr-' . $token . '.example.test.php';
$integration = NOOBROCKET_CONFIG_PATH . 'integration-' . $token . '-' . $root_id . '.php';
$owned_bytes = "<?php\n\$noobrocket_cookie_hash = 'owned-stale';\n";
add_filter( 'pre_wp_mail', '__return_true' );
try {
	$q_config_exists = array_key_exists( 'q_config', $GLOBALS );
	$original_q_config = $GLOBALS['q_config'] ?? null;
	$lang_exists = array_key_exists( 'lang', $_GET );
	$original_lang = $_GET['lang'] ?? null;
	try {
		unset( $_GET['lang'] );
		$GLOBALS['q_config'] = [ 'enabled_languages' => [ 'fr' ], 'language_name' => [ 'fr' => 'Français' ], 'flag_location' => 'flags/', 'flag' => [ 'fr' => 'fr.png' ] ];
		$flags = get_noobrocket_qtranslate_langs_for_admin_bar();
		$expected_flag = '<img src="' . esc_url( trailingslashit( WP_CONTENT_URL ) . 'flags/fr.png' ) . '" alt="Français" width="18" height="12" />';
		$check( $expected_flag === $flags['fr']['flag'], 'native content URL owner preserves ordinary qTranslate flag output' );
	} finally {
		if ( $q_config_exists ) { $GLOBALS['q_config'] = $original_q_config; } else { unset( $GLOBALS['q_config'] ); }
		if ( $lang_exists ) { $_GET['lang'] = $original_lang; } else { unset( $_GET['lang'] ); }
	}
	if ( 'multisite' === $phase ) {
		foreach ( [ 'sibling', 'dormant' ] as $name ) {
			$id = wp_insert_site( [ 'domain' => get_network()->domain, 'path' => trailingslashit( get_network()->path ) . $token . '-' . $name . '/', 'network_id' => get_current_network_id() ] );
			if ( is_wp_error( $id ) || $id <= 0 ) { throw new RuntimeException( 'Cannot create owned native site.' ); }
			$sites[ $name ] = $id;
			$check( wp_is_site_initialized( $id ), 'native owned site initialized: ' . $name );
		}
		$activated = $run( [ $wordpress, '--activate', (string) $sites['sibling'], get_home_url( $sites['sibling'], '/' ), $owned, $token, (string) $root_id ] );
		$check( [ 'activated' => $sites['sibling'] ] === $activated, 'native sibling activation completes before its fresh configured request' );
		$prepared = $run( [ $wordpress, '--prepare', (string) $sites['sibling'], get_home_url( $sites['sibling'], '/' ), $owned, $token, (string) $root_id ] );
		$check( is_array( $prepared ) && $prepared['prepared'] === $sites['sibling'] && ( is_plugin_active_for_network( 'noobrocket/noobrocket.php' ) || $prepared['site_active'] ), 'fresh native sibling has its own active plugin/configuration' );
		$check( ! get_blog_option( $sites['dormant'], 'noobrocket_settings' ), 'dormant owned site has no live plugin settings' );
	}
	$subscriber->regenerate_configs();
	$check( is_file( $alias ) && is_file( $integration ), 'native generator writes language and integration aliases before pruning' );
	file_put_contents( NOOBROCKET_CONFIG_PATH . 'stale-owned.php', $owned_bytes );
	file_put_contents( NOOBROCKET_CONFIG_PATH . 'foreign.php', '<?php /* foreign owner */' );
	file_put_contents( NOOBROCKET_CONFIG_PATH . 'owned.txt', $owned_bytes );
	if ( $sites ) { file_put_contents( NOOBROCKET_CONFIG_PATH . '127.0.0.1.' . $token . '-dormant.php', $owned_bytes ); }
	$subscriber->delete_old_configs();
	$check( is_file( $alias ) && is_file( $integration ), 'native pruning retains newly generated language/integration aliases' );
	$check( ! is_file( NOOBROCKET_CONFIG_PATH . 'stale-owned.php' ) && is_file( NOOBROCKET_CONFIG_PATH . 'foreign.php' ) && is_file( NOOBROCKET_CONFIG_PATH . 'owned.txt' ), 'native pruning removes only stale owned PHP files' );
	$replace = true;
	$subscriber->regenerate_configs();
	$subscriber->delete_old_configs();
	$check( is_file( $integration ) && ! is_file( $alias ), 'native replacement-path filter controls complete live set' );
	$replace = false;
	$subscriber->regenerate_configs();
	if ( $sites ) {
		$sibling_home = parse_url( get_home_url( $sites['sibling'], '/' ) );
		$sibling_path = NOOBROCKET_CONFIG_PATH . strtolower( $sibling_home['host'] ) . str_replace( '/', '.', untrailingslashit( $sibling_home['path'] ) ) . '.php';
		$check( is_file( $sibling_path ), 'native sibling canonical policy survives originating home URL memoization' );
		switch_to_blog( $sites['sibling'] );
		try {
			$stack = $GLOBALS['_wp_switched_stack'];
			$subscriber->delete_old_configs();
			$check( is_file( $sibling_path ) && is_file( $alias ) && get_current_blog_id() === $sites['sibling'] && $stack === $GLOBALS['_wp_switched_stack'], 'native shared pruning preserves active sibling, root aliases and exact originating stack' );
			$refusal = static function ( $paths ) use ( $sites ) { if ( get_current_blog_id() === $sites['sibling'] ) { throw new RuntimeException( 'Injected native config-path failure.' ); } return $paths; };
			add_filter( 'noobrocket_config_files_path', $refusal, 99 );
			file_put_contents( NOOBROCKET_CONFIG_PATH . 'failure-stale.php', $owned_bytes );
			try { $subscriber->delete_old_configs(); throw new RuntimeException( 'Native path failure was not propagated.' ); }
			catch ( RuntimeException $error ) { $check( 'Injected native config-path failure.' === $error->getMessage(), 'native integration-path refusal propagates' ); }
			finally { remove_filter( 'noobrocket_config_files_path', $refusal, 99 ); }
			$check( get_current_blog_id() === $sites['sibling'] && $stack === $GLOBALS['_wp_switched_stack'] && is_file( NOOBROCKET_CONFIG_PATH . 'failure-stale.php' ), 'native path failure restores context before stale deletion starts' );
		} finally { restore_current_blog(); }
		$check( ! is_file( NOOBROCKET_CONFIG_PATH . '127.0.0.1.' . $token . '-dormant.php' ), 'native dormant settings-free owned config is removed' );
	}
	$check( $original_blog === get_current_blog_id() && $original_stack === ( $GLOBALS['_wp_switched_stack'] ?? [] ), 'native fixture preserves original blog and stack' );
} finally {
	remove_filter( 'pre_wp_mail', '__return_true' );
	remove_filter( 'noobrocket_has_i18n', $has_i18n );
	remove_filter( 'noobrocket_i18n_subdomains', $language_urls );
	remove_filter( 'noobrocket_config_files_path', $path_filter );
	require_once ABSPATH . 'wp-admin/includes/ms.php';
	foreach ( $sites as $id ) { wpmu_delete_blog( $id, true ); }
	if ( realpath( dirname( $owned ) ) !== realpath( $wordpress . '/wp-content' ) || basename( $owned ) !== $token ) { throw new RuntimeException( 'Owned config cleanup containment failed.' ); }
	foreach ( new DirectoryIterator( $owned ) as $file ) { if ( ! $file->isDot() ) { if ( ! $file->isFile() ) { throw new RuntimeException( 'Unexpected entry in owned config root.' ); } unlink( $file->getPathname() ); } }
	rmdir( $owned );
}
$check( $debug_before === ( is_file( $debug_file ) ? file_get_contents( $debug_file ) : '' ), 'native operations add no WordPress database or PHP diagnostics' );
$completed = true;
echo 'PASS native config alias retention: ', $checks, " checks\n";
