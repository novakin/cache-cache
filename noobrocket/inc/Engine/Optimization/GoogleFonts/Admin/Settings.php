<?php

namespace NoobRocket\Engine\Optimization\GoogleFonts\Admin;

use NoobRocket\Abstract_Render;
use NoobRocket\Admin\Options_Data;
use NoobRocket\Engine\Admin\Beacon\Beacon;

class Settings extends Abstract_Render {
	/**
	 * NoobRocket options instance
	 *
	 * @var Options_Data
	 */
	private $options;

	/**
	 * Beacon instance
	 *
	 * @var Beacon
	 */
	private $beacon;

	/**
	 * Instantiate the class
	 *
	 * @param Options_Data $options       NoobRocket options instance.
	 * @param Beacon       $beacon        Beacon instance.
	 * @param string       $template_path Path to template files.
	 */
	public function __construct( Options_Data $options, Beacon $beacon, $template_path ) {
		parent::__construct( $template_path );

		$this->options = $options;
		$this->beacon  = $beacon;
	}

	/**
	 * Displays the Google Fonts Optimization section in the tools tab
	 *
	 * @since 3.7
	 *
	 * @return void
	 */
	public function display_google_fonts_enabler() {
		if ( ! current_user_can( 'noobrocket_manage_options' ) ) {
			return;
		}

		if ( ! apply_filters( 'pre_get_noobrocket_option_minify_google_fonts', true ) ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			return;
		}

		if ( $this->options->get( 'minify_google_fonts', 0 ) ) {
			return;
		}

		$data = [
			'beacon' => $this->beacon->get_suggest( 'google_fonts' ),
		];

		echo $this->generate( 'settings/enable-google-fonts', $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Callback method for the AJAX request to enable Google Fonts Optimization
	 *
	 * @since 3.7
	 *
	 * @return void
	 */
	public function enable_google_fonts() {
		check_ajax_referer( 'noobrocket-ajax', 'nonce', true );

		if ( ! current_user_can( 'noobrocket_manage_options' ) ) {
			wp_send_json_error();
			return;
		}

		$this->options->set( 'minify_google_fonts', 1 );
		$option_name = noobrocket_get_constant( 'NOOBROCKET_SLUG', 'noobrocket_settings' );
		update_option( $option_name, $this->options->get_options() );

		// The options snapshot is already changed; verify the native persisted value.
		$saved = get_option( $option_name, [] );
		if ( ! is_array( $saved ) || ! in_array( $saved['minify_google_fonts'] ?? 0, [ 1, '1' ], true ) ) {
			wp_send_json_error();
			return;
		}

		wp_send_json_success();
	}
}
