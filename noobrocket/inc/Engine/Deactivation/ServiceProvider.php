<?php
namespace NoobRocket\Engine\Deactivation;

use NoobRocket\Dependencies\League\Container\Argument\Literal\StringArgument;
use NoobRocket\Dependencies\League\Container\ServiceProvider\{AbstractServiceProvider, BootableServiceProviderInterface};
use NoobRocket\Engine\Cache\{AdvancedCache, WPCache};
use NoobRocket\Engine\Capabilities\Manager;
use NoobRocket\ThirdParty\Plugins\CDN\{Cloudflare, CloudflareFacade};

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
		'cloudflare_plugin_facade',
		'cloudflare_plugin_subscriber',
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
			->inflector( DeactivationInterface::class )
			->invokeMethod( 'deactivate', [] );
	}

	/**
	 * Registers the option array in the container.
	 */
	public function register(): void {
		$filesystem = noobrocket_direct_filesystem();

		$this->getContainer()->add( 'cloudflare_plugin_facade', CloudflareFacade::class );
		$this->getContainer()
			->addShared( 'cloudflare_plugin_subscriber', Cloudflare::class )
			->addArgument( 'options' )
			->addArgument( 'options_api' )
			->addArgument( 'beacon' )
			->addArgument( 'cloudflare_plugin_facade' );

		$this->getContainer()->add( 'advanced_cache', AdvancedCache::class )
			->addArgument( new StringArgument( $this->getContainer()->get( 'template_path' ) . '/cache/' ) )
			->addArgument( $filesystem );
		$this->getContainer()->add( 'capabilities_manager', Manager::class );
		$this->getContainer()->add( 'wp_cache', WPCache::class )
			->addArgument( $filesystem );
	}
}
