<?php
declare(strict_types=1);

namespace NoobRocket\Engine\Tracking;

use NoobRocket\Abstract_Render;
use NoobRocket\Admin\Options_Data;
use NoobRocket\Engine\Admin\PerformanceMonitoring\Database\Rows\PerformanceMonitoring;
use NoobRocket\Engine\Common\Utils;
use NoobRocket\Engine\License\API\User;
use WPMedia\Mixpanel\Optin;
use WPMedia\Mixpanel\TrackingPlugin as MixpanelTracking;

class Tracking extends Abstract_Render {
	/**
	 * Options Data instance.
	 *
	 * @var Options_Data
	 */
	private $options;

	/**
	 * Optin instance.
	 *
	 * @var Optin
	 */
	private $optin;

	/**
	 * Mixpanel Tracking instance.
	 *
	 * @var MixpanelTracking
	 */
	private $mixpanel;

	/**
	 * License User instance.
	 *
	 * @var User
	 */
	private $user;

	/**
	 * ChannelDetector instance.
	 *
	 * @var ChannelDetector
	 */
	private $channel_detector;

	/**
	 * Constructor.
	 *
	 * @param Options_Data     $options          Options Data instance.
	 * @param Optin            $optin            Optin instance.
	 * @param MixpanelTracking $mixpanel         Mixpanel Tracking instance.
	 * @param User             $user             License User instance.
	 * @param ChannelDetector  $channel_detector Channel Detector instance.
	 * @param string           $template_path    Path to the template files.
	 */
	public function __construct( Options_Data $options, Optin $optin, MixpanelTracking $mixpanel, User $user, ChannelDetector $channel_detector, $template_path ) {
		parent::__construct( $template_path );

		$this->options          = $options;
		$this->optin            = $optin;
		$this->mixpanel         = $mixpanel;
		$this->user             = $user;
		$this->channel_detector = $channel_detector;

		$consumer_email = $this->options->get( 'consumer_email', '' );

		$this->mixpanel->identify( $consumer_email );

		$this->sync_is_reseller_property( $consumer_email );
	}

	/**
	 * Sync the is_reseller property with Mixpanel when tracking is allowed.
	 *
	 * @param string $consumer_email Consumer email.
	 *
	 * @return void
	 */
	private function sync_is_reseller_property( string $consumer_email ): void {
		if ( ! $this->optin->can_track() ) {
			return;
		}

		if ( ! is_admin() ) {
			return;
		}

		$is_reseller = $this->user->is_reseller_account();
		if ( ! $is_reseller ) {
			return;
		}

		if ( false !== get_transient( 'noobrocket_mixpanel_reseller_synced' ) ) {
			return;
		}

		// We could fire this on change instead of running it once per day.
		$this->mixpanel->set_user_property(
			$this->mixpanel->hash( $consumer_email ),
			'is_reseller',
			$is_reseller
		);

		set_transient( 'noobrocket_mixpanel_reseller_synced', 1, DAY_IN_SECONDS );
	}

	/**
	 * Track option change.
	 *
	 * @param mixed $old_value The old value of the option.
	 * @param mixed $value     The new value of the option.
	 */
	public function track_option_change( $old_value, $value ) {
		if ( ! $this->optin->is_enabled() ) {
			return;
		}

		/**
		 * Filters the tracked options.
		 *
		 * @since 3.19.2
		 *
		 * @param string[] $options Array of options that are tracked by default.
		 * @return string[] array of strings.
		 */
		$options_to_track = wpm_apply_filters_typed(
			'string[]',
			'noobrocket_mixpanel_tracked_options',
			[]
		);

		foreach ( $options_to_track as $option_tracked ) {
			if ( ! isset( $old_value[ $option_tracked ], $value[ $option_tracked ] ) ) {
				continue;
			}

			if ( $old_value[ $option_tracked ] === $value[ $option_tracked ] ) {
				continue;
			}

			$this->mixpanel->track(
				'Option Changed',
				[
					'context'             => 'wp_plugin',
					'option_name'         => $option_tracked,
					'previous_value'      => $old_value[ $option_tracked ],
					'new_value'           => $value[ $option_tracked ],
					'interaction_channel' => $this->channel_detector->detect(),
				]
			);
		}
	}

	/**
	 * Migrate opt-in to new package on upgrade
	 *
	 * @param string $new_version The new version of the plugin.
	 * @param string $old_version The old version of the plugin.
	 *
	 * @return void
	 */
	public function migrate_optin( string $new_version, string $old_version ): void {
		if ( version_compare( $old_version, '3.19.1', '>=' ) ) {
			return;
		}

		if ( ! $this->options->get( 'analytics_enabled', false ) ) {
			return;
		}

		$this->optin->enable();
	}

	/**
	 * Render the opt-in section.
	 *
	 * @return void
	 */
	public function render_optin(): void {
		echo $this->generate( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			'optin',
			[
				'current_value' => (int) $this->optin->is_enabled(),
			]
		);
	}

	/**
	 * Handle AJAX request to toggle opt-in.
	 *
	 * @return void
	 */
	public function ajax_toggle_optin(): void {
		check_ajax_referer( 'noobrocket-ajax' );

		if ( ! current_user_can( 'noobrocket_manage_options' ) ) {
			wp_send_json_error( 'Missing capability' );
		}

		if ( ! isset( $_POST['value'] ) ) {
			wp_send_json_error( 'Missing value parameter' );
		}

		$value = sanitize_key( wp_unslash( $_POST['value'] ) );

		if ( '1' === $value ) {
			$this->optin->enable();
			// Update the legacy option to prevent the notice from being displayed again after the opt-in is enabled.
			update_option( 'noobrocket_analytics_notice_displayed', 1 );
			// Set the thank-you transient to display the thank-you notice after the opt-in is enabled.
			set_transient( 'noobrocket_analytics_optin', 1 );
			wp_send_json_success( 'Opt-in enabled.' );
		} elseif ( '0' === $value ) {
			$this->optin->disable();
			wp_send_json_success( 'Opt-in disabled.' );
		}

		wp_send_json_error( 'Invalid value parameter.' );
	}

	/**
	 * Add opt-in status to admin scripts.
	 *
	 * @return void
	 */
	public function localize_optin_status(): void {
		if ( ! current_user_can( 'noobrocket_manage_options' ) ) {
			return;
		}

		// Get the license email and hash it for privacy.
		$consumer_email = $this->options->get( 'consumer_email', '' );
		$hashed_email   = ! empty( $consumer_email ) ? $this->mixpanel->hash( $consumer_email ) : '';

		wp_localize_script(
			'noobrocket-settings-common',
			'noobrocket_mixpanel_data',
			[
				'optin_enabled' => $this->optin->is_enabled() ? true : false,
				'plugin'        => 'NoobRocket ' . noobrocket_get_constant( 'NOOBROCKET_VERSION', '' ),
				'brand'         => 'wp media',
				'app'           => 'NoobRocket',
				'context'       => 'wp_plugin',
				'path'          => isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '',
				'user_id'       => $hashed_email,
			]
		);
	}

	/**
	 * Injects Mixpanel JavaScript SDK when opt-in is enabled.
	 *
	 * @since 3.19.2
	 * @return void
	 */
	public function inject_mixpanel_script(): void {
		// Only inject if user has capability and opt-in is enabled.
		if ( ! current_user_can( 'noobrocket_manage_options' ) || ! $this->optin->is_enabled() ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen || 'settings_page_noobrocket' !== $screen->id ) {
			return;
		}

		$this->mixpanel->add_script();
	}

	/**
	 * Track opt-in change event.
	 *
	 * @param bool $status The new opt-in status.
	 *
	 * @return void
	 */
	public function track_optin_change( $status ): void {
		$this->mixpanel->track_optin( $status );
	}

	/**
	 * Track when a URL is added in Performance monitoring
	 *
	 * @param string $url        The URL that was added for monitoring.
	 * @param string $plan       Plan name.
	 * @param int    $urls_count The current number of URLs being monitored.
	 * @param string $source     The source of the request.
	 *
	 * @return void
	 */
	public function track_noobrocket_insights_url_added( $url, $plan, $urls_count, $source ): void {
		if ( ! $this->optin->can_track() ) {
			return;
		}

		$this->mixpanel->track(
			'Performance monitoring Page Added',
			[
				'context'             => 'wp_plugin',
				'plan_type'           => $plan,
				'tracked_pages'       => $urls_count,
				'source'              => $source,
				'interaction_channel' => $this->channel_detector->detect(),
			]
		);
	}

	/**
	 * Tracks when a performance test is completed or failed in Performance monitoring.
	 *
	 * @since 3.20
	 *
	 * @param PerformanceMonitoring $row_details Details related to the database row.
	 * @param array          $job_details Details related to the job.
	 * @param string         $plan Plan name.
	 *
	 * @return void
	 */
	public function track_noobrocket_insights_test( $row_details, $job_details, $plan ): void {
		if ( ! $this->optin->can_track() ) {
			return;
		}

		if ( empty( $row_details->data ) ) {
			return;
		}

		$event_data = [
			'context'             => 'wp_plugin',
			'status'              => $row_details->status,
			'score'               => $row_details->score,
			'retest'              => $row_details->data['is_retest'],
			'duration'            => time() - $row_details->data['start_time'],
			'plan_type'           => $plan,
			'source'              => $row_details->data['source'],
			'interaction_channel' => $row_details->data['interaction_channel'] ?? $this->channel_detector->detect(),
		];

		if ( Utils::is_home( $row_details->url ) ) {
			$event_data['ri_page_identifier'] = 'is_home_page';
		}

		$this->mixpanel->track_direct(
			'Performance monitoring Performance Test',
			$event_data
		);
	}

	/**
	 * Track when "View Details" is clicked from the Performance monitoring column.
	 *
	 * @since 3.20.5
	 *
	 * @param int    $row_id  The database row ID of the test.
	 * @param string $context The context where the button was clicked (e.g., 'post type listing').
	 *
	 * @return void
	 */
	public function track_noobrocket_insights_view_details( int $row_id, string $context ): void {
		if ( ! $this->optin->can_track() ) {
			return;
		}

		$this->mixpanel->track(
			'Performance monitoring View Details',
			[
				'context'             => 'wp_plugin',
				'source'              => $context,
				'test_id'             => $row_id,
				'interaction_channel' => $this->channel_detector->detect(),
			]
		);
	}

	/**
	 * Tracks a Performance monitoring details action event.
	 *
	 * @param string     $event_name The name of the event to track.
	 * @param int|string $row_id     The ID of the test row, or 'all' for global actions.
	 * @param string     $source     The source of the action (e.g., 'url_expand', 'global_expand', 'post type listing', 'auto_expand_url').
	 *
	 * @return void
	 */
	public function track_noobrocket_insights_details_action( $event_name, $row_id, $source ): void {
		if ( ! $this->optin->can_track() ) {
			return;
		}

		$this->mixpanel->track_direct(
			$event_name,
			[
				'context'             => 'wp_plugin',
				'test_id'             => $row_id,
				'source'              => $source,
				'interaction_channel' => $this->channel_detector->detect(),
			]
		);
	}

	/**
	 * Tracks when the HostedCDN activation failed banner is viewed.
	 *
	 * @return void
	 */
	public function track_noobrocketcdn_activation_failed_banner_viewed(): void {
		$this->track_event( 'HostedCDN Activation Failed Banner Viewed' );
	}

	/**
	 * Track event dynamically.
	 *
	 * @param string $event_name The name of the event to track.
	 * @param array  $event_data An associative array of event data to send with the event.
	 * @return void
	 */
	public function track_event( $event_name, $event_data = [] ): void {
		if ( ! $this->optin->can_track() ) {
			return;
		}

		$event_data = wp_parse_args(
			$event_data,
			[
				'context'             => 'wp_plugin',
				'interaction_channel' => $this->channel_detector->detect(),
				'path'                => isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '',
			]
		);

		$this->mixpanel->track( $event_name, $event_data );
	}

	/**
	 * Track when the "Add Homepage" button is clicked for HostedCDN.
	 *
	 * @param string $source Either 'add_homepage_button' (CDN settings) or 'admin_notices'.
	 * @return void
	 */
	public function track_add_noobrocket_cdn_homepage( string $source ): void {
		if ( ! $this->optin->can_track() ) {
			return;
		}

		$this->track_event(
			'Button Clicked',
			[
				'button'  => 'rocket cdn add homepage',
				'context' => 'wp_plugin',
				'source'  => $source,
			]
		);
	}

	/**
	 * Track when HostedCDN free-tier is activated.
	 *
	 * @return void
	 */
	public function track_noobrocketcdn_free_activated(): void {
		if ( ! $this->optin->can_track() ) {
			return;
		}

		$this->track_event( 'HostedCDN Activated' );
	}

	/**
	 * Track when a HostedCDN notice is viewed
	 *
	 * @param string $box The notice box identifier.
	 * @return void
	 */
	public function track_noobrocketcdn_notice_viewed( string $box ) {
		if ( ! $this->optin->can_track() ) {
			return;
		}

		$noobrocketcdn_boxes = [ 'noobrocketcdn_install_notice', 'noobrocket_update_notice' ];

		if ( ! in_array( $box, $noobrocketcdn_boxes, true ) ) {
			return;
		}

		$this->track_event( 'HostedCDN Notice Viewed' );
	}
}
