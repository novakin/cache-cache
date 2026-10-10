<?php
namespace NoobRocket\Engine\Optimization\LocalCSS;

use NoobRocket\Event_Management\Subscriber_Interface;

class Subscriber implements Subscriber_Interface {
	/**
	 * Local CSS job storage and invalidation.
	 *
	 * @var Jobs
	 */
	private $jobs;

	/**
	 * Frontend stylesheet delivery.
	 *
	 * @var Frontend
	 */
	private $frontend;

	/**
	 * Permalinks captured before post updates.
	 *
	 * @var array
	 */
	private $old_urls = [];

	public function __construct( Jobs $jobs, Frontend $frontend ) {
		$this->jobs     = $jobs;
		$this->frontend = $frontend;
	}

	public static function get_subscribed_events(): array {
		return [
			'noobrocket_buffer'                        => [ 'optimize', 1 ],
			'noobrocket_host_fonts_locally_inline_css' => [ 'inline_local_fonts', 0 ],
			'after_noobrocket_clean_files'             => 'invalidate_urls',
			'noobrocket_after_clean_terms'             => 'invalidate_urls',
			'noobrocket_after_clean_domain'            => 'invalidate_all',
			'after_noobrocket_clean_home'              => 'invalidate_home',
			'pre_post_update'                          => 'remember_post_url',
			'post_updated'                             => [ 'invalidate_post', 20 ],
			'before_delete_post'                       => 'invalidate_post',
			'wp_trash_post'                            => 'invalidate_post',
			'after_noobrocket_clean_post'              => [ 'invalidate_dates', 20 ],
			'switch_theme'                             => 'invalidate_all',
			'upgrader_process_complete'                => 'invalidate_all',
			// Public taxonomy changes already purge via the cache owner; private ones
			// cannot change an anonymous CSS sample. Do not invalidate twice here.
			'permalink_structure_changed'              => 'invalidate_all',
			'noobrocket_domain_options_changed'        => 'invalidate_all',
			'noobrocket_host_fonts_locally_changed'    => 'invalidate_all',
			'update_option_noobrocket_settings'        => [ 'settings_changed', 20, 2 ],
		];
	}

	public function optimize( string $html ): string {
		return $this->frontend->optimize( $html );
	}

	// Set an early default; a site's later explicit native font opt-out still wins.
	public function inline_local_fonts( bool $inline ): bool {
		return $inline || $this->frontend->has_critical_css();
	}

	public function invalidate_urls( array $urls ): void {
		$this->jobs->invalidate_urls( $urls );
	}

	public function invalidate_all(): void {
		$this->jobs->invalidate_all();
	}

	public function invalidate_home(): void {
		$this->jobs->invalidate_urls(
			[
				home_url( '/' ),
				get_permalink( (int) get_option( 'page_for_posts' ) ) ?: home_url( '/' )
			]
		);
	}

	public function remember_post_url( int $id ): void {
		if ( wp_is_post_revision( $id ) ) {
			return;
		}

		$this->old_urls[ $id ] = get_permalink( $id );
	}

	public function invalidate_post( int $id ): void {
		if ( wp_is_post_revision( $id ) ) {
			return;
		}

		$this->jobs->invalidate_urls( array_filter( [ $this->old_urls[ $id ] ?? '', get_permalink( $id ) ] ) );
		unset( $this->old_urls[ $id ] );
	}

	public function invalidate_dates( $post ): void {
		$date = explode( '-', get_the_time( 'Y-m-d', $post ) );
		if ( count( $date ) === 3 ) {
			$this->jobs->invalidate_urls(
				[
					get_year_link( $date[0] ),
					get_month_link( $date[0], $date[1] ),
					get_day_link( $date[0], $date[1], $date[2] )
				]
			);
		}
	}

	public function settings_changed( $old, $new ): void {
		if ( ! is_array( $old ) || ! is_array( $new ) ) {
			return;
		}

		if ( noobrocket_create_options_hash( $old ) !== noobrocket_create_options_hash( $new ) ) {
			$this->jobs->invalidate_all();
		}
	}
}
