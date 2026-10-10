<?php

defined( 'ABSPATH' ) || exit;

if ( defined( 'WP_POSTRATINGS_VERSION' ) ) :
	/**
	 * Conflict with WP-PostRatings: Clear the cache when a post gets rated.
	 *
	 * @since 2.6.6
	 *
	 * @param int $user_id user ID.
	 * @param int $post_id post ID.
	 */
	function noobrocket_clear_cache_on_wp_postratings_rate( $user_id, $post_id ) {
		noobrocket_clean_post( $post_id );
	}
	add_action( 'rate_post', 'noobrocket_clear_cache_on_wp_postratings_rate', 10, 2 );
endif;
