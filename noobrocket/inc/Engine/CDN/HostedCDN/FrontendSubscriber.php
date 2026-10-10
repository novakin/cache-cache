<?php
declare(strict_types=1);

namespace NoobRocket\Engine\CDN\HostedCDN;

use NoobRocket\Engine\CDN\Context;
use NoobRocket\Event_Management\Subscriber_Interface;

/**
 * Subscriber for HostedCDN frontend integration.
 *
 * Dynamically provides cdn_cnames and cdn_zone values from the HostedCDN subscription data,
 * and drives the fresh-install Pro subscription detection workflow. Registered as a common
 * (not admin-only) subscriber because the Action Scheduler retry callback runs outside of wp-admin.
 *
 * @since 3.22
 */
class FrontendSubscriber implements Subscriber_Interface {

	/**
	 * CDN context.
	 *
	 * @var Context
	 */
	private $context;

	/**
	 * Cached HostedCDN URL to avoid multiple transient calls hits per request.
	 *
	 * @var string|null
	 */
	private $noobrocketcdn_url = null;

	/**
	 * Subscription controller.
	 *
	 * @var SubscriptionController
	 */
	private $subscription_controller;

	/**
	 * Constructor.
	 *
	 * @param Context                $context    CDN context.
	 * @param SubscriptionController $subscription_controller Subscription controller.
	 */
	public function __construct( Context $context, SubscriptionController $subscription_controller ) {
		$this->context                 = $context;
		$this->subscription_controller = $subscription_controller;
	}

	/**
	 * Return an array of events that this subscriber wants to listen to.
	 *
	 * @return array
	 */
	public static function get_subscribed_events() {
		return [
			'get_noobrocket_option_cdn_cnames' => [ 'set_cdn_cnames', 9 ],
			'get_noobrocket_option_cdn_zone'   => [ 'set_cdn_zone', 9 ],
			'noobrocket_cdn_auto_detect'       => 'scheduled_auto_detect_pro_subscription',
		];
	}

	/**
	 * Sets the CDN CNAME from the HostedCDN subscription data on the filter level.
	 *
	 * @since 3.22
	 *
	 * @param mixed $cnames The current filter value.
	 *
	 * @return mixed The CDN CNAME array if HostedCDN is active, or the original value for BYOCDN, or empty otherwise.
	 */
	public function set_cdn_cnames( $cnames ) {
		if ( is_admin() ) {
			return $this->handle_admin_cname( $cnames );
		}

		if ( ! $this->context->is_noobrocketcdn() ) {
			return $cnames;
		}

		$cdn_url = $this->get_noobrocketcdn_url();

		return empty( $cdn_url ) ? [] : [ $cdn_url ];
	}

	/**
	 * Sets the CDN zone from the HostedCDN subscription data on the filter level.
	 *
	 * @since 3.22
	 *
	 * @param mixed $value The current filter value.
	 *
	 * @return mixed The CDN zone array if HostedCDN is active, or the original value.
	 */
	public function set_cdn_zone( $value ) {
		$cdn_url = $this->get_noobrocketcdn_url();

		if ( empty( $cdn_url ) ) {
			return $value;
		}

		return [ 'all' ];
	}

	/**
	 * Gets the CDN URL from the HostedCDN subscription data.
	 *
	 * @since 3.22
	 *
	 * @return string The CDN URL if subscription is active, empty string otherwise.
	 */
	private function get_noobrocketcdn_url(): string {
		// Use memoized value if available.
		if ( null !== $this->noobrocketcdn_url ) {
			return $this->noobrocketcdn_url;
		}

		if ( ! $this->context->is_noobrocketcdn() ) {
			$this->noobrocketcdn_url = '';
			return '';
		}

		if ( ! $this->subscription_controller->has_active_subscription() ) {
			$this->noobrocketcdn_url = '';
			return '';
		}

		$this->noobrocketcdn_url = $this->get_raw_noobrocketcdn_url();

		return $this->noobrocketcdn_url;
	}

	/**
	 * Get raw noobrocketcdn url without any check.
	 *
	 * @return string
	 */
	private function get_raw_noobrocketcdn_url() {
		return $this->subscription_controller->get_noobrocketcdn_url();
	}

	/**
	 * Handles the CDN CNAME value in the admin area to ensure that the HostedCDN URL is removed from the list of CNAMEs when the user saves the settings.
	 *
	 * @param array $cnames CNAME array.
	 * @return array
	 */
	private function handle_admin_cname( $cnames ) {
		if ( empty( $cnames ) ) {
			return $cnames;
		}

		return array_filter( $cnames, [ $this, 'filter_noobrocketcdn_cname' ] );
	}

	/**
	 * Filter noobrocketcdn cname by comparing the current noobrocketcdn url with the CNAME value.
	 *
	 * @param string $cname CNAME string.
	 * @return bool
	 */
	private function filter_noobrocketcdn_cname( $cname ) {
		return $cname !== $this->get_raw_noobrocketcdn_url();
	}

	/**
	 * Action Scheduler callback that retries the fresh-install Pro subscription detection.
	 *
	 * @param int $attempt Number of remaining detection attempts.
	 * @return void
	 */
	public function scheduled_auto_detect_pro_subscription( int $attempt ) {
		$this->subscription_controller->scheduled_auto_detect_pro_subscription( $attempt );
	}
}
