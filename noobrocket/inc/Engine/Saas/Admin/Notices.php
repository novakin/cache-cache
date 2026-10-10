<?php
declare(strict_types=1);

namespace NoobRocket\Engine\Saas\Admin;

use NoobRocket\Admin\Options_Data;
use NoobRocket\Engine\Admin\Beacon\Beacon;
use NoobRocket\Engine\Common\Context\ContextInterface;

class Notices {
	/**
	 * Options Data instance
	 *
	 * @var Options_Data
	 */
	private $options;

	/**
	 * Beacon instance.
	 *
	 * @var Beacon
	 */
	private $beacon;

	/**
	 * Constructor
	 *
	 * @param Options_Data $options Options_Data instance.
	 * @param Beacon       $beacon Beacon instance.
	 */
	public function __construct( Options_Data $options, Beacon $beacon ) {
		$this->options = $options;
		$this->beacon  = $beacon;
	}

	/**
	 * Show admin notice after clearing SaaS tables.
	 *
	 * @return void
	 */
	public function clean_saas_result() {
		if ( ! current_user_can( 'noobrocket_manage_options' ) ) {
			return;
		}

		if ( ! $this->options->get( 'remove_unused_css', 0 ) ) {
			return;
		}

		$response = get_transient( 'noobrocket_saas_clean_message' );

		if ( ! $response ) {
			return;
		}

		delete_transient( 'noobrocket_saas_clean_message' );

		noobrocket_notice_html( $response );
	}

	/**
	 * Displays the SaaS currently processing notice
	 *
	 * @return void
	 */
	public function display_processing_notice() {
		if ( $this->has_saas_error_notice() ) {
			return;
		}

		if ( ! $this->can_display_notice() ) {
			return;
		}

		$transient = get_transient( 'noobrocket_saas_processing' );

		if ( false === $transient ) {
			return;
		}

		$current_time = time();

		if ( $transient < $current_time ) {
			return;
		}

		$remaining = $transient - $current_time;

		$message = sprintf(
			// translators: %1$s = plugin name, %2$s = number of seconds.
			__( '%1$s: Please wait %2$s seconds. The Remove Unused CSS service is processing your pages, the plugin is optimizing LCP and the images above the fold.', 'noobrocket' ),
			'<strong>NoobRocket</strong>',
			'<span id="noobrocket-rucss-timer">' . $remaining . '</span>'
		);

		noobrocket_notice_html(
			[
				'status'  => 'info',
				'message' => $message,
				'id'      => 'noobrocket-notice-saas-processing',
			]
		);
	}

	/**
	 * Displays the SaaS success notice
	 *
	 * @return void
	 */
	public function display_success_notice() {
		if ( ! $this->can_display_notice() ) {
			return;
		}

		if ( $this->has_saas_error_notice() ) {
			return;
		}

		$boxes = get_user_meta( get_current_user_id(), 'noobrocket_boxes', true );

		if ( in_array( 'saas_success_notice', (array) $boxes, true ) ) {
			return;
		}

		$transient = get_transient( 'noobrocket_saas_processing' );
		$class     = '';

		if ( false !== $transient || ( ! $this->options->get( 'remove_unused_css', 0 ) ) ) {
			$class = 'hidden';
		}

		$message = sprintf(
			// translators: %1$s = plugin name, %2$s = number of URLs, %3$s = number of seconds.
			__(
				'%1$s: The LCP element has been optimized, and the images above the fold were excluded from lazyload. The Used CSS of your homepage has been processed.
			 Cache-Cache will continue to generate Used CSS for up to %2$s URLs per %3$s second(s).',
				'noobrocket'
				),
			'<strong>NoobRocket</strong>',
			noobrocket_apply_filter_and_deprecated(
				'noobrocket_saas_pending_jobs_cron_rows_count',
				[ 100 ],
				'3.16',
				'noobrocket_rucss_pending_jobs_cron_rows_count'
			),
			noobrocket_apply_filter_and_deprecated(
				'noobrocket_saas_pending_jobs_cron_interval',
				[ MINUTE_IN_SECONDS ],
				'3.16',
				'noobrocket_rucss_pending_jobs_cron_interval'
			)
		);

		if ( ! $this->options->get( 'manual_preload', 0 ) ) {
			$message .= ' ' . sprintf(
				// translators: %1$s = opening link tag, %2$s = closing link tag.
				__( 'We suggest enabling %1$sPreload%2$s for the fastest results.', 'noobrocket' ),
				'<a href="#preload">',
				'</a>'
			);
		}

		$beacon = $this->beacon->get_suggest( 'async_opti' );

		$message .= '<br>' . sprintf(
			// translators: %1$s = opening link tag, %2$s = closing link tag.
			__( 'To learn more about the process check our %1$sdocumentation%2$s.', 'noobrocket' ),
			'<a href="' . esc_url( $beacon['url'] ) . '" data-beacon-article="' . esc_attr( $beacon['id'] ) . '" rel="noopener noreferrer" target="_blank">',
			'</a>'
		);

		noobrocket_notice_html(
			[
				'message'              => $message,
				'dismissible'          => $class,
				'id'                   => 'noobrocket-notice-saas-success',
				'dismiss_button'       => 'saas_success_notice',
				'dismiss_button_class' => 'button-primary',
			]
		);
	}

	/**
	 * Adds the notice end time to NoobRocket localize script data
	 *
	 * @since 3.11
	 *
	 * @param array $data Localize script data.
	 *
	 * @return array
	 */
	public function add_localize_script_data( array $data ): array {
		if ( ! $this->options->get( 'remove_unused_css', 0 ) ) {
			return $data;
		}

		$transient = get_transient( 'noobrocket_saas_processing' );

		if ( false === $transient ) {
			return $data;
		}

		$data['notice_end_time'] = $transient;
		$data['cron_disabled']   = noobrocket_get_constant( 'DISABLE_WP_CRON', false );

		return $data;
	}

	/**
	 * Display a notification on wrong license.
	 *
	 * @return void
	 */
	public function display_wrong_license_notice() {
		$transient = get_option( 'noobrocket_no_licence' );

		if ( ! $transient ) {
			return;
		}

		if ( ! $this->can_display_notice() ) {
			return;
		}

		$main_message = __( "We couldn't generate the used CSS because you're using a nulled version of Cache-Cache. You need an active license to use the Remove Unused CSS feature and further improve your website's performance.", 'noobrocket' );
		$cta_message  = sprintf(
			// translators: %1$s = promo percentage.
			__( 'Click here to get a Cache-Cache single license at %1$s off!', 'noobrocket' ),
			'10%%'
		);

		$message = sprintf(
		// translators: %1$s = plugin name, %2$s = opening anchor tag, %3$s = closing anchor tag.
			"%1\$s: <p>$main_message</p>%2\$s$cta_message%3\$s",
			'<strong>NoobRocket</strong>',
			'<a href="https://www.noobunbox.net/" class="button button-primary" rel="noopener noreferrer" target="_blank">',
			'</a>'
		);

		noobrocket_notice_html(
			[
				'status'      => 'error',
				'dismissible' => '',
				'message'     => $message,
				'id'          => 'noobrocket-notice-rucss-wrong-licence',
			]
		);
	}

	/**
	 * Display an error notice when the connection to the server fails
	 *
	 * @return void
	 */
	public function display_saas_error_notice() {

		if ( ! $this->has_saas_error_notice() ) {
			$boxes = get_user_meta( get_current_user_id(), 'noobrocket_boxes', true );
			if ( in_array( 'rucss_saas_error_notice', (array) $boxes, true ) ) {
				unset( $boxes['rucss_saas_error_notice'] );
				update_user_meta( get_current_user_id(), 'noobrocket_boxes', $boxes );
			}

			return;
		}

		if ( ! $this->can_display_notice() ) {
			return;
		}

		$boxes = get_user_meta( get_current_user_id(), 'noobrocket_boxes', true );

		if ( in_array( 'saas_error_notice', (array) $boxes, true ) ) {
			return;
		}

		$firewall_beacon = $this->beacon->get_suggest( 'rucss_firewall_ips' );

		$main_message = sprintf(
			// translators: %1$s = <a> open tag, %2$s = </a> closing tag.
			__( 'It seems a security plugin or the server\'s firewall prevents Cache-Cache from accessing the SaaS features. IPs listed %1$shere in our documentation%2$s should be added to your allowlists:', 'noobrocket' ),
			'<a href="' . esc_url( $firewall_beacon['url'] ) . '" data-beacon-article="' . esc_attr( $firewall_beacon['id'] ) . '" rel="noopener noreferrer" target="_blank">',
			'</a>'
		);

		$security_message = __( '- In the security plugin, if you are using one', 'noobrocket' );
		$firewall_message = __( "- In the server's firewall. Your host can help you with this", 'noobrocket' );

		$message = "<strong>NoobRocket</strong>: $main_message<ul><li>$security_message</li><li>$firewall_message</li></ul>";

		noobrocket_notice_html(
			[
				'status'               => 'error',
				'message'              => $message,
				'dismissible'          => '',
				'id'                   => 'noobrocket-notice-rucss-error-http',
				'dismiss_button'       => 'saas_error_notice',
				'dismiss_button_class' => 'button-primary',
			]
		);
	}

	/**
	 * Checks if we can display the SaaS notices
	 *
	 * @return bool
	 */
	private function can_display_notice(): bool {
		$screen = get_current_screen();

		if (
			isset( $screen->id )
			&&
			'settings_page_noobrocket' !== $screen->id
		) {
			return false;
		}

		if ( ! current_user_can( 'noobrocket_manage_options' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Is the error notice present.
	 *
	 * @return bool
	 */
	private function has_saas_error_notice() {
		return (bool) get_transient( 'noobrocket_rucss_errors_count' );
	}
}
