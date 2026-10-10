<?php
declare(strict_types=1);

namespace NoobRocket\Engine\Preload;

use WP_Post;
use NoobRocket\Admin\Options_Data;
use NoobRocket\Engine\Preload\Activation\Activation;
use NoobRocket\Engine\Preload\Controller\CheckExcludedTrait;
use NoobRocket\Engine\Preload\Controller\ClearCache;
use NoobRocket\Engine\Preload\Controller\LoadInitialSitemap;
use NoobRocket\Engine\Preload\Controller\PreloadUrl;
use NoobRocket\Engine\Preload\Controller\Queue;
use NoobRocket\Engine\Preload\Database\Queries\Cache;
use NoobRocket\Event_Management\Subscriber_Interface;
use NoobRocket\Dependencies\Detection\MobileDetect;
use NoobRocket\Logger\LoggerAware;
use NoobRocket\Logger\LoggerAwareInterface;
use NoobRocket\Engine\Common\Utils;

class Subscriber implements Subscriber_Interface, LoggerAwareInterface {

	use LoggerAware;
	use CheckExcludedTrait;

	/**
	 * Options instance.
	 *
	 * @var Options_Data
	 */
	protected $options;

	/**
	 * Controller to load initial tasks.
	 *
	 * @var LoadInitialSitemap
	 */
	protected $controller;

	/**
	 * Clear cache controller.
	 *
	 * @var ClearCache
	 */
	protected $clear_cache;

	/**
	 * Cache query instance.
	 *
	 * @var Cache
	 */
	private $query;

	/**
	 * Activation manager.
	 *
	 * @var Activation
	 */
	protected $activation;

	/**
	 * Mobile detector instance.
	 *
	 * @var MobileDetect
	 */
	protected $mobile_detect;

	/**
	 * Preload queue.
	 *
	 * @var Queue
	 */
	protected $queue;

	/**
	 * Creates an instance of the class.
	 *
	 * @param Options_Data       $options Options instance.
	 * @param LoadInitialSitemap $controller controller creating the initial task.
	 * @param Cache              $query Cache query instance.
	 * @param Activation         $activation Activation manager.
	 * @param MobileDetect       $mobile_detect Mobile detector instance.
	 * @param ClearCache         $clear_cache Clear cache controller.
	 * @param Queue              $queue Preload queue.
	 */
	public function __construct( Options_Data $options, LoadInitialSitemap $controller, $query, Activation $activation, MobileDetect $mobile_detect, ClearCache $clear_cache, Queue $queue ) {
		$this->options       = $options;
		$this->controller    = $controller;
		$this->query         = $query;
		$this->activation    = $activation;
		$this->mobile_detect = $mobile_detect;
		$this->clear_cache   = $clear_cache;
		$this->queue         = $queue;
	}

	/**
	 * Return an array of events that this subscriber listens to.
	 *
	 * @return array
	 */
	public static function get_subscribed_events() {
		return [
			'update_option_' . NOOBROCKET_SLUG        => [
				[ 'maybe_load_initial_sitemap', 10, 2 ],
				[ 'maybe_cancel_preload', 10, 2 ],
			],
			'noobrocket_after_process_buffer'            => 'update_cache_row',
			'noobrocket_cache_file_published'            => 'cache_file_published',
			'noobrocket_deactivation'                    => [ 'on_deactivation', 10, 2 ],
			'noobrocket_reset_preload'                   => 'on_permalink_changed',
			'permalink_structure_changed'            => 'on_permalink_changed',
			'noobrocket_domain_changed'                  => 'on_permalink_changed',
			'noobrocket_upgrade'                      => [ 'on_update', 16, 2 ],
			'noobrocket_saas_complete_job_status'        => 'clean_url',
			'noobrocket_rucss_after_clearing_usedcss'    => [ 'clean_url', 20 ],
			'noobrocket_after_automatic_cache_purge'     => 'preload_after_automatic_cache_purge',
			'noobrocket_refresh_expired_cache' => [ 'refresh_expired_cache', 10, 2 ],
			'after_noobrocket_clean_post'                => [ 'clean_partial_cache', 10, 3 ],
			'after_noobrocket_clean_term'                => [ 'clean_partial_cache', 10, 3 ],
			'after_noobrocket_clean_file'                => 'clean_url',
			'set_404'                                => 'delete_url_on_not_found',
			'noobrocket_after_clean_terms'               => 'clean_urls',
			'noobrocket_after_clean_domain'              => 'clean_full_cache',
			'delete_post'                            => 'delete_post_preload_cache',
			'pre_delete_term'                        => 'delete_term_preload_cache',
			'noobrocket_preload_format_url'              => 'format_preload_url',
			'noobrocket_preload_lock_url'                => 'lock_url',
			'noobrocket_preload_unlock_url'              => 'unlock_url',
			'noobrocket_preload_unlock_all_urls'         => 'unlock_all_urls',
			'noobrocket_preload_exclude_urls'            => [
				[ 'add_preload_excluded_uri' ],
				[ 'add_cache_reject_uri_to_excluded' ],
			],
			'noobrocket_rucss_after_clearing_failed_url' => [ 'clean_urls', 20 ],
			'transition_post_status'                 => [ 'remove_private_post', 10, 3 ],
			'noobrocket_preload_exclude'                 => [ 'exclude_private_url', 10, 2 ],
		];
	}

	/**
	 * Load first tasks from preload when preload option is enabled.
	 *
	 * @param array $old_value old configuration values.
	 * @param array $value new configuration values.
	 * @return void
	 */
	public function maybe_load_initial_sitemap( $old_value, $value ) {
		if ( ! isset( $value['manual_preload'], $old_value['manual_preload'] ) ) {
			return;
		}

		if ( $value['manual_preload'] === $old_value['manual_preload'] ) {
			return;
		}

		if ( ! $value['manual_preload'] ) {
			return;
		}

		if ( get_option( 'noobrocket_preload_paused', false ) ) {
			return;
		}

		noobrocket_renew_box( 'preload_notice' );

		$this->queue->add_job_preload_job_load_initial_sitemap_async();
	}

	/**
	 * Cancel preload when configuration from sitemap changed.
	 *
	 * @param array $old_value old configuration values.
	 * @param array $value new configuration values.
	 * @return void
	 */
	public function maybe_cancel_preload( $old_value, $value ) {
		if ( ! isset( $value['manual_preload'], $old_value['manual_preload'] ) ) {
			return;
		}

		if ( $value['manual_preload'] === $old_value['manual_preload'] ) {
			return;
		}

		if ( $value['manual_preload'] ) {
			return;
		}

		$this->controller->cancel_preload();
	}

	/**
	 * Create or update the cache row after processing the buffer
	 *
	 * @return void
	 */
	public function update_cache_row() {
		global $wp;

		if ( is_user_logged_in() ) {
			return;
		}

		if ( (bool) ! $this->options->get( 'manual_preload', true ) ) {
			return; // Bail out if preload is disabled.
		}

		$url = home_url( add_query_arg( [], $wp->request ) );

		if ( ! empty( (array) $_GET ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( $this->is_excluded_by_filter( $url ) ) {
			$this->query->delete_by_url( $url );
			return;
		}

		$this->query->create_or_update(
			[
				'url'           => $url,
				'last_accessed' => true,
			]
		);
	}

	/** Only the outer writer can report a successfully published variant. */
	public function cache_file_published( string $path ) {
		global $wp;
		if ( is_user_logged_in() || ! $this->options->get( 'manual_preload', true ) || ! empty( $_GET ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$url = home_url( add_query_arg( [], $wp->request ) );
		if ( $this->is_excluded_by_filter( $url ) ) {
			return;
		}
		$device = false !== strpos( basename( $path ), '-mobile' ) ? 'mobile' : 'desktop';
		do_action( 'noobrocket_preload_completed', $url, $device );
		$options = $this->options->get_options();
		$desktop = dirname( $path ) . '/' . preg_replace( '/^index-mobile/', 'index', basename( $path ) );
		if ( ! PreloadUrl::is_cache_file_ready( $desktop, $options ) ) {
			return;
		}
		if ( $this->options->get( 'do_caching_mobile_files', false ) ) {
			$mobile = dirname( $desktop ) . '/' . preg_replace( '/^index/', 'index-mobile', basename( $desktop ) );
			if ( ! PreloadUrl::is_cache_file_ready( $mobile, $options ) ) {
				return;
			}
		}
		$this->query->make_status_complete( $url );
	}

	/**
	 * Delete url from the Preload when a 404 is risen.
	 *
	 * @return void
	 */
	public function delete_url_on_not_found() {
		global $wp;
		$url = home_url( $wp->request );
		$this->query->delete_by_url( $url );
	}

	/**
	 * Preload on permalink changed.
	 *
	 * @return void
	 */
	public function on_permalink_changed() {
		$this->query->remove_all();
		$this->queue->cancel_pending_jobs();
		if ( ! $this->options->get( 'manual_preload', false ) ) {
			return;
		}

		$this->queue->add_job_preload_job_load_initial_sitemap_async();
	}

	/**
	 * Disable cron and jobs on update.
	 *
	 * @param string $new_version new version from the plugin.
	 * @param string $old_version old version from the plugin.
	 * @return void
	 */
	public function on_update( $new_version, $old_version ) {
		$this->activation->clean_on_update( $new_version, $old_version );
		if ( ! $this->options->get( 'manual_preload', false ) ) {
			return;
		}
		$this->activation->refresh_on_update( $new_version, $old_version );
	}

	/**
	 * Clear preload on deactivation.
	 *
	 * @return void
	 */
	public function on_deactivation( $sites_number = 0, bool $network_wide = false ) {
		\NoobRocket\Engine\Deactivation\Deactivation::for_each_deactivated_site(
			function () {
				$this->activation->deactivation();
				$this->queue->cancel_pending_jobs();
			},
			$network_wide
		);
	}

	/**
	 * Clean the url.
	 *
	 * @param string $url url.
	 * @return void
	 */
	public function clean_url( string $url ) {
		if ( ! $this->options->get( 'manual_preload', 0 ) ) {
			return;
		}
		$this->clear_cache->partial_clean( [ $url ] );
	}

	/**
	 * Preload after clearing full cache.
	 *
	 * @return void
	 */
	public function clean_full_cache() {
		if ( ! $this->options->get( 'manual_preload', 0 ) ) {
			return;
		}

		set_transient( 'nbr_preload_running', true );
		$this->queue->add_job_preload_job_check_finished_async();
		$this->clear_cache->full_clean();
	}

	/**
	 * Preload after clearing some cache.
	 *
	 * @param object $object object modified.
	 * @param array  $urls urls cleaned.
	 * @param string $lang lang from the website.
	 * @return void
	 */
	public function clean_partial_cache( $object, array $urls, $lang ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.objectFound
		if ( ! $this->options->get( 'manual_preload', false ) ) {
			return;
		}

		// Add Homepage URL to $purge_urls for preload.
		$urls[] = get_noobrocket_i18n_home_url( $lang );

		$urls = array_filter( $urls );
		$this->clear_cache->partial_clean( $urls );
	}

	/**
	 * Clean the list of urls.
	 *
	 * @param array $urls urls.
	 * @return void
	 */
	public function clean_urls( array $urls ) {
		if ( ! $this->options->get( 'manual_preload', 0 ) ) {
			return;
		}

		$this->clear_cache->partial_clean( $urls );
	}

	/**
	 * Delete URL from a post from the preload.
	 *
	 * @param int $post_id ID from the post.
	 * @return void
	 */
	public function delete_post_preload_cache( $post_id ) {
		if ( ! $this->options->get( 'manual_preload', 0 ) ) {
			return;
		}

		$url = get_permalink( $post_id );

		if ( empty( $url ) ) {
			return;
		}

		$this->clear_cache->delete_url( $url );
	}

	/**
	 * Delete URL from a term from the preload.
	 *
	 * @param int $term_id ID from the term.
	 * @return void
	 */
	public function delete_term_preload_cache( $term_id ) {
		if ( ! $this->options->get( 'manual_preload', 0 ) ) {
			return;
		}

		$url = get_term_link( (int) $term_id );

		if ( empty( $url ) ) {
			return;
		}

		$this->clear_cache->delete_url( $url );
	}

	/**
	 * Pushes URLs to preload to the queue after cache directories are purged.
	 *
	 * @since  3.4
	 *
	 * @param array $deleted {
	 *     An array of arrays, described like: {.
	 *         @type string $home_url  The home URL.
	 *         @type string $home_path Path to home.
	 *         @type bool   $logged_in True if the home path corresponds to a logged in user’s folder.
	 *         @type array  $files     A list of paths of files that have been deleted.
	 *     }
	 * }
	 */
	public function preload_after_automatic_cache_purge( $deleted ) {
		if ( ! $deleted || ! $this->options->get( 'manual_preload' ) ) {
			return;
		}

		Utils::process_deleted_cache_urls(
			$deleted,
			function ( $url ) {
				$this->clear_cache->partial_clean( [ $url ] );
			}
		);
	}

	/** Retain stale bytes only when a supported anonymous refresh can be queued. */
	public function refresh_expired_cache( bool $retained, string $url ): bool {
		if ( ! noobrocket_is_stale_refresh_enabled( $this->options->get_options() ) || get_option( 'noobrocket_preload_paused', false ) || $this->is_excluded_by_filter( $url ) ) {
			return false;
		}
		$args = [ $url ];
		if ( $this->queue->is_scheduled( 'noobrocket_preload_job_preload_url', $args ) ) {
			return true;
		}
		// Admit through the existing pending table. Its normal adaptive cron owns
		// scheduling limits and worker backpressure, including large expiry scans.
		return false !== $this->query->create_or_update( [ 'url' => $url, 'status' => 'pending' ] );
	}

	/**
	 * Remove index from url.
	 *
	 * @param string $url url to reformat.
	 *
	 * @return string
	 */
	public function format_preload_url( string $url ) {
		return preg_replace( '/(index(-https)?\.html$)|(index(-https)?\.html_gzip$)/', '', $url );
	}

	/**
	 * Lock a URL.
	 *
	 * @param string $url URL to lock.
	 *
	 * @return void
	 */
	public function lock_url( string $url ) {
		$this->query->lock( $url );
	}

	/**
	 * Unlock all URL.
	 *
	 * @return void
	 */
	public function unlock_all_urls() {
		$this->query->unlock_all();
	}

	/**
	 * Unlock a URL.
	 *
	 * @param string $url URL to unlock.
	 *
	 * @return void
	 */
	public function unlock_url( string $url ) {
		$this->query->unlock( $url );
	}

	/**
	 * Add the excluded uri from the preload to the filter.
	 *
	 * @param array $regexes regexes containing excluded uris.
	 * @return array
	 */
	public function add_preload_excluded_uri( $regexes ): array {
		$preload_excluded_uri = (array) $this->options->get( 'preload_excluded_uri', [] );

		if ( empty( $preload_excluded_uri ) ) {
			return $regexes;
		}

		return array_merge( $regexes, $preload_excluded_uri );
	}

	/**
	 * Remove private post from cache.
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post Wp post object.
	 * @return void
	 */
	public function remove_private_post( string $new_status, string $old_status, $post ) {
		if ( $new_status === $old_status ) {
			return;
		}

		if ( 'private' !== $new_status ) {
			return;
		}

		$this->delete_post_preload_cache( $post->ID );
	}

	/**
	 * Exclude private urls.
	 *
	 * @param bool   $excluded In case we want to exclude that url.
	 * @param string $url Current URL to test.
	 *
	 * @return bool Tells if it's excluded or not.
	 */
	public function exclude_private_url( $excluded, string $url ): bool {
		if ( $excluded ) {
			return true;
		}

		$is_private = ! empty( noobrocket_url_to_postid( $url, [ 'private' ] ) );

		if ( $is_private ) {
			$this->logger::debug(
				"Private URL excluded from preload: {$url}",
				[
					'method' => __METHOD__,
				]
			);
		}

		return $is_private;
	}
}
