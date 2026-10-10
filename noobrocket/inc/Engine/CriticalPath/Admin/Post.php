<?php

namespace NoobRocket\Engine\CriticalPath\Admin;

use NoobRocket\Abstract_Render;
use NoobRocket\Admin\Options_Data;
use NoobRocket\Engine\Admin\Beacon\Beacon;

class Post extends Abstract_Render {
	/**
	 * Instance of the Beacon handler.
	 *
	 * @var Beacon
	 */
	private $beacon;

	/**
	 * Instance of options handler.
	 *
	 * @var Options_Data
	 */
	private $options;

	/**
	 * Path to the critical-css directory.
	 *
	 * @var string
	 */
	private $critical_css_path;

	/**
	 * Array of reasons to disable actions.
	 *
	 * @var null|array
	 */
	private $disabled_data;

	/**
	 * Creates an instance of the subscriber.
	 *
	 * @param Options_Data $options       NoobRocket Options instance.
	 * @param Beacon       $beacon        Beacon instance.
	 * @param string       $critical_path Path to the critical CSS base folder.
	 * @param string       $template_path Path to the templates folder.
	 */
	public function __construct( Options_Data $options, Beacon $beacon, $critical_path, $template_path ) {
		parent::__construct( $template_path );

		$this->beacon            = $beacon;
		$this->options           = $options;
		$this->critical_css_path = $critical_path . get_current_blog_id() . '/posts/';
	}

	/**
	 * Displays the critical CSS block in NoobRocket options metabox.
	 *
	 * @since 3.6
	 *
	 * @return void
	 */
	public function cpcss_section() {
		if ( ! current_user_can( 'noobrocket_manage_options' ) ) {
			return;
		}

		$data = [
			'disabled_description' => $this->get_disabled_description(),
		];

		echo $this->generate( 'metabox/container', $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Displays the content inside the critical CSS block.
	 *
	 * @since 3.6
	 *
	 * @return void
	 */
	public function cpcss_actions() {
		if ( ! current_user_can( 'noobrocket_manage_options' ) ) {
			return;
		}

		$data = [
			'disabled'     => $this->is_enabled(),
			'beacon'       => $this->beacon->get_suggest( 'async' ),
			'cpcss_exists' => $this->cpcss_exists(),
		];

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->generate(
			'metabox/generate',
			$data // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}

	/**
	 * Enqueue CPCSS generation / deletion script on edit.php page.
	 *
	 * @since 3.6
	 *
	 * @param string $page The current admin page.
	 *
	 * @return void
	 */
	public function enqueue_admin_edit_script( $page ) {
		global $post, $pagenow;

		if ( ! current_user_can( 'noobrocket_manage_options' ) ) {
			return;
		}

		// Bailout if the page is not Post / Page.
		if ( ! in_array( $page, [ 'edit.php', 'post.php' ], true ) ) {
			return;
		}

		if ( ! in_array( $pagenow, [ 'post-new.php', 'post.php' ], true ) ) {
			return;
		}

		// Bailout if the CPCSS is not enabled for this Post / Page.
		if ( $this->is_enabled() ) {
			return;
		}

		$post_id = ( 'post-new.php' === $pagenow ) ? '' : $post->ID;

		wp_enqueue_script(
			'noobrocket-edit-cpcss-script',
			noobrocket_get_constant( 'NOOBROCKET_ASSETS_JS_URL' ) . 'noobrocket-cpcss.js',
			[],
			noobrocket_get_constant( 'NOOBROCKET_VERSION' ),
			true
		);

		wp_localize_script(
			'noobrocket-edit-cpcss-script',
			'noobrocket_cpcss',
			[
				'rest_url'              => rest_url( "noobrocket/v1/cpcss/post/{$post_id}" ),
				'rest_nonce'            => wp_create_nonce( 'wp_rest' ),
				'generate_btn'          => __( 'Generate Specific CPCSS', 'noobrocket' ),
				'regenerate_btn'        => __( 'Regenerate specific CPCSS', 'noobrocket' ),
				'noobrocketMobileCpcssEnabled' => $this->options->get( 'async_css_mobile', 0 ),
			]
		);
	}

	/**
	 * Gets data for the disabled checks.
	 *
	 * @since 3.6
	 *
	 * @return null|array
	 */
	private function get_disabled_data() {
		global $post;

		if ( noobrocket_get_constant( 'NOOBROCKET_IS_TESTING', false ) ) {
			$this->disabled_data = null;
		}

		if ( isset( $this->disabled_data ) ) {
			return $this->disabled_data;
		}

		if ( 'publish' !== $post->post_status ) {
			$this->disabled_data['not_published'] = 1;
		}

		if ( ! $this->options->get( 'async_css', 0 ) ) {
			$this->disabled_data['option_disabled'] = 1;
		}

		if ( get_post_meta( $post->ID, '_noobrocket_exclude_async_css', true ) ) {
			$this->disabled_data['option_excluded'] = 1;
		}

		if ( ! is_post_type_viewable( get_post_type( $post ) ) ) {
			$this->disabled_data['not_viewable'] = 1;
		}

		return $this->disabled_data;
	}

	/**
	 * Checks if critical CSS generation is enabled for the current post.
	 *
	 * @since 3.6
	 *
	 * @return bool
	 */
	private function is_enabled() {
		return ! empty( $this->get_disabled_data() );
	}

	/**
	 * Returns the reason why actions are disabled.
	 *
	 * @since 3.6
	 *
	 * @return string
	 */
	private function get_disabled_description() {
		global $post;

		$disabled_data = $this->get_disabled_data();

		if ( empty( $disabled_data ) ) {
			return '';
		}

		if ( isset( $disabled_data['not_viewable'] ) ) {
			return __( 'This feature is not available for non-public post types.', 'noobrocket' );
		}

		$notice = __( '%l to use this feature.', 'noobrocket' );
		$list   = [
			// translators: %s = post type.
			'not_published'   => sprintf( __( 'Publish the %s', 'noobrocket' ), $post->post_type ),
			'option_disabled' => __( 'Enable Load CSS asynchronously in Cache-Cache settings', 'noobrocket' ),
			'option_excluded' => __( 'Enable Load CSS asynchronously in the options above', 'noobrocket' ),
		];

		return wp_sprintf_l( $notice, array_intersect_key( $list, $disabled_data ) );
	}

	/**
	 * Checks if a specific critical css file exists for the current post.
	 *
	 * @since 3.6
	 *
	 * @return bool
	 */
	private function cpcss_exists() {
		global $post;

		$post_cpcss = "{$this->critical_css_path}{$post->post_type}-{$post->ID}.css";

		return noobrocket_direct_filesystem()->exists( $post_cpcss );
	}
}
