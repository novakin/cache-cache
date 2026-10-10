<?php
namespace NoobRocket\Engine\Capabilities;

use NoobRocket\Event_Management\Subscriber_Interface;

/**
 * Manage NoobRocket custom capabilities
 *
 * @since 3.4
 */
class Subscriber implements Subscriber_Interface {
	/**
	 * Capabilities manager instance
	 *
	 * @var Manager
	 */
	private $capabilities;

	/**
	 * Instantiate the subscriber
	 *
	 * @param Manager $capabilities Capabilities manager instance.
	 */
	public function __construct( Manager $capabilities ) {
		$this->capabilities = $capabilities;
	}

	/**
	 * Return an array of events that this subscriber wants to listen to.
	 *
	 * @since  3.4
	 *
	 * @return array
	 */
	public static function get_subscribed_events() {
		$slug = noobrocket_get_constant( 'NOOBROCKET_PLUGIN_SLUG' );

		return [
			"option_page_capability_{$slug}" => 'required_capability',
			'ure_built_in_wp_caps'           => 'add_caps_to_ure',
			'ure_capabilities_groups_tree'   => 'add_group_to_ure',
			'members_register_cap_groups'    => 'add_cap_group_to_members',
			'members_register_caps'          => 'add_caps_to_members',
			'noobrocket_upgrade'              => [ 'add_capabilities_on_upgrade', 12, 2 ],
		];
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
		return $this->capabilities->required_capability( $capability );
	}

	/**
	 * Adds NoobRocket capabilities to User Role Editor
	 *
	 * @since 3.4
	 *
	 * @param array $caps Array of existing capabilities.
	 * @return array
	 */
	public function add_caps_to_ure( $caps ) {
		return $this->capabilities->add_caps_to_ure( $caps );
	}

	/**
	 * Adds NoobRocket as a group in User Role Editor
	 *
	 * @since 3.4
	 *
	 * @param array $groups Array of existing groups.
	 * @return array
	 */
	public function add_group_to_ure( $groups ) {
		return $this->capabilities->add_group_to_ure( $groups );
	}

	/**
	 * Add NoobRocket as a cap group in Members
	 */
	public function add_cap_group_to_members() {
		$this->capabilities->add_cap_group_to_members();
	}

	/**
	 * Add NoobRocket capabilities to Members
	 */
	public function add_caps_to_members() {
		$this->capabilities->add_caps_to_members();
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
		$this->capabilities->add_capabilities_on_upgrade( $noobrocket_version, $actual_version );
	}
}
