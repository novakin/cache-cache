<?php
declare(strict_types=1);

namespace NoobRocket\Engine\Optimization\Bloat;

use NoobRocket\Admin\Options_Data;
use NoobRocket\Event_Management\Subscriber_Interface;

/** Opt-in removal of unused WordPress frontend assets. */
class Subscriber implements Subscriber_Interface {
	private const OPTIONS = [ 'remove_dashicons', 'remove_embed_assets', 'remove_block_library' ];
	private $options;
	private $rendered_blocks = false;

	public function __construct( Options_Data $options ) {
		$this->options = $options;
	}

	public static function get_subscribed_events(): array {
		return [
			'wp' => 'remove_embed_discovery',
			'wp_print_styles' => [ 'remove_dashicons', 900 ],
			'render_block' => 'remember_block',
			'noobrocket_buffer' => [ 'remove_unused_assets', 0 ],
			'option_noobrocket_settings' => 'normalize_options',
			'pre_add_option_noobrocket_settings' => 'normalize_options',
			'pre_update_option_noobrocket_settings' => 'normalize_options',
			'noobrocket_first_install_options' => 'normalize_options',
			'noobrocket_input_sanitize' => 'normalize_options',
			'noobrocket_mcp_options_allowlist' => 'allow_options',
		];
	}

	public function normalize_options( $options ) {
		if ( ! is_array( $options ) ) {
			return $options;
		}
		foreach ( self::OPTIONS as $option ) {
			$options[ $option ] = in_array( $options[ $option ] ?? 0, [ true, 1, '1' ], true ) ? 1 : 0;
		}
		return $options;
	}

	public function allow_options( array $options ): array {
		return array_values( array_unique( array_merge( $options, self::OPTIONS ) ) );
	}

	public function remember_block( string $content ): string {
		$this->rendered_blocks = true;
		return $content;
	}

	public function remove_embed_discovery(): void {
		if ( ! $this->can_optimize() || ! $this->enabled( 'remove_embed_assets' ) ) {
			return;
		}
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
	}

	public function remove_dashicons(): void {
		if ( ! $this->can_optimize() || is_user_logged_in() || ! $this->enabled( 'remove_dashicons' ) ) {
			return;
		}
		if ( ! $this->has_dependents( 'dashicons', wp_styles() ) ) {
			wp_dequeue_style( 'dashicons' );
		}
	}

	/** Wait for complete markup before deciding whether block or embed assets are unused. */
	public function remove_unused_assets( string $html ): string {
		if ( ! $this->can_optimize() || '' === $html ) {
			return $html;
		}
		$styles = [];
		$scripts = [];
		if ( $this->enabled( 'remove_block_library' ) && $this->can_remove_block_styles( $html ) ) {
			foreach ( [ 'wp-block-library', 'wp-block-library-theme' ] as $handle ) {
				if ( ! $this->has_dependents( $handle, wp_styles() ) ) {
					$styles[] = $handle;
				}
			}
		}
		if ( $this->enabled( 'remove_embed_assets' ) && false === stripos( $html, 'wp-embedded-content' ) && ! $this->has_dependents( 'wp-embed', wp_scripts() ) ) {
			$scripts[] = 'wp-embed';
		}
		if ( ! $styles && ! $scripts ) {
			return $html;
		}
		$result = preg_replace_callback(
			'#<!--[\s\S]*?-->|<(script|style|textarea|noscript)\b[^>]*>.*?</\1\s*>|<link\b[^>]*>#is',
			static function ( $match ) use ( $styles, $scripts ) {
				$tag = $match[0];
				if ( 0 === strpos( $tag, '<!--' ) || ! preg_match( '/^<(link|style|script)\b[^>]*\s+id\s*=\s*(["\'])([^"\']*)\2/i', $tag, $id ) ) {
					return $tag;
				}
				$type = strtolower( $id[1] );
				foreach ( $styles as $handle ) {
					if ( ( 'link' === $type && $handle . '-css' === $id[3] ) || ( 'style' === $type && $handle . '-inline-css' === $id[3] ) ) {
						return '';
					}
				}
				foreach ( $scripts as $handle ) {
					if ( 'script' === $type && in_array( $id[3], [ $handle . '-js', $handle . '-js-before', $handle . '-js-after' ], true ) ) {
						return '';
					}
				}
				return $tag;
			},
			$html
		);
		return is_string( $result ) ? $result : $html;
	}

	private function can_optimize(): bool {
		return ! is_admin() && ! is_feed() && ! is_embed() && ! is_preview() && ! noobrocket_bypass()
			&& ! ( defined( 'REST_REQUEST' ) && REST_REQUEST )
			&& ! ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST )
			&& ! ( defined( 'DOING_AJAX' ) && DOING_AJAX );
	}

	private function enabled( string $option ): bool {
		return in_array( $this->options->get( $option, 0 ), [ true, 1, '1' ], true );
	}

	private function can_remove_block_styles( string $html ): bool {
		global $wp_query;
		if ( $this->rendered_blocks || ! function_exists( 'wp_is_block_theme' ) || wp_is_block_theme() || is_active_widget( false, false, 'block', true ) ) {
			return false;
		}
		if ( ! isset( $wp_query->posts ) || ! is_array( $wp_query->posts ) || ! $wp_query->posts ) {
			return false;
		}
		foreach ( $wp_query->posts as $post ) {
			if ( ! $post instanceof \WP_Post || has_blocks( $post->post_content ) || false !== strpos( $post->post_content, '[' ) ) {
				return false;
			}
		}
		$markup = preg_replace( '#<!--[\s\S]*?-->|<(script|style|textarea)\b[^>]*>.*?</\1\s*>#is', '', $html );
		return is_string( $markup ) && ! preg_match( '/<[^>]+\sclass\s*=\s*(["\'])[^"\']*\bwp-block(?:-|\b)[^"\']*\1/i', $markup );
	}

	/** Keep an asset when another queued handle requires it, including indirect dependencies. */
	private function has_dependents( string $handle, $assets ): bool {
		foreach ( $assets->queue as $queued ) {
			if ( $queued === $handle ) {
				continue;
			}
			$pending = [ $queued ];
			$seen = [];
			while ( $pending ) {
				$current = array_pop( $pending );
				if ( isset( $seen[ $current ] ) || ! isset( $assets->registered[ $current ] ) ) {
					continue;
				}
				$seen[ $current ] = true;
				foreach ( $assets->registered[ $current ]->deps as $dependency ) {
					if ( $dependency === $handle ) {
						return true;
					}
					$pending[] = $dependency;
				}
			}
		}
		return false;
	}
}
