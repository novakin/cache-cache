<?php
declare(strict_types=1);

namespace NoobRocket\Engine\Optimization;

trait RegexTrait {
	/**
	 * Read complete attributes in source order, retaining original byte offsets.
	 *
	 * @param string $attributes Original opening-tag attributes.
	 * @return array Attribute captures and their byte offsets.
	 */
	protected function get_html_attributes( string $attributes ): array {
		$pattern = '/(?<name>[^\s"\'\/=<>`]+)(?:\s*=\s*(?:"(?<double>[^"]*)"|\'(?<single>[^\']*)\'|(?<unquoted>[^\s"\'<>`]+)))?/';
		preg_match_all( $pattern, $attributes, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL );
		return $matches;
	}

	/**
	 * Locate an insertion boundary without matching tag-like comments, raw text or attributes.
	 *
	 * @param string $html HTML content.
	 * @param string $name Element name.
	 * @param bool   $closing Whether to find the closing tag.
	 * @return array The original tag bytes and their offset, or an empty array.
	 */
	protected function find_html_tag( string $html, string $name, bool $closing = false ): array {
		foreach ( $this->find_html_tags( $html, $name, $closing ) as $tag ) {
			return $tag;
		}
		return [];
	}

	/**
	 * Yield active tag bytes and offsets without selecting inert or raw-text content.
	 *
	 * @param string $html HTML content.
	 * @param string $name Element name.
	 * @param bool   $closing Whether to find closing tags.
	 * @param bool   $include_noscript Whether to include content active when scripting is disabled.
	 * @return \Generator
	 */
	protected function find_html_tags( string $html, string $name, bool $closing = false, bool $include_noscript = false ): \Generator {
		// An unfinished tag/quoted attribute consumes the remaining document in
		// the HTML parser; tag-like bytes inside it cannot be insertion points.
		$attributes = '(?:"[^"]*(?:"|\z)|\'[^\']*(?:\'|\z)|[^\'">])*';
		$name       = preg_quote( $name, '#' );
		$target     = $closing ? '</' . $name . '\s*>' : '<' . $name . '(?=[\s/>])' . $attributes . '>';
		$raw_text   = 'script|style|textarea|title|xmp|iframe|noembed|noframes' . ( $include_noscript ? '' : '|noscript' );
		$pattern    = '#<!--.*?(?:-->|\z)(*SKIP)(*FAIL)'
			. '|<plaintext(?=[\s/>])' . $attributes . '>.*\z(*SKIP)(*FAIL)'
			. '|<(' . $raw_text . ')(?=[\s/>])' . $attributes . '>.*?(?:</\1\s*>|\z)(*SKIP)(*FAIL)'
			. '|' . $target
			. '|</?template(?=[\s/>])' . $attributes . '>'
			. '|</?[a-z][a-z0-9:-]*\b' . $attributes . '(?:>|\z)(*SKIP)(*FAIL)#is';

		$template_depth = 0;
		$offset = 0;
		while ( 1 === preg_match( $pattern, $html, $match, PREG_OFFSET_CAPTURE, $offset ) ) {
			$offset = $match[0][1] + strlen( $match[0][0] );
			if ( 0 === stripos( $match[0][0], '<template' ) ) {
				++$template_depth;
			} elseif ( 0 === stripos( $match[0][0], '</template' ) ) {
				$template_depth = max( 0, $template_depth - 1 );
			} elseif ( 0 === $template_depth ) {
				yield $match[0];
			}
		}
	}

	/**
	 * Array of replaced xmp tags
	 *
	 * @var array
	 */
	private $xmp_replace = [];

	/**
	 * Array of replaced html tags.
	 *
	 * @var array
	 */
	private $html_replace = [];

	/**
	 * Array of replaced svg tags
	 *
	 * @var array
	 */
	private $svg_replace = [];
	/**
	 * Finds nodes matching the pattern in the HTML.
	 *
	 * @param string $pattern Pattern to match.
	 * @param string $html HTML content.
	 * @param string $modifiers Regex modifiers.
	 * @param mixed  $flag Flag to use.
	 * @return array
	 */
	protected function find( string $pattern, string $html, string $modifiers = 'Umsi', $flag = PREG_SET_ORDER ) {
		preg_match_all( '/' . $pattern . '/' . $modifiers, $html, $matches, $flag );

		if ( empty( $matches ) ) {
			return [];
		}

		return $matches;
	}

	/**
	 * Hides unwanted blocks from the HTML to be parsed for optimization
	 *
	 * @since 3.1.4
	 *
	 * @param string $html HTML content.
	 * @return string
	 */
	protected function hide_comments( $html ) {
		$replace = preg_replace( '#<!--\s*noptimize\s*-->.*?<!--\s*/\s*noptimize\s*-->#is', '', $html );

		if ( null === $replace ) {
			return $html;
		}

		$replace = preg_replace( '/<!--(.*)-->/Uis', '', $replace );

		if ( null === $replace ) {
			return $html;
		}

		return $replace;
	}

	/**
	 * Hides scripts from the HTML to be parsed when removing CSS from it
	 *
	 * @since 3.10.2
	 *
	 * @param string $html HTML content.
	 *
	 * @return string
	 */
	protected function hide_scripts( $html ) {
		$replace = preg_replace( '#<script[^>]*>.*?<\/script\s*>#mis', '', $html );

		if ( null === $replace ) {
			return $html;
		}

		return $replace;
	}

	/**
	 * Hides <noscript> blocks from the HTML to be parsed.
	 *
	 * @param string $html HTML content.
	 *
	 * @return string
	 */
	protected function hide_noscripts( $html ) {
		$replace = preg_replace( '#<noscript[^>]*>.*?<\/noscript\s*>#mis', '', $html );

		if ( null === $replace ) {
			return $html;
		}

		return $replace;
	}


	/**
	 * Replace HTML comments.
	 *
	 * @param string $html HTML content.
	 *
	 * @return string
	 */
	protected function replace_html_comments( string $html ): string {
		$this->html_replace = [];

		$regex         = '#<!--.*-->#iUs';
		$replaced_html = preg_replace_callback( $regex, [ $this, 'replace_html_comment' ], $html );

		if ( empty( $replaced_html ) ) {
			return $html;
		}

		return $replaced_html;
	}

	/**
	 * Replace html with comment
	 *
	 * @param array $match HTML comment.
	 * @return string
	 */
	protected function replace_html_comment( $match ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.matchFound
		$key                        = sprintf( '<!-- %s -->', uniqid( 'NOOBROCKET_HTML_COMMENT_' ) );
		$this->html_replace[ $key ] = $match[0];
		return $key;
	}

	/**
	 * Restore html with comment
	 *
	 * @param string $html HTML content.
	 * @return string
	 */
	protected function restore_html_comments( $html ) {
		if ( ! is_string( $html ) || empty( $this->html_replace ) ) { // @phpstan-ignore-line For some reason, html could be null for some users, and we can't find a way to reproduce it.
			return $html;
		}

		return str_replace( array_keys( $this->html_replace ), array_values( $this->html_replace ), $html );
	}

	/**
	 * Replace <xmp> tags in the HTML with comment
	 *
	 * @since 3.12.3
	 *
	 * @param string $html HTML content.
	 * @return string
	 */
	protected function replace_xmp_tags( $html ) {
		$this->xmp_replace = [];
		$regex             = '#<xmp.*>.*</xmp>#Uis';
		$replaced_html     = preg_replace_callback( $regex, [ $this, 'replace_xmp' ], $html );

		if ( empty( $replaced_html ) ) {
			return $html;
		}

		return $replaced_html;
	}

	/**
	 * Replace <svg> tags in the HTML with comment
	 *
	 * @since 3.12.5.3
	 *
	 * @param string $html HTML content.
	 * @return string
	 */
	protected function replace_svg_tags( $html ) {
		$this->svg_replace = [];
		$regex             = '#<\s*svg.*>.*<\s*\\\\?/\s*svg\s*>#Uis';
		$replaced_html     = preg_replace_callback( $regex, [ $this, 'replace_svg' ], $html );

		if ( empty( $replaced_html ) ) {
			return $html;
		}

		return $replaced_html;
	}

	/**
	 * Replace svg with comment
	 *
	 * @since 3.12.3
	 *
	 * @param array $match svg tag.
	 * @return string
	 */
	protected function replace_svg( $match ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.matchFound
		$key                       = sprintf( '<!-- %s -->', uniqid( 'NOOBROCKET_SVG_' ) );
		$this->svg_replace[ $key ] = $match[0];
		return $key;
	}

	/**
	 * Replace xmp with comment
	 *
	 * @since 3.12.3
	 *
	 * @param array $match xmp tag.
	 * @return string
	 */
	protected function replace_xmp( $match ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.matchFound
		$key                       = sprintf( '<!-- %s -->', uniqid( 'NOOBROCKET_XMP_' ) );
		$this->xmp_replace[ $key ] = $match[0];
		return $key;
	}

	/**
	 * Restore <svg> tags
	 *
	 * @since 3.12.5.3
	 *
	 * @param string $html HTML content.
	 * @return string
	 */
	protected function restore_svg_tags( $html ) {
		if ( empty( $this->svg_replace ) ) {
			return $html;
		}

		return str_replace( array_keys( $this->svg_replace ), array_values( $this->svg_replace ), $html );
	}

	/**
	 * Restore <xmp> tags
	 *
	 * @since 3.12.3
	 *
	 * @param string $html HTML content.
	 * @return string
	 */
	protected function restore_xmp_tags( $html ) {
		if ( empty( $this->xmp_replace ) ) {
			return $html;
		}

		return str_replace( array_keys( $this->xmp_replace ), array_values( $this->xmp_replace ), $html );
	}

	/**
	 * Checks if the page HTML is valid or not.
	 * Valid here means that it has a closing title tag.
	 *
	 * @param string $html Page HTML.
	 *
	 * @return bool
	 */
	private function html_has_title_tag( string $html ) {
		return (bool) preg_match( '#</title>#iU', $html );
	}
}
