<?php

namespace NoobRocket\Engine\Admin\Settings;

trait DataClearingTrait {

	/**
	 * Truncate data table when action is taken
	 *
	 * @param array  $data An array containing the status and message.
	 * @param string $transient The transient key to set after cleaning.
	 *
	 * @return void
	 */
	protected function clean_data( $data, string $transient ): void {
		if ( ! current_user_can( 'noobrocket_manage_options' ) ) {
			return;
		}

		if (
			empty( $data )
			||
			'die' === $data['status']
		) {
			wp_safe_redirect( esc_url_raw( wp_get_referer() ) );
			noobrocket_get_constant( 'NOOBROCKET_IS_TESTING', false ) ? wp_die() : exit;
		}

		if ( 'error' === $data['status'] ) {
			wp_safe_redirect( esc_url_raw( wp_get_referer() ) );
			noobrocket_get_constant( 'NOOBROCKET_IS_TESTING', false ) ? wp_die() : exit;
		}

		noobrocket_clean_domain();

		noobrocket_dismiss_box( 'noobrocket_warning_plugin_modification' );

		set_transient(
			$transient,
			$data
		);

		wp_safe_redirect( esc_url_raw( wp_get_referer() ) );
		noobrocket_get_constant( 'NOOBROCKET_IS_TESTING', false ) ? wp_die() : exit;
	}
}
