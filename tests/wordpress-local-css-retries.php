<?php
/** Isolated native job-state/fingerprint/publication contracts, with owned temporary CSS files. */
declare(strict_types=1);
namespace NoobRocket\Engine\Optimization\RUCSS\Database\Tables { class UsedCSS { public function exists() { return true; } public function get_name() { return 'fixture_css'; } } }
namespace {
	$fixture = sys_get_temp_dir() . '/noobrocket-css-retries-' . bin2hex( random_bytes( 6 ) );
	mkdir( $fixture );
	define( 'NOOBROCKET_USED_CSS_PATH', $fixture . '/' );
	define( 'NOOBROCKET_USED_CSS_URL', 'https://site.test/cache/css/' );
	define( 'MB_IN_BYTES', 1048576 );
	$checks = 0;
	$options = [];
	function css_retry_check( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); } ++$GLOBALS['checks']; }
	function home_url() { return 'https://site.test/'; }
	function wp_parse_url( $url ) { return parse_url( $url ); }
	function trailingslashit( $value ) { return rtrim( $value, '/' ) . '/'; }
	function untrailingslashit( $value ) { return rtrim( $value, '/' ); }
	function user_trailingslashit( $value ) { return trailingslashit( $value ); }
	function get_current_blog_id() { return 1; }
	function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
	function add_option( $key, $value ) { if ( isset( $GLOBALS['options'][ $key ] ) ) { return false; } $GLOBALS['options'][ $key ] = $value; return true; }
	function wp_cache_delete() {}
	function get_noobrocket_option( $key, $default = false ) { return 'local_css_mode' === $key ? 'used' : $default; }
	function is_noobrocket_generate_caching_mobile_files() { return false; }
	function wp_generate_uuid4() { return 'aaaaaaaa-bbbb-cccc-dddd-' . bin2hex( random_bytes( 6 ) ); }
	function wp_json_encode( $value ) { return json_encode( $value ); }
	function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
	function noobrocket_mkdir_p( $directory ) { return is_dir( $directory ) || mkdir( $directory, 0777, true ); }
	function noobrocket_put_content( $path, $bytes ) { return false !== file_put_contents( $path, $bytes ); }
	function noobrocket_clean_files( $urls ) { $GLOBALS['published_urls'] = $urls; }
	function noobrocket_rrmdir( $directory ) {
		if ( ! is_dir( $directory ) ) { return; }
		if ( 0 !== strpos( realpath( $directory ), realpath( $GLOBALS['fixture'] ) ) ) { throw new RuntimeException( 'Foreign fixture cleanup.' ); }
		foreach ( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $directory, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::CHILD_FIRST ) as $file ) {
			if ( $file->isDir() ) { rmdir( $file->getPathname() ); } else { unlink( $file->getPathname() ); }
		}
		rmdir( $directory );
	}
	class RetryDatabase {
		public $options = 'fixture_options';
		public $rows = [];
		public $queries = [];
		public function prepare( $sql, ...$args ) { return [ $sql, $args ]; }
		public function insert( $table, $data ) { $data += [ 'id' => count( $this->rows ) + 1, 'error_code' => '', 'error_message' => '' ]; $this->rows[ $data['id'] ] = (object) $data; return 1; }
		public function delete( $table, $data ) { if ( isset( $data['option_name'] ) ) { unset( $GLOBALS['options'][ $data['option_name'] ] ); } else { unset( $this->rows[ $data['id'] ] ); } return 1; }
		public function get_row( $query ) {
			[ $sql, $args ] = $query;
			if ( false !== strpos( $sql, 'job_id = %s' ) ) { $row = $this->rows[ $args[0] ] ?? null; return $row && $row->job_id === $args[1] && 'local-running' === $row->status ? clone $row : null; }
			foreach ( $this->rows as $row ) { if ( $row->url === $args[0] ) { return clone $row; } }
			return null;
		}
		public function get_results( $query ) {
			$this->queries[] = $query;
			[ $sql, $args ] = $query;
			$bounded = false !== strpos( $sql, 'retries < %d' );
			return array_values( array_map( static function ( $row ) { return clone $row; }, array_filter( $this->rows, static function ( $row ) use ( $args, $bounded ) {
				if ( $row->queue_name !== $args[0] || ( $bounded && $row->retries >= $args[1] ) ) { return false; }
				if ( 'local-pending' === $row->status ) { return true; }
				$now = $bounded ? $args[2] : $args[1];
				$expired = $bounded ? $args[3] : $args[2];
				return ( 'local-failed' === $row->status && ( ! $bounded || ! in_array( $row->error_code, [ 'unsupported', 'exhausted' ], true ) ) && $row->next_retry_time <= $now ) || ( 'local-running' === $row->status && $row->modified <= $expired );
			} ) ) );
		}
		public function query( $query ) {
			$this->queries[] = $query;
			[ $sql, $args ] = $query;
			if ( false !== strpos( $sql, "error_code = 'exhausted'" ) ) {
				foreach ( $this->rows as $row ) { if ( 'local-running' === $row->status && $row->modified <= $args[1] && $row->retries >= $args[2] ) { $row->status = 'local-failed'; $row->error_code = 'exhausted'; } }
				return 0;
			}
			if ( false !== strpos( $sql, "SET status = 'local-running'" ) ) {
				$row = $this->rows[ $args[2] ];
				if ( $row->status !== $args[3] || $row->job_id !== $args[4] || $row->modified !== $args[5] ) { return 0; }
				$row->status = 'local-running'; $row->job_id = $args[0]; $row->modified = $args[1];
				if ( false !== strpos( $sql, 'retries = retries + 1' ) ) { ++$row->retries; }
				return 1;
			}
			if ( false !== strpos( $sql, "SET status = 'local-failed'" ) ) {
				$row = $this->rows[ $args[4] ];
				if ( 'local-running' !== $row->status || $row->job_id !== $args[5] ) { return 0; }
				$row->status = 'local-failed'; $row->error_code = $args[0]; $row->error_message = $args[1]; $row->modified = $args[2]; $row->next_retry_time = $args[3];
				return 1;
			}
			if ( false !== strpos( $sql, "SET status = 'local-pending'" ) ) {
				$count = 0;
				foreach ( $this->rows as $row ) { if ( 'local-failed' === $row->status && $row->queue_name === $args[2] ) { $row->status = 'local-pending'; $row->job_id = ''; $row->error_code = ''; $row->error_message = ''; $row->retries = 0; ++$count; } }
				return $count;
			}
			if ( false !== strpos( $sql, "SET status = 'local-completed'" ) ) {
				$row = $this->rows[ $args[2] ];
				if ( $row->job_id !== $args[3] || $row->hash !== $args[4] || 'local-running' !== $row->status ) { return 0; }
				$row->status = 'local-completed'; $row->css = $args[0];
				return 1;
			}
			throw new RuntimeException( 'Unexpected native job query.' );
		}
	}
	$wpdb = new RetryDatabase();
	require dirname( __DIR__ ) . '/noobrocket/inc/Engine/Optimization/LocalCSS/Jobs.php';
	$jobs = new NoobRocket\Engine\Optimization\LocalCSS\Jobs( new NoobRocket\Engine\Optimization\RUCSS\Database\Tables\UsedCSS() );
	$styles = [ [ 'href' => 'https://site.test/style.css', 'media' => 'all' ] ];
	try {
		css_retry_check( $jobs->enqueue( 'https://site.test/a/', false, 'used', md5( 'a' ), $styles, [] ), 'native enqueue creates fresh bounded-attempt state' );
		$row = $wpdb->rows[1];
		css_retry_check( 0 === $row->retries && '' === $row->job_id, 'new attempts start at zero with a usable CAS token field' );
		for ( $attempt = 1; $attempt <= 3; ++$attempt ) {
			$job = $jobs->claim();
			css_retry_check( is_array( $job ) && $row->retries === $attempt, 'claim counts the actual browser attempt, including crash exposure' );
			css_retry_check( $jobs->fail( $job['id'], $job['token'], 'HTTP 503', 'transient' ), 'owned attempt records transient failure' );
			$delay = strtotime( $row->next_retry_time . ' UTC' ) - time();
			css_retry_check( abs( $delay - 300 * ( 2 ** ( $attempt - 1 ) ) ) <= 2, 'retry uses bounded exponential backoff' );
			css_retry_check( null === $jobs->claim(), 'backoff/attempt ceiling prevents immediate repeated work' );
			$row->next_retry_time = '2000-01-01 00:00:00';
		}
		css_retry_check( 'exhausted' === $row->error_code && null === $jobs->claim(), 'exhausted work stops after three attempts even once due' );
		css_retry_check( 1 === $jobs->retry_failed() && 0 === $row->retries, 'explicit manual retry resets the existing retry field' );
		$job = $jobs->claim();
		css_retry_check( ! $jobs->fail( 1, 'stale-token', 'late result' ), 'old worker cannot mark a successor attempt failed' );
		css_retry_check( $jobs->fail( 1, $job['token'], 'Enforcing CSP', 'unsupported' ), 'known stable unsupported condition is recorded explicitly' );
		$row->next_retry_time = '2000-01-01 00:00:00';
		css_retry_check( null === $jobs->claim(), 'permanent failure waits for explicit user retry' );
		$jobs->retry_failed();
		$job = $jobs->claim();
		$results = [ [ 'href' => $styles[0]['href'], 'css' => '.fixture{color:red}', 'source_hash' => hash( 'sha256', 'original bytes' ) ] ];
		$invalid = $results; $invalid[0]['source_hash'] = 'bad';
		css_retry_check( ! $jobs->complete( 1, $job['token'], $job['fingerprint'], $invalid ), 'malformed source identity cannot be published' );
		css_retry_check( $jobs->complete( 1, $job['token'], $job['fingerprint'], $results ), 'manual recovery publishes usable files and preserves native token policy' );
		$data = json_decode( $row->css, true );
		css_retry_check( $results[0]['source_hash'] === $data['results'][0]['source_hash'] && is_readable( $data['results'][0]['path'] ), 'ready result retains the worker-computed stylesheet source identity' );
		css_retry_check( [ 'https://site.test/a' ] === $GLOBALS['published_urls'], 'publication retains targeted purge of the affected URL' );
		css_retry_check( ! $jobs->complete( 1, $job['token'], $job['fingerprint'], $results ), 'completed attempt cannot publish again' );
		$jobs->enqueue( 'https://site.test/b/', false, 'used', md5( 'b' ), $styles, [] );
		$crashed = $wpdb->rows[2]; $crashed->status = 'local-running'; $crashed->retries = 3; $crashed->modified = '2000-01-01 00:00:00';
		css_retry_check( null === $jobs->claim() && 'local-failed' === $crashed->status && 'exhausted' === $crashed->error_code, 'killed final attempt becomes a visible stopped failure' );
		echo 'PASS: local CSS retries/source identity (', $checks, " assertions; isolated native contracts/files)\n";
	} finally { noobrocket_rrmdir( $fixture ); }
}
