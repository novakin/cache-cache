<?php
/** Real WordPress/BerlinDB SQL plans on a marked disposable MariaDB installation. */
$root = isset( $argv[1] ) ? realpath( $argv[1] ) : false;
$workspace = isset( $argv[2] ) ? realpath( $argv[2] ) : false;
$output = $argv[3] ?? '';
if ( PHP_SAPI !== 'cli' || ! $root || ! $workspace || ! is_file( $root . '/.noobrocket-preload-sql-site' ) || getenv( 'NOOBROCKET_PRELOAD_SQL_SITE' ) !== $root || ! $output ) {
	fwrite( STDERR, "Marked disposable SQL site and matching environment are required.\n" );
	exit( 2 );
}
define( 'WP_INSTALLING', true );
$_SERVER['HTTP_HOST'] = 'sql-proof.invalid';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/';
require $root . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
if ( ! is_blog_installed() ) {
	wp_install( 'Disposable preload SQL proof', 'proof', 'proof@example.test', false, '', 'disposable-sql-proof' );
}
require $workspace . '/noobrocket/vendor/autoload.php';
use NoobRocket\Engine\Preload\Database\Tables\Cache as CacheTable;
use NoobRocket\Engine\Preload\Database\Queries\Cache as CacheQuery;
use NoobRocket\Logger\Logger;

$checks = 0;
$check = static function ( $condition, $label ) use ( &$checks ) {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $label ); }
	++$checks;
};
$table = new CacheTable();
$table->install();
$name = $table->get_name();
$check( $table->exists(), 'native source schema installs' );
$check( strpos( $name, 'wp_' ) === 0, 'native table owns correct site prefix' );
if ( method_exists( $table, 'add_status_locked_modified_index' ) ) {
	$check( $table->index_exists( 'status_locked_modified' ), 'fresh native source schema includes index' );
}
$run = static function ( $sql ) use ( $wpdb ) {
	$result = $wpdb->query( $sql );
	if ( false === $result ) { throw new RuntimeException( $wpdb->last_error ); }
	return $result;
};
$time = static function ( $sql, $iterations = 15 ) use ( $run ) {
	$times = [];
	for ( $i = 0; $i < $iterations; ++$i ) {
		$start = microtime( true );
		$run( $sql );
		$times[] = ( microtime( true ) - $start ) * 1000;
	}
	sort( $times );
	return [ 'median_ms' => round( $times[ (int) floor( count( $times ) / 2 ) ], 4 ), 'p95_ms' => round( $times[ (int) ceil( count( $times ) * 0.95 ) - 1 ], 4 ), 'samples' => $iterations ];
};
$captured = [];
$capture = static function ( $sql ) use ( &$captured, $name ) {
	if ( preg_match( '/^SELECT/i', $sql ) && false !== strpos( $sql, $name ) ) { $captured[] = $sql; }
	return $sql;
};
add_filter( 'query', $capture );
$query = new CacheQuery( new Logger() );
$query->get_pending_jobs( 45 );
add_filter( 'noobrocket_preload_order', '__return_true' );
$query->get_pending_jobs( 45 );
remove_filter( 'noobrocket_preload_order', '__return_true' );
$query->get_status_counts();
remove_filter( 'query', $capture );
$statements = array_values( array_unique( $captured ) );
$check( count( $statements ) >= 7, 'actual native pending modified/id order and status SQL captured' );
$proof = [ 'database' => $wpdb->get_var( 'SELECT VERSION()' ), 'charset' => $wpdb->charset, 'schema' => $wpdb->get_row( "SHOW CREATE TABLE $name", ARRAY_N ), 'queries' => $statements, 'sizes' => [] ];

foreach ( [ 1000, 10000, 100000 ] as $size ) {
	if ( $table->index_exists( 'status_locked_modified' ) ) { $run( "ALTER TABLE $name DROP INDEX status_locked_modified" ); }
	$run( "TRUNCATE TABLE $name" );
	$values = [];
	for ( $i = 1; $i <= $size; ++$i ) {
		$slot = $i % 1000;
		$status = $slot < 20 ? 'pending' : ( $slot < 25 ? 'in-progress' : ( $slot < 75 ? 'failed' : 'completed' ) );
		$locked = 0 === $i % 500 ? 1 : 0;
		$date = gmdate( 'Y-m-d H:i:s', 1700000000 + ( $size - $i ) );
		$values[] = "('https://sql-proof.invalid/article-$i','$status','$date','$date',$locked)";
		if ( count( $values ) === 1000 || $i === $size ) {
			$run( "INSERT INTO $name (url,status,modified,last_accessed,is_locked) VALUES " . implode( ',', $values ) );
			$values = [];
		}
	}
	$record = [ 'rows' => $size, 'phases' => [] ];
	foreach ( [ 'before', 'after' ] as $phase ) {
		if ( 'after' === $phase ) { $run( "ALTER TABLE $name ADD KEY status_locked_modified (status(32),is_locked,modified)" ); }
		$run( "ANALYZE TABLE $name" );
		$plans = [];
		foreach ( $statements as $sql ) {
			$timing = $time( $sql );
			$plans[] = [ 'sql' => $sql, 'explain' => $wpdb->get_results( 'EXPLAIN ' . $sql, ARRAY_A ), 'analysis' => json_decode( $wpdb->get_var( 'ANALYZE FORMAT=JSON ' . $sql ), true ), 'median_ms' => $timing['median_ms'], 'p95_ms' => $timing['p95_ms'], 'samples' => $timing['samples'] ];
		}
		$start = microtime( true );
		$run( 'START TRANSACTION' );
		for ( $i = 1; $i <= 1000; ++$i ) { $run( "UPDATE $name SET status='pending', modified=DATE_ADD(modified,INTERVAL 1 SECOND) WHERE id=$i" ); }
		$run( 'ROLLBACK' );
		$update_ms = round( ( microtime( true ) - $start ) * 1000, 4 );
		$start = microtime( true );
		$run( 'START TRANSACTION' );
		$run( "INSERT INTO $name (url,status,modified,last_accessed,is_locked) SELECT CONCAT(url,'/write-test'),status,modified,last_accessed,is_locked FROM $name WHERE id<=1000" );
		$run( 'ROLLBACK' );
		$insert_ms = round( ( microtime( true ) - $start ) * 1000, 4 );
		$record['phases'][ $phase ] = [ 'queries' => $plans, '1000_status_updates_ms' => $update_ms, '1000_insert_rows_ms' => $insert_ms ];
	}
	$proof['sizes'][] = $record;
}

// Existing source migration is checked after the index decision is implemented.
if ( method_exists( $table, 'add_status_locked_modified_index' ) ) {
	$run( "ALTER TABLE $name DROP INDEX status_locked_modified" );
	update_option( 'nbr_noobrocket_cache_version', 20220927 );
	$existing_rows = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $name" );
	$upgrade = new CacheTable();
	$upgrade->maybe_upgrade();
	$check( $upgrade->index_exists( 'status_locked_modified' ), 'native old schema receives index' );
	$check( 20261009 === (int) get_option( 'nbr_noobrocket_cache_version' ), 'native successful upgrade records version' );
	$check( $existing_rows === (int) $wpdb->get_var( "SELECT COUNT(*) FROM $name" ), 'upgrade preserves all existing rows' );
	$check( true === $upgrade->add_status_locked_modified_index(), 'index migration is idempotent' );
	$run( "ALTER TABLE $name DROP INDEX status_locked_modified" );
	update_option( 'nbr_noobrocket_cache_version', 20220927 );
	$reject = static function ( $sql ) use ( $name ) { return strpos( $sql, "ALTER TABLE $name ADD INDEX" ) === 0 ? 'INVALID_SQL_FOR_MIGRATION_PROOF' : $sql; };
	add_filter( 'query', $reject );
	$wpdb->suppress_errors( true );
	$refused = new CacheTable();
	$refused->maybe_upgrade();
	remove_filter( 'query', $reject );
	$wpdb->suppress_errors( false );
	$check( ! $refused->index_exists( 'status_locked_modified' ), 'failed native DDL leaves index absent' );
	$check( 20220927 === (int) get_option( 'nbr_noobrocket_cache_version' ), 'failed native DDL retains old version for retry' );
	$refused->maybe_upgrade();
	$check( $refused->index_exists( 'status_locked_modified' ), 'next native upgrade retries failed DDL' );
	$proof['migration_checks'] = 7;
}
$proof['assertions'] = $checks;
file_put_contents( $output, wp_json_encode( $proof, JSON_PRETTY_PRINT ) );
echo "PASS: $checks native SQL assertions; 1k/10k/100k real query plans and write costs recorded.\n";
