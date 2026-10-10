<?php

defined( 'ABSPATH' ) || exit;

/**
 * Call the cache server to purge the cache with Pagely hosting.
 *
 * @since 2.5.7
 *
 * @return void
 */
function noobrocket_clean_pagely() {
	if ( class_exists( 'PagelyCachePurge' ) ) {
			$purger = new PagelyCachePurge();
			$purger->purgeAll();
	}
}
add_action( 'noobrocket_after_clean_domain', 'noobrocket_clean_pagely' );
