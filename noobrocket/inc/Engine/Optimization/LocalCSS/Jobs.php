<?php
namespace NoobRocket\Engine\Optimization\LocalCSS;

use NoobRocket\Engine\Optimization\RUCSS\Database\Tables\UsedCSS;

/**
 * Local browser jobs share the existing per-site CSS table, with separate states.
 */
class Jobs {
	private const MAX_ATTEMPTS = 3;
	/**
	 * Per-site CSS job table.
	 *
	 * @var UsedCSS
	 */
	private $table;

	/**
	 * Whether a page purge is exposing a newly published result.
	 *
	 * @var bool
	 */
	private $publishing = false;

	public function __construct( UsedCSS $table ) {
		$this->table = $table;
	}

	public function valid_url( string $url ): bool {
		$home = wp_parse_url( home_url( '/' ) );
		$page = wp_parse_url( $url );
		if ( ! is_array( $page )
			|| isset( $page['user'] )
			|| isset( $page['pass'] )
			|| ! empty( $page['query'] )
			|| ! empty( $page['fragment'] )
		) {
			return false;
		}

		$port = function ( $parts ) {
			return $parts['port'] ?? ( 'https' === ( $parts['scheme'] ?? '' ) ? 443 : 80 );
		};

		return ( $page['scheme'] ?? '' ) === $home['scheme']
			&& strtolower( $page['host'] ?? '' ) === strtolower( $home['host'] )
			&& $port( $page ) === $port( $home )
			&& 0 === strpos( trailingslashit( $page['path'] ?? '/' ), trailingslashit( $home['path'] ?? '/' ) );
	}

	public function find( string $url, bool $mobile = false ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table->get_name()} WHERE url = %s AND is_mobile = %d AND queue_name IN ('local-critical','local-used') ORDER BY id DESC LIMIT 1",
				untrailingslashit( $url ),
				(int) $mobile
			)
		);
	}

	public function enqueue( string $url, bool $mobile, string $mode, string $fingerprint, array $styles, array $safelist ): bool {
		global $wpdb;

		if ( ! $this->valid_url( $url )
			|| ! in_array( $mode, [ 'critical', 'used' ], true )
			|| ! $styles
			|| ! preg_match( '/^[a-f0-9]{32}$/', $fingerprint )
			|| ! $this->table->exists()
		) {
			return false;
		}

		// The inherited table has no unique URL/mobile index. WordPress options do.
		$lock     = 'noobrocket_local_css_lock_' . md5( untrailingslashit( $url ) . (int) $mobile );
		$old_lock = get_option( $lock );
		if ( $old_lock && (int) $old_lock < time() - 10 ) {
			$wpdb->delete( $wpdb->options, [ 'option_name' => $lock, 'option_value' => (string) $old_lock ] );
			wp_cache_delete( $lock, 'options' );
		}

		$lock_value = time() . ':' . wp_generate_uuid4();
		if ( ! add_option( $lock, $lock_value, '', false ) ) {
			return false;
		}

		try {
			$row = $this->find( $url, $mobile );
			if ( $row && $row->queue_name === 'local-' . $mode && $row->hash === $fingerprint ) {
				return true;
			}

			if ( $row ) {
				$this->remove_row( $row );
			}

			$now = gmdate( 'Y-m-d H:i:s' );
			return false !== $wpdb->insert(
				$this->table->get_name(),
				[
					'url'             => untrailingslashit( $url ),
					'is_mobile'       => (int) $mobile,
					'queue_name'      => 'local-' . $mode,
					'hash'            => $fingerprint,
					'status'          => 'local-pending',
					'job_id'          => '',
					'retries'         => 0,
					'css'             => wp_json_encode(
						[
							'styles'   => $styles,
							'safelist' => array_values( $safelist )
						]
					),
					'modified'        => $now,
					'last_accessed'   => $now,
					'submitted_at'    => $now,
					'next_retry_time' => $now,
				]
			);
		} finally {
			$wpdb->delete( $wpdb->options, [ 'option_name' => $lock, 'option_value' => $lock_value ] );
			wp_cache_delete( $lock, 'options' );
		}
	}

	public function claim(): ?array {
		global $wpdb;

		if ( get_option( 'noobrocket_local_css_paused', false ) ) {
			return null;
		}

		$mode = get_noobrocket_option( 'local_css_mode', 'off' );
		if ( ! in_array( $mode, [ 'critical', 'used' ], true ) || ! $this->table->exists() ) {
			return null;
		}

		$now     = gmdate( 'Y-m-d H:i:s' );
		$expired = gmdate( 'Y-m-d H:i:s', time() - 600 );
		// A killed final attempt must become a visible stopped failure, not run forever.
		$wpdb->query( $wpdb->prepare( "UPDATE {$this->table->get_name()} SET status = 'local-failed',error_code = 'exhausted',error_message = 'Worker attempt expired; use Retry to restart.' WHERE queue_name = %s AND status = 'local-running' AND modified <= %s AND retries >= %d", 'local-' . $mode, $expired, self::MAX_ATTEMPTS ) );
		$rows    = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table->get_name()} WHERE queue_name = %s AND retries < %d AND (status = 'local-pending' OR (status = 'local-failed' AND COALESCE(error_code,'') NOT IN ('unsupported','exhausted') AND next_retry_time <= %s) OR (status = 'local-running' AND modified <= %s)) ORDER BY submitted_at,id LIMIT 10",
				'local-' . $mode,
				self::MAX_ATTEMPTS,
				$now,
				$expired
			)
		);

		foreach ( $rows as $row ) {
			$input = json_decode( $row->css, true );
			if ( ! $this->valid_url( $row->url ) || empty( $input['styles'] ) ) {
				$this->invalidate_urls( [ $row->url ] );
				continue;
			}

			$token   = wp_generate_uuid4();
			$changed = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$this->table->get_name()} SET status = 'local-running',job_id = %s,modified = %s,retries = retries + 1 WHERE id = %d AND status = %s AND job_id = %s AND modified = %s",
					$token,
					$now,
					$row->id,
					$row->status,
					$row->job_id,
					$row->modified
				)
			);
			if ( 1 !== $changed ) {
				continue;
			}

			$desktop = [
				[ 'width' => 1366, 'height' => 768 ],
				[ 'width' => 1024, 'height' => 768 ]
			];
			$mobile  = [
				[ 'width' => 390, 'height' => 844 ],
				[ 'width' => 768, 'height' => 1024 ]
			];

			return [
				'id'          => (int) $row->id,
				'token'       => $token,
				'url'         => user_trailingslashit( $row->url ),
				'mode'        => $mode,
				'mobile'      => (bool) $row->is_mobile,
				'fingerprint' => $row->hash,
				'styles'      => $input['styles'],
				'safelist'    => $input['safelist'] ?? [],
				'viewports'   => is_noobrocket_generate_caching_mobile_files() ? ( $row->is_mobile ? $mobile : $desktop ) : array_merge( $desktop, $mobile ),
			];
		}

		return null;
	}

	public function complete( int $id, string $token, string $fingerprint, array $results ): bool {
		global $wpdb;

		$row = $this->running( $id, $token );
		if ( ! $row
			|| $row->hash !== $fingerprint
			|| $row->queue_name !== 'local-' . get_noobrocket_option( 'local_css_mode', 'off' )
		) {
			return false;
		}

		$input    = json_decode( $row->css, true );
		$expected = array_column( $input['styles'] ?? [], 'href' );
		$actual   = array_column( $results, 'href' );
		sort( $expected );
		sort( $actual );
		if ( ! $expected || $expected !== $actual || count( $actual ) !== count( array_unique( $actual ) ) ) {
			return false;
		}

		$size = 0;
		foreach ( $results as $result ) {
			if ( ! isset( $result['css'] )
				|| ! is_string( $result['css'] )
				|| '' === trim( $result['css'] )
				|| preg_match( '#</style|@import\b#i', $result['css'] )
			) {
				return false;
			}
			if ( isset( $result['source_hash'] ) && ( ! is_string( $result['source_hash'] ) || ! preg_match( '/^[a-f0-9]{64}$/', $result['source_hash'] ) ) ) {
				return false;
			}

			$size += strlen( $result['css'] );
		}
		if ( $size > 2 * MB_IN_BYTES ) {
			return false;
		}

		$relative  = get_current_blog_id() . '/local/' . $id . '-' . $token . '/';
		$directory = NOOBROCKET_USED_CSS_PATH . $relative;
		if ( ! noobrocket_mkdir_p( $directory ) ) {
			return false;
		}

		foreach ( $results as $index => &$result ) {
			if ( ! noobrocket_put_content( $directory . $index . '.css', $result['css'] ) ) {
				noobrocket_rrmdir( $directory );
				return false;
			}
			$result['uri']  = NOOBROCKET_USED_CSS_URL . $relative . $index . '.css';
			$result['path'] = $directory . $index . '.css';
			if ( 'local-used' === $row->queue_name ) {
				// Used delivery links the published file; only critical delivery needs an inline body.
				unset( $result['css'] );
			}
		}
		unset( $result );

		$input['results'] = $results;
		$changed = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->table->get_name()} SET status = 'local-completed',css = %s,modified = %s,error_message = '' WHERE id = %d AND job_id = %s AND hash = %s AND status = 'local-running'",
				wp_json_encode( $input ),
				gmdate( 'Y-m-d H:i:s' ),
				$id,
				$token,
				$fingerprint
			)
		);
		if ( 1 !== $changed ) {
			noobrocket_rrmdir( $directory );
			return false;
		}

		// Page purge must retain the result it is about to expose, and reuse preload.
		$this->publishing = true;
		try {
			noobrocket_clean_files( [ $row->url ] );
		} finally {
			$this->publishing = false;
		}

		return true;
	}

	public function fail( int $id, string $token, string $error, string $category = 'transient' ): bool {
		global $wpdb;
		$row = $this->running( $id, $token );
		if ( ! $row ) {
			return false;
		}
		$attempts = max( 1, (int) $row->retries );
		$category = 'unsupported' === $category ? 'unsupported' : ( $attempts >= self::MAX_ATTEMPTS ? 'exhausted' : 'transient' );

		return 1 === $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->table->get_name()} SET status = 'local-failed',error_code = %s,error_message = %s,modified = %s,next_retry_time = %s WHERE id = %d AND job_id = %s AND status = 'local-running'",
				$category,
				substr( wp_strip_all_tags( $error ), 0, 1000 ),
				gmdate( 'Y-m-d H:i:s' ),
				gmdate( 'Y-m-d H:i:s', time() + min( 3600, 300 * ( 2 ** ( $attempts - 1 ) ) ) ),
				$id,
				$token
			)
		);
	}

	/** Whether local CSS job storage exists. */
	public function is_available(): bool {
		return $this->table->exists();
	}

	/** Report the saved CSS mode; Retry targets the same queue. */
	public function diagnostics(): array {
		global $wpdb;

		$data = [
			'available' => $this->is_available(),
			'mode'      => get_noobrocket_option( 'local_css_mode', 'off' ),
			'counts'    => [
				'pending' => 0,
				'running' => 0,
				'failed'  => 0,
				'ready'   => 0
			],
			'failed'    => [],
			'worker'    => get_option( 'noobrocket_local_css_worker', [] ),
			'paused'    => (bool) get_option( 'noobrocket_local_css_paused', false )
		];
		if ( ! $data['available'] || ! in_array( $data['mode'], [ 'critical', 'used' ], true ) ) {
			return $data;
		}

		$states = [
			'local-pending'   => 'pending',
			'local-running'   => 'running',
			'local-failed'    => 'failed',
			'local-completed' => 'ready'
		];
		$queue = 'local-' . $data['mode'];
		foreach ( $wpdb->get_results(
			$wpdb->prepare(
				"SELECT status,COUNT(*) AS total FROM {$this->table->get_name()} WHERE queue_name = %s GROUP BY status",
				$queue
			)
		) as $row ) {
			if ( isset( $states[ $row->status ] ) ) {
				$data['counts'][ $states[ $row->status ] ] = (int) $row->total;
			}
		}

		foreach ( $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id,url,error_code,error_message,retries,next_retry_time,modified FROM {$this->table->get_name()} WHERE queue_name = %s AND status = 'local-failed' ORDER BY modified DESC,id DESC LIMIT 10",
				$queue
			)
		) as $row ) {
			$data['failed'][] = [
				'id'       => (int) $row->id,
				'url'      => $row->url,
				'reason'   => $row->error_message,
				'category' => $row->error_code,
				'attempts' => (int) $row->retries,
				'automatic_retry' => ! in_array( $row->error_code, [ 'unsupported', 'exhausted' ], true ) && (int) $row->retries < self::MAX_ATTEMPTS,
				'modified' => $row->modified
			];
		}

		return $data;
	}

	public function retry_failed(): int {
		global $wpdb;

		$mode = get_noobrocket_option( 'local_css_mode', 'off' );
		if ( ! $this->table->exists() || ! in_array( $mode, [ 'critical', 'used' ], true ) ) {
			return 0;
		}

		return (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->table->get_name()} SET status = 'local-pending',job_id = '',error_code = '',error_message = '',retries = 0,modified = %s,next_retry_time = %s WHERE queue_name = %s AND status = 'local-failed'",
				gmdate( 'Y-m-d H:i:s' ),
				gmdate( 'Y-m-d H:i:s' ),
				'local-' . $mode
			)
		);
	}

	/**
	 * One browser worker per site; a killed process releases its lease after ten minutes.
	 */
	public function worker_start( array $runtime ): ?string {
		global $wpdb;

		$name = 'noobrocket_local_css_worker_lock';
		$old  = $this->worker_lock();
		if ( $old && (int) $old <= time() ) {
			$wpdb->delete( $wpdb->options, [ 'option_name' => $name, 'option_value' => $old ] );
			wp_cache_delete( $name, 'options' );
		}

		$token = wp_generate_uuid4();
		if ( ! add_option( $name, ( time() + 600 ) . ':' . $token, '', false ) ) {
			return null;
		}

		$this->worker_update( $token, 'starting', $runtime );
		return $token;
	}

	private function worker_lock(): string {
		global $wpdb;

		return (string) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
				'noobrocket_local_css_worker_lock'
			)
		);
	}

	public function worker_update( string $token, string $state, array $runtime = [] ): bool {
		global $wpdb;

		$old = $this->worker_lock();
		if ( ! preg_match( '/^[a-f0-9-]{36}$/', $token )
			|| ( explode( ':', $old, 2 )[1] ?? '' ) !== $token
			|| (int) $old <= time()
			|| ! in_array( $state, [ 'starting', 'idle', 'running', 'stopped', 'error', 'ready' ], true )
		) {
			return false;
		}

		$new = ( time() + 600 ) . ':' . $token;
		$wpdb->update(
			$wpdb->options,
			[ 'option_value' => $new ],
			[
				'option_name'  => 'noobrocket_local_css_worker_lock',
				'option_value' => $old
			]
		);
		wp_cache_delete( 'noobrocket_local_css_worker_lock', 'options' );
		if ( $this->worker_lock() !== $new ) {
			return false;
		}

		$data = (array) get_option( 'noobrocket_local_css_worker', [] );
		foreach ( [
			'node',
			'chromium',
			'php',
			'platform',
			'sandbox',
			'processed',
			'failed',
			'job_id',
			'error',
			'node_cpu_ms',
			'peak_node_memory_mb'
		] as $key ) {
			if ( isset( $runtime[ $key ] ) && is_scalar( $runtime[ $key ] ) ) {
				$data[ $key ] = is_string( $runtime[ $key ] ) ? substr( wp_strip_all_tags( $runtime[ $key ] ), 0, 1000 ) : $runtime[ $key ];
			}
		}

		$data['php']       = PHP_VERSION;
		$data['last_seen'] = time();
		$data['state']     = $state;
		update_option( 'noobrocket_local_css_worker', $data, false );
		return true;
	}

	public function worker_stop( string $token, string $state, array $runtime = [] ): bool {
		global $wpdb;

		if ( ! $this->worker_update( $token, $state, $runtime ) ) {
			return false;
		}

		$old = $this->worker_lock();
		if ( ( explode( ':', $old, 2 )[1] ?? '' ) !== $token ) {
			return false;
		}

		$deleted = $wpdb->delete(
			$wpdb->options,
			[
				'option_name'  => 'noobrocket_local_css_worker_lock',
				'option_value' => $old
			]
		);
		wp_cache_delete( 'noobrocket_local_css_worker_lock', 'options' );
		return 1 === $deleted;
	}

	private function running( int $id, string $token ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table->get_name()} WHERE id = %d AND job_id = %s AND status = 'local-running'",
				$id,
				$token
			)
		);
	}

	public function invalidate_urls( array $urls ): void {
		global $wpdb, $wp_rewrite;

		if ( $this->publishing || ! $this->table->exists() ) {
			return;
		}

		foreach ( array_unique( $urls ) as $url ) {
			$url        = untrailingslashit( $url );
			$pagination = $wpdb->esc_like( $url . '/' . ( $wp_rewrite->pagination_base ?? 'page' ) . '/' ) . '%';
			$rows       = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$this->table->get_name()} WHERE queue_name IN ('local-critical','local-used') AND (url = %s OR url LIKE %s)",
					$url,
					$pagination
				)
			);
			foreach ( $rows as $row ) {
				$this->remove_row( $row );
			}
		}
	}

	private function remove_row( $row ): void {
		global $wpdb;

		$wpdb->delete( $this->table->get_name(), [ 'id' => $row->id ] );
		if ( preg_match( '/^[a-f0-9-]{36}$/', $row->job_id ) ) {
			noobrocket_rrmdir( NOOBROCKET_USED_CSS_PATH . get_current_blog_id() . '/local/' . $row->id . '-' . $row->job_id . '/' );
		}
	}

	public function invalidate_all(): void {
		global $wpdb;

		if ( $this->publishing || ! $this->table->exists() ) {
			return;
		}

		$wpdb->query( "DELETE FROM {$this->table->get_name()} WHERE queue_name IN ('local-critical','local-used')" );
		noobrocket_rrmdir( NOOBROCKET_USED_CSS_PATH . get_current_blog_id() . '/local/' );
	}
}
