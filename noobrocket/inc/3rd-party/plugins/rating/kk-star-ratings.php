<?php

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'BhittaniPlugin_kkStarRatings' ) ) :
	/**
	 * Conflict with kk Star Ratings: Clear the cache when a post gets rated.
	 *
	 * @since 2.5.3
	 *
	 * @param int $post_id Post ID.
	 */
	function noobrocket_clear_cache_on_kksr_rate( $post_id ) {
		noobrocket_clean_post( $post_id );
	}
	add_action( 'kksr_rate', 'noobrocket_clear_cache_on_kksr_rate' );
endif;
