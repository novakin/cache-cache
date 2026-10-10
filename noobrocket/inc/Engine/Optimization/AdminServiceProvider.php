<?php
declare(strict_types=1);

namespace NoobRocket\Engine\Optimization;

use NoobRocket\Dependencies\League\Container\ServiceProvider\AbstractServiceProvider;
use NoobRocket\Engine\Optimization\GoogleFonts\Admin\{Settings, Subscriber};
use NoobRocket\Engine\Optimization\Minify\AdminSubscriber as MinifyAdminSubscriber;
use NoobRocket\Engine\Optimization\Minify\CSS\AdminSubscriber as MinifyCssAdminSubscriber;

/**
 * Service provider for the NoobRocket optimizations
 */
class AdminServiceProvider extends AbstractServiceProvider {
	/**
	 * Array of services provided by this service provider
	 *
	 * @var array
	 */
	protected $provides = [
		'minify_css_admin_subscriber',
		'google_fonts_settings',
		'google_fonts_admin_subscriber',
		'minify_admin_subscriber',
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
		$this->getContainer()->addShared( 'minify_css_admin_subscriber', MinifyCssAdminSubscriber::class );
		$this->getContainer()->add( 'google_fonts_settings', Settings::class )
			->addArgument( 'options' )
			->addArgument( 'beacon' )
			->addArgument( 'template_path' );
		$this->getContainer()->addShared( 'google_fonts_admin_subscriber', Subscriber::class )
			->addArgument( 'google_fonts_settings' );
		$this->getContainer()->addShared( 'minify_admin_subscriber', MinifyAdminSubscriber::class )
			->addArgument( 'options' );
	}
}
