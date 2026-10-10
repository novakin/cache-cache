<?php
namespace NoobRocket\Engine\Admin\Database;

/**
 * Handles the database optimization process.
 */
class Optimization {
	/**
	 * Background process instance
	 *
	 * @var OptimizationProcess $process Background Process instance.
	 */
	protected $process;

	/**
	 * Class constructor.
	 *
	 * @param OptimizationProcess $process Background process instance.
	 */
	public function __construct( OptimizationProcess $process ) {
		$this->process = $process;
	}

	/**
	 * Get Database options
	 *
	 * @since 3.0.4
	 *
	 * @return array
	 */
	public function get_options() {
		return [
			'database_revisions'        => __( 'Revisions', 'noobrocket' ),
			'database_auto_drafts'      => __( 'Auto Drafts', 'noobrocket' ),
			'database_trashed_posts'    => __( 'Trashed Posts', 'noobrocket' ),
			'database_spam_comments'    => __( 'Spam Comments', 'noobrocket' ),
			'database_trashed_comments' => __( 'Trashed Comments', 'noobrocket' ),
			'database_all_transients'   => __( 'Transients', 'noobrocket' ),
			'database_optimize_tables'  => __( 'Tables', 'noobrocket' ),
		];
	}

	/**
	 * Performs the database optimization
	 *
	 * @since 2.11
	 *
	 * @param array $options NoobRocket Database options.
	 */
	public function process_handler( $options ) {
		$options = array_values( array_intersect( array_keys( $this->get_options() ), $options ) );
		return $options && $this->process->start( $options );
	}

	/** Stop only this site's native cleanup queue; never flush a shared cache group. */
	public function deactivate(): void {
		$this->process->cancel_process();
	}

	/** Cancellation follows the current blog when network deactivation switches sites. */
	public static function cancel_site_queue( bool $legacy_network_queue = false ): void {
		$identifier = 'noobrocket_database_optimization';
		if ( is_multisite() && ! $legacy_network_queue ) {
			$identifier .= '_' . get_current_blog_id();
		}
		$results = get_option( 'noobrocket_database_optimization_results' );
		$running = get_transient( 'noobrocket_database_optimization_process' );
		noobrocket_direct_filesystem()->touch( NOOBROCKET_CACHE_ROOT_PATH . '.' . $identifier . '_process_cancelled' );
		$queued = self::clear_site_queue( $legacy_network_queue );
		if ( $legacy_network_queue ) {
			return;
		}
		if ( is_multisite() ) {
			wp_clear_scheduled_hook( 'noobrocket_database_optimization_cron' );
		}
		delete_option( 'noobrocket_database_optimization_start' );
		delete_transient( 'noobrocket_database_optimization_process' );
		if ( $queued || $running || is_array( $results ) ) {
			$results = is_array( $results ) ? $results : [ 'counts' => [], 'failed' => [] ];
			$results['status'] = 'cancelled';
			set_transient( 'noobrocket_database_optimization_process_complete', $results );
		}
		delete_option( 'noobrocket_database_optimization_results' );
	}

	/** Also used without plugin bootstrap by uninstall. */
	public static function clear_site_queue( bool $legacy_network_queue = false ): bool {
		global $wpdb;
		$identifier = 'noobrocket_database_optimization';
		if ( is_multisite() && ! $legacy_network_queue ) {
			$identifier .= '_' . get_current_blog_id();
		}
		$prefix = $wpdb->esc_like( $identifier . '_batch_' ) . '%';
		if ( is_multisite() ) {
			$keys = $wpdb->get_col( $wpdb->prepare( "SELECT meta_key FROM {$wpdb->sitemeta} WHERE site_id = %d AND meta_key LIKE %s", get_current_network_id(), $prefix ) );
			foreach ( $keys as $key ) {
				delete_site_option( $key );
			}
		} else {
			$keys = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $prefix ) );
			foreach ( $keys as $key ) {
				delete_option( $key );
			}
		}
		delete_site_transient( $identifier . '_process_lock' );
		wp_clear_scheduled_hook( $identifier . '_cron' );
		return ! empty( $keys );
	}

	/**
	 * Count the number of items concerned by the database cleanup
	 *
	 * @since 2.8
	 *
	 * @param string $type Item type to count.
	 * @return int Number of items for this type
	 */
	public function count_cleanup_items( $type ) {
		global $wpdb;

		$count = 0;

		switch ( $type ) {
			case 'database_revisions':
				$count = $wpdb->get_var( "SELECT COUNT(ID) FROM $wpdb->posts WHERE post_type = 'revision'" );
				break;
			case 'database_auto_drafts':
				$count = $wpdb->get_var( "SELECT COUNT(ID) FROM $wpdb->posts WHERE post_status = 'auto-draft'" );
				break;
			case 'database_trashed_posts':
				$count = $wpdb->get_var( "SELECT COUNT(ID) FROM $wpdb->posts WHERE post_status = 'trash'" );
				break;
			case 'database_spam_comments':
				$count = $wpdb->get_var( "SELECT COUNT(comment_ID) FROM $wpdb->comments WHERE comment_approved = 'spam'" );
				break;
			case 'database_trashed_comments':
				$count = $wpdb->get_var( "SELECT COUNT(comment_ID) FROM $wpdb->comments WHERE (comment_approved = 'trash' OR comment_approved = 'post-trashed')" );
				break;
			case 'database_all_transients':
				$count = $wpdb->get_var( "SELECT COUNT(option_id) FROM {$wpdb->options} WHERE " . self::transient_condition() );
				break;
			case 'database_optimize_tables':
				$count = count( self::optimizable_tables() );
				break;
		}

		return $count;
	}

	/** Displayed counts and maintenance commands share this explicit scope. */
	public static function optimizable_tables(): array {
		global $wpdb;
		$owned = array_values( $wpdb->tables( 'blog', true ) );
		if ( ! $owned ) {
			return [];
		}
		$placeholders = implode( ',', array_fill( 0, count( $owned ), '%s' ) );
		$tables = $wpdb->get_col( $wpdb->prepare( "SELECT table_name FROM information_schema.tables WHERE table_schema = %s AND Engine <> 'InnoDB' AND data_free > 0 AND table_name IN ($placeholders) ORDER BY table_name", array_merge( [ DB_NAME ], $owned ) ) );
		return array_values( array_intersect( $owned, $tables ) );
	}

	/** Site cleanup cannot delete shared network transients; exclude timeout companions. */
	public static function transient_condition(): string {
		global $wpdb;
		$condition = $wpdb->prepare( '(option_name LIKE %s AND option_name NOT LIKE %s)', $wpdb->esc_like( '_transient_' ) . '%', $wpdb->esc_like( '_transient_timeout_' ) . '%' );
		if ( ! is_multisite() ) {
			$condition = '(' . $condition . ' OR ' . $wpdb->prepare( '(option_name LIKE %s AND option_name NOT LIKE %s)', $wpdb->esc_like( '_site_transient_' ) . '%', $wpdb->esc_like( '_site_transient_timeout_' ) . '%' ) . ')';
		}
		// Deleting the running cleanup's lock would admit a second destructive process.
		return '(' . $condition . ')' . $wpdb->prepare( ' AND option_name NOT LIKE %s AND option_name NOT LIKE %s', $wpdb->esc_like( '_transient_noobrocket_database_optimization' ) . '%', $wpdb->esc_like( '_site_transient_noobrocket_database_optimization' ) . '%' );
	}
}
