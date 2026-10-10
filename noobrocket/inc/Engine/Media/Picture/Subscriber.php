<?php
declare(strict_types=1);

namespace NoobRocket\Engine\Media\Picture;

use NoobRocket\Admin\Options_Data;
use NoobRocket\Event_Management\Subscriber_Interface;

/** Browser-selected image formats, with one shared HTML cache. */
class Subscriber implements Subscriber_Interface {

	private $options;
	private $derivatives = [];

	public function __construct( Options_Data $options ) {
		$this->options = $options;
	}

	public static function get_subscribed_events() {
		return [ 'noobrocket_buffer' => [ 'convert', 1 ] ];
	}

	/** Change only image tokens; preserve all original fallback markup. */
	public function convert( string $html ): string {
		if ( ! $this->options->get( 'image_delivery', 0 ) || noobrocket_bypass() ) {
			return $html;
		}
		// Reuse duplicate URL/format resolutions only within this one HTML render.
		$this->derivatives = [];

		// Quoted attribute values may contain literal markup or greater-than signs.
		$pattern = '~<!--.*?(?:-->|$)|<![^>]*>|<\s*(/?)\s*([a-z][a-z0-9:-]*)\b(?:"[^"]*"|\x27[^\x27]*\x27|[^\x27">])*?>~is';
		preg_match_all( $pattern, $html, $tokens, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );
		$protected = [];
		$raw       = '';
		$offset    = 0;
		$output    = '';

		foreach ( $tokens as $token ) {
			$tag   = $token[0][0];
			$start = $token[0][1];
			$name  = isset( $token[2] ) ? strtolower( $token[2][0] ) : '';
			$close = ! empty( $token[1][0] );
			if ( $raw ) {
				if ( $close && $name === $raw ) {
					$raw = '';
				}
				continue;
			}
			if ( in_array( $name, [ 'script', 'style', 'noscript', 'textarea', 'title', 'xmp', 'iframe' ], true ) && ! $close ) {
				$raw = $name;
				continue;
			}
			if ( in_array( $name, [ 'picture', 'template', 'svg', 'math' ], true ) ) {
				if ( $close ) {
					if ( isset( $protected[ $name ] ) && --$protected[ $name ] <= 0 ) {
						unset( $protected[ $name ] );
					}
				} else {
					$protected[ $name ] = ( $protected[ $name ] ?? 0 ) + 1;
				}
				continue;
			}
			if ( 'img' !== $name || $close || $protected ) {
				continue;
			}
			$sources = $this->sources( $tag );
			if ( ! $sources ) {
				continue;
			}
			$output .= substr( $html, $offset, $start - $offset ) . '<picture data-noobrocket-picture>';
			foreach ( $sources as $source ) {
				$output .= '<source type="' . $source['type'] . '" srcset="' . esc_attr( $source['srcset'] ) . '"';
				if ( isset( $source['sizes'] ) ) {
					$output .= ' sizes="' . esc_attr( $source['sizes'] ) . '"';
				}
				$output .= '>';
			}
			$output .= $tag . '</picture>';
			$offset  = $start + strlen( $tag );
		}
		return $output . substr( $html, $offset );
	}

	/** Read attributes without serializing or changing the original token. */
	private static function attributes( string $tag ): array {
		preg_match_all( '~\s+([^\s=/>]+)(?:\s*=\s*(?:"([^"]*)"|\x27([^\x27]*)\x27|([^\s>]+)))?~', $tag, $matches, PREG_SET_ORDER );
		$attributes = [];
		foreach ( $matches as $match ) {
			$name = strtolower( $match[1] );
			if ( array_key_exists( $name, $attributes ) ) {
				return []; // Ambiguous markup is left to the browser.
			}
			$attributes[ $name ] = html_entity_decode( $match[2] ?? $match[3] ?? $match[4] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			// Unmatched regex groups are empty strings on older PHP versions.
			foreach ( [ 2, 3, 4 ] as $index ) {
				if ( isset( $match[ $index ] ) && '' !== $match[ $index ] ) {
					$attributes[ $name ] = html_entity_decode( $match[ $index ], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
					break;
				}
			}
		}
		return $attributes;
	}

	private function sources( string $tag ): array {
		$attributes = self::attributes( $tag );
		if ( empty( $attributes['src'] ) || isset( $attributes['data-src'] ) || isset( $attributes['data-lazy-src'] ) || isset( $attributes['data-srcset'] ) ) {
			return [];
		}
		$candidates = [ [ $attributes['src'], '' ] ];
		if ( ! empty( $attributes['srcset'] ) ) {
			$candidates = [];
			foreach ( explode( ',', $attributes['srcset'] ) as $candidate ) {
				if ( ! preg_match( '~^\s*(\S+?)(?:\s+([1-9][0-9]*w|(?:[0-9]+(?:\.[0-9]+)?)x))?\s*$~', $candidate, $match ) ) {
					return [];
				}
				$candidates[] = [ $match[1], $match[2] ?? '' ];
			}
		}
		$sources = [];
		foreach ( [ 'avif', 'webp' ] as $format ) {
			$converted = [];
			foreach ( $candidates as $candidate ) {
				$url = $this->derivative( $candidate[0], $format );
				if ( ! $url ) {
					continue 2;
				}
				$converted[] = $url . ( $candidate[1] ? ' ' . $candidate[1] : '' );
			}
			$source = [ 'type' => 'image/' . $format, 'srcset' => implode( ', ', $converted ) ];
			if ( isset( $attributes['sizes'] ) ) {
				$source['sizes'] = $attributes['sizes'];
			}
			$sources[] = $source;
		}
		return $sources;
	}

	/** Resolve only explicit local URL roots, with real-path containment. No HTTP. */
	private function derivative( string $url, string $format ) {
		$key = $url . '\n' . $format;
		if ( ! array_key_exists( $key, $this->derivatives ) ) {
			$this->derivatives[ $key ] = $this->resolve_derivative( $url, $format );
		}
		return $this->derivatives[ $key ];
	}

	private function resolve_derivative( string $url, string $format ) {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || ( isset( $parts['scheme'] ) && ! in_array( strtolower( $parts['scheme'] ), [ 'http', 'https' ], true ) ) ) {
			return false;
		}
		$path = $parts['path'] ?? '';
		if ( ! preg_match( '~\.(?:jpe?g|png)$~i', $path ) || false !== strpos( rawurldecode( $path ), '\\' ) ) {
			return false;
		}
		$absolute = isset( $parts['host'] ) ? $url : ( '/' === substr( $url, 0, 1 ) ? home_url( $url ) : '' );
		if ( ! $absolute ) {
			return false;
		}
		$absolute = preg_replace( '~[?#].*$~', '', preg_replace( '~^https?:~i', '', $absolute ) );
		$uploads  = wp_upload_dir( null, false );
		$roots    = [ preg_replace( '~^https?:~i', '', trailingslashit( $uploads['baseurl'] ) ) => trailingslashit( $uploads['basedir'] ), preg_replace( '~^https?:~i', '', trailingslashit( content_url() ) ) => trailingslashit( WP_CONTENT_DIR ) ];
		$roots    = apply_filters( 'noobrocket_url_to_path_associations', $roots );
		foreach ( $roots as $root_url => $directory ) {
			$root_url = trailingslashit( preg_replace( '~^https?:~i', '', $root_url ) );
			if ( 0 !== strpos( $absolute, $root_url ) ) {
				continue;
			}
			$relative = rawurldecode( substr( $absolute, strlen( $root_url ) ) );
			if ( preg_match( '~(?:^|/)\.\.?(?:/|$)|[\x00-\x1f\\\\]~', $relative ) ) {
				return false;
			}
			$root = realpath( $directory );
			$file = realpath( trailingslashit( $directory ) . $relative );
			if ( ! $root || ! $file || ! is_file( $file ) || 0 !== strpos( wp_normalize_path( $file ), trailingslashit( wp_normalize_path( $root ) ) ) ) {
				return false;
			}
			foreach ( [ $file . '.' . $format, preg_replace( '~\.[^.]+$~', '.' . $format, $file ) ] as $sidecar ) {
				$resolved = realpath( $sidecar );
				if ( ! $resolved || ! is_file( $resolved ) || ! is_readable( $resolved ) || 0 !== strpos( wp_normalize_path( $resolved ), trailingslashit( wp_normalize_path( $root ) ) )
					|| ! filesize( $resolved ) || filesize( $resolved ) > floor( filesize( $file ) * ( 1 - Generator::MINIMUM_SAVING ) ) ) {
					continue;
				}
				$extension = $sidecar === $file . '.' . $format ? '$0.' . $format : '.' . $format;
				return preg_replace( '~\.(?:jpe?g|png)(?=[?#]|$)~i', $extension, $url );
			}
		}
		return false;
	}

	/** Use delivered picture sources for older stored LCP records, including CDN URLs. */
	public static function synchronize_lcp( object $lcp, string $html ): object {
		if ( ! in_array( $lcp->type ?? '', [ 'img', 'img-srcset', 'picture' ], true ) || empty( $lcp->src ) ) {
			return $lcp;
		}
		preg_match_all( '~<picture data-noobrocket-picture>(.*?)</picture>~is', $html, $pictures );
		foreach ( $pictures[1] as $picture ) {
			if ( ! preg_match( '~<img\b(?:"[^"]*"|\x27[^\x27]*\x27|[^\x27">])*?>~is', $picture, $image ) ) {
				continue;
			}
			$attributes = self::attributes( $image[0] );
			if ( ( $attributes['src'] ?? '' ) !== $lcp->src ) {
				continue;
			}
			$lcp          = clone $lcp;
			$lcp->type    = 'picture';
			$lcp->sources = [];
			preg_match_all( '~<source\b[^>]*>~i', $picture, $sources );
			foreach ( $sources[0] as $source ) {
				$lcp->sources[] = (object) ( self::attributes( $source ) + [ 'media' => '', 'sizes' => '' ] );
			}
			break;
		}
		return $lcp;
	}
}
