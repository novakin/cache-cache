<?php

use NoobRocket\Dependencies\BerlinDB\Database\Table;
use NoobRocket\Engine\Optimization\RUCSS\Database\Tables\UsedCSS;
use NoobRocket\Engine\Preload\Database\Tables\Cache;
use NoobRocket\Engine\Media\AboveTheFold\Database\Tables\AboveTheFold;
use NoobRocket\Engine\Optimization\LazyRenderContent\Database\Table\LazyRenderContent;
use NoobRocket\Engine\Media\PreloadFonts\Database\Table\PreloadFonts;
use NoobRocket\Engine\Media\PreconnectExternalDomains\Database\Table\PreconnectExternalDomains;
use NoobRocket\Engine\Admin\Database\Optimization as DatabaseOptimization;

// Uninstall runs with the plugin inactive; this static owner needs no container/bootstrap.
require_once __DIR__ . '/Admin/Database/Optimization.php';

/**
 * Manages the deletion of NoobRocket data and files on uninstall.
 */
class NoobRocketUninstall {

	/**
	 * Path to the cache folder.
	 *
	 * @var string
	 */
	private $cache_path;

	/**
	 * Path to the config folder.
	 *
	 * @var string
	 */
	private $config_path;

	/**
	 * NoobRocket options.
	 *
	 * @var array
	 */
	private $options = [
		'noobrocket_settings',
		'noobrocket_analytics_notice_displayed',
		'noobrocketcdn_user_token',
		'noobrocketcdn_process',
		'noobrocket_noobrocketcdn_forced_pause_state',
		'noobrocket_hide_deactivation_form',
		'noobrocket_last_base_url',
		'noobrocket_no_licence',
		'noobrocket_last_option_hash',
		'noobrocket_debug',
		'noobrocket_noobrocketcdn_old_url',
		'noobrocket_mixpanel_optin',
		'noobrocketcdn_status_fetch_lock',
		'noobrocket_cdn_website_search_fetch_lock',
		'noobrocket_cdn_retry_pro_detection_attempts',
		'noobrocket_preload_paused',
		'noobrocket_local_css_paused',
		'noobrocket_local_css_worker',
		'noobrocket_local_css_worker_lock',
		'noobrocket_asset_rules',
		'noobrocket_settings_snapshots',
		'noobrocket_image_generation',
		'noobrocket_assets.lock',
		'noobrocket_pagespeed_api_key',
		'noobrocket_pagespeed_results',
		'noobrocket_pagespeed_lock',
		'noobrocket_database_optimization_results',
		'noobrocket_database_optimization_start',
	];

	/**
	 * NoobRocket transients.
	 *
	 * @var array
	 */
	private $transients = [
		'noobrocket_customer_data',
		'noobrocket_notice_missing_tags',
		'noobrocket_clear_cache',
		'noobrocket_cache_delivery_probe',
		'noobrocket_diagnostics_notice',
		'noobrocket_check_key_errors',
		'noobrocket_send_analytics_data',
		'noobrocket_critical_css_generation_process_running',
		'noobrocket_critical_css_generation_process_complete',
		'noobrocket_critical_css_generation_triggered',
		'noobrocketcdn_status',
		'noobrocketcdn_pricing',
		'noobrocketcdn_purge_cache_response',
		'noobrocketcdn_purchase_tracked',
		'noobrocket_cloudflare_ips',
		'noobrocket_cloudflare_is_api_keys_valid',
		'noobrocket_preload_triggered',
		'noobrocket_preload_complete',
		'noobrocket_preload_complete_time',
		'noobrocket_preload_errors',
		'noobrocket_database_optimization_process',
		'noobrocket_database_optimization_process_complete',
		'noobrocket_hide_deactivation_form',
		'nbr_preload_running',
		'noobrocket_preload_as_tables_count',
		'nbr_dynamic_lists',
		'nbr_dynamic_lists_delayjs',
		'noobrocket_domain_changed',
		'noobrocket_rucss_errors_count',
		'nbr_dynamic_lists_incompatible_plugins',
		'noobrocket_divi_notice',
		'noobrocket_saas_processing',
		'noobrocket_mod_pagespeed_enabled',
		'noobrocket_pricing',
		'noobrocket_pricing_timeout',
		'noobrocket_pricing_timeout_active',
		'noobrocket_get_refreshed_fragments_cache',
		'noobrocket_preload_previous_requests_durations',
		'noobrocket_preload_check_duration',
		'nbr_user_information_timeout_active',
		'nbr_user_information_timeout',
		'noobrocket_fonts_data_collection',
		'nbr_global_score_data',
		'noobrocket_log_file_size_check',
		'nbr_ri_recommendations',
		'noobrocket_cdn_website_search',
		'noobrocket_mixpanel_reseller_synced',
		'noobrocket_cdn_pro_detection_failed',
		'noobrocket_cdn_check_status_request_timeout',
		'noobrocket_cdn_check_status_request_timeout_active',
	];

	/**
	 * Prefixes of NoobRocket transients with dynamic suffixes.
	 *
	 * @var array
	 */
	private $transient_prefixes = [
		'noobrocket_cname_valid_',
		'noobrocket_pagespeed_notice_',
		'noobrocket_import_review_',
		'noobrocket_asset_notice_',
		'noobrocket_font_failure_',
	];

	/**
	 * NoobRocket scheduled events.
	 *
	 * @var array
	 */
	private $events = [
		'noobrocket_purge_time_event',
		'noobrocket_generate_images',
		'noobrocket_database_optimization_time_event',
		'noobrocket_cache_dir_size_check',
		'noobrocketcdn_check_subscription_status_event',
		'noobrocket_cron_deactivate_cloudflare_devmode',
		'noobrocket_saas_clean_rows_time_event',
		'noobrocket_saas_on_submit_jobs',
		'noobrocket_saas_pending_jobs',
		'noobrocket_remove_saas_failed_jobs',
		'noobrocket_performance_hints_cleanup',
		'action_scheduler_run_queue_noobrocket_rucss',
		'noobrocket_update_dynamic_lists',
		'noobrocket_preload_clean_rows_time_event',
		'noobrocket_preload_process_pending',
		'noobrocket_preload_revert_old_failed_rows',
		'noobrocket_cdn_create_request_timeout',
		'noobrocket_cdn_create_request_timeout_active',
		'noobrocket_cron_refresh_trial_customer_data',
	];

	/**
	 * NoobRocket cache directories.
	 *
	 * @var array
	 */
	private $cache_dirs = [
		'pages',
		'min',
		'busting',
		'critical-css',
		'used-css',
		'fonts',
		'background-css',
	];

	/**
	 * NoobRocket Post MetaData Entries
	 *
	 * @var array
	 */
	private $post_meta = [
		'minify_css',
		'minify_js',
		'cdn',
		'lazyload',
		'lazyload_iframes',
		'async_css',
		'defer_all_js',
		'delay_js',
		'remove_unused_css',
		'lazyload_css_bg_img',
	];

	/**
	 * Tables instances
	 *
	 * @var array
	 */
	private $tables;

	/**
	 * Constructor.
	 *
	 * @param string $cache_path            Path to the cache folder.
	 * @param string $config_path           Path to the config folder.
	 * @param array  $tables Array of tables to be dropped.
	 */
	public function __construct(
		$cache_path,
		$config_path,
		$tables
	) {
		$this->cache_path  = trailingslashit( $cache_path );
		$this->config_path = $config_path;
		$this->tables      = $tables;
	}

	/**
	 * Deletes all plugin data and files on uninstall.
	 *
	 * @since 3.5.2
	 *
	 * @return void
	 */
	public function uninstall() {
		if ( is_multisite() ) {
			// Legacy unsuffixed jobs had network scope and cannot be resumed safely per blog.
			DatabaseOptimization::clear_site_queue( true );
			foreach ( get_sites( [ 'fields' => 'ids', 'number' => 0 ] ) as $site_id ) {
				switch_to_blog( $site_id );
				try {
					$this->delete_plugin_data();
				} finally {
					restore_current_blog();
				}
			}
		} else {
			$this->delete_plugin_data();
		}
		$this->delete_cache_files();
		$this->delete_config_files();

		foreach ( $this->tables as $table ) {
			$this->delete_table( $table );
		}
	}

	/**
	 * Deletes a table
	 *
	 * @param Table $table Table instance.
	 *
	 * @return void
	 */
	private function delete_table( $table ) {
		if ( $table->exists() ) {
			$table->uninstall();
		}

		if ( ! is_multisite() ) {
			return;
		}

		foreach ( get_sites( [ 'fields' => 'ids', 'number' => 0 ] ) as $site_id ) {
			switch_to_blog( $site_id );
			try {
				if ( $table->exists() ) {
					$table->uninstall();
				}
			} finally {
				restore_current_blog();
			}
		}
	}

	/**
	 * Deletes NoobRocket options, transients and events.
	 *
	 * @since 3.5.2
	 *
	 * @return void
	 */
	private function delete_plugin_data() {
		DatabaseOptimization::clear_site_queue();
		delete_site_transient( 'noobrocket_update_data' );

		// Delete all user meta related to NoobRocket.
		delete_metadata( 'user', '', 'noobrocket_boxes', '', true );
		// Drafts and inventories belong to this site even when accounts are network-wide.
		foreach ( [ 'draft', 'inventory', 'entry', 'notice' ] as $name ) {
			delete_metadata( 'user', 0, 'noobrocket_asset_' . $name . '_' . get_current_blog_id(), '', true );
		}
		// Keep image derivatives and ownership metadata: cached HTML may still refer to them.
		delete_post_meta_by_key( '_noobrocket_image_pending' );
		delete_post_meta_by_key( '_noobrocket_image_error' );

		// Delete all post meta related to NoobRocket.
		foreach ( $this->post_meta as $post_meta ) {
			delete_post_meta_by_key( "_noobrocket_exclude_{$post_meta}" );
		}

		array_walk( $this->transients, 'delete_transient' );
		// Known active records can also live in a persistent cache provider.
		if ( function_exists( 'get_current_user_id' ) && get_current_user_id() ) {
			delete_transient( 'noobrocket_import_review_' . get_current_user_id() );
			delete_transient( 'noobrocket_asset_notice_' . get_current_user_id() );
		}
		array_walk( $this->transient_prefixes, [ $this, 'delete_transients_by_prefix' ] );
		array_walk( $this->options, 'delete_option' );

		foreach ( $this->events as $event ) {
			if ( ! wp_next_scheduled( $event ) ) {
				continue;
			}
			wp_clear_scheduled_hook( $event );
		}
	}

	/**
	 * Deletes all transients whose names start with the given prefix.
	 *
	 * Used for transients with a dynamic suffix (e.g. noobrocket_cname_valid_<md5>).
	 *
	 * @param string $prefix Transient name prefix (without the _transient_ storage prefix).
	 * @return void
	 */
	private function delete_transients_by_prefix( string $prefix ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_' . $prefix ) . '%',
				$wpdb->esc_like( '_transient_timeout_' . $prefix ) . '%'
			)
		);
	}

	/**
	 * Deletes all NoobRocket cache files.
	 *
	 * @since 3.5.2
	 *
	 * @return void
	 */
	private function delete_cache_files() {
		foreach ( $this->cache_dirs as $dir ) {
			$this->delete( $this->cache_path . $dir );
		}
	}

	/**
	 * Deletes all NoobRocket config files.
	 *
	 * @since 3.5.2
	 *
	 * @return void
	 */
	private function delete_config_files() {
		$this->delete( $this->config_path );
	}

	/**
	 * Recursively deletes files and directories.
	 *
	 * @since 3.5.2
	 *
	 * @param string $file Path to file or directory.
	 */
	private function delete( $file ) {
		if ( ! is_dir( $file ) ) {
			wp_delete_file( $file );
			return;
		}

		try {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $file, FilesystemIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::CHILD_FIRST
			);
		} catch ( UnexpectedValueException $e ) {
			return;
		}

		foreach ( $iterator as $item ) {
			if ( $item->isDir() ) {
				@rmdir( $item ); //phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir

				continue;
			}

			wp_delete_file( $item );
		}

		@rmdir( $file ); //phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	}
}
