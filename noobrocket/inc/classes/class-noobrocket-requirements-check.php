<?php
defined( 'ABSPATH' ) || exit;

/**
 * Class to check if the current WordPress and PHP versions meet our requirements
 *
 * @since 3.0
 * @author Remy Perona
 */
class NoobRocket_Requirements_Check {
	/**
	 * Plugin Name
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * Plugin version
	 *
	 * @var string
	 */
	private $plugin_version;

	/**
	 * Required WordPress version
	 *
	 * @var string
	 */
	private $wp_version;

	/**
	 * Required PHP version
	 *
	 * @var string
	 */
	private $php_version;

	/**
	 * Constructor
	 *
	 * @since 3.0
	 * @author Remy Perona
	 *
	 * @param array $args {
	 *     Arguments to populate the class properties.
	 *
	 *     @type string $plugin_name Plugin name.
	 *     @type string $wp_version  Required WordPress version.
	 *     @type string $php_version Required PHP version.
	 *     @type string $plugin_version Plugin version.
	 * }
	 */
	public function __construct( $args ) {
		foreach ( [ 'plugin_name', 'plugin_version', 'wp_version', 'php_version' ] as $setting ) {
			if ( isset( $args[ $setting ] ) ) {
				$this->$setting = $args[ $setting ];
			}
		}
	}

	/**
	 * Checks requirements and registers a notice when the runtime is unsupported.
	 *
	 * @since 3.0
	 * @author Remy Perona
	 *
	 * @return bool
	 */
	public function check() {
		if ( ! $this->php_passes() || ! $this->wp_passes() ) {

			add_action( 'admin_notices', [ $this, 'notice' ] );

			return false;
		}

		return true;
	}

	/**
	 * Checks if the current PHP version is equal or superior to the required PHP version
	 *
	 * @since 3.0
	 * @author Remy Perona
	 *
	 * @return bool
	 */
	private function php_passes() {
		return version_compare( PHP_VERSION, $this->php_version ) >= 0;
	}

	/**
	 * Checks if the current WordPress version meets the required WordPress version.
	 *
	 * @since 3.0
	 * @author Remy Perona
	 *
	 * @return bool
	 */
	private function wp_passes() {
		global $wp_version;

		return version_compare( $wp_version, $this->wp_version ) >= 0;
	}

	/**
	 * Warns if PHP or WordPress versions do not meet the requirements.
	 *
	 * @since 3.0 Updated minimum PHP version to 5.4 and minimum WordPress version to 4.2
	 * @since 3.0 Moved to class
	 * @since 2.11
	 * @author Remy Perona
	 */
	public function notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Translators: %1$s = Plugin name, %2$s = Plugin version.
		$message = '<p>' . sprintf( __( 'To function properly, %1$s %2$s requires at least:', 'noobrocket' ), $this->plugin_name, $this->plugin_version ) . '</p><ul>';

		if ( ! $this->php_passes() ) {
			// Translators: %1$s = PHP version required.
			$message .= '<li>' . sprintf( __( 'PHP %1$s. To use this Cache-Cache version, please ask your web host how to upgrade your server to PHP %1$s or higher.', 'noobrocket' ), $this->php_version ) . '</li>';
		}

		if ( ! $this->wp_passes() ) {
			// Translators: %1$s = WordPress version required.
			$message .= '<li>' . sprintf( __( 'WordPress %1$s. To use this Cache-Cache version, please upgrade WordPress to version %1$s or higher.', 'noobrocket' ), $this->wp_version ) . '</li>';
		}

		$message .= '</ul><p>' . __( 'If you cannot upgrade PHP or WordPress, restore a compatible Cache-Cache ZIP from your own backup.', 'noobrocket' ) . '</p>';

		echo '<div class="notice notice-error">' . wp_kses_post( $message ) . '</div>';
	}
}
