<?php
/** Native theme-update hint invalidation on a marked copied-plugin installation. */
$wordpress = isset( $argv[1] ) ? realpath( $argv[1] ) : false;
if ( PHP_SAPI !== 'cli' || ! $wordpress || ! is_file( $wordpress . '/.noobrocket-acceptance-site' ) ) {
	throw new RuntimeException( 'Provide a marked disposable WordPress installation.' );
}
$candidate = dirname( __DIR__ ) . '/noobrocket';
$installed = $wordpress . '/wp-content/plugins/noobrocket';
$normalize = static function ( $path ) { return str_replace( '\\', '/', $path ); };
if ( ! realpath( $installed ) || $normalize( realpath( $installed ) ) !== $normalize( $installed ) || realpath( $candidate ) === realpath( $installed ) ) {
	throw new RuntimeException( 'Use a copied plugin in the disposable site.' );
}
foreach ( [ 'inc/common/purge.php', 'inc/Engine/Common/PerformanceHints/Admin/Subscriber.php', 'inc/Engine/Common/PerformanceHints/Admin/Controller.php' ] as $owner ) {
	if ( hash_file( 'sha256', $candidate . '/' . $owner ) !== hash_file( 'sha256', $installed . '/' . $owner ) ) {
		throw new RuntimeException( 'Copied theme-update owner differs from candidate.' );
	}
}
$content = realpath( $wordpress . '/wp-content' );
$name = 'noobrocket-theme-update-' . bin2hex( random_bytes( 8 ) );
$temporary = $content . '/' . $name;
if ( ! mkdir( $temporary ) ) { throw new RuntimeException( 'Cannot create owned fixture directory.' ); }
define( 'NOOBROCKET_CACHE_ROOT_PATH', $temporary . '/cache/' );
define( 'NOOBROCKET_CONFIG_PATH', $temporary . '/config/' );
define( 'WP_ADMIN', true );
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'POST';
require_once $wordpress . '/wp-includes/plugin.php';
$blocked = 0;
add_filter( 'pre_http_request', static function () use ( &$blocked ) {
	++$blocked;
	return new WP_Error( 'theme_update_fixture_http', 'HTTP is blocked by the isolated theme-update fixture.' );
}, PHP_INT_MAX - 1 );
add_filter( 'enable_loading_advanced_cache_dropin', static function () { return false; } );
add_filter( 'action_scheduler_allow_async_request_runner', '__return_false' );
add_filter( 'noobrocket_generate_advanced_cache_file', '__return_false' );
add_filter( 'noobrocket_disable_htaccess', '__return_true' );
require $wordpress . '/wp-load.php';
ini_set( 'display_errors', 'stderr' );
wp_set_current_user( 1 );
$container = apply_filters( 'noobrocket_container', null );
if ( ! $container ) { throw new RuntimeException( 'Native Cache-Cache container missing.' ); }
$hints = $container->get( 'performance_hints_admin_subscriber' );
$checks = 0;
$check = static function ( $condition, $message ) use ( &$checks ) {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
	++$checks;
};
$check( 10 === has_action( 'upgrader_process_complete', [ $hints, 'truncate_on_theme_update' ] ), 'native hint updater callback registered' );
$check( 10 === has_action( 'upgrader_process_complete', 'noobrocket_clean_cache_theme_update' ), 'existing page-cache callback remains registered' );
$factories = [];
$backups = [];
foreach ( [ 'atf_factory', 'lrc_factory', 'preload_fonts_factory', 'preconnect_external_domains_factory' ] as $factory_name ) {
	$factory = $container->get( $factory_name );
	$check( $factory->table()->exists(), $factory_name . ': native hint table exists' );
	$factories[ $factory_name ] = $factory;
	$table = $factory->table()->get_name();
	$backups[ $table ] = $wpdb->get_results( "SELECT * FROM $table", ARRAY_A );
	$check( is_array( $backups[ $table ] ), $factory_name . ': native rows backed up' );
}
$old_theme = get_stylesheet();
$parent = $name . '-parent';
$child = $name . '-child';
$owned_themes = [ $content . '/themes/' . $parent, $content . '/themes/' . $child ];
foreach ( $owned_themes as $directory ) {
	$check( ! file_exists( $directory ) && mkdir( $directory ), 'new owned theme directory' );
}
file_put_contents( $owned_themes[0] . '/style.css', "/*\nTheme Name: Owned Theme Update Parent\n*/\n" );
file_put_contents( $owned_themes[0] . '/index.php', "<?php echo 'Owned theme update fixture';\n" );
file_put_contents( $owned_themes[1] . '/style.css', "/*\nTheme Name: Owned Theme Update Child\nTemplate: $parent\n*/\n" );
wp_clean_themes_cache();
$url = untrailingslashit( home_url( '/' . $name . '/' ) );
$pending_url = $url . '-pending';
$seed = static function () use ( $factories, $url, $pending_url, $wpdb, $check ) {
	foreach ( $factories as $factory_name => $factory ) {
		$table = $factory->table()->get_name();
		$wpdb->delete( $table, [ 'url' => $url ] );
		$wpdb->delete( $table, [ 'url' => $pending_url ] );
		$query_class = get_class( $factory->queries() );
		$query = new $query_class();
		foreach ( [ $url => 'completed', $pending_url => 'pending' ] as $row_url => $status ) {
			$check( false !== $query->add_item( [ 'url' => $row_url, 'status' => $status, 'modified' => current_time( 'mysql', true ), 'last_accessed' => current_time( 'mysql', true ) ] ), $factory_name . ': seed native ' . $status . ' row' );
		}
	}
};
$counts = static function ( $row_url ) use ( $factories, $wpdb ) {
	$counts = [];
	foreach ( $factories as $factory ) {
		$table = $factory->table()->get_name();
		$counts[] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE url=%s", $row_url ) );
	}
	return array_values( array_unique( $counts ) );
};
try {
	switch_theme( $child );
	$check( $child === get_stylesheet() && $parent === get_template(), 'native active child and parent selected' );
	foreach ( [
		[ [ 'action' => 'update', 'type' => 'theme', 'themes' => [ $old_theme ] ], 1 ],
		[ [ 'action' => 'update', 'type' => 'plugin', 'themes' => [ $child ] ], 1 ],
		[ [ 'action' => 'install', 'type' => 'theme', 'themes' => [ $child ] ], 1 ],
		[ [ 'action' => 'update', 'type' => 'theme', 'themes' => [ $child ] ], 0 ],
		[ [ 'action' => 'update', 'type' => 'theme', 'themes' => [ $parent ] ], 0 ],
		[ [ 'action' => 'update', 'type' => 'theme', 'themes' => [ $old_theme, $child, $parent ] ], 0 ],
	] as $case ) {
		$seed();
		$blog = get_current_blog_id();
		$stack = $GLOBALS['_wp_switched_stack'] ?? [];
		do_action( 'upgrader_process_complete', null, $case[0] );
		$check( [ $case[1] ] === $counts( $url ), 'theme update clears exactly the applicable completed hints' );
		$check( [ 1 ] === $counts( $pending_url ), 'pending hint work survives update invalidation' );
		$check( $blog === get_current_blog_id() && $stack === ( $GLOBALS['_wp_switched_stack'] ?? [] ), 'native blog context is preserved' );
	}
	$seed();
	switch_theme( $parent );
	$check( [ 0 ] === $counts( $url ) && [ 1 ] === $counts( $pending_url ), 'existing switch-theme invalidation retains pending work' );
	$check( 0 === $blocked, 'fixture made no HTTP requests' );
} finally {
	switch_theme( $old_theme );
	foreach ( $backups as $table => $rows ) {
		$check( false !== $wpdb->query( "DELETE FROM $table" ), 'native hint restoration clears fixture rows' );
		foreach ( $rows as $row ) { $check( false !== $wpdb->insert( $table, $row ), 'native hint snapshot restored' ); }
		$check( $rows === $wpdb->get_results( "SELECT * FROM $table", ARRAY_A ), 'native hint restoration readback matches snapshot' );
	}
	$check( $old_theme === get_stylesheet(), 'native active theme restored' );
	foreach ( $owned_themes as $directory ) {
		$check( dirname( realpath( $directory ) ) === realpath( $content . '/themes' ), 'owned theme cleanup containment' );
		foreach ( [ 'style.css', 'index.php' ] as $file ) { if ( is_file( $directory . '/' . $file ) ) { unlink( $directory . '/' . $file ); } }
		rmdir( $directory );
	}
	wp_clean_themes_cache();
	$check( realpath( $temporary ) === $temporary && dirname( $temporary ) === $content, 'owned cache/config cleanup containment' );
	noobrocket_rrmdir( $temporary );
}
echo "PASS: $checks native theme-update hint assertions; completed/pending rows, theme scope and context.\n";
