<?php
declare(strict_types=1);

namespace NoobRocket\Engine\License\API;

/** Legacy account accessors retained without collecting license credentials. */
trait CustomerDataTrait {
	/** @return array NoobRocket does not have a license account. */
	public function get_customer_data(): array {
		return [];
	}

	/** @return string No license key is used. */
	protected function get_customer_key(): string {
		return '';
	}

	/** @return string No license account email is used. */
	protected function get_customer_email(): string {
		return '';
	}
}
