<?php

namespace NoobRocket\Engine\CriticalPath\Admin;

use NoobRocket\Abstract_Render;
use NoobRocket\Admin\Options_Data;
use NoobRocket\Engine\Admin\Beacon\Beacon;
use NoobRocket\Engine\CriticalPath\CriticalCSS;

class Settings extends Abstract_Render {
	/**
	 * Instance of the Beacon handler.
	 *
	 * @var Beacon
	 */
	private $beacon;

	/**
	 * Instance of options handler.
	 *
	 * @var Options_Data
	 */
	private $options;

	/**
	 * Instance of CriticalCSS.
	 *
	 * @var CriticalCSS
	 */
	private $critical_css;

	/**
	 * Creates an instance of the subscriber.
	 *
	 * @param Options_Data $options       NoobRocket Options instance.
	 * @param Beacon       $beacon        Beacon instance.
	 * @param CriticalCSS  $critical_css  CriticalCSS instance.
	 * @param string       $template_path Path to the templates folder.
	 */
	public function __construct( Options_Data $options, Beacon $beacon, CriticalCSS $critical_css, $template_path ) {
		parent::__construct( $template_path );

		$this->beacon       = $beacon;
		$this->options      = $options;
		$this->critical_css = $critical_css;
	}

	/**
	 * Display CPCSS mobile section tool admin view.
	 *
	 * @since 3.6
	 *
	 * @return void
	 */
	public function display_cpcss_mobile_section() {
		if ( ! current_user_can( 'noobrocket_manage_options' ) ) {
			return;
		}

		// Bailout if CPCSS is not enabled & separate cache for mobiles is not enabled.
		// Or bailout if CPCSS mobile option is false.
		if (
			! (
				$this->options->get( 'async_css', 0 )
				&&
				$this->options->get( 'cache_mobile', 0 )
				&&
				$this->options->get( 'do_caching_mobile_files', 0 )
			)
			||
			$this->options->get( 'async_css_mobile', 0 )
		) {
			return;
		}

		$data = [
			'beacon' => $this->beacon->get_suggest( 'async' ),
		];

		echo $this->generate( 'activate-cpcss-mobile', $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Enable CPCSS mobile.
	 *
	 * @since 3.6
	 *
	 * @return void
	 */
	public function enable_mobile_cpcss() {
		check_ajax_referer( 'noobrocket-ajax', 'nonce', true );

		if ( ! current_user_can( 'noobrocket_manage_options' ) || ! current_user_can( 'noobrocket_regenerate_critical_css' ) ) {
			wp_send_json_error();
		}

		$this->options->set( 'async_css_mobile', 1 );
		update_option( noobrocket_get_constant( 'NOOBROCKET_SLUG', 'noobrocket_settings' ), $this->options->get_options() );

		// Start Mobile CPCSS process.
		$this->critical_css->process_handler( 'mobile' );

		wp_send_json_success();
	}

	/**
	 * Adds async_css_mobile option to NoobRocket options.
	 *
	 * @since 3.6
	 *
	 * @param array $options NoobRocket options array.
	 *
	 * @return array
	 */
	public function add_async_css_mobile_option( $options ) {
		$options = (array) $options;

		$options['async_css_mobile'] = 1;

		return $options;
	}

	/**
	 * Sets the default value of async_css_mobile to 0 when upgrading from < 3.6.
	 *
	 * @since 3.6
	 *
	 * @param string $new_version New NoobRocket version.
	 * @param string $old_version Previous NoobRocket version.
	 */
	public function set_async_css_mobile_default_value( $new_version, $old_version ) {
		if ( version_compare( $old_version, '3.6', '>' ) ) {
			return;
		}

		$options = get_option( 'noobrocket_settings', [] );

		$options['async_css_mobile'] = 0;

		update_option( 'noobrocket_settings', $options );
	}

	/**
	 * Adds async_css_mobile to the hidden settings fields.
	 *
	 * @since 3.6
	 *
	 * @param array $hidden_settings_fields An array of hidden settings fields ID.
	 *
	 * @return array
	 */
	public function add_hidden_async_css_mobile( $hidden_settings_fields ) {
		$hidden_settings_fields = (array) $hidden_settings_fields;

		$hidden_settings_fields[] = 'async_css_mobile';

		return $hidden_settings_fields;
	}
}
