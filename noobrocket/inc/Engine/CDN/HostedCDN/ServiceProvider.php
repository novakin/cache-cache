<?php
declare(strict_types=1);

namespace NoobRocket\Engine\CDN\HostedCDN;

use NoobRocket\Dependencies\League\Container\Argument\Literal\StringArgument;
use NoobRocket\Dependencies\League\Container\ServiceProvider\AbstractServiceProvider;
use NoobRocket\Engine\CDN\HostedCDN\APIHandler\CheckStatusAPIClient;
use NoobRocket\Engine\CDN\HostedCDN\APIHandler\CreateAPIClient;
use NoobRocket\Engine\CDN\HostedCDN\APIHandler\WebsiteSearch;
use NoobRocket\Engine\CDN\HostedCDN\Database\Queries\HostedCDN as HostedCDNQuery;
use NoobRocket\Engine\CDN\HostedCDN\Database\Tables\HostedCDN as HostedCDNTable;

/**
 * Service provider for HostedCDN
 */
class ServiceProvider extends AbstractServiceProvider {
	/**
	 * Array of services provided by this service provider
	 *
	 * @var array
	 */
	protected $provides = [
		'noobrocketcdn_table',
		'noobrocketcdn_query',
		'noobrocketcdn_api_client',
		'noobrocketcdn_options_manager',
		'noobrocketcdn_data_manager_subscriber',
		'noobrocketcdn_frontend_subscriber',
		'noobrocketcdn_rest',
		'noobrocketcdn_rest_subscriber',
		'noobrocketcdn_admin_subscriber',
		'noobrocketcdn_notices_subscriber',
		'noobrocketcdn_subscription_controller',
		'noobrocketcdn_create_api_client',
		'noobrocketcdn_queue',
		'noobrocketcdn_check_status_api_client',
		'noobrocketcdn_website_search_api_client',
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
		// HostedCDN database layer.
		$this->getContainer()->addShared( 'noobrocketcdn_table', HostedCDNTable::class );
		$this->getContainer()->add( 'noobrocketcdn_query', HostedCDNQuery::class );

		// HostedCDN API Client.
		$this->getContainer()->add( 'noobrocketcdn_api_client', APIClient::class );
		// HostedCDN CDN options manager.
		$this->getContainer()->add( 'noobrocketcdn_options_manager', CDNOptionsManager::class )
			->addArguments(
				[
					'options_api',
					'options',
				]
			);
		// HostedCDN Data manager subscriber.
		$this->getContainer()->addShared( 'noobrocketcdn_data_manager_subscriber', DataManagerSubscriber::class )
			->addArguments(
				[
					'noobrocketcdn_api_client',
					'noobrocketcdn_options_manager',
					'options',
					'options_api',
					'user_client',
					'noobrocketcdn_subscription_controller',
					'noobrocketcdn_query',
				]
			);
		// HostedCDN Frontend subscriber.
		$this->getContainer()->addShared( 'noobrocketcdn_frontend_subscriber', FrontendSubscriber::class )
			->addArguments(
				[
					'cdn_context',
					'noobrocketcdn_subscription_controller',
				]
			);
		// HostedCDN REST API pages controller.
		$this->getContainer()->add( 'noobrocketcdn_rest', Rest::class )
			->addArguments(
				[
					'noobrocketcdn_query',
					'options',
					'options_api',
					'cdn_render_controller',
					'cdn_context',
					'noobrocketcdn_subscription_controller',
				]
			);
		// HostedCDN REST API Subscriber.
		$this->getContainer()->addShared( 'noobrocketcdn_rest_subscriber', RESTSubscriber::class )
			->addArguments(
				[
					'noobrocketcdn_options_manager',
					'options',
					'noobrocketcdn_rest',
					'noobrocketcdn_subscription_controller',
				]
			);
		// HostedCDN Notices Subscriber.
		$this->getContainer()->addShared( 'noobrocketcdn_notices_subscriber', NoticesSubscriber::class )
			->addArguments(
				[
					'noobrocketcdn_api_client',
					'beacon',
					'tracking',
					new StringArgument( __DIR__ . '/views' ),
					'options',
					'noobrocketcdn_subscription_controller',
					'user',
				]
			);

		$this->getContainer()->add( 'noobrocketcdn_queue', Queue::class );
		$this->getContainer()->add( 'noobrocketcdn_create_api_client', CreateAPIClient::class )->addArgument( 'user' );
		$this->getContainer()->add( 'noobrocketcdn_check_status_api_client', CheckStatusAPIClient::class );
		$this->getContainer()->add( 'noobrocketcdn_website_search_api_client', WebsiteSearch::class );
		$this->getContainer()->add( 'noobrocketcdn_subscription_controller', SubscriptionController::class )
			->addArguments(
				[
					'noobrocketcdn_api_client',
					'noobrocketcdn_create_api_client',
					'noobrocketcdn_options_manager',
					'noobrocketcdn_queue',
					'noobrocketcdn_check_status_api_client',
					'user',
					'noobrocketcdn_website_search_api_client',
					'user_client',
				]
				);

		// HostedCDN settings page subscriber.
		$this->getContainer()->addShared( 'noobrocketcdn_admin_subscriber', AdminPageSubscriber::class )
			->addArguments(
				[
					'noobrocketcdn_api_client',
					'user_client',
					'user',
					new StringArgument( __DIR__ . '/views' ),
				]
			);

		// Ensure the table is created and refreshed when needed.
		$this->getContainer()->get( 'noobrocketcdn_table' );
	}
}
