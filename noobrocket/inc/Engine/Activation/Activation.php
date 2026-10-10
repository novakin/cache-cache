<?php

namespace NoobRocket\Engine\Activation;

use NoobRocket\Admin\Options;
use NoobRocket\Engine\Container\IndexedDefinitionAggregate;
use NoobRocket\Dependencies\League\Container\Argument\Literal\StringArgument;
use NoobRocket\Dependencies\League\Container\Container;
use NoobRocket\Engine\Common\PerformanceHints\Activation\ServiceProvider as PerformanceHintsActivationServiceProvider;
use NoobRocket\Engine\License\ServiceProvider as LicenseServiceProvider;
use NoobRocket\Engine\Preload\Activation\ServiceProvider as PreloadActivationServiceProvider;
use NoobRocket\Logger\ServiceProvider as LoggerServiceProvider;
use NoobRocket\ServiceProvider\Options as OptionsServiceProvider;
use NoobRocket\ThirdParty\Hostings\HostResolver;
use NoobRocket\ThirdParty\Hostings\ServiceProvider as HostingsServiceProvider;
use NoobRocket\Event_Management\Event_Manager;

/**
 * Plugin activation controller
 *
 * @since 3.6.3
 */
class Activation {

	/**
	 * Aliases in the container for each class that needs to call its activate method
	 *
	 * @var array
	 */
	private static $activators = [
		'advanced_cache',
		'capabilities_manager',
		'wp_cache',
		'action_scheduler_check',
		'preload_activation',
	];

	/**
	 * Performs these actions during the plugin activation
	 *
	 * @return void
	 */
	public static function activate_plugin() {
		$container     = new Container( new IndexedDefinitionAggregate() );
		$event_manager = new Event_Manager();

		$container->add( 'template_path', new StringArgument( noobrocket_get_constant( 'NOOBROCKET_PATH', '' ) . 'views' ) );
		$options_api = new Options( 'noobrocket_' );
		$container->add( 'options_api', $options_api );
		$container->addServiceProvider( new OptionsServiceProvider() );
		$container->addServiceProvider( new PreloadActivationServiceProvider() );
		$container->addServiceProvider( new ServiceProvider() );
		$container->addServiceProvider( new HostingsServiceProvider() );
		$container->addServiceProvider( new LicenseServiceProvider() );
		$container->addServiceProvider( new LoggerServiceProvider() );
		$container->get( 'logger' );
		$container->addServiceProvider( new PerformanceHintsActivationServiceProvider() );

		$host_type = HostResolver::get_host_service();

		if ( ! empty( $host_type ) ) {
			array_unshift( self::$activators, $host_type );
		}

		foreach ( self::$activators as $activator ) {
			$container->get( $activator );
		}

		// Last constants.
		if ( ! defined( 'NOOBROCKET_PLUGIN_NAME' ) ) {
			define( 'NOOBROCKET_PLUGIN_NAME', 'Cache-Cache' );
		}
		if ( ! defined( 'NOOBROCKET_PLUGIN_SLUG' ) ) {
			define( 'NOOBROCKET_PLUGIN_SLUG', 'noobrocket' );
		}

		if ( defined( 'SUNRISE' ) && SUNRISE === 'on' && function_exists( 'domain_mapping_siteurl' ) ) {
			require_once NOOBROCKET_INC_PATH . 'domain-mapping.php';
		}

		// A site-active plugin may already be loaded when network activation runs.
		require_once NOOBROCKET_FUNCTIONS_PATH . 'options.php';
		require_once NOOBROCKET_FUNCTIONS_PATH . 'formatting.php';
		require_once NOOBROCKET_FUNCTIONS_PATH . 'i18n.php';
		require_once NOOBROCKET_FUNCTIONS_PATH . 'htaccess.php';
		require_once NOOBROCKET_FUNCTIONS_PATH . 'api.php';
		require_once NOOBROCKET_FUNCTIONS_PATH . 'admin.php';

		/**
		 * NoobRocket activation.
		 *
		 * @since  3.1.5
		 */
		do_action( 'noobrocket_activation' );

		// Flush once, after every activator has registered its rewrite rules.
		flush_rewrite_rules();

		if ( noobrocket_valid_key() ) {
			// Add All NoobRocket rules of the .htaccess file.
			flush_noobrocket_htaccess();
		}

		// Create the cache folders (noobrocket & min).
		noobrocket_init_cache_dir();

		// Create the config folder (noobrocket-config).
		noobrocket_init_config_dir();


		/**
		 * Fires after NoobRocket is activated
		 */
		do_action( 'noobrocket_after_activation' );
	}
}
