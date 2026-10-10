<?php
namespace NoobRocket\Engine\Optimization\LocalCSS;

use voku\helper\HtmlDomParser;
use NoobRocket\Engine\Media\Fonts\Frontend\Controller as Fonts;
use NoobRocket\Engine\Optimization\RegexTrait;

/**
 * Only replace the exact external sheets successfully processed by the worker.
 */
class Frontend {
	use RegexTrait;

	/**
	 * Local CSS job storage and publication.
	 *
	 * @var Jobs
	 */
	private $jobs;

	/**
	 * Native font delivery controller.
	 *
	 * @var Fonts
	 */
	private $fonts;

	/**
	 * Whether critical CSS was applied to the current buffer.
	 *
	 * @var bool
	 */
	private $critical_applied = false;

	public function __construct( Jobs $jobs, Fonts $fonts ) {
		$this->jobs  = $jobs;
		$this->fonts = $fonts;
	}

	public function has_critical_css(): bool {
		return $this->critical_applied;
	}

	public function optimize( string $html ): string {
		$this->critical_applied = false;
		$mode = get_noobrocket_option( 'local_css_mode', 'off' );

		if ( ! in_array( $mode, [ 'critical', 'used' ], true )
			|| is_admin()
			|| is_user_logged_in()
			|| is_feed()
			|| is_preview()
			|| is_404()
			|| noobrocket_bypass()
			|| noobrocket_get_constant( 'DONOTNOOBROCKETOPTIMIZE' )
			|| noobrocket_get_constant( 'DONOTCACHEPAGE' )
			// Cookie-free browser samples cannot represent personalized cache variants.
			|| get_noobrocket_cache_dynamic_cookies()
			|| get_noobrocket_cache_mandatory_cookies()
			|| $this->has_rejected_cookie()
			|| ( is_singular() && ( post_password_required() || ! is_post_publicly_viewable( get_queried_object_id() ) ) )
			|| is_noobrocket_post_excluded_option( 'used' === $mode ? 'remove_unused_css' : 'async_css' )
			|| ! empty( $_GET )
			|| ! $this->find_html_tag( $html, 'head', true )
		) {
			return $html;
		}

		if ( 'critical' === $mode ) {
			// This delivery path uses inline critical styles and an onload handler.
			// Preserve blocking originals when an enforcing CSP may reject either.
			foreach ( headers_list() as $header ) {
				if ( 0 === stripos( $header, 'Content-Security-Policy:' ) ) {
					return $html;
				}
			}

			if ( preg_match( '#<meta\b[^>]*http-equiv\s*=\s*["\']?content-security-policy\b#i', $html ) ) {
				return $html;
			}
		}

		global $wp;

		$url = home_url( '/' . ltrim( $wp->request ?? '', '/' ) );
		if ( ! $this->jobs->valid_url( $url ) ) {
			return $html;
		}

		$styles = $this->styles( $html, $url );
		if ( ! $styles ) {
			return $html;
		}

		$safelist = (array) get_noobrocket_option( 'local_css_safelist', [] );
		$manifest = array_values(
			array_map(
				function ( $style ) {
					return [
						'href'  => $style['href'],
						'media' => $style['media']
					];
				},
				$styles
			)
		);

		// Repeated links keep their positions, but one browser result serves each URL.
		$manifest = array_values( array_column( $manifest, null, 'href' ) );
		$fingerprint = md5( wp_json_encode( [ $mode, $manifest, $safelist ] ) );
		$mobile = $this->mobile();
		$row = $this->jobs->find( $url, $mobile );
		if ( ! $row || $row->queue_name !== 'local-' . $mode || $row->hash !== $fingerprint ) {
			$this->jobs->enqueue( $url, $mobile, $mode, $fingerprint, $manifest, $safelist );
			return $html;
		}

		if ( 'local-completed' !== $row->status ) {
			return $html;
		}

		$data    = json_decode( $row->css, true );
		$results = array_column( $data['results'] ?? [], null, 'href' );
		foreach ( $manifest as $sheet ) {
			$result = $results[ $sheet['href'] ] ?? [];
			if ( empty( $result['path'] )
				|| ! is_readable( $result['path'] )
				|| ( 'critical' === $mode && empty( $result['css'] ) )
				|| ( 'used' === $mode && ( empty( $result['uri'] ) || ! is_file( $result['path'] ) || filesize( $result['path'] ) <= 0 ) )
			) {
				$this->jobs->invalidate_urls( [ $url ] );
				return $html;
			}
		}

		foreach ( array_reverse( $styles ) as $style ) {
			$result = $results[ $style['href'] ];
			$tag    = $style['tag'];
			$node   = HtmlDomParser::str_get_html( $tag )->findOne( 'link' );

			if ( 'used' === $mode ) {
				$node->setAttribute( 'href', $result['uri'] );
				$replacement = $node->html();
			} else {
				$media    = $style['media'];
				$nonce    = $style['nonce'] ? ' nonce="' . esc_attr( $style['nonce'] ) . '"' : '';
				$critical = '<style data-noobrocket-local-css="critical" media="' . esc_attr( $media ?: 'all' ) . '"' . $nonce . '>' . $result['css'] . '</style>';
				$node->setAttribute( 'media', 'print' );
				$node->setAttribute( 'onload', "this.onload=null;this.media='" . ( $media ?: 'all' ) . "';" );
				$deferred    = $node->html();
				$replacement = $critical . $deferred . '<noscript>' . $tag . '</noscript>';
			}

			$html = substr_replace( $html, $replacement, $style['offset'], strlen( $tag ) );
			if ( 'critical' === $mode ) {
				$this->critical_applied = true;
			}
		}

		return $html;
	}

	private function has_rejected_cookie(): bool {
		$rejected = get_noobrocket_cache_reject_cookies();
		if ( ! $rejected || ! $_COOKIE ) {
			return false;
		}

		foreach ( array_keys( $_COOKIE ) as $name ) {
			if ( 0 !== @preg_match( '#^(?:' . str_replace( '#', '\\#', $rejected ) . ')#', $name ) ) {
				return true;
			}
		}

		return false;
	}

	private function styles( string $html, string $url ): array {
		// Explicit opt-out blocks stay excluded without shifting original byte positions.
		$masked = preg_replace_callback(
			'#<!--\s*noptimize\s*-->.*?<!--\s*/\s*noptimize\s*-->#is',
			function ( $match ) {
				return str_repeat( ' ', strlen( $match[0] ) );
			},
			$html
		);
		$base = $url;

		$base_tag = $this->find_html_tag( $masked, 'base' );
		if ( $base_tag ) {
			$node = HtmlDomParser::str_get_html( $base_tag[0] )->findOne( 'base' );
			if ( $node && $node->getAttribute( 'href' ) ) {
				$base = \WP_Http::make_absolute_url( html_entity_decode( $node->getAttribute( 'href' ), ENT_QUOTES, 'UTF-8' ), $url );
			}
		}

		$styles          = [];
		$excluded        = (array) apply_filters( 'noobrocket_rucss_external_exclusions', [] );
		$skip_attrs      = (array) apply_filters( 'noobrocket_rucss_skip_styles_with_attr', [] );
		$excluded        = array_merge( $excluded, (array) apply_filters( 'noobrocket_exclude_async_css', [] ) );
		$minify_excluded = (array) apply_filters( 'noobrocket_exclude_css', get_noobrocket_option( 'exclude_css', [] ) );

		foreach ( $this->find_html_tags( $masked, 'link' ) as $match ) {
			list( $tag, $offset ) = $match;
			$node = HtmlDomParser::str_get_html( $tag )->findOne( 'link' );
			if ( ! $node || 'stylesheet' !== strtolower( trim( (string) $node->getAttribute( 'rel' ) ) ) ) {
				continue;
			}

			$media = strtolower( trim( (string) $node->getAttribute( 'media' ) ) );
			if ( ! in_array( $media, [ '', 'all', 'screen' ], true )
				|| $node->hasAttribute( 'disabled' )
				|| $node->hasAttribute( 'integrity' )
				|| $node->hasAttribute( 'onload' )
				|| $node->hasAttribute( 'data-no-minify' )
				|| $node->hasAttribute( 'data-minify' )
				|| $node->hasAttribute( 'data-noobrocket-local-css' )
			) {
				continue;
			}

			$skip = false;
			foreach ( $excluded as $pattern ) {
				if ( is_string( $pattern ) && '' !== $pattern && false !== strpos( $tag, $pattern ) ) {
					$skip = true;
					break;
				}
			}

			foreach ( $skip_attrs as $attribute ) {
				if ( is_string( $attribute ) && $node->hasAttribute( $attribute ) ) {
					$skip = true;
					break;
				}
			}

			if ( $skip ) {
				continue;
			}

			$href  = \WP_Http::make_absolute_url( html_entity_decode( (string) $node->getAttribute( 'href' ), ENT_QUOTES, 'UTF-8' ), $base );
			$parts = wp_parse_url( $href );
			if ( ! $parts
				|| ! in_array( $parts['scheme'] ?? '', [ 'http', 'https' ], true )
				|| empty( $parts['host'] )
				|| isset( $parts['user'] )
				|| isset( $parts['pass'] )
				|| isset( $parts['fragment'] )
			) {
				continue;
			}

			// Match the existing CSS minifier's path/host regular-expression exclusions.
			foreach ( $minify_excluded as $pattern ) {
				if ( ! is_string( $pattern ) || '' === $pattern ) {
					continue;
				}

				$regex = '#^(' . str_replace( '#', '\#', $pattern ) . ')$#';
				foreach ( [ $parts['path'] ?? '', $parts['host'], $parts['host'] . ( $parts['path'] ?? '' ) ] as $subject ) {
					$match = @preg_match( $regex, $subject ); // Invalid exclusions retain the original sheet.
					if ( false === $match || 1 === $match ) {
						$skip = true;
						break 2;
					}
				}
			}

			if ( $skip ) {
				continue;
			}

			// Native hosting owns Google font definitions and their local URLs.
			if ( $this->fonts->owns_font_link( $tag ) ) {
				continue;
			}

			$styles[] = [
				'href'   => $href,
				'media'  => $media,
				'nonce'  => (string) $node->getAttribute( 'nonce' ),
				'tag'    => $tag,
				'offset' => $offset
			];
		}

		return $styles;
	}

	private function mobile(): bool {
		if ( ! is_noobrocket_generate_caching_mobile_files() ) {
			return false;
		}

		$detect = new \NoobRocket\Dependencies\Detection\MobileDetect();
		$tablet = apply_filters( 'noobrocket_cache_mobile_files_tablet', 'desktop' );
		return $detect->isMobile() && ( ! $detect->isTablet() || 'mobile' === $tablet );
	}
}
