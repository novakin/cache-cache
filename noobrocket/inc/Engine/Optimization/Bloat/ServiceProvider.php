<?php
declare(strict_types=1);

namespace NoobRocket\Engine\Optimization\Bloat;

use NoobRocket\Dependencies\League\Container\ServiceProvider\AbstractServiceProvider;

class ServiceProvider extends AbstractServiceProvider {
	protected $provides = [ 'bloat_subscriber' ];

	public function provides( string $id ): bool {
		return in_array( $id, $this->provides, true );
	}

	public function register(): void {
		$this->getContainer()->addShared( 'bloat_subscriber', Subscriber::class )->addArgument( 'options' );
	}
}
