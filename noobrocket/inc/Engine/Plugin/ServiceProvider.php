<?php
declare(strict_types=1);

namespace NoobRocket\Engine\Plugin;

use NoobRocket\Dependencies\League\Container\Argument\Literal\ArrayArgument;
use NoobRocket\Dependencies\League\Container\Argument\Literal\StringArgument;
use NoobRocket\Dependencies\League\Container\ServiceProvider\AbstractServiceProvider;
use NoobRocket\Engine\Plugin\Admin\NoticeSubscriber;

/**
 * Service provider for the NoobRocket updates.
 */
class ServiceProvider extends AbstractServiceProvider {
	/**
	 * Array of services provided by this service provider
	 *
	 * @var array
	 */
	protected $provides = [
		'plugin_renewal_notice',
		'plugin_updater_common_subscriber',
		'plugin_information_subscriber',
		'plugin_updater_subscriber',
		'plugin_notice_subscriber',
		'options_backup',
		'options_backup_subscriber',
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
		$this->getContainer()->add( 'plugin_renewal_notice', RenewalNotice::class )
			->addArguments(
				[
					'user',
					new StringArgument( $this->getContainer()->get( 'template_path' ) . '/plugins/' ),
				]
			);
		$this->getContainer()->addShared( 'plugin_updater_common_subscriber', UpdaterApiCommonSubscriber::class )
			->addArgument(
				new ArrayArgument(
					[
						'site_url'           => home_url(),
						'plugin_version'     => NOOBROCKET_VERSION,
						'settings_slug'      => NOOBROCKET_SLUG,
						'settings_nonce_key' => NOOBROCKET_PLUGIN_SLUG,
						'plugin_options'     => $this->getContainer()->get( 'options' ),
					]
				)
			);
		$this->getContainer()->addShared( 'plugin_information_subscriber', InformationSubscriber::class )
			->addArgument(
				new ArrayArgument(
					[
						'plugin_file' => NOOBROCKET_FILE,
					]
				)
			);
		$this->getContainer()->addShared( 'plugin_updater_subscriber', UpdaterSubscriber::class )
			->addArguments(
				[
					'plugin_renewal_notice',
					new ArrayArgument(
						[
							'plugin_file'    => NOOBROCKET_FILE,
							'plugin_version' => NOOBROCKET_VERSION,
							'vendor_url'     => NOOBROCKET_WEB_MAIN,
							'icons'          => [
								'2x' => NOOBROCKET_ASSETS_IMG_URL . 'icon-256x256.png',
								'1x' => NOOBROCKET_ASSETS_IMG_URL . 'icon-128x128.png',
							],
						]
					),
				]
			);
		$this->getContainer()->addShared( 'plugin_notice_subscriber', NoticeSubscriber::class );
		$this->getContainer()->addShared( 'options_backup', OptionsBackup::class )
			->addArguments(
				[
					new StringArgument( NOOBROCKET_CONFIG_PATH ),
					'options',
				]
			);
		$this->getContainer()->addShared( 'options_backup_subscriber', OptionsBackupSubscriber::class )
			->addArgument( 'options_backup' );
	}
}
