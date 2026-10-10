<?php

namespace NoobRocket\Engine\CriticalPath;

use WP_Error;

/** Critical CSS API compatibility surface; cloud generation is unavailable. */
class APIClient {
	/** Cloud endpoint is unavailable; no request is made. */
	const API_URL = '';

	/**
	 * @param string $url Page URL.
	 * @param array  $params Generation parameters.
	 * @param string $item_type Content type.
	 * @return WP_Error Cloud generation is unavailable.
	 */
	public function send_generation_request( $url, $params = [], $item_type = 'custom' ) {
		return $this->cloud_unavailable();
	}

	/**
	 * @param string $job_id Generation job ID.
	 * @param string $url Page URL.
	 * @param bool   $is_mobile Whether the job is for mobile.
	 * @param string $item_type Content type.
	 * @return WP_Error Cloud generation is unavailable.
	 */
	public function get_job_details( $job_id, $url, $is_mobile = false, $item_type = 'custom' ) {
		return $this->cloud_unavailable();
	}

	/** @return WP_Error Cloud generation is not included in this local plugin. */
	private function cloud_unavailable() {
		return new WP_Error(
			'noobrocket_cloud_unavailable',
			__( 'Critical CSS cloud generation is unavailable in Cache-Cache.', 'noobrocket' ),
			[ 'status' => 503 ]
		);
	}
}
