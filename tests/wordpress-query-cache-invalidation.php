<?php
/** Native query generations, mutation visibility and repeated-read efficiency on an owned table. */
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
$owner = '/inc/Dependencies/BerlinDB/Database/Query.php';
if ( hash_file( 'sha256', $source . $owner ) !== hash_file( 'sha256', $installed . $owner ) ) {
	fwrite( STDERR, "Copy the current query owner into the disposable plugin before this check.\n" );
	exit( 2 );
}
define( 'WP_ADMIN', true );
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
$_SERVER['SERVER_PORT'] = parse_url( 'http://' . $_SERVER['HTTP_HOST'], PHP_URL_PORT ) ?: 80;
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'POST';
require_once $wordpress . '/wp-includes/plugin.php';
add_filter( 'pre_http_request', static function () { return new WP_Error( 'query_fixture_http', 'Unowned HTTP is blocked by this fixture.' ); }, PHP_INT_MAX - 1 );
add_filter( 'action_scheduler_allow_async_request_runner', '__return_false' );
add_filter( 'noobrocket_generate_advanced_cache_file', '__return_false' );
add_filter( 'noobrocket_disable_htaccess', '__return_true' );
$query_fixture_completed = false;
register_shutdown_function( static function () use ( &$query_fixture_completed ) {
	if ( ! $query_fixture_completed ) {
		fwrite( STDERR, "FAIL: native query-cache fixture did not complete.\n" );
		exit( 2 );
	}
} );
require $wordpress . '/wp-load.php';
ini_set( 'display_errors', 'stderr' );
$redis_expiry = isset( $argv[2] ) && 'redis-expiry' === $argv[2];
if ( $redis_expiry && ( ! wp_using_ext_object_cache() || ! is_callable( [ $wp_object_cache, 'redis_instance' ] ) || ! is_callable( [ $wp_object_cache, 'build_key' ] ) || ! function_exists( 'wp_cache_flush_runtime' ) ) ) {
	throw new RuntimeException( 'Redis expiry mode requires the official Redis Object Cache drop-in on this disposable site.' );
}

use NoobRocket\Engine\Preload\Database\Queries\Cache as CacheQuery;
use NoobRocket\Engine\Preload\Database\Tables\Cache as CacheTable;
use NoobRocket\Logger\Logger;

class QueryCacheFixtureTable extends CacheTable {
	public function __construct( $name ) {
		$this->name = $name;
		$this->db_version_key = $name . '_version';
		parent::__construct();
	}
}
class QueryCacheFixtureQuery extends CacheQuery {
	public function __construct( $name ) {
		$this->table_name = $name;
		$this->cache_group = $name;
		parent::__construct( new Logger() );
	}
}

$checks = 0;
$check = static function ( $condition, $message ) use ( &$checks ) {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
	++$checks;
};
$name = 'nbr_noobrocket_cache_generation_' . bin2hex( random_bytes( 6 ) );
$table = new QueryCacheFixtureTable( $name );
$sql_reads = 0;
$full_name = $table->get_name();
$probe = static function ( $sql ) use ( &$sql_reads, $full_name ) {
	if ( preg_match( '/^\s*SELECT\b/i', $sql ) && false !== strpos( $sql, $full_name ) ) { ++$sql_reads; }
	return $sql;
};

try {
	$table->install();
	$check( $table->exists(), 'owned native table is installed' );
	add_filter( 'query', $probe );
	foreach ( [ 'reused-writer', 'distinct-writers' ] as $scenario ) {
		$first = new QueryCacheFixtureQuery( $name );
		$second = new QueryCacheFixtureQuery( $name );
		$reader = new QueryCacheFixtureQuery( $name );
		$url = 'https://query-cache.invalid/' . $scenario;
		$args = [ 'url' => $url ];
		$check( [] === $first->query( $args ), $scenario . ': initial miss is cached on the writer' );
		$check( [] === $reader->query( $args ), $scenario . ': separate reader sees the same miss' );
		$reads_before = $sql_reads;
		$check( [] === $reader->query( $args ), $scenario . ': same reader repeats its cached miss' );
		$check( $reads_before === $sql_reads, $scenario . ': repeated miss uses the result cache' );
		$miss_generation = wp_cache_get( 'last_changed', $name );
		$id = $first->add_item( [ 'url' => $url, 'status' => 'pending' ] );
		$check( $id > 0, $scenario . ': native insertion succeeds' );
		$insert_generation = wp_cache_get( 'last_changed', $name );
		$check( $miss_generation !== $insert_generation, $scenario . ': insertion advances the cached miss generation' );
		$rows = $reader->query( $args );
		$check( 1 === count( $rows ) && (int) $rows[0]->id === (int) $id && 'pending' === $rows[0]->status, $scenario . ': cached miss cannot hide the inserted row' );
		$check( $insert_generation === wp_cache_get( 'last_changed', $name ), $scenario . ': row shaping does not invalidate query results' );
		$check( 1 === count( $second->query( $args ) ), $scenario . ': another query instance reads the inserted row' );
		$reads_before = $sql_reads;
		$check( 1 === count( $second->query( $args ) ), $scenario . ': same query instance repeats its successful read' );
		$check( $reads_before === $sql_reads, $scenario . ': repeated successful query performs no table SELECT' );

		wp_cache_delete( $id, $name );
		$check( (int) $reader->get_item( $id )->id === (int) $id, $scenario . ': direct item read repopulates the item cache' );
		$check( $insert_generation === wp_cache_get( 'last_changed', $name ), $scenario . ': direct item read preserves query generation' );
		wp_cache_delete( $id, $name );
		$reads_before = $sql_reads;
		$check( 1 === count( $second->query( $args ) ), $scenario . ': result cache primes an uncached row' );
		$check( $sql_reads === $reads_before + 1, $scenario . ': missing item cache costs one priming SELECT' );
		$check( $insert_generation === wp_cache_get( 'last_changed', $name ), $scenario . ': item priming preserves query generation' );
		$reads_before = $sql_reads;
		$check( 1 === count( $reader->query( $args ) ) && $reads_before === $sql_reads, $scenario . ': the next complete read remains cached' );

		$updater = 'reused-writer' === $scenario ? $first : $second;
		$check( 1 === $updater->update_item( $id, [ 'status' => 'completed' ] ), $scenario . ': native update succeeds' );
		$update_generation = wp_cache_get( 'last_changed', $name );
		$check( $insert_generation !== $update_generation, $scenario . ': update advances the result generation' );
		$rows = $reader->query( $args );
		$check( 1 === count( $rows ) && 'completed' === $rows[0]->status, $scenario . ': cached query observes the update' );
		$check( 1 === $first->delete_item( $id ), $scenario . ': original writer deletes the row after another writer may have changed it' );
		$delete_generation = wp_cache_get( 'last_changed', $name );
		$check( $delete_generation !== $insert_generation && $delete_generation !== $update_generation, $scenario . ': deletion cannot restore an earlier generation' );
		$check( [] === $reader->query( $args ), $scenario . ': query observes deletion' );
		$reads_before = $sql_reads;
		$check( [] === $reader->query( $args ) && $reads_before === $sql_reads, $scenario . ': repeated deletion miss stays cached' );
		$check( false === $reader->get_item( $id ), $scenario . ': deleted item is absent from the item cache and database' );
	}
	if ( $redis_expiry ) {
		$redis = $wp_object_cache->redis_instance();
		$key_method = new ReflectionMethod( NoobRocket\Dependencies\BerlinDB\Database\Query::class, 'get_cache_key' );
		$key_method->setAccessible( true );
		$writer = new QueryCacheFixtureQuery( $name );
		$reader = new QueryCacheFixtureQuery( $name );
		$args = [ 'url' => 'https://query-cache.invalid/redis-expiry' ];
		$id = $writer->add_item( [ 'url' => $args['url'], 'status' => 'pending' ] );
		$check( $id > 0, 'Redis: owned native insertion succeeds' );
		$check( 1 === count( $reader->query( $args ) ), 'Redis: initial result is populated' );
		$keys = [ $key_method->invoke( $reader ) ];
		$reads_before = $sql_reads;
		$check( 1 === count( $reader->query( $args ) ) && $reads_before === $sql_reads, 'Redis: result is reused before expiry without table SELECTs' );
		foreach ( [ 'completed', 'failed', 'pending' ] as $status ) {
			$check( 1 === $writer->update_item( $id, [ 'status' => $status ] ), 'Redis: native mutation succeeds for ' . $status );
			$rows = $reader->query( $args );
			$check( 1 === count( $rows ) && $status === $rows[0]->status, 'Redis: generation change exposes ' . $status . ' immediately' );
			$keys[] = $key_method->invoke( $reader );
		}
		$check( 4 === count( array_unique( $keys ) ), 'Redis: mutations create distinct retired/current result keys' );
		foreach ( $keys as $key ) {
			$ttl = (int) $redis->ttl( $wp_object_cache->build_key( $key, $name ) );
			$check( $ttl > 0 && $ttl <= HOUR_IN_SECONDS, 'Redis: every actual result generation has a finite one-hour TTL' );
		}
		$cache_set = new ReflectionMethod( NoobRocket\Dependencies\BerlinDB\Database\Query::class, 'cache_set' );
		$cache_set->setAccessible( true );
		$metadata_group = $name . '_meta';
		$cache_set->invoke( $writer, 'owned_metadata', [ 'retained' ], $metadata_group );
		foreach ( [ [ $id, $name ], [ 'last_changed', $name ], [ 'owned_metadata', $metadata_group ] ] as $entry ) {
			$check( -1 === (int) $redis->ttl( $wp_object_cache->build_key( $entry[0], $entry[1] ) ), 'Redis: stable item/generation/metadata entries retain their existing no-expiry semantics' );
		}
		$generation = wp_cache_get( 'last_changed', $name );
		// Shorten only this fixture's known result keys after checking their real TTL.
		// This exercises actual Redis expiration without waiting one hour.
		foreach ( $keys as $key ) { $redis->pexpire( $wp_object_cache->build_key( $key, $name ), 1 ); }
		usleep( 10000 );
		wp_cache_flush_runtime();
		foreach ( $keys as $key ) {
			$check( -2 === (int) $redis->ttl( $wp_object_cache->build_key( $key, $name ) ) && false === wp_cache_get( $key, $name ), 'Redis: retired/current result key physically expires' );
		}
		$check( $generation === wp_cache_get( 'last_changed', $name ), 'Redis: result expiry preserves the current generation' );
		$check( [ 'retained' ] === wp_cache_get( 'owned_metadata', $metadata_group ), 'Redis: result expiry preserves stable metadata' );
		$check( false !== wp_cache_get( $id, $name ), 'Redis: result expiry preserves the stable item entry' );
		$reads_before = $sql_reads;
		$rows = $reader->query( $args );
		$check( 1 === count( $rows ) && (int) $rows[0]->id === (int) $id && 'pending' === $rows[0]->status, 'Redis: expired result refills correct native database rows' );
		$check( $sql_reads > $reads_before, 'Redis: expired result performs a database refill' );
		$refill_key = $key_method->invoke( $reader );
		$check( end( $keys ) === $refill_key && (int) $redis->ttl( $wp_object_cache->build_key( $refill_key, $name ) ) > 0, 'Redis: refill reuses the current generation with a new finite TTL' );
		$reads_before = $sql_reads;
		$check( 1 === count( $reader->query( $args ) ) && $reads_before === $sql_reads, 'Redis: refilled result is reused without another SELECT' );
		$writer->delete_item( $id );
		foreach ( $keys as $key ) { wp_cache_delete( $key, $name ); }
		wp_cache_delete( 'owned_metadata', $metadata_group );
		echo "PASS: actual Redis result TTL, accelerated physical expiry, stable-entry preservation and native DB refill.\n";
	}
	echo "PASS: $checks native query-cache assertions; reused/distinct writers, mutation visibility, item priming and repeated-read SQL counts.\n";
} finally {
	remove_filter( 'query', $probe );
	$table->uninstall();
	delete_option( $name . '_version' );
}
$query_fixture_completed = true;
