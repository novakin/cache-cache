<?php
/** Native cleanup category revalidation, stale-cache preservation, SQL failure and keyset resume. */
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
foreach ( [ '/inc/Engine/Admin/Database/Optimization.php', '/inc/Engine/Admin/Database/OptimizationProcess.php' ] as $owner ) {
	if ( hash_file( 'sha256', $source . $owner ) !== hash_file( 'sha256', $installed . $owner ) ) {
		fwrite( STDERR, "Copy the current database cleanup owners into the disposable plugin before this check.\n" );
		exit( 2 );
	}
}
define( 'WP_ADMIN', true );
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'POST';
require_once $wordpress . '/wp-includes/plugin.php';
add_filter( 'pre_http_request', static function ( $pre ) {
	if ( false === $pre ) { throw new RuntimeException( 'Unowned HTTP requested by native cleanup fixture.' ); }
	return $pre;
}, PHP_INT_MAX - 1 );
add_filter( 'action_scheduler_allow_async_request_runner', '__return_false' );
add_filter( 'noobrocket_generate_advanced_cache_file', '__return_false' );
add_filter( 'noobrocket_disable_htaccess', '__return_true' );
$fixture_completed = false;
register_shutdown_function( static function () use ( &$fixture_completed ) {
	if ( ! $fixture_completed ) { fwrite( STDERR, "FAIL: native database-eligibility fixture did not complete.\n" ); exit( 2 ); }
} );
require $wordpress . '/wp-load.php';
ini_set( 'display_errors', 'stderr' );

$checks = 0;
$check = static function ( $condition, $message ) use ( &$checks ) {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
	++$checks;
};
$container = apply_filters( 'noobrocket_container', null );
$check( $container && false !== has_action( 'admin_post_noobrocket_database_cleanup' ), 'the native database cleanup subscriber is registered' );
$process = $container->get( 'db_optimization_process' );
$check( get_class( $process ) === 'NoobRocket\\Engine\\Admin\\Database\\OptimizationProcess', 'the registered native cleanup owner runs without a fixture subclass' );
$task = new ReflectionMethod( $process, 'task' );
$task->setAccessible( true );
$start = new ReflectionProperty( $process, 'start_time' );
$start->setAccessible( true );
$run = static function ( $item ) use ( $process, $task, $start ) {
	// The queue normally sets this immediately before invoking task(). No dispatch is needed here.
	$start->setValue( $process, time() );
	return $task->invoke( $process, $item );
};
$original_errors = $wpdb->suppress_errors( true );
$missing = new stdClass();
$results_key = 'noobrocket_database_optimization_results';
$original_results = get_option( $results_key, $missing );
$token = 'cache_cache_eligibility_' . bin2hex( random_bytes( 6 ) );
$owned_posts = $owned_comments = $scope_posts = $scope_comments = [];
$deleted_posts = $deleted_comments = [];
$transition = null;
$refused_read = '';
$post_row = static function ( $id ) use ( $wpdb ) { return $wpdb->get_var( $wpdb->prepare( "SELECT post_status FROM {$wpdb->posts} WHERE ID = %d", $id ) ); };
$comment_row = static function ( $id ) use ( $wpdb ) { return $wpdb->get_var( $wpdb->prepare( "SELECT comment_approved FROM {$wpdb->comments} WHERE comment_ID = %d", $id ) ); };
$seed_post = static function ( $status ) use ( &$owned_posts, $check, $token ) {
	$id = wp_insert_post( [ 'post_title' => $token, 'post_status' => $status, 'post_type' => 'post', 'post_author' => 1 ], true );
	$check( ! is_wp_error( $id ) && $id > 0, 'owned post inserted with native API' );
	$owned_posts[] = (int) $id;
	return (int) $id;
};
$seed_comment = static function ( $status, $parent ) use ( &$owned_comments, $check, $token ) {
	$id = wp_insert_comment( [ 'comment_post_ID' => $parent, 'comment_author' => $token, 'comment_content' => $token, 'comment_approved' => $status ] );
	$check( $id > 0, 'owned comment inserted with native API' );
	$owned_comments[] = (int) $id;
	return (int) $id;
};
$query_filter = static function ( $sql ) use ( $wpdb, &$scope_posts, &$scope_comments, &$refused_read ) {
	foreach ( [ [ 'ID', $wpdb->posts, $scope_posts ], [ 'comment_ID', $wpdb->comments, $scope_comments ] ] as [ $column, $table, $scope ] ) {
		$selection = "SELECT {$column} FROM {$table} WHERE ";
		if ( 0 === strpos( $sql, $selection ) ) {
			// Retain the owner's category/keyset SQL; only constrain selection to fixture-owned IDs.
			$owned = $scope ? $wpdb->prepare( "{$column} IN (" . implode( ',', array_fill( 0, count( $scope ), '%d' ) ) . ')', $scope ) : '1 = 0';
			$order = strpos( $sql, " ORDER BY {$column} ASC LIMIT " );
			$sql = false === $order ? $sql . ' AND ' . $owned : substr( $sql, 0, $order ) . ' AND ' . $owned . substr( $sql, $order );
		}
	}
	return $refused_read && $sql === $refused_read ? 'CACHE_CACHE_FIXTURE_REFUSED_CATEGORY_READ' : $sql;
};
$post_deleted = static function ( $id ) use ( &$owned_posts, &$deleted_posts, &$transition ) {
	if ( in_array( (int) $id, $owned_posts, true ) ) { $deleted_posts[] = (int) $id; }
	if ( $transition && 'post' === $transition['kind'] && (int) $id === $transition['first'] ) { ( $transition['run'] )(); }
};
$comment_deleted = static function ( $id ) use ( &$owned_comments, &$deleted_comments, &$transition ) {
	if ( in_array( (int) $id, $owned_comments, true ) ) { $deleted_comments[] = (int) $id; }
	if ( $transition && 'comment' === $transition['kind'] && (int) $id === $transition['first'] ) { ( $transition['run'] )(); }
};

try {
	add_filter( 'query', $query_filter );
	add_action( 'deleted_post', $post_deleted, 20 );
	add_action( 'deleted_comment', $comment_deleted, 20 );
	$host = $seed_post( 'publish' );
	$control = $seed_post( 'trash' );
	foreach ( [ [ 'database_auto_drafts', 'auto-draft', 'draft' ], [ 'database_trashed_posts', 'trash', 'publish' ] ] as [ $category, $selected, $restored ] ) {
		$scope_comments = [];
		$scope_posts = [ $seed_post( $selected ), $seed_post( $selected ) ];
		[ $first, $second ] = $scope_posts;
		$deleted_posts = [];
		$stale = get_post( $second );
		$transition = [ 'kind' => 'post', 'first' => $first, 'run' => static function () use ( $second, $restored, $selected, $stale, $check ) {
			$updated = wp_update_post( [ 'ID' => $second, 'post_status' => $restored ], true );
			$check( ! is_wp_error( $updated ) && $updated === $second, 'native deletion hook saves/restores the next selected post' );
			// Model another request's stale local cache while preserving the real database update.
			wp_cache_set( $second, $stale, 'posts' );
			$check( $selected === get_post_status( $second ), 'the selected post category remains stale in WordPress object cache' );
		} ];
		$check( false === $run( $category ), $category . ': selected batch finishes' );
		$results = get_option( $results_key );
		$check( [ $first ] === $deleted_posts && $restored === $post_row( $second ), $category . ': native hook restore survives despite stale cache' );
		$check( 1 === $results['counts'][ $category ] && 0 === $results['failed'][ $category ], $category . ': skip is neither deletion nor failure' );
	}
	foreach ( [ [ 'database_spam_comments', 'spam', 'approve', '1' ], [ 'database_trashed_comments', 'trash', 'hold', '0' ], [ 'database_trashed_comments', 'post-trashed', 'approve', '1' ] ] as [ $category, $selected, $restored, $expected ] ) {
		$scope_posts = [];
		$scope_comments = [ $seed_comment( $selected, $host ), $seed_comment( $selected, $host ) ];
		[ $first, $second ] = $scope_comments;
		$deleted_comments = [];
		$stale = get_comment( $second );
		$transition = [ 'kind' => 'comment', 'first' => $first, 'run' => static function () use ( $second, $restored, $selected, $stale, $check ) {
			$check( true === wp_set_comment_status( $second, $restored, true ), 'native deletion hook approves/restores the next selected comment' );
			wp_cache_set( $second, $stale, 'comment' );
			$check( $selected === get_comment( $second )->comment_approved, 'the selected comment category remains stale in WordPress object cache' );
		} ];
		$check( false === $run( $category ), $category . ': selected batch finishes' );
		$results = get_option( $results_key );
		$check( [ $first ] === $deleted_comments && $expected === $comment_row( $second ), $category . ': native hook restore survives despite stale cache' );
		$check( 1 === $results['counts'][ $category ] && 0 === $results['failed'][ $category ], $category . ': skip is neither deletion nor failure' );
	}
	$transition = null;
	foreach ( [ 'post', 'comment' ] as $kind ) {
		$scope_posts = $scope_comments = [];
		if ( 'post' === $kind ) {
			$scope_posts = [ $seed_post( 'trash' ), $seed_post( 'trash' ), $seed_post( 'trash' ) ];
			$ids = $scope_posts;
			$category = 'database_trashed_posts';
			$refused_read = $wpdb->prepare( "SELECT post_status FROM {$wpdb->posts} WHERE ID = %d", $ids[1] );
		} else {
			$scope_comments = [ $seed_comment( 'spam', $host ), $seed_comment( 'spam', $host ), $seed_comment( 'spam', $host ) ];
			$ids = $scope_comments;
			$category = 'database_spam_comments';
			$refused_read = $wpdb->prepare( "SELECT comment_approved FROM {$wpdb->comments} WHERE comment_ID = %d", $ids[1] );
		}
		$deleted_posts = $deleted_comments = [];
		$check( false === $run( $category ), $kind . ': read refusal does not strand the selected batch' );
		$refused_read = '';
		$results = get_option( $results_key );
		$deleted = 'post' === $kind ? $deleted_posts : $deleted_comments;
		$remaining = 'post' === $kind ? $post_row( $ids[1] ) : $comment_row( $ids[1] );
		$check( [ $ids[0], $ids[2] ] === $deleted && null !== $remaining, $kind . ': actual SQL read failure retains its row and later eligible rows run' );
		$check( 2 === $results['counts'][ $category ] && 1 === $results['failed'][ $category ], $kind . ': read failure is recorded once' );
	}
	$scope_comments = [];
	$scope_posts = [];
	for ( $index = 0; $index < 51; ++$index ) { $scope_posts[] = $seed_post( 'trash' ); }
	[ $first, $second, $third ] = $scope_posts;
	$transition = [ 'kind' => 'post', 'first' => $first, 'run' => static function () use ( $second, $third, $check ) {
		$check( $second === wp_update_post( [ 'ID' => $second, 'post_status' => 'draft' ], true ), 'native restore changes the second selected row' );
		$check( wp_delete_post( $third, true ) instanceof WP_Post, 'another native deletion removes the third selected row' );
	} ];
	$state = $run( 'database_trashed_posts' );
	$check( is_array( $state ) && $scope_posts[49] === (int) $state['cursor'] && 48 === $state['count'] && 0 === $state['failed'], 'changed and missing rows advance the full native batch cursor without false counts' );
	$check( false === $run( $state ), 'the native keyset resumes and finishes the final eligible row' );
	$results = get_option( $results_key );
	$check( 'draft' === $post_row( $second ) && null === $post_row( $scope_posts[50] ) && 49 === $results['counts']['database_trashed_posts'] && 0 === $results['failed']['database_trashed_posts'], 'resume preserves the restored row and deletes the final eligible row exactly once' );
	$check( 'trash' === $post_row( $control ), 'fixture selection leaves its same-category control outside the owned scope' );
} finally {
	$transition = null;
	$refused_read = '';
	remove_filter( 'query', $query_filter );
	remove_action( 'deleted_post', $post_deleted, 20 );
	remove_action( 'deleted_comment', $comment_deleted, 20 );
	foreach ( $owned_comments as $id ) { if ( get_comment( $id ) ) { wp_delete_comment( $id, true ); } }
	foreach ( $owned_posts as $id ) { if ( get_post( $id ) ) { wp_delete_post( $id, true ); } }
	if ( $missing === $original_results ) { delete_option( $results_key ); }
	else { update_option( $results_key, $original_results, false ); }
	$wpdb->suppress_errors( $original_errors );
}
$post_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE ID IN (" . implode( ',', array_fill( 0, count( $owned_posts ), '%d' ) ) . ')', $owned_posts ) );
$check( ! $post_ids && ! $wpdb->last_error, 'native cleanup removes every fixture-owned post' );
$comment_ids = $wpdb->get_col( $wpdb->prepare( "SELECT comment_ID FROM {$wpdb->comments} WHERE comment_ID IN (" . implode( ',', array_fill( 0, count( $owned_comments ), '%d' ) ) . ')', $owned_comments ) );
$check( ! $comment_ids && ! $wpdb->last_error, 'native cleanup removes every fixture-owned comment' );
$check( $original_results === get_option( $results_key, $missing ), 'native cleanup restores the original result storage' );
$fixture_completed = true;
echo "PASS: $checks native database-eligibility assertions; five categories, native restore hooks, stale cache, SQL refusal and 51-row resume.\n";
