<?php

namespace NoobRocket\Engine\Capabilities;

use NoobRocket\Engine\Activation\ActivationInterface;
use NoobRocket\Engine\Deactivation\DeactivationInterface;
use WP_Role;

class Manager implements ActivationInterface, DeactivationInterface {
	/**
	 * List of NoobRocket capabilities
	 *
	 * @var array
	 */
	private $capabilities = [
		'noobrocket_manage_options',
		'noobrocket_purge_cache',
		'noobrocket_purge_posts',
		'noobrocket_purge_terms',
		'noobrocket_purge_users',
		'noobrocket_purge_cloudflare_cache',
		'noobrocket_purge_sucuri_cache',
		'noobrocket_preload_cache',
		'noobrocket_regenerate_critical_css',
		'noobrocket_remove_unused_css',
	];

	/**
	 * Gets the NoobRocket capabilities
	 *
	 * @since 3.4
	 *
	 * @return array
	 */
	private function get_capabilities() {
		return $this->capabilities;
	}

	/**
	 * Performs these actions during the plugin activation
	 *
	 * @return void
	 */
	public function activate() {
		add_action( 'noobrocket_activation', [ $this, 'add_noobrocket_capabilities' ] );
	}

	/**
	 * Performs these actions during the plugin deactivation
	 *
	 * @return void
	 */
	public function deactivate() {
		add_action( 'noobrocket_deactivation', [ $this, 'remove_noobrocket_capabilities' ], 10, 2 );
	}

	/**
	 * Add NoobRocket capabilities to the administrator role
	 *
	 * @since 3.4
	 *
	 * @return void
	 */
	public function add_noobrocket_capabilities() {
		$role = $this->get_administrator_role_object();

		if ( is_null( $role ) ) {
			return;
		}

		foreach ( $this->get_capabilities() as $cap ) {
			$role->add_cap( $cap );
		}
	}

	/**
	 * Remove NoobRocket capabilities from the administrator role
	 *
	 * @since 3.4
	 *
	 * @param int  $sites_number Number of sites in the network.
	 * @param bool $network_wide Whether the plugin is deactivated network-wide.
	 * @return void
	 */
	public function remove_noobrocket_capabilities( $sites_number = 0, bool $network_wide = false ) {
		\NoobRocket\Engine\Deactivation\Deactivation::for_each_deactivated_site(
			function () {
				$role = $this->get_administrator_role_object();

				if ( is_null( $role ) ) {
					return;
				}

				foreach ( $this->get_capabilities() as $cap ) {
					$role->remove_cap( $cap );
				}
			},
			$network_wide
		);
	}

	/**
	 * Sets the capability for the options page.
	 *
	 * @since 3.4
	 *
	 * @param string $capability The capability used for the page, which is manage_options by default.
	 * @return string
	 */
	public function required_capability( $capability ) {
		return 'noobrocket_manage_options';
	}

	/**
	 * Add NoobRocket capabilities to User Role Editor
	 *
	 * @since 3.4
	 *
	 * @param array $caps Array of existing capabilities.
	 * @return array
	 */
	public function add_caps_to_ure( $caps ) {
		foreach ( $this->get_capabilities() as $cap ) {
			$caps[ $cap ] = [
				'custom',
				'noobrocket',
			];
		}

		return $caps;
	}

	/**
	 * Add NoobRocket as a group in User Role Editor
	 *
	 * @since 3.4
	 *
	 * @param array $groups Array of existing groups.
	 * @return array
	 */
	public function add_group_to_ure( $groups ) {
		$groups['noobrocket'] = [
			'caption' => esc_html( 'Cache-Cache' ),
			'parent'  => 'custom',
			'level'   => 2,
		];

		return $groups;
	}

	/**
	 * Add NoobRocket as a cap group in Members
	 */
	public function add_cap_group_to_members() {
		// @phpstan-ignore-next-line
		\members_register_cap_group(
			'noobrocket',
			[
				'label'    => esc_html( 'Cache-Cache' ),
				'priority' => 42,
				'caps'     => $this->get_capabilities(),
			]
		);
	}

	/**
	 * Add NoobRocket capabilities to Members
	 */
	public function add_caps_to_members() {
		foreach ( $this->get_capabilities() as $cap ) {
			// @phpstan-ignore-next-line
			\members_register_cap( $cap, [ 'label' => $cap ] );
		}
	}

	/**
	 * Adds NoobRocket capabilities on plugin upgrade
	 *
	 * @since 3.6.3
	 *
	 * @param string $noobrocket_version Latest NoobRocket version.
	 * @param string $actual_version Installed NoobRocket version.
	 * @return void
	 */
	public function add_capabilities_on_upgrade( $noobrocket_version, $actual_version ) {
		if ( version_compare( $actual_version, '3.9', '<' ) ) {
			$this->add_noobrocket_capabilities();
		}
	}

	/**
	 * Returns the object for the administrator roll
	 *
	 * @since 3.6.3
	 *
	 * @return WP_Role|null
	 */
	private function get_administrator_role_object() {
		return get_role( 'administrator' );
	}
}
