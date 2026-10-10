<?php
declare(strict_types=1);

namespace NoobRocket\Engine\Admin;

use NoobRocket\Dependencies\League\Container\ServiceProvider\AbstractServiceProvider;
use NoobRocket\Dependencies\League\Container\Argument\Literal\StringArgument;
use NoobRocket\Engine\Admin\Deactivation\{DeactivationIntent, Subscriber};
use NoobRocket\Engine\Admin\Metaboxes\PostEditOptionsSubscriber;
use NoobRocket\Engine\Cache\ObjectCache\Diagnostics as ObjectCacheDiagnostics;
use NoobRocket\ThirdParty\Plugins\Optimization\Hummingbird;

/**
 * Service Provider for admin subscribers.
 */
class ServiceProvider extends AbstractServiceProvider {
	/**
	 * Array of services provided by this service provider
	 *
	 * @var array
	 */
	protected $provides = [
		'deactivation_intent',
		'deactivation_intent_subscriber',
		'hummingbird_subscriber',
		'actionscheduler_admin_subscriber',
		'post_edit_options_subscriber',
		'pagespeed_subscriber',
		'object_cache_diagnostics',
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
		$this->getContainer()->addShared( 'pagespeed_subscriber', PageSpeed::class );
		$this->getContainer()->addShared( 'object_cache_diagnostics', ObjectCacheDiagnostics::class );
		$this->getContainer()->add( 'deactivation_intent', DeactivationIntent::class )
			->addArguments(
				[
					new StringArgument( $this->getContainer()->get( 'template_path' ) . '/deactivation-intent' ),
					'options_api',
					'options',
				]
			);
		$this->getContainer()->addShared( 'deactivation_intent_subscriber', Subscriber::class )
			->addArgument( $this->getContainer()->get( 'deactivation_intent' ) );
		$this->getContainer()->addShared( 'hummingbird_subscriber', Hummingbird::class )
			->addArgument( 'options' );
		$this->getContainer()->addShared( 'actionscheduler_admin_subscriber', ActionSchedulerSubscriber::class );
		$this->getContainer()->addShared( 'post_edit_options_subscriber', PostEditOptionsSubscriber::class )
			->addArguments(
				[
					'options',
					new StringArgument( $this->getContainer()->get( 'template_path' ) . '/metaboxes' ),
				]
			);
	}
}
