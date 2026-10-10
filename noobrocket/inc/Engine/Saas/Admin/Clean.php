<?php
declare(strict_types=1);

namespace NoobRocket\Engine\Saas\Admin;

use NoobRocket\Admin\Options_Data;
use NoobRocket\Engine\Admin\Settings\DataClearingTrait;

class Clean {
	use DataClearingTrait;

	/**
	 * Truncate SaaS tables when clicking on the dashboard button
	 *
	 * @return void
	 */
	public function clean_saas() {
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'noobrocket_clean_saas' ) ) {
			wp_nonce_ays( '' );
		}

		/**
		 * Filters the value of the SaaS clean
		 *
		 * @since 3.16
		 *
		 * @param array $clean An array containing the status and message.
		 */
		$clean = wpm_apply_filters_typed( 'array', 'noobrocket_saas_clean_all', [] );

		$this->clean_data( $clean, 'noobrocket_saas_clean_message' );
	}

	/**
	 * Clean SaaS for the current URL.
	 *
	 * @return void
	 */
	public function clean_url_saas() {
		check_admin_referer( 'noobrocket_clean_saas_url' );

		/**
		 * Fires when cleaning a single URL for the Saas
		 *
		 * @since 3.16
		 */
		do_action( 'noobrocket_saas_clean_url' );

		wp_safe_redirect( esc_url_raw( wp_get_referer() ) );
		noobrocket_get_constant( 'NOOBROCKET_IS_TESTING', false ) ? wp_die() : exit;
	}
}
