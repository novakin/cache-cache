<?php
namespace NoobRocket\Engine\Admin\Settings;

use stdClass;
use NoobRocket\Abstract_Render;
use NoobRocket\Dependencies\WPMedia\PluginFamily\Model\PluginFamily;

defined( 'ABSPATH' ) || exit;

/**
 * Handle rendering of HTML content for the settings page.
 *
 * @since 3.5.5 Moves into the new architecture.
 * @since 3.0
 */
class Render extends Abstract_Render {
	/**
	 * Settings array.
	 *
	 * @since 3.0
	 *
	 * @var array
	 */
	private $settings = [];

	/**
	 * Hidden settings array.
	 *
	 * @since 3.0
	 *
	 * @var array
	 */
	private $hidden_settings;

	/**
	 * Plugin family
	 *
	 * @var PluginFamily
	 *
	 * @since 3.17.2
	 */
	protected $plugin_family;

	/**
	 * Creates an instance of the object.
	 *
	 * @param string       $template_path Template path.
	 * @param PluginFamily $plugin_family Plugin Family Instance.
	 */
	public function __construct( string $template_path, PluginFamily $plugin_family ) {
		parent::__construct( $template_path );
		$this->plugin_family = $plugin_family;
	}

	/**
	 * Sets the settings value.
	 *
	 * @since 3.0
	 *
	 * @param array $settings Array of settings.
	 * @return void
	 */
	public function set_settings( $settings ) {
		$this->settings = (array) $settings;
		/**
		 * Makes registered labels and sections available to other settings views.
		 *
		 * @param array $settings Registered settings and field metadata.
		 */
		do_action( 'noobrocket_settings_registered', $this->settings );
	}

	/**
	 * Sets the hidden settings value.
	 *
	 * @since 3.0
	 *
	 * @param array $hidden_settings Array of hidden settings.
	 */
	public function set_hidden_settings( $hidden_settings ) {
		$this->hidden_settings = $hidden_settings;
	}

	/**
	 * Renders the page sections navigation.
	 *
	 * @since 3.0
	 */
	public function render_navigation() {
		/**
		 * Filters NoobRocket settings page navigation items.
		 *
		 * @since 3.0
		 *
		 * @param array $navigation {
		 *     Items to populate the navigation.
		 *
		 *     @type string $id               Page section identifier.
		 *     @type string $title            Menu item title.
		 *     @type string $menu_description Menu item description.
		 *     @type string $class            Menu item classes
		 * }
		 */
		$navigation = (array) apply_filters( 'noobrocket_settings_menu_navigation', $this->settings );

		$default = [
			'id'               => '',
			'title'            => '',
			'menu_description' => '',
			'class'            => '',
		];

		$navigation = array_map(
			function ( array $item ) use ( $default ) {
				$item = wp_parse_args( $item, $default );

				if ( ! empty( $item['class'] ) ) {
					$item['class'] = implode( ' ', array_map( 'sanitize_html_class', $item['class'] ) );
				}

				unset( $item['sections'] );
				return $item;
			},
			$navigation
		);

		echo $this->generate( 'navigation', $navigation ); // phpcs:ignore WordPress.Security.EscapeOutput -- Dynamic content is properly escaped in the view.
	}

	/**
	 * Render the page sections.
	 *
	 * @since 3.0
	 */
	public function render_form_sections() {
		foreach ( $this->settings as $id => $args ) {
			// Asset operations have their own forms; render them outside options.php's form.
			if ( 'assets' === $id ) {
				continue;
			}
			$default = [
				'title'            => '',
				'menu_description' => '',
				'class'            => '',
			];

			$args = wp_parse_args( $args, $default );
			$id   = str_replace( '_', '-', $id );

			echo $this->generate( 'page-sections/' . $id, $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view.
		}
	}

	/**
	 * Render the Imagify page section.
	 *
	 * @since 3.2
	 */
	public function render_imagify_section() {

		// @phpstan-ignore-next-line
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

		$plugin_data = get_transient( 'noobrocket_imagify_plugin_data' );

		if ( ! $plugin_data ) {

			$query_args = [
				'slug'   => 'imagify',
				'fields' => [
					'icons'                  => true,
					'active_installs'        => true,
					'rating'                 => true,
					'ratings'                => true,
					'short_description'      => false,
					'sections'               => false,
					'last_updated'           => false,
					'added'                  => false,
					'tags'                   => false,
					'homepage'               => false,
					'donate_link'            => false,
					'screenshots'            => false,
					'versions'               => false,
					'banners'                => false,
					'contributors'           => false,
					'requires'               => false,
					'tested'                 => false,
					'requires_php'           => false,
					'support_url'            => false,
					'upgrade_notice'         => false,
					'business_model'         => false,
					'repository_url'         => false,
					'commercial_support_url' => false,
					'preview_link'           => false,
				],
			];

			$plugin_data = plugins_api( 'plugin_information', $query_args );

			if ( is_wp_error( $plugin_data ) ) {
				$plugin_data = [];
			}

			set_transient( 'noobrocket_imagify_plugin_data', $plugin_data, WEEK_IN_SECONDS );
		}

		echo $this->generate( 'page-sections/imagify', $plugin_data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view.
	}

	/**
	 * Render the Tutorials page section.
	 *
	 * @since 3.4
	 */
	public function render_tutorials_section() {
		echo $this->generate( 'page-sections/tutorials' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view.
	}

	/**
	 * Render the tools page section.
	 *
	 * @param array $data Data to pass to the template.
	 *
	 * @since 3.0
	 */
	public function render_tools_section( $data = [] ) {
		echo $this->generate( 'page-sections/tools', $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view.
	}

	/**
	 * Render the plugins page section.
	 *
	 * @since 3.17.2
	 */
	public function render_plugin_section() {
		$plugin_family = $this->plugin_family->get_filtered_plugins( 'noobrocket/noobrocket' );

		$data = $plugin_family['categorized'];

		echo $this->generate( 'page-sections/plugins', $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view.
	}

	/**
	 * Renders the settings sections for a page section.
	 *
	 * @since 3.0
	 *
	 * @param string $page Page section identifier.
	 * @return void
	 */
	public function render_settings_sections( $page ) {
		if ( ! isset( $this->settings[ $page ]['sections'] ) ) {
			return;
		}

		foreach ( $this->settings[ $page ]['sections'] as $args ) {
			$default = [
				'type'        => 'fields_container',
				'title'       => '',
				'description' => '',
				'class'       => '',
				'help'        => '',
				'helper'      => '',
				'page'        => '',
			];

			$args = wp_parse_args( $args, $default );

			if ( ! empty( $args['class'] ) ) {
				$args['class'] = implode( ' ', array_map( 'sanitize_html_class', $args['class'] ) );
			}

			call_user_func_array( [ $this, $args['type'] ], [ $args ] );
		}
	}

	/**
	 * Renders the settings fields for a setting section and page.
	 *
	 * @since 3.0
	 *
	 * @param string $page    Page section identifier.
	 * @param string $section Settings section identifier.
	 * @return void
	 */
	public function render_settings_fields( $page, $section ) {
		if ( ! isset( $this->settings[ $page ]['sections'][ $section ]['fields'] ) ) {
			return;
		}
		$fields = $this->settings[ $page ]['sections'][ $section ]['fields'];
		foreach ( $fields as $id => $field ) {
			$fields[ $id ]['page'] = $page;
			if ( 'textarea' === ( $field['type'] ?? 'text' ) && empty( $field['label'] ) ) {
				$fields[ $id ]['labelledby'] = 'noobrocket-group-' . $section;
			}
		}
		$this->render_fields( $fields );
	}

	/**
	 * Renders hidden fields in the form.
	 *
	 * @since 3.0
	 */
	public function render_hidden_fields() {
		foreach ( $this->hidden_settings as $setting ) {
			call_user_func_array( [ $this, 'hidden' ], [ $setting ] );
		}
	}

	/**
	 * Displays the fields container section template.
	 *
	 * @since 3.0
	 * @author Remy Perona
	 *
	 * @param array $args Array of arguments to populate the template.
	 */
	public function fields_container( $args ) {
		echo $this->generate( 'sections/fields-container', $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view.
	}

	/**
	 * Displays the no container section template.
	 *
	 * @since 3.0
	 *
	 * @param array $args Array of arguments to populate the template.
	 */
	public function nocontainer( $args ) {
		echo $this->generate( 'sections/nocontainer', $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view.
	}

	/**
	 * Displays the add-ons container section template.
	 *
	 * @since 3.0
	 *
	 * @param array $args Array of arguments to populate the template.
	 */
	public function addons_container( $args ) {
		echo $this->generate( 'sections/addons-container', $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view.
	}

	/**
	 * Displays the Your Own CDN section template.
	 *
	 * This is a section for configuring your own CDN settings.
	 * It includes the PAUSE CDN button.
	 *
	 * @param array $args Array of arguments to populate the template.
	 */
	public function your_own_cdn( $args ) {
		echo $this->generate( 'sections/cdn/your-own-cdn', $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view.
	}

	/**
	 * Displays the HostedCDN Free section template.
	 *
	 * @param array $args Array of arguments to populate the template.
	 */
	public function noobrocketcdn_free( $args ) {
		echo $this->generate( 'sections/cdn/noobrocketcdn-free', $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view.
	}

	/**
	 * Displays the HostedCDN Paid section template.
	 *
	 * @param array $args Array of arguments to populate the template.
	 */
	public function noobrocketcdn_paid( $args ) {
		echo $this->generate( 'sections/cdn/noobrocketcdn-paid', $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view.
	}

	/**
	 * Displays the purge CDN cache section template.
	 *
	 * @param array $args Array of arguments to populate the template.
	 */
	public function purge_cdn_cache_section( $args ) {
		echo $this->generate( 'sections/cdn/purge-cdn-cache', $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view.
	}

	/**
	 * Displays the no container with title section template.
	 *
	 * @param array $args Array of arguments to populate the template.
	 */
	public function nocontainer_with_title( $args ) {
		echo $this->generate( 'sections/nocontainer-with-title', $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view.
	}

	/**
	 * Displays the text field template.
	 *
	 * @since 3.0
	 *
	 * @param array $args Array of arguments to populate the template.
	 */
	public function text( $args ) {
		echo $this->generate( 'fields/text', $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view.
	}

	/**
	 * Displays the checkbox field template.
	 *
	 * @since 3.0
	 * @author Remy Perona
	 *
	 * @param array $args Array of arguments to populate the template.
	 * @return void
	 */
	public function checkbox( $args ) {
		// Cleanup selects items; other boolean settings enable an ongoing feature.
		if ( 'database' !== $args['page'] || 0 !== strpos( $args['id'], 'database_' ) ) {
			$args['container_class'] = trim( $args['container_class'] . ' noobrocket-field--toggle' );
		}
		echo $this->generate( 'fields/checkbox', $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view.
	}

	/**
	 * Displays the textarea field template.
	 *
	 * @since 3.0
	 *
	 * @param array $args Array of arguments to populate the template.
	 */
	public function textarea( $args ) {
		if ( is_array( $args['value'] ) ) {
			$args['value'] = implode( "\n", $args['value'] );
		}

		$args['value'] = empty( $args['value'] ) ? '' : $args['value'];

		echo $this->generate( 'fields/textarea', $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view.
	}

	/**
	 * Displays the textarea with container field template.
	 *
	 * @since 3.22
	 *
	 * @param array $args Array of arguments to populate the template.
	 */
	public function textarea_with_container( $args ) {
		if ( is_array( $args['value'] ) ) {
			$args['value'] = implode( "\n", $args['value'] );
		}

		$args['value'] = empty( $args['value'] ) ? '' : $args['value'];

		echo $this->generate( 'fields/textarea-with-container', $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view.
	}

	/**
	 * Displays the sliding checkbox field template.
	 *
	 * @since 3.0
	 *
	 * @param array $args Array of arguments to populate the template.
	 */
	public function sliding_checkbox( $args ) {
		echo $this->generate( 'fields/sliding-checkbox', $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view.
	}

	/**
	 * Displays the number input field template.
	 *
	 * @since 3.0
	 *
	 * @param array $args Array of arguments to populate the template.
	 */
	public function number( $args ) {
		echo $this->generate( 'fields/number', $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view.
	}

	/**
	 * Displays the multiselect field template.
	 *
	 * @param array $args Array of arguments to populate the template.
	 */
	public function categorized_multiselect( $args ) {
		$args['items']    = empty( $args['items'] ) ? new stdClass() : $args['items'];
		$args['selected'] = get_noobrocket_option( sanitize_key( $args['id'] ), [] );

		echo $this->generate( 'fields/categorized_multiselect', $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view.
	}

	/**
	 * Displays the select field template.
	 *
	 * @since 3.0
	 *
	 * @param array $args Array of arguments to populate the template.
	 */
	public function select( $args ) {
		echo $this->generate( 'fields/select', $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view.
	}

	/**
	 * Displays the clear cache lifespan block template.
	 *
	 * @since 3.0
	 *
	 * @param array $args Array of arguments to populate the template.
	 */
	public function cache_lifespan( $args ) {
		echo $this->generate( 'fields/cache-lifespan', $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view.
	}

	/**
	 * Displays the hidden field template.
	 *
	 * @since 3.0
	 *
	 * @param array $args Array of arguments to populate the template.
	 */
	public function hidden( $args ) {
		if ( is_array( $args['value'] ) ) {
			$args['value'] = implode( "\n", $args['value'] );
		}

		echo $this->generate( 'fields/hidden', $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view.
	}

	/**
	 * Displays the CDN CNAMES template.
	 *
	 * @since 3.0
	 *
	 * @param array $args Array of arguments to populate the template.
	 */
	public function cnames( $args ) {
		echo $this->generate( 'fields/cnames', $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view.
	}

	/**
	 * Displays the HostedCDN template.
	 *
	 * @since 3.5
	 *
	 * @param array $args Array of arguments to populate the template.
	 */
	public function noobrocket_cdn( $args ) {
		echo $this->generate( 'fields/noobrocket-cdn', $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view.
	}

	/**
	 * Displays the one-click add-on field template.
	 *
	 * @since 3.0
	 *
	 * @param array $args Array of arguments to populate the template.
	 */
	public function one_click_addon( $args ) {
		echo $this->generate( 'fields/one-click-addon', $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view.
	}

	/**
	 * Displays the Rocket add-on field template.
	 *
	 * @since 3.0
	 *
	 * @param array $args Array of arguments to populate the template.
	 */
	public function noobrocket_addon( $args ) {
		echo $this->generate( 'fields/noobrocket-addon', $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view.
	}

	/**
	 * Displays the import form template.
	 *
	 * @since 3.0
	 */
	public function render_import_form( bool $review_only = false ) {
		$args = [ 'review_only' => $review_only ];

		/**
		 * Filter the maximum allowed upload size for import files.
		 *
		 * @since (WordPress) 2.3.0
		 *
		 * @see wp_max_upload_size()
		 *
		 * @param int $max_upload_size Allowed upload size. Default 1 MB.
		 */
		$args['bytes']       = apply_filters( 'import_upload_size_limit', wp_max_upload_size() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$args['size']        = size_format( $args['bytes'] );
		$args['upload_dir']  = wp_upload_dir();
		$args['action']      = 'noobrocket_import_settings';
		$args['submit_text'] = __( 'Upload file and import settings', 'noobrocket' );
		$args['labels'] = [];
		$args['choices'] = [];
		$args['types'] = [];
		foreach ( $this->settings as $page ) {
			foreach ( $page['sections'] ?? [] as $section ) {
				foreach ( $section['fields'] ?? [] as $id => $field ) {
					$args['labels'][ $id ] = wp_strip_all_tags( ( $field['label'] ?? '' ) ?: ( $section['title'] ?? $id ) );
					$args['choices'][ $id ] = array_filter( $field['choices'] ?? [], 'is_string' );
					$args['types'][ $id ] = $field['type'] ?? 'text';
					if ( in_array( $id, [ 'cloudflare_api_key_mask', 'cloudflare_zone_id_mask' ], true ) ) {
						// Imports contain the stored value, while the screen registers its masked editor.
						$args['labels'][ substr( $id, 0, -5 ) ] = $args['labels'][ $id ];
					}
					if ( 'cnames' === ( $field['type'] ?? '' ) ) {
						$args['labels']['cdn_zone'] = __( 'Files to serve', 'noobrocket' );
						$args['choices']['cdn_zone'] = Settings::get_cdn_scope_labels();
					}
				}
			}
		}

		echo $this->generate( 'fields/import-form', $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view.
	}

	/**
	 * Displays the radio_buttons field template.
	 *
	 * @since 3.10
	 *
	 * @param array $args Array of arguments to populate the template.
	 */
	public function radio_buttons( $args ) {
		echo $this->generate( 'fields/radio-buttons', $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view.
	}

	/**
	 * Renders the fields.
	 *
	 * @since 3.10
	 *
	 * @param array $fields   fields to render.
	 *
	 * @return void
	 */
	public function render_fields( $fields ) {

		foreach ( $fields as $id => $args ) {
			$default = [
				'type'              => 'text',
				'label'             => '',
				'description'       => '',
				'class'             => '',
				'container_class'   => '',
				'default'           => '',
				'helper'            => '',
				'placeholder'       => '',
				'secret'            => false,
				'parent'            => '',
				'section'           => '',
				'page'              => '',
				'sanitize_callback' => 'sanitize_text_field',
				'input_attr'        => '',
				'warning'           => [],
			];

			$args = wp_parse_args( $args, $default );

			if ( empty( $args['id'] ) ) {
				$args['id'] = $id;
			}

			if ( ! empty( $args['input_attr'] ) ) {
				$input_attr = '';

				foreach ( $args['input_attr'] as $key => $value ) {
					if ( 'disabled' === $key ) {
						if ( 1 === $value ) {
							$input_attr .= ' disabled';
						}

						continue;
					}

					$input_attr .= ' ' . sanitize_key( $key ) . '="' . esc_attr( $value ) . '"';
				}

				$args['input_attr'] = $input_attr;
			}

			if ( ! empty( $args['parent'] ) ) {
				$args['parent'] = ' data-parent="' . esc_attr( $args['parent'] ) . '"';
			}

			if ( ! empty( $args['class'] ) ) {
				$args['class'] = implode( ' ', array_map( 'sanitize_html_class', $args['class'] ) );
			}

			if ( ! empty( $args['container_class'] ) ) {
				$args['container_class'] = implode( ' ', array_map( 'sanitize_html_class', $args['container_class'] ) );
			}

			if ( ! empty( $args['disclosure'] ) ) {
				$parent_class = false !== strpos( $args['container_class'], 'noobrocket-field--children' ) ? ' noobrocket-field--children' : '';
				printf(
					'<details class="noobrocket-field-disclosure%s"%s><summary>%s',
					esc_attr( $parent_class ),
					$args['parent'], // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Parent attributes were escaped above.
					esc_html( $args['disclosure'] )
				);
				if ( ! empty( $args['value'] ) ) {
					echo '<span class="noobrocket-badge is-neutral">' . esc_html__( 'Configured', 'noobrocket' ) . '</span>';
				}
				echo '</summary>';
				// The disclosure owns conditional visibility; its controls retain their names and values.
				$args['parent']          = '';
				$args['container_class'] = str_replace( 'noobrocket-field--children', '', $args['container_class'] );
			}

			call_user_func_array( [ $this, $args['type'] ], [ $args ] );

			if ( ! empty( $args['disclosure'] ) ) {
				echo '</details>';
			}
		}
	}
}
