<?php
declare(strict_types=1);

namespace NoobRocket\Engine\Preload\Admin;

use NoobRocket\Engine\Preload\Controller\Queue;
use NoobRocket\Engine\Preload\Database\Tables\Cache as CacheTable;

class Settings {
	/**
	 * Preload queue.
	 *
	 * @var Queue
	 */
	private $queue;

	/**
	 * CacheTable instance
	 *
	 * @var CacheTable
	 */
	private $cache_table;

	/**
	 * Instantiate the class
	 *
	 * @param Queue      $queue Preload queue.
	 * @param CacheTable $cache_table CacheTable instance.
	 */
	public function __construct( Queue $queue, CacheTable $cache_table ) {
		$this->queue       = $queue;
		$this->cache_table = $cache_table;
	}

	/**
	 * Maybe display the preload notice.
	 *
	 * @return void
	 */
	public function maybe_display_preload_notice() {
		if ( ! $this->can_display_notice() ) {
			return;
		}

		if ( false === get_transient( 'nbr_preload_running' ) ) {
			return;
		}

		$boxes = get_user_meta( get_current_user_id(), 'noobrocket_boxes', true );

		if ( in_array( 'preload_notice', (array) $boxes, true ) ) {
			return;
		}

		$message = sprintf(
			// translators: %1$s = plugin name.
			__( '%1$s: The preload service is now active. After the initial preload it will continue to cache all your pages whenever they are purged. No further action is needed.', 'noobrocket' ),
			'<strong>NoobRocket</strong>'
		);

		noobrocket_dismiss_box( 'preload_notice' );

		noobrocket_notice_html(
			[
				'status'  => 'info',
				'message' => $message,
				'id'      => 'noobrocket-notice-preload-processing',
			]
		);
	}

	/**
	 * Checks if we can display the Preload notices.
	 *
	 * @return bool
	 */
	private function can_display_notice(): bool {
		$screen = get_current_screen();

		if (
			isset( $screen->id )
			&&
			'settings_page_noobrocket' !== $screen->id
		) {
			return false;
		}

		if ( ! current_user_can( 'noobrocket_manage_options' ) ) {
			return false;
		}

		return $this->is_enabled() && ! get_option( 'noobrocket_preload_paused', false );
	}

	/**
	 * Determines if Preload option is enabled.
	 *
	 * @return boolean
	 */
	public function is_enabled(): bool {
		return (bool) get_noobrocket_option( 'manual_preload', 0 );
	}

	/**
	 * Queue homepage warming outside the settings request.
	 *
	 * @return void
	 */
	public function preload_homepage() {
		if ( ! $this->is_enabled() || get_option( 'noobrocket_preload_paused', false ) ) {
			return;
		}

		$this->queue->add_job_preload_job_preload_url_async( home_url() );
	}

	/**
	 * Clear cache table and preload.
	 *
	 * @return void
	 */
	public function clear_and_preload() {
		$this->cache_table->truncate_cache_table();

		if ( ! $this->is_enabled() || get_option( 'noobrocket_preload_paused', false ) ) {
			return;
		}

		$this->queue->add_job_preload_job_load_initial_sitemap_async();
		$this->queue->add_job_preload_job_preload_url_async( home_url() );
	}
}
