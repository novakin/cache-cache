<?php
/** Native revision pruning/autosaves and parent invalidation on a marked copied-plugin site. */
$wordpress = isset( $argv[1] ) ? realpath( $argv[1] ) : false;
if ( PHP_SAPI !== 'cli' || ! $wordpress || ! is_file( $wordpress . '/wp-load.php' ) || ! is_file( $wordpress . '/.noobrocket-acceptance-site' ) ) {
	fwrite( STDERR, "Provide a marked disposable WordPress installation with a copied plugin.\n" );
	exit( 2 );
}
$source = dirname( __DIR__ ) . '/noobrocket';
$installed = $wordpress . '/wp-content/plugins/noobrocket';
$normalize = static function ( $path ) { return str_replace( '\\', '/', $path ); };
if ( ! realpath( $installed ) || $normalize( realpath( $installed ) ) !== $normalize( $installed ) || realpath( $source ) === realpath( $installed ) ) {
	fwrite( STDERR, "The disposable site must contain a real plugin copy, not a source junction.\n" );
	exit( 2 );
}
foreach ( [
	'inc/Engine/Common/PerformanceHints/Admin/Controller.php', 'inc/Engine/Preload/Subscriber.php',
	'inc/Engine/Optimization/LocalCSS/Subscriber.php', 'inc/Engine/Optimization/RUCSS/Admin/Subscriber.php',
] as $file ) {
	if ( hash_file( 'sha256', $source . '/' . $file ) !== hash_file( 'sha256', $installed . '/' . $file ) ) {
		fwrite( STDERR, "Copy the current invalidation owners into the disposable plugin before this check.\n" );
		exit( 2 );
	}
}
$temporary = $wordpress . '/wp-content/cache-cache-revision-fixture-' . bin2hex( random_bytes( 8 ) );
if ( ! mkdir( $temporary ) ) { throw new RuntimeException( 'Cannot create the owned revision fixture directory.' ); }
define( 'NOOBROCKET_CACHE_ROOT_PATH', $temporary . '/cache/' );
define( 'NOOBROCKET_CONFIG_PATH', $temporary . '/config/' );
define( 'WP_ADMIN', true );
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
$_SERVER['SERVER_PORT'] = parse_url( 'http://' . $_SERVER['HTTP_HOST'], PHP_URL_PORT ) ?: 80;
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'POST';
require_once $wordpress . '/wp-includes/plugin.php';
$blocked = 0;
$block_http = static function () use ( &$blocked ) {
	++$blocked;
	return new WP_Error( 'revision_fixture_http', 'Unowned HTTP is blocked by the revision fixture.' );
};
add_filter( 'pre_http_request', $block_http, PHP_INT_MAX - 1 );
add_filter( 'action_scheduler_allow_async_request_runner', '__return_false' );
add_filter( 'noobrocket_generate_advanced_cache_file', '__return_false' );
add_filter( 'noobrocket_disable_htaccess', '__return_true' );
add_filter( 'pre_get_noobrocket_option_manual_preload', '__return_true' );
add_filter( 'pre_get_noobrocket_option_auto_preload_fonts', '__return_true' );
add_filter( 'pre_get_noobrocket_option_noobrocket_no_licence', '__return_false' );
$no_licence = static function () { return 0; };
add_filter( 'pre_option_noobrocket_no_licence', $no_licence );
$permalinks = static function () { return '/%postname%/'; };
add_filter( 'pre_option_permalink_structure', $permalinks );
$keep_revisions = static function () { return 2; };
add_filter( 'wp_revisions_to_keep', $keep_revisions );

$checks = 0;
$posts = [];
$snapshots = [];
$tables = [];
$revision_scope = false;
$revision_queries = [];
$revision_links = [];
$revision_hooks = 0;
$pruned = [];
$scope_start = static function ( $id, $post = null ) use ( &$revision_scope, &$revision_hooks ) {
	$post = $post instanceof WP_Post ? $post : get_post( $id );
	$revision_scope = $post && 'revision' === $post->post_type;
	if ( $revision_scope ) { ++$revision_hooks; }
};
$scope_end = static function () use ( &$revision_scope ) { $revision_scope = false; };
$query_probe = static function ( $sql ) use ( &$revision_scope, &$revision_queries, &$tables ) {
	if ( $revision_scope ) {
		foreach ( $tables as $table ) {
			if ( false !== strpos( $sql, $table ) ) { $revision_queries[] = $table; break; }
		}
	}
	return $sql;
};
$link_probe = static function ( $url, $post ) use ( &$revision_scope, &$revision_links ) {
	if ( $revision_scope ) { $revision_links[] = $post instanceof WP_Post ? $post->ID : $post; }
	return $url;
};
$prune_probe = static function ( $id, $post ) use ( &$pruned ) { $pruned[ $id ] = $post->post_parent; };
$check = static function ( $condition, $message ) use ( &$checks ) {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
	++$checks;
};
$tracked_hooks = [ 'pre_post_update', 'post_updated', 'before_delete_post', 'delete_post', 'clean_post_cache' ];
$revision_fixture_completed = false;
register_shutdown_function( static function () use ( &$revision_fixture_completed ) {
	if ( ! $revision_fixture_completed ) {
		fwrite( STDERR, "FAIL: native revision fixture did not complete.\n" );
		exit( 2 );
	}
} );

try {
	require $wordpress . '/wp-load.php';
	ini_set( 'display_errors', 'stderr' );
	register_shutdown_function( static function () use ( &$blocked ) {
		if ( $blocked ) { throw new RuntimeException( 'Revision fixture blocked an unexpected HTTP request.' ); }
	} );
	wp_set_current_user( 1 );
	$check( $normalize( realpath( WP_CONTENT_DIR ) ) === $normalize( $wordpress . '/wp-content' ), 'fixture paths stay inside the disposable site content directory' );
	$rewrite_before = clone $GLOBALS['wp_rewrite'];
	$GLOBALS['wp_rewrite']->init();
	$container = apply_filters( 'noobrocket_container', null );
	$hints = $container->get( 'performance_hints_admin_subscriber' );
	$preload = $container->get( 'preload_subscriber' );
	$local_css = $container->get( 'local_css_subscriber' );
	$check( false !== has_action( 'delete_post', [ $hints, 'delete_post' ] ) && false !== has_action( 'delete_post', [ $preload, 'delete_post_preload_cache' ] ), 'native hint/preload deletion callbacks are registered' );
	$check( false !== has_action( 'before_delete_post', [ $local_css, 'invalidate_post' ] ) && false !== has_action( 'pre_post_update', [ $local_css, 'remember_post_url' ] ), 'native local CSS deletion/update callbacks are registered' );
	$factories = [];
	foreach ( [ 'atf_factory', 'lrc_factory', 'preload_fonts_factory', 'preconnect_external_domains_factory' ] as $name ) {
		$factory = $container->get( $name );
		$check( $factory->get_context()->is_allowed() && $factory->table()->exists(), $name . ': enabled native context/table' );
		$factories[ $name ] = $factory;
		$tables[] = $factory->table()->get_name();
	}
	$preload_table = $wpdb->prefix . 'nbr_noobrocket_cache';
	$css_table = $container->get( 'rucss_usedcss_table' )->get_name();
	$tables[] = $preload_table;
	$tables[] = $css_table;
	foreach ( $tables as $table ) { $snapshots[ $table ] = $wpdb->get_results( "SELECT * FROM $table", OBJECT_K ); }
	$jobs = $container->get( 'local_css_jobs' );
	$query = $container->get( 'preload_caches_query' );
	$cache_files = [];
	$seed = static function ( $url, $hint_rows = true ) use ( $factories, $jobs, $query, &$cache_files, $check ) {
		if ( $hint_rows ) {
			foreach ( $factories as $name => $factory ) {
				// Collection writes use another query owner, not the admin's cached lookup.
				$query_class = get_class( $factory->queries() );
				$seed_query = new $query_class();
				$check( false !== $seed_query->add_item( [
					'url' => untrailingslashit( $url ), 'status' => 'completed', 'modified' => current_time( 'mysql', true ), 'last_accessed' => current_time( 'mysql', true ),
				] ), $name . ': seed owned completed hint row' );
			}
		}
		$check( $jobs->enqueue( $url, false, 'used', md5( $url ), [ [ 'href' => home_url( '/revision-fixture.css' ) ] ], [] ), 'seed owned local CSS job' );
		$check( false !== $query->create_or_update( [ 'url' => $url, 'status' => 'completed' ] ), 'seed owned preload row' );
		$parsed = get_noobrocket_parse_url( $url );
		$file = NOOBROCKET_CACHE_PATH . $parsed['host'] . rtrim( $parsed['path'], '/' ) . '/index.html';
		wp_mkdir_p( dirname( $file ) );
		file_put_contents( $file, 'Owned revision fixture cache' );
		$cache_files[ $url ] = $file;
		_noobrocket_get_cache_dirs( '', '', true );
	};
	$rows_for = static function ( $table, $url ) {
		return (int) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( "SELECT COUNT(*) FROM $table WHERE url = %s", untrailingslashit( $url ) ) );
	};
	$prefix = 'nbr-revision-' . bin2hex( random_bytes( 6 ) );
	add_filter( 'noobrocket_is_importing', '__return_true' );
	foreach ( [ 'update', 'delete' ] as $name ) {
		$posts[ $name ] = wp_insert_post( [ 'post_title' => $prefix . '-' . $name, 'post_name' => $prefix . '-' . $name, 'post_content' => 'Initial revision fixture content.', 'post_status' => 'publish' ], true );
		$check( ! is_wp_error( $posts[ $name ] ) && $posts[ $name ] > 0, 'create owned published ' . $name . ' parent' );
	}
	remove_filter( 'noobrocket_is_importing', '__return_true' );
	$old_url = get_permalink( $posts['update'] );
	$new_slug = $prefix . '-renamed';
	$new_url = home_url( '/' . $new_slug . '/' );
	$delete_url = get_permalink( $posts['delete'] );
	$unrelated_url = home_url( '/' . $prefix . '-unrelated/' );
	$seed( $old_url, false );
	$seed( $new_url );
	$seed( $delete_url );
	$seed( $unrelated_url );
	foreach ( $factories as $name => $factory ) {
		$check( ! empty( $factory->queries()->get_rows_by_url( untrailingslashit( $delete_url ) ) ), $name . ': registered admin query sees the seeded deletion row' );
	}
	foreach ( $tracked_hooks as $hook ) {
		add_action( $hook, $scope_start, -100, 2 );
		add_action( $hook, $scope_end, PHP_INT_MAX );
	}
	add_filter( 'query', $query_probe );
	foreach ( [ 'post_link', 'post_type_link', 'page_link' ] as $hook ) { add_filter( $hook, $link_probe, PHP_INT_MAX, 2 ); }
	add_action( 'wp_delete_post_revision', $prune_probe, 10, 2 );

	$check( wp_update_post( [ 'ID' => $posts['update'], 'post_name' => $new_slug, 'post_content' => 'Updated parent content 1.' ], true ) === $posts['update'], 'native parent update succeeds' );
	$check( get_permalink( $posts['update'] ) === $new_url, 'native slug update reaches the expected new URL' );
	clearstatcache();
	$check( ! is_file( $cache_files[ $old_url ] ) && ! is_file( $cache_files[ $new_url ] ), 'parent update still purges old/current disk page cache' );
	foreach ( $factories as $name => $factory ) { $check( 0 === $rows_for( $factory->table()->get_name(), $new_url ), $name . ': parent update removes hint row' ); }
	$check( 0 === $rows_for( $css_table, $old_url ) && 0 === $rows_for( $css_table, $new_url ), 'parent update removes old/current local CSS jobs' );
	$updated_rows = $query->get_rows_by_url( $new_url );
	$check( $updated_rows && 'pending' === $updated_rows[0]->status, 'parent update still requeues its preload URL' );

	// wp_save_post_revision runs through actual update hooks and enforces the native keep limit.
	for ( $number = 2; $number <= 6; ++$number ) {
		$check( wp_update_post( [ 'ID' => $posts['update'], 'post_content' => 'Updated parent content ' . $number . '.' ], true ) === $posts['update'], 'native parent update creates revision ' . $number );
	}
	$check( count( $pruned ) >= 3 && 2 === count( wp_get_post_revisions( $posts['update'] ) ), 'actual core pruning deletes old revision rows and retains the configured two' );
	foreach ( array_keys( $pruned ) as $id ) { $check( null === get_post( $id ), 'pruned revision row is gone: ' . $id ); }
	$check( null !== get_post( $posts['update'] ), 'revision pruning retains published parent row' );
	$autosave = _wp_put_post_revision( $posts['update'], true );
	$check( ! is_wp_error( $autosave ) && $autosave > 0 && wp_is_post_autosave( $autosave ) === $posts['update'], 'native autosave is a revision belonging to the parent' );
	$check( wp_update_post( [ 'ID' => $autosave, 'post_content' => 'Changed autosave content.' ], true ) === $autosave, 'native autosave update exercises local CSS capture/invalidation hooks' );
	$check( wp_delete_post_revision( $autosave ) instanceof WP_Post, 'native autosave deletion succeeds' );
	$check( $revision_hooks > 0 && [] === $revision_queries, 'revision/autosave callback hooks execute without hint/preload/local CSS table queries' );
	$check( [] === $revision_links, 'revision/autosave callbacks generate no permalink filter output' );

	$check( wp_delete_post( $posts['delete'], true ) instanceof WP_Post && null === get_post( $posts['delete'] ), 'regular parent deletion removes its row' );
	clearstatcache();
	$check( ! is_file( $cache_files[ $delete_url ] ), 'regular parent deletion still purges disk page cache' );
	foreach ( $factories as $name => $factory ) { $check( 0 === $rows_for( $factory->table()->get_name(), $delete_url ), $name . ': parent deletion removes hint row' ); }
	$check( 0 === $rows_for( $css_table, $delete_url ) && 0 === $rows_for( $preload_table, $delete_url ), 'parent deletion removes local CSS/preload rows' );
	foreach ( $tables as $table ) { $check( 1 === $rows_for( $table, $unrelated_url ), 'unrelated owned control row remains: ' . $table ); }
	$check( is_file( $cache_files[ $unrelated_url ] ), 'unrelated owned page-cache file remains' );
	echo 'PASS: ', $checks, ' native revision/autosave and parent-invalidation assertions; ', count( $pruned ), ' actual revision rows deleted; ', $revision_hooks, ' revision hook callbacks monitored.', PHP_EOL;
} finally {
	foreach ( $tracked_hooks as $hook ) { remove_action( $hook, $scope_start, -100 ); remove_action( $hook, $scope_end, PHP_INT_MAX ); }
	remove_filter( 'query', $query_probe );
	foreach ( [ 'post_link', 'post_type_link', 'page_link' ] as $hook ) { remove_filter( $hook, $link_probe, PHP_INT_MAX ); }
	remove_action( 'wp_delete_post_revision', $prune_probe );
	if ( function_exists( 'wp_delete_post' ) ) {
		add_filter( 'noobrocket_is_importing', '__return_true' );
		foreach ( $posts as $id ) { if ( ! is_wp_error( $id ) ) { wp_delete_post( $id, true ); } }
		foreach ( $snapshots as $table => $rows ) {
			foreach ( $wpdb->get_col( "SELECT id FROM $table" ) as $id ) { if ( ! isset( $rows[ $id ] ) ) { $wpdb->delete( $table, [ 'id' => $id ] ); } }
			foreach ( $rows as $row ) { $wpdb->replace( $table, (array) $row ); }
		}
		if ( isset( $rewrite_before ) ) { $GLOBALS['wp_rewrite'] = $rewrite_before; }
	}
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $temporary, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $iterator as $file ) { if ( $file->isDir() ) { rmdir( $file->getPathname() ); } else { unlink( $file->getPathname() ); } }
	rmdir( $temporary );
}
$revision_fixture_completed = true;
