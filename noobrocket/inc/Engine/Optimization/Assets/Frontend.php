<?php
namespace NoobRocket\Engine\Optimization\Assets;

use NoobRocket\Event_Management\Subscriber_Interface;

/** Applies published rules, or the current administrator's private draft. */
class Frontend implements Subscriber_Interface {

	const PREVIEW = 'noobrocket_asset_preview';
	const REVISION = 'noobrocket_asset_revision';
	private $rules;
	private $preview = false;
	private $inventory = [];
	private $preview_rules = [];
	private $preview_revision = '';

	public function __construct( Rules $rules ) {
		$this->rules = $rules;
	}

	public static function get_subscribed_events(): array {
		return [
			'init'                    => [ 'prepare_preview', 1 ],
			'wp_enqueue_scripts'      => [ 'apply_rules', 999 ],
			'wp_print_styles'         => [ 'apply_rules', 999 ],
			'wp_print_scripts'        => [ 'apply_rules', 999 ],
			'wp_print_footer_scripts' => [ 'apply_rules', 0 ],
			'wp_robots'               => 'preview_robots',
			'noobrocket_override_donotcachepage' => [ 'prevent_preview_cache', PHP_INT_MAX ],
			'wp_footer'               => [ 'preview_notice', 99 ],
			'shutdown'                => 'save_inventory',
		];
	}

	public static function user_key( string $name ): string {
		return 'noobrocket_asset_' . $name . '_' . get_current_blog_id();
	}

	public function prepare_preview(): void {
		if ( ! isset( $_GET[ self::PREVIEW ] ) || is_admin() ) {
			return;
		}

		// Invalid preview links must not become public indexable pages either.
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		$nonce = is_string( $_GET[ self::PREVIEW ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::PREVIEW ] ) ) : '';
		if ( ! current_user_can( 'noobrocket_manage_options' ) || ! wp_verify_nonce( $nonce, self::PREVIEW ) ) {
			wp_die( esc_html__( 'Sign in as a Cache-Cache administrator and open a fresh preview link.', 'noobrocket' ), '', [ 'response' => 403 ] );
		}
		$draft = get_user_meta( get_current_user_id(), self::user_key( 'draft' ), true );
		$published = $this->rules->get();
		$draft = is_array( $draft ) && isset( $draft['rules'], $draft['base'] ) ? $draft : [ 'base' => $published, 'rules' => $published ];
		$revision = isset( $_GET[ self::REVISION ] ) && is_string( $_GET[ self::REVISION ] ) ? wp_unslash( $_GET[ self::REVISION ] ) : '';
		$validated = $this->rules->validate( $draft['rules'] );
		if ( is_wp_error( $validated ) || ! hash_equals( Rules::draft_revision( $draft ), $revision ) ) {
			wp_die( esc_html__( 'This preview belongs to an older private draft. Open a fresh preview from Asset Manager.', 'noobrocket' ), '', [ 'response' => 409 ] );
		}
		$this->preview_rules = $validated;
		$this->preview_revision = $revision;
		$this->preview = true;
	}

	public function preview_robots( array $robots ): array {
		if ( $this->preview ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
			unset( $robots['index'], $robots['follow'] );
		}
		return $robots;
	}

	public function prevent_preview_cache( bool $override ): bool {
		return isset( $_GET[ self::PREVIEW ] ) ? false : $override;
	}

	public function apply_rules(): void {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || is_feed() || is_embed() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		$rules = $this->rules->get();
		if ( $this->preview ) {
			$rules = $this->preview_rules;
		}
		if ( ! $rules && ! $this->preview ) {
			return;
		}

		foreach ( [ 'style' => wp_styles(), 'script' => wp_scripts() ] as $type => $assets ) {
			$handles = [];
			foreach ( $rules as $rule ) {
				if ( $type === $rule['type'] && $this->rules->matches( $rule ) ) {
					$handles[] = $rule['handle'];
				}
			}
			$queued = $assets->queue;
			$result = $this->rules->apply( $assets, $handles );
			if ( ! $this->preview ) {
				continue;
			}
			// Include dependencies even when they have no direct queue entry (e.g. jQuery).
			$pending = $queued;
			$seen    = [];
			while ( $pending ) {
				$handle = array_pop( $pending );
				if ( isset( $seen[ $handle ] ) || ! isset( $assets->registered[ $handle ] ) ) {
					continue;
				}
				$seen[ $handle ] = true;
				$asset          = $assets->registered[ $handle ];
				foreach ( $asset->deps as $dependency ) {
					$pending[] = $dependency;
				}
				$status = in_array( $handle, $result['removed'], true ) ? 'removed' : 'loaded';
				if ( in_array( $handle, $result['protected'], true ) ) {
					$status = 'required dependency';
				}
				$this->inventory[ $type . ':' . $handle ] = [
					'type'   => $type,
					'handle' => $handle,
					'src'    => is_string( $asset->src ) ? $asset->src : '',
					'deps'   => $asset->deps,
					'status' => $status,
					'retained_by' => $result['retained_by'][ $handle ] ?? [],
					'printed' => in_array( $handle, $assets->done, true ),
				];
			}
		}
	}

	public function save_inventory(): void {
		if ( ! $this->preview ) {
			return;
		}
		$home = wp_parse_url( home_url( '/' ) );
		$url  = $home['scheme'] . '://' . $home['host'] . ( isset( $home['port'] ) ? ':' . $home['port'] : '' );
		$url .= isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		update_user_meta( get_current_user_id(), self::user_key( 'inventory' ), [
			'url'    => esc_url_raw( remove_query_arg( self::REVISION, remove_query_arg( self::PREVIEW, $url ) ) ),
			'time'   => time(),
			'draft_revision' => $this->preview_revision,
			'assets' => array_values( $this->inventory ),
		] );
	}

	public function preview_notice(): void {
		if ( ! $this->preview ) {
			return;
		}
		printf( '<p role="status" style="position:fixed;bottom:16px;left:16px;z-index:999999;background:#fff;color:#17243a;padding:12px 16px;border:2px solid #4169e1;box-shadow:0 2px 8px #0003;font:14px/1.5 sans-serif;max-width:calc(100vw - 64px)">%s <a href="%s">%s</a></p>',
			esc_html__( 'Cache-Cache private asset preview. Visitors still use published rules.', 'noobrocket' ),
			esc_url( admin_url( 'options-general.php?page=noobrocket' ) . '#assets' ),
			esc_html__( 'Return to Asset Manager', 'noobrocket' )
		);
	}
}
