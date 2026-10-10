<?php

namespace NoobRocket\Engine\License\API;

use NoobRocket\Admin\Options_Data;
use NoobRocket\Engine\Common\JobManager\APIHandler\AbstractSafeAPIClient;

/** Local account compatibility service. NoobRocket has no license account. */
class UserClient extends AbstractSafeAPIClient {
	/** @param Options_Data $options Local plugin options, retained for compatibility. */
	public function __construct( Options_Data $options ) {
	}

	protected function get_transient_key() {
		return 'nbr_user_information';
	}

	protected function get_api_url() {
		return '';
	}

	/** @return object Empty local account data; no cloud entitlement is implied. */
	public function get_user_data() {
		return new \stdClass();
	}

	/** @return void */
	public function flush_cache() {
		delete_transient( 'noobrocket_customer_data' );
	}
}
