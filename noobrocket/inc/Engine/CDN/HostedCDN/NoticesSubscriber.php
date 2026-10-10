<?php
namespace NoobRocket\Engine\CDN\HostedCDN;

use NoobRocket\Abstract_Render;
use NoobRocket\Admin\Options_Data;
use NoobRocket\Engine\Admin\Beacon\Beacon;
use NoobRocket\Engine\Common\Utils;
use NoobRocket\Engine\License\API\User;
use NoobRocket\Engine\Tracking\Tracking;
use NoobRocket\Event_Management\Subscriber_Interface;
use NoobRocket\Engine\Tracking\TrackingTrait;

/**
 * Subscriber for the HostedCDN notices on NoobRocket settings page
 *
 * @since 3.5
 */
class NoticesSubscriber extends Abstract_Render implements Subscriber_Interface {
	use TrackingTrait;

	/**
	 * HostedCDN API Client instance.
	 *
	 * @var APIClient
	 */
	private $api_client;

	/**
	 * Beacon instance
	 *
	 * @var Beacon
	 */
	private $beacon;

	/**
	 * Tracking instance
	 *
	 * @var Tracking
	 */
	private $tracking;

	/**
	 * NoobRocket options instance.
	 *
	 * @var Options_Data
	 */
	private $options;

	/**
	 * Subscription controller instance.
	 *
	 * @var SubscriptionController
	 */
	private $subscription_controller;

	/**
	 * User instance.
	 *
	 * @var User
	 */
	private $user;

	/**
	 * Constructor
	 *
	 * @param APIClient              $api_client              HostedCDN API Client instance.
	 * @param Beacon                 $beacon                  Beacon instance.
	 * @param Tracking               $tracking                Tracking instance.
	 * @param string                 $template_path           Path to the templates.
	 * @param Options_Data           $options                 NoobRocket options instance.
	 * @param SubscriptionController $subscription_controller Subscription controller instance.
	 * @param User                   $user                    User instance.
	 */
	public function __construct(
		APIClient $api_client,
		Beacon $beacon,
		Tracking $tracking,
		$template_path,
		Options_Data $options,
		SubscriptionController $subscription_controller,
		User $user
	) {
		parent::__construct( $template_path );

		$this->api_client              = $api_client;
		$this->beacon                  = $beacon;
		$this->tracking                = $tracking;
		$this->options                 = $options;
		$this->subscription_controller = $subscription_controller;
		$this->user                    = $user;
	}

	/**
	 * Return an array of events that this subscriber wants to listen to.
	 *
	 * @return array
	 */
	public static function get_subscribed_events() {
		return [
			'admin_notices'                           => [
				[ 'purge_cache_notice' ],
				[ 'change_cname_notice' ],
				[ 'activation_failed_notice' ],
			],
			'noobrocket_cdn_free_before_status_indicator' => [
				[ 'display_noobrocketcdn_cta' ],
				[ 'display_reseller_limit_banner', 11 ],
			],
			'admin_post_noobrocket_ignore'                => [
				[ 'track_notice_homepage_cta_click', 5 ],
				[ 'track_noobrocketcdn_notice_dismissed', 5 ],
			],
			'wp_ajax_noobrocket_ignore'                   => [ [ 'track_noobrocketcdn_notice_dismissed', 5 ] ],
			'noobrocket_display_major_release_notice'     => 'maybe_display_major_release_notice',
		];
	}

	/**
	 * Displays the HostedCDN Call to Action on the CDN tab of NoobRocket settings page
	 *
	 * @since 3.5
	 *
	 * @param array $cta_data CTA data.
	 *
	 * @return void
	 */
	public function display_noobrocketcdn_cta( array $cta_data ) {
		/**
		 * Filters the display of the HostedCDN cta banner.
		 *
		 * @param bool $display_cta_banner; true to display, false otherwise.
		 */
		if ( ! apply_filters( 'noobrocket_display_noobrocketcdn_cta', true ) ) {
			return;
		}

		if ( $this->is_white_label_account() ) {
			return;
		}

		if ( $this->user->is_reseller_account() ) {
			return;
		}

		if ( ! noobrocket_is_live_site() ) {
			return;
		}

		$subscription_data = $this->api_client->get_subscription_data();

		if ( $this->subscription_controller->has_active_subscription() && $this->subscription_controller->is_paid() ) {
			return;
		}

		$pricing = $this->api_client->get_pricing_data();

		$regular_price_monthly = '';
		$regular_price_annual  = '';
		$nopromo_variant       = '--no-promo';
		$cta_small_class       = 'noobrocket-isHidden';
		$cta_big_class         = '';

		if ( get_user_meta( get_current_user_id(), 'noobrocket_noobrocketcdn_cta_hidden', true ) ) {
			$cta_small_class = '';
			$cta_big_class   = 'noobrocket-isHidden';
		}

		$small_cta_data = [
			'container_class' => $cta_small_class,
		];

		// Get button URL for one-click checkout.
		$button_url = $this->subscription_controller->get_express_checkout_url();

		if ( is_wp_error( $pricing ) ) {
			$beacon    = $this->beacon->get_suggest( 'noobrocketcdn_error' );
			$more_info = sprintf(
				// translators: %1$is = opening link tag, %2$s = closing link tag.
				__( '%1$sMore Info%2$s', 'noobrocket' ),
				'<a href="' . esc_url( $beacon['url'] ) . '" data-beacon-article="' . esc_attr( $beacon['id'] ) . '" rel="noopener noreferrer" target="_blank">',
				'</a>'
			);

			$message = $pricing->get_error_message() . ' ' . $more_info;

			$big_cta_data = [
				'container_class' => $cta_big_class,
				'nopromo_variant' => $nopromo_variant,
				'error'           => true,
				'message'         => $message,
				'button_url'      => $button_url,
			];
		} else {
			$current_price_monthly = number_format_i18n( $pricing['monthly_price'], 2 );
			$current_price_annual  = number_format_i18n( $pricing['annual_price'] / 12, 2 );
			$promotion_campaign    = '';
			$end_date              = strtotime( $pricing['end_date'] );
			$promotion_end_date    = '';

			if (
				$pricing['is_discount_active']
				&&
				$end_date > time()
			) {
				$promotion_campaign    = $pricing['discount_campaign_name'];
				$regular_price_monthly = $current_price_monthly;
				$regular_price_annual  = $current_price_annual;
				$current_price_monthly = number_format_i18n( $pricing['discounted_price_monthly'], 2 ) . '*';
				$current_price_annual  = number_format_i18n( $pricing['discounted_price_yearly'] / 12, 2 ) . '*';
				$nopromo_variant       = '';
				$promotion_end_date    = date_i18n( get_option( 'date_format' ), $end_date );
			}

			global $wp_locale;
			$current_price_array = explode( $wp_locale->number_format['decimal_point'], $current_price_monthly );

			if ( $cta_data['limit_reached'] ) {
				$cta_big_class .= 'noobrocket-noobrocketcdn-cta---max-limit';
			}

			$big_cta_data = [
				'container_class'       => $cta_big_class,
				'promotion_campaign'    => $promotion_campaign,
				'promotion_end_date'    => $promotion_end_date,
				'nopromo_variant'       => $nopromo_variant,
				'regular_price_monthly' => $regular_price_monthly,
				'regular_price_annual'  => $regular_price_annual,
				'current_price_monthly' => $current_price_monthly,
				'current_price_annual'  => $current_price_annual,
				'button_url'            => $button_url,
				'regular_price'         => $regular_price_monthly,
				'current_price'         => $current_price_monthly,
				'current_price_array'   => [
					'major' => ! empty( $current_price_array[0] ) ? $current_price_array[0] : 0,
					'minor' => ! empty( $current_price_array[1] ) ? $current_price_array[1] : 0,
				],
			];
		}

		$big_cta_data = array_merge( $big_cta_data, $cta_data );

		echo $this->generate( 'cta-big', $big_cta_data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view.
	}

	/**
	 * Displays a reseller-specific informational banner when the 3-page limit is reached.
	 *
	 * @param array $cta_data CTA data including limit_reached flag.
	 *
	 * @return void
	 */
	public function display_reseller_limit_banner( array $cta_data ) {
		if ( ! $this->user->is_reseller_account() ) {
			return;
		}

		$banner_data = [
			'heading'     => esc_html__( 'Nice work!  You\'re using HostedCDN on all available pages.', 'noobrocket' ),
			'description' => esc_html__( 'HostedCDN covers up to 3 pages, and you\'re all set.', 'noobrocket' ),
			'is_hidden'   => empty( $cta_data['limit_reached'] ),
		];

		echo $this->generate( 'cta-reseller-limit', $banner_data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view.
	}

	/**
	 * Displays a notice after purging the HostedCDN cache.
	 *
	 * @since 3.5
	 *
	 * @return void
	 */
	public function purge_cache_notice() {
		if ( ! current_user_can( 'noobrocket_manage_options' ) ) {
			return;
		}

		if ( 'settings_page_noobrocket' !== get_current_screen()->id ) {
			return;
		}

		$purge_response = get_transient( 'noobrocketcdn_purge_cache_response' );

		if ( false === $purge_response ) {
			return;
		}

		$message = $purge_response['message'];

		if ( 'error' === $purge_response['status'] ) {
			$beacon    = $this->beacon->get_suggest( 'noobrocketcdn_error' );
			$more_info = sprintf(
				// translators: %1$is = opening link tag, %2$s = closing link tag.
				__( '%1$sMore Info%2$s', 'noobrocket' ),
				'<a href="' . esc_url( $beacon['url'] ) . '" data-beacon-article="' . esc_attr( $beacon['id'] ) . '" rel="noopener noreferrer" target="_blank">',
				'</a>'
			);

			$message .= ' ' . $more_info;
		}

		delete_transient( 'noobrocketcdn_purge_cache_response' );

		noobrocket_notice_html(
			[
				'status'  => $purge_response['status'],
				'message' => $message,
			]
		);
	}

	/**
	 * Checks if white label is enabled
	 *
	 * @since 3.6
	 *
	 * @return bool
	 */
	private function is_white_label_account() {
		return (bool) noobrocket_get_constant( 'NOOBROCKET_WHITE_LABEL_ACCOUNT' );
	}

	/**
	 * Change CName admin notice contents.
	 *
	 * @return void
	 */
	public function change_cname_notice() {
		if ( ! current_user_can( 'noobrocket_manage_options' ) ) {
			return;
		}

		if ( 'settings_page_noobrocket' !== get_current_screen()->id ) {
			return;
		}

		$boxes = get_user_meta( get_current_user_id(), 'noobrocket_boxes', true );

		if ( in_array( 'noobrocketcdn_change_cname', (array) $boxes, true ) ) {
			return;
		}

		$old_cname = get_option( 'noobrocket_noobrocketcdn_old_url' );
		if ( empty( $old_cname ) ) {
			return;
		}

		$new_subscription = $this->api_client->get_subscription_data();
		if ( empty( $new_subscription['cdn_url'] ) || $old_cname === $new_subscription['cdn_url'] ) {
			return;
		}

		$support_url = noobrocket_get_external_url(
			'support',
			[
				'utm_source' => 'wp_plugin',
				'utm_medium' => 'noobrocket',
			]
		);

		$message_lines = [
			// translators: %1$s = Old CName, %2$s = New CName.
			sprintf( esc_html__( 'We\'ve updated your HostedCDN CNAME from %1$s to %2$s.', 'noobrocket' ), $old_cname, $new_subscription['cdn_url'] ),
			// translators: %1$s = New CName.
			sprintf( esc_html__( 'The change is already applied to the plugin settings. If you were using the CNAME in your code, make sure to update it to: %1$s.', 'noobrocket' ), $new_subscription['cdn_url'] ),
		];

		noobrocket_notice_html(
			[
				'status'         => 'info',
				'message'        => implode( '<br>', $message_lines ),
				'dismiss_button' => 'noobrocketcdn_change_cname',
				'id'             => 'noobrocketcdn_change_cname_notice',
				'action'         => sprintf( '<a href="%1$s" target="_blank" rel="noopener" class="noobrocket-button" id="noobrocketcdn-change-cname-button">%2$s</a>', $support_url, esc_html__( 'contact support', 'noobrocket' ) ),
			]
		);
	}

	/**
	 * Displays an admin notice when HostedCDN activation failed.
	 *
	 * Shows a notice with express checkout URL when:
	 * - is_active is false
	 * - cdn_url is empty
	 *
	 * @return void
	 */
	public function activation_failed_notice(): void {
		if ( ! current_user_can( 'noobrocket_manage_options' ) ) {
			return;
		}

		if ( 'settings_page_noobrocket' !== get_current_screen()->id ) {
			return;
		}

		if ( $this->is_white_label_account() ) {
			return;
		}

		if ( ! $this->should_display_activation_failed_notice() ) {
			return;
		}

		if ( $this->subscription_controller->is_free() ) {
			// Send the request again (create subscription) to fix what is wrong with this account.
			$this->subscription_controller->create_subscription( true );
			return;
		}

		$express_checkout_url = $this->subscription_controller->get_express_checkout_url();

		if ( empty( $express_checkout_url ) ) {
			return;
		}

		// Track banner view.
		$this->tracking->track_noobrocketcdn_activation_failed_banner_viewed();

		$message = sprintf(
			'<strong>%1$s</strong><br><br>%2$s<br>%3$s',
			esc_html__( 'HostedCDN activation incomplete', 'noobrocket' ),
			esc_html__( 'HostedCDN isn’t active on this website yet.', 'noobrocket' ),
			esc_html__( 'Click below to complete the activation. You’ll be redirected to checkout to confirm your subscription.', 'noobrocket' )
		);

		noobrocket_notice_html(
			[
				'status'      => 'error',
				'message'     => $message,
				'dismissible' => false,
				'id'          => 'noobrocketcdn_activation_failed_notice',
				'action'      => sprintf(
					'<a href="%1$s" target="_blank" rel="noopener" class="noobrocket-button" id="noobrocket-noobrocketcdn-activation-cta">%2$s</a>',
					esc_url( $express_checkout_url ),
					esc_html__( 'Complete activation', 'noobrocket' )
				),
			]
		);
	}

	/**
	 * Checks if the activation failed notice should be displayed.
	 *
	 * @return bool True if notice should be displayed, false otherwise.
	 */
	private function should_display_activation_failed_notice(): bool {
		if ( $this->subscription_controller->is_subscription_creation_loading() ) {
			return false;
		}

		// Do not show the notice if there is no HostedCDN user token saved:
		// this usually means the user never went through the checkout/activation flow.
		if ( empty( get_option( 'noobrocketcdn_user_token', '' ) ) ) {
			return false;
		}

		// Show notice when webiste is not attached.
		return ! $this->subscription_controller->is_website_attached() && $this->subscription_controller->has_active_subscription();
	}

	/**
	 * Display HostedCDN notice on admin dashboard if flag is set and notice hasn't been dismissed.
	 * Kept for backward compatibility — no longer hooked via get_subscribed_events().
	 * Post-activation notice display is now handled by NoticeSubscriber.
	 *
	 * @since 3.22
	 *
	 * @return void
	 */
	public function maybe_display_noobrocketcdn_notice() {
		if ( $this->should_hide_notices() ) {
			return;
		}

		$previous_version = $this->options->get( 'previous_version' );
		// @phpstan-ignore-next-line
		$noobrocket_cdn_token = get_option( 'noobrocketcdn_user_token', '' );

		// Don't show the notice if HostedCDN is already active (token exists).
		if ( ! empty( $noobrocket_cdn_token ) ) {
			return;
		}

		// Fresh install, show new install notice.
		if ( empty( $previous_version ) ) {
			$message = sprintf(
				// translators: %1$s opening <strong> tag, %2$s closing </strong> tag.
				esc_html__(
					'%1$sNew in Cache-Cache: Faster loading for your key pages%2$s',
					'noobrocket'
				),
				'<p><strong>',
				'</strong></p>'
			);

			$message .= sprintf(
				// translators: %1$s opening <p> tag, %2$s closing </p> tag.
				esc_html__(
					'%1$sYou can now use Content Delivery, powered by HostedCDN, to speed up your homepage and 2 more pages, at no extra cost.%2$s',
					'noobrocket'
				),
				'<p>',
				'</p>'
			);

			$notice_info = [
				'new_version'     => '3.22.0',
				'dismiss_button'  => 'noobrocketcdn_install_notice',
				'dismiss_message' => __( 'Dismiss', 'noobrocket' ),
				'message'         => $message,
				'action'          => 'noobrocketcdn_install_page',
				'status'          => 'success',
				'track_event'     => true,
			];

			Utils::display_update_notice( $notice_info, true );

			return;
		}

		$message = sprintf(
			// translators: %1$s opening <strong> tag, %2$s closing </strong> tag.
			esc_html__(
				'%1$sUse HostedCDN for free to boost up to 3 pages 🚀%2$s',
				'noobrocket'
			),
			'<p><strong>',
			'</strong></p>'
		);

		$message .= sprintf(
			// translators: %1$s opening <p> tag, %2$s closing </p> tag.
			esc_html__(
				'%1$sAs a Cache-Cache user, you can now activate HostedCDN for free on up to 3 pages. Choose your top pages and speed up their performance worldwide!%2$s',
				'noobrocket'
			),
			'<p>',
			'</p>'
		);

		$notice_info = [
			'new_version'      => '3.22.0',
			'dismiss_button'   => 'noobrocket_update_notice',
			'dismiss_message'  => __( 'Check it later', 'noobrocket' ),
			'message'          => $message,
			'action'           => 'noobrocketcdn_upgrade_page',
			'previous_version' => $previous_version,
			'track_event'      => true,
		];

		Utils::display_update_notice( $notice_info, true );
	}

	/**
	 * Tracks the "Start with my homepage" CTA click from the HostedCDN promo admin notice.
	 *
	 * Fires before noobrocket_dismiss_boxes() redirects, so we can track before the exit.
	 *
	 * @return void
	 */
	public function track_notice_homepage_cta_click(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce is verified later by noobrocket_dismiss_boxes().
		$box = isset( $_GET['box'] ) ? sanitize_key( wp_unslash( $_GET['box'] ) ) : '';

		if ( 'noobrocketcdn_install_notice' !== $box ) {
			return;
		}

		/**
		 * Fires when the homepage cta button is clicked.
		 *
		 * @param string $source The source of the click, either 'add_homepage_button' or 'admin_notices'.
		 */
		do_action( 'noobrocket_noobrocketcdn_add_homepage', 'admin_notices' );
	}

	/**
	 * Tracks when a HostedCDN admin notice is dismissed.
	 *
	 * Fires before noobrocket_dismiss_boxes() redirects, so we can track before the exit.
	 *
	 * @return void
	 */
	public function track_noobrocketcdn_notice_dismissed(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce is verified later by noobrocket_dismiss_boxes().
		$box = isset( $_GET['box'] ) ? sanitize_key( wp_unslash( $_GET['box'] ) ) : '';

		$noobrocketcdn_boxes = [ 'noobrocketcdn_install_notice', 'noobrocket_update_notice' ];

		if ( ! in_array( $box, $noobrocketcdn_boxes, true ) ) {
			return;
		}

		// Track Mixpanel event immediately.
		$this->track_event( 'HostedCDN Admin Notice Dismissed' );
	}

	/**
	 * Displays the major release upgrade notice for HostedCDN.
	 *
	 * @param string $major_version Current major.minor version.
	 *
	 * @return void
	 */
	public function maybe_display_major_release_notice( string $major_version ): void {
		if ( $this->should_hide_notices() ) {
			return;
		}

		$noobrocket_cdn_token = get_option( 'noobrocketcdn_user_token', '' );

		if ( ! empty( $noobrocket_cdn_token ) ) {
			return;
		}

		$message = sprintf(
			// translators: %1$s = opening strong+paragraph tags, %2$s = closing strong+paragraph tags.
			esc_html__(
				'%1$sUse HostedCDN for free to boost up to 3 pages 🚀%2$s',
				'noobrocket'
			),
			'<p><strong>',
			'</strong></p>'
		);

		$message .= sprintf(
			// translators: %1$s = opening paragraph tag, %2$s = closing paragraph tag.
			esc_html__(
				'%1$sAs a Cache-Cache user, you can now activate HostedCDN for free on up to 3 pages. Choose your top pages and speed up their performance worldwide!%2$s',
				'noobrocket'
			),
			'<p>',
			'</p>'
		);

		$notice_key   = $this->get_major_release_notice_key( $major_version );
		$redirect_url = admin_url( 'options-general.php?page=' . NOOBROCKET_PLUGIN_SLUG . '&noobrocket_source=notice_noobrocketcdn_upgrade#page_cdn' );
		$action_url   = wp_nonce_url(
			admin_url(
				'admin-post.php?action=noobrocket_ignore&box=' . $notice_key
				. '&redirect=' . rawurlencode( $redirect_url )
			),
			'noobrocket_ignore_' . $notice_key
		);

		$notice_info = [
			'new_version'     => NOOBROCKET_VERSION,
			'dismiss_button'  => $notice_key,
			'dismiss_message' => __( 'Check it later', 'noobrocket' ),
			'message'         => $message,
			'action'          => '<a class="button button-primary" href="' . esc_url( $action_url ) . '">' . esc_html__( 'Add your pages now', 'noobrocket' ) . '</a>',
			'status'          => 'info',
			'track_event'     => true,
		];

		Utils::display_update_notice( $notice_info, true );
	}

	/**
	 * Returns the notice dismiss key for the given major release version.
	 *
	 * @param string $major_version Major.minor version string (e.g. "3.22").
	 * @return string
	 */
	private function get_major_release_notice_key( string $major_version ): string {
		return 'noobrocket_major_release_notice_' . str_replace( '.', '_', $major_version );
	}

	/**
	 * Checks whether HostedCDN admin notices should be hidden.
	 *
	 * @return bool
	 */
	private function should_hide_notices(): bool {
		/**
		 * Filters showing noobrocketcdn admin notices
		 *
		 * @since 3.22
		 *
		 * @param bool $show_noobrocketcdn_notices Show noobrocketcdn notices, by default it's shown.
		 */
		return wpm_apply_filters_typed( 'boolean', 'noobrocket_hide_noobrocketcdn_notices', false );
	}
}
