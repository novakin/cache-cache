<?php
/** Actual discovery/migration owners and simultaneous PHP requests on disposable MariaDB. */
$wordpress = isset( $argv[1] ) ? realpath( $argv[1] ) : false;
if ( PHP_SAPI !== 'cli' || ! $wordpress || ! is_file( $wordpress . '/wp-load.php' ) || ! is_file( $wordpress . '/.noobrocket-acceptance-site' ) ) {
	fwrite( STDERR, "Provide a marked disposable WordPress installation with a copied plugin.\n" );
	exit( 2 );
}
define( 'WP_ADMIN', true );
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
$_SERVER['HTTP_HOST'] = '127.0.0.1';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'POST';
require $wordpress . '/wp-load.php';

use NoobRocket\Engine\Preload\Database\Queries\Cache as CacheQuery;
use NoobRocket\Engine\Preload\Database\Tables\Cache as CacheTable;
use NoobRocket\Logger\Logger;

class DiscoveryFixtureTable extends CacheTable {
	public function __construct( $name ) {
		$this->name = $name;
		$this->db_version_key = $name . '_version';
		parent::__construct();
	}
}
class DiscoveryFixtureQuery extends CacheQuery {
	public $barrier = '';
	public $disappearing_id = 0;
	public $disappeared = false;
	public function __construct( $name ) {
		$this->table_name = $name;
		$this->cache_group = $name;
		parent::__construct( new Logger() );
	}
	public function get_item( $item_id = 0 ) {
		if ( $this->disappearing_id && (int) $item_id === $this->disappearing_id ) {
			$this->disappearing_id = 0;
			$this->disappeared = (bool) $this->delete_item( $item_id );
		}
		return parent::get_item( $item_id );
	}
	public function query( $args = [], bool $use_cache = true ) {
		$rows = parent::query( $args, $use_cache );
		if ( $this->barrier && isset( $args['url'] ) ) {
			$barrier = $this->barrier;
			$this->barrier = '';
			file_put_contents( $barrier . '.ready', 'ready' );
			$deadline = microtime( true ) + 15;
			while ( ! is_file( dirname( $barrier ) . '/release' ) ) {
				if ( microtime( true ) > $deadline ) { throw new RuntimeException( 'Discovery barrier timed out.' ); }
				usleep( 10000 );
			}
		}
		return $rows;
	}
}

if ( isset( $argv[2] ) && 'worker' === $argv[2] ) {
	$job = json_decode( file_get_contents( $argv[3] ), true );
	if ( ! is_array( $job ) || ! preg_match( '/^nbr_noobrocket_cache_discovery_[a-f0-9]{12}$/', $job['table'] ?? '' ) ) { throw new RuntimeException( 'Invalid owned worker table.' ); }
	new DiscoveryFixtureTable( $job['table'] );
	$query = new DiscoveryFixtureQuery( $job['table'] );
	$query->barrier = substr( $argv[3], 0, -5 );
	$result = $query->{$job['operation']}( $job['resource'] );
	file_put_contents( substr( $argv[3], 0, -5 ) . '.result', wp_json_encode( [ 'result' => $result ] ) );
	exit;
}

$checks = 0;
$check = static function ( $condition, $message ) use ( &$checks ) {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
	++$checks;
};
$check( false !== strpos( (string) $wpdb->get_var( 'SELECT VERSION()' ), 'MariaDB' ) || false !== strpos( (string) $wpdb->get_var( 'SELECT @@version_comment' ), 'MySQL' ), 'native MySQL/MariaDB required; SQLite is not concurrent database proof' );
$container = apply_filters( 'noobrocket_container', null );
$check( $container->get( 'preload_caches_query' ) instanceof CacheQuery, 'registered native discovery owner is active' );
$name = 'nbr_noobrocket_cache_discovery_' . bin2hex( random_bytes( 6 ) );
$table = new DiscoveryFixtureTable( $name );
$query = new DiscoveryFixtureQuery( $name );
$temporary = sys_get_temp_dir() . '/cache-cache-discovery-' . bin2hex( random_bytes( 8 ) );
mkdir( $temporary );
$processes = [];
$passed = false;
$run = static function ( $sql ) use ( $wpdb ) {
	$result = $wpdb->query( $sql );
	if ( false === $result ) { throw new RuntimeException( 'Native SQL failed: ' . $wpdb->last_error ); }
	return $result;
};
$parallel = static function ( array $resources ) use ( &$processes, $name, $wordpress, $temporary, $check ) {
	$directory = $temporary . '/batch-' . bin2hex( random_bytes( 4 ) );
	mkdir( $directory );
	foreach ( $resources as $i => $resource ) {
		$stem = $directory . '/' . $i;
		file_put_contents( $stem . '.json', wp_json_encode( [ 'table' => $name, 'operation' => $resource[0], 'resource' => $resource[1] ] ) );
		$process = proc_open( [ PHP_BINARY, __FILE__, $wordpress, 'worker', $stem . '.json' ], [ 0 => [ 'pipe', 'r' ], 1 => [ 'file', $stem . '.log', 'w' ], 2 => [ 'file', $stem . '.errors', 'w' ] ], $pipes );
		$check( is_resource( $process ), 'native discovery worker starts' );
		fclose( $pipes[0] );
		$processes[ $stem ] = $process;
	}
	$deadline = microtime( true ) + 20;
	do {
		$ready = count( glob( $directory . '/*.ready' ) );
		if ( $ready === count( $resources ) ) { break; }
		if ( microtime( true ) > $deadline ) { throw new RuntimeException( 'Workers did not reach simultaneous empty lookups.' ); }
		usleep( 10000 );
	} while ( true );
	file_put_contents( $directory . '/release', 'release' );
	$results = [];
	foreach ( array_keys( $processes ) as $stem ) {
		$code = proc_close( $processes[ $stem ] );
		unset( $processes[ $stem ] );
		$check( 0 === $code, 'native worker exits successfully; details retained in owned logs' );
		$results[] = json_decode( file_get_contents( $stem . '.result' ), true )['result'];
	}
	return $results;
};

try {
	$table->install();
	$check( $table->exists() && 20261010 === (int) get_option( $name . '_version' ), 'fresh native source schema installs' );
	$full_name = $table->get_name();
	$check( $table->column_exists( 'url_hash' ) && $table->index_exists( 'url_hash' ), 'fresh schema includes discovery column/index' );
	$index = $wpdb->get_row( "SHOW INDEX FROM $full_name WHERE Key_name = 'url_hash'" );
	$check( $index && 0 === (int) $index->Non_unique, 'fresh discovery index is unique' );
	$vanishing_url = 'https://discovery.invalid/disappearing';
	$vanishing_id = $query->create_or_nothing( [ 'url' => $vanishing_url ] );
	$check( $vanishing_id > 0, 'owned disappearing row is inserted' );
	$query->disappearing_id = $vanishing_id;
	$invalid_items = static function ( $rows, $owner ) use ( $query ) {
		if ( $owner === $query ) {
			$rows[] = false;
			$rows[] = new stdClass();
		}
		return $rows;
	};
	add_filter( 'the_caches', $invalid_items, 10, 2 );
	set_error_handler( static function ( $severity, $message, $file, $line ) {
		if ( error_reporting() & $severity ) { throw new ErrorException( $message, 0, $severity, $file, $line ); }
		return false;
	} );
	try {
		$check( false === $query->get_rows_by_url( $vanishing_url ) && $query->disappeared, 'native row disappearing after ID selection is ignored without diagnostics' );
		$replacement = $query->create_or_update( [ 'url' => $vanishing_url, 'status' => 'pending' ] );
		$check( $replacement > 0 && $replacement !== $vanishing_id, 'discovery recovers after row disappearance while filtered invalid items are present' );
		$check( $query->make_status_complete( $vanishing_url ), 'completion ignores false and missing-URL result items' );
		$check( $query->delete_by_url( $vanishing_url ), 'deletion ignores false and missing-URL result items' );
	} finally {
		restore_error_handler();
		remove_filter( 'the_caches', $invalid_items, 10 );
	}
	$url = 'https://discovery.invalid/article';
	$results = $parallel( [ [ 'create_or_nothing', [ 'url' => $url . '/?tracking=1' ] ], [ 'create_or_nothing', [ 'url' => $url . '?tracking=2' ] ] ] );
	$check( count( array_filter( $results ) ) === 1, 'simultaneous normalized discoveries have one insertion winner' );
	$check( count( $query->get_rows_by_url( $url ) ) === 1, 'simultaneous native discovery stores one row' );
	$query->delete_by_url( $url );
	$results = $parallel( [ [ 'create_or_update', [ 'url' => $url, 'status' => 'pending', 'is_locked' => true ] ], [ 'create_or_update', [ 'url' => $url . '/?ignored=1', 'status' => 'pending', 'is_locked' => true ] ] ] );
	$check( $results[0] > 0 && $results[0] === $results[1], 'simultaneous update discoveries recover the winning row ID' );
	$check( count( $query->get_rows_by_url( $url ) ) === 1, 'update discovery does not duplicate winner' );
	$first = $query->get_rows_by_url( $url )[0];
	$check( $first->is_locked && 'pending' === $first->status, 'update conflict preserves explicit status/lock' );
	$case_url = 'https://discovery.invalid/Article';
	$check( $query->create_or_nothing( [ 'url' => $case_url ] ) > 0, 'case-sensitive path gets distinct native row' );
	$check( count( $query->get_rows_by_url( $url ) ) === 1 && count( $query->get_rows_by_url( $case_url ) ) === 1, 'lookup separates case-folding database matches' );
	$query->make_status_complete( $url );
	$check( 'pending' === $query->get_rows_by_url( $case_url )[0]->status, 'completion isolates differing-case path' );
	$query->delete_by_url( $url );
	$check( false === $query->get_rows_by_url( $url ) && count( $query->get_rows_by_url( $case_url ) ) === 1, 'deletion isolates differing-case path' );
	$long_url = 'https://discovery.invalid/' . str_repeat( 'same-prefix-', 25 );
	$check( $query->create_or_nothing( [ 'url' => $long_url . 'first' ] ) > 0 && $query->create_or_nothing( [ 'url' => $long_url . 'second' ] ) > 0, 'different full URLs sharing 191 indexed bytes remain distinct' );
	$accent = 'https://discovery.invalid/caf';
	$check( $query->create_or_nothing( [ 'url' => $accent . 'e' ] ) > 0 && $query->create_or_nothing( [ 'url' => $accent . 'é' ] ) > 0, 'accent-folding database matches remain distinct URL paths' );
	$format = static function ( $value ) { return str_replace( '/filter-source', '/filter-target', $value ); };
	add_filter( 'noobrocket_preload_format_url', $format );
	try {
		$check( $query->create_or_nothing( [ 'url' => 'https://discovery.invalid/filter-source/?ignored=1' ] ) > 0, 'filtered discovery inserts normalized target' );
		$check( $query->create_or_update( [ 'url' => 'https://discovery.invalid/filter-source/', 'status' => 'completed' ] ) > 0, 'filtered update selects same normalized target' );
	} finally {
		remove_filter( 'noobrocket_preload_format_url', $format );
	}
	$check( 'completed' === $query->get_rows_by_url( 'https://discovery.invalid/filter-target' )[0]->status, 'filtered URLs retain existing status update behavior' );
	$check( false === $query->create_or_nothing( [ 'url' => 'https://discovery.invalid/feed.xml' ] ), 'existing resource exclusion remains authoritative' );

	// Force a digest collision without replacing hash(): another URL owns this key.
	$collision = 'https://discovery.invalid/collision';
	$id = $query->add_item( [ 'url' => $case_url . '-collision', 'url_hash' => hash( 'sha256', $collision ), 'status' => 'completed', 'is_locked' => true ] );
	$check( $id > 0, 'native colliding-key control row inserted' );
	$check( false === $query->create_or_update( [ 'url' => $collision, 'status' => 'pending' ] ), 'collision fails without updating another URL' );
	$check( 'completed' === $query->get_row_by_id( $id )->status, 'collision preserves unrelated stored status' );
	$check( ! $wpdb->suppress_errors, 'discovery restores prior WordPress diagnostic state' );
	$failure_url = 'https://discovery.invalid/update-failure';
	$failure_id = $query->create_or_update( [ 'url' => $failure_url, 'status' => 'completed', 'is_locked' => true ] );
	$reject_update = static function ( $sql ) use ( $full_name ) { return false !== strpos( $sql, "UPDATE `$full_name`" ) ? 'INVALID_SQL_FOR_DISCOVERY_UPDATE' : $sql; };
	add_filter( 'query', $reject_update );
	$previous_errors = $wpdb->suppress_errors( true );
	try {
		$check( false === $query->create_or_update( [ 'url' => $failure_url, 'status' => 'pending' ] ), 'genuine update failure cannot report the stored row as success' );
	} finally {
		remove_filter( 'query', $reject_update );
		$wpdb->suppress_errors( $previous_errors );
	}
	$check( 'completed' === $query->get_row_by_id( $failure_id )->status, 'failed update preserves original storage' );
	$run( $wpdb->prepare( "UPDATE $full_name SET is_locked=0 WHERE id=%d", $failure_id ) );
	$check( $failure_id === $query->create_or_update( [ 'url' => $failure_url, 'status' => 'completed' ] ), 'identical native update retains successful row-ID contract' );
	$check( 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT is_locked FROM $full_name WHERE id=%d", $failure_id ) ), 'omitted lock does not restore stale cached state' );
	$query->remove_all();

	// Rehearse old installed schema without touching the site's registered table.
	$run( "ALTER TABLE $full_name DROP INDEX url_hash, DROP COLUMN url_hash" );
	update_option( $name . '_version', 20261009 );
	$legacy = 'https://discovery.invalid/legacy';
	for ( $i = 0; $i < 3; ++$i ) {
		$run( $wpdb->prepare( "INSERT INTO $full_name (url,status,modified,last_accessed,is_locked) VALUES (%s,%s,'2020-01-01 00:00:00','2020-01-02 00:00:00',%d)", $legacy, $i ? 'completed' : 'in-progress', $i % 2 ) );
	}
	$before = $wpdb->get_results( "SELECT * FROM $full_name ORDER BY id", ARRAY_A );
	$check( false === $query->create_or_nothing( [ 'url' => $legacy . '-new' ] ), 'unready schema refuses unprotected discovery' );
	$check( 3 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM $full_name" ), 'unready schema preserves legacy rows' );
	$reject = static function ( $sql ) use ( $full_name ) { return false !== strpos( $sql, "ALTER TABLE $full_name ADD UNIQUE INDEX" ) ? 'INVALID_SQL_FOR_DISCOVERY_UPGRADE' : $sql; };
	add_filter( 'query', $reject );
	$previous_errors = $wpdb->suppress_errors( true );
	$failed = new DiscoveryFixtureTable( $name );
	$failed->maybe_upgrade();
	remove_filter( 'query', $reject );
	$wpdb->suppress_errors( $previous_errors );
	$check( $failed->column_exists( 'url_hash' ) && ! $failed->index_exists( 'url_hash' ), 'failed unique index publication leaves explicit partial schema' );
	$check( 20261009 === (int) get_option( $name . '_version' ), 'failed upgrade retains old marker for retry' );
	$check( false === $query->create_or_update( [ 'url' => $legacy . '-new' ] ), 'partial schema still refuses unprotected discovery' );
	$run( "ALTER TABLE $full_name ADD INDEX url_hash (url_hash)" );
	$failed->maybe_upgrade();
	$check( 20261009 === (int) get_option( $name . '_version' ), 'nonunique lookalike index cannot publish migration readiness' );
	$run( "ALTER TABLE $full_name DROP INDEX url_hash" );
	$failed->maybe_upgrade();
	$check( $failed->index_exists( 'url_hash' ) && 20261010 === (int) get_option( $name . '_version' ), 'native retry publishes unique key and version' );
	$after = $wpdb->get_results( "SELECT id,url,status,modified,last_accessed,is_locked FROM $full_name ORDER BY id", ARRAY_A );
	$check( $before === $after, 'migration preserves all row IDs, statuses, timestamps and locks' );
	$check( 3 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM $full_name WHERE url_hash IS NULL" ), 'legacy duplicate rows retain nullable keys' );
	$check( true === $failed->add_discovery_key(), 'native key migration is idempotent' );
	$check( false === $query->create_or_nothing( [ 'url' => $legacy . '/' ] ), 'discovery retains existing legacy duplicates without another insert' );
	$query->create_or_update( [ 'url' => $legacy, 'status' => 'pending' ] );
	$check( 3 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM $full_name" ), 'legacy update preserves duplicate row identities' );
	$check( 1 === (int) $wpdb->get_var( "SELECT SUM(is_locked) FROM $full_name" ), 'omitted lock fields preserve legacy lock metadata' );
	$check( 3 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM $full_name WHERE last_accessed='2020-01-02 00:00:00'" ), 'omitted access fields preserve legacy timestamps' );
	$check( $query->make_status_complete( $legacy ), 'post-migration duplicate completion still succeeds' );
	$check( 3 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM $full_name WHERE status='completed'" ), 'post-migration completion recovers every legacy row' );
	$check( $query->create_or_nothing( [ 'url' => $legacy . '-new' ] ) > 0, 'discovery succeeds after native upgrade recovery' );
	$passed = true;
	echo "PASS: $checks native discovery assertions; simultaneous PHP/MySQL requests, migration, nullable legacy rows and exact URL identity.\n";
} finally {
	foreach ( $processes as $process ) { proc_terminate( $process ); proc_close( $process ); }
	$table->uninstall();
	delete_option( $name . '_version' );
	if ( $passed ) {
		foreach ( glob( $temporary . '/*/*' ) ?: [] as $file ) { if ( is_file( $file ) ) { unlink( $file ); } }
		foreach ( glob( $temporary . '/*' ) ?: [] as $directory ) { if ( is_dir( $directory ) ) { rmdir( $directory ); } }
		rmdir( $temporary );
	} else {
		fwrite( STDERR, "Owned failure evidence retained: $temporary\n" );
	}
}
