<?php
declare(strict_types=1);

namespace NoobRocket\Engine\Optimization\DelayJS;

use NoobRocket\Admin\Options_Data;
use NoobRocket\Engine\Optimization\RegexTrait;
use NoobRocket\Event_Management\Subscriber_Interface;

class Subscriber implements Subscriber_Interface {
	use RegexTrait;

	/**
	 * HTML instance.
	 *
	 * @since 3.7
	 *
	 * @var HTML
	 */
	private $html;

	/**
	 * WP_Filesystem_Direct instance.
	 *
	 * @since 3.7
	 *
	 * @var \WP_Filesystem_Direct
	 */
	private $filesystem;

	/**
	 * Subscriber constructor.
	 *
	 * @param HTML                  $html HTML Instance.
	 * @param \WP_Filesystem_Direct $filesystem The Filesystem object.
	 */
	public function __construct( HTML $html, $filesystem ) {
		$this->html       = $html;
		$this->filesystem = $filesystem;
	}

	/**
	 * Return an array of events that this subscriber wants to listen to.
	 *
	 * @since 3.7
	 *
	 * @return array
	 */
	public static function get_subscribed_events() {
		return [
			'noobrocket_buffer'                               => [
				[ 'delay_js', 26 ],
				[ 'add_delay_js_script', 26 ],
			],
			'pre_get_noobrocket_option_minify_concatenate_js' => 'maybe_disable_option',
		];
	}

	/**
	 * Modifies scripts HTML to apply delay JS attribute
	 *
	 * @since 3.7
	 *
	 * @param string $buffer_html Html for the page.
	 *
	 * @return string
	 */
	public function delay_js( $buffer_html ) {
		if ( ! $this->find_html_tag( $buffer_html, 'head' ) ) {
			return $buffer_html;
		}
		$version = wpm_apply_filters_typesafe( 'noobrocket_delay_js_version_js_script', '' );
		if ( ! $this->get_runtime( $version ) ) {
			return $buffer_html;
		}
		return $this->html->delay_js( $buffer_html );
	}

	/**
	 * Displays the inline script to the head when the option is enabled.
	 *
	 * @since 3.9.4 Move meta charset to head.
	 * @since 3.9 Hooked on noobrocket_buffer, display the script right after <head>
	 * @since 3.7
	 *
	 * @param string $html HTML content.
	 *
	 * @return string
	 */
	public function add_delay_js_script( $html ): string {
		if ( ! $this->html->is_allowed( $html ) ) {
			return $html;
		}

		$head = $this->find_html_tag( $html, 'head' );
		if ( ! $head ) {
			return $html;
		}

		/**
		 * Select the version of the JS script used for delay js.
		 *
		 * @param string $version Version of the script.
		 */
		$version = wpm_apply_filters_typesafe( 'noobrocket_delay_js_version_js_script', '' );

		$lazyload_script = $this->get_runtime( $version );
		if ( ! $lazyload_script ) {
			return $html;
		}

		$scripts       = '<script>' . $this->html->get_ie_fallback() . '</script><script>' . $lazyload_script . '</script>';
		$replaced_html = substr_replace( $html, $scripts, $head[1] + strlen( $head[0] ), 0 );

		return $this->html->move_meta_charset_to_head( $replaced_html );
	}

	/**
	 * Disables defer JS if delay JS is enabled
	 *
	 * @since 3.9
	 *
	 * @param null $value Original value. Should be always null.
	 *
	 * @return null|false
	 */
	public function maybe_disable_option( $value ) {
		if ( $this->html->is_allowed() ) {
			return false;
		}

		return $value;
	}

	/** Read a usable runtime before making any original script inert. */
	private function get_runtime( string $version = '' ): string {
		$path = noobrocket_get_constant( 'NOOBROCKET_PATH' ) . "assets/js/lazyload-scripts$version.min.js";
		if ( ! $this->filesystem->exists( $path ) ) {
			$path = noobrocket_get_constant( 'NOOBROCKET_PATH' ) . 'assets/js/lazyload-scripts.min.js';
		}
		$script = $this->filesystem->get_contents( $path );
		return is_string( $script ) ? $script : '';
	}
}
