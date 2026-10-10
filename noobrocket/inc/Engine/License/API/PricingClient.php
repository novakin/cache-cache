<?php

namespace NoobRocket\Engine\License\API;

use NoobRocket\Engine\Common\JobManager\APIHandler\AbstractSafeAPIClient;

/** Local pricing compatibility service. NoobRocket is free. */
class PricingClient extends AbstractSafeAPIClient {
	protected function get_transient_key() {
		return 'noobrocket_pricing';
	}

	protected function get_api_url() {
		return '';
	}

	/** @return object Empty local pricing data. */
	public function get_pricing_data() {
		return new \stdClass();
	}
}
