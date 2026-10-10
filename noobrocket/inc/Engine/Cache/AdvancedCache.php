<?php

namespace NoobRocket\Engine\Cache;

use WP_Filesystem_Direct;
use NoobRocket\Engine\Activation\ActivationInterface;
use NoobRocket\Engine\Deactivation\DeactivationInterface;

class AdvancedCache implements ActivationInterface, DeactivationInterface {

	/**
	 * Absolute path to template files
	 *
	 * @var string
	 */
	private $template_path;

	/**
	 * WP Content directory path
	 *
	 * @var string
	 */
	private $content_dir;

	/**
	 * Instance of the filesystem handler.
	 *
	 * @var WP_Filesystem_Direct
	 */
	private $filesystem;

	/**
	 * Instantiate of the class.
	 *
	 * @param string               $template_path Absolute path to template files.
	 * @param WP_Filesystem_Direct $filesystem    Instance of the filesystem handler.
	 */
	public function __construct( $template_path, $filesystem ) {
		$this->template_path = $template_path;
		$this->content_dir   = noobrocket_get_constant( 'WP_CONTENT_DIR' );
		$this->filesystem    = $filesystem;
	}

	/**
	 * Actions to perform on plugin activation
	 *
	 * @since 3.6.3
	 *
	 * @return void
	 */
	public function activate() {
		add_action( 'noobrocket_activation', [ $this, 'update_advanced_cache' ] );
	}

	/**
	 * Actions to perform on plugin deactivation
	 *
	 * @since 3.6.3
	 *
	 * @return void
	 */
	public function deactivate() {
		add_action( 'noobrocket_deactivation', [ $this, 'update_advanced_cache' ] );
	}

	/**
	 * Generates the advanced-cache.php file with its content
	 *
	 * @since 3.6.3
	 *
	 * @param int $sites_number Number of NoobRocket config files found.
	 * @return void
	 */
	public function update_advanced_cache( $sites_number = 0 ) {
		/**
		 * Filters whether to generate the advanced-cache.php file.
		 *
		 * @since 3.6.3
		 *
		 * @param bool $generate True (default) to go ahead with advanced cache file generation; false to stop generation.
		 */
		if ( ! (bool) apply_filters( 'noobrocket_generate_advanced_cache_file', true ) ) {
			return;
		}

		$content = $this->get_advanced_cache_content();

		if ( 'noobrocket_deactivation' === current_filter() ) {
			if ( is_multisite() && 0 !== $sites_number ) {
				return;
			}

			$content = '';
		}

		noobrocket_put_php_content(
			"{$this->content_dir}/advanced-cache.php",
			$content,
			$this->filesystem
		);
	}

	/**
	 * Gets the content for the advanced-cache.php file
	 *
	 * @since 3.6
	 *
	 * @return string
	 */
	public function get_advanced_cache_content() {
		$content = $this->filesystem->get_contents( $this->template_path . 'advanced-cache.php' );
		$mobile  = is_noobrocket_generate_caching_mobile_files() ? '$1' : '';
		$content = preg_replace( "/'{{MOBILE_CACHE}}';(\X*)'{{\/MOBILE_CACHE}}';/", $mobile, $content );

		$replacements = [
			'{{NOOBROCKET_PHP_VERSION}}' => noobrocket_get_constant( 'NOOBROCKET_PHP_VERSION' ),
			'{{NOOBROCKET_PATH}}'        => noobrocket_get_constant( 'NOOBROCKET_PATH' ),
			'{{NOOBROCKET_CONFIG_PATH}}' => noobrocket_get_constant( 'NOOBROCKET_CONFIG_PATH' ),
			'{{NOOBROCKET_CACHE_PATH}}'  => noobrocket_get_constant( 'NOOBROCKET_CACHE_PATH' ),
		];

		foreach ( $replacements as $key => $value ) {
			$content = str_replace( $key, $value, $content );
		}

		/**
		 * Filter the content of advanced-cache.php file.
		 *
		 * @since 2.1
		 *
		 * @param string $content The content that will be printed in advanced-cache.php.
		 */
		return (string) apply_filters( 'noobrocket_advanced_cache_file', $content );
	}

	/**
	 * This warning is displayed when the advanced-cache.php file isn't writeable
	 *
	 * @since 3.6 Moved to a method in AdvancedCache
	 * @since 2.0
	 *
	 * @return void
	 */
	public function notice_permissions() {
		if ( ! $this->is_user_allowed() ) {
			return;
		}

		// This filter is documented in inc/functions/files.php.
		if ( ! (bool) apply_filters( 'noobrocket_generate_advanced_cache_file', true ) ) {
			return;
		}

		if (
			$this->filesystem->is_writable( "{$this->content_dir}/advanced-cache.php" )
			||
			noobrocket_get_constant( 'NOOBROCKET_ADVANCED_CACHE' )
		) {
			return;
		}

		$notice_name = 'noobrocket_warning_advanced_cache_permissions';

		if (
		in_array(
			$notice_name,
			(array) get_user_meta( get_current_user_id(), 'noobrocket_boxes', true ),
			true
		)
		) {
			return;
		}

		noobrocket_notice_html(
			[
				'status'           => 'error',
				'dismissible'      => '',
				'message'          => $this->get_notice_message(),
				'dismiss_button'   => $notice_name,
				'readonly_content' => $this->get_advanced_cache_content(),
			]
		);
	}

	/**
	 * Checks if current user can see the notices
	 *
	 * @since 3.6
	 *
	 * @return bool
	 */
	private function is_user_allowed() {
		return current_user_can( 'noobrocket_manage_options' ) && noobrocket_valid_key();
	}

	/**
	 * Gets the message to display in the notice
	 *
	 * @since 3.6
	 *
	 * @return string
	 */
	private function get_notice_message() {
		return noobrocket_notice_writing_permissions( basename( $this->content_dir ) . '/advanced-cache.php' );
	}
}
