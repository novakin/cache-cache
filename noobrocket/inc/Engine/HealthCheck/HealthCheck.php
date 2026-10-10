<?php

namespace NoobRocket\Engine\HealthCheck;

use NoobRocket\Admin\Options_Data;
use NoobRocket\Event_Management\Subscriber_Interface;

class HealthCheck implements Subscriber_Interface {
	/**
	 * Instance of options.
	 *
	 * @var Options_Data
	 */
	private $options;

	/**
	 * Creates an instance of the health checker.
	 *
	 * @param Options_Data $options Options_Data instance.
	 */
	public function __construct( Options_Data $options ) {
		$this->options = $options;
	}

	/**
	 * Returns an array of events that this subscriber wants to listen to.
	 *
	 * @return array
	 */
	public static function get_subscribed_events() {
		return [
			'admin_notices' => 'missed_cron',
		];
	}

	/**
	 * Display a warning notice if NoobRocket scheduled events are not running properly.
	 *
	 * @since 3.5.4
	 */
	public function missed_cron() {
		if ( ! $this->should_check() ) {
			return;
		}

		$delay  = noobrocket_get_constant( 'DISABLE_WP_CRON' ) ? HOUR_IN_SECONDS : 5 * MINUTE_IN_SECONDS;
		$list   = '';
		$events = $this->get_events();

		foreach ( $events as $event => $description ) {
			$timestamp = wp_next_scheduled( $event );

			if (
				false === $timestamp
				||
				( $timestamp + $delay - time() ) > 0
			) {
				unset( $events[ $event ] );
				continue;
			}

			$list .= "<li>{$description}</li>";
		}

		if ( empty( $events ) ) {
			return;
		}

		$message = sprintf(
			'<p>%1$s</p>
			<ul>%2$s</ul>
			<p>%3$s</p>',
			_n(
				'The following scheduled event failed to run. This may indicate the CRON system is not running properly, which can prevent some Cache-Cache features from working as intended:',
				'The following scheduled events failed to run. This may indicate the CRON system is not running properly, which can prevent some Cache-Cache features from working as intended:',
				count( $events ),
				'noobrocket'
			),
			$list,
			__( 'Please contact your host to check if CRON is working.', 'noobrocket' )
		);

		noobrocket_notice_html(
			[
				'status'         => 'warning',
				'dismissible'    => '',
				'message'        => $message,
				'dismiss_button' => 'noobrocket_warning_cron',
			]
		);
	}

	/**
	 * Checks if health check should run.
	 *
	 * @since 3.5.4
	 *
	 * @return bool true when should do health check; else, false.
	 */
	protected function should_check() {
		if ( ! current_user_can( 'noobrocket_manage_options' ) ) {
			return false;
		}

		if ( 'settings_page_noobrocket' !== get_current_screen()->id ) {
			return false;
		}

		$dismissed = (array) get_user_meta( get_current_user_id(), 'noobrocket_boxes', true );
		if ( in_array( 'noobrocket_warning_cron', $dismissed, true ) ) {
			return false;
		}

		return ! (
			0 === (int) $this->options->get( 'purge_cron_interval', 0 )
			&&
			0 === (int) $this->options->get( 'async_css', 0 )
			&&
			0 === (int) $this->options->get( 'manual_preload', 0 )
			&&
			0 === (int) $this->options->get( 'schedule_automatic_cleanup', 0 )
		);
	}

	/**
	 * Gets an array of events with their descriptions.
	 *
	 * @since 3.5.4
	 *
	 * @return array array of events => descriptions.
	 */
	protected function get_events() {
		return [
			'noobrocket_purge_time_event'                      => __( 'Scheduled Cache Purge', 'noobrocket' ),
			'noobrocket_database_optimization_time_event'      => __( 'Scheduled Database Optimization', 'noobrocket' ),
			'noobrocket_database_optimization_cron_interval'   => __( 'Database Optimization Process', 'noobrocket' ),
			'noobrocket_preload_process_pending'               => _x( 'Preload', 'noun', 'noobrocket' ),
			'noobrocket_critical_css_generation_cron_interval' => __( 'Critical Path CSS Generation Process', 'noobrocket' ),
		];
	}
}
