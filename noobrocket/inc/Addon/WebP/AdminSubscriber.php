<?php
declare(strict_types=1);

namespace NoobRocket\Addon\WebP;

use NoobRocket\Admin\Options_Data;
use NoobRocket\Engine\Admin\Beacon\Beacon;
use NoobRocket\Engine\CDN\Subscriber as CDNSubscriber;
use NoobRocket\Event_Management\Subscriber_Interface;

class AdminSubscriber extends AbstractWebp implements Subscriber_Interface {
	/**
	 * Options_Data instance.
	 *
	 * @var Options_Data
	 */
	private $options_data;

	/**
	 * Beacon instance
	 *
	 * @var Beacon
	 */
	private $beacon;

	/**
	 * Constructor.
	 *
	 * @since 3.4
	 *
	 * @param Options_Data  $options_data   Options_Data instance.
	 * @param CDNSubscriber $cdn_subscriber CDNSubscriber instance.
	 * @param Beacon        $beacon         Beacon instance.
	 */
	public function __construct( Options_Data $options_data, CDNSubscriber $cdn_subscriber, Beacon $beacon ) {
		parent::__construct( $cdn_subscriber );

		$this->options_data = $options_data;
		$this->beacon       = $beacon;
	}

	/**
	 * {@inheritdoc}
	 */
	public static function get_subscribed_events() {
		return [
			'noobrocket_cache_webp_setting_field' => [
				[ 'maybe_disable_setting_field' ],
				[ 'webp_section_description' ],
			],
		];
	}

	/**
	 * Modifies the WebP section description of NoobRocket settings.
	 *
	 * @since 3.4
	 *
	 * @param  array $cache_webp_field Section description.
	 * @return array
	 */
	public function webp_section_description( $cache_webp_field ) {
		if ( $this->options_data->get( 'image_delivery', 0 ) ) {
			$cache_webp_field['description'] = esc_html__( 'Disabled while modern image delivery is enabled in Media. That option keeps original images as fallbacks and uses the same cached HTML for all browsers.', 'noobrocket' );

			return $cache_webp_field;
		}

		$webp_beacon            = $this->beacon->get_suggest( 'webp' );
		$webp_plugins           = $this->get_webp_plugins();
		$serving                = [];
		$serving_not_compatible = [];
		$creating               = [];

		if ( $webp_plugins ) {
			$is_using_cdn = $this->is_using_cdn();

			foreach ( $webp_plugins as $plugin ) {
				if ( $plugin->is_serving_webp() ) {
					if ( $is_using_cdn && ! $plugin->is_serving_webp_compatible_with_cdn() ) {
						// Serving WebP using a method not compatible with CDN.
						$serving_not_compatible[ $plugin->get_id() ] = $plugin->get_name();
					} else {
						// Serving WebP when no CDN or with a method compatible with CDN.
						$serving[ $plugin->get_id() ] = $plugin->get_name();
					}
				}
				if ( $plugin->is_converting_to_webp() ) {
					// Generating WebP.
					$creating[ $plugin->get_id() ] = $plugin->get_name();
				}
			}
		}

		if ( $serving ) {
			// 5, 8.
			$cache_webp_field['description'] = sprintf(
			// Translators: %1$s = plugin name(s), %2$s = opening <a> tag, %3$s = closing </a> tag.
				esc_html( _n( 'You are using %1$s to serve WebP images so you do not need to enable this option. %2$sMore info%3$s %4$s If you prefer to have Cache-Cache serve WebP for you instead, please disable WebP display in %1$s.', 'You are using %1$s to serve WebP images so you do not need to enable this option. %2$sMore info%3$s %4$s If you prefer to have Cache-Cache serve WebP for you instead, please disable WebP display in %1$s.', count( $serving ), 'noobrocket' ) ),
				esc_html( wp_sprintf_l( '%l', $serving ) ),
				'<a href="' . esc_url( $webp_beacon['url'] ) . '" data-beacon-article="' . esc_attr( $webp_beacon['id'] ) . '" target="_blank" rel="noopener noreferrer">',
				'</a>',
				'<br>'
			);

			return $cache_webp_field;
		}

		/** This filter is documented in inc/classes/buffer/class-cache.php */
		if ( apply_filters( 'noobrocket_disable_webp_cache', false ) ) {
			$cache_webp_field['description'] = esc_html__( 'WebP cache is disabled by filter.', 'noobrocket' );

			return $cache_webp_field;
		}

		if ( $serving_not_compatible ) {
			if ( ! $this->options_data->get( 'cache_webp', 0 ) ) {
				// 6.
				$cache_webp_field['description'] = sprintf(
				// Translators: %1$s = plugin name(s), %2$s = opening <a> tag, %3$s = closing </a> tag.
					esc_html( _n( 'You are using %1$s to convert images to WebP. If you want Cache-Cache to serve them for you, activate this option. %2$sMore info%3$s', 'You are using %1$s to convert images to WebP. If you want Cache-Cache to serve them for you, activate this option. %2$sMore info%3$s', count( $serving_not_compatible ), 'noobrocket' ) ),
					esc_html( wp_sprintf_l( '%l', $serving_not_compatible ) ),
					'<a href="' . esc_url( $webp_beacon['url'] ) . '" data-beacon-article="' . esc_attr( $webp_beacon['id'] ) . '" target="_blank" rel="noopener noreferrer">',
					'</a>'
				);

				return $cache_webp_field;
			}

			// 7.
			$cache_webp_field['description'] = sprintf(
			// Translators: %1$s = plugin name(s), %2$s = opening <a> tag, %3$s = closing </a> tag.
				esc_html( _n( 'You are using %1$s to convert images to WebP. Cache-Cache will create separate cache files to serve your WebP images. %2$sMore info%3$s', 'You are using %1$s to convert images to WebP. Cache-Cache will create separate cache files to serve your WebP images. %2$sMore info%3$s', count( $serving_not_compatible ), 'noobrocket' ) ),
				esc_html( wp_sprintf_l( '%l', $serving_not_compatible ) ),
				'<a href="' . esc_url( $webp_beacon['url'] ) . '" data-beacon-article="' . esc_attr( $webp_beacon['id'] ) . '" target="_blank" rel="noopener noreferrer">',
				'</a>'
			);

			return $cache_webp_field;
		}

		if ( $creating ) {
			if ( ! $this->options_data->get( 'cache_webp', 0 ) ) {
				// 3.
				$cache_webp_field['description'] = sprintf(
				// Translators: %1$s = plugin name(s), %2$s = opening <a> tag, %3$s = closing </a> tag.
					esc_html( _n( 'You are using %1$s to convert images to WebP. If you want Cache-Cache to serve them for you, activate this option. %2$sMore info%3$s', 'You are using %1$s to convert images to WebP. If you want Cache-Cache to serve them for you, activate this option. %2$sMore info%3$s', count( $creating ), 'noobrocket' ) ),
					esc_html( wp_sprintf_l( '%l', $creating ) ),
					'<a href="' . esc_url( $webp_beacon['url'] ) . '" data-beacon-article="' . esc_attr( $webp_beacon['id'] ) . '" target="_blank" rel="noopener noreferrer">',
					'</a>'
				);

				return $cache_webp_field;
			}

			// 4.
			$cache_webp_field['description'] = sprintf(
			// Translators: %1$s = plugin name(s), %2$s = opening <a> tag, %3$s = closing </a> tag.
				esc_html( _n( 'You are using %1$s to convert images to WebP. Cache-Cache will create separate cache files to serve your WebP images. %2$sMore info%3$s', 'You are using %1$s to convert images to WebP. Cache-Cache will create separate cache files to serve your WebP images. %2$sMore info%3$s', count( $creating ), 'noobrocket' ) ),
				esc_html( wp_sprintf_l( '%l', $creating ) ),
				'<a href="' . esc_url( $webp_beacon['url'] ) . '" data-beacon-article="' . esc_attr( $webp_beacon['id'] ) . '" target="_blank" rel="noopener noreferrer">',
				'</a>'
			);

			return $cache_webp_field;
		}

		// Keep the native-image guidance when no external WebP plugin is present.

		return $cache_webp_field;
	}

	/**
	 * Disable 'cache_webp' when native image delivery or another plugin serves WebP.
	 *
	 * @since 3.4
	 *
	 * @param  array $cache_webp_field Data to be added to the setting field.
	 * @return array
	 */
	public function maybe_disable_setting_field( $cache_webp_field ) {
		/** This filter is documented in inc/classes/buffer/class-cache.php */
		if ( ! $this->options_data->get( 'image_delivery', 0 ) && ! apply_filters( 'noobrocket_disable_webp_cache', false ) ) {
			return $cache_webp_field;
		}

		foreach ( [ 'input_attr', 'container_class' ] as $attr ) {
			if ( ! isset( $cache_webp_field[ $attr ] ) || ! is_array( $cache_webp_field[ $attr ] ) ) {
				$cache_webp_field[ $attr ] = [];
			}
		}

		$cache_webp_field['input_attr']['disabled'] = 1;

		return $cache_webp_field;
	}
}
