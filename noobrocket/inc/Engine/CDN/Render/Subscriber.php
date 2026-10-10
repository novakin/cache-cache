<?php
declare(strict_types=1);

namespace NoobRocket\Engine\CDN\Render;

use NoobRocket\Event_Management\Subscriber_Interface;

/**
 * Subscriber for the CDN Render module.
 *
 * Wires the Controller methods to the appropriate WordPress hooks
 * for CDN driver sections, tabs, status indicator, upsell banner,
 * and exclusion fields.
 *
 * @since 3.22
 */
class Subscriber implements Subscriber_Interface {
	/**
	 * Controller instance.
	 *
	 * @var Controller
	 */
	private $controller;

	/**
	 * Constructor.
	 *
	 * @param Controller $controller Controller instance.
	 */
	public function __construct( Controller $controller ) {
		$this->controller = $controller;
	}

	/**
	 * {@inheritdoc}
	 */
	public static function get_subscribed_events(): array {
		return [
			'noobrocket_cdn_driver_sections'              => [
				[ 'add_applied_cdn_state_to_cdn_section' ],
				[ 'add_noobrocketcdn_paid_section' ],
				[ 'add_noobrocketcdn_free_section' ],
				[ 'add_exclude_cdn_section' ],
				[ 'add_purge_cdn_cache_section' ],
			],
			'current_screen'                          => [
				[ 'maybe_disable_noobrocketcdn_paid_after_cancellation' ],
				[ 'maybe_sync_forced_off_tracking_state' ],
			],
			'noobrocket_cdn_free_page_list'               => 'render_built_in_page_list',
			'noobrocket_cdn_free_page_rows'               => 'render_built_in_page_rows',
			'noobrocket_cdn_driver_tabs'                  => 'render_cdn_driver_tabs',
			'noobrocket_cdn_settings_fields'              => 'add_exclusions_fields',
			'noobrocket_display_noobrocketcdn_cta'            => 'maybe_display_noobrocketcdn_cta',
			'noobrocket_cdn_tab_badge'                    => 'maybe_hide_cdn_tab_badge',
			'pre_get_noobrocket_option_cdn'               => 'maybe_turn_off_noobrocketcdn_for_inactive_subscription',
			'noobrocket_cdn_free_before_status_indicator' => [
				[ 'render_expired_nbr_licence_notice', 9 ],
				[ 'render_reseller_banned_notice', 9 ],
			],
			'noobrocket_cdn_paid_before_status_indicator' => [
				[ 'render_cancelled_banner_notice', 9 ],
				[ 'render_pro_detection_failure_notice', 9 ],
			],
			'noobrocket_noobrocketcdn_status_indicator_texts' => [
				[ 'get_free_status_indicator_texts', 10, 4 ],
				[ 'get_paid_status_indicator_texts', 10, 4 ],
			],
			'admin_enqueue_scripts'                   => [ 'localize_tracking_data', 15 ],
		];
	}

	/**
	 * Adds the applied CDN state to the "Your CDN" (BYOCDN) section, when present.
	 *
	 * @since 3.23.3
	 *
	 * @param array $sections CDN driver sections.
	 *
	 * @return array
	 */
	public function add_applied_cdn_state_to_cdn_section( array $sections ): array {
		return $this->controller->add_applied_cdn_state_to_cdn_section( $sections );
	}

	/**
	 * Adds the HostedCDN Paid section to the CDN driver sections.
	 *
	 * @since 3.22
	 *
	 * @param array $sections CDN driver sections.
	 *
	 * @return array
	 */
	public function add_noobrocketcdn_paid_section( array $sections ): array {
		return $this->controller->add_noobrocketcdn_paid_section( $sections );
	}

	/**
	 * Adds the HostedCDN Free section to the CDN driver sections.
	 *
	 * @since 3.22
	 *
	 * @param array $sections CDN driver sections.
	 *
	 * @return array
	 */
	public function add_noobrocketcdn_free_section( array $sections ): array {
		return $this->controller->add_noobrocketcdn_free_section( $sections );
	}

	/**
	 * Adds the Exclude CDN section to the CDN driver sections.
	 *
	 * @since 3.22
	 *
	 * @param array $sections CDN driver sections.
	 *
	 * @return array
	 */
	public function add_exclude_cdn_section( array $sections ): array {
		return $this->controller->add_exclude_cdn_section( $sections );
	}

	/**
	 * Adds the Purge CDN Cache section to the CDN driver sections.
	 *
	 * @since 3.22
	 *
	 * @param array $sections CDN driver sections.
	 *
	 * @return array
	 */
	public function add_purge_cdn_cache_section( array $sections ): array {
		return $this->controller->add_purge_cdn_cache_section( $sections );
	}

	/**
	 * Renders the built-in CDN page list table.
	 *
	 * @since 3.22
	 *
	 * @return void
	 */
	public function render_built_in_page_list(): void {
		$this->controller->render_built_in_page_list();
	}

	/**
	 * Renders the built-in CDN page list rows.
	 *
	 * @since 3.22
	 *
	 * @return void
	 */
	public function render_built_in_page_rows(): void {
		$this->controller->render_built_in_page_rows();
	}

	/**
	 * Renders the CDN driver tabs.
	 *
	 * @since 3.22
	 *
	 * @return void
	 */
	public function render_cdn_driver_tabs(): void {
		$this->controller->render_cdn_driver_tabs();
	}

	/**
	 * Adds exclusion fields for CDN to the settings fields array.
	 *
	 * @since 3.22
	 *
	 * @param array $fields Existing settings fields array.
	 *
	 * @return array
	 */
	public function add_exclusions_fields( array $fields ): array {
		return $this->controller->add_exclusions_fields( $fields );
	}

	/**
	 * Displays the HostedCDN Call to Action on the CDN tab of NoobRocket settings page.
	 *
	 * @param bool $display Whether to display the CTA. Default true.
	 *
	 * @since 3.22
	 *
	 * @return bool
	 */
	public function maybe_display_noobrocketcdn_cta( bool $display = true ): bool {
		return $this->controller->maybe_display_noobrocketcdn_cta( $display );
	}

	/**
	 * Removes the "NEW" badge from the Content Delivery tab for existing paid subscribers.
	 *
	 * @since 3.22
	 *
	 * @param string $badge Current badge label.
	 *
	 * @return string
	 */
	public function maybe_hide_cdn_tab_badge( string $badge ): string {
		return $this->controller->maybe_hide_cdn_tab_badge( $badge );
	}

	/**
	 * Syncs the forced pause tracking option state on NoobRocket settings screen.
	 *
	 * @param \WP_Screen $screen Current screen object.
	 *
	 * @return void
	 */
	public function maybe_sync_forced_off_tracking_state( \WP_Screen $screen ): void {
		$this->controller->maybe_sync_forced_off_tracking_state( $screen );
	}

	/**
	 * Pauses the CDN for inactive subscriptions.
	 *
	 * @since 3.22
	 *
	 * @param mixed $cdn CDN Option.
	 *
	 * @return mixed
	 */
	public function maybe_turn_off_noobrocketcdn_for_inactive_subscription( $cdn ) {
		return $this->controller->maybe_turn_off_noobrocketcdn_for_inactive_subscription( $cdn );
	}

	/**
	 * Renders the expired NoobRocket license notice.
	 *
	 * @since 3.22
	 *
	 * @return void
	 */
	public function render_expired_nbr_licence_notice(): void {
		$this->controller->render_expired_nbr_licence_notice();
	}

	/**
	 * Renders the reseller-banned notice.
	 *
	 * @since 3.23.1
	 *
	 * @return void
	 */
	public function render_reseller_banned_notice(): void {
		$this->controller->render_reseller_banned_notice();
	}

	/**
	 * Filters status indicator texts for the free HostedCDN tier.
	 *
	 * @param array $texts                   Text strings for the status indicator.
	 * @param int   $pages_count             Number of pages using HostedCDN.
	 * @param bool  $is_subscription_loading Whether the subscription is loading.
	 * @param bool  $free                    Whether this is for the free version.
	 *
	 * @return array
	 */
	public function get_free_status_indicator_texts( array $texts, int $pages_count, bool $is_subscription_loading, bool $free ): array {
		return $this->controller->get_free_status_indicator_texts( $texts, $pages_count, $is_subscription_loading, $free );
	}

	/**
	 * Filters status indicator texts for the paid HostedCDN tier.
	 *
	 * @param array $texts                   Text strings for the status indicator.
	 * @param int   $pages_count             Number of pages using HostedCDN.
	 * @param bool  $is_subscription_loading Whether the subscription is loading.
	 * @param bool  $free                    Whether this is for the free version.
	 *
	 * @return array
	 */
	public function get_paid_status_indicator_texts( array $texts, int $pages_count, bool $is_subscription_loading, bool $free ): array {
		return $this->controller->get_paid_status_indicator_texts( $texts, $pages_count, $is_subscription_loading, $free );
	}

	/**
	 * Disables HostedCDN for paid subscriptions after cancellation.
	 *
	 * @since 3.23.4
	 *
	 * @return void
	 */
	public function maybe_disable_noobrocketcdn_paid_after_cancellation(): void {
		$this->controller->maybe_disable_noobrocketcdn_paid_after_cancellation();
	}

	/**
	 * Display notice when cancelled or in grace period.
	 *
	 * @return void
	 */
	public function render_cancelled_banner_notice() {
		$this->controller->render_cancelled_banner_notice();
	}

	/**
	 * Displays an admin notice when fresh-install Pro subscription detection failed after all retries.
	 *
	 * @return void
	 */
	public function render_pro_detection_failure_notice() {
		$this->controller->render_pro_detection_failure_notice();
	}

	/**
	 * Localizes the `cdn_mode`/`cdn_status` tracking axis properties for JS-side
	 * Mixpanel tracking.
	 *
	 * @return void
	 */
	public function localize_tracking_data(): void {
		$this->controller->localize_tracking_data();
	}
}
