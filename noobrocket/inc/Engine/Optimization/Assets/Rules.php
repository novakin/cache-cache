<?php
namespace NoobRocket\Engine\Optimization\Assets;

/** Per-handle rules. Only WordPress-enqueued assets participate. */
class Rules {

	const OPTION = 'noobrocket_asset_rules';
	const LIMIT  = 100;
	private $edit_lock_held = false;

	/** A review identifies rule content, including its public base, without writing metadata on render. */
	public static function draft_revision( array $draft ): string {
		return hash( 'sha256', wp_json_encode( [ 'base' => $draft['base'] ?? [], 'rules' => $draft['rules'] ?? [] ] ) );
	}

	/** Validate the whole draft; invalid scopes must never become site-wide rules. */
	public function validate( $rules ) {
		if ( ! is_array( $rules ) || count( $rules ) > self::LIMIT ) {
			return new \WP_Error( 'asset_rules', __( 'Use no more than 100 asset rules.', 'noobrocket' ) );
		}

		$clean = [];
		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) || ! in_array( $rule['type'] ?? '', [ 'style', 'script' ], true )
				|| ! is_string( $rule['handle'] ?? null ) || ! preg_match( '/^[a-zA-Z0-9_.:-]{1,200}$/D', $rule['handle'] )
				|| ! in_array( $rule['scope'] ?? '', [ 'everywhere', 'post', 'post_type', 'template' ], true ) ) {
				return new \WP_Error( 'asset_rule', __( 'Choose an asset type, registered handle and valid scope.', 'noobrocket' ) );
			}

			$value = $rule['value'] ?? '';
			if ( ! is_scalar( $value ) ) {
				return new \WP_Error( 'asset_value', __( 'Enter a valid scope value.', 'noobrocket' ) );
			}
			$value = trim( (string) $value );
			if ( 'post' === $rule['scope'] && ! preg_match( '/^[1-9][0-9]*$/D', $value ) ) {
				return new \WP_Error( 'asset_post', __( 'A page or post rule needs its numeric post ID.', 'noobrocket' ) );
			}
			if ( 'post_type' === $rule['scope'] && ( '' === $value || sanitize_key( $value ) !== $value || ! post_type_exists( $value ) ) ) {
				return new \WP_Error( 'asset_post_type', __( 'Choose a registered post type.', 'noobrocket' ) );
			}
			if ( 'template' === $rule['scope'] && ( '' === $value || strlen( $value ) > 200 || sanitize_text_field( $value ) !== $value ) ) {
				return new \WP_Error( 'asset_template', __( 'Enter the template filename, or default.', 'noobrocket' ) );
			}

			$exceptions = $rule['exceptions'] ?? [];
			if ( is_string( $exceptions ) ) {
				$exceptions = '' === trim( $exceptions ) ? [] : preg_split( '/[\s,]+/', trim( $exceptions ) );
			}
			if ( ! is_array( $exceptions ) || count( $exceptions ) > 100 ) {
				return new \WP_Error( 'asset_exceptions', __( 'Use no more than 100 exception post IDs.', 'noobrocket' ) );
			}
			foreach ( $exceptions as $id ) {
				if ( ! is_scalar( $id ) || ! preg_match( '/^[1-9][0-9]*$/D', (string) $id ) ) {
					return new \WP_Error( 'asset_exception', __( 'Exceptions must be numeric post IDs.', 'noobrocket' ) );
				}
			}

			$clean[] = [
				'type'       => $rule['type'],
				'handle'     => $rule['handle'],
				'scope'      => $rule['scope'],
				'value'      => 'everywhere' === $rule['scope'] ? '' : $value,
				'exceptions' => array_values( array_unique( array_map( 'intval', $exceptions ) ) ),
			];
		}
		return $clean;
	}

	public function get(): array {
		$rules = $this->validate( get_option( self::OPTION, [] ) );
		return is_wp_error( $rules ) ? [] : $rules;
	}

	/** Publication and restores share WordPress's native short-operation lock. */
	public function with_edit_lock( callable $operation ) {
		if ( $this->edit_lock_held ) {
			return $operation();
		}
		$result = $this->acquire_edit_lock();
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		try {
			return $operation();
		} finally {
			$this->release_edit_lock();
		}
	}

	public function has_edit_lock(): bool {
		return $this->edit_lock_held;
	}

	/** Native settings writes share the publication lock until their commit or request shutdown. */
	public function acquire_edit_lock() {
		if ( $this->edit_lock_held ) {
			return true;
		}
		if ( ! class_exists( 'WP_Upgrader', false ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		}
		if ( ! \WP_Upgrader::create_lock( 'noobrocket_assets', 5 * MINUTE_IN_SECONDS ) ) {
			return new \WP_Error( 'asset_busy', __( 'Another rule publication or restore is in progress. Try again shortly.', 'noobrocket' ) );
		}
		$this->edit_lock_held = true;
		wp_cache_delete( self::OPTION, 'options' );
		wp_cache_delete( Snapshots::OPTION, 'options' );
		wp_cache_delete( 'noobrocket_settings', 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		return true;
	}

	public function release_edit_lock(): void {
		if ( $this->edit_lock_held ) {
			$this->edit_lock_held = false;
			\WP_Upgrader::release_lock( 'noobrocket_assets' );
		}
	}

	public function matches( array $rule ): bool {
		$id = is_singular() ? get_queried_object_id() : 0;
		if ( $id && in_array( $id, $rule['exceptions'], true ) ) {
			return false;
		}
		switch ( $rule['scope'] ) {
			case 'everywhere':
				return true;
			case 'post':
				return $id && (int) $rule['value'] === $id;
			case 'post_type':
				return is_singular( $rule['value'] ) || is_post_type_archive( $rule['value'] );
			case 'template':
				return $id && ( get_page_template_slug( $id ) ?: 'default' ) === $rule['value'];
		}
		return false;
	}

	/**
	 * Remove requested roots, while retaining everything needed by surviving roots.
	 * Include the surviving consumers so preview can explain retained dependencies.
	 */
	public function apply( \WP_Dependencies $assets, array $handles ): array {
		$selected  = array_fill_keys( $handles, true );
		$required  = [];
		$retained_by = [];
		$remaining = array_diff( $assets->queue, $handles );
		$stack     = array_merge( $remaining, $assets->done );

		while ( $stack ) {
			$handle = array_pop( $stack );
			if ( isset( $required[ $handle ] ) ) {
				continue;
			}
			$required[ $handle ] = true;
			if ( isset( $assets->registered[ $handle ] ) ) {
				foreach ( $assets->registered[ $handle ]->deps as $dependency ) {
					$retained_by[ $dependency ][ $handle ] = true;
					$stack[] = $dependency;
				}
			}
		}

		$removed   = [];
		$protected = [];
		foreach ( array_keys( $selected ) as $handle ) {
			if ( isset( $required[ $handle ] ) ) {
				$protected[] = $handle;
				continue;
			}
			if ( in_array( $handle, $assets->queue, true ) ) {
				$assets->dequeue( $handle );
				$removed[] = $handle;
			}
		}
		return [
			'removed' => $removed,
			'protected' => $protected,
			'retained_by' => array_map( 'array_keys', $retained_by ),
		];
	}
}
