<?php
namespace NoobRocket\Addon\Varnish;

use NoobRocket\Admin\Options_Data;
use NoobRocket\Event_Management\Subscriber_Interface;

/**
 * Subscriber for the Varnish Purge.
 *
 * @since 3.5
 */
class Subscriber implements Subscriber_Interface {
	/**
	 * Varnish instance
	 *
	 * @var Varnish
	 */
	private $varnish;

	/**
	 * NoobRocket options instance
	 *
	 * @var Options_Data
	 */
	private $options;

	/**
	 * Constructor
	 *
	 * @param Varnish      $varnish Varnish instance.
	 * @param Options_Data $options NoobRocket options instance.
	 */
	public function __construct( Varnish $varnish, Options_Data $options ) {
		$this->varnish = $varnish;
		$this->options = $options;
	}

	/**
	 * {@inheritdoc}
	 */
	public static function get_subscribed_events() {
		return [
			'before_noobrocket_clean_domain'                   => [ 'clean_domain', 10, 3 ],
			'before_noobrocket_clean_file'                     => [ 'clean_file' ],
			'noobrocket_rucss_after_clearing_usedcss'          => [ 'clean_file' ],
			'noobrocket_performance_hints_data_after_clearing' => [ 'clean_file' ],
			'before_noobrocket_clean_home'                     => [ 'clean_home', 10, 2 ],
			'noobrocket_saas_complete_job_status'              => [ 'clean_file' ],
		];
	}

	/**
	 * Checks if Varnish cache should be purged
	 *
	 * @since 3.5
	 *
	 * @return bool
	 */
	private function should_purge() {
		return (
			/**
			 * Filters the use of the Varnish compatibility add-on
			 *
			 * @param bool $varnish_purge True to use, false otherwise.
			 */
			apply_filters( 'do_noobrocket_varnish_http_purge', false ) // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
			||
			(bool) $this->options->get( 'varnish_auto_purge', 0 )
		);
	}

	/**
	 * Clears Varnish cache for the whole domain
	 *
	 * @param string $root The path of home cache file.
	 * @param string $lang The current lang to purge.
	 * @param string $url  The home url.
	 * @return void
	 */
	public function clean_domain( $root, $lang, $url ) {
		if ( ! $this->should_purge() ) {
			return;
		}

		$this->varnish->purge( trailingslashit( $url ) . '?regex' );
	}

	/**
	 * Clears a specific page in Varnish cache
	 *
	 * @param string $url The url to purge.
	 * @return void
	 */
	public function clean_file( $url ) {
		if ( ! $this->should_purge() ) {
			return;
		}

		$this->varnish->purge( trailingslashit( $url ) . '?regex' );
	}

	/**
	 * Clears the homepage in Varnish cache
	 *
	 * @param string $root The path of home cache file.
	 * @param string $lang The current lang to purge.
	 * @return void
	 */
	public function clean_home( $root, $lang ) {
		if ( ! $this->should_purge() ) {
			return;
		}

		$home_url            = trailingslashit( get_noobrocket_i18n_home_url( $lang ) );
		$home_pagination_url = $home_url . trailingslashit( $GLOBALS['wp_rewrite']->pagination_base ) . '?regex';

		$this->varnish->purge( $home_url );
		$this->varnish->purge( $home_pagination_url );
	}
}
