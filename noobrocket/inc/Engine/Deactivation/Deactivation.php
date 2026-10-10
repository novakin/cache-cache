<?php

namespace NoobRocket\Engine\Deactivation;

use NoobRocket\Admin\Options;
use NoobRocket\Engine\Container\IndexedDefinitionAggregate;
use NoobRocket\Dependencies\League\Container\Argument\Literal\StringArgument;
use NoobRocket\Dependencies\League\Container\Container;
use NoobRocket\Engine\Admin\Beacon\ServiceProvider as BeaconServiceProvider;
use NoobRocket\Engine\Support\ServiceProvider as SupportServiceProvider;
use NoobRocket\ServiceProvider\Options as OptionsServiceProvider;
use NoobRocket\ThirdParty\Hostings\HostResolver;
use NoobRocket\ThirdParty\Hostings\ServiceProvider as HostingsServiceProvider;

class Deactivation {

	/**
	 * Aliases in the container for each class that needs to call its deactivate method
	 *
	 * @var array
	 */
	private static $deactivators = [
		'advanced_cache',
		'capabilities_manager',
		'wp_cache',
		'cloudflare_plugin_subscriber',
	];

	/**
	 * Performs these actions during the plugin deactivation
	 *
	 * @param bool $network_wide Whether WordPress is deactivating the network plugin.
	 * @return void
	 */
	public static function deactivate_plugin( $network_wide = false ) {
		global $is_apache;

		$container = new Container( new IndexedDefinitionAggregate() );

		$container->add( 'options_api', new Options( 'noobrocket_' ) );
		$container->add( 'template_path', new StringArgument( noobrocket_get_constant( 'NOOBROCKET_PATH', '' ) . 'views' ) );

		$container->addServiceProvider( new OptionsServiceProvider() );
		$container->addServiceProvider( new BeaconServiceProvider() );
		$container->addServiceProvider( new SupportServiceProvider() );
		$container->addServiceProvider( new ServiceProvider() );
		$container->addServiceProvider( new HostingsServiceProvider() );

		$host_type = HostResolver::get_host_service();

		if ( ! empty( $host_type ) ) {
			array_unshift( self::$deactivators, $host_type );
		}

		foreach ( self::$deactivators as $deactivator ) {
			$container->get( $deactivator );
		}

		if ( ! isset( $_GET['noobrocket_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['noobrocket_nonce'] ), 'force_deactivation' ) ) {
			$causes = [];

			// .htaccess problem.
			if ( $is_apache && ! noobrocket_direct_filesystem()->is_writable( get_home_path() . '.htaccess' ) ) {
				$causes[] = 'htaccess';
			}

			/**
			 * Filters the causes which can prevent the deactivation of the plugin
			 *
			 * @since 3.6.3
			 *
			 * @param array $causes An array of causes to pass to the notice.
			 */
			$causes = (array) apply_filters( 'noobrocket_prevent_deactivation', $causes );

			if ( count( $causes ) ) {
				set_transient( get_current_user_id() . '_donotdeactivatenoobrocket', $causes );
				wp_safe_redirect( wp_get_referer() );
				die();
			}
		}

		// Remove early policy and local state only where NoobRocket is losing activation.
		self::for_each_deactivated_site(
			static function () {
				noobrocket_delete_config_file();
				delete_transient( 'noobrocket_check_licence_30' );
				delete_transient( 'noobrocket_check_licence_1' );
				delete_transient( 'noobrocket_rucss_as_tables_count' );
				wp_clear_scheduled_hook( 'noobrocket_cache_dir_size_check' );
			},
			(bool) $network_wide
		);

		$sites_number = count( _noobrocket_get_php_files_in_dir( noobrocket_get_constant( 'NOOBROCKET_CONFIG_PATH' ) ) );

		if ( ! $sites_number ) {
			// Delete All NoobRocket rules of the .htaccess file.
			flush_noobrocket_htaccess( true );
		}


		// These update records have network scope.
		delete_site_transient( 'update_noobrocket_response' );
		delete_site_transient( 'noobrocket_update_data' );

		// Delete user metadata.
		noobrocket_renew_box( 'preload_notice' );

		/**
		 * NoobRocket deactivation.
		 *
		 * @since 3.6.3 add $sites_count parameter.
		 * @since  3.1.5
		 *
		 * @param int $sites_number Number of NoobRocket config files found.
		 * @param bool $network_wide Whether this deactivation affects the current network.
		 */
		do_action( 'noobrocket_deactivation', $sites_number, (bool) $network_wide );

		// Flush once, after every deactivator has had a chance to unregister
		// its rewrite rules.
		flush_rewrite_rules();
	}

	/** Run site-local cleanup in the affected network, preserving independently active blogs. */
	public static function for_each_deactivated_site( callable $callback, bool $network_wide = false ): void {
		if ( ! is_multisite() || ! $network_wide ) {
			$callback();
			return;
		}
		$plugin = plugin_basename( noobrocket_get_constant( 'NOOBROCKET_FILE' ) );
		foreach ( get_sites( [ 'fields' => 'ids', 'number' => 0, 'network_id' => get_current_network_id() ] ) as $site_id ) {
			switch_to_blog( $site_id );
			try {
				if ( ! in_array( $plugin, (array) get_option( 'active_plugins', [] ), true ) ) {
					$callback();
				}
			} finally {
				restore_current_blog();
			}
		}
	}
}
