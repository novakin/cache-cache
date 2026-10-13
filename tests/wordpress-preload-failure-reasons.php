<?php
/** Additive preload diagnostics migration and persistence on owned native MariaDB tables. */
$wordpress = isset( $argv[1] ) ? realpath( $argv[1] ) : false;
if ( PHP_SAPI !== 'cli' || ! $wordpress || ! is_file( $wordpress . '/.noobrocket-acceptance-site' ) || ! is_file( $wordpress . '/wp-load.php' ) ) {
	fwrite( STDERR, "Provide a marked disposable WordPress installation with a copied plugin.\n" );
	exit( 2 );
}
$plugin = $wordpress . '/wp-content/plugins/noobrocket';
if ( is_link( $plugin ) || realpath( $plugin ) === realpath( dirname( __DIR__ ) . '/noobrocket' ) ) {
	throw new RuntimeException( 'Fixture requires a copied plugin.' );
}
foreach ( [ 'Controller/PreloadUrl.php', 'Database/Queries/Cache.php', 'Database/Tables/Cache.php', 'Database/Schemas/Cache.php', 'Database/Rows/CacheRow.php' ] as $owner ) {
	$path = '/inc/Engine/Preload/' . $owner;
	if ( hash_file( 'sha256', $plugin . $path ) !== hash_file( 'sha256', dirname( __DIR__ ) . '/noobrocket' . $path ) ) {
		throw new RuntimeException( 'Copied preload owner bytes differ.' );
	}
}
$temporary = tempnam( sys_get_temp_dir(), 'cache-cache-failure-reasons-' );
if ( ! $temporary || ! unlink( $temporary ) || ! mkdir( $temporary ) || realpath( $temporary ) !== $temporary ) {
	throw new RuntimeException( 'Cannot create owned cache fixture root.' );
}
define( 'NOOBROCKET_CACHE_ROOT_PATH', $temporary . '/' );
define( 'NOOBROCKET_CONFIG_PATH', $temporary . '/config/' );
define( 'WP_ADMIN', true );
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
$_SERVER['HTTP_HOST'] = '127.0.0.1:8875';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'POST';
require $wordpress . '/wp-load.php';

use NoobRocket\Engine\Preload\Database\Queries\Cache;
use NoobRocket\Engine\Preload\Database\Tables\Cache as CacheTable;
use NoobRocket\Engine\Preload\Database\Schemas\Cache as CacheSchema;
use NoobRocket\Engine\Common\Database\Queries\AbstractQuery;
use NoobRocket\Logger\Logger;

if ( false === stripos( $wpdb->get_var( 'SELECT VERSION()' ), 'mariadb' ) && false !== stripos( $wpdb->db_server_info(), 'sqlite' ) ) {
	throw new RuntimeException( 'Native MySQL/MariaDB schema proof is required.' );
}
class FailureReasonTable extends CacheTable {
	public function __construct( $name ) { $this->name = $name; $this->db_version_key = $name . '_version'; parent::__construct(); }
}
class FailureReasonQuery extends Cache {
	public function __construct( $name ) { $this->table_name = $name; $this->cache_group = $name; parent::__construct( new Logger() ); }
}
/** The prior schema's reader/writer shape, through unchanged native BerlinDB. */
class FailureReasonLegacySchema extends CacheSchema {
	public function __construct() {
		$this->columns = array_filter( $this->columns, static function ( $column ) { return 'failure_reason' !== $column['name']; } );
		parent::__construct();
	}
}
class FailureReasonLegacyQuery extends AbstractQuery {
	protected $table_schema = FailureReasonLegacySchema::class;
	public function __construct( $name ) { $this->table_name = $name; $this->cache_group = $name . '_legacy'; parent::__construct(); }
}
$checks = 0;
$check = static function ( $condition, $label ) use ( &$checks ) { if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $label ); } ++$checks; };
$name = 'nbr_noobrocket_failure_' . bin2hex( random_bytes( 6 ) );
$table = new FailureReasonTable( $name );
$full_name = $table->get_name();
$run = static function ( $sql ) use ( $wpdb ) { $result = $wpdb->query( $sql ); if ( false === $result ) { throw new RuntimeException( 'Owned SQL failed: ' . $wpdb->last_error ); } return $result; };
$reject = static function ( $sql ) use ( $full_name ) { return false !== strpos( $sql, "ALTER TABLE $full_name ADD COLUMN failure_reason" ) ? 'INVALID_SQL_FOR_FAILURE_REASON_UPGRADE' : $sql; };
$previous_errors = $wpdb->suppress_errors( true );
$native_response = null;
$native_requests = null;
$native_unpaused = static function () { return false; };
$native_delay = static function () { return 1; };
$native_duration = static function () { return 1; };
try {
	$table->install();
	$check( $table->column_exists( 'failure_reason' ) && 20261013 === (int) get_option( $name . '_version' ), 'fresh table installs additive diagnostics column and marker' );
	$run( "ALTER TABLE $full_name DROP COLUMN failure_reason" );
	update_option( $name . '_version', 20261010 );
	$run( $wpdb->prepare( "INSERT INTO $full_name (url,status,modified,last_accessed,is_locked) VALUES (%s,'failed','2020-01-01 00:00:00','2020-01-02 00:00:00',1)", 'https://failure.invalid/legacy' ) );
	$legacy_id = (int) $wpdb->insert_id;
	$before = $wpdb->get_row( "SELECT * FROM $full_name WHERE id=$legacy_id", ARRAY_A );
	add_filter( 'query', $reject );
	$failed = new FailureReasonTable( $name );
	$failed->maybe_upgrade();
	$check( ! $failed->column_exists( 'failure_reason' ) && 20261010 === (int) get_option( $name . '_version' ), 'refused ALTER preserves old schema and migration marker' );
	$old_query = new FailureReasonQuery( $name );
	$check( [ 'unavailable' => 1 ] === $old_query->get_failure_reason_counts( 1 ), 'old schema exposes unavailable evidence without referencing absent column' );
	$new_id = $old_query->create_or_nothing( [ 'url' => 'https://failure.invalid/old-schema', 'status' => 'pending' ] );
	$check( $new_id > 0 && $old_query->make_status_failed_by_id( $new_id, 'transport' ), 'failed diagnostics migration preserves existing discovery and failed status writes' );
	remove_filter( 'query', $reject );
	$failed->maybe_upgrade();
	$check( $failed->column_exists( 'failure_reason' ) && 20261013 === (int) get_option( $name . '_version' ), 'native retry verifies physical column before publishing readiness' );
	$after = $wpdb->get_row( "SELECT id,url,url_hash,status,modified,last_accessed,is_locked FROM $full_name WHERE id=$legacy_id", ARRAY_A );
	$check( $before === $after, 'migration preserves legacy identity, status, access, lock and timestamps' );
	$check( '' === $wpdb->get_row( "SELECT failure_reason FROM $full_name WHERE id=$legacy_id" )->failure_reason, 'legacy failed row receives empty unavailable reason' );
	$check( true === $failed->add_failure_reason(), 'completed migration is idempotent' );
	// Existing column plus an unadvanced marker is a safe, retryable partial publication.
	update_option( $name . '_version', 20261010 );
	$partial = new FailureReasonQuery( $name );
	$check( [ 'unavailable' => 2 ] === $partial->get_failure_reason_counts( 2 ), 'partial publication does not invent available reasons' );
	$failed->maybe_upgrade();
	$check( 20261013 === (int) get_option( $name . '_version' ), 'existing column recovers marker without destructive DDL' );
	$query = new FailureReasonQuery( $name );
	foreach ( [ 'transport', 'http_503', 'no_requests', 'cache_desktop', 'cache_mobile' ] as $reason ) {
		$check( $query->make_status_failed_by_id( $new_id, $reason ), 'bounded reason persists through native failed transition: ' . $reason );
		$stored = $query->get_row_by_id( $new_id );
		$check( 'failed' === $stored->status && $reason === $stored->failure_reason, 'row shaping retains exact bounded evidence: ' . $reason );
	}
	foreach ( [ 'https://private.invalid/?secret=raw', 'http_099', 'http_600', [ 'transport' ], new stdClass() ] as $invalid ) {
		$query->update_item( $new_id, [ 'status' => 'failed', 'failure_reason' => $invalid ] );
		$check( '' === $query->get_row_by_id( $new_id )->failure_reason, 'direct integration cannot persist malformed or private reason payload' );
	}
	$query->make_status_failed_by_id( $new_id, 'transport' );
	$query->make_status_inprogress_by_id( $new_id );
	$check( '' === $query->get_row_by_id( $new_id )->failure_reason, 'in-progress retry clears old evidence' );
	$query->make_status_failed_by_id( $new_id, 'http_503' );
	$check( $query->make_status_complete( 'https://failure.invalid/old-schema' ) && '' === $query->get_row_by_id( $new_id )->failure_reason, 'successful completion clears old evidence' );
	$query->make_status_failed_by_id( $new_id, 'cache_mobile' );
	$query->create_or_update( [ 'url' => 'https://failure.invalid/old-schema', 'status' => 'pending' ] );
	$check( '' === $query->get_row_by_id( $new_id )->failure_reason, 'discovery retry clears obsolete evidence' );
	foreach ( [ 'revert_old_failed' => 'failed', 'revert_old_in_progress' => 'in-progress', 'set_all_to_pending' => 'failed', 'revert_in_progress' => 'in-progress' ] as $method => $status ) {
		$query->update_item( $new_id, [ 'status' => 'failed', 'failure_reason' => 'transport' ] );
		$run( "UPDATE $full_name SET status='$status',modified='2020-01-01 00:00:00' WHERE id=$new_id" );
		$query->$method();
		$stored = $wpdb->get_row( "SELECT status,failure_reason FROM $full_name WHERE id=$new_id" );
		$check( $stored && 'pending' === $stored->status && '' === $stored->failure_reason, $method . ' clears evidence in existing bulk/row status path' );
	}
	$query->make_status_failed_by_id( $new_id, 'http_503' );
	$legacy = new FailureReasonLegacyQuery( $name );
	$check( $legacy->get_row_by_id( $new_id ) && $legacy->update_item( $new_id, [ 'status' => 'completed' ] ), 'previous schema can still read/write after additive upgrade' );
	$check( 'completed' === $wpdb->get_var( "SELECT status FROM $full_name WHERE id=$new_id" ), 'old-reader rollback preserves ordinary queue behavior' );
	$query->make_status_inprogress_by_id( $new_id );
	$check( '' === $query->get_row_by_id( $new_id )->failure_reason, 'new retry clears evidence retained by old-reader rollback' );
	// Actual controller and WordPress HTTP hooks persist outcomes to this owned table.
	$native_url = home_url( '/cache-cache-failure-native-' . wp_generate_uuid4() );
	$native_id = $query->create_or_nothing( [ 'url' => $native_url, 'status' => 'pending' ] );
	$parsed = get_noobrocket_parse_url( $native_url );
	$directory = NOOBROCKET_CACHE_PATH . $parsed['host'] . trailingslashit( $parsed['path'] );
	wp_mkdir_p( $directory );
	$check( strpos( realpath( $directory ), $temporary . '/' ) === 0, 'native publication fixtures stay inside owned cache root' );
	$desktop = $directory . 'index.html';
	$mobile = $directory . 'index-mobile.html';
	$native_case = ''; $request_count = 0;
	$native_response = static function ( $pre ) use ( &$native_case, &$request_count, $desktop, $mobile ) {
		++$request_count;
		if ( 'transport' === $native_case ) { return new WP_Error( 'fixture_transport_failure', 'Private request error details must not be stored.' ); }
		if ( ! in_array( $native_case, [ 'cache_desktop', 'no_requests' ], true ) ) { file_put_contents( $desktop, 'Usable owned desktop cache' ); }
		if ( ! in_array( $native_case, [ 'cache_desktop', 'cache_mobile', 'no_requests' ], true ) ) { file_put_contents( $mobile, 'Usable owned mobile cache' ); }
		return [ 'response' => [ 'code' => 'http_503' === $native_case ? 503 : ( 'http_403' === $native_case ? 403 : 200 ) ], 'headers' => [], 'body' => 'Owned response', 'cookies' => [] ];
	};
	$native_requests = static function ( $requests ) use ( &$native_case ) { return 'no_requests' === $native_case ? [] : $requests; };
	add_filter( 'pre_option_noobrocket_preload_paused', $native_unpaused );
	add_filter( 'noobrocket_preload_delay_between_requests', $native_delay );
	add_filter( 'pre_transient_noobrocket_preload_check_duration', $native_duration );
	add_filter( 'pre_http_request', $native_response, PHP_INT_MAX - 1 );
	add_filter( 'noobrocket_preload_before_preload_url', $native_requests );
	$controller = new NoobRocket\Engine\Preload\Controller\PreloadUrl( new NoobRocket\Admin\Options_Data( [ 'cache_ssl' => false, 'cache_webp' => false, 'do_caching_mobile_files' => true ] ), new NoobRocket\Engine\Preload\Controller\Queue(), $query, noobrocket_direct_filesystem() );
	foreach ( [ 'transport', 'http_403', 'http_503', 'no_requests', 'cache_desktop', 'cache_mobile', 'success' ] as $native_case ) {
		foreach ( [ $desktop, $mobile ] as $file ) { if ( is_file( $file ) ) { unlink( $file ); } }
		$query->make_status_inprogress_by_id( $native_id );
		$before_requests = $request_count;
		$controller->preload_url( $native_url );
		$stored = $query->get_row_by_id( $native_id );
		$check( ( 'success' === $native_case ? 'completed' : 'failed' ) === $stored->status && ( 'success' === $native_case ? '' : $native_case ) === $stored->failure_reason, 'actual native controller persists responsible outcome: ' . $native_case );
		$check( ( 'no_requests' === $native_case ? 0 : 2 ) === $request_count - $before_requests, 'native classification adds no HTTP work: ' . $native_case );
	}
	remove_filter( 'pre_option_noobrocket_preload_paused', $native_unpaused );
	remove_filter( 'noobrocket_preload_delay_between_requests', $native_delay );
	remove_filter( 'pre_transient_noobrocket_preload_check_duration', $native_duration );
	remove_filter( 'pre_http_request', $native_response, PHP_INT_MAX - 1 );
	remove_filter( 'noobrocket_preload_before_preload_url', $native_requests );
	$old_write_id = $legacy->add_item( [ 'url' => 'https://failure.invalid/old-write-sample', 'status' => 'pending' ] );
	$new_write_id = $query->add_item( [ 'url' => 'https://failure.invalid/new-write-sample', 'status' => 'pending' ] );
	$check( $old_write_id > 0 && $new_write_id > 0, 'matched native write samples own separate equivalent rows' );
	$write_times = [ 'prior' => [], 'reasons' => [] ];
	$write_queries = [ 'prior' => [], 'reasons' => [] ];
	for ( $i = 0; $i < 30; ++$i ) {
		$data = [ 'status' => $i % 2 ? 'pending' : 'failed', 'modified' => current_time( 'mysql', true ) ];
		foreach ( [ 'prior' => [ $legacy, $old_write_id ], 'reasons' => [ $query, $new_write_id ] ] as $arm => $sample ) {
			$queries = $wpdb->num_queries; $start = microtime( true );
			$updated = $sample[0]->update_item( $sample[1], $data + ( 'reasons' === $arm ? [ 'failure_reason' => 'transport' ] : [] ) );
			$write_times[ $arm ][] = ( microtime( true ) - $start ) * 1000;
			$write_queries[ $arm ][] = $wpdb->num_queries - $queries;
			$check( $updated, 'equivalent native status write succeeds: ' . $arm );
		}
	}
	foreach ( $write_times as $arm => $times ) {
		sort( $times );
		echo 'Measured native status write ', $arm, ': 30 samples; median_ms=', round( $times[15], 4 ), '; p95_ms=', round( $times[28], 4 ), '; SQL_counts=', implode( ',', array_unique( $write_queries[ $arm ] ) ), '.', PHP_EOL;
	}
	// Bound rendering output and measure the extra uncached query at a declared queue size.
	$run( "DELETE FROM $full_name" );
	$reasons = [ 'transport', 'http_503', 'no_requests', 'cache_desktop', 'cache_mobile', '', 'private raw value' ];
	$values = [];
	for ( $i = 0; $i < 1000; ++$i ) { $values[] = $wpdb->prepare( "(%s,'failed',%s)", 'https://failure.invalid/sample-' . $i, $reasons[ $i % 7 ] ); }
	$run( "INSERT INTO $full_name (url,status,failure_reason) VALUES " . implode( ',', $values ) );
	$counts = $query->get_failure_reason_counts( 1000 );
	$check( 6 === count( $counts ) && 1000 === array_sum( $counts ) && 143 === $counts['http_503'], 'summary preserves HTTP status and counts every failed row' );
	$check( 285 === $counts['unavailable'], 'legacy and unknown payloads aggregate as unavailable' );
	$samples = [];
	for ( $i = 0; $i < 15; ++$i ) {
		$queries = $wpdb->num_queries; $start = microtime( true );
		$query->get_failure_reason_counts( 1000 );
		$samples[] = ( microtime( true ) - $start ) * 1000;
		$check( 1 === $wpdb->num_queries - $queries, 'diagnostics adds exactly one native aggregate query' );
	}
	sort( $samples );
	echo 'Measured extra diagnostics query: 1000 failed rows; 15 samples; median_ms=', round( $samples[7], 4 ), '; p95_ms=', round( $samples[14], 4 ), '. No visitor or preload HTTP request added.', PHP_EOL;
	$values = [];
	foreach ( [ 403 => 150, 302 => 140, 201 => 120, 404 => 110, 500 => 100 ] as $status => $total ) {
		for ( $i = 0; $i < $total; ++$i ) { $values[] = $wpdb->prepare( "(%s,'failed',%s)", 'https://failure.invalid/http-' . $status . '-' . $i, 'http_' . $status ); }
	}
	$run( "INSERT INTO $full_name (url,status,failure_reason) VALUES " . implode( ',', $values ) );
	$counts = $query->get_failure_reason_counts( 1620 );
	$check( 9 === count( $counts ) && 1620 === array_sum( $counts ), 'many statuses retain fixed-size summary and complete failed-row coverage' );
	$check( 150 === $counts['http_403'] && 143 === $counts['http_503'] && 140 === $counts['http_302'] && 330 === $counts['http_other'], 'three most frequent validated statuses retain counts and an exact HTTP remainder' );
	foreach ( [ "http_503\n", "transport\n", 'HTTP_503', 'http_099' ] as $index => $invalid ) {
		$run( $wpdb->prepare( "INSERT INTO $full_name (url,status,failure_reason) VALUES (%s,'failed',%s)", 'https://failure.invalid/raw-invalid-' . $index, $invalid ) );
	}
	$counts = $query->get_failure_reason_counts( 1624 );
	$check( 9 === count( $counts ) && 1624 === array_sum( $counts ) && 289 === $counts['unavailable'] && 143 === $counts['http_503'], 'raw malformed aggregate reasons including a final newline remain counted as unavailable' );
	echo "PASS: $checks native failure-reason migration, persistence, recovery and bounded workload assertions.\n";
} finally {
	remove_filter( 'pre_option_noobrocket_preload_paused', $native_unpaused );
	remove_filter( 'noobrocket_preload_delay_between_requests', $native_delay );
	remove_filter( 'pre_transient_noobrocket_preload_check_duration', $native_duration );
	if ( $native_response ) { remove_filter( 'pre_http_request', $native_response, PHP_INT_MAX - 1 ); }
	if ( $native_requests ) { remove_filter( 'noobrocket_preload_before_preload_url', $native_requests ); }
	remove_filter( 'query', $reject );
	$table->uninstall();
	delete_option( $name . '_version' );
	$wpdb->suppress_errors( $previous_errors );
	if ( realpath( $temporary ) !== $temporary ) { throw new RuntimeException( 'Owned cache root changed before cleanup.' ); }
	noobrocket_direct_filesystem()->delete( $temporary, true );
}
