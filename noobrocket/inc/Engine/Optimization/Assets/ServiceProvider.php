<?php
namespace NoobRocket\Engine\Optimization\Assets;

use NoobRocket\Dependencies\League\Container\ServiceProvider\AbstractServiceProvider;

class ServiceProvider extends AbstractServiceProvider {

	protected $provides = [ 'asset_rules', 'settings_snapshots', 'asset_frontend_subscriber', 'asset_admin_subscriber' ];

	public function provides( string $id ): bool {
		return in_array( $id, $this->provides, true );
	}

	public function register(): void {
		$this->getContainer()->addShared( 'asset_rules', Rules::class );
		$this->getContainer()->addShared( 'settings_snapshots', Snapshots::class )->addArgument( 'asset_rules' );
		$this->getContainer()->addShared( 'asset_frontend_subscriber', Frontend::class )->addArgument( 'asset_rules' );
		$this->getContainer()->addShared( 'asset_admin_subscriber', Admin::class )->addArguments( [ 'asset_rules', 'settings_snapshots' ] );
	}
}
