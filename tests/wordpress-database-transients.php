<?php
/** Native persisted transient cleanup, cache invalidation and isolated failure/scope proof. */
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
$_SERVER['SERVER_PORT'] = parse_url( 'http://' . $_SERVER['HTTP_HOST'], PHP_URL_PORT ) ?: 80;
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'POST';
require_once $wordpress . '/wp-includes/plugin.php';
add_filter( 'pre_http_request', static function () { return new WP_Error( 'transient_fixture_http', 'Unowned HTTP is blocked by this fixture.' ); }, PHP_INT_MAX - 1 );
add_filter( 'action_scheduler_allow_async_request_runner', '__return_false' );
add_filter( 'noobrocket_generate_advanced_cache_file', '__return_false' );
add_filter( 'noobrocket_disable_htaccess', '__return_true' );
$fixture_completed = false;
register_shutdown_function( static function () use ( &$fixture_completed ) {
	if ( ! $fixture_completed ) {
		fwrite( STDERR, "FAIL: native database-transient fixture did not complete.\n" );
		exit( 2 );
	}
} );
require $wordpress . '/wp-load.php';
ini_set( 'display_errors', 'stderr' );

use NoobRocket\Engine\Admin\Database\Optimization;
use NoobRocket\Engine\Admin\Database\OptimizationProcess;

class TransientCleanupFixtureProcess extends OptimizationProcess {
	protected function time_exceeded() { return false; }
	protected function memory_exceeded() { return false; }
	protected function is_process_cancelled() { return false; }
}

$checks = 0;
$check = static function ( $condition, $message ) use ( &$checks ) {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
	++$checks;
};
$network_proof = isset( $argv[2] ) && 'multisite' === $argv[2];
$check( ! $network_proof || is_multisite(), 'explicit network proof requires a real multisite installation' );
$original_blog = get_current_blog_id();
$original_cache_mode = wp_using_ext_object_cache();
$original_errors = $wpdb->suppress_errors( true );
$results_key = 'noobrocket_database_optimization_results';
$missing = new stdClass();
$original_results = get_option( $results_key, $missing );
$token = 'cache_cache_cleanup_fixture_' . bin2hex( random_bytes( 6 ) );
$owned_options = [];
$owned_network_options = [];
$owned_cache = [];
$scope = [];
$refused_option = '';
$before_hooks = [];
$after_hooks = [];
$other_blog = 0;
$process = new TransientCleanupFixtureProcess();
$optimization = new Optimization( $process );
$task = new ReflectionMethod( $process, 'task' );
$task->setAccessible( true );

$row_exists = static function ( $name ) use ( $wpdb ) {
	return null !== $wpdb->get_var( $wpdb->prepare( "SELECT option_id FROM {$wpdb->options} WHERE option_name = %s", $name ) );
};
$seed = static function ( $name, bool $site = false, bool $timeout = true ) use ( &$owned_options, $check ) {
	$prefix = $site ? '_site_transient_' : '_transient_';
	$value = $prefix . $name;
	$check( add_option( $value, 'fixture-value', '', false ), 'owned transient value is inserted' );
	$owned_options[] = [ get_current_blog_id(), $value ];
	if ( $timeout ) {
		$key = $prefix . 'timeout_' . $name;
		$check( add_option( $key, time() + HOUR_IN_SECONDS, '', false ), 'owned timeout is inserted' );
		$owned_options[] = [ get_current_blog_id(), $key ];
	}
	return $value;
};
$cache = static function ( $name, bool $site = false ) use ( &$owned_cache, $check ) {
	$group = $site ? 'site-transient' : 'transient';
	$check( wp_cache_set( $name, 'fixture-cache', $group ), 'owned transient cache key is inserted' );
	$owned_cache[] = [ get_current_blog_id(), $name, $group ];
};
$query_filter = static function ( $sql ) use ( $wpdb, &$scope, &$refused_option ) {
	// Keep native admission/keyset SQL; add only an exact owned-name fixture boundary.
	$selection = "SELECT option_name FROM {$wpdb->options} WHERE ";
	$count = "SELECT COUNT(option_id) FROM {$wpdb->options} WHERE ";
	if ( ( 0 === strpos( $sql, $selection ) || 0 === strpos( $sql, $count ) ) && false !== strpos( $sql, $wpdb->remove_placeholder_escape( Optimization::transient_condition() ) ) ) {
		$owned = $scope ? $wpdb->prepare( 'option_name IN (' . implode( ',', array_fill( 0, count( $scope ), '%s' ) ) . ')', $scope ) : '1 = 0';
		$order = strpos( $sql, ' ORDER BY option_name ASC LIMIT ' );
		$sql = false === $order ? $sql . ' AND ' . $owned : substr( $sql, 0, $order ) . ' AND ' . $owned . substr( $sql, $order );
	}
	if ( $refused_option && preg_match( '/^\s*DELETE\s/i', $sql ) && false !== strpos( $sql, $wpdb->prepare( '%s', $refused_option ) ) ) {
		return 'CACHE_CACHE_FIXTURE_REFUSED_DELETE';
	}
	return $sql;
};
$before_hook = static function ( $name ) use ( &$before_hooks ) { $before_hooks[ $name ] = ( $before_hooks[ $name ] ?? 0 ) + 1; };
$after_hook = static function ( $name ) use ( &$after_hooks ) { $after_hooks[ $name ] = ( $after_hooks[ $name ] ?? 0 ) + 1; };

try {
	wp_using_ext_object_cache( false );
	$protected = $seed( 'noobrocket_database_optimization_' . $token );
	$foreign = $token . '_ordinary_option';
	$check( add_option( $foreign, 'fixture-control', '', false ), 'owned nontransient control is inserted' );
	$owned_options[] = [ $original_blog, $foreign ];
	$controls = [ $protected, $foreign ];
	add_filter( 'query', $query_filter );
	add_action( 'deleted_transient', $after_hook );
	add_action( 'deleted_site_transient', $after_hook );

	foreach ( [ false, true ] as $external ) {
		$label = $external ? 'external' : 'normal';
		$name = $token . '_' . $label;
		$value = $seed( $name );
		$no_timeout_name = $name . '_without_timeout';
		$no_timeout = $seed( $no_timeout_name, false, false );
		$scope = array_merge( [ $value, $no_timeout ], $controls );
		add_action( 'delete_transient_' . $name, $before_hook );
		if ( $external ) { $cache( $name ); }
		$site_name = '';
		if ( ! is_multisite() ) {
			$site_name = $name . '_site';
			$scope[] = $seed( $site_name, true );
			add_action( 'delete_site_transient_' . $site_name, $before_hook );
			if ( $external ) { $cache( $site_name, true ); }
		}
		wp_using_ext_object_cache( $external );
		$expected = $site_name ? 3 : 2;
		$check( $expected === (int) $optimization->count_cleanup_items( 'database_all_transients' ), $label . ': counts exclude protected and nontransient values' );
		$check( false === $task->invoke( $process, 'database_all_transients' ), $label . ': actual worker finishes the owned transient scope' );
		$receipt = get_option( $results_key );
		$check( $expected === $receipt['counts']['database_all_transients'] && 0 === $receipt['failed']['database_all_transients'], $label . ': persisted deletions succeed, including an absent timeout/cache key' . ' ' . json_encode( $receipt ) );
		$check( ! $row_exists( $value ) && ! $row_exists( '_transient_timeout_' . $name ) && ! $row_exists( $no_timeout ), $label . ': selected value and timeout rows are gone' );
		$check( 1 === ( $before_hooks[ $name ] ?? 0 ), $label . ': native before-delete transient hook runs once' );
		$check( 1 === ( $after_hooks[ $name ] ?? 0 ), $label . ': native completed transient hook is preserved' );
		$check( false === wp_cache_get( $name, 'transient' ), $label . ': matching transient cache key is absent' );
		if ( $site_name ) {
			$check( ! $row_exists( '_site_transient_' . $site_name ) && ! $row_exists( '_site_transient_timeout_' . $site_name ), $label . ': single-site persisted site-transient pair is gone' );
			$check( 1 === ( $before_hooks[ $site_name ] ?? 0 ) && 1 === ( $after_hooks[ $site_name ] ?? 0 ), $label . ': native site-transient hooks are preserved' );
			$check( false === wp_cache_get( $site_name, 'site-transient' ), $label . ': matching site-transient cache key is absent' );
		}
		$check( $row_exists( $protected ) && $row_exists( $foreign ), $label . ': protected worker and unrelated option values remain' );
		$check( 0 === (int) $optimization->count_cleanup_items( 'database_all_transients' ), $label . ': displayed count has the same completed database scope' );
	}

	foreach ( [ 'timeout', 'autoloaded_timeout', 'value' ] as $refusal ) {
		wp_using_ext_object_cache( false );
		$name = $token . '_refused_' . $refusal;
		$value = $seed( $name );
		$timeout = '_transient_timeout_' . $name;
		$cache( $name );
		$scope = [ $value ];
		if ( 'autoloaded_timeout' === $refusal ) {
			delete_option( $timeout );
			$check( add_option( $timeout, time() + HOUR_IN_SECONDS, '', true ), 'owned autoloaded timeout is inserted' );
			$check( isset( wp_load_alloptions( true )[ $timeout ] ), 'timeout is actually autoloaded before the cache is cleared' );
			wp_cache_delete( 'alloptions', 'options' );
		}
		$refused_option = 'value' === $refusal ? $value : $timeout;
		wp_using_ext_object_cache( true );
		$task->invoke( $process, 'database_all_transients' );
		$refused_option = '';
		$receipt = get_option( $results_key );
		$check( 0 === $receipt['counts']['database_all_transients'] && 1 === $receipt['failed']['database_all_transients'], $refusal . ': successful cache invalidation cannot conceal SQL refusal' );
		$check( $row_exists( $value ), $refusal . ': failed persisted deletion keeps the value selectable for a later cleanup' );
		$check( ( 'value' !== $refusal ) === $row_exists( $timeout ), $refusal . ': timeout-first deletion preserves the pair when timeout deletion fails' );
		$check( false === wp_cache_get( $name, 'transient' ), $refusal . ': native cache invalidation still runs' );
	}

	if ( is_multisite() ) {
		wp_using_ext_object_cache( false );
		$name = $token . '_network';
		$network_id = get_current_network_id();
		foreach ( [ '_site_transient_' . $name, '_site_transient_timeout_' . $name ] as $key ) {
			$check( add_network_option( $network_id, $key, 'fixture-network' ), 'owned network control is inserted' );
			$owned_network_options[] = [ $network_id, $key ];
		}
		$legacy_network = $seed( $name, true );
		$cache( $name, true );
		wp_using_ext_object_cache( true );
		$scope = array_merge( [ $legacy_network ], $controls );
		$check( 0 === (int) $optimization->count_cleanup_items( 'database_all_transients' ), 'network: current-site count excludes network-style values' );
		$task->invoke( $process, 'database_all_transients' );
		$check( $row_exists( $legacy_network ), 'network: current-site cleanup preserves network-style rows' );
		$check( 'fixture-network' === get_network_option( $network_id, '_site_transient_' . $name ) && 'fixture-network' === get_network_option( $network_id, '_site_transient_timeout_' . $name ), 'network: actual shared network value and timeout remain unchanged' );
		$check( 'fixture-cache' === wp_cache_get( $name, 'site-transient' ), 'network: shared transient cache key is preserved without a group flush' );
		if ( $network_proof ) {
			$other_blog = wp_insert_site( [ 'domain' => get_current_site()->domain, 'path' => '/' . $token . '/', 'network_id' => $network_id ] );
			$check( ! is_wp_error( $other_blog ) && $other_blog > 0, 'network: owned sibling site is created' );
			switch_to_blog( $other_blog );
			$sibling = $seed( $token . '_sibling' );
			restore_current_blog();
			$scope = [ $sibling ];
			$task->invoke( $process, 'database_all_transients' );
			switch_to_blog( $other_blog );
			$check( $row_exists( $sibling ), 'network: cleanup on the original site cannot delete sibling-site transients' );
			restore_current_blog();
		}
	}
	echo "PASS: $checks native database-transient assertions; normal/external cache, persisted pairs, hooks, SQL refusal and site scope.\n";
} finally {
	$refused_option = '';
	remove_filter( 'query', $query_filter );
	remove_action( 'deleted_transient', $after_hook );
	remove_action( 'deleted_site_transient', $after_hook );
	wp_using_ext_object_cache( false );
	while ( get_current_blog_id() !== $original_blog && ms_is_switched() ) { restore_current_blog(); }
	foreach ( $owned_options as [ $blog_id, $name ] ) {
		$switched = get_current_blog_id() !== $blog_id;
		if ( $switched ) { switch_to_blog( $blog_id ); }
		delete_option( $name );
		if ( $switched ) { restore_current_blog(); }
	}
	foreach ( $owned_cache as [ $blog_id, $name, $group ] ) {
		$switched = get_current_blog_id() !== $blog_id;
		if ( $switched ) { switch_to_blog( $blog_id ); }
		wp_cache_delete( $name, $group );
		if ( $switched ) { restore_current_blog(); }
	}
	foreach ( $owned_network_options as [ $network_id, $name ] ) { delete_network_option( $network_id, $name ); }
	if ( $other_blog && ! is_wp_error( $other_blog ) ) { wp_delete_site( $other_blog ); }
	if ( $missing === $original_results ) { delete_option( $results_key ); }
	else { update_option( $results_key, $original_results, false ); }
	wp_using_ext_object_cache( $original_cache_mode );
	$wpdb->suppress_errors( $original_errors );
}
$fixture_completed = true;
