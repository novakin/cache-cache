<?php
namespace NoobRocket\Engine\Optimization\Assets;

use NoobRocket\Engine\Abilities\Options\AllowedOptions;
use NoobRocket\Event_Management\Subscriber_Interface;

/** Bounded, local history of restorable settings; never copies credentials or site keys. */
class Snapshots implements Subscriber_Interface {

	const OPTION = 'noobrocket_settings_snapshots';
	const LIMIT  = 5;
	private $rules;
	private $restoring = false;
	private $ordinary_lock = false;
	private $ordinary_depth = 0;

	public function __construct( Rules $rules ) {
		$this->rules = $rules;
	}

	public static function get_subscribed_events(): array {
		return [
			'pre_update_option_noobrocket_settings' => [ 'before_settings', PHP_INT_MAX, 2 ],
			'pre_update_option_noobrocket_asset_rules' => [ 'before_rules', PHP_INT_MAX, 2 ],
			'update_option_noobrocket_settings' => [ 'settings_updated', PHP_INT_MAX, 2 ],
			'update_option_noobrocket_asset_rules' => [ 'rules_updated', PHP_INT_MAX, 2 ],
			'add_option_noobrocket_asset_rules' => [ 'first_rules_added', PHP_INT_MAX, 2 ],
			'shutdown' => 'release_pending_lock',
		];
	}

	/** Use the existing supported-setting allowlist, retaining mobile options too. */
	private function setting_keys(): array {
		$keys = array_merge( ( new AllowedOptions() )->get(), [ 'cache_mobile', 'do_caching_mobile_files' ] );
		$keys = array_filter( $keys, function ( $key ) {
			// Site integrations may extend the allowlist. Credentials still stay out of history.
			return is_string( $key ) && ! preg_match( '/(?:key|secret|password|token|email|license|version)/i', $key );
		} );
		return $keys;
	}

	public function restorable( array $settings ): array {
		return array_intersect_key( noobrocket_free_settings( $settings ), array_fill_keys( $this->setting_keys(), true ) );
	}

	public function all(): array {
		$history = get_option( self::OPTION, [] );
		return is_array( $history ) ? $history : [];
	}

	/** A changed-settings save adds one snapshot before applying the same limit. */
	public function save_may_evict( string $id ): bool {
		foreach ( array_slice( $this->all(), self::LIMIT - 1 ) as $snapshot ) {
			if ( ( $snapshot['id'] ?? '' ) === $id ) {
				return true;
			}
		}
		return false;
	}

	/** Compare only the values restore can change, against the currently saved state. */
	public function changes( array $snapshot ): array {
		$current = $this->restorable( (array) get_option( 'noobrocket_settings', [] ) );
		$target  = $this->restorable( (array) ( $snapshot['settings'] ?? [] ) );
		$settings = [];
		foreach ( array_unique( array_merge( array_keys( $current ), array_keys( $target ) ) ) as $key ) {
			if ( ( $current[ $key ] ?? null ) !== ( $target[ $key ] ?? null ) ) {
				$settings[ $key ] = [ 'current' => $current[ $key ] ?? null, 'snapshot' => $target[ $key ] ?? null ];
			}
		}
		$current_rules = $this->rules->get();
		$target_rules  = $this->rules->validate( $snapshot['rules'] ?? [] );
		$target_rules  = is_wp_error( $target_rules ) ? [] : $target_rules;
		$removed = [];
		$remaining = $target_rules;
		foreach ( $current_rules as $rule ) {
			$match = array_search( $rule, $remaining, true );
			if ( false === $match ) {
				$removed[] = $rule;
			} else {
				unset( $remaining[ $match ] );
			}
		}
		return [ 'settings' => $settings, 'removed_rules' => $removed, 'added_rules' => array_values( $remaining ), 'rules_changed' => $current_rules !== $target_rules ];
	}

	public function before_settings( $new, $old ) {
		return $this->guard_change( $new, $old, 'noobrocket_settings' );
	}

	public function before_rules( $new, $old ) {
		return $this->guard_change( $new, $old, Rules::OPTION );
	}

	private function guard_change( $new, $old, string $option ) {
		if ( $new === $old || $this->restoring ) {
			return $new;
		}
		if ( $this->rules->has_edit_lock() ) {
			if ( $this->ordinary_lock ) {
				++$this->ordinary_depth;
			}
			return $new;
		}
		$result = $this->rules->acquire_edit_lock();
		if ( is_wp_error( $result ) ) {
			if ( function_exists( 'add_settings_error' ) ) {
				add_settings_error( 'general', 'noobrocket_settings_busy', __( 'Another settings change is in progress. Review the current values and try again.', 'noobrocket' ), 'error' );
			}
			return $old;
		}
		if ( get_option( $option, $old ) !== $old ) {
			$this->rules->release_edit_lock();
			if ( function_exists( 'add_settings_error' ) ) {
				add_settings_error( 'general', 'noobrocket_settings_conflict', __( 'Saved settings changed during this request. Review the current values and try again.', 'noobrocket' ), 'error' );
			}
			return $old;
		}
		$this->ordinary_lock = true;
		$this->ordinary_depth = 1;
		return $new;
	}

	public function release_change_lock(): void {
		if ( $this->ordinary_lock ) {
			if ( $this->ordinary_depth > 1 ) {
				--$this->ordinary_depth;
				return;
			}
			$this->ordinary_lock = false;
			$this->ordinary_depth = 0;
			$this->rules->release_edit_lock();
		}
	}

	/** A failed native write has no updated hook; release its request-owned lock on shutdown. */
	public function release_pending_lock(): void {
		$this->ordinary_depth = 0;
		$this->release_change_lock();
	}

	public function settings_updated( $old, $new ): void {
		try {
			if ( ! $this->restoring && is_array( $new ) && is_array( $old ) && $this->restorable( $new ) !== $this->restorable( $old ) ) {
				$result = $this->record( $old, $this->rules->get() );
				$this->report_history_failure( $result );
			}
		} finally {
			$this->release_change_lock();
		}
	}

	public function rules_updated( $old, $new ): void {
		try {
			if ( ! $this->restoring && is_array( $new ) && is_array( $old ) && $new !== $old ) {
				$result = $this->record( (array) get_option( 'noobrocket_settings', [] ), $old );
				$this->report_history_failure( $result, true );
			}
		} finally {
			$this->release_change_lock();
		}
	}

	public function first_rules_added( $option, $new ): void {
		$this->rules_updated( [], $new );
	}

	/** Bind both the retained target and the current restorable state to the visible comparison. */
	public function review_token( array $snapshot ): string {
		return hash( 'sha256', wp_json_encode( [
			'target' => $snapshot,
			'settings' => $this->restorable( (array) get_option( 'noobrocket_settings', [] ) ),
			'rules' => $this->rules->get(),
		] ) );
	}

	/** Return persistence/ownership errors so restores cannot proceed without recovery. */
	private function record( array $settings, array $rules ) {
		return $this->rules->with_edit_lock( function () use ( $settings, $rules ) {
			$history = $this->all();
			array_unshift( $history, [
				'id'       => wp_generate_uuid4(),
				'time'     => time(),
				'user'     => get_current_user_id(),
				'settings' => $this->restorable( $settings ),
				'rules'    => $rules,
			] );
			$history = array_slice( $history, 0, self::LIMIT );
			update_option( self::OPTION, $history, false );
			if ( $this->all() !== $history ) {
				return new \WP_Error( 'snapshot_history_write', __( 'The previous state could not be saved in settings history. No restore was started.', 'noobrocket' ) );
			}
			return true;
		} );
	}

	/** An ordinary committed setting/rule change stays saved if only its history write fails. */
	private function report_history_failure( $result, bool $rules_changed = false ): void {
		if ( ! is_wp_error( $result ) ) {
			return;
		}
		$message = __( 'The change was saved, but its previous state could not be recorded in settings history.', 'noobrocket' );
		$native_notice = function_exists( 'add_settings_error' );
		if ( $native_notice ) {
			add_settings_error( 'general', 'noobrocket_history_failed', $message, 'error' );
		}
		if ( ( $rules_changed || ! $native_notice ) && get_current_user_id() > 0 && function_exists( 'set_transient' ) ) {
			// admin-post does not preserve Settings API errors; retain feedback through its existing notice owner.
			set_transient( 'noobrocket_asset_notice_' . get_current_user_id(), [ 'message' => $message, 'route' => 'history' ], MINUTE_IN_SECONDS );
		}
	}

	/** Restore through the existing option filters and cache invalidation paths. */
	public function restore( string $id, ?string $expected_review = null ) {
		if ( ! current_user_can( 'noobrocket_manage_options' ) ) {
			return new \WP_Error( 'snapshot_permission', __( 'You cannot restore Cache-Cache settings.', 'noobrocket' ) );
		}
		$snapshot = null;
		foreach ( $this->all() as $item ) {
			if ( is_array( $item ) && ( $item['id'] ?? '' ) === $id ) {
				$snapshot = $item;
				break;
			}
		}
		if ( ! $snapshot || ! is_array( $snapshot['settings'] ?? null ) ) {
			return new \WP_Error( 'snapshot_missing', __( 'This snapshot is no longer available.', 'noobrocket' ) );
		}
		$rules = $this->rules->validate( $snapshot['rules'] ?? null );
		if ( is_wp_error( $rules ) ) {
			return $rules;
		}

		return $this->rules->with_edit_lock( function () use ( $id, $snapshot, $rules, $expected_review ) {
			$retained = null;
			foreach ( $this->all() as $item ) {
				if ( is_array( $item ) && ( $item['id'] ?? '' ) === $id ) {
					$retained = $item;
					break;
				}
			}
			if ( $retained !== $snapshot || ( null !== $expected_review && ! hash_equals( $this->review_token( $snapshot ), $expected_review ) ) ) {
				return new \WP_Error( 'snapshot_conflict', __( 'Saved settings or this snapshot changed after the review. Review the current differences before restoring.', 'noobrocket' ) );
			}
			$current   = (array) get_option( 'noobrocket_settings', [] );
			$protected = array_diff_key( $current, array_fill_keys( $this->setting_keys(), true ) );
			$settings  = array_merge( $protected, $this->restorable( $snapshot['settings'] ) );
			// Save the current state once, so a mistaken restore can itself be reversed.
			$recovery = $this->record( $current, $this->rules->get() );
			if ( is_wp_error( $recovery ) ) {
				return $recovery;
			}
			$this->restoring = true;
			try {
				update_option( 'noobrocket_settings', $settings );
				update_option( Rules::OPTION, $rules, false );
			} finally {
				$this->restoring = false;
			}
			if ( $this->restorable( (array) get_option( 'noobrocket_settings', [] ) ) != $this->restorable( $settings ) || $this->rules->get() !== $rules ) {
				return new \WP_Error( 'snapshot_write', __( 'The settings restore could not be completed. The previous state remains in settings history.', 'noobrocket' ) );
			}
			return true;
		} );
	}
}
