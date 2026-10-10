<?php
declare(strict_types=1);

namespace NoobRocket\Engine\Common\JobManager;

use NoobRocket\Dependencies\League\Container\ServiceProvider\AbstractServiceProvider;
use NoobRocket\Engine\Common\Clock\CacheClock;
use NoobRocket\Engine\Common\JobManager\Cron\Subscriber as CronSubscriber;
use NoobRocket\Engine\Common\JobManager\Queue\Queue;
use NoobRocket\Engine\Common\JobManager\Strategy\Context\RetryContext;
use NoobRocket\Engine\Common\JobManager\Strategy\Factory\StrategyFactory;

class ServiceProvider extends AbstractServiceProvider {
	/**
	 * The provides array is a way to let the container
	 * know that a service is provided by this service
	 * provider. Every service that is registered via
	 * this service provider must have an alias added
	 * to this array or it will be ignored.
	 *
	 * @var array
	 */
	protected $provides = [
		'nbr_clock',
		'retry_strategy_factory',
		'retry_strategy_context',
		'job_processor',
		'queue',
		'api_client',
		'cron_subscriber',
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
	 * Registers the classes in the container
	 *
	 * @return void
	 */
	public function register(): void {
		$factories = [
			'rucss'           => $this->getContainer()->get( 'rucss_factory' ),
			'noobrocket_insights' => $this->getContainer()->get( 'ri_factory' ),
		];

		$this->getContainer()->add( 'nbr_clock', CacheClock::class );
		$this->getContainer()->add( 'retry_strategy_context', RetryContext::class );
		$this->getContainer()->add( 'retry_strategy_factory', StrategyFactory::class )
			->addArgument( 'nbr_clock' );
		$this->getContainer()->add( 'queue', Queue::class );

		$this->getContainer()->addShared( 'job_processor', JobProcessor::class )
			->addArguments(
				[
					$factories,
					'queue',
					'retry_strategy_factory',
					'nbr_clock',
				]
			);
		$this->getContainer()->addShared( 'cron_subscriber', CronSubscriber::class )
			->addArguments(
				[
					'job_processor',
					$factories,
				]
			);
	}
}
