<?php
namespace NoobRocket\Engine\Admin\Settings;

use NoobRocket\Engine\Admin\Database\Optimization;
use NoobRocket\Engine\Admin\Beacon\Beacon;
use NoobRocket\Engine\CDN\HostedCDN\SubscriptionController;
use NoobRocket\Engine\License\API\UserClient;
use NoobRocket\Engine\Optimization\DelayJS\Admin\SiteList;
use NoobRocket\Engine\Optimization\DelayJS\Admin\Settings as DelayJSSettings;
use NoobRocket\Engine\Admin\PerformanceMonitoring\Context\Context;
use NoobRocket\Abstract_Render;
use NoobRocket\Admin\Options_Data;

/**
 * Registers the admin page and NoobRocket settings.
 *
 * @since 3.5.5 Moves into the new architecture.
 * @since 3.0
 */
class Page extends Abstract_Render {
	/**
	 * Plugin slug.
	 *
	 * @since 3.0
	 *
	 * @var string
	 */
	private $slug;

	/**
	 * Plugin page title.
	 *
	 * @since 3.0
	 *
	 * @var string
	 */
	private $title;

	/**
	 * Required capability to access the page.
	 *
	 * @since 3.0
	 *
	 * @var string
	 */
	private $capability;

	/**
	 * Settings instance.
	 *
	 * @since 3.0
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Render implementation.
	 *
	 * @since 3.0
	 *
	 * @var Render
	 */
	private $render;

	/**
	 * Beacon instance.
	 *
	 * @since 3.2
	 *
	 * @var Beacon
	 */
	private $beacon;

	/**
	 * Database optimization instance.
	 *
	 * @since 3.3
	 *
	 * @var Optimization
	 */
	private $optimize;

	/**
	 * User client instance.
	 *
	 * @var UserClient
	 */
	private $user_client;

	/**
	 * Delay JS Site List controller.
	 *
	 * @var SiteList
	 */
	protected $delayjs_sitelist;

	/**
	 * NoobRocket options instance
	 *
	 * @var Options_Data
	 */
	private $options;

	/**
	 * Performance monitoring context instance
	 *
	 * @var Context
	 */
	private $ri_context;

	/**
	 * Subscription controller instance.
	 *
	 * @var SubscriptionController
	 */
	private $subscription_controller;

	/**
	 * Creates an instance of the Page object.
	 *
	 * @param array                  $args        Array of required arguments to add the admin page.
	 * @param Settings               $settings    Instance of Settings class.
	 * @param Render                 $render      Render instance.
	 * @param Beacon                 $beacon      Beacon instance.
	 * @param Optimization           $optimize    Database optimization instance.
	 * @param UserClient             $user_client User client instance.
	 * @param SiteList               $delayjs_sitelist User client instance.
	 * @param string                 $template_path Path to views.
	 * @param Options_Data           $options       NoobRocket options instance.
	 * @param Context                $ri_context   Performance monitoring context instance.
	 * @param SubscriptionController $subscription_controller Subscription controller instance.
	 * @since 3.0
	 */
	public function __construct(
		array $args,
		Settings $settings,
		Render $render,
		Beacon $beacon,
		Optimization $optimize,
		UserClient $user_client,
		SiteList $delayjs_sitelist,
		$template_path,
		Options_Data $options,
		Context $ri_context,
		SubscriptionController $subscription_controller
	) {
		parent::__construct( $template_path );
		$args = array_merge(
			[
				'slug'       => 'noobrocket',
				'title'      => 'Cache-Cache',
				'capability' => 'noobrocket_manage_options',
			],
			$args
		);

		$this->slug                    = $args['slug'];
		$this->title                   = $args['title'];
		$this->capability              = $args['capability'];
		$this->settings                = $settings;
		$this->render                  = $render;
		$this->beacon                  = $beacon;
		$this->optimize                = $optimize;
		$this->user_client             = $user_client;
		$this->delayjs_sitelist        = $delayjs_sitelist;
		$this->options                 = $options;
		$this->ri_context              = $ri_context;
		$this->subscription_controller = $subscription_controller;
	}

	/**
	 * Returns the settings page title.
	 *
	 * @since 3.3
	 *
	 * @return string
	 */
	public function get_title() {
		return $this->title;
	}

	/**
	 * Returns the settings page slug.
	 *
	 * @since 3.3
	 *
	 * @return string
	 */
	public function get_slug() {
		return $this->slug;
	}

	/**
	 * Returns the settings page capability.
	 *
	 * @since 3.3
	 *
	 * @return string
	 */
	public function get_capability() {
		return $this->capability;
	}

	/**
	 * Registers the settings, page sections, fields sections and fields.
	 *
	 * @since 3.0
	 */
	public function configure() {
		register_setting( $this->slug, NOOBROCKET_SLUG, [ $this->settings, 'sanitize_callback' ] );
	}

	/**
	 * Renders the settings page.
	 *
	 * @since 3.0
	 */
	public function render_page() {
		$this->dashboard_section();
		$this->settings->add_page_section( 'assets', [
			'title' => __( 'Asset Manager', 'noobrocket' ),
			'menu_description' => __( 'Page rules and private preview', 'noobrocket' ),
		] );
		$this->settings->add_page_section( 'history', [
			'title' => __( 'Settings history', 'noobrocket' ),
			'menu_description' => __( 'Review and restore previous settings', 'noobrocket' ),
		] );
		$this->cdn_section();
		$this->assets_section();
		$this->media_section();
		$this->preload_section();
		$this->advanced_cache_section();
		$this->database_section();
		$this->heartbeat_section();
		$this->addons_section();
		$this->cloudflare_section();
		$this->sucuri_section();

		$this->render->set_settings( $this->settings->get_settings() );

		$this->hidden_fields();

		$this->render->set_hidden_settings( $this->settings->get_hidden_settings() );

		$btn_submit_text = __( 'Save Changes', 'noobrocket' );
		echo $this->render->generate( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view.
			'page',
			[
				'slug'            => $this->slug, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view.
				'btn_submit_text' => $btn_submit_text, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view.
				'tools_page'      => [],
			]
		);
	}

	/**
	 * Enqueues NoobRocket scripts on the settings page
	 *
	 * @since 3.6.1
	 *
	 * @param string $hook The current admin page.
	 *
	 * @return void
	 */
	public function enqueue_noobrocket_scripts( $hook ) {
		if ( 'settings_page_noobrocket' !== $hook ) {
			return;
		}

		wp_enqueue_script( 'wistia-e-v1', 'https://fast.wistia.com/assets/external/E-v1.js', [], null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
	}

	/**
	 * Adds the async attribute to the Wistia script
	 *
	 * @since 3.6.1
	 *
	 * @param string $tag    The <script> tag for the enqueued script.
	 * @param string $handle The script's registered handle.
	 *
	 * @return string
	 */
	public function async_wistia_script( $tag, $handle ) {
		if ( 'wistia-e-v1' !== $handle ) {
			return $tag;
		}

		return str_replace( ' src', ' async src', $tag );
	}

	/**
	 * Returns the customer data to display on the dashboard
	 *
	 * @since 3.7.3 Update to use the user client class to get the data
	 * @since 3.0
	 *
	 * @return array
	 */
	public function customer_data() {
		return [
			'license_type'      => __( 'Free', 'noobrocket' ),
			'can_update_plugin' => false,
		];
	}

	/**
	 * Toggle sliding checkboxes option value.
	 *
	 * @since 3.0
	 */
	public function toggle_option() {
		check_ajax_referer( 'noobrocket-ajax' );

		if ( ! current_user_can( 'noobrocket_manage_options' ) ) {
			wp_die();
		}

		$allowed = [
			'debug_enabled'               => 1,
			'varnish_auto_purge'          => 1,
			'do_cloudflare'               => 1,
			'cloudflare_protocol_rewrite' => 1,
			'sucury_waf_cache_sync'       => 1,
			'sucury_waf_api_key'          => 1,
			'cache_webp'                  => 1,
			'cache_logged_user'           => 1,
			'noobrocket-js-tips'                 => 1,
		];

		if ( ! isset( $_POST['option']['name'] ) || ! isset( $allowed[ $_POST['option']['name'] ] ) ) {
			wp_die();
		}

		$value = (int) ! empty( $_POST['option']['value'] );

		update_noobrocket_option( sanitize_key( $_POST['option']['name'] ), $value );

		wp_die();
	}

	/**
	 * Forces the value for the mobile options if a mobile plugin is active.
	 *
	 * @since  3.0
	 * @since  3.2 Not used anymore.
	 * @see    \NoobRocket\Subscriber\Third_Party\Plugins\Mobile_Subscriber::is_mobile_plugin_active_callback()
	 *
	 * @param mixed $value Option value.
	 *
	 * @return mixed
	 */
	public function is_mobile_plugin_active( $value ) {
		if ( noobrocket_is_mobile_plugin_active() ) {
			return 1;
		}

		return $value;
	}

	/**
	 * Registers Dashboard section.
	 *
	 * @since 3.0
	 */
	private function dashboard_section() {
		$this->settings->add_page_section(
			'dashboard',
			[
				'title'                   => __( 'Dashboard', 'noobrocket' ),
				'menu_description'        => __( 'Free local optimization', 'noobrocket' ),
				'faq'                     => $this->beacon->get_suggest( 'faq' ),
			]
		);
	}

	/**
	 * Registers CSS & Javascript section.
	 *
	 * @since 3.0
	 */
	private function assets_section() {
		$combine_beacon             = $this->beacon->get_suggest( 'combine' );
		$defer_js_beacon            = $this->beacon->get_suggest( 'defer_js' );
		$files_beacon               = $this->beacon->get_suggest( 'file_optimization' );
		$inline_js_beacon           = $this->beacon->get_suggest( 'exclude_inline_js' );
		$exclude_js_beacon          = $this->beacon->get_suggest( 'exclude_js' );
		$exclude_css_beacon         = $this->beacon->get_suggest( 'exclude_css' );
		$delay_js_beacon            = $this->beacon->get_suggest( 'delay_js' );
		$exclude_defer_js           = $this->beacon->get_suggest( 'exclude_defer_js' );

		$disable_combine_js = $this->disable_combine_js();

		$this->settings->add_page_section(
			'file_optimization',
			[
				'title'            => __( 'CSS & JavaScript', 'noobrocket' ),
				'menu_description' => __( 'Optimize CSS & JS', 'noobrocket' ),
			]
		);

		$css_section_helper = [];

		if ( noobrocket_maybe_disable_minify_css() ) {
			// translators: %1$s = type of minification (HTML, CSS or JS), %2$s = “NoobRocket”.
			$css_section_helper[] = sprintf( __( '%1$s Minification is currently activated in <strong>Autoptimize</strong>. If you want to use %2$s’s minification, disable this option in Autoptimize.', 'noobrocket' ), 'CSS', NOOBROCKET_PLUGIN_NAME );
		}

		$css_worker      = get_option( 'noobrocket_local_css_worker', [] );
		$css_worker_seen = is_array( $css_worker ) ? (int) ( $css_worker['last_seen'] ?? 0 ) : 0;
		$css_report      = $css_worker_seen
			? sprintf( __( 'Last environment report: %s. This does not confirm the worker is currently scheduled.', 'noobrocket' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $css_worker_seen ) )
			: __( 'No worker report yet. Set up the worker before expecting generated styles.', 'noobrocket' );
		$css_setup       = '<div class="noobrocket-css-setup"><h3>' . esc_html__( 'Worker setup', 'noobrocket' ) . '</h3><p>' .
			esc_html__( 'Requires Node.js and Chromium on your WordPress server or VPS. Save a mode, then schedule the bundled worker on the same server. No account or API key is required.', 'noobrocket' ) .
			'</p><p class="noobrocket-css-worker-report">' . esc_html( $css_report ) . ' <a href="#dashboard">' . esc_html__( 'View generation status', 'noobrocket' ) . '</a></p></div>';

		$this->settings->add_settings_sections(
			[
				'css' => [
					'title'  => __( 'CSS delivery', 'noobrocket' ),
					'help'   => [
						'id'  => $this->beacon->get_suggest( 'css_section' ),
						'url' => $files_beacon['url'],
					],
					'page'   => 'file_optimization',
					'helper' => $css_section_helper,
				],
				'js'  => [
					'title'  => __( 'JavaScript delivery', 'noobrocket' ),
					'help'   => [
						'id'  => $this->beacon->get_suggest( 'js_section' ),
						'url' => $files_beacon['url'],
					],
					'page'   => 'file_optimization',
					// translators: %1$s = type of minification (HTML, CSS or JS), %2$s = “NoobRocket”.
					'helper' => noobrocket_maybe_disable_minify_js() ? sprintf( __( '%1$s Minification is currently activated in <strong>Autoptimize</strong>. If you want to use %2$s’s minification, disable those options in Autoptimize.', 'noobrocket' ), 'JS', NOOBROCKET_PLUGIN_NAME ) : '',
				],
				'css_advanced' => [
					'title'      => __( 'CSS setup and exclusions', 'noobrocket' ),
					'disclosure' => true,
					'description' => $css_setup,
					'page'       => 'file_optimization',
				],
				'js_advanced' => [
					'title'      => __( 'JavaScript exclusions and compatibility', 'noobrocket' ),
					'disclosure' => true,
					'page'       => 'file_optimization',
				],
				'wordpress_assets' => [
					'title'      => __( 'WordPress asset removal', 'noobrocket' ),
					'disclosure' => true,
					'page'       => 'file_optimization',
				],
			]
		);

		$delay_js_list_helper       = esc_html__( 'Exclusions keep essential scripts available before interaction. Test navigation and other controls when changing them.', 'noobrocket' );
		$delay_js_found_list_helper = esc_html__( 'Internal scripts are excluded by default to protect interactive features. Keep these exclusions unless you have tested the affected pages.', 'noobrocket' );

		$this->settings->add_settings_fields(
			[
				'local_css_mode'               => [
					'type'              => 'select',
					'label'             => __( 'Local CSS generation', 'noobrocket' ),
					'description'       => __( 'Original styles remain active while generation is pending or fails. Check menus, dialogs and other interactive states after enabling CSS generation.', 'noobrocket' ),
					'section'           => 'css',
					'page'              => 'file_optimization',
					'default'           => 'off',
					'sanitize_callback' => 'sanitize_text_field',
					'choices'           => [
						'off'      => __( 'Off', 'noobrocket' ),
						'critical' => __( 'Local critical CSS', 'noobrocket' ),
						'used'     => __( 'Local used CSS', 'noobrocket' ),
					],
				],
				'minify_css'                   => [
					'type'              => 'checkbox',
					'label'             => __( 'Minify CSS files', 'noobrocket' ),
					'description'       => __( 'Minify CSS removes whitespace and comments to reduce the file size.', 'noobrocket' ),
					'container_class'   => [
						'noobrocket-isParent',
						noobrocket_maybe_disable_minify_css() ? 'noobrocket-isDisabled' : '',
					],
					'section'           => 'css',
					'page'              => 'file_optimization',
					'default'           => 0,
					'sanitize_callback' => 'sanitize_checkbox',
					'input_attr'        => [
						'disabled' => noobrocket_maybe_disable_minify_css() ? 1 : 0,
					],
				],
				'minify_js'                    => [
					'type'              => 'checkbox',
					'label'             => __( 'Minify JavaScript files', 'noobrocket' ),
					'description'       => __( 'Minify JavaScript removes whitespace and comments to reduce the file size.', 'noobrocket' ),
					'container_class'   => [
						'noobrocket-isParent',
						noobrocket_maybe_disable_minify_js() ? 'noobrocket-isDisabled' : '',
					],
					'section'           => 'js',
					'page'              => 'file_optimization',
					'default'           => 0,
					'input_attr'        => [
						'disabled' => noobrocket_maybe_disable_minify_js() ? 1 : 0,
					],
					'sanitize_callback' => 'sanitize_checkbox',
				],
				'defer_all_js'                 => [
					'container_class'   => [
						'noobrocket-isParent',
					],
					'type'              => 'checkbox',
					'label'             => __( 'Load JavaScript deferred', 'noobrocket' ),
					// translators: %1$s = opening <a> tag, %2$s = closing </a> tag.
					'description'       => sprintf( __( 'Download scripts without blocking page parsing, then execute them after the HTML is parsed. %1$sMore info%2$s', 'noobrocket' ), '<a href="' . esc_url( $defer_js_beacon['url'] ) . '" data-beacon-article="' . esc_attr( $defer_js_beacon['id'] ) . '" target="_blank" rel="noopener noreferrer">', '</a>' ),
					'section'           => 'js',
					'page'              => 'file_optimization',
					'default'           => 0,
					'sanitize_callback' => 'sanitize_checkbox',
				],
				'delay_js'                     => apply_filters(
					'noobrocket_delay_js_settings_field',
					[
						'container_class'   => [
							'noobrocket-isParent',
							'noobrocket-Delayjs',
						],
						'type'              => 'checkbox',
						'label'             => __( 'Delay JavaScript execution', 'noobrocket' ),
						// translators: %1$s = opening <a> tag, %2$s = closing </a> tag.
						'description'       => sprintf( __( 'Wait for visitor interaction before loading scripts. After saving, test menus and forms; use %1$sscript exclusions%2$s if a control must work immediately.', 'noobrocket' ), '<a href="#file_optimization=delay_js_exclusions">', '</a>' ),
						'section'           => 'js',
						'page'              => 'file_optimization',
						'default'           => 0,
						'sanitize_callback' => 'sanitize_checkbox',
					]
				),
				'exclude_css'                  => [
					'type'              => 'textarea',
					'label'             => __( 'Excluded CSS Files', 'noobrocket' ),
					'description'       => __( 'Specify URLs of CSS files to be excluded from minification (one per line).', 'noobrocket' ),
					'helper'            => __( '<strong>Internal:</strong> The domain part of the URL will be stripped automatically. Use (.*).css wildcards to exclude all CSS files located at a specific path.', 'noobrocket' ) . '<br>' .
					// translators: %1$s = opening <a> tag, %2$s = closing </a> tag.
					sprintf( __( '<strong>3rd Party:</strong> Use either the full URL path or only the domain name, to exclude external CSS. %1$sMore info%2$s', 'noobrocket' ), '<a href="' . esc_url( $exclude_css_beacon['url'] ) . '" data-beacon-article="' . esc_attr( $exclude_css_beacon['id'] ) . '" rel="noopener noreferrer" target="_blank">', '</a>' ),
					'container_class'   => [
						'noobrocket-field--children',
					],
					'placeholder'       => '/wp-content/plugins/some-plugin/(.*).css',
					'parent'            => 'minify_css',
					'section'           => 'css_advanced',
					'page'              => 'file_optimization',
					'default'           => [],
					'sanitize_callback' => 'sanitize_textarea',
				],
				'local_css_safelist'           => [
					'type'              => 'textarea',
					'label'             => __( 'CSS selector safelist', 'noobrocket' ),
					'description'       => __( 'Keep rules whose selectors contain these literal substrings (one per line). Add selectors for menus, dialogs and other states that appear after interaction. Entries are case-sensitive; regular expressions and wildcards are not interpreted.', 'noobrocket' ),
					'placeholder'       => ".menu-open\n.modal\n.is-active",
					'section'           => 'css_advanced',
					'page'              => 'file_optimization',
					'default'           => [],
					'sanitize_callback' => 'sanitize_textarea',
				],
				'minify_concatenate_js'        => [
					'type'              => 'checkbox',
					'label'             => __( 'Combine JavaScript files', 'noobrocket' ),
					// translators: %1$s = opening <a> tag, %2$s = closing </a> tag.
					'description'       => sprintf( __( 'Combine JavaScript files combines your site’s internal, 3rd party and inline JS reducing HTTP requests. Not recommended if your site uses HTTP/2. %1$sMore info%2$s', 'noobrocket' ), '<a href="' . esc_url( $combine_beacon['url'] ) . '" data-beacon-article="' . esc_attr( $combine_beacon['id'] ) . '" target="_blank">', '</a>' ),
					'helper'            => __( 'Enable Minify JavaScript files and turn off Delay JavaScript execution to use combining.', 'noobrocket' ),
					'container_class'   => [
						$disable_combine_js ? 'noobrocket-isDisabled' : '',
						'noobrocket-field--parent',
						'noobrocket-NoPaddingBottom',
					],
					'section'           => 'js_advanced',
					'page'              => 'file_optimization',
					'default'           => 0,
					'sanitize_callback' => 'sanitize_checkbox',
					'input_attr'        => [
						'disabled' => $disable_combine_js ? 1 : 0,
					],
					'warning'           => [
						'title'        => __( 'Check scripts after combining', 'noobrocket' ),
						'description'  => __( 'Combining can change script order and affect menus, forms or other controls. Test them after saving. If needed, turn combining off and save again.', 'noobrocket' ),
						'button_label' => __( 'Enable JavaScript combining', 'noobrocket' ),
					],
				],
				'exclude_inline_js'            => [
					'type'              => 'textarea',
					'disclosure'        => __( 'Inline JavaScript exclusions', 'noobrocket' ),
					'label'             => __( 'Excluded Inline JavaScript', 'noobrocket' ),
					// translators: %1$s = opening <a> tag, %2$s = closing </a> tag.
					'description'       => sprintf( __( 'Specify patterns of inline JavaScript to be excluded from concatenation (one per line). %1$sMore info%2$s', 'noobrocket' ), '<a href="' . esc_url( $inline_js_beacon['url'] ) . '" data-beacon-article="' . esc_attr( $inline_js_beacon['id'] ) . '" rel="noopener noreferrer" target="_blank">', '</a>' ),
					'container_class'   => [
						'noobrocket-field--children',
					],
					'placeholder'       => 'recaptcha',
					'parent'            => 'minify_concatenate_js',
					'section'           => 'js_advanced',
					'page'              => 'file_optimization',
					'default'           => [],
					'sanitize_callback' => 'sanitize_textarea',
					'input_attr'        => [
						'disabled' => get_noobrocket_option( 'minify_concatenate_js' ) ? 0 : 1,
					],
				],
				'exclude_js'                   => [
					'type'              => 'textarea',
					'disclosure'        => __( 'JavaScript minification exclusions', 'noobrocket' ),
					'label'             => __( 'Excluded JavaScript Files', 'noobrocket' ),
					'description'       => __( 'Specify URLs of JavaScript files to be excluded from minification and concatenation (one per line).', 'noobrocket' ),
					'helper'            => __( '<strong>Internal:</strong> The domain part of the URL will be stripped automatically. Use (.*).js wildcards to exclude all JS files located at a specific path.', 'noobrocket' ) . '<br>' .
					// translators: %1$s = opening <a> tag, %2$s = closing </a> tag.
					sprintf( __( '<strong>3rd Party:</strong> Use either the full URL path or only the domain name, to exclude external JS. %1$sMore info%2$s', 'noobrocket' ), '<a href="' . esc_url( $exclude_js_beacon['url'] ) . '" data-beacon-article="' . esc_attr( $exclude_js_beacon['id'] ) . '" rel="noopener noreferrer" target="_blank">', '</a>' ),
					'container_class'   => [
						'noobrocket-field--children',
					],
					'placeholder'       => '/wp-content/themes/some-theme/(.*).js',
					'parent'            => 'minify_js',
					'section'           => 'js_advanced',
					'page'              => 'file_optimization',
					'default'           => [],
					'sanitize_callback' => 'sanitize_textarea',
				],
				'exclude_defer_js'             => [
					'disclosure'        => __( 'Deferred JavaScript exclusions', 'noobrocket' ),
					'container_class'   => [
						'noobrocket-field--children',
					],
					'type'              => 'textarea',
					'label'             => __( 'Excluded JavaScript Files', 'noobrocket' ),
					// translators: %1$s = opening <a> tag, %2$s = closing </a> tag.
					'description'       => sprintf( __( 'Specify URLs or keywords of JavaScript files to be excluded from defer (one per line). %1$sMore info%2$s', 'noobrocket' ), '<a href="' . esc_url( $exclude_defer_js['url'] ) . '" data-beacon-article="' . esc_attr( $exclude_defer_js['id'] ) . '" target="_blank">', '</a>' ),
					'placeholder'       => '/wp-content/themes/some-theme/(.*).js',
					'parent'            => 'defer_all_js',
					'section'           => 'js_advanced',
					'page'              => 'file_optimization',
					'default'           => [],
					'sanitize_callback' => 'sanitize_textarea',
				],
				'delay_js_exclusions_selected' => [
					'type'              => 'categorized_multiselect',
					'disclosure'        => __( 'One-click exclusions', 'noobrocket' ),
					'label'             => __( 'Plugins, themes and services', 'noobrocket' ),
					'description'       => __( 'Select elements that must load before interaction. Their scripts will be excluded from Delay JavaScript execution.', 'noobrocket' ),
					'container_class'   => [
						'noobrocket-field--children',
					],
					'parent'            => 'delay_js',
					'section'           => 'js_advanced',
					'page'              => 'file_optimization',
					'default'           => [],
					'sanitize_callback' => 'sanitize_textarea',
					'input_attr'        => [
						'disabled' => get_noobrocket_option( 'delay_js' ) ? 0 : 1,
					],
					'items'             => $this->delayjs_sitelist->prepare_delayjs_ui_list(),
				],
				'delay_js_exclusions'          => [
					'type'              => 'textarea',
					'disclosure'        => __( 'Delayed JavaScript exclusions', 'noobrocket' ),
					'container_class'   => [
						'noobrocket-field--children',
					],
					'label'             => __( 'Excluded JavaScript Files', 'noobrocket' ),
					'description'       => __( 'Specify URLs or keywords that can identify inline or JavaScript files to be excluded from delaying execution (one per line).', 'noobrocket' ),
					'parent'            => 'delay_js',
					'section'           => 'js_advanced',
					'page'              => 'file_optimization',
					'default'           => [],
					'sanitize_callback' => 'sanitize_textarea',
					'input_attr'        => [
						'disabled' => get_noobrocket_option( 'delay_js' ) ? 0 : 1,
					],
					'helper'            => DelayJSSettings::exclusion_list_has_default() ? $delay_js_found_list_helper : $delay_js_list_helper,
					'placeholder'       => '',
				],
				'delay_js_execution_safe_mode' => [
					'type'              => 'checkbox',
					'label'             => __( 'Safe Mode for Delay JavaScript Execution', 'noobrocket' ),
					// translators: %1$s = opening <a> tag, %2$s = closing </a> tag.
					'description'       => __( 'Keep all internal scripts available before interaction while testing exclusions.', 'noobrocket' ),
					'helper'            => '',
					'container_class'   => [
						'noobrocket-field--parent',
						'noobrocket-NoPaddingBottom',
						'noobrocket-field--children',
					],
					'section'           => 'js_advanced',
					'page'              => 'file_optimization',
					'parent'            => 'delay_js',
					'default'           => 0,
					'sanitize_callback' => 'sanitize_checkbox',
					'input_attr'        => [
						'disabled' => 0,
					],
					'warning'           => [
						'title'        => __( 'Performance impact', 'noobrocket' ),
						'description'  => __( 'Safe Mode reduces the performance benefits of delayed JavaScript. Use it temporarily while testing script exclusions, then verify your site before turning it off.', 'noobrocket' ),
						'button_label' => __( 'Enable Safe Mode', 'noobrocket' ),
					],
				],
				'remove_dashicons' => [
					'type' => 'checkbox',
					'label' => __( 'Remove unused Dashicons', 'noobrocket' ),
					'description' => __( 'For anonymous visitors only. Styles that depend on Dashicons keep them active.', 'noobrocket' ),
					'section' => 'wordpress_assets', 'page' => 'file_optimization', 'default' => 0, 'sanitize_callback' => 'sanitize_checkbox',
				],
				'remove_embed_assets' => [
					'type' => 'checkbox',
					'label' => __( 'Remove unused WordPress embed assets', 'noobrocket' ),
					'description' => __( 'Skip WordPress embed scripts on pages without embedded posts. Embedded content remains supported.', 'noobrocket' ),
					'section' => 'wordpress_assets', 'page' => 'file_optimization', 'default' => 0, 'sanitize_callback' => 'sanitize_checkbox',
				],
				'remove_block_library' => [
					'type' => 'checkbox',
					'label' => __( 'Remove unused block styles', 'noobrocket' ),
					'description' => __( 'Only classic pages without blocks, block widgets or dependent styles qualify. Block themes keep their styles.', 'noobrocket' ),
					'section' => 'wordpress_assets', 'page' => 'file_optimization', 'default' => 0, 'sanitize_callback' => 'sanitize_checkbox',
				],
			],
		);
	}

	/**
	 * Registers Media section.
	 *
	 * @since 3.0
	 */
	private function media_section() {
		$lazyload_beacon  = $this->beacon->get_suggest( 'lazyload' );
		$exclude_lazyload = $this->beacon->get_suggest( 'exclude_lazyload' );
		$dimensions       = $this->beacon->get_suggest( 'image_dimensions' );
		$fonts            = $this->beacon->get_suggest( 'host_fonts_locally' );
		$fonts_preload    = $this->beacon->get_suggest( 'fonts_preload' );

		$this->settings->add_page_section(
			'media',
			[
				'title'            => __( 'Media', 'noobrocket' ),
				'menu_description' => __( 'LazyLoad, image dimensions, font optimization', 'noobrocket' ),
			]
		);

		$disable_images_lazyload  = [];
		$disable_iframes_lazyload = [];
		$disable_youtube_lazyload = [];

		if ( noobrocket_maybe_disable_lazyload() ) {
			$disable_images_lazyload[] = __( 'Autoptimize', 'noobrocket' );
		}

		/**
		 * Lazyload Helper filter which disables NOOBROCKET lazyload functionality for images.
		 *
		 * @since  3.4.2
		 *
		 * @param array $disable_images_lazyload Will return the array with all plugin names which should disable LazyLoad
		 */
		$disable_images_lazyload = (array) apply_filters( 'noobrocket_maybe_disable_lazyload_helper', $disable_images_lazyload );
		$disable_images_lazyload = $this->sanitize_and_format_list( $disable_images_lazyload );

		/**
		 * Lazyload Helper filter which disables NOOBROCKET lazyload functionality for iframes.
		 *
		 * @since 3.5.5
		 *
		 * @param array $disable_iframes_lazyload Will return the array with all plugin names which should disable LazyLoad
		 */
		$disable_iframes_lazyload = (array) apply_filters( 'noobrocket_maybe_disable_iframes_lazyload_helper', $disable_iframes_lazyload );
		$disable_iframes_lazyload = $this->sanitize_and_format_list( $disable_iframes_lazyload );

		$disable_css_bg_img_lazyload = false;

		/**
		 * Lazyload Helper filter which disables NOOBROCKET lazyload functionality for bg css.
		 *
		 * @param bool $disable_css_bg_img_lazyload Should the lazyload CSS be disabled.
		 */
		$disable_css_bg_img_lazyload = (bool) apply_filters( 'noobrocket_maybe_disable_css_bg_img_lazyload_helper', $disable_css_bg_img_lazyload );

		/**
		 * Lazyload Helper filter which disables NOOBROCKET lazyload functionality to replace YouTube iframe with preview image.
		 *
		 * @since 3.6.3
		 *
		 * @param array $disable_youtube_lazyload Will return the array with all plugin/themes names which should disable replace YouTube iframe with preview image
		 */
		$disable_youtube_lazyload = (array) apply_filters( 'noobrocket_maybe_disable_youtube_lazyload_helper', $disable_youtube_lazyload );
		$disable_youtube_lazyload = $this->sanitize_and_format_list( $disable_youtube_lazyload );
		$disable_youtube_lazyload = array_merge( $disable_youtube_lazyload, $disable_iframes_lazyload );
		$disable_youtube_lazyload = array_unique( $disable_youtube_lazyload );

		$disable_lazyload = array_merge( $disable_images_lazyload, $disable_iframes_lazyload );
		$disable_lazyload = array_unique( $disable_lazyload );

		$disable_lazyload         = wp_sprintf_l( '%l', $disable_lazyload );
		$disable_images_lazyload  = wp_sprintf_l( '%l', $disable_images_lazyload );
		$disable_youtube_lazyload = wp_sprintf_l( '%l', $disable_youtube_lazyload );

		$this->settings->add_settings_sections(
			[
				'lazyload_section'          => [
					'title'       => __( 'Images', 'noobrocket' ),
					'type'        => 'fields_container',
					'description' => __( 'Choose when images load, how they are served and how their space is reserved.', 'noobrocket' ),
					'help'        => [
						'id'  => $this->beacon->get_suggest( 'lazyload_section' ),
						'url' => $lazyload_beacon['url'],
					],
					'page'        => 'media',
					// translators: %1$s = “NoobRocket”, %2$s = a list of plugin names.
					'helper'      => [
						! empty( $disable_lazyload ) ? sprintf( __( 'LazyLoad is currently activated in %2$s. If you want to use Cache-Cache’s LazyLoad, disable this option in %2$s.', 'noobrocket' ), NOOBROCKET_PLUGIN_NAME, $disable_lazyload ) : '',
						'<div class="noobrocket-status-navigation"><a href="#dashboard">' . esc_html__( 'Open media generation status', 'noobrocket' ) . '</a></div>',
					],
				],
				'video_delivery_section' => [
					'title' => __( 'Videos and iframes', 'noobrocket' ),
					'type'  => 'fields_container',
					'page'  => 'media',
				],
				'font_optimization_section' => [
					'title' => __( 'Fonts', 'noobrocket' ),
					'type'  => 'fields_container',
					'help'  => [
						'id'  => $this->beacon->get_suggest( 'fonts_section' ),
						'url' => $fonts['url'],
					],
					'page'  => 'media',
				],
			]
		);

		/**
		 * Add more content to the 'cache_webp' setting field.
		 *
		 * @since  3.4
		 *
		 * @param array $cache_webp_field Data to be added to the setting field.
		 */

		$this->settings->add_settings_fields(
			[
				'lazyload'            => [
					'type'              => 'checkbox',
					'label'             => __( 'Lazy load images', 'noobrocket' ),
					'section'           => 'lazyload_section',
					'page'              => 'media',
					'default'           => 0,
					'sanitize_callback' => 'sanitize_checkbox',
					'container_class'   => [
						! empty( $disable_images_lazyload ) ? 'noobrocket-isDisabled' : '',
					],
					'input_attr'        => [
						'disabled' => ! empty( $disable_images_lazyload ) ? 1 : 0,
					],
					// translators: %1$s = “NoobRocket”, %2$s = a list of plugin names.
					'description'       => ! empty( $disable_images_lazyload ) ? sprintf( __( 'LazyLoad for images is currently activated in %2$s. If you want to use %1$s’s LazyLoad, disable this option in %2$s.', 'noobrocket' ), NOOBROCKET_PLUGIN_NAME, $disable_images_lazyload ) : '',
				],
				'lazyload_css_bg_img' => [
					'container_class'   => [
						$disable_css_bg_img_lazyload ? 'noobrocket-isDisabled' : '',
						'noobrocket-isParent',
					],
					'type'              => 'checkbox',
					'label'             => __( 'Lazy load CSS background images', 'noobrocket' ),
					'section'           => 'lazyload_section',
					'page'              => 'media',
					'default'           => 0,
					'sanitize_callback' => 'sanitize_checkbox',
					'input_attr'        => [
						'disabled' => $disable_css_bg_img_lazyload ? 1 : 0,
					],
				],
				'image_delivery'      => [
					'type'              => 'checkbox',
					'label'             => __( 'Generate and serve AVIF and WebP images', 'noobrocket' ),
					'description'       => __( 'Convert new JPEG and PNG uploads locally, keeping originals as fallbacks. Save first, then queue existing images from Overview. Test image layouts after enabling delivery.', 'noobrocket' ),
					'section'           => 'lazyload_section',
					'page'              => 'media',
					'default'           => 0,
					'sanitize_callback' => 'sanitize_checkbox',
				],
				'image_dimensions'    => [
					'type'              => 'checkbox',
					'label'             => __( 'Add missing image dimensions', 'noobrocket' ),
					// translators: %1$s = opening <a> tag, %2$s = closing </a> tag.
					'description'       => sprintf( __( 'Add missing width and height attributes to images. Helps prevent layout shifts and improve the reading experience for your visitors. %1$sMore info%2$s', 'noobrocket' ), '<a href="' . esc_url( $dimensions['url'] ) . '" data-beacon-article="' . esc_attr( $dimensions['id'] ) . '" target="_blank" rel="noopener noreferrer">', '</a>' ),
					'section'           => 'lazyload_section',
					'page'              => 'media',
					'default'           => 0,
					'sanitize_callback' => 'sanitize_checkbox',
				],
				'exclude_lazyload'    => [
					'disclosure'      => __( 'Excluded images or iframes', 'noobrocket' ),
					'container_class' => [
						'noobrocket-Delayjs',
					],
					'type'            => 'textarea',
					'label'           => __( 'Excluded images or iframes', 'noobrocket' ),
					// translators: %1$s = opening <a> tag, %2$s = closing </a> tag.
					'description'     => sprintf( __( 'Specify keywords (e.g. image filename, CSS filename, CSS class, domain) from the image or iframe code to be excluded (one per line). %1$sMore info%2$s', 'noobrocket' ), '<a href="' . esc_url( $exclude_lazyload['url'] ) . '" data-beacon-article="' . esc_attr( $exclude_lazyload['id'] ) . '" target="_blank" rel="noopener noreferrer">', '</a>' ),
					'section'         => 'lazyload_section',
					'page'            => 'media',
					'default'         => [],
					'placeholder'     => "example-image.jpg\nslider-image\nbackground-image-style.css",
				],
				'lazyload_iframes'    => [
					'container_class'   => [
						! empty( $disable_iframes_lazyload ) ? 'noobrocket-isDisabled' : '',
						'noobrocket-isParent',
					],
					'type'              => 'checkbox',
					'label'             => __( 'Lazy load iframes and videos', 'noobrocket' ),
					'section'           => 'video_delivery_section',
					'page'              => 'media',
					'default'           => 0,
					'sanitize_callback' => 'sanitize_checkbox',
					'input_attr'        => [
						'disabled' => ! empty( $disable_iframes_lazyload ) ? 1 : 0,
					],
				],
				'lazyload_youtube'    => [
					'container_class'   => [
						! empty( $disable_youtube_lazyload ) ? 'noobrocket-isDisabled' : '',
						'noobrocket-field--children',
					],
					'type'              => 'checkbox',
					'label'             => __( 'Replace YouTube iframe with preview image', 'noobrocket' ),
					// translators: %1$s = “NoobRocket”, %2$s = a list of plugin or themes names.
					'description'       => ! empty( $disable_youtube_lazyload ) ? sprintf( __( 'Replace YouTube iframe with preview image is not compatible with %2$s.', 'noobrocket' ), NOOBROCKET_PLUGIN_NAME, $disable_youtube_lazyload ) : __( 'This can significantly improve your loading time if you have a lot of YouTube videos on a page.', 'noobrocket' ),
					'parent'            => 'lazyload_iframes',
					'section'           => 'video_delivery_section',
					'page'              => 'media',
					'default'           => 0,
					'sanitize_callback' => 'sanitize_checkbox',
					'input_attr'        => [
						'disabled' => ! empty( $disable_youtube_lazyload ) ? 1 : 0,
					],
				],
				'auto_preload_fonts'  => [
					'type'              => 'checkbox',
					'label'             => __( 'Preload fonts', 'noobrocket' ),
					// translators: %1$s = opening <a> tag, %2$s = closing </a> tag.
					'description'       => sprintf( __( 'Load fonts used in the first screen sooner so visible text can appear faster. %1$sMore info%2$s', 'noobrocket' ), '<a href="' . esc_url( $fonts_preload['url'] ) . '" data-beacon-article="' . esc_attr( $fonts_preload['id'] ) . '" target="_blank" rel="noopener noreferrer">', '</a>' ),
					'section'           => 'font_optimization_section',
					'page'              => 'media',
					'default'           => 0,
					'sanitize_callback' => 'sanitize_checkbox',
				],
				'host_fonts_locally'  => [
					'type'              => 'checkbox',
					'label'             => __( 'Self-host Google Fonts', 'noobrocket' ),
					// translators: %1$s = opening <a> tag, %2$s = closing </a> tag.
					'description'       => sprintf( __( 'Prepare Google Fonts in the background and serve them locally when ready. Pages keep the original fonts until preparation succeeds. %1$sMore info%2$s', 'noobrocket' ), '<a href="' . esc_url( $fonts['url'] ) . '" data-beacon-article="' . esc_attr( $fonts['id'] ) . '" target="_blank" rel="noopener noreferrer">', '</a>' ),
					'section'           => 'font_optimization_section',
					'page'              => 'media',
					'default'           => 0,
					'sanitize_callback' => 'sanitize_checkbox',
				],
			]
		);
	}

	/**
	 * Registers Preload section.
	 *
	 * @since 3.0
	 */
	private function preload_section() {
		$this->settings->add_page_section(
			'preload',
			[
				'title'            => __( 'Preload', 'noobrocket' ),
				'menu_description' => __( 'Generate cache files', 'noobrocket' ),
			]
		);

		$bot_beacon    = $this->beacon->get_suggest( 'bot' );
		$fonts_preload = $this->beacon->get_suggest( 'fonts_preload' );
		$preload_links = $this->beacon->get_suggest( 'preload_links' );
		$exclusions    = $this->beacon->get_suggest( 'preload_exclusions' );

		$this->settings->add_settings_sections(
			[
				'preload_section'       => [
					'title'       => __( 'Preload Cache', 'noobrocket' ),
					'type'        => 'fields_container',
					'description' => __( 'Warm eligible sitemap pages automatically in small WordPress cron batches.', 'noobrocket' ),
					'help'        => [
						'id'  => $this->beacon->get_suggest( 'sitemap_preload' ),
						'url' => $bot_beacon['url'],
					],
					'page'        => 'preload',
					'helper'      => '<div class="noobrocket-status-navigation"><a href="#dashboard">' . esc_html__( 'View preload progress', 'noobrocket' ) . '</a></div>',
				],
				'preload_links_section' => [
					'title'       => __( 'Next-page loading', 'noobrocket' ),
					'type'        => 'fields_container',
					'description' => __( 'Prepare the next page in the visitor’s browser to improve later navigation.', 'noobrocket' ),
					'help'        => [
						'id'  => $preload_links['id'],
						'url' => $preload_links['url'] . '#next-page',
					],
					'page'        => 'preload',
				],
			]
		);

		$this->settings->add_settings_fields(
			[
				'manual_preload'       => [
					'type'              => 'checkbox',
					'label'             => __( 'Enable automatic warming', 'noobrocket' ),
					'section'           => 'preload_section',
					'page'              => 'preload',
					'default'           => 1,
					'sanitize_callback' => 'sanitize_checkbox',
					'container_class'   => [
						'noobrocket-isParent',
					],
				],
				'preload_excluded_uri' => [
					'type'              => 'textarea',
					'disclosure'        => __( 'Exclude URLs', 'noobrocket' ),
					'label'             => __( 'Exclude URLs', 'noobrocket' ),
					'container_class'   => [
						'noobrocket-field--children',
					],
					// translators: %1$s = opening <a> tag, %2$s = closing </a> tag.
					'description'       => sprintf( __( 'Specify URLs to be excluded from the preload feature (one per line). %1$sMore info%2$s', 'noobrocket' ), '<a href="' . esc_url( $exclusions['url'] ) . '" data-beacon-article="' . esc_attr( $exclusions['id'] ) . '" target="_blank">', '</a>' ),
					'placeholder'       => '/author/(.*)',
					'helper'            => __( 'Use (.*) wildcards to address multiple URLs under a given path.', 'noobrocket' ),
					'parent'            => 'manual_preload',
					'section'           => 'preload_section',
					'page'              => 'preload',
					'default'           => [],
					'sanitize_callback' => 'sanitize_textarea',
				],
				'preload_links'        => [
					'type'              => 'checkbox',
					'label'             => __( 'Enable link preloading', 'noobrocket' ),
					'description'       => __( 'Download the next page’s HTML when a visitor shows interest in a link.', 'noobrocket' ),
					'section'           => 'preload_links_section',
					'page'              => 'preload',
					'default'           => 0,
					'sanitize_callback' => 'sanitize_checkbox',
				],
				'prerender_links'      => [
					'type'              => 'checkbox',
					'label'             => __( 'Prerender public pages', 'noobrocket' ),
					'description'       => __( 'Load resources and run JavaScript before a visitor navigates to a public page. Uses more bandwidth.', 'noobrocket' ),
					'helper'            => '<details class="noobrocket-field-disclosure"><summary>' . esc_html__( 'Browser compatibility', 'noobrocket' ) . '</summary><p>' . esc_html__( 'Prerendering takes priority where supported and falls back to HTML preloading. Enable link preloading on its own to preload HTML without prerendering. Browsers that support neither use normal navigation.', 'noobrocket' ) . '</p></details>',
					'container_class'   => [ 'noobrocket-isParent' ],
					'section'           => 'preload_links_section',
					'page'              => 'preload',
					'default'           => 0,
					'sanitize_callback' => 'sanitize_checkbox',
				],
				'prerender_links_mode' => [
					'type'              => 'select',
					'label'             => __( 'Prerender trigger', 'noobrocket' ),
					'description'       => __( 'Link intent waits for hovering, focus or touch. Document rules let the browser choose among eligible public links. Unsupported browsers keep link preloading.', 'noobrocket' ),
					'choices'           => [ 'intent' => __( 'Link intent', 'noobrocket' ), 'document' => __( 'Browser document rules', 'noobrocket' ) ],
					'container_class'   => [ 'noobrocket-field--children' ],
					'parent'            => 'prerender_links',
					'section'           => 'preload_links_section',
					'page'              => 'preload',
					'default'           => 'intent',
					'sanitize_callback' => 'sanitize_text_field',
				],
				'prerender_links_eagerness' => [
					'type'              => 'select',
					'label'             => __( 'Document rule eagerness', 'noobrocket' ),
					'description'       => __( 'Applies to browser document rules. Conservative waits for a click or touch; moderate may prepare links on hover; eager may prepare visible links and uses more bandwidth.', 'noobrocket' ),
					'choices'           => [ 'conservative' => __( 'Conservative', 'noobrocket' ), 'moderate' => __( 'Moderate', 'noobrocket' ), 'eager' => __( 'Eager', 'noobrocket' ) ],
					'container_class'   => [ 'noobrocket-field--children' ],
					'parent'            => 'prerender_links',
					'section'           => 'preload_links_section',
					'page'              => 'preload',
					'default'           => 'conservative',
					'sanitize_callback' => 'sanitize_text_field',
				],
				'prerender_excluded_uri' => [
					'type'              => 'textarea',
					'disclosure'        => __( 'Prerender exclusions', 'noobrocket' ),
					'label'             => __( 'Prerender exclusions', 'noobrocket' ),
					'description'       => __( 'Exclude action links and private routes, one URI pattern per line. Cache exclusions are also honored. Add the no-prerender class to individual links to skip them.', 'noobrocket' ),
					'helper'            => __( 'Use (.*) wildcards to address multiple URLs under a given path.', 'noobrocket' ),
					'placeholder'       => '/members/(.*)',
					'container_class'   => [ 'noobrocket-field--children' ],
					'parent'            => 'prerender_links',
					'section'           => 'preload_links_section',
					'page'              => 'preload',
					'default'           => [],
					'sanitize_callback' => 'sanitize_textarea',
				],
			]
		);
	}

	/**
	 * Registers Advanced Cache section.
	 *
	 * @since 3.0
	 */
	private function advanced_cache_section() {
		$stale_refresh_helper = '';
		if ( $this->options->get( 'cache_stale_refresh', 0 ) && ! noobrocket_is_stale_refresh_enabled( $this->options->get_options() ) ) {
			$stale_refresh_helper = __( 'Background refresh is inactive with the current settings. Check its requirements, save changes, then verify cache delivery from Overview.', 'noobrocket' );
		}

		$this->settings->add_page_section(
			'advanced_cache',
			[
				'title'            => __( 'Cache rules', 'noobrocket' ),
				'menu_description' => __( 'Fine-tune cache rules', 'noobrocket' ),
			]
		);
		$ecommerce_beacon           = $this->beacon->get_suggest( 'ecommerce' );
		$never_cache_beacon         = $this->beacon->get_suggest( 'exclude_cache' );
		$cache_life_beacon          = $this->beacon->get_suggest( 'cache_lifespan' );
		$nonce_beacon               = $this->beacon->get_suggest( 'nonce' );

		$ecommerce_plugin = '';
		$reject_uri_desc  = __( 'Sensitive pages like custom login/logout URLs should be excluded from cache.', 'noobrocket' );

		if ( function_exists( 'WC' ) && function_exists( 'wc_get_page_id' ) ) {
			$ecommerce_plugin = _x( 'WooCommerce', 'plugin name', 'noobrocket' );
		} elseif ( function_exists( 'EDD' ) ) {
			$ecommerce_plugin = _x( 'Easy Digital Downloads', 'plugin name', 'noobrocket' );
		} elseif ( function_exists( 'it_exchange_get_page_type' ) && function_exists( 'it_exchange_get_page_url' ) ) {
			$ecommerce_plugin = _x( 'iThemes Exchange', 'plugin name', 'noobrocket' );
		} elseif ( defined( 'JIGOSHOP_VERSION' ) && function_exists( 'jigoshop_get_page_id' ) ) {
			$ecommerce_plugin = _x( 'Jigoshop', 'plugin name', 'noobrocket' );
		} elseif ( defined( 'WPSHOP_VERSION' ) && class_exists( 'wpshop_tools' ) && method_exists( 'wpshop_tools', 'get_page_id' ) ) { // @phpstan-ignore-line
			$ecommerce_plugin = _x( 'WP-Shop', 'plugin name', 'noobrocket' );
		}

		if ( ! empty( $ecommerce_plugin ) ) {
			$reject_uri_desc .= sprintf(
					// translators: %1$s = opening <a> tag, %2$s = plugin name, %3$s closing </a> tag.
					__( '<br>Cart, checkout and "my account" pages set in <strong>%1$s%2$s%3$s</strong> will be detected and never cached by default.', 'noobrocket' ),
					'<a href="' . esc_url( $ecommerce_beacon['url'] ) . '" data-beacon-article="' . esc_attr( $ecommerce_beacon['id'] ) . '" target="_blank">',
					$ecommerce_plugin,
					'</a>'
			);
		}

		$this->settings->add_settings_sections(
			[
				'cache_lifespan'               => [
					'title'       => __( 'Cache Lifespan', 'noobrocket' ),
					'type'        => 'fields_container',
					// translators: %1$s = opening <a> tag, %2$s = closing </a> tag.
					'description' => sprintf( __( 'Expired cache files are deleted. Enable %1$spreloading%2$s to rebuild them automatically.', 'noobrocket' ), '<a href="#preload">', '</a>' ),
					'help'        => [
						'url' => $cache_life_beacon['url'],
						'id'  => $this->beacon->get_suggest( 'cache_lifespan_section' ),
					],
					'page'        => 'advanced_cache',
					'helper'      => '<details class="noobrocket-field-disclosure noobrocket-cache-lifespan-guidance"><summary>' . esc_html__( 'Cache guidance and requirements', 'noobrocket' ) . '</summary><p>' .
						// translators: %1$s = opening <a> tag, %2$s = closing </a> tag.
						sprintf( __( 'Reduce lifespan to 10 hours or less if you notice issues that seem to appear periodically. %1$sWhy?%2$s', 'noobrocket' ), '<a href="' . esc_url( $nonce_beacon['url'] ) . '" data-beacon-article="' . esc_attr( $nonce_beacon['id'] ) . '" target="_blank" rel="noopener noreferrer">', '</a>' ) . '</p><p>' .
						esc_html__( 'Background refresh requires automatic preloading, a nonzero cache lifespan and legacy WebP Compatibility off. Mandatory or dynamic cache cookies prevent it. Nginx must send page-cache requests through PHP.', 'noobrocket' ) . '</p></details>',
				],
				'cache_exclusions' => [
					'title'       => __( 'Exclusions and purge rules', 'noobrocket' ),
					'disclosure'  => true,
					'description' => $reject_uri_desc,
					'help'        => $never_cache_beacon,
					'page'        => 'advanced_cache',
				],
			]
		);

		$this->settings->add_settings_fields(
			[
				'purge_cron_interval'  => [
					'type'              => 'cache_lifespan',
					'label'             => __( 'Clear cache after', 'noobrocket' ),
					'description'       => __( 'Set 0 for an unlimited lifespan.', 'noobrocket' ),
					'section'           => 'cache_lifespan',
					'page'              => 'advanced_cache',
					'default'           => 10,
					'sanitize_callback' => 'sanitize_cache_lifespan',
					'choices'           => [
						'HOUR_IN_SECONDS' => __( 'Hours', 'noobrocket' ),
						'DAY_IN_SECONDS'  => __( 'Days', 'noobrocket' ),
					],
				],
				'cache_stale_refresh' => [
					'type' => 'checkbox',
					'label' => __( 'Refresh expired pages in the background', 'noobrocket' ),
					'description' => __( 'Public desktop and mobile pages can remain available for up to one extra hour while refreshing. Content changes still purge immediately.', 'noobrocket' ),
					'helper' => $stale_refresh_helper,
					'section' => 'cache_lifespan', 'page' => 'advanced_cache', 'default' => 0, 'sanitize_callback' => 'sanitize_checkbox',
				],
				'cache_reject_uri'     => [
					'type'              => 'textarea',
					'label'             => __( 'Never Cache URL(s)', 'noobrocket' ),
					'description'       => __( 'Specify URLs of pages or posts that should never be cached (one per line)', 'noobrocket' ),
					'helper'            => __( 'The domain part of the URL will be stripped automatically.<br>Use (.*) wildcards to address multiple URLs under a given path.', 'noobrocket' ),
					'placeholder'       => '/example/(.*)',
					'section'           => 'cache_exclusions',
					'page'              => 'advanced_cache',
					'default'           => [],
					'sanitize_callback' => 'sanitize_textarea',
				],
				'cache_reject_cookies' => [
					'disclosure'        => __( 'Cookie exclusions', 'noobrocket' ),
					'type'              => 'textarea',
					'label'             => __( 'Never Cache Cookies', 'noobrocket' ),
					'description'       => __( 'Specify full or partial IDs of cookies that, when set in the visitor\'s browser, should prevent a page from getting cached (one per line)', 'noobrocket' ),
					'section'           => 'cache_exclusions',
					'page'              => 'advanced_cache',
					'default'           => [],
					'sanitize_callback' => 'sanitize_textarea',
				],
				'cache_reject_ua'      => [
					'disclosure'        => __( 'Visitor browser exclusions', 'noobrocket' ),
					'type'              => 'textarea',
					'label'             => __( 'Never Cache User Agent(s)', 'noobrocket' ),
					'description'       => __( 'Specify user agent strings that should never see cached pages (one per line)', 'noobrocket' ),
					'helper'            => __( 'Use (.*) wildcards to detect parts of UA strings.', 'noobrocket' ),
					'placeholder'       => '(.*)Mobile(.*)Safari(.*)',
					'section'           => 'cache_exclusions',
					'page'              => 'advanced_cache',
					'default'           => [],
					'sanitize_callback' => 'sanitize_textarea',
				],
				'cache_purge_pages'    => [
					'disclosure'        => __( 'Additional pages to purge', 'noobrocket' ),
					'type'              => 'textarea',
					'label'             => __( 'Always Purge URL(s)', 'noobrocket' ),
					'description'       => __( 'Specify URLs you always want purged from cache whenever you update any post or page (one per line)', 'noobrocket' ),
					'helper'            => __( 'The domain part of the URL will be stripped automatically.<br>Use (.*) wildcards to address multiple URLs under a given path.', 'noobrocket' ),
					'section'           => 'cache_exclusions',
					'page'              => 'advanced_cache',
					'default'           => [],
					'sanitize_callback' => 'sanitize_textarea',
				],
				'cache_query_strings'  => [
					'disclosure'        => __( 'Cache URL parameters', 'noobrocket' ),
					'type'              => 'textarea',
					'label'             => __( 'Cache Query String(s)', 'noobrocket' ),
					'description'       => __( 'Specify query strings for caching (one per line)', 'noobrocket' ),
					'section'           => 'cache_exclusions',
					'page'              => 'advanced_cache',
					'default'           => [],
					'sanitize_callback' => 'sanitize_textarea',
				],
			]
		);
	}

	/**
	 * Registers Database section.
	 *
	 * @since 3.0
	 */
	private function database_section() {
		$total = [];

		foreach ( array_keys( $this->optimize->get_options() ) as $key ) {
			$total[ $key ] = $this->optimize->count_cleanup_items( $key );
		}

		$this->settings->add_page_section(
			'database',
			[
				'title'            => __( 'Database', 'noobrocket' ),
				'menu_description' => __( 'Optimize, reduce bloat', 'noobrocket' ),
			]
		);

		$this->settings->add_settings_sections(
			[
				'post_cleanup_section'       => [
					'title'       => __( 'Cleanup selection', 'noobrocket' ),
					'type'        => 'fields_container',
					'description' => __( 'Selected revisions, drafts, trashed posts and comments are permanently deleted. Back up your database first.', 'noobrocket' ),
					'help'        => $this->beacon->get_suggest( 'db_optimization' ),
					'page'        => 'database',
				],
				'schedule_cleanup_section'   => [
					'title' => __( 'Scheduled cleanup', 'noobrocket' ),
					'description' => __( 'After saving, the selected cleanup categories run at this frequency. Immediate cleanup uses its own confirmation.', 'noobrocket' ),
					'type'  => 'fields_container',
					'page'  => 'database',
				],
			]
		);

		$this->settings->add_settings_fields(
			[
				'database_revisions'          => [
					'type'              => 'checkbox',
					'label'             => __( 'Revisions', 'noobrocket' ),
					// translators: %s is the number of revisions found in the database. It's a formatted number, don't use %d.
					'count_label'       => sprintf( _n( '%s revision', '%s revisions', $total['database_revisions'], 'noobrocket' ), number_format_i18n( $total['database_revisions'] ) ),
					'section'           => 'post_cleanup_section',
					'page'              => 'database',
					'default'           => 0,
					'sanitize_callback' => 'sanitize_checkbox',
				],
				'database_auto_drafts'        => [
					'type'              => 'checkbox',
					'label'             => __( 'Auto Drafts', 'noobrocket' ),
					// translators: %s is the number of revisions found in the database. It's a formatted number, don't use %d.
					'count_label'       => sprintf( _n( '%s draft', '%s drafts', $total['database_auto_drafts'], 'noobrocket' ), number_format_i18n( $total['database_auto_drafts'] ) ),
					'section'           => 'post_cleanup_section',
					'page'              => 'database',
					'default'           => 0,
					'sanitize_callback' => 'sanitize_checkbox',
				],
				'database_trashed_posts'      => [
					'type'              => 'checkbox',
					'label'             => __( 'Trashed Posts', 'noobrocket' ),
					// translators: %s is the number of revisions found in the database. It's a formatted number, don't use %d.
					'count_label'       => sprintf( _n( '%s post', '%s posts', $total['database_trashed_posts'], 'noobrocket' ), number_format_i18n( $total['database_trashed_posts'] ) ),
					'section'           => 'post_cleanup_section',
					'page'              => 'database',
					'default'           => 0,
					'sanitize_callback' => 'sanitize_checkbox',
				],
				'database_spam_comments'      => [
					'type'              => 'checkbox',
					'label'             => __( 'Spam Comments', 'noobrocket' ),
					// translators: %s is the number of revisions found in the database. It's a formatted number, don't use %d.
					'count_label'       => sprintf( _n( '%s comment', '%s comments', $total['database_spam_comments'], 'noobrocket' ), number_format_i18n( $total['database_spam_comments'] ) ),
					'section'           => 'post_cleanup_section',
					'page'              => 'database',
					'default'           => 0,
					'sanitize_callback' => 'sanitize_checkbox',
				],
				'database_trashed_comments'   => [
					'type'              => 'checkbox',
					'label'             => __( 'Trashed Comments', 'noobrocket' ),
					// translators: %s is the number of revisions found in the database. It's a formatted number, don't use %d.
					'count_label'       => sprintf( _n( '%s comment', '%s comments', $total['database_trashed_comments'], 'noobrocket' ), number_format_i18n( $total['database_trashed_comments'] ) ),
					'section'           => 'post_cleanup_section',
					'page'              => 'database',
					'default'           => 0,
					'sanitize_callback' => 'sanitize_checkbox',
				],
				'database_all_transients'     => [
					'type'              => 'checkbox',
					'label'             => __( 'All transients', 'noobrocket' ),
					// translators: %s is the number of revisions found in the database. It's a formatted number, don't use %d.
					'count_label'       => sprintf( _n( '%s transient', '%s transients', $total['database_all_transients'], 'noobrocket' ), number_format_i18n( $total['database_all_transients'] ) ),
					'description'       => __( 'Temporary values are regenerated when your plugins need them.', 'noobrocket' ),
					'section'           => 'post_cleanup_section',
					'page'              => 'database',
					'default'           => 0,
					'sanitize_callback' => 'sanitize_checkbox',
				],
				'database_optimize_tables'    => [
					'type'              => 'checkbox',
					'label'             => __( 'Optimize Tables', 'noobrocket' ),
					// translators: %s is the number of revisions found in the database. It's a formatted number, don't use %d.
					'count_label'       => sprintf( _n( '%s table', '%s tables', $total['database_optimize_tables'], 'noobrocket' ), number_format_i18n( $total['database_optimize_tables'] ) ),
					'description'       => __( 'Reduce unused space in database tables.', 'noobrocket' ),
					'section'           => 'post_cleanup_section',
					'page'              => 'database',
					'default'           => 0,
					'sanitize_callback' => 'sanitize_checkbox',
				],
				'schedule_automatic_cleanup'  => [
					'container_class'   => [
						'noobrocket-isParent',
					],
					'type'              => 'checkbox',
					'label'             => __( 'Schedule Automatic Cleanup', 'noobrocket' ),
					'description'       => '',
					'section'           => 'schedule_cleanup_section',
					'page'              => 'database',
					'default'           => 0,
					'sanitize_callback' => 'sanitize_checkbox',
				],
				'automatic_cleanup_frequency' => [
					'container_class'   => [
						'noobrocket-field--children',
					],
					'type'              => 'select',
					'label'             => __( 'Frequency', 'noobrocket' ),
					'description'       => '',
					'parent'            => 'schedule_automatic_cleanup',
					'section'           => 'schedule_cleanup_section',
					'page'              => 'database',
					'default'           => 'daily',
					'sanitize_callback' => 'sanitize_text_field',
					'choices'           => [
						'daily'   => __( 'Daily', 'noobrocket' ),
						'weekly'  => __( 'Weekly', 'noobrocket' ),
						'monthly' => __( 'Monthly', 'noobrocket' ),
					],
				],
			]
		);
	}

	/**
	 * Registers CDN section
	 *
	 * @since 3.0
	 */
	private function cdn_section() {
		$cdn_beacon = $this->beacon->get_suggest( 'own_cdn' );

		$this->settings->add_page_section(
			'page_cdn',
			[
				'title'            => __( 'Content Delivery', 'noobrocket' ),
				'menu_description' => __( 'Configure your own CDN', 'noobrocket' ),
			]
		);

		$this->settings->add_settings_sections(
			[
				'cdn_section' => [
					'title'       => __( 'CDN connection', 'noobrocket' ),
					'type'        => 'fields_container',
					'class'       => [ 'noobrocket-cdn-settings' ],
					'help'        => $cdn_beacon,
					'page'        => 'page_cdn',
				],
			]
		);

		$this->settings->add_settings_fields(
			apply_filters(
				'noobrocket_cdn_settings_fields',
				[
					'cdn'              => [
						'type'              => 'checkbox',
						'label'             => __( 'Enable CDN rewriting', 'noobrocket' ),
						'section'           => 'cdn_section',
						'page'              => 'page_cdn',
						'default'           => 0,
						'sanitize_callback' => 'sanitize_checkbox',
					],
					'cdn_cnames'       => [
						'type'        => 'cnames',
						'label'       => __( 'CDN hostnames', 'noobrocket' ),
						'description' => __( 'Enter a hostname or an HTTP(S) URL supplied by your CDN provider.', 'noobrocket' ),
						'default'     => [],
						'section'     => 'cdn_section',
						'page'        => 'page_cdn',
					],
					'cdn_reject_files' => [
						'type'              => 'textarea',
						'disclosure'        => __( 'Excluded files', 'noobrocket' ),
						'label'             => __( 'Excluded files', 'noobrocket' ),
						'description'       => __( 'Specify file URLs to exclude from CDN rewriting, one per line.', 'noobrocket' ),
						'default'           => [],
						'section'           => 'cdn_section',
						'page'              => 'page_cdn',
						'sanitize_callback' => 'sanitize_textarea',
					],
				]
			)
		);
	}

	/**
	 * Registers Heartbeat section.
	 *
	 * @since  3.2
	 */
	private function heartbeat_section() {
		$heartbeat_beacon = $this->beacon->get_suggest( 'heartbeat_settings' );

		$this->settings->add_page_section(
			'heartbeat',
			[
				'title'            => __( 'Heartbeat', 'noobrocket' ),
				'menu_description' => __( 'Control WordPress Heartbeat API', 'noobrocket' ),
			]
		);

		$this->settings->add_settings_sections(
			[
				'heartbeat_section'  => [
					'title'       => __( 'Background activity', 'noobrocket' ),
					'description' => __( 'Reduce activity limits Heartbeat to one request every two minutes. Disable stops it in the selected context.', 'noobrocket' ),
					'type'        => 'fields_container',
					'page'        => 'heartbeat',
					'help'        => $heartbeat_beacon,
				],
			]
		);

		$fields_default = [
			'type'              => 'select',
			'page'              => 'heartbeat',
			'section'           => 'heartbeat_section',
			'parent'            => 'control_heartbeat',
			'container_class'   => [ 'noobrocket-field--children' ],
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'reduce_periodicity',
			'choices'           => [
				''                   => __( 'Do not limit', 'noobrocket' ),
				'reduce_periodicity' => __( 'Reduce activity', 'noobrocket' ),
				'disable'            => __( 'Disable', 'noobrocket' ),
			],
		];

		$this->settings->add_settings_fields(
			[
				'control_heartbeat'         => [
					'type'              => 'checkbox',
					'label'             => __( 'Control Heartbeat', 'noobrocket' ),
					'description'       => __( 'Apply the choices below. Turning this off keeps your choices for later.', 'noobrocket' ),
					'container_class'   => [ 'noobrocket-isParent' ],
					'page'              => 'heartbeat',
					'section'           => 'heartbeat_section',
					'sanitize_callback' => 'sanitize_checkbox',
					'default'           => 0,
				],
				'heartbeat_admin_behavior'  => array_merge(
					$fields_default,
					[
						'label'       => __( 'WordPress admin', 'noobrocket' ),
						'description' => __( 'Disabling stops session notices and admin features that use Heartbeat.', 'noobrocket' ),
					]
				),
				'heartbeat_editor_behavior' => array_merge(
					$fields_default,
					[
						'label' => __( 'Post editor', 'noobrocket' ),
						'description' => __( 'Disabling stops post-lock refresh, session notices and classic-editor autosave. Keep enabled when editing together.', 'noobrocket' ),
					]
				),
				'heartbeat_site_behavior'   => array_merge(
					$fields_default,
					[
						'label' => __( 'Public site', 'noobrocket' ),
						'description' => __( 'Disabling can affect themes or plugins that use Heartbeat on visitor pages.', 'noobrocket' ),
					]
				),
			]
		);
	}

	/**
	 * Registers Add-ons section.
	 *
	 * @since 3.0
	 */
	private function addons_section() {
		$webp_beacon       = $this->beacon->get_suggest( 'webp' );
		$user_cache_beacon = $this->beacon->get_suggest( 'user_cache' );

		$this->settings->add_page_section(
			'addons',
			[
				'title'            => __( 'Integrations', 'noobrocket' ),
				'menu_description' => __( 'Connect existing services', 'noobrocket' ),
			]
		);

		$this->settings->add_settings_sections(
			[
				'one_click' => [
					'title'       => __( 'Optional integrations', 'noobrocket' ),
					'description' => __( 'Choose integrations for services already used by your site, then save settings.', 'noobrocket' ),
					'type'        => 'addons_container',
					'page'        => 'addons',
				],
			]
		);

		$this->settings->add_settings_sections(
			[
				'addons' => [
					'title'       => __( 'Available integrations', 'noobrocket' ),
					'description' => __( 'Configure the integrations used by your site, then save settings.', 'noobrocket' ),
					'type'        => 'addons_container',
					'page'        => 'addons',
				],
			]
		);

		$this->settings->add_settings_fields(
			[
				'cache_logged_user' => [
					'type'              => 'one_click_addon',
					'label'             => __( 'User Cache', 'noobrocket' ),
					'logo'              => [
						'url'    => NOOBROCKET_ASSETS_IMG_URL . 'icon-user-cache.svg',
						'width'  => 152,
						'height' => 135,
					],
					'title'             => __( 'Signed-in requests bypass early page-cache delivery.', 'noobrocket' ),
					'input_attr'        => [ 'disabled' => 1 ],
					// translators: %1$s = opening <a> tag, %2$s = closing </a> tag.
					'description'       => sprintf( __( 'User Cache is temporarily unavailable while native session validation is completed. Public caching remains available. %1$sUser cache guidance%2$s', 'noobrocket' ), '<a href="' . esc_url( $user_cache_beacon['url'] ) . '" data-beacon-article="' . esc_attr( $user_cache_beacon['id'] ) . '" target="_blank" rel="noopener noreferrer">', '</a>' ),
					'section'           => 'one_click',
					'page'              => 'addons',
					'settings_page'     => 'user_cache',
					'default'           => 0,
					'sanitize_callback' => 'sanitize_checkbox',
				],
			]
		);

		$default_cf_settings = [
			'do_cloudflare' => [
				'type'              => 'noobrocket_addon',
				'label'             => __( 'Cloudflare', 'noobrocket' ),
				'logo'              => [
					'url'    => noobrocket_get_constant( 'NOOBROCKET_ASSETS_IMG_URL', '' ) . 'logo-cloudflare2.svg',
					'width'  => 153,
					'height' => 51,
				],
				'description'       => __( 'Provide your account email, global API key, and Zone ID to clear the Cloudflare cache or change its zone settings.', 'noobrocket' ),
				'helper'            => sprintf(
				// translators: %1$s = opening span tag, %2$s = closing span tag.
				__( '%1$sPlanning on using Automatic Platform Optimization (APO)?%2$s Just activate the official Cloudflare plugin and configure it. Cache-Cache will automatically enable compatibility.', 'noobrocket' ),
					'<span class="noobrocket-helper-title">',
					'</span>'
				),
				'section'           => 'addons',
				'page'              => 'addons',
				'settings_page'     => 'cloudflare',
				'default'           => 0,
				'sanitize_callback' => 'sanitize_checkbox',
			],
		];

		/**
		 * Filters the Cloudflare Addon field values
		 *
		 * @since 3.14
		 *
		 * @param array $cf_settings Array of values to populate the field.
		 */
		$cf_settings = (array) apply_filters( 'noobrocket_cloudflare_field_settings', $default_cf_settings );
		$cf_settings = wp_parse_args( $cf_settings, $default_cf_settings );

		$this->settings->add_settings_fields( $cf_settings );

		/**
		 * Allow to display the "Varnish" tab in the settings page
		 *
		 * @since 2.7
		 *
		 * @param bool $display true will display the "Varnish" tab.
		*/
		if ( apply_filters( 'noobrocket_display_varnish_options_tab', true ) ) {
			$varnish_beacon = $this->beacon->get_suggest( 'varnish' );

			$this->settings->add_settings_fields(
				/**
				 * Filters the Varnish field settings data
				 *
				 * @since 3.0
				 * @author Remy Perona
				 *
				 * @param array $settings Field settings data.
				 */
				apply_filters(
					'noobrocket_varnish_field_settings',
					[
						'varnish_auto_purge' => [
							'type'              => 'one_click_addon',
							'label'             => __( 'Varnish', 'noobrocket' ),
							'logo'              => [
								'url'    => NOOBROCKET_ASSETS_IMG_URL . 'logo-varnish.svg',
								'width'  => 152,
								'height' => 135,
							],
							'title'             => __( 'If your server uses Varnish, clear its cache when Cache-Cache clears the page cache.', 'noobrocket' ),
							// translators: %1$s = opening <a> tag, %2$s = closing </a> tag.
							'description'       => sprintf( __( '%1$sLearn more%2$s', 'noobrocket' ), '<a href="' . esc_url( $varnish_beacon['url'] ) . '" data-beacon-article="' . esc_attr( $varnish_beacon['id'] ) . '" target="_blank" rel="noopener noreferrer">', '</a>' ),
							'section'           => 'one_click',
							'page'              => 'addons',
							'settings_page'     => 'varnish',
							'default'           => 0,
							'sanitize_callback' => 'sanitize_checkbox',
						],
					]
				)
			);
		}

		$webp_beacon = $this->beacon->get_suggest( 'webp' );


		$this->settings->add_settings_fields(
			[
				'cache_webp' =>
				/**
				 * Add more content to the 'cache_webp' setting field.
				 *
				 * @since  3.10 moved to add-on section
				 * @since  3.4
				 *
				 * @param array $cache_webp_field Data to be added to the setting field.
				 */
					apply_filters(
						'noobrocket_cache_webp_setting_field',
						[
							'type'              => 'one_click_addon',
							'label'             => __( 'WebP Compatibility', 'noobrocket' ),
							'logo'              => [
								'url'    => NOOBROCKET_ASSETS_IMG_URL . 'logo-webp.svg',
								'width'  => 152,
								'height' => 135,
							],
							'title'             => __( 'Improve browser compatibility for WebP images.', 'noobrocket' ),
							// translators: %1$s = opening <a> tag, %2$s = closing </a> tag.
							'description'       => sprintf(
							// translators: %1$s = opening <a> tag, %2$s = closing </a> tag.
								__( 'Enable modern image delivery in Media, then queue your existing library from Overview. This legacy compatibility option requires images generated by another tool. %1$sMore info%2$s', 'noobrocket' ),
								'<a href="' . esc_url( $webp_beacon['url'] ) . '" data-beacon-article="' . esc_attr( $webp_beacon['id'] ) . '" target="_blank" rel="noopener noreferrer">',
								'</a>'
							),
							'section'           => 'one_click',
							'page'              => 'addons',
							'settings_page'     => 'webp',
							'default'           => 0,
							'sanitize_callback' => 'sanitize_checkbox',
							'container_class'   => [
								'noobrocket-webp-addon',
							],
						]
						),
			]
		);

		if ( defined( 'NOOBROCKET_SUCURI_API_KEY_HIDDEN' ) && NOOBROCKET_SUCURI_API_KEY_HIDDEN ) {
			// No need to display the dedicated tab if there is nothing to display on it.
			$description   = __( 'Clear the Sucuri cache when Cache-Cache’s cache is cleared.', 'noobrocket' );
			$settings_page = false;
		} else {
			$description   = __( 'Provide your API key to clear the Sucuri cache when Cache-Cache’s cache is cleared.', 'noobrocket' );
			$settings_page = 'sucuri';
		}

		$this->settings->add_settings_fields(
			[
				'sucury_waf_cache_sync' => [
					'type'              => 'noobrocket_addon',
					'label'             => __( 'Sucuri', 'noobrocket' ),
					'logo'              => [
						'url'    => NOOBROCKET_ASSETS_IMG_URL . 'logo-sucuri.png',
						'width'  => 152,
						'height' => 56,
					],
					'description'       => $description,
					'section'           => 'addons',
					'page'              => 'addons',
					'settings_page'     => $settings_page,
					'default'           => 0,
					'sanitize_callback' => 'sanitize_checkbox',
				],
			]
		);
	}

	/**
	 * Registers Cloudflare section.
	 *
	 * @since 3.0
	 */
	private function cloudflare_section() {
		$this->settings->add_page_section(
			'cloudflare',
			[
				'title'            => __( 'Cloudflare', 'noobrocket' ),
				'menu_description' => '',
				'class'            => [
					'noobrocket-subMenuItem',
					'noobrocket-addonSubMenuItem',
				],
			]
		);

		$beacon_cf_credentials     = $this->beacon->get_suggest( 'cloudflare_credentials' );
		$beacon_cf_settings        = $this->beacon->get_suggest( 'cloudflare_settings' );
		$beacon_cf_credentials_api = $this->beacon->get_suggest( 'cloudflare_credentials_api' );

		$this->settings->add_settings_sections(
			[
				'cloudflare_credentials' => [
					'type'  => 'fields_container',
					'title' => __( 'Cloudflare credentials', 'noobrocket' ),
					'help'  => [
						'id'  => $beacon_cf_credentials['id'],
						'url' => $beacon_cf_credentials['url'],
					],
					'page'  => 'cloudflare',
				],
				'cloudflare_settings'    => [
					'disclosure' => true,
					'type'  => 'fields_container',
					'title' => __( 'Cloudflare settings', 'noobrocket' ),
					'help'  => [
						'id'  => $beacon_cf_settings['id'],
						'url' => $beacon_cf_settings['url'],
					],
					'page'  => 'cloudflare',
				],
			]
		);

		if ( ! defined( 'NOOBROCKET_CF_API_KEY_HIDDEN' ) || ! NOOBROCKET_CF_API_KEY_HIDDEN ) {
			$this->settings->add_settings_fields(
				[
					'cloudflare_api_key_mask' => [
						'label'       => _x( 'Global API key', 'Cloudflare', 'noobrocket' ),
						'secret'      => true,
						'description' => sprintf( '<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>', esc_url( $beacon_cf_credentials_api['url'] ), _x( 'Find your API key', 'Cloudflare', 'noobrocket' ) ),
						'default'     => '',
						'section'     => 'cloudflare_credentials',
						'page'        => 'cloudflare',
					],
				]
			);
		}

		$this->settings->add_settings_fields(
			[
				'cloudflare_email'            => [
					'label'           => _x( 'Account email', 'Cloudflare', 'noobrocket' ),
					'default'         => '',
					'container_class' => [
						'noobrocket-field--split',
					],
					'section'         => 'cloudflare_credentials',
					'page'            => 'cloudflare',
				],
				'cloudflare_zone_id_mask'     => [
					'label'           => _x( 'Zone ID', 'Cloudflare', 'noobrocket' ),
					'description'     => sprintf( '<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>', 'https://developers.cloudflare.com/fundamentals/account/find-account-and-zone-ids/', _x( 'Find your Zone ID', 'Cloudflare', 'noobrocket' ) ),
					'default'         => '',
					'container_class' => [
						'noobrocket-field--split',
					],
					'section'         => 'cloudflare_credentials',
					'page'            => 'cloudflare',
				],
				'cloudflare_devmode'          => [
					'type'              => 'sliding_checkbox',
					'label'             => __( 'Development mode', 'noobrocket' ),
					// translators: %1$s = link opening tag, %2$s = link closing tag.
					'description'       => sprintf( __( 'Temporarily activate development mode on your website. This setting will automatically turn off after 3 hours. %1$sLearn more%2$s', 'noobrocket' ), '<a href="https://support.cloudflare.com/hc/en-us/articles/200168246" target="_blank">', '</a>' ),
					'default'           => 0,
					'section'           => 'cloudflare_settings',
					'page'              => 'cloudflare',
					'sanitize_callback' => 'sanitize_checkbox',
				],
				'cloudflare_auto_settings'    => [
					'type'              => 'sliding_checkbox',
					'label'             => __( 'Optimal settings', 'noobrocket' ),
					'description'       => __( 'On save, changes this Cloudflare zone: cache level to Aggressive, Rocket Loader off, and browser cache TTL to one year. Turning this off attempts to restore the previously saved zone values.', 'noobrocket' ),
					'default'           => 0,
					'section'           => 'cloudflare_settings',
					'page'              => 'cloudflare',
					'sanitize_callback' => 'sanitize_checkbox',
				],
				'cloudflare_protocol_rewrite' => [
					'type'              => 'sliding_checkbox',
					'label'             => __( 'Relative protocol', 'noobrocket' ),
					'description'       => __( 'Should only be used with Cloudflare\'s flexible SSL feature. URLs of static files (CSS, JS, images) will be rewritten to use // instead of http:// or https://.', 'noobrocket' ),
					'default'           => 0,
					'section'           => 'cloudflare_settings',
					'page'              => 'cloudflare',
					'sanitize_callback' => 'sanitize_checkbox',
				],
			]
		);
	}

	/**
	 * Registers Sucuri cache section.
	 *
	 * @since  3.2
	 */
	private function sucuri_section() {
		if ( defined( 'NOOBROCKET_SUCURI_API_KEY_HIDDEN' ) && NOOBROCKET_SUCURI_API_KEY_HIDDEN ) {
			return;
		}

		$sucuri_beacon = $this->beacon->get_suggest( 'sucuri_credentials' );

		$this->settings->add_page_section(
			'sucuri',
			[
				'title'            => __( 'Sucuri', 'noobrocket' ),
				'menu_description' => '',
				'class'            => [
					'noobrocket-subMenuItem',
					'noobrocket-addonSubMenuItem',
				],
			]
		);

		$this->settings->add_settings_sections(
			[
				'sucuri_credentials' => [
					'type'  => 'fields_container',
					'title' => __( 'Sucuri credentials', 'noobrocket' ),
					'page'  => 'sucuri',
					'help'  => [
						'id'  => $sucuri_beacon['id'],
						'url' => $sucuri_beacon['url'],
					],
				],
			]
		);

		$this->settings->add_settings_fields(
			[
				'sucury_waf_api_key' => [
					'label'       => __( 'Firewall API key', 'noobrocket' ),
					'secret'      => true,
					'description' => esc_html__( 'Use the plugin API key: 32 characters, a slash, then 32 characters.', 'noobrocket' ) . ' ' . sprintf( '<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>', 'https://kb.sucuri.net/firewall/Performance/clearing-cache', _x( 'Find your API key', 'Sucuri', 'noobrocket' ) ),
					'default'     => '',
					'section'     => 'sucuri_credentials',
					'page'        => 'sucuri',
				],
			]
		);
	}

	/**
	 * Sets hidden fields.
	 *
	 * @since 3.0
	 */
	private function hidden_fields() {

		$hidden_fields = [
			'secret_cache_key',
			'minify_css_key',
			'minify_js_key',
			'version',
			'engine_version',
			'previous_version',
			'cloudflare_old_settings',
			'cache_ssl',
			'minify_google_fonts',
			'emoji',
			'cache_mobile',
			'do_caching_mobile_files',
			'minify_concatenate_css',
			'cloudflare_api_key',
			'cloudflare_zone_id',
			'dns_prefetch',
		];

		$this->settings->add_hidden_settings_fields(
			/**
			 * Filters the hidden settings fields
			 *
			 * @since 3.5
			 * @author Remy Perona
			 *
			 * @param array $hidden_settings_fields An array of hidden settings fields ID
			 */
			apply_filters(
				'noobrocket_hidden_settings_fields',
				$hidden_fields
			)
		);
	}

	/**
	 * Sanitize and format a list.
	 *
	 * @since 3.5.5
	 *
	 * @param  array  $list     A list of strings.
	 * @param  string $tag_name Name of the HTML tag that will wrap each element of the list.
	 * @return array
	 */
	private function sanitize_and_format_list( array $list, $tag_name = 'strong' ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.listFound
		if ( empty( $list ) ) {
			return [];
		}

		$list = array_filter( $list );

		if ( empty( $list ) ) {
			return [];
		}

		$list = array_unique( $list );

		if ( empty( $tag_name ) ) {
			return $list;
		}

		$format = "<$tag_name>%s</$tag_name>";

		return array_map( 'sprintf', array_fill( 0, count( $list ), $format ), $list );
	}

	/**
	 * Checks if combine JS option should be disabled
	 *
	 * @since 3.9
	 *
	 * @return bool
	 */
	private function disable_combine_js(): bool {
		if ( (bool) get_noobrocket_option( 'delay_js', 0 ) ) {
			return true;
		}

		return ! (bool) get_noobrocket_option( 'minify_js', 0 );
	}

	/**
	 * Render radio options sub fields.
	 *
	 * @since 3.10
	 *
	 * @param array $sub_fields    Array of fields to display.
	 */
	public function display_radio_options_sub_fields( $sub_fields ) {
		$sub_fields = $this->settings->set_radio_buttons_sub_fields_value( $sub_fields );
		$this->render->render_fields( $sub_fields );
	}

	/**
	 * Render mobile cache option.
	 *
	 * @return void
	 */
	public function display_mobile_cache_option(): void {
		if ( (bool) $this->options->get( 'cache_mobile', 0 ) ) {
			return;
		}

		$data = $this->beacon->get_suggest( 'mobile_cache' );
		echo $this->generate( 'settings/mobile-cache', $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view.
	}

	/**
	 * Callback method for the AJAX request to mobile cache.
	 *
	 * @return void
	 */
	public function enable_mobile_cache(): void {
		check_ajax_referer( 'noobrocket-ajax', 'nonce', true );

		if ( ! current_user_can( 'noobrocket_manage_options' ) ) {
			wp_send_json_error();
			return; // @phpstan-ignore-line
		}

		$this->options->set( 'cache_mobile', 1 );
		$this->options->set( 'do_caching_mobile_files', 1 );
		update_option( noobrocket_get_constant( 'NOOBROCKET_SLUG', 'noobrocket_settings' ), $this->options->get_options() );

		wp_send_json_success();
	}

	/**
	 * Enable Separate cache files option on upgrade.
	 *
	 * @return void
	 */
	public function enable_separate_cache_files_mobile(): void {
		if ( ! (bool) $this->options->get( 'cache_mobile', 0 ) ) {
			return;
		}

		if ( (bool) $this->options->get( 'do_caching_mobile_files', 0 ) ) {
			return;
		}

		$this->options->set( 'do_caching_mobile_files', 1 );
		update_option( noobrocket_get_constant( 'NOOBROCKET_SLUG', 'noobrocket_settings' ), $this->options->get_options() );
	}
}
