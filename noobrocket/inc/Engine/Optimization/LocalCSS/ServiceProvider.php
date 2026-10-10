<?php
namespace NoobRocket\Engine\Optimization\LocalCSS;

use NoobRocket\Dependencies\League\Container\ServiceProvider\AbstractServiceProvider;

class ServiceProvider extends AbstractServiceProvider {
	/**
	 * Services registered by this provider.
	 *
	 * @var string[]
	 */
	protected $provides = [
		'local_css_jobs',
		'local_css_frontend',
		'local_css_subscriber',
		'local_css_diagnostics'
	];

	public function provides( string $id ): bool {
		return in_array( $id, $this->provides, true );
	}

	public function register(): void {
		$this->getContainer()
			->addShared( 'local_css_jobs', Jobs::class )
			->addArgument( 'rucss_usedcss_table' );

		$this->getContainer()
			->addShared( 'local_css_frontend', Frontend::class )
			->addArguments( [ 'local_css_jobs', 'media_fonts_frontend_controller' ] );

		$this->getContainer()
			->addShared( 'local_css_subscriber', Subscriber::class )
			->addArguments( [ 'local_css_jobs', 'local_css_frontend' ] );

		$this->getContainer()
			->addShared( 'local_css_diagnostics', Diagnostics::class )
			->addArguments(
				[
					'local_css_jobs',
					'preload_caches_query',
					'load_initial_sitemap_controller',
					'preload_queue'
				]
			);
	}
}
