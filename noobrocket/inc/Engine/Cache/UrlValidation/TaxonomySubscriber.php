<?php
namespace NoobRocket\Engine\Cache\UrlValidation;

use NoobRocket\Event_Management\Subscriber_Interface;

/**
 * Subscriber for the taxonomy frontend pages.
 */
class TaxonomySubscriber extends AbstractUrlValidation implements Subscriber_Interface {
	/**
	 * {@inheritdoc}
	 */
	public static function get_subscribed_events() {
		return [
			'do_noobrocket_generate_caching_files' => 'disable_cache_on_not_valid_url',
			'noobrocket_buffer'                    => [ 'stop_optimizations_for_not_valid_url', 1 ],
		];
	}

	/**
	 * Check if we are on the taxonomy frontend page, but it's not valid url query.
	 *
	 * @return bool (True when not valid taxonomy page, False if it's a valid one)
	 */
	protected function is_not_valid_url(): bool {
		if ( ! is_category() && ! is_tag() && ! is_tax() ) {
			return false;
		}

		$term_id = get_queried_object_id();
		if ( empty( $term_id ) ) {
			return false;
		}

		global $wp;

		$term_link = get_term_link( $term_id );
		if ( is_wp_error( $term_link ) ) {
			return false;
		}

		$current_link = home_url( add_query_arg( [], $wp->request ?? '' ) );
		if ( is_paged() ) {
			$term_link = trailingslashit( $term_link ) . 'page/' . get_query_var( 'paged' );
		}

		$term_link = urldecode( untrailingslashit( $term_link ) );

		if ( urldecode( untrailingslashit( $current_link ) ) !== $term_link && ! empty( $_SERVER['REQUEST_URI'] ) ) {
			$current_link = home_url( add_query_arg( [], wp_unslash( $_SERVER['REQUEST_URI'] ) ) );// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}

		return urldecode( untrailingslashit( $current_link ) ) !== $term_link;
	}
}
