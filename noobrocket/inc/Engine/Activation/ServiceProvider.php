<?php
declare(strict_types=1);

namespace NoobRocket\Engine\Activation;

use NoobRocket\Dependencies\League\Container\Argument\Literal\StringArgument;
use NoobRocket\Dependencies\League\Container\ServiceProvider\AbstractServiceProvider;
use NoobRocket\Dependencies\League\Container\ServiceProvider\BootableServiceProviderInterface;
use NoobRocket\Engine\Cache\AdvancedCache;
use NoobRocket\Engine\Cache\WPCache;
use NoobRocket\Engine\Capabilities\Manager;
use NoobRocket\Engine\HealthCheck\ActionSchedulerCheck;

/**
 * Service Provider for the activation process.
 */
class ServiceProvider extends AbstractServiceProvider implements BootableServiceProviderInterface {
	/**
	 * Array of services provided by this service provider
	 *
	 * @var array
	 */
	protected $provides = [
		'advanced_cache',
		'capabilities_manager',
		'wp_cache',
		'action_scheduler_check',
	];

	/**
	 * Check if the service provider provides a specific service.
	 *
	 * @param string $id The id of the service.
	 *
	 * @return bool
	 */
	public function provides( string $id ): bool {
		return in_array( $id, $this->provides, true );
	}

	/**
	 * Executes this method when the service provider is registered
	 *
	 * @return void
	 */
	public function boot(): void {
		$this->getContainer()
			->inflector( ActivationInterface::class )
			->invokeMethod( 'activate', [] );
	}

	/**
	 * Registers the option array in the container.
	 */
	public function register(): void {
		$filesystem = noobrocket_direct_filesystem();

		$this->getContainer()->add( 'advanced_cache', AdvancedCache::class )
			->addArguments(
				[
					new StringArgument( $this->getContainer()->get( 'template_path' ) . '/cache/' ),
					$filesystem,
				]
			);
		$this->getContainer()->add( 'capabilities_manager', Manager::class );
		$this->getContainer()->add( 'wp_cache', WPCache::class )
			->addArgument( $filesystem );
		$this->getContainer()->add( 'action_scheduler_check', ActionSchedulerCheck::class );
	}
}
