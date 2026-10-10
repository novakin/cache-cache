<?php
namespace NoobRocket\Engine\Admin\Database;

use NoobRocket_WP_Background_Process;

/** Bounded current-site maintenance using the existing background process. */
class OptimizationProcess extends NoobRocket_WP_Background_Process {
	protected $prefix = 'noobrocket';
	protected $action = 'database_optimization';
	private const RESULTS = 'noobrocket_database_optimization_results';
	private const BATCH_SIZE = 50;

	public function __construct() {
		// The inherited queue uses network options. Give each site's queue its own owner.
		if ( is_multisite() ) {
			$this->action .= '_' . get_current_blog_id();
		}
		parent::__construct();
	}

	/** Do not cancel another cleanup or silently replace its scope. */
	public function start( array $categories ): bool {
		global $wpdb;
		$lock = 'noobrocket_database_optimization_start';
		$old = get_option( $lock );
		if ( $old && (int) $old < time() - 60 ) {
			$wpdb->delete( $wpdb->options, [ 'option_name' => $lock, 'option_value' => (string) $old ] );
			wp_cache_delete( $lock, 'options' );
		}
		$started = time();
		if ( ! add_option( $lock, $started, '', false ) ) {
			return false;
		}
		try {
			if ( ! $this->is_queue_empty() || $this->is_process_running() ) {
				return false;
			}
			delete_option( self::RESULTS );
			delete_transient( 'noobrocket_database_optimization_process_complete' );
			$this->data = [];
			if ( $this->is_process_cancelled() ) {
				noobrocket_direct_filesystem()->delete( NOOBROCKET_CACHE_ROOT_PATH . '.' . $this->identifier . '_process_cancelled' );
			}
			foreach ( $categories as $category ) {
				$this->push_to_queue( [ 'type' => $category, 'cursor' => 0, 'count' => 0, 'failed' => 0 ] );
			}
			$this->save()->dispatch();
			return true;
		} finally {
			$wpdb->delete( $wpdb->options, [ 'option_name' => $lock, 'option_value' => (string) $started ] );
			wp_cache_delete( $lock, 'options' );
		}
	}

	public function dispatch() {
		set_transient( 'noobrocket_database_optimization_process', 'running', HOUR_IN_SECONDS );
		return parent::dispatch();
	}

	/** Cancel every owned batch, rather than the inherited first-batch-only behavior. */
	public function cancel_process() {
		Optimization::cancel_site_queue();
	}

	/** Return the unfinished cursor/counts to the same native queue. */
	protected function task( $item ) {
		global $wpdb;
		$state = is_array( $item ) ? $item : [ 'type' => $item, 'cursor' => 0, 'count' => 0, 'failed' => 0 ];
		$type = $state['type'];
		$cursor = $state['cursor'];
		if ( 'database_optimize_tables' === $type ) {
			if ( ! isset( $state['tables'] ) ) {
				$state['tables'] = Optimization::optimizable_tables();
			}
			$ids = array_slice( $state['tables'], (int) $cursor, self::BATCH_SIZE );
		} else {
			$ids = $this->matching_ids( $type, $cursor );
			if ( null === $ids ) {
				return false;
			}
		}
		if ( ! empty( $wpdb->last_error ) ) {
			++$state['failed'];
			$this->save_results( $state );
			return false;
		}
		$processed = 0;
		foreach ( $ids as $id ) {
			if ( $this->time_exceeded() || $this->memory_exceeded() || $this->is_process_cancelled() ) {
				$this->save_results( $state );
				return $state;
			}
			try {
				$deleted = $this->delete_item( $type, $id );
			} catch ( \Throwable $error ) {
				$deleted = false;
			}
			if ( $deleted ) {
				++$state['count'];
			} else {
				++$state['failed'];
			}
			$state['cursor'] = 'database_optimize_tables' === $type ? (int) $state['cursor'] + 1 : $id;
			++$processed;
		}
		$this->save_results( $state );
		if ( 'database_optimize_tables' === $type ) {
			return $state['cursor'] < count( $state['tables'] ) ? $state : false;
		}
		return self::BATCH_SIZE === $processed ? $state : false;
	}

	/** All selections use a stable keyset, a fixed limit, and the current blog's tables. */
	private function matching_ids( string $type, $cursor ): ?array {
		global $wpdb;
		$post_conditions = [
			'database_revisions' => "post_type = 'revision'",
			'database_auto_drafts' => "post_status = 'auto-draft'",
			'database_trashed_posts' => "post_status = 'trash'",
		];
		$comment_conditions = [
			'database_spam_comments' => "comment_approved = 'spam'",
			'database_trashed_comments' => "comment_approved IN ('trash','post-trashed')",
		];
		if ( isset( $post_conditions[ $type ] ) ) {
			return $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE {$post_conditions[$type]} AND ID > %d ORDER BY ID ASC LIMIT %d", $cursor, self::BATCH_SIZE ) );
		}
		if ( isset( $comment_conditions[ $type ] ) ) {
			return $wpdb->get_col( $wpdb->prepare( "SELECT comment_ID FROM {$wpdb->comments} WHERE {$comment_conditions[$type]} AND comment_ID > %d ORDER BY comment_ID ASC LIMIT %d", $cursor, self::BATCH_SIZE ) );
		}
		if ( 'database_all_transients' === $type ) {
			// Timeout rows belong to their value; native deletion removes both once.
			return $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE " . Optimization::transient_condition() . " AND option_name > %s ORDER BY option_name ASC LIMIT %d", (string) $cursor, self::BATCH_SIZE ) );
		}
		return null;
	}

	private function delete_item( string $type, $id ): bool {
		global $wpdb;
		if ( 'database_revisions' === $type ) {
			return wp_delete_post_revision( (int) $id ) instanceof \WP_Post;
		}
		if ( in_array( $type, [ 'database_auto_drafts', 'database_trashed_posts' ], true ) ) {
			return wp_delete_post( (int) $id, true ) instanceof \WP_Post;
		}
		if ( in_array( $type, [ 'database_spam_comments', 'database_trashed_comments' ], true ) ) {
			return (bool) wp_delete_comment( (int) $id, true );
		}
		if ( 'database_all_transients' === $type ) {
			return 0 === strpos( $id, '_site_transient_' ) ? delete_site_transient( substr( $id, 16 ) ) : delete_transient( substr( $id, 11 ) );
		}
		if ( 'database_optimize_tables' === $type ) {
			// Names come only from the explicit native current-site table list.
			$messages = $wpdb->get_results( 'OPTIMIZE TABLE `' . str_replace( '`', '``', $id ) . '`' );
			if ( ! $messages ) {
				return false;
			}
			foreach ( $messages as $message ) {
				if ( 'error' === ( $message->Msg_type ?? '' ) ) {
					return false;
				}
			}
			return true;
		}
		return false;
	}

	private function save_results( array $state ): void {
		$results = get_option( self::RESULTS, [ 'counts' => [], 'failed' => [] ] );
		$results['counts'][ $state['type'] ] = $state['count'];
		$results['failed'][ $state['type'] ] = $state['failed'];
		update_option( self::RESULTS, $results, false );
	}

	protected function complete() {
		delete_transient( 'noobrocket_database_optimization_process' );
		$results = get_option( self::RESULTS, [ 'counts' => [], 'failed' => [] ] );
		if ( $this->is_process_cancelled() ) {
			// A worker can finish a native deletion after cancellation clears progress.
			// Retain totals from earlier categories in the cancellation receipt.
			$cancelled = get_transient( 'noobrocket_database_optimization_process_complete' );
			if ( is_array( $cancelled ) && 'cancelled' === ( $cancelled['status'] ?? '' ) ) {
				$results['counts'] = array_replace( $cancelled['counts'], $results['counts'] );
				$results['failed'] = array_replace( $cancelled['failed'], $results['failed'] );
			}
			$results['status'] = 'cancelled';
		} else {
			$results['status'] = array_sum( $results['failed'] ) ? 'failed' : 'complete';
		}
		set_transient( 'noobrocket_database_optimization_process_complete', $results );
		delete_option( self::RESULTS );
		parent::complete();
	}
}
