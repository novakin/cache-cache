<?php
/** Native CSS diagnostic/retry scope over an in-memory database; no WordPress bootstrap or writes. */
declare(strict_types=1);

namespace NoobRocket\Engine\Optimization\RUCSS\Database\Tables {
	class UsedCSS {
		public $available = true;
		public function exists() { return $this->available; }
		public function get_name() { return 'fixture_css'; }
	}
}

namespace {
	$checks = 0;
	$options = [];
	function get_option( $name, $default = false ) { return $GLOBALS['options'][ $name ] ?? $default; }
	function get_noobrocket_option( $name, $default = false ) { return get_option( $name, $default ); }
	function overview_css_check( $condition, $message ) {
		if ( ! $condition ) { throw new \RuntimeException( 'FAIL: ' . $message ); }
		++$GLOBALS['checks'];
	}

	/** Run native SQL against SQLite rather than returning expected diagnostic results. */
	class OverviewCssDatabase {
		public $database;
		public $statements = [];

		public function __construct() {
			$this->database = new \SQLite3( ':memory:' );
			$this->database->enableExceptions( true );
			$this->database->exec( 'CREATE TABLE fixture_css (id INTEGER PRIMARY KEY, queue_name TEXT, status TEXT, url TEXT, error_code TEXT, error_message TEXT, retries INTEGER, next_retry_time TEXT, modified TEXT, job_id TEXT)' );
		}

		public function prepare( $sql, ...$arguments ) {
			$index = 0;
			return preg_replace_callback( '/%[sd]/', static function ( $match ) use ( $arguments, &$index ) {
				$value = $arguments[ $index++ ];
				return '%d' === $match[0] ? (string) (int) $value : "'" . \SQLite3::escapeString( (string) $value ) . "'";
			}, $sql );
		}

		public function get_results( $sql ) {
			$this->statements[] = $sql;
			$result = $this->database->query( $sql );
			$rows = [];
			while ( $row = $result->fetchArray( SQLITE3_ASSOC ) ) { $rows[] = (object) $row; }
			return $rows;
		}

		public function query( $sql ) {
			$this->statements[] = $sql;
			$this->database->exec( $sql );
			return $this->database->changes();
		}

		public function seed(): void {
			$this->database->exec( 'DELETE FROM fixture_css' );
			$fixtures = [
				[ 'local-critical', 'local-pending', '', 0 ],
				[ 'local-critical', 'local-running', '', 1 ],
				[ 'local-critical', 'local-completed', '', 1 ],
				[ 'local-critical', 'local-failed', 'transient', 1 ],
				[ 'local-critical', 'local-failed', 'exhausted', 3 ],
				[ 'local-critical', 'unrelated-state', '', 0 ],
				[ 'local-used', 'local-pending', '', 0 ],
				[ 'local-used', 'local-pending', '', 0 ],
				[ 'local-used', 'local-completed', '', 1 ],
				[ 'local-used', 'local-failed', 'unsupported', 3 ],
				[ 'foreign-queue', 'local-failed', 'transient', 1 ],
			];
			foreach ( $fixtures as $offset => [ $queue, $status, $error, $retries ] ) {
				$id = $offset + 1;
				$this->database->exec( $this->prepare(
					'INSERT INTO fixture_css VALUES (%d, %s, %s, %s, %s, %s, %d, %s, %s, %s)',
					$id, $queue, $status, 'https://site.test/page-' . $id . '/', $error, 'Fixture error ' . $id,
					$retries, '2000-01-01 00:00:00', '2026-10-01 00:00:00', 'owned-job-' . $id
				) );
			}
			$this->statements = [];
		}

		public function snapshot( string $queue ): array {
			$result = $this->database->query( $this->prepare( 'SELECT * FROM fixture_css WHERE queue_name != %s ORDER BY id', $queue ) );
			$rows = [];
			while ( $row = $result->fetchArray( SQLITE3_ASSOC ) ) { $rows[] = $row; }
			return $rows;
		}
	}

	$wpdb = new OverviewCssDatabase();
	$table = new \NoobRocket\Engine\Optimization\RUCSS\Database\Tables\UsedCSS();
	require dirname( __DIR__ ) . '/noobrocket/inc/Engine/Optimization/LocalCSS/Jobs.php';
	$jobs = new \NoobRocket\Engine\Optimization\LocalCSS\Jobs( $table );
	$zero_counts = [ 'pending' => 0, 'running' => 0, 'failed' => 0, 'ready' => 0 ];

	foreach ( [
		'critical' => [ [ 'pending' => 1, 'running' => 1, 'failed' => 2, 'ready' => 1 ], [ 4, 5 ] ],
		'used' => [ [ 'pending' => 2, 'running' => 0, 'failed' => 1, 'ready' => 1 ], [ 10 ] ],
	] as $mode => [ $expected_counts, $failed_ids ] ) {
		$options = [ 'local_css_mode' => $mode ];
		$wpdb->seed();
		$other_rows = $wpdb->snapshot( 'local-' . $mode );
		$diagnostics = $jobs->diagnostics();
		overview_css_check( $expected_counts === $diagnostics['counts'], $mode . ' diagnostics count only its selected queue and recognized states' );
		$expected_urls = array_map( static function ( $id ) { return 'https://site.test/page-' . $id . '/'; }, $failed_ids );
		$actual_urls = array_column( $diagnostics['failed'], 'url' );
		sort( $expected_urls );
		sort( $actual_urls );
		overview_css_check( $expected_urls === $actual_urls, $mode . ' failure evidence excludes other modes and foreign queues' );
		overview_css_check( count( $failed_ids ) === $jobs->retry_failed(), $mode . ' Retry affects exactly the failures shown by diagnostics' );
		overview_css_check( $other_rows === $wpdb->snapshot( 'local-' . $mode ), $mode . ' Retry preserves every other queue row exactly' );
		$after = $jobs->diagnostics();
		$expected_after = $expected_counts;
		$expected_after['pending'] += $expected_after['failed'];
		$expected_after['failed'] = 0;
		overview_css_check( $expected_after === $after['counts'] && [] === $after['failed'], $mode . ' Retry moves the same displayed failures into its pending count' );
		overview_css_check( 0 === $jobs->retry_failed(), $mode . ' repeated Retry leaves unrelated failed rows untouched' );
	}

	foreach ( [ 'off', 'invalid-mode' ] as $mode ) {
		$options = [ 'local_css_mode' => $mode ];
		$wpdb->seed();
		$diagnostics = $jobs->diagnostics();
		overview_css_check( $zero_counts === $diagnostics['counts'] && [] === $diagnostics['failed'], $mode . ' diagnostics do not claim active generation from retained rows' );
		overview_css_check( [] === $wpdb->statements, $mode . ' diagnostics avoid irrelevant queue reads' );
		overview_css_check( 0 === $jobs->retry_failed() && null === $jobs->claim(), $mode . ' cannot retry or claim generation work' );
		overview_css_check( [] === $wpdb->statements, $mode . ' inactive actions perform no database statements' );
	}

	foreach ( [ 'critical', 'used' ] as $mode ) {
		$options = [ 'local_css_mode' => $mode, 'noobrocket_local_css_paused' => true ];
		$wpdb->seed();
		overview_css_check( null === $jobs->claim() && [] === $wpdb->statements, $mode . ' pause prevents any worker database work' );
		$diagnostics = $jobs->diagnostics();
		overview_css_check( $diagnostics['paused'] && $diagnostics['counts']['pending'] > 0, $mode . ' paused diagnostics retain the selected pending-work evidence' );
	}

	$options = [ 'local_css_mode' => 'critical' ];
	$table->available = false;
	$wpdb->seed();
	$diagnostics = $jobs->diagnostics();
	overview_css_check( ! $diagnostics['available'] && $zero_counts === $diagnostics['counts'] && [] === $diagnostics['failed'], 'missing storage returns unavailable with empty evidence' );
	overview_css_check( 0 === $jobs->retry_failed() && null === $jobs->claim() && [] === $wpdb->statements, 'missing storage cannot read, retry or claim jobs' );
	$wpdb->database->close();
	echo 'PASS: overview CSS queue scope (', $checks, " assertions; native Jobs over isolated in-memory SQLite)\n";
}
