<?php
declare(strict_types=1);

namespace NoobRocket;

use Imagify_Partner;
use NoobRocket\Dependencies\League\Container\Container;
use NoobRocket\Admin\{Options, Options_Data};
use NoobRocket\Engine\Admin\API\ServiceProvider as APIServiceProvider;
use NoobRocket\Engine\Common\ExtractCSS\ServiceProvider as CommonExtractCSSServiceProvider;
use NoobRocket\Engine\Common\Head\ServiceProvider as CommonHeadServiceProvider;
use NoobRocket\Engine\Common\JobManager\ServiceProvider as JobManagerServiceProvider;
use NoobRocket\Engine\Media\Lazyload\CSS\ServiceProvider as LazyloadCSSServiceProvider;
use NoobRocket\Engine\Media\Lazyload\CSS\Admin\ServiceProvider as AdminLazyloadCSSServiceProvider;
use NoobRocket\Event_Management\Event_Manager;
use NoobRocket\Logger\ServiceProvider as LoggerServiceProvider;
use NoobRocket\ThirdParty\Hostings\HostResolver;
use NoobRocket\ThirdParty\Plugins\PluginResolver;
use NoobRocket\Addon\ServiceProvider as AddonServiceProvider;
use NoobRocket\Addon\Cloudflare\ServiceProvider as CloudflareServiceProvider;
use NoobRocket\Addon\Varnish\ServiceProvider as VarnishServiceProvider;
use NoobRocket\Dependencies\League\Container\Argument\Literal\StringArgument;
use NoobRocket\Engine\Admin\Beacon\ServiceProvider as BeaconServiceProvider;
use NoobRocket\Engine\Admin\Database\ServiceProvider as AdminDatabaseServiceProvider;
use NoobRocket\Engine\Admin\ServiceProvider as EngineAdminServiceProvider;
use NoobRocket\Engine\Admin\Settings\ServiceProvider as SettingsServiceProvider;
use NoobRocket\Engine\Cache\ServiceProvider as CacheServiceProvider;
use NoobRocket\Engine\Capabilities\ServiceProvider as CapabilitiesServiceProvider;
use NoobRocket\Engine\CDN\HostedCDN\ServiceProvider as HostedCDNServiceProvider;
use NoobRocket\Engine\CDN\ServiceProvider as CDNServiceProvider;
use NoobRocket\Engine\CriticalPath\ServiceProvider as CriticalPathServiceProvider;
use NoobRocket\Engine\HealthCheck\ServiceProvider as HealthCheckServiceProvider;
use NoobRocket\Engine\Heartbeat\ServiceProvider as HeartbeatServiceProvider;
use NoobRocket\Engine\License\ServiceProvider as LicenseServiceProvider;
use NoobRocket\Engine\Media\ServiceProvider as MediaServiceProvider;
use NoobRocket\Engine\Media\AboveTheFold\ServiceProvider as ATFServiceProvider;
use NoobRocket\Engine\Optimization\AdminServiceProvider as OptimizationAdminServiceProvider;
use NoobRocket\Engine\Optimization\DeferJS\ServiceProvider as DeferJSServiceProvider;
use NoobRocket\Engine\Optimization\DelayJS\ServiceProvider as DelayJSServiceProvider;
use NoobRocket\Engine\Optimization\DynamicLists\ServiceProvider as DynamicListsServiceProvider;
use NoobRocket\Engine\Optimization\RUCSS\ServiceProvider as RUCSSServiceProvider;
use NoobRocket\Engine\Optimization\LocalCSS\ServiceProvider as LocalCSSServiceProvider;
use NoobRocket\Engine\Optimization\ServiceProvider as OptimizationServiceProvider;
use NoobRocket\Engine\Plugin\ServiceProvider as PluginServiceProvider;
use NoobRocket\Engine\Preload\Links\ServiceProvider as PreloadLinksServiceProvider;
use NoobRocket\Engine\Preload\ServiceProvider as PreloadServiceProvider;
use NoobRocket\Engine\Support\ServiceProvider as SupportServiceProvider;
use NoobRocket\ServiceProvider\Common_Subscribers;
use NoobRocket\ServiceProvider\Options as OptionsServiceProvider;
use NoobRocket\ThirdParty\Hostings\ServiceProvider as HostingsServiceProvider;
use NoobRocket\ThirdParty\ServiceProvider as ThirdPartyServiceProvider;
use NoobRocket\ThirdParty\Themes\ServiceProvider as ThemesServiceProvider;
use NoobRocket\Engine\Admin\DomainChange\ServiceProvider as DomainChangeServiceProvider;
use NoobRocket\ThirdParty\Themes\ThemeResolver;
use NoobRocket\Engine\Debug\Resolver as DebugResolver;
use NoobRocket\Engine\Debug\ServiceProvider as DebugServiceProvider;
use NoobRocket\Engine\Common\PerformanceHints\ServiceProvider as PerformanceHintsServiceProvider;
use NoobRocket\Engine\Optimization\LazyRenderContent\ServiceProvider as LRCServiceProvider;
use NoobRocket\Engine\Media\Fonts\ServiceProvider as MediaFontsServiceProvider;
use NoobRocket\Engine\Media\PreloadFonts\ServiceProvider as PreloadFontsServiceProvider;
use NoobRocket\Engine\Media\PreconnectExternalDomains\ServiceProvider as PreconnectExternalDomainsServiceProvider;
use NoobRocket\Engine\Tracking\ServiceProvider as TrackingServiceProvider;
use NoobRocket\Engine\Admin\PerformanceMonitoring\ServiceProvider as PerformanceMonitoringServiceProvider;
use NoobRocket\Engine\Abilities\ServiceProvider as AbilitiesServiceProvider;
use NoobRocket\Engine\Optimization\Assets\ServiceProvider as AssetsServiceProvider;
use NoobRocket\Engine\Optimization\Bloat\ServiceProvider as BloatServiceProvider;

/**
 * Plugin Manager.
 */
class Plugin {

	/**
	 * Instance of Container class.
	 *
	 * @since 3.3
	 *
	 * @var Container instance
	 */
	private $container;

	/**
	 * Instance of the event manager.
	 *
	 * @since 3.6
	 *
	 * @var Event_Manager
	 */
	private $event_manager;

	/**
	 * Flag for if the license key is valid.
	 *
	 * @since 3.6
	 *
	 * @var bool
	 */
	private $is_valid_key;

	/**
	 * Instance of the Options_Data.
	 *
	 * @since 3.6
	 *
	 * @var Options_Data
	 */
	private $options;

	/**
	 * Creates an instance of the Plugin.
	 *
	 * @since 3.0
	 *
	 * @param string    $template_path Path to the views.
	 * @param Container $container     Instance of the container.
	 */
	public function __construct( $template_path, Container $container ) {
		$this->container = $container;

		add_filter( 'noobrocket_container', [ $this, 'get_container' ] );

		$this->container->add( 'template_path', new StringArgument( $template_path ) );
	}

	/**
	 * Returns the Rocket container instance.
	 *
	 * @return Container
	 */
	public function get_container() {
		return $this->container;
	}

	/**
	 * Loads the plugin into WordPress.
	 *
	 * @since 3.0
	 *
	 * @return void
	 */
	public function load() {
		$this->event_manager = new Event_Manager();
		$this->container->addShared( 'event_manager', $this->event_manager );

		$this->container->add( 'options_api', Options::class )
			->addArgument( new StringArgument( 'noobrocket_' ) );
		$this->container->addServiceProvider( new OptionsServiceProvider() );
		$this->options = $this->container->get( 'options' );

		$this->container->add( 'debug_resolver', DebugResolver::class )
			->addArgument( $this->options );

		$this->container->addServiceProvider( new LoggerServiceProvider() );

		$this->container->get( 'logger' );

		$this->container->addServiceProvider( new AdminDatabaseServiceProvider() );
		$this->container->addServiceProvider( new SupportServiceProvider() );
		$this->container->addServiceProvider( new BeaconServiceProvider() );
		$this->container->addServiceProvider( new HostedCDNServiceProvider() );
		$this->container->addServiceProvider( new CacheServiceProvider() );
		$this->container->addServiceProvider( new CriticalPathServiceProvider() );
		$this->container->addServiceProvider( new HealthCheckServiceProvider() );
		$this->container->addServiceProvider( new MediaServiceProvider() );
		$this->container->addServiceProvider( new DeferJSServiceProvider() );

		$this->is_valid_key = noobrocket_valid_key();

		foreach ( $this->get_subscribers() as $subscriber ) {
			$this->event_manager->add_subscriber( $this->container->get( $subscriber ) );
		}
	}

	/**
	 * Get the subscribers to add to the event manager.
	 *
	 * @since 3.6
	 *
	 * @return array array of subscribers.
	 */
	private function get_subscribers() {
		$subscribers = [];

		if ( is_admin() ) {
			$subscribers = $this->init_admin_subscribers();
		} elseif ( $this->is_valid_key ) {
			$subscribers = $this->init_valid_key_subscribers();
		}

		return array_merge( $subscribers, $this->init_common_subscribers() );
	}

	/**
	 * Initializes the admin subscribers.
	 *
	 * @since 3.6
	 *
	 * @return array array of subscribers.
	 */
	private function init_admin_subscribers() {

		$this->container->addServiceProvider( new SettingsServiceProvider() );
		$this->container->addServiceProvider( new EngineAdminServiceProvider() );
		$this->container->addServiceProvider( new OptimizationAdminServiceProvider() );
		$this->container->addServiceProvider( new DomainChangeServiceProvider() );
		$this->container->addServiceProvider( new AdminLazyloadCSSServiceProvider() );
		$this->container->addServiceProvider( new PerformanceMonitoringServiceProvider() );

		$subscribers = [
			'settings_page_subscriber',
			'pagespeed_subscriber',
			'object_cache_diagnostics',
			'hummingbird_subscriber',
			'health_check',
			'minify_css_admin_subscriber',
			'admin_cache_subscriber',
			'google_fonts_admin_subscriber',
			'image_dimensions_admin_subscriber',
			'defer_js_admin_subscriber',
			'lazyload_admin_subscriber',
			'preload_admin_subscriber',
			'minify_admin_subscriber',
			'action_scheduler_check',
			'actionscheduler_admin_subscriber',
			'domain_change_subscriber',
			'lazyload_css_admin_subscriber',
			'post_edit_options_subscriber',
			'preconnect_external_domains_admin_subscriber',
			'media_fonts_admin_subscriber',
			'preload_fonts_admin_subscriber',
		];

		return $subscribers;
	}

	/**
	 * For plugins with a valid key, initialize the subscribers.
	 *
	 * @since 3.6
	 *
	 * @return array array of subscribers.
	 */
	private function init_valid_key_subscribers() {
		$this->container->addServiceProvider( new OptimizationServiceProvider() );

		$subscribers = [
			'buffer_subscriber',
			'ie_conditionals_subscriber',
			'combine_google_fonts_subscriber',
			'minify_css_subscriber',
			'minify_js_subscriber',
			'cache_dynamic_resource',
			'emojis_subscriber',
			'delay_js_subscriber',
			'image_dimensions_subscriber',
			'defer_js_subscriber',
		];

		// Don't insert the LazyLoad file if Rocket LazyLoad is activated.
		if ( ! noobrocket_is_plugin_active( 'rocket-lazy-load/rocket-lazy-load.php' ) ) {
			$subscribers[] = 'lazyload_subscriber';
		}

		return $subscribers;
	}

	/**
	 * Initializes the common subscribers.
	 *
	 * @since 3.6
	 *
	 * @return array array of common subscribers.
	 */
	private function init_common_subscribers() {
		$this->container->addServiceProvider( new CapabilitiesServiceProvider() );
		$this->container->addServiceProvider( new AddonServiceProvider() );
		$this->container->addServiceProvider( new VarnishServiceProvider() );
		$this->container->addServiceProvider( new PreloadServiceProvider() );
		$this->container->addServiceProvider( new PreloadLinksServiceProvider() );
		$this->container->addServiceProvider( new CDNServiceProvider() );
		$this->container->addServiceProvider( new Common_Subscribers() );
		$this->container->addServiceProvider( new HostingsServiceProvider() );
		$this->container->addServiceProvider( new PluginServiceProvider() );
		$this->container->addServiceProvider( new DynamicListsServiceProvider() );
		$this->container->addServiceProvider( new DelayJSServiceProvider() );
		$this->container->addServiceProvider( new RUCSSServiceProvider() );
		$this->container->addServiceProvider( new LocalCSSServiceProvider() );
		$this->container->addServiceProvider( new AssetsServiceProvider() );
		$this->container->addServiceProvider( new BloatServiceProvider() );
		$this->container->addServiceProvider( new HeartbeatServiceProvider() );
		$this->container->addServiceProvider( new LicenseServiceProvider() );
		$this->container->addServiceProvider( new ThemesServiceProvider() );
		$this->container->addServiceProvider( new APIServiceProvider() );
		$this->container->addServiceProvider( new CommonExtractCSSServiceProvider() );
		$this->container->addServiceProvider( new CommonHeadServiceProvider() );
		$this->container->addServiceProvider( new LazyloadCSSServiceProvider() );
		$this->container->addServiceProvider( new DebugServiceProvider() );
		$this->container->addServiceProvider( new ATFServiceProvider() );
		$this->container->addServiceProvider( new JobManagerServiceProvider() );
		$this->container->addServiceProvider( new PerformanceHintsServiceProvider() );
		$this->container->addServiceProvider( new LRCServiceProvider() );
		$this->container->addServiceProvider( new MediaFontsServiceProvider() );
		$this->container->addServiceProvider( new PreloadFontsServiceProvider() );
		$this->container->addServiceProvider( new ThirdPartyServiceProvider() );
		$this->container->addServiceProvider( new PreconnectExternalDomainsServiceProvider() );
		$this->container->addServiceProvider( new PerformanceMonitoringServiceProvider() );
		$this->container->addServiceProvider( new TrackingServiceProvider() );

		$common_subscribers = [
			'picture_subscriber',
			'picture_generator',
			'asset_frontend_subscriber',
			'asset_admin_subscriber',
			'settings_snapshots',
			'bloat_subscriber',
			'local_css_subscriber',
			'local_css_diagnostics',
			'cdn_subscriber',
			'cdn_admin_subscriber',
			'cdn_state_bridge',
			'sucuri_subscriber',
			'common_extractcss_subscriber',
			'common_head_subscriber',
			'expired_cache_purge_subscriber',
			'heartbeat_subscriber',
			'db_optimization_subscriber',
			'options_backup_subscriber',
			'capabilities_subscriber',
			'varnish_subscriber',
			'detect_missing_tags_subscriber',
			'purge_actions_subscriber',
			'user_cache_key_subscriber',
			'delay_js_admin_subscriber',
			'preload_subscriber',
			'preload_front_subscriber',
			'preload_links_admin_subscriber',
			'preload_links_subscriber',
			'preload_cron_subscriber',
			'mod_pagespeed',
			'webp_subscriber',
			'webp_admin_subscriber',
			'ezoic',
			'dynamic_lists_subscriber',
			'cache_config',
			'admin_api_subscriber',
			'lazyload_css_subscriber',
			'cron_subscriber',
			'debug_subscriber',
			'atf_subscriber',
			'performance_hints_ajax_subscriber',
			'performance_hints_frontend_subscriber',
			'performance_hints_cron_subscriber',
			'performance_hints_admin_subscriber',
			'lrc_frontend_subscriber',
			'taxonomy_subscriber',
			'media_fonts_frontend_subscriber',
			'media_fonts_admin_subscriber',
			'media_fonts_clean_subscriber',
			'preload_fonts_frontend_subscriber',
			'preload_fonts_admin_subscriber',
			'preconnect_frontend_subscriber',
			'post_subscriber',
			'logger_subscriber',
		];

		$host_type = HostResolver::get_host_service();
		$theme     = ThemeResolver::get_current_theme();

		if ( ! empty( $host_type ) ) {
			$common_subscribers[] = $host_type;
		}

		if ( ! empty( $theme ) ) {
			$common_subscribers[] = $theme;
		}

		$common_subscribers = array_merge( $common_subscribers, PluginResolver::get_active_plugins() );

		if ( $this->options->get( 'do_cloudflare', false ) ) {
			$this->container->addServiceProvider( new CloudflareServiceProvider() );

			$common_subscribers[] = 'cloudflare_admin_subscriber';
			$common_subscribers[] = 'cloudflare_subscriber';
		}

		$services = $this->container->get( 'debug_resolver' )->get_services();

		if ( ! empty( $services ) ) {
			foreach ( $services as $service ) {
				$common_subscribers[] = $service['service'];
			}
		}

		return array_merge( $common_subscribers, $this->init_abilities_subscribers() );
	}

	/**
	 * Registers ability service providers and returns the list of ability subscriber service IDs.
	 *
	 * @return string[]
	 */
	private function init_abilities_subscribers(): array {
		$this->container->addServiceProvider( new AbilitiesServiceProvider() );

		$subscribers = [
			'abilities_subscriber',
			'cache_abilities_subscriber',
			'preload_abilities_subscriber',
			'abilities_cli_subscriber',
		];

		return $subscribers;
	}
}
