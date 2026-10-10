<?php
namespace NoobRocket\Engine\Cache;

use WP_Post;
use NoobRocket\Event_Management\Subscriber_Interface;
use NoobRocket\Admin\Options_Data;
use NoobRocket\Logger\Logger;

/**
 * Subscriber for the cache purge actions
 *
 * @since 3.5
 */
class PurgeActionsSubscriber implements Subscriber_Interface {
	/**
	 * NoobRocket options instance
	 *
	 * @var Options_Data
	 */
	private $options;

	/**
	 * Purge instance
	 *
	 * @var Purge
	 */
	private $purge;

	/**
	 * Actual relationship changes awaiting WordPress's cache invalidation.
	 *
	 * @var array
	 */
	private $changed_relationships = [];

	/**
	 * Constructor
	 *
	 * @param Options_Data $options NoobRocket options instance.
	 * @param Purge        $purge   Purge instance.
	 */
	public function __construct( Options_Data $options, Purge $purge ) {
		$this->options = $options;
		$this->purge   = $purge;
	}

	/**
	 * {@inheritdoc}
	 */
	public static function get_subscribed_events() {
		$slug = noobrocket_get_constant( 'NOOBROCKET_SLUG' );

		return [
			'profile_update'                               => 'purge_user_cache',
			'delete_user'                                  => 'purge_user_cache',
			'create_term'                                  => [ 'maybe_purge_cache_on_term_change', 10, 3 ],
			'edit_term'                                    => [ 'maybe_purge_cache_on_term_change', 10, 3 ],
			'delete_term'                                  => [ 'maybe_purge_cache_on_term_change', 10, 3 ],
			'add_term_relationship'                        => [ 'remember_term_relationship_changes', 10, 3 ],
			'delete_term_relationships'                    => [ 'remember_deleted_term_relationships', 10, 3 ],
			'set_object_terms'                             => [ 'purge_set_term_relationships', 10, 4 ],
			'deleted_term_relationships'                   => [ 'purge_deleted_term_relationships', 10, 3 ],
			'after_noobrocket_clean_post'                      => [
				[ 'purge_dates_archives' ],
				[ 'purge_post_terms_urls' ],
			],
			'noobrocket_saas_complete_job_status'              => [ 'purge_url_cache', 100 ],
			'noobrocket_rucss_after_clearing_usedcss'          => 'purge_url_cache',
			'noobrocket_performance_hints_data_after_clearing' => 'purge_url_cache',
			'noobrocket_after_save_dynamic_lists'              => 'purge_cache_after_saving_dynamic_lists',
			'update_option_' . $slug                       => [ 'purge_cache_reject_uri_partially', 10, 2 ],
			'update_option_blog_public'                    => 'purge_cache',
			'noobrocket_upgrade'                            => [ 'on_update', 10, 2 ],
		];
	}

	/**
	 * Purges the cache of the corresponding user
	 *
	 * @since 3.5
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function purge_user_cache( $user_id ) {
		if ( ! $this->should_purge_user_cache() ) {
			return;
		}

		noobrocket_clean_user( $user_id );
	}

	/**
	 * Purges the cache when a public term is created|updated|deleted
	 *
	 * @since 3.5.5
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @return void
	 */
	public function maybe_purge_cache_on_term_change( $term_id, $tt_id, $taxonomy ) {
		if ( noobrocket_is_importing() ) {
			return;
		}

		if ( ! $this->is_taxonomy_public( $taxonomy ) ) {
			return;
		}

		noobrocket_clean_domain();
	}

	/**
	 * Records real additions/removals before a category-based permalink changes.
	 *
	 * @param int       $object_id Post ID.
	 * @param int|array $tt_ids Term taxonomy IDs.
	 * @param string    $taxonomy Taxonomy name.
	 * @return void
	 */
	public function remember_term_relationship_changes( $object_id, $tt_ids, $taxonomy ) {
		if ( ! $this->should_purge_term_relationships( $object_id, $taxonomy ) ) {
			return;
		}

		if ( ! isset( $this->changed_relationships[ $object_id ][ $taxonomy ] ) ) {
			$this->changed_relationships[ $object_id ][ $taxonomy ] = [
				'url'    => get_permalink( $object_id ),
				'tt_ids' => [],
			];
		}

		$this->changed_relationships[ $object_id ][ $taxonomy ]['tt_ids'] = array_unique(
			array_merge( $this->changed_relationships[ $object_id ][ $taxonomy ]['tt_ids'], (array) $tt_ids )
		);
	}

	/**
	 * Direct removal also emits hooks for terms never assigned to this object.
	 *
	 * @param int    $object_id Post ID.
	 * @param array  $tt_ids Term taxonomy IDs requested for removal.
	 * @param string $taxonomy Taxonomy name.
	 * @return void
	 */
	public function remember_deleted_term_relationships( $object_id, $tt_ids, $taxonomy ) {
		if ( ! $this->should_purge_term_relationships( $object_id, $taxonomy ) ) {
			return;
		}

		$current = wp_get_object_terms( $object_id, $taxonomy, [ 'fields' => 'tt_ids' ] );
		if ( is_wp_error( $current ) ) {
			return;
		}
		$removed = array_intersect( $tt_ids, $current );
		if ( $removed ) {
			$this->remember_term_relationship_changes( $object_id, $removed, $taxonomy );
		}
	}

	/**
	 * Purges additions after WordPress has cleared relationship object caches.
	 *
	 * @param int    $object_id Post ID.
	 * @param array  $terms Terms supplied to WordPress.
	 * @param array  $tt_ids Current term taxonomy IDs.
	 * @param string $taxonomy Taxonomy name.
	 * @return void
	 */
	public function purge_set_term_relationships( $object_id, $terms, $tt_ids, $taxonomy ) {
		$this->purge_term_relationship_changes( $object_id, $taxonomy );
	}

	/**
	 * Handles direct removals as well as removals during a replacement assignment.
	 *
	 * @param int    $object_id Post ID.
	 * @param array  $tt_ids Removed term taxonomy IDs.
	 * @param string $taxonomy Taxonomy name.
	 * @return void
	 */
	public function purge_deleted_term_relationships( $object_id, $tt_ids, $taxonomy ) {
		$this->purge_term_relationship_changes( $object_id, $taxonomy );
	}

	/**
	 * Purges the post's old/new URLs and the changed terms, then reuses preloading.
	 *
	 * @param int    $object_id Post ID.
	 * @param string $taxonomy Taxonomy name.
	 * @return void
	 */
	private function purge_term_relationship_changes( $object_id, $taxonomy ) {
		$changed = $this->changed_relationships[ $object_id ][ $taxonomy ] ?? null;
		unset( $this->changed_relationships[ $object_id ][ $taxonomy ] );
		if ( empty( $this->changed_relationships[ $object_id ] ) ) {
			unset( $this->changed_relationships[ $object_id ] );
		}

		if ( ! $changed || ! $this->should_purge_term_relationships( $object_id, $taxonomy ) ) {
			return;
		}

		$this->purge->purge_term_relationships( $changed['tt_ids'], $taxonomy );
		noobrocket_clean_post( $object_id );
		// The common post purger deduplicates per request; explicitly cover URL changes.
		noobrocket_clean_files( array_unique( array_filter( [ $changed['url'], get_permalink( $object_id ) ] ) ) );
	}

	/**
	 * Limits relationship invalidation to visible posts and supported taxonomies.
	 *
	 * @param int    $object_id Post ID.
	 * @param string $taxonomy Taxonomy name.
	 * @return bool
	 */
	private function should_purge_term_relationships( $object_id, $taxonomy ) {
		return ! noobrocket_is_importing()
			&& is_post_publicly_viewable( $object_id )
			&& is_object_in_taxonomy( get_post_type( $object_id ), $taxonomy )
			&& $this->is_taxonomy_public( $taxonomy )
			&& ! in_array( $taxonomy, apply_filters( 'noobrocket_exclude_post_taxonomy', [] ), true );
	}

	/**
	 * Purges cache for the dates archives of a post after cleaning the post
	 *
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public function purge_dates_archives( $post ) {
		$this->purge->purge_dates_archives( $post );
	}

	/**
	 * Purge all terms archives urls associated to a specific post.
	 *
	 * @param  WP_Post $post Post object.
	 * @return void
	 */
	public function purge_post_terms_urls( $post ) {
		$this->purge->purge_post_terms_urls( $post );
	}

	/**
	 * Checks if the given taxonomy is public
	 *
	 * @param string $name Taxonomy name.
	 * @return bool
	 */
	private function is_taxonomy_public( $name ) {
		$taxonomy = get_taxonomy( $name );

		if ( false === $taxonomy ) {
			return false;
		}

		return ( $taxonomy->public && $taxonomy->publicly_queryable );
	}

	/**
	 * Checks if the user cache should be purged
	 *
	 * @since 3.5
	 *
	 * @return boolean
	 */
	private function should_purge_user_cache() {
		if ( ! $this->options->get( 'cache_logged_user', 0 ) ) {
			return false;
		}

		// This filter is documented in /inc/functions/files.php.
		return ! (bool) apply_filters( 'noobrocket_common_cache_logged_users', false );
	}

	/**
	 * Purge cache after RUCSS
	 *
	 * @param string $url URL to be purged.
	 *
	 * @return void
	 */
	public function purge_url_cache( string $url ) {
		// Flush cache for this url.
		Logger::debug( 'RUCSS: Purge the cache for url: ' . $url );

		$this->purge->purge_url( $url );
	}

	/**
	 * Clean the whole cache
	 *
	 * @return void
	 */
	public function purge_cache() {
		noobrocket_clean_domain();
	}

	/**
	 * Purge single cache file(s) added in the Never Cache URL(s).
	 *
	 * @param array $old_value An array of previous values for the settings.
	 * @param array $value An array of submitted values for the settings.
	 * @return void
	 */
	public function purge_cache_reject_uri_partially( array $old_value, array $value ): void {
		$this->purge->purge_cache_reject_uri_partially( $old_value, $value );
	}

	/**
	 * Purge cache after saving dynamic lists.
	 *
	 * @param bool $should_purge Should purge or not.
	 *
	 * @return void
	 */
	public function purge_cache_after_saving_dynamic_lists( $should_purge = true ) {
		if ( ! $should_purge ) {
			return;
		}
		$this->purge_cache();
	}

	/**
	 * Regenerate the advanced cache file on update
	 *
	 * @param string $new_version New plugin version.
	 * @param string $old_version Previous plugin version.
	 *
	 * @return void
	 */
	public function on_update( $new_version, $old_version ) {
		if ( version_compare( $old_version, '3.23.3', '>=' ) ) {
			return;
		}
		noobrocket_generate_advanced_cache_file();
	}
}
