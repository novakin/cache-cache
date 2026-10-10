<?php
namespace NoobRocket\Engine\Admin\Settings;

use Imagify_Partner;
use NoobRocket\Event_Management\Subscriber_Interface;
use NoobRocket\Dependencies\WPMedia\PluginFamily\Controller\{ PluginFamily, PluginFamilyInterface };

/**
 * NoobRocket settings page subscriber.
 *
 * @since 3.5.5 Moves into the new architecture.
 * @since 3.3
 */
class Subscriber implements Subscriber_Interface, PluginFamilyInterface {
	/**
	 * Page instance
	 *
	 * @var Page
	 */
	private $page;

	/**
	 * PluginFamily instance
	 *
	 * @var PluginFamily
	 *
	 * @since 3.17.2
	 */
	protected $plugin_family;

	/**
	 * Creates an instance of the object.
	 *
	 * @param Page         $page Page instance.
	 * @param PluginFamily $plugin_family Plugin Family Instance.
	 */
	public function __construct( Page $page, PluginFamily $plugin_family ) {
		$this->page          = $page;
		$this->plugin_family = $plugin_family;
	}

	/**
	 * Return an array of events that this subscriber wants to listen to.
	 *
	 * @since  3.3
	 *
	 * @return array
	 */
	public static function get_subscribed_events() {
		$events = [
			'admin_menu'                           => 'add_admin_page',
			'admin_init'                           => 'configure',
			'wp_ajax_noobrocket_toggle_option'         => 'toggle_option',
			'noobrocket_settings_menu_navigation'      => [
				[ 'add_menu_tools_page' ],
			],
			'admin_enqueue_scripts'                => [
				[ 'enqueue_url' ],
			],
			'noobrocket_after_settings_radio_options'  => [ 'display_radio_options_sub_fields', 11 ],
			'noobrocket_settings_tools_content'        => 'display_mobile_cache_option',
			'wp_ajax_noobrocket_enable_mobile_cache'   => 'enable_mobile_cache',
			'noobrocket_upgrade'                    => [ 'enable_separate_cache_files_mobile', 9, 2 ],
		];

		return $events;
	}

	/**
	 * Enqueue the URL for option exporting.
	 *
	 * @return void
	 */
	public function enqueue_url() {
		wp_localize_script(
			'noobrocket-settings-common',
			'noobrocket_option_export',
			[
				'rest_url_option_export' => rest_url( 'noobrocket/v1/options/export/' ),
			]
		);
	}

	/**
	 * Enqueues NoobRocket scripts on the settings page
	 *
	 * @since 3.6
	 *
	 * @param string $hook The current admin page.
	 *
	 * @return void
	 */
	public function enqueue_noobrocket_scripts( $hook ) {
		$this->page->enqueue_noobrocket_scripts( $hook );
	}

	/**
	 * Adds the async attribute to the Wistia script
	 *
	 * @param string $tag    The <script> tag for the enqueued script.
	 * @param string $handle The script's registered handle.
	 *
	 * @return string
	 */
	public function async_wistia_script( $tag, $handle ) {
		return $this->page->async_wistia_script( $tag, $handle );
	}

	/**
	 * Adds plugin page to the Settings menu.
	 *
	 * @since 3.0
	 */
	public function add_admin_page() {
		add_options_page(
			$this->page->get_title(),
			/**
			 * Filters the menu title to display in the Settings sub-menu
			 *
			 * @since 3.7.4
			 *
			 * @param string $menu_title The text to be used for the menu.
			 * @param string $context Filter context, adminmenu or adminbar.
			 */
			wpm_apply_filters_typed( 'string', 'noobrocket_menu_title', $this->page->get_title(), 'adminmenu' ),
			$this->page->get_capability(),
			$this->page->get_slug(),
			[ $this->page, 'render_page' ]
		);
	}

	/**
	 * Registers the settings, page sections, fields sections and fields.
	 *
	 * @since 3.0
	 */
	public function configure() {
		$this->page->configure();
	}

	/**
	 * Gets customer data to refresh it on the dashboard with AJAX.
	 *
	 * @since 3.0
	 *
	 * @return string
	 */
	public function refresh_customer_data() {
		check_ajax_referer( 'noobrocket-ajax' );

		if ( ! current_user_can( 'noobrocket_manage_options' ) ) {
			wp_die();
		}

		delete_transient( 'noobrocket_customer_data' );
		delete_transient( 'nbr_user_information_timeout_active' );
		delete_transient( 'nbr_user_information_timeout' );

		return wp_send_json_success( $this->page->customer_data() );
	}

	/**
	 * Toggle sliding checkboxes option value.
	 *
	 * @since 3.0
	 */
	public function toggle_option() {
		$this->page->toggle_option();
	}

	/**
	 * Add Tools section to navigation.
	 *
	 * @since 3.0
	 *
	 * @param array $navigation Array of menu items.
	 * @return array
	 */
	public function add_menu_tools_page( $navigation ) {
		$navigation['tools'] = [
			'id'               => 'tools',
			'title'            => __( 'Tools', 'noobrocket' ),
			'menu_description' => __( 'Export and import settings', 'noobrocket' ),
		];

		return $navigation;
	}

	/**
	 * Add Imagify section to navigation.
	 *
	 * @since 3.2
	 *
	 * @param array $navigation Array of menu items.
	 * @return array
	 */
	public function add_imagify_page( $navigation ) {
		if (
			noobrocket_get_constant( 'NOOBROCKET_WHITE_LABEL_ACCOUNT' )
			||
			Imagify_Partner::has_imagify_api_key()
		) {
			return $navigation;
		}

		$navigation['imagify'] = [
			'id'               => 'imagify',
			'title'            => __( 'Image Optimization', 'noobrocket' ),
			'menu_description' => __( 'Compress your images', 'noobrocket' ),
		];

		return $navigation;
	}

	/**
	 * Add Tutorials section to navigation.
	 *
	 * @since 3.4
	 *
	 * @param array $navigation Array of menu items.
	 * @return array
	 */
	public function add_tutorials_page( $navigation ) {
		$navigation['tutorials'] = [
			'id'               => 'tutorials',
			'title'            => __( 'Tutorials', 'noobrocket' ),
			'menu_description' => __( 'Getting started and how to videos', 'noobrocket' ),
		];

		return $navigation;
	}

	/**
	 * Displays the radio option sub fields
	 *
	 * @since 3.10
	 *
	 * @param array $option_data array of option_id and sub_fields of the option.
	 *
	 * @return void
	 */
	public function display_radio_options_sub_fields( $option_data ) {
		if ( empty( $option_data['sub_fields'] ) ) {
			return;
		}
		$this->page->display_radio_options_sub_fields( $option_data['sub_fields'] );
	}

	/**
	 * Render mobile cache option.
	 *
	 * @return void
	 */
	public function display_mobile_cache_option(): void {
		$this->page->display_mobile_cache_option();
	}

	/**
	 * Callback method for the AJAX request to mobile cache.
	 *
	 * @return void
	 */
	public function enable_mobile_cache(): void {
		$this->page->enable_mobile_cache();
	}

	/**
	 * Enable Separate cache files for mobile devices on upgrade.
	 *
	 * @param string $new_version New plugin version.
	 * @param string $old_version Previous plugin version.
	 * @return void
	 */
	public function enable_separate_cache_files_mobile( $new_version, $old_version ): void {
		if ( version_compare( $old_version, '3.16', '>' ) ) {
			return;
		}

		$this->page->enable_separate_cache_files_mobile();
	}

	/**
	 * Add Plugins section to navigation.
	 *
	 * @since 3.17.2
	 *
	 * @param array $navigation Array of menu items.
	 * @return array
	 */
	public function add_plugins_page( $navigation ) {
		$navigation['plugins'] = [
			'id'               => 'plugins',
			'title'            => __( 'Our Plugins', 'noobrocket' ),
			'menu_description' => __( 'Build Better, Faster, Safer', 'noobrocket' ),
		];

		return $navigation;
	}

	/**
	 * Install and activate plugin method for plugin family
	 *
	 * @return void
	 */
	public function install_activate(): void {
		$this->plugin_family->install_activate();
	}

	/**
	 * Display error related to plugin family
	 *
	 * @return void
	 */
	public function display_error_notice(): void {
		$this->plugin_family->display_error_notice();
	}

	/**
	 * Enqueue block editor assets.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		$this->plugin_family->enqueue_assets();
	}

	/**
	 * Install Imagify using the ajax request.
	 *
	 * @return void
	 */
	public function install_imagify(): void {
		$this->plugin_family->install_imagify();
	}

	/**
	 * Enqueue Admin assets.
	 *
	 * @param string $page Page ID.
	 * @return void
	 */
	public function enqueue_admin_assets( $page ): void {
		$this->plugin_family->enqueue_admin_assets( $page );
	}

	/**
	 * Insert admin footer JS templates.
	 *
	 * @return void
	 */
	public function insert_footer_templates(): void {
		$this->plugin_family->insert_footer_templates();
	}

	/**
	 * Dismiss promote Imagify using the ajax request.
	 *
	 * @return void
	 */
	public function dismiss_promote_imagify(): void {
		$this->plugin_family->dismiss_promote_imagify();
	}
}
