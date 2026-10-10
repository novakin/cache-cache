<?php
declare(strict_types=1);

namespace NoobRocket\Engine\Media\Fonts\Frontend;

use NoobRocket\Engine\Common\Head\ElementTrait;
use NoobRocket\Engine\Media\Fonts\Context\OptimizationContext;
use NoobRocket\Engine\Media\Fonts\Context\SaasContext;
use NoobRocket\Engine\Media\Fonts\Filesystem;
use NoobRocket\Engine\Optimization\RegexTrait;
use NoobRocket\Logger\Logger;
use NoobRocket\Engine\Media\Fonts\FontsTrait;
use NoobRocket\Engine\Media\Fonts\Admin\Data;

class Controller {
	use RegexTrait;
	use FontsTrait;
	use ElementTrait;

	/**
	 * Used for debugging head elements.
	 *
	 * @var string
	 */
	private $feature = 'host_google_fonts_locally';

	/**
	 * Optimization Context instance.
	 *
	 * @var OptimizationContext
	 */
	private $optimization_context;

	/**
	 * SaaS Context instance.
	 *
	 * @var SaasContext
	 */
	private $saas_context;

	/**
	 * Filesystem instance.
	 *
	 * @var Filesystem
	 */
	private $filesystem;

	/** @var Data */
	private $data;

	/**
	 * Base url.
	 *
	 * @var string
	 */
	private $base_url;

	/**
	 * Base path.
	 *
	 * @var string
	 */
	private $base_path;

	/**
	 * Error flag.
	 *
	 * @var bool
	 */
	private $error = false;

	/**
	 * Constructor.
	 *
	 * @param OptimizationContext $optimization_context Optimization Context instance.
	 * @param SaasContext         $saas_context SaaS Context instance.
	 * @param Filesystem          $filesystem Filesystem instance.
	 * @param Data                $data Background font preparation queue.
	 */
	public function __construct( OptimizationContext $optimization_context, SaasContext $saas_context, Filesystem $filesystem, Data $data ) {
		$this->optimization_context = $optimization_context;
		$this->saas_context         = $saas_context;
		$this->base_path            = noobrocket_get_constant( 'NOOBROCKET_CACHE_ROOT_PATH', '' ) . 'fonts/' . get_current_blog_id() . '/';
		$this->base_url             = noobrocket_get_constant( 'NOOBROCKET_CACHE_ROOT_URL', '' ) . 'fonts/' . get_current_blog_id() . '/';
		$this->filesystem           = $filesystem;
		$this->data                 = $data;
	}

	/**
	 * Rewrites the Google Fonts paths to local ones.
	 *
	 * @param string $html HTML content.
	 * @return string
	 */
	private function rewrite_fonts( $html ): string {
		// For test purposes.
		$start_time = microtime( true );

		$this->error = false;
		// Protect conditional comments even when they contain the same link as the page.
		$html = $this->replace_html_comments( $html );
		$html_nocomments = $this->hide_noscripts( $this->hide_scripts( $html ) );

		[ $v1_fonts, $v2_fonts ] = $this->find_font_links( $html_nocomments );

		if ( ! $v1_fonts && ! $v2_fonts ) {
			Logger::debug( 'No Google Fonts found.', [ 'Host Fonts Locally' ] );
			return $this->restore_html_comments( $html );
		}

		$exclusions = $this->get_exclusions();

		// Count fonts - for test purposes.
		$total_v1    = count( $v1_fonts );
		$total_v2    = count( $v2_fonts );
		$total_fonts = $total_v1 + $total_v2;

		foreach ( $v1_fonts as $font ) {
			if ( $this->is_excluded( $font[0], $exclusions ) ) {
				$this->error = true;
				continue;
			}
			$html = $this->replace_font( $font, $html );
		}

		foreach ( $v2_fonts as $font ) {
			if ( $this->is_excluded( $font[0], $exclusions ) ) {
				$this->error = true;
				continue;
			}
			$html = $this->replace_font( $font, $html );
		}

		if ( ! $this->error ) {
			$html = $this->remove_preconnect_and_prefetch( $html );
		}

		// End time measurement.
		$end_time = microtime( true );

		// Log the total execution time and number of fonts processed, with breakdown.
		$duration = $end_time - $start_time;
		Logger::debug( "Total execution time for Host Google Fonts Feature in seconds -- $duration. CSS files processed: $total_fonts | Total v1: $total_v1 | Total v2: $total_v2", [ 'Host Fonts Locally' ] );

		return $this->restore_html_comments( $html );
	}

	/**
	 * Whether native local hosting owns this link in the current context.
	 */
	public function owns_font_link( string $tag ): bool {
		if ( ! $this->optimization_context->is_allowed() ) {
			return false;
		}
		[ $v1_fonts, $v2_fonts ] = $this->find_font_links( $tag );
		$exclusions = $this->get_exclusions();
		foreach ( array_merge( $v1_fonts, $v2_fonts ) as $font ) {
			if ( ! $this->is_excluded( $font[0], $exclusions ) && ! $this->has_integrity( $font[0] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Keep provider recognition identical for native rewriting and CSS ownership.
	 */
	private function find_font_links( string $html ): array {
		$fonts = [ [], [] ];
		$links = $this->find( '<link(?=\s|\/?>)(?:"[^"]*"|\'[^\']*\'|[^\'">])*>', $html );
		foreach ( $links as $link ) {
			if ( ! preg_match( '/\s+href\s*=\s*([\'"])(?<url>(?:(?!\1).)*)\1|"[^"]*"(*SKIP)(*FAIL)|\'[^\']*\'(*SKIP)(*FAIL)/is', $link[0], $href ) ) {
				continue;
			}
			if ( preg_match( '#^(?:https?:)?//fonts\.googleapis\.com/css[^\d].+#i', $href['url'] ) ) {
				$fonts[0][] = [ $link[0], 'url' => $href['url'] ];
			} elseif ( preg_match( '#^(?:https?:)?//fonts\.googleapis\.com/css2.+#i', $href['url'] ) ) {
				$fonts[1][] = [ $link[0], 'url' => $href['url'] ];
			}
		}
		return $fonts;
	}

	/**
	 * Rewrite fonts for normal optimizations.
	 *
	 * @param string $html page HTML.
	 * @return string
	 */
	public function rewrite_fonts_for_optimizations( $html ): string {
		if ( ! $this->optimization_context->is_allowed() ) {
			return $html;
		}
		return $this->rewrite_fonts( $html );
	}

	/**
	 * Rewrite fonts for SaaS visits optimizations.
	 *
	 * @param string $html page HTML.
	 * @return string
	 */
	public function rewrite_fonts_for_saas( $html ): string {
		if ( ! $this->saas_context->is_allowed() ) {
			return $html;
		}
		return $this->rewrite_fonts( $html );
	}

	/**
	 * Replaces the Google Fonts URL with the local one.
	 *
	 * @param array  $font    Font data.
	 * @param string $html    HTML content.
	 * @param string $font_provider Font provider.
	 *
	 * @return string
	 */
	private function replace_font( array $font, string $html, string $font_provider = 'google-fonts' ): string {
		if ( $this->has_integrity( $font[0] ) ) {
			$this->error = true;
			return $html;
		}
		$hash = md5( $font['url'] );

		if ( $this->filesystem->exists( $this->get_css_path( $hash, $font_provider ) ) ) {
			$local = $this->get_optimized_markup( $hash, $font['url'], $font_provider, $font[0] );

			$updated = preg_replace_callback(
				'#<script\b[^>]*>[\s\S]*?</script\s*>(*SKIP)(*FAIL)|<noscript\b[^>]*>[\s\S]*?</noscript\s*>(*SKIP)(*FAIL)|' . preg_quote( $font[0], '#' ) . '#i',
				static function () use ( $local ) { return $local; },
				$html
			);
			return null === $updated ? $html : $updated;
		}

		$this->schedule_preparation( $font['url'] );
		$this->error = true;
		return $html;
	}

	/** Enqueue only this site's query-free rendered page using the existing font queue. */
	private function schedule_preparation( string $css_url ): void {
		global $wp;
		if ( ! isset( $wp->request ) ) {
			return;
		}
		$this->data->schedule_font_preparation( $css_url, home_url( add_query_arg( [], $wp->request ) ) );
	}

	/** Parse complete attributes so quoted data values cannot impersonate real attributes. */
	private function get_link_attributes( string $tag ): array {
		$attributes = [];
		preg_match_all( '/\s+(?<name>[^\s=\/>]+)(?:\s*=\s*(?:"(?<double>[^"]*)"|\'(?<single>[^\']*)\'|(?<unquoted>[^\s"\'<>`=]+)))?/', $tag, $matches, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL );
		foreach ( $matches as $match ) {
			$attributes[ strtolower( $match['name'] ) ] = $match['double'] ?? $match['single'] ?? $match['unquoted'] ?? null;
		}
		return $attributes;
	}

	/** SRI hashes refer to the original CSS bytes; retain the original resource. */
	private function has_integrity( string $tag ): bool {
		return array_key_exists( 'integrity', $this->get_link_attributes( $tag ) );
	}

	/** Only inline links whose loading and application semantics a style tag preserves. */
	private function can_inline( array $attributes ): bool {
		if ( 'stylesheet' !== strtolower( html_entity_decode( $attributes['rel'] ?? '', ENT_QUOTES ) )
			|| ! in_array( strtolower( $attributes['media'] ?? 'all' ), [ '', 'all' ], true )
			|| ! in_array( strtolower( $attributes['type'] ?? '' ), [ '', 'text/css' ], true ) ) {
			return false;
		}
		foreach ( $attributes as $name => $value ) {
			if ( ! is_string( $name ) || ( ! in_array( $name, [ 'href', 'rel', 'media', 'id', 'class', 'nonce', 'type' ], true ) && 0 !== strpos( $name, 'data-' ) ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Returns the optimized markup for Google Fonts
	 *
	 * @since 3.18
	 *
	 * @param string $hash Font Url has.
	 * @param string $original_url Fonts Url.
	 * @param string $font_provider Fonts provider.
	 * @param string $original_tag Original link attributes and delivery semantics.
	 *
	 * @return string
	 */
	private function get_optimized_markup(
		string $hash,
		string $original_url,
		string $font_provider,
		string $original_tag
	): string {
		$font_provider_path = sprintf( '%s/', $font_provider );

		$original_url  = html_entity_decode( $original_url, ENT_QUOTES );
		$gf_parameters = wp_parse_url( $original_url, PHP_URL_QUERY );

		$attributes = $this->get_link_attributes( $original_tag );
		if ( $this->is_host_fonts_inline_css() && $this->can_inline( $attributes ) ) {
			$local_css_path = $this->get_css_path( $hash, $font_provider );

			$inline_css = $this->get_font_inline_css( $local_css_path, $gf_parameters, $attributes );

			if ( ! empty( $inline_css ) ) {
				return $inline_css;
			}
		}

		// This filter is documented in inc/classes/optimization/css/class-abstract-css-optimization.php.
		$url = wpm_apply_filters_typed( 'string', 'noobrocket_css_url', $this->base_url . $font_provider_path . 'css/' . $this->filesystem->hash_to_path( $hash ) . '.css' );

		// Change only href. Keep media, disabled, handlers, nonce and integration attributes exact.
		$local = preg_replace_callback(
			'/\s+href\s*=\s*(?:"[^"]*"|\'[^\']*\')|"[^"]*"(*SKIP)(*FAIL)|\'[^\']*\'(*SKIP)(*FAIL)/i',
			static function ( $match ) use ( $url ) { return ' href="' . esc_attr( $url ) . '"'; },
			$original_tag,
			1
		);
		return preg_replace_callback(
			'/\s*\/?>$/',
			static function () use ( $gf_parameters ) { return ' data-noobrocket-hosted-gf-parameters="' . esc_attr( $gf_parameters ) . '"/>'; },
			$local,
			1
		);
	}

	/**
	 * Check if we need to show local font styles as inline or not.
	 *
	 * @return bool
	 */
	private function is_host_fonts_inline_css() {
		/**
		 * Filters to enable the inline css output.
		 *
		 * @since 3.18
		 *
		 * @param bool $enable Tells if we are enabling or not the inline css output.
		 */
		return wpm_apply_filters_typed( 'boolean', 'noobrocket_host_fonts_locally_inline_css', false );
	}

	/**
	 * Get optimized local url.
	 *
	 * @param string $hash Font url hash.
	 * @param string $font_provider Font provider.
	 * @return string
	 */
	private function get_optimized_url( string $hash, string $font_provider ) {
		$font_provider_path = sprintf( '%s/', $font_provider );

		// This filter is documented in inc/classes/optimization/css/class-abstract-css-optimization.php.
		return wpm_apply_filters_typed( 'string', 'noobrocket_css_url', $this->base_url . $font_provider_path . 'css/' . $this->filesystem->hash_to_path( $hash ) . '.css' );
	}

	/**
	 * Gets the CSS path for the font.
	 *
	 * @param string $hash Font hash.
	 * @param string $font_provider Font provider.
	 *
	 * @return string
	 */
	private function get_css_path( string $hash, string $font_provider ): string {
		$font_provider_path = sprintf( '%s/', $font_provider );

		return $this->base_path . $font_provider_path . 'css/' . $this->filesystem->hash_to_path( $hash ) . '.css';
	}

	/**

	 * Removes preconnect and prefetch links for Google Fonts from the HTML content.
	 *
	 * @param string $html HTML content.
	 *
	 * @return string Modified HTML content without preconnect and prefetch links.
	 */
	private function remove_preconnect_and_prefetch( string $html ) {
		/**
		 * Filters the removal of Google preconnect/prefetch links.
		 *
		 * @since 3.18
		 *
		 * @param bool $enable_removal Enable or disable removal of Google preconnect/prefetch links.
		 */
		$remove_links = wpm_apply_filters_typed( 'boolean', 'noobrocket_remove_font_pre_links', true );

		if ( ! $remove_links ) {
			return $html;
		}

		$pattern = '/<link[^>]*\b(rel\s*=\s*[\'"](?:preconnect|dns-prefetch)[\'"]|href\s*=\s*[\'"](?:https?:)?\/\/(?:fonts\.(?:googleapis|gstatic)\.com)[\'"])[^>]*\b(rel\s*=\s*[\'"](?:preconnect|dns-prefetch)[\'"]|href\s*=\s*[\'"](?:https?:)?\/\/(?:fonts\.(?:googleapis|gstatic)\.com)[\'"])[^>]*>/i';

		$html = preg_replace( $pattern, '', $html );

		return $html;
	}

	/**
	 * Gets the font inline css.
	 *
	 * @param string $local_css_path CSS file path.
	 * @param string $gf_parameters Google Fonts parameters.
	 * @param array  $attributes Original stylesheet attributes.
	 *
	 * @return string
	 */
	private function get_font_inline_css( string $local_css_path, string $gf_parameters, array $attributes ): string {
		$content = $this->filesystem->get_file_content( $local_css_path );

		if ( empty( $content ) ) {
			return '';
		}

		$preserved = '';
		foreach ( $attributes as $name => $value ) {
			if ( in_array( $name, [ 'rel', 'href', 'media' ], true ) ) {
				continue;
			}
			$preserved .= ' ' . $name . ( null === $value ? '' : '="' . esc_attr( html_entity_decode( $value, ENT_QUOTES ) ) . '"' );
		}
		return sprintf(
			'<style data-noobrocket-hosted-gf-parameters="%1$s"%2$s>%3$s</style>',
			esc_attr( $gf_parameters ),
			$preserved,
			$content
		);
	}

	/**
	 * Get local font url using the external one.
	 *
	 * @param string $font_url Font url.
	 * @param string $font_provider Font provider.
	 * @return string
	 */
	private function get_font_local_url( string $font_url, string $font_provider = 'google-fonts' ): string {
		$hash = md5( $font_url );

		if ( $this->filesystem->exists( $this->get_css_path( $hash, $font_provider ) ) ) {
			return $this->get_optimized_url( $hash, $font_provider );
		}

		$this->schedule_preparation( $font_url );
		return '';
	}

	/**
	 * Is this a url of google font or not.
	 *
	 * @param string $url Font url to test.
	 * @return bool
	 */
	private function is_google_font_url( string $url ): bool {
		return (bool) preg_match( '#^(?:https?:)?//fonts\.googleapis\.com/css(?:[^\d].+|2.+)$#i', $url );
	}

	/**
	 * Get font CSS styles as inline using font url.
	 *
	 * @param string $font_url Font url.
	 * @param string $gf_parameters Google font query string.
	 * @param string $font_provider Font provider.
	 *
	 * @return array|string[]
	 */
	private function get_font_styles_by_url( string $font_url, string $gf_parameters, string $font_provider = 'google-fonts' ) {
		$hash           = md5( $font_url );
		$local_css_path = $this->get_css_path( $hash, $font_provider );

		$inline_css = $this->filesystem->get_file_content( $local_css_path );
		if ( empty( $inline_css ) ) {
			return [];
		}

		return $this->style_tag(
			$inline_css,
			[
				'data-noobrocket-hosted-gf-parameters' => $gf_parameters,
			]
		);
	}

	/**
	 * Rewrite fonts in head items.
	 *
	 * @param array $items Head items.
	 * @return array
	 */
	public function rewrite_fonts_in_head( array $items ): array {
		if ( ! $this->optimization_context->is_allowed() ) {
			return $items;
		}

		$exclusions = $this->get_exclusions();
		foreach ( $items as $key => &$item ) {
			if ( empty( $item['href'] ) || ! $this->is_google_font_url( $item['href'] ) ) {
				continue;
			}

			if ( $this->is_excluded( $item['href'], $exclusions ) ) {
				continue;
			}
			if ( array_key_exists( 'integrity', $item ) ) {
				continue;
			}

			$font_url       = html_entity_decode( $item['href'], ENT_QUOTES );
			$gf_parameters  = wp_parse_url( $font_url, PHP_URL_QUERY );
			$local_font_url = $this->get_font_local_url( $item['href'] );
			if ( '' === $local_font_url ) {
				continue;
			}

			$attributes = array_diff_key( $item, array_flip( [ 'open_tag', 'close_tag', 'inner_content' ] ) );
			if ( $this->is_host_fonts_inline_css() && $this->can_inline( $attributes ) ) {
				$style = $this->get_font_styles_by_url( $item['href'], $gf_parameters );
				if ( ! $style ) {
					continue;
				}
				$items[] = array_merge( array_diff_key( $attributes, array_flip( [ 'rel', 'href', 'media' ] ) ), $style );
				unset( $items[ $key ] );
				continue;
			}

			$item['href']                          = $local_font_url;
			$item['data-noobrocket-hosted-gf-parameters'] = $gf_parameters;
		}
		return $items;
	}
}
