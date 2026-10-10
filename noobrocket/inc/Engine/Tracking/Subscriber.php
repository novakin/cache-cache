<?php
declare(strict_types=1);

namespace NoobRocket\Engine\Tracking;

use NoobRocket\Event_Management\Subscriber_Interface;
use NoobRocket\Engine\Admin\PerformanceMonitoring\Managers\Plan;

class Subscriber implements Subscriber_Interface {
	/**
	 * The tracking service.
	 *
	 * @var Tracking
	 */
	private $tracking;

	/**
	 * Constructor.
	 *
	 * @param Tracking $tracking The tracking service.
	 */
	public function __construct( Tracking $tracking ) {
		$this->tracking = $tracking;
	}

	/**
	 * Returns an array of events this subscriber wants to listen to.
	 *
	 * @return array
	 */
	public static function get_subscribed_events(): array {
		return [
			'update_option_noobrocket_settings'     => [ 'track_option_change', 10, 2 ],
			'noobrocket_upgrade'                    => [ 'migrate_optin', 10, 2 ],
			'noobrocket_dashboard_after_account_data'  => [ 'render_optin', 8 ],
			'wp_ajax_noobrocket_toggle_optin'          => [ 'ajax_toggle_optin' ],
			'admin_enqueue_scripts'                => [ 'localize_optin_status', 15 ],
			'admin_print_scripts'                  => [ 'inject_mixpanel_script' ],
			'noobrocket_mixpanel_optin_changed'        => 'track_optin_change',
			'noobrocket_noobrocket_insights_job_added'     => [ 'track_noobrocket_insights_url_added', 10, 4 ],
			'noobrocket_noobrocket_insights_job_failed'    => [ 'track_noobrocket_insights_test', 10, 3 ],
			'noobrocket_noobrocket_insights_job_completed' => [ 'track_noobrocket_insights_test', 10, 3 ],
			'noobrocket_mixpanel_track_event'          => [ 'track_event', 10, 2 ],
			'noobrocket_noobrocketcdn_add_homepage'        => [ 'track_add_noobrocket_cdn_homepage', 10, 1 ],
			'noobrocket_cdnfree_website_created'       => 'track_noobrocketcdn_free_activated',
			'noobrocket_notice_displayed'              => [ 'track_noobrocketcdn_notice_viewed', 10, 1 ],
		];
	}

	/**
	 * Track option change.
	 *
	 * @param mixed $old_value The old value of the option.
	 * @param mixed $value     The new value of the option.
	 *
	 * @return void
	 */
	public function track_option_change( $old_value, $value ): void {
		$this->tracking->track_option_change( $old_value, $value );
	}

	/**
	 * Migrate opt-in to new package on upgrade
	 *
	 * @param string $new_version The new version of the plugin.
	 * @param string $old_version The old version of the plugin.
	 *
	 * @return void
	 */
	public function migrate_optin( $new_version, $old_version ): void {
		$this->tracking->migrate_optin( $new_version, $old_version );
	}

	/**
	 * Render the opt-in section.
	 *
	 * @return void
	 */
	public function render_optin(): void {
		$this->tracking->render_optin();
	}

	/**
	 * Handle AJAX request to toggle opt-in.
	 *
	 * @return void
	 */
	public function ajax_toggle_optin(): void {
		$this->tracking->ajax_toggle_optin();
	}

	/**
	 * Localize opt-in status to JavaScript.
	 *
	 * @return void
	 */
	public function localize_optin_status(): void {
		$this->tracking->localize_optin_status();
	}

	/**
	 * Inject Mixpanel JavaScript SDK.
	 *
	 * @since 3.19.2
	 * @return void
	 */
	public function inject_mixpanel_script(): void {
		$this->tracking->inject_mixpanel_script();
	}

	/**
	 * Track opt-in change event.
	 *
	 * @param bool $status The new opt-in status.
	 *
	 * @return void
	 */
	public function track_optin_change( $status ): void {
		$this->tracking->track_optin_change( $status );
	}
	/**
	 * Tracks when a URL is added to Performance monitoring.
	 *
	 * @param string $url        URL that was added.
	 * @param string $plan       Plan name.
	 * @param int    $urls_count The current number of URLs being monitored.
	 * @param string $source     The source of the request.
	 *
	 * @return void
	 */
	public function track_noobrocket_insights_url_added( $url, $plan, $urls_count, $source ): void {
		$this->tracking->track_noobrocket_insights_url_added( $url, $plan, $urls_count, $source );
	}
	/**
	 * Tracks when a performance test is completed or failed in Performance monitoring.
	 *
	 * @since 3.20
	 *
	 * @param object $row_details Details related to the database row.
	 * @param array  $job_details Details related to the job.
	 * @param string $plan Plan name.
	 *
	 * @return void
	 */
	public function track_noobrocket_insights_test( $row_details, $job_details, $plan ): void {
		$this->tracking->track_noobrocket_insights_test( $row_details, $job_details, $plan );
	}

	/**
	 * Track generic event with Mixpanel.
	 *
	 * @param string $event_name Event name to track.
	 * @param array  $event_data Additional data to send with the event.
	 *
	 * @return void
	 */
	public function track_event( string $event_name, array $event_data = [] ): void {
		$this->tracking->track_event( $event_name, $event_data );
	}

	/**
	 * Track HostedCDN add homepage button click.
	 *
	 * @param string $source Either 'add_homepage_button' or 'admin_notices'.
	 * @return void
	 */
	public function track_add_noobrocket_cdn_homepage( string $source ): void {
		$this->tracking->track_add_noobrocket_cdn_homepage( $source );
	}

	/**
	 * Track when HostedCDN free-tier is activated.
	 *
	 * @return void
	 */
	public function track_noobrocketcdn_free_activated(): void {
		$this->tracking->track_noobrocketcdn_free_activated();
	}

	/**
	 * Track when a HostedCDN notice is viewed.
	 *
	 * @param string $box The notice box identifier.
	 *
	 * @return void
	 */
	public function track_noobrocketcdn_notice_viewed( string $box ): void {
		$this->tracking->track_noobrocketcdn_notice_viewed( $box );
	}
}
