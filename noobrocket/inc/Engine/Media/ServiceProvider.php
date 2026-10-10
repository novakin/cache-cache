<?php
declare(strict_types=1);

namespace NoobRocket\Engine\Media;

use NoobRocket\Buffer\{Config, Tests};
use NoobRocket\Dependencies\League\Container\Argument\Literal\ArrayArgument;
use NoobRocket\Dependencies\League\Container\ServiceProvider\AbstractServiceProvider;
use NoobRocket\Dependencies\NoobRocketLazyload\{Assets, Iframe, Image};
use NoobRocket\Engine\Media\Emojis\EmojisSubscriber;
use NoobRocket\Engine\Media\ImageDimensions\{
	AdminSubscriber as ImageDimensionsAdminSubscriber,
	ImageDimensions,
	Subscriber as ImageDimensionsSubscriber
};
use NoobRocket\Engine\Media\Lazyload\{
	AdminSubscriber as LazyloadAdminSubscriber,
	Subscriber
};

/**
 * Service provider for Media module
 */
class ServiceProvider extends AbstractServiceProvider {
	/**
	 * Array of services provided by this service provider
	 *
	 * @var array
	 */
	protected $provides = [
		'config',
		'tests',
		'lazyload_assets',
		'lazyload_image',
		'lazyload_iframe',
		'lazyload_subscriber',
		'lazyload_admin_subscriber',
		'emojis_subscriber',
		'image_dimensions',
		'image_dimensions_subscriber',
		'image_dimensions_admin_subscriber',
		'picture_subscriber',
		'picture_generator',
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
		$this->getContainer()->addShared( 'picture_subscriber', Picture\Subscriber::class )->addArgument( 'options' );
		$this->getContainer()->addShared( 'picture_generator', Picture\Generator::class )->addArgument( 'options' );
		$this->getContainer()->add( 'config', Config::class )
			->addArgument(
				new ArrayArgument(
					[
						'config_dir_path' => noobrocket_get_constant( 'NOOBROCKET_CONFIG_PATH' ),
					]
				)
			);
		$this->getContainer()->add( 'tests', Tests::class )
			->addArgument( 'config' );

		$this->getContainer()->add( 'lazyload_assets', Assets::class );
		$this->getContainer()->add( 'lazyload_image', Image::class );
		$this->getContainer()->add( 'lazyload_iframe', Iframe::class );
		$this->getContainer()->addShared( 'lazyload_subscriber', Subscriber::class )
			->addArguments(
				[
					'options',
					'lazyload_assets',
					'lazyload_image',
					'lazyload_iframe',
				]
			);
		$this->getContainer()->addShared( 'lazyload_admin_subscriber', LazyloadAdminSubscriber::class );
		$this->getContainer()->addShared( 'emojis_subscriber', EmojisSubscriber::class )
			->addArgument( 'options' );
		$this->getContainer()->add( 'image_dimensions', ImageDimensions::class )
			->addArgument( 'options' );
		$this->getContainer()->addShared( 'image_dimensions_subscriber', ImageDimensionsSubscriber::class )
			->addArguments(
				[
					'image_dimensions',
					'tests',
				]
			);
		$this->getContainer()->addShared( 'image_dimensions_admin_subscriber', ImageDimensionsAdminSubscriber::class )
			->addArgument( 'image_dimensions' );
	}
}
