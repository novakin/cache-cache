<?php
declare(strict_types=1);

namespace NoobRocket\Engine\Preload\Activation;

use NoobRocket\Engine\Activation\ActivationInterface;
use NoobRocket\Admin\Options_Data;
use NoobRocket\Engine\Preload\Controller\{PreloadUrl, Queue};
use NoobRocket\Engine\Preload\Database\Queries\Cache;

class Activation implements ActivationInterface {
	/**
	 * Preload controller
	 *
	 * @var PreloadUrl
	 */
	private $preload_url;

	/**
	 * Preload queue.
	 *
	 * @var Queue
	 */
	protected $queue;

	/**
	 * DB query.
	 *
	 * @var Cache
	 */
	protected $query;

	/**
	 * Options.
	 *
	 * @var Options_Data
	 */
	protected $options;

	/**
	 * Instantiate class.
	 *
	 * @param PreloadUrl   $preload_url PreloadUrl instance.
	 * @param Queue        $queue Preload queue.
	 * @param Cache        $query DB query.
	 * @param Options_Data $options Options.
	 */
	public function __construct( PreloadUrl $preload_url, Queue $queue, Cache $query, Options_Data $options ) {
		$this->preload_url = $preload_url;
		$this->queue       = $queue;
		$this->query       = $query;
		$this->options     = $options;
	}

	/**
	 * Launch preload on activation.
	 */
	public function activate() {
		add_action( 'noobrocket_activation', [ $this, 'preload_activation' ], 15 );
		add_action( 'noobrocket_after_activation', [ $this, 'preload_homepage' ] );
	}

	/**
	 * Run actions on activation.
	 *
	 * @return void
	 */
	public function preload_activation() {
		if ( ! get_noobrocket_option( 'manual_preload', true ) || get_option( 'noobrocket_preload_paused', false ) ) {
			return;
		}

		/**
		 * Action that fires before the preload does.
		 */
		do_action( 'noobrocket_preload_activation' );

		$this->queue->add_job_preload_job_load_initial_sitemap_async();
	}

	/**
	 * Queue homepage warming after activation prepares the cache directories.
	 *
	 * @return void
	 */
	public function preload_homepage() {
		if ( ! get_noobrocket_option( 'manual_preload', true ) || get_option( 'noobrocket_preload_paused', false ) ) {
			return;
		}
		$this->queue->add_job_preload_job_preload_url_async( home_url() );
	}

	/**
	 * Disable cron and jobs on update.
	 *
	 * @param string $new_version new version from the plugin.
	 * @param string $old_version old version from the plugin.
	 * @return void
	 */
	public function clean_on_update( $new_version, $old_version ) {
		if ( version_compare( $old_version, '3.12.0', '>=' ) ) {
			return;
		}

		$this->query->remove_all();

		$this->queue->cancel_pending_jobs();

		if ( ! wp_next_scheduled( 'noobrocket_preload_process_pending' ) ) {
			return;
		}

		wp_clear_scheduled_hook( 'noobrocket_preload_process_pending' );
	}

	/**
	 * Reload sitemap on update.
	 *
	 * @param string $new_version new version from the plugin.
	 * @param string $old_version old version from the plugin.
	 * @return void
	 */
	public function refresh_on_update( $new_version, $old_version ) {
		if ( version_compare( $old_version, '3.12.0.2', '>' ) ) {
			return;
		}
		$this->queue->add_job_preload_job_load_initial_sitemap_async();
	}

	/**
	 * Clear preload on deactivation.
	 */
	public function deactivation() {
		wp_clear_scheduled_hook( 'noobrocket_preload_clean_rows_time_event' );
		wp_clear_scheduled_hook( 'noobrocket_preload_process_pending' );
		wp_clear_scheduled_hook( 'noobrocket_preload_revert_old_failed_rows' );
	}
}
