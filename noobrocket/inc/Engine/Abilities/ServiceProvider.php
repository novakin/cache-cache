<?php
declare(strict_types=1);

namespace NoobRocket\Engine\Abilities;

use NoobRocket\Dependencies\League\Container\ServiceProvider\AbstractServiceProvider;
use NoobRocket\Engine\Abilities\Catalog;
use NoobRocket\Engine\Abilities\CLI\Command as CLICommand;
use NoobRocket\Engine\Abilities\CLI\Subscriber as CLISubscriber;
use NoobRocket\Engine\Abilities\Context;
use NoobRocket\Engine\Abilities\Options\AllowedOptions;
use NoobRocket\Engine\Abilities\Options\GetOptions;
use NoobRocket\Engine\Abilities\Options\SetOption;
use NoobRocket\Engine\Abilities\Options\Subscriber;

class ServiceProvider extends AbstractServiceProvider {
	/**
	 * Array of services provided by this service provider
	 *
	 * @var array
	 */
	protected $provides = [
		'abilities_context',
		'abilities_allowed_options',
		'abilities_get_options',
		'abilities_set_option',
		'abilities_subscriber',
		'abilities_catalog',
		'abilities_cli_command',
		'abilities_cli_subscriber',
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
	 * Register the services provided by this service provider.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->getContainer()->addShared( 'abilities_context', Context::class );
		$this->getContainer()->addShared( 'abilities_allowed_options', AllowedOptions::class );
		$this->getContainer()->add( 'abilities_get_options', GetOptions::class )
			->addArguments( [ 'options', 'abilities_allowed_options' ] );
		$this->getContainer()->add( 'abilities_set_option', SetOption::class )
			->addArgument( 'abilities_allowed_options' );
		$this->getContainer()->addShared( 'abilities_subscriber', Subscriber::class )
			->addArguments(
				[
					'abilities_get_options',
					'abilities_set_option',
					'abilities_context',
				]
			);
		$this->getContainer()->addShared( 'abilities_catalog', Catalog::class );
		$this->getContainer()->add( 'abilities_cli_command', CLICommand::class )
			->addArgument( 'abilities_catalog' );
		$this->getContainer()->addShared( 'abilities_cli_subscriber', CLISubscriber::class )
			->addArgument( 'abilities_cli_command' );
	}
}
