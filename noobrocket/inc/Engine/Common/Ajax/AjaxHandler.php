<?php

namespace NoobRocket\Engine\Common\Ajax;

class AjaxHandler {

	/**
	 * Validate the referer before going further.
	 *
	 * @param string $action Action to validate.
	 * @param string $capacities User capacity to verify.
	 * @return bool
	 */
	public function validate_referer( string $action, string $capacities ) {

		check_admin_referer( $action );

		if ( ! current_user_can( $capacities ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Redirect the page at the end of the logic.
	 *
	 * @param string $url URl to redirect to (by default referrer).
	 * @return void
	 */
	public function redirect( string $url = '' ) {
		wp_safe_redirect( '' === $url ? wp_get_referer() : $url );
		noobrocket_get_constant( 'NOOBROCKET_IS_TESTING', false ) ? wp_die() : exit;
	}
}
