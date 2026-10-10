<?php
/**
 * Real taxonomy relationship invalidation with disposable posts/terms/cache files.
 * php tests/wordpress-taxonomy-cache.php /absolute/path/to/disposable/wordpress [baseline]
 */
$wordpress = isset( $argv[1] ) ? realpath( $argv[1] ) : false;
if ( ! $wordpress || ! is_file( $wordpress . '/wp-load.php' ) || ! is_file( $wordpress . '/.noobrocket-acceptance-site' ) ) {
	fwrite( STDERR, "Provide a disposable WordPress installation with the .noobrocket-acceptance-site marker.\n" );
	exit( 2 );
}
$fixture = tempnam( sys_get_temp_dir(), 'noobrocket-taxonomy-test-' );
unlink( $fixture );
mkdir( $fixture );
define( 'NOOBROCKET_CACHE_ROOT_PATH', $fixture . '/' );
define( 'NOOBROCKET_CONFIG_PATH', $fixture . '/config/' );
define( 'WP_ADMIN', true );
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
putenv( 'NOOBROCKET_TEST_PHASE=taxonomy-cache' );
$_SERVER['HTTP_HOST'] = '127.0.0.1:8765';
$_SERVER['REQUEST_URI'] = '/';
// Bypass early homepage delivery so the term fixtures always execute.
$_SERVER['REQUEST_METHOD'] = 'POST';
require $wordpress . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
wp_set_current_user( 1 );

$checks = [];
$cache_files = [];
$posts = [];
$terms = [];
$container = apply_filters( 'noobrocket_container', null );
$query = $container->get( 'preload_caches_query' );
$subscriber = $container->get( 'purge_actions_subscriber' );
$table = $GLOBALS['wpdb']->prefix . 'nbr_noobrocket_cache';
$rows_before = $GLOBALS['wpdb']->get_results( "SELECT * FROM $table", ARRAY_A );
$rewrite_before = clone $GLOBALS['wp_rewrite'];
$relationships = [
	'add_term_relationship' => [ 'remember_term_relationship_changes', 3 ],
	'delete_term_relationships' => [ 'remember_deleted_term_relationships', 3 ],
	'set_object_terms' => [ 'purge_set_term_relationships', 4 ],
	'deleted_term_relationships' => [ 'purge_deleted_term_relationships', 3 ],
];
$block_http = function () { return new WP_Error( 'taxonomy_fixture_http', 'HTTP disabled by taxonomy fixture.' ); };
$permalinks = function () { return '/%category%/%postname%/'; };
add_filter( 'pre_http_request', $block_http, PHP_INT_MAX - 1 );
add_filter( 'pre_option_permalink_structure', $permalinks );
add_filter( 'pre_get_noobrocket_option_manual_preload', '__return_true' );
add_filter( 'noobrocket_is_importing', '__return_true' );
$GLOBALS['wp_rewrite']->init();

function taxonomy_cache_check( $condition, $message ) {
	global $checks;
	if ( ! $condition ) {
		throw new RuntimeException( 'FAIL: ' . $message );
	}
	$checks[] = $message;
}

function taxonomy_cache_seed( $url ) {
	global $query, $cache_files;
	$parsed = get_noobrocket_parse_url( $url );
	$directory = NOOBROCKET_CACHE_PATH . $parsed['host'] . rtrim( $parsed['path'], '/' );
	$cache_files[ $url ] = [
		$directory . '/index.html',
		$directory . '/index.html_gzip',
		$directory . '/index-mobile.html',
		$directory . '/page/2/index.html',
		$directory . '/#fixture-query/index.html',
	];
	foreach ( $cache_files[ $url ] as $file ) {
		wp_mkdir_p( dirname( $file ) );
		file_put_contents( $file, 'Taxonomy fixture cache' );
	}
	$query->create_or_update( [ 'url' => $url, 'status' => 'completed' ] );
	_noobrocket_get_cache_dirs( '', '', true );
}

function taxonomy_cache_state( $url, $purged, $message ) {
	global $query, $cache_files;
	clearstatcache();
	$remaining = array_filter( $cache_files[ $url ], 'is_file' );
	taxonomy_cache_check( $purged ? ! $remaining : count( $remaining ) === count( $cache_files[ $url ] ), $message . ': cache files' );
	$rows = $query->get_rows_by_url( $url );
	taxonomy_cache_check( $rows && count( $rows ) === 1 && $rows[0]->status === ( $purged ? 'pending' : 'completed' ), $message . ': preload status' );
}

try {
	$prefix = 'nbr-fixture-' . strtolower( wp_generate_password( 8, false, false ) );
	foreach ( [ 'parent-a' => 0, 'parent-b' => 0, 'a' => 'parent-a', 'b' => 'parent-b', 'unrelated' => 'parent-a' ] as $name => $parent ) {
		$created = wp_insert_term( $prefix . '-' . $name, 'category', [ 'slug' => $prefix . '-' . $name, 'parent' => $parent ? $terms[ $parent ] : 0 ] );
		taxonomy_cache_check( ! is_wp_error( $created ), 'create ' . $name . ' fixture term' );
		$terms[ $name ] = (int) $created['term_id'];
	}
	foreach ( [ 'nbr_cache_private' => [ false, false, 'post' ], 'nbr_cache_noquery' => [ true, false, 'post' ], 'nbr_cache_user' => [ true, true, 'user' ] ] as $taxonomy => $visibility ) {
		register_taxonomy( $taxonomy, $visibility[2], [ 'public' => $visibility[0], 'publicly_queryable' => $visibility[1], 'rewrite' => [ 'slug' => $taxonomy ] ] );
		$created = wp_insert_term( $prefix, $taxonomy, [ 'slug' => $prefix ] );
		taxonomy_cache_check( ! is_wp_error( $created ), 'create guarded taxonomy fixture' );
		$terms[ $taxonomy ] = (int) $created['term_id'];
	}
	foreach ( [ 'publish', 'draft', 'private' ] as $status ) {
		$id = wp_insert_post( [ 'post_title' => $prefix . '-' . $status, 'post_name' => $prefix . '-' . $status, 'post_status' => $status, 'post_category' => [ $terms['a'] ] ], true );
		taxonomy_cache_check( ! is_wp_error( $id ) && $id > 0, 'create ' . $status . ' fixture post' );
		$posts[ $status ] = $id;
	}
	remove_filter( 'noobrocket_is_importing', '__return_true' );
	$urls = [];
	foreach ( $terms as $name => $term_id ) {
		$taxonomy = strpos( $name, 'nbr_cache_' ) === 0 ? $name : 'category';
		$urls[ $name ] = get_term_link( $term_id, $taxonomy );
		taxonomy_cache_check( is_string( $urls[ $name ] ) && ! wp_parse_url( $urls[ $name ], PHP_URL_QUERY ), 'pretty archive URL for ' . $name );
	}
	$post_id = $posts['publish'];
	$old_url = get_permalink( $post_id );
	$category_b = function () use ( $terms ) { return get_term( $terms['b'], 'category' ); };
	add_filter( 'post_link_category', $category_b );
	$new_url = get_permalink( $post_id );
	remove_filter( 'post_link_category', $category_b );
	taxonomy_cache_check( $old_url !== $new_url && strpos( $old_url, $prefix . '-a/' ) !== false && strpos( $new_url, $prefix . '-b/' ) !== false, 'category assignment changes the post permalink' );
	$home_url = home_url( '/' );
	foreach ( array_merge( array_values( $urls ), [ $old_url, $new_url, $home_url ] ) as $url ) {
		taxonomy_cache_seed( $url );
	}
	if ( ( $argv[2] ?? '' ) === 'baseline' ) {
		foreach ( $relationships as $hook => $handler ) {
			remove_action( $hook, [ $subscriber, $handler[0] ], 10 );
		}
	}
	$result = wp_set_post_terms( $post_id, [ $terms['b'] ], 'category' );
	taxonomy_cache_check( ! is_wp_error( $result ) && get_permalink( $post_id ) === $new_url, 'real WordPress replacement assigns category B' );
	foreach ( [ 'a', 'parent-a', 'b', 'parent-b' ] as $name ) {
		taxonomy_cache_state( $urls[ $name ], true, 'replacement purges ' . $name . ' archive and pagination' );
	}
	taxonomy_cache_state( $old_url, true, 'replacement purges old category permalink' );
	taxonomy_cache_state( $new_url, true, 'replacement purges current category permalink' );
	taxonomy_cache_state( $home_url, true, 'replacement uses common homepage purger' );
	taxonomy_cache_state( $urls['unrelated'], false, 'unrelated child archive survives ancestor purge' );

	foreach ( [ $urls['a'], $urls['parent-a'], $old_url, $new_url ] as $url ) {
		taxonomy_cache_seed( $url );
	}
	wp_set_post_terms( $post_id, [ $terms['a'] ], 'category', true );
	taxonomy_cache_check( get_permalink( $post_id ) === $old_url, 'real append changes the category-based permalink again' );
	taxonomy_cache_state( $urls['a'], true, 'append purges added archive' );
	taxonomy_cache_state( $urls['parent-a'], true, 'append purges added ancestor' );
	taxonomy_cache_state( $old_url, true, 'append purges current permalink despite post-purge deduplication' );
	taxonomy_cache_state( $new_url, true, 'append purges previous permalink despite post-purge deduplication' );

	foreach ( [ $urls['a'], $urls['b'], $urls['unrelated'], $old_url ] as $url ) {
		taxonomy_cache_seed( $url );
	}
	wp_set_post_terms( $post_id, [ $terms['a'], $terms['b'] ], 'category' );
	wp_set_post_terms( $post_id, [ $terms['b'] ], 'category', true );
	wp_remove_object_terms( $post_id, $terms['unrelated'], 'category' );
	foreach ( [ 'a', 'b', 'unrelated' ] as $name ) {
		taxonomy_cache_state( $urls[ $name ], false, 'no-op replacement/append/removal preserves ' . $name );
	}
	taxonomy_cache_state( $old_url, false, 'no-op term writes preserve post cache' );

	foreach ( [ $urls['a'], $urls['parent-a'], $old_url, $new_url ] as $url ) {
		taxonomy_cache_seed( $url );
	}
	taxonomy_cache_check( wp_remove_object_terms( $post_id, $terms['a'], 'category' ), 'real direct removal succeeds' );
	taxonomy_cache_state( $urls['a'], true, 'direct removal purges removed archive' );
	taxonomy_cache_state( $urls['parent-a'], true, 'direct removal purges removed ancestor' );
	taxonomy_cache_state( $old_url, true, 'direct removal purges previous permalink' );
	taxonomy_cache_state( $new_url, true, 'direct removal purges current permalink' );
	taxonomy_cache_state( $urls['b'], false, 'direct removal preserves unchanged assigned archive' );

	$exclude_category = function ( $excluded ) { $excluded[] = 'category'; return $excluded; };
	taxonomy_cache_seed( $urls['a'] );
	taxonomy_cache_seed( $new_url );
	add_filter( 'noobrocket_exclude_post_taxonomy', $exclude_category );
	wp_set_post_terms( $post_id, [ $terms['a'] ], 'category', true );
	remove_filter( 'noobrocket_exclude_post_taxonomy', $exclude_category );
	taxonomy_cache_state( $urls['a'], false, 'excluded public taxonomy retains archive' );
	taxonomy_cache_state( $new_url, false, 'excluded public taxonomy retains post cache' );
	add_filter( 'noobrocket_is_importing', '__return_true' );
	wp_remove_object_terms( $post_id, $terms['a'], 'category' );
	remove_filter( 'noobrocket_is_importing', '__return_true' );
	taxonomy_cache_state( $urls['a'], false, 'imported relationship removal retains archive' );
	taxonomy_cache_state( $new_url, false, 'imported relationship removal retains post cache' );
	foreach ( [ 'nbr_cache_private', 'nbr_cache_noquery' ] as $taxonomy ) {
		wp_set_object_terms( $post_id, [ $terms[ $taxonomy ] ], $taxonomy );
		taxonomy_cache_state( $urls[ $taxonomy ], false, 'nonpublic/nonqueryable taxonomy retains archive: ' . $taxonomy );
	}
	wp_set_object_terms( $post_id, [ $terms['nbr_cache_user'] ], 'nbr_cache_user' );
	taxonomy_cache_state( $urls['nbr_cache_user'], false, 'nonpost object taxonomy retains archive despite a matching numeric post ID' );
	foreach ( [ 'draft', 'private' ] as $status ) {
		wp_set_post_terms( $posts[ $status ], [ $terms['b'] ], 'category' );
		taxonomy_cache_state( $urls['a'], false, $status . ' post relationship retains removed archive' );
		taxonomy_cache_state( $urls['b'], false, $status . ' post relationship retains added archive' );
	}
	echo 'PASS: ', count( $checks ), ' real WordPress taxonomy/cache/preload assertions.', PHP_EOL;
} finally {
	add_filter( 'noobrocket_is_importing', '__return_true' );
	foreach ( $posts as $post_id ) {
		wp_delete_post( $post_id, true );
	}
	foreach ( array_reverse( $terms, true ) as $name => $term_id ) {
		wp_delete_term( $term_id, strpos( $name, 'nbr_cache_' ) === 0 ? $name : 'category' );
	}
	$known_urls = array_column( $rows_before, 'url' );
	foreach ( $GLOBALS['wpdb']->get_col( "SELECT url FROM $table" ) as $url ) {
		if ( ! in_array( $url, $known_urls, true ) ) {
			$query->delete_by_url( $url );
		}
	}
	foreach ( $rows_before as $row ) {
		$id = $row['id'];
		unset( $row['id'] );
		$GLOBALS['wpdb']->update( $table, $row, [ 'id' => $id ] );
	}
	remove_filter( 'noobrocket_is_importing', '__return_true' );
	remove_filter( 'pre_get_noobrocket_option_manual_preload', '__return_true' );
	remove_filter( 'pre_option_permalink_structure', $permalinks );
	remove_filter( 'pre_http_request', $block_http, PHP_INT_MAX - 1 );
	$GLOBALS['wp_rewrite'] = $rewrite_before;
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $fixture, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $iterator as $file ) {
		if ( $file->isDir() ) {
			rmdir( $file->getPathname() );
		} else {
			unlink( $file->getPathname() );
		}
	}
	rmdir( $fixture );
}
