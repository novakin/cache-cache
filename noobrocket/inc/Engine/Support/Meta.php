<?php
declare(strict_types=1);

namespace NoobRocket\Engine\Support;

use NoobRocket\Admin\Options_Data;
use NoobRocket\Dependencies\Detection\MobileDetect;

class Meta {
	/**
	 * Mobile Detect instance
	 *
	 * @var MobileDetect
	 */
	private $mobile_detect;

	/**
	 * Options instance
	 *
	 * @var Options_Data
	 */
	private $options;

	/**
	 * Instantiate the class
	 *
	 * @param MobileDetect $mobile_detect Mobile Detect instance.
	 * @param Options_Data $options Options instance.
	 */
	public function __construct( MobileDetect $mobile_detect, Options_Data $options ) {
		$this->mobile_detect = $mobile_detect;
		$this->options       = $options;
	}

	/**
	 * Add the NoobRocket meta generator tag to the HTML
	 *
	 * @param string $html The HTML content.
	 * @return string
	 */
	public function add_meta_generator( $html ): string {
		if ( noobrocket_bypass() ) {
			return $html;
		}

		if ( noobrocket_get_constant( 'DONOTNOOBROCKETOPTIMIZE' ) ) {
			return $html;
		}

		/**
		 * Filters whether to disable the NoobRocket meta generator tag.
		 *
		 * @since 3.17.2
		 *
		 * @param bool $disable True to disable, false otherwise.
		 */
		if ( wpm_apply_filters_typed( 'boolean', 'noobrocket_disable_meta_generator', false ) ) {
			return $html;
		}

		if ( noobrocket_get_constant( 'NOOBROCKET_WHITE_LABEL_FOOTPRINT', false ) ) {
			return $this->remove_features_comments( $html );
		}

		if ( is_user_logged_in() ) {
			return $this->remove_features_comments( $html );
		}

		if ( false === preg_match_all( '/<!-- (?<feature>nbr_(?:[^-]*)) -->/i', $html, $comments, PREG_PATTERN_ORDER ) ) {
			return $html;
		}

		$meta = $this->get_meta_tag( $comments['feature'] );

		if ( empty( $meta ) ) {
			return $html;
		}

		$result = preg_replace( '/<\/head>/i', $meta . '</head>', $html, 1 );

		if ( null === $result ) {
			return $html;
		}

		return $this->remove_features_comments( $result );
	}

	/**
	 * Get the NoobRocket meta generator tag
	 *
	 * @param array $features Features to add to the meta tag.
	 *
	 * @return string
	 */
	private function get_meta_tag( array $features = [] ): string {
		$options = $this->options;

		// Feature mapping for meta tags.
		$features_to_check = [
			'nbr_preload_links'      => 'preload_links',
			'nbr_host_fonts_locally' => 'host_fonts_locally',
		];

		foreach ( $features_to_check as $meta_name => $option_name ) {
			if ( $options->get( $option_name, false ) ) {
				$features[] = $meta_name;
			}
		}

		// Mobile/Desktop caching.
		if ( $options->get( 'do_caching_mobile_files', false ) ) {
			$features[] = $this->mobile_detect->isMobile() ? 'nbr_mobile' : 'nbr_desktop';
		}

		// CDN & DNS prefetch check.
		$dns_prefetch = noobrocket_get_dns_prefetch_domains();
		if ( $dns_prefetch && ( ! $options->get( 'cdn', false ) || count( $dns_prefetch ) > 1 ) ) {
			$features[] = 'nbr_dns_prefetch';
		}

		if ( ! $features ) {
			return '';
		}

		// Check if NoobRocket version should be included.
		$version = wpm_apply_filters_typed( 'boolean', 'noobrocket_display_meta_generator_content_version', true )
			? ' ' . noobrocket_get_constant( 'NOOBROCKET_VERSION', '' )
			: '';

		return sprintf(
			'<meta name="generator" content="NoobRocket%s" data-noobrocket-features="%s" />',
			$version,
			implode( ' ', $features )
		);
	}

	/**
	 * Remove NoobRocket features comments from the HTML
	 *
	 * @param string $html The HTML content.
	 *
	 * @return string
	 */
	private function remove_features_comments( $html ): string {
		$result = preg_replace( '/<!-- nbr_[^-]* -->/i', '', $html );

		if ( null === $result ) {
			return $html;
		}

		return $result;
	}
}
