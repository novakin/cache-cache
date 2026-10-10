<?php
declare(strict_types=1);

namespace NoobRocket\Engine\CDN;

use NoobRocket\Dependencies\League\Container\Argument\Literal\StringArgument;
use NoobRocket\Dependencies\League\Container\ServiceProvider\AbstractServiceProvider;
use NoobRocket\Engine\CDN\Admin\Subscriber as AdminSubscriber;
use NoobRocket\Engine\CDN\Drivers\{
	Custom,
	DriverFactory,
	HostedCDNFree,
	HostedCDNPaid
};
use NoobRocket\Engine\CDN\Render\{
	Controller as RenderController,
	Subscriber as RenderSubscriber
};

/**
 * Service provider for NoobRocket CDN
 */
class ServiceProvider extends AbstractServiceProvider {
	/**
	 * Array of services provided by this service provider
	 *
	 * @var array
	 */
	protected $provides = [
		'cdn',
		'cdn_cname_validator',
		'cdn_context',
		'cdn_subscriber',
		'cdn_admin_subscriber',
		'cdn_render_controller',
		'cdn_render_subscriber',
		'cdn_driver_factory',
		'cdn_driver_free',
		'cdn_driver_paid',
		'cdn_driver_byocdn',
		'cdn_driver',
		'cache_controller',
		'cdn_state_bridge',
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
	 * Registers items with the container
	 *
	 * @return void
	 */
	public function register(): void {
		$this->getContainer()->add( 'cache_controller', Cache::class )
			->addArgument( 'noobrocketcdn_query' );
		$this->getContainer()->addShared( 'cdn_cname_validator', CNAMEValidator::class );
		$this->getContainer()->addShared( 'cdn', CDN::class )
			->addArgument( 'options' );
		$this->getContainer()->addShared( 'cdn_context', Context::class )
			->addArguments(
				[
					'options',
					'noobrocketcdn_subscription_controller',
					'user',
				]
			);

		// Register individual drivers.
		$this->getContainer()->add(
			'cdn_driver_free',
			HostedCDNFree::class
		)->addArgument( 'noobrocketcdn_query' );

		$this->getContainer()->add(
			'cdn_driver_paid',
			HostedCDNPaid::class
		)->addArgument( 'options' );

		$this->getContainer()->add(
			'cdn_driver_byocdn',
			Custom::class
		);

		// Register Driver Factory.
		$this->getContainer()->addShared(
			'cdn_driver_factory',
			DriverFactory::class
		)
			->addArgument( $this->getContainer() )
			->addArgument( 'cdn_context' );

		// Register current active driver (resolved at runtime).
		$this->getContainer()->add(
			'cdn_driver',
			function () {
				$factory = $this->getContainer()->get( 'cdn_driver_factory' );
				return $factory->create();
			}
		);

		$this->getContainer()->addShared( 'cdn_subscriber', Subscriber::class )
			->addArguments(
				[
					'options',
					'cdn',
					'options_api',
					'noobrocketcdn_subscription_controller',
					'cache_controller',
					'noobrocketcdn_query',
					'cdn_state_bridge',
					'cdn_driver',
					'cdn_cname_validator',
				]
			);
		$this->getContainer()->addShared( 'cdn_admin_subscriber', AdminSubscriber::class );

		// CDN state compatibility bridge (HostedCDN refactor epic #8693 - temporary, removed in Story 10).
		$this->getContainer()->addShared( 'cdn_state_bridge', CdnStateBridge::class )
			->addArguments(
				[
					'noobrocketcdn_subscription_controller',
					'options_api',
				]
			);

		// CDN Render controller.
		$this->getContainer()->addShared( 'cdn_render_controller', RenderController::class )
			->addArguments(
				[
					'beacon',
					new StringArgument( noobrocket_get_constant( 'NOOBROCKET_PATH', '' ) . 'views/settings/' ),
					'cdn_context',
					'options',
					'options_api',
					'noobrocketcdn_query',
					'noobrocketcdn_subscription_controller',
					'user',
					'cache_controller',
				]
			);

		// CDN Render subscriber.
		$this->getContainer()->addShared( 'cdn_render_subscriber', RenderSubscriber::class )
			->addArgument( 'cdn_render_controller' );
	}
}
