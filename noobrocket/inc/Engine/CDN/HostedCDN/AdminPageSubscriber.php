<?php

namespace NoobRocket\Engine\CDN\HostedCDN;

use NoobRocket\Abstract_Render;
use NoobRocket\Engine\License\API\User;
use NoobRocket\Engine\License\API\UserClient;
use NoobRocket\Event_Management\Subscriber_Interface;

/**
 * Subscriber for the HostedCDN integration in NoobRocket settings page
 *
 * @since  3.5
 */
class AdminPageSubscriber extends Abstract_Render implements Subscriber_Interface {
	/**
	 * HostedCDN API Client instance.
	 *
	 * @var APIClient
	 */
	private $api_client;

	/**
	 * UserClient instance
	 *
	 * @var UserClient
	 */
	private $user_client;

	/**
	 * User instance
	 *
	 * @var User
	 */
	private $user;

	/**
	 * Constructor
	 *
	 * @param APIClient  $api_client    HostedCDN API Client instance.
	 * @param UserClient $user_client   UserClient instance.
	 * @param User       $user          User instance.
	 * @param string     $template_path Path to the templates.
	 */
	public function __construct( APIClient $api_client, $user_client, User $user, $template_path ) {
		parent::__construct( $template_path );

		$this->api_client  = $api_client;
		$this->user_client = $user_client;
		$this->user        = $user;
	}

	/**
	 * Return an array of events that this subscriber wants to listen to.
	 *
	 * @return array
	 */
	public static function get_subscribed_events() {
		return [
			'noobrocket_dashboard_after_account_data'        => 'display_noobrocketcdn_status',
			'admin_post_noobrocket_purge_noobrocketcdn'          => 'purge_cdn_cache',
			'noobrocket_settings_page_footer'                => 'add_subscription_modal',
			'http_request_args'                          => [ 'preserve_authorization_token', PHP_INT_MAX, 2 ],
			'noobrocket_insights_api_recommendations_params' => 'maybe_add_noobrocketcdn_to_recommendations_api_params',
		];
	}

	/**
	 * Displays the HostedCDN section on the dashboard tab
	 *
	 * @since  3.5
	 *
	 * @return void
	 */
	public function display_noobrocketcdn_status() {
		/**
		 * Filters the display of the HostedCDN status.
		 *
		 * @param bool $display_noobrocketcdn_status; true to display, false otherwise.
		 */
		if ( ! apply_filters( 'noobrocket_display_noobrocketcdn_status', true ) ) {
			return;
		}

		if ( $this->is_white_label_account() ) {
			return;
		}

		if ( $this->user->is_reseller_account() ) {
			return;
		}

		$subscription_data = $this->api_client->get_subscription_data();

		$container_class = '';
		$is_active       = false;
		$items           = [];

		if ( 'running' === $subscription_data['subscription_status'] && 'paid' === $subscription_data['plan_type'] ) {
			$items[] = [
				'label' => __( 'Plan', 'noobrocket' ),
				'value' => __( 'HostedCDN Pro', 'noobrocket' ),
				'class' => ' noobrocket-isValid noobrocket-no-icon',
			];
			$items[] = [
				'label' => __( 'Next Billing Date', 'noobrocket' ),
				'value' => date_i18n( get_option( 'date_format' ), strtotime( $subscription_data['subscription_next_date_update'] ) ),
				'class' => ' noobrocket-isValid',
			];

			$is_active = true;
		} else {
			$items[]         = [
				'label' => '',
				'value' => __( 'No HostedCDN Pro Subscription', 'noobrocket' ),
				'class' => ' noobrocket-isInvalid',
			];
			$container_class = ' noobrocket-flex--egal';
		}

		$data = [
			'is_live_site'    => noobrocket_is_live_site(),
			'container_class' => $container_class,
			'is_active'       => $is_active,
			'items'           => $items,
		];

		echo $this->generate( 'dashboard-status', $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view.
	}

	/**
	 * Purges the CDN cache and store the response in a transient.
	 *
	 * @since  3.5
	 *
	 * @return void
	 */
	public function purge_cdn_cache() {
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'noobrocket_purge_noobrocketcdn' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
			wp_nonce_ays( '' );
		}

		if ( ! current_user_can( 'noobrocket_manage_options' ) ) {
			wp_die();
		}

		set_transient( 'noobrocketcdn_purge_cache_response', $this->api_client->purge_cache_request(), HOUR_IN_SECONDS );

		wp_safe_redirect( esc_url_raw( wp_get_referer() ) );
		noobrocket_get_constant( 'NOOBROCKET_IS_TESTING', false ) ? wp_die() : exit;
	}

	/**
	 * Adds the subscription modal on the NoobRocket settings page
	 *
	 * @since  3.5
	 *
	 * @return void
	 */
	public function add_subscription_modal() {
		if ( $this->is_white_label_account() ) {
			return;
		}

		if ( $this->user->is_reseller_account() ) {
			return;
		}

		if ( ! noobrocket_is_live_site() ) {
			return;
		}

		// Check if user data has button URL.
		$button_url = '';
		$user_data  = $this->user_client->get_user_data();

		if ( false !== $user_data && isset( $user_data->noobrocketcdn->button->url ) && ! empty( $user_data->noobrocketcdn->button->url ) ) {
			$button_url = add_query_arg(
				'dashboard_url',
				rawurlencode(
					add_query_arg(
						[
							'page'               => NOOBROCKET_PLUGIN_SLUG,
							'noobrocketcdn_checkout' => 'true',
						],
						admin_url( 'options-general.php' )
					)
				),
				esc_url_raw( $user_data->noobrocketcdn->button->url )
			);
		}

		$iframe_src = add_query_arg(
			[
				'website'  => home_url(),
				'callback' => rest_url( 'noobrocket/v1/noobrocketcdn/' ),
				'source'   => 'plugin',
			],
			''
		);
		?>
		<script type="text/javascript">
			window.noobrocketcdnButtonUrl = <?php echo wp_json_encode( $button_url ); ?>;
		</script>
		<div class="noobrocket-noobrocketcdn-modal" id="noobrocket-noobrocketcdn-modal" aria-hidden="true">
			<div class="noobrocket-noobrocketcdn-modal__overlay" tabindex="-1" data-micromodal-close>
				<div class="noobrocket-loader" id="noobrocket-noobrocketcdn-modal-loader"></div>
				<div class="noobrocket-noobrocketcdn-modal__container" role="dialog" aria-modal="true" aria-labelledby="noobrocket-noobrocketcdn-modal-title">
					<div id="noobrocket-noobrocketcdn-modal-content">
						<iframe id="noobrocketcdn-iframe" data-src="<?php echo esc_url( $iframe_src ); ?>" loading="lazy" width="674" height="425"></iframe>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Filter the arguments used in an HTTP request, to make sure our user token has not been overwritten
	 * by some other plugin.
	 *
	 * @since  3.5
	 *
	 * @param  array  $args An array of HTTP request arguments.
	 * @param  string $url  The request URL.
	 * @return array
	 */
	public function preserve_authorization_token( $args, $url ) {
		return $this->api_client->preserve_authorization_token( $args, $url );
	}

	/**
	 * Adds the 'plugin_noobrocketcdn' option to the recommendations API parameters if certain conditions are met.
	 *
	 * @param array $params The existing API parameters.
	 * @return array The modified API parameters with 'plugin_noobrocketcdn' added if applicable.
	 */
	public function maybe_add_noobrocketcdn_to_recommendations_api_params( array $params ): array {
		if ( ! $this->should_add_to_recommendations_api_params( $params ) ) {
			return $params;
		}

		$params['enabled_options'][] = 'plugin_noobrocketcdn';

		return $params;
	}

	/**
	 * Determines whether to add the 'plugin_noobrocketcdn' option to the recommendations API parameters.
	 *
	 * This method checks multiple conditions to decide if the user should be included:
	 * - Returns true if the account is a white label account.
	 * - Returns true if the HostedCDN standalone is active.
	 * - Returns true if CDN option in NoobRocket is enabled.
	 * - Returns false otherwise.
	 *
	 * @param array $params API params.
	 * @return bool True if the user should be added to the recommendations API parameters, false otherwise.
	 */
	private function should_add_to_recommendations_api_params( $params ): bool {
		// Return true if white label is true.
		if ( $this->is_white_label_account() ) {
			return true;
		}

		if ( ! empty( noobrocket_get_constant( 'HOSTEDCDN_VERSION' ) ) ) {
			return true;
		}

		if ( in_array( 'cdn', $params['enabled_options'], true ) ) {
			return true;
		}

		return false;
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
}
