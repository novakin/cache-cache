<?php
declare(strict_types=1);

namespace NoobRocket\Engine\License\API;

use NoobRocket\Admin\Options_Data;
use NoobRocket\Engine\Common\JobManager\APIHandler\AbstractSafeAPIClient;

/** Local settings compatibility service. No remote account settings are used. */
class RemoteSettingsClient extends AbstractSafeAPIClient {
	/** @param Options_Data $options Local plugin options, retained for compatibility. */
	public function __construct( Options_Data $options ) {
	}

	protected function get_transient_key(): string {
		return 'noobrocket_remote_settings';
	}

	protected function get_api_url(): string {
		return '';
	}

	/** @return object Empty local settings. */
	public function get_remote_settings_data() {
		return new \stdClass();
	}

	/** @return void */
	public function flush_cache(): void {
		delete_transient( $this->get_transient_key() );
	}
}
