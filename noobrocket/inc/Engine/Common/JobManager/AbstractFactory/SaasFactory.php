<?php
declare( strict_types=1 );

namespace NoobRocket\Engine\Common\JobManager\AbstractFactory;

use NoobRocket\Engine\Common\JobManager\APIHandler\AbstractAPIClient;
use NoobRocket\Engine\Common\JobManager\Managers\ManagerInterface;
use NoobRocket\Engine\Common\Database\TableInterface;

interface SaasFactory {
	/**
	 * SaaS job manager.
	 *
	 * @return ManagerInterface
	 */
	public function manager(): ManagerInterface;

	/**
	 * Job table.
	 *
	 * @return TableInterface
	 */
	public function table(): TableInterface;

	/**
	 * API Client.
	 *
	 * @return AbstractAPIClient
	 */
	public function api(): AbstractAPIClient;
}
