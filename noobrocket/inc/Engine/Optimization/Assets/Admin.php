<?php
namespace NoobRocket\Engine\Optimization\Assets;

use NoobRocket\Event_Management\Subscriber_Interface;
use NoobRocket\Engine\Admin\Settings\Settings;

/** Native WordPress forms keep draft editing separate from public publication. */
class Admin implements Subscriber_Interface {

	private $rules;
	private $snapshots;
	private $setting_fields = [];

	public function __construct( Rules $rules, Snapshots $snapshots ) {
		$this->rules     = $rules;
		$this->snapshots = $snapshots;
	}

	public static function get_subscribed_events(): array {
		return [
			'admin_init' => 'redirect_legacy_page',
			'noobrocket_settings_assets_content' => 'render',
			'noobrocket_settings_history_content' => 'render_history',
			'noobrocket_before_add_field_to_settings' => 'remember_setting_field',
			'noobrocket_settings_registered' => 'remember_settings',
			'admin_post_noobrocket_assets' => 'handle_request',
			'update_option_noobrocket_asset_rules' => 'rules_changed',
			'add_option_noobrocket_asset_rules' => 'rules_changed',
		];
	}

	public function redirect_legacy_page(): void {
		if ( isset( $_GET['page'] ) && 'noobrocket-assets' === $_GET['page'] && current_user_can( 'noobrocket_manage_options' ) ) {
			wp_safe_redirect( admin_url( 'options-general.php?page=noobrocket' ) . '#assets' );
			exit;
		}
	}

	/** Reuse the settings screen's translated labels and value choices in restore reviews. */
	public function remember_setting_field( array $field ): array {
		if ( isset( $field['id'] ) ) {
			$this->setting_fields[ $field['id'] ] = $field;
			if ( 'cnames' === ( $field['type'] ?? '' ) ) {
				$this->setting_fields['cdn_zone'] = [
					'id' => 'cdn_zone',
					'label' => __( 'Files to serve', 'noobrocket' ),
					'choices' => Settings::get_cdn_scope_labels(),
				];
			}
		}
		return $field;
	}

	/** Reuse the registered section title for fields without a visible label. */
	public function remember_settings( array $settings ): void {
		foreach ( $settings as $page ) {
			foreach ( $page['sections'] ?? [] as $section ) {
				foreach ( $section['fields'] ?? [] as $id => $field ) {
					$field['id'] = $id;
					$field['label'] = ( $field['label'] ?? '' ) ?: ( $section['title'] ?? $id );
					$this->remember_setting_field( $field );
				}
			}
		}
	}

	public function rules_changed(): void {
		noobrocket_clean_domain();
		do_action( 'noobrocket_asset_rules_changed' );
	}

	public function draft(): array {
		$draft = get_user_meta( get_current_user_id(), Frontend::user_key( 'draft' ), true );
		if ( is_array( $draft ) && isset( $draft['rules'], $draft['base'] ) && is_array( $draft['rules'] ) && is_array( $draft['base'] ) ) {
			return $draft;
		}
		$published = $this->rules->get();
		return [ 'rules' => $published, 'base' => $published ];
	}

	/** Discard reviews own both the saved rules and recoverable rejected input. */
	private function discard_revision( array $draft ): string {
		return hash( 'sha256', wp_json_encode( [
			'draft' => Rules::draft_revision( $draft ),
			'entry' => get_user_meta( get_current_user_id(), Frontend::user_key( 'entry' ), true ),
		] ) );
	}

	/** Preview only URLs belonging to the current site, never arbitrary redirects. */
	public function preview_url( $value ) {
		if ( ! is_string( $value ) ) {
			return new \WP_Error( 'asset_url', __( 'Enter a URL on this WordPress site.', 'noobrocket' ) );
		}
		$url  = wp_parse_url( esc_url_raw( $value ) );
		$home = wp_parse_url( home_url( '/' ) );
		if ( ! is_array( $url ) || ! in_array( $url['scheme'] ?? '', [ 'http', 'https' ], true )
			|| ( $url['scheme'] ?? '' ) !== $home['scheme'] || ( $url['host'] ?? '' ) !== $home['host']
			|| ( $url['port'] ?? null ) !== ( $home['port'] ?? null ) || isset( $url['user'] ) || isset( $url['pass'] )
			|| false !== strpos( $url['path'] ?? '', '/wp-admin' ) || false !== strpos( $url['path'] ?? '', 'wp-login.php' ) ) {
			return new \WP_Error( 'asset_url', __( 'Enter a frontend URL on this WordPress site, using its normal scheme and port.', 'noobrocket' ) );
		}
		return add_query_arg( Frontend::PREVIEW, wp_create_nonce( Frontend::PREVIEW ), remove_query_arg( Frontend::PREVIEW, esc_url_raw( $value ) ) );
	}

	public function handle_request(): void {
		if ( ! current_user_can( 'noobrocket_manage_options' ) ) {
			wp_die( esc_html__( 'You cannot manage Cache-Cache assets.', 'noobrocket' ), '', [ 'response' => 403 ] );
		}
		check_admin_referer( 'noobrocket_assets' );
		$method  = $_SERVER['REQUEST_METHOD'] ?? '';
		$input   = 'GET' === $method ? $_GET : $_POST;
		$command = isset( $input['command'] ) && is_string( $input['command'] ) ? sanitize_key( wp_unslash( $input['command'] ) ) : '';
		// Preview navigates to a private page; every state-changing action still requires POST.
		if ( 'POST' !== $method && ! ( 'GET' === $method && 'preview' === $command ) ) {
			wp_die( esc_html__( 'Use the Asset Manager form.', 'noobrocket' ), '', [ 'response' => 405 ] );
		}
		$error   = null;

		$apply = function () use ( &$command, &$error, $input ) {
			$draft = $this->draft();
			if ( in_array( $command, [ 'add', 'remove', 'discard' ], true ) ) {
				$revision = isset( $_POST['draft_revision'] ) && is_string( $_POST['draft_revision'] ) ? wp_unslash( $_POST['draft_revision'] ) : '';
				$expected = 'discard' === $command ? $this->discard_revision( $draft ) : Rules::draft_revision( $draft );
				if ( ! hash_equals( $expected, $revision ) ) {
					$message = 'discard' === $command
						? __( 'Your private draft or rejected entry changed in another tab. Review the current private work before discarding it.', 'noobrocket' )
						: __( 'Your private draft changed in another tab. Review the current draft before editing it.', 'noobrocket' );
					$error = new \WP_Error( 'asset_edit_conflict', $message );
					return;
				}
			}
			switch ( $command ) {
				case 'add':
					$rule = isset( $_POST['rule'] ) && is_array( $_POST['rule'] ) ? wp_unslash( $_POST['rule'] ) : [];
					$rule = $this->rule_from_form( $rule, wp_unslash( $_POST ) );
					$draft['rules'][] = $rule;
					$validated = $this->rules->validate( $draft['rules'] );
					if ( is_wp_error( $validated ) ) {
						$error = $validated;
						// Keep rejected input private so a validation round trip does not lose the editor.
						$entry = [];
						foreach ( [ 'type', 'handle', 'scope', 'value', 'exceptions' ] as $key ) {
							$entry[ $key ] = isset( $rule[ $key ] ) && is_scalar( $rule[ $key ] ) ? substr( sanitize_text_field( (string) $rule[ $key ] ), 0, 'exceptions' === $key ? 2000 : 200 ) : '';
						}
						$entry['expert'] = isset( $_POST['rule_expert'] ) && '1' === $_POST['rule_expert'];
						update_user_meta( get_current_user_id(), Frontend::user_key( 'entry' ), $entry );
						if ( get_user_meta( get_current_user_id(), Frontend::user_key( 'entry' ), true ) !== $entry ) {
							$error = new \WP_Error( 'asset_entry_write', __( 'The invalid rule could not be kept in your private editor. Your previous private work remains available.', 'noobrocket' ) );
						}
					} else {
						$draft['rules'] = $validated;
						update_user_meta( get_current_user_id(), Frontend::user_key( 'draft' ), $draft );
						if ( get_user_meta( get_current_user_id(), Frontend::user_key( 'draft' ), true ) !== $draft ) {
							$error = new \WP_Error( 'asset_draft_write', __( 'The rule could not be saved to your private draft. Your existing draft and rejected entry remain available.', 'noobrocket' ) );
							break;
						}
						delete_user_meta( get_current_user_id(), Frontend::user_key( 'entry' ) );
						if ( metadata_exists( 'user', get_current_user_id(), Frontend::user_key( 'entry' ) ) ) {
							$error = new \WP_Error( 'asset_entry_clear', __( 'The rule was saved, but the previous rejected entry could not be cleared from your private editor.', 'noobrocket' ) );
						}
					}
					break;
				case 'remove':
					$index = isset( $_POST['index'] ) && is_scalar( $_POST['index'] ) ? (int) $_POST['index'] : -1;
					if ( $index >= 0 && isset( $draft['rules'][ $index ] ) ) {
						unset( $draft['rules'][ $index ] );
						$draft['rules'] = array_values( $draft['rules'] );
						update_user_meta( get_current_user_id(), Frontend::user_key( 'draft' ), $draft );
						if ( get_user_meta( get_current_user_id(), Frontend::user_key( 'draft' ), true ) !== $draft ) {
							$error = new \WP_Error( 'asset_draft_write', __( 'The rule could not be removed from your private draft. Your existing draft remains available.', 'noobrocket' ) );
						}
					}
					break;
				case 'discard':
					delete_user_meta( get_current_user_id(), Frontend::user_key( 'draft' ) );
					if ( metadata_exists( 'user', get_current_user_id(), Frontend::user_key( 'draft' ) ) ) {
						$error = new \WP_Error( 'asset_draft_discard', __( 'Your private draft could not be discarded. Your draft and rejected entry remain available.', 'noobrocket' ) );
						break;
					}
					delete_user_meta( get_current_user_id(), Frontend::user_key( 'entry' ) );
					if ( metadata_exists( 'user', get_current_user_id(), Frontend::user_key( 'entry' ) ) ) {
						$error = new \WP_Error( 'asset_entry_discard', __( 'Your private draft was discarded, but its rejected entry could not be cleared.', 'noobrocket' ) );
					}
					break;
				case 'publish':
					$revision = isset( $_POST['draft_revision'] ) && is_string( $_POST['draft_revision'] ) ? wp_unslash( $_POST['draft_revision'] ) : '';
					$error = $this->publish( $draft, isset( $_POST['confirm'] ) && '1' === $_POST['confirm'], $revision );
					if ( false === $error ) {
						$command = 'publish_unchanged';
					}
					break;
				case 'restore':
					if ( ! isset( $_POST['confirm'] ) || '1' !== $_POST['confirm'] ) {
						$error = new \WP_Error( 'snapshot_confirm', __( 'Confirm the settings restore first.', 'noobrocket' ) );
						break;
					}
					$id = isset( $_POST['snapshot'] ) && is_string( $_POST['snapshot'] ) ? sanitize_text_field( wp_unslash( $_POST['snapshot'] ) ) : '';
					$review = isset( $_POST['review_token'] ) && is_string( $_POST['review_token'] ) ? wp_unslash( $_POST['review_token'] ) : '';
					$error = $this->snapshots->restore( $id, $review );
					break;
				case 'preview':
					$revision = isset( $input['draft_revision'] ) && is_string( $input['draft_revision'] ) ? wp_unslash( $input['draft_revision'] ) : '';
					if ( ! hash_equals( Rules::draft_revision( $draft ), $revision ) ) {
						$error = new \WP_Error( 'asset_preview_conflict', __( 'Your private draft changed. Open its current review before previewing.', 'noobrocket' ) );
						break;
					}
					$url = $this->preview_url( isset( $input['url'] ) ? wp_unslash( $input['url'] ) : '' );
					if ( ! is_wp_error( $url ) ) {
						$url = add_query_arg( Frontend::REVISION, $revision, $url );
					}
					if ( is_wp_error( $url ) ) {
						$error = $url;
					} else {
						wp_safe_redirect( $url );
						exit;
					}
					break;
				default:
					$error = new \WP_Error( 'asset_command', __( 'Unknown Asset Manager action.', 'noobrocket' ) );
			}
		};
		if ( in_array( $command, [ 'add', 'remove', 'discard' ], true ) ) {
			$result = $this->rules->with_edit_lock( function () use ( $apply ) {
				wp_cache_delete( get_current_user_id(), 'user_meta' );
				$apply();
			} );
			if ( is_wp_error( $result ) ) {
				$error = $result;
			}
		} else {
			$apply();
		}

		if ( is_wp_error( $error ) ) {
			set_transient( 'noobrocket_asset_notice_' . get_current_user_id(), [ 'message' => $error->get_error_message(), 'route' => 'restore' === $command ? 'history' : 'assets' ], MINUTE_IN_SECONDS );
		}
		// Asset actions own their feedback; core's updated flag incorrectly says settings were saved.
		wp_safe_redirect( add_query_arg( [ 'page' => 'noobrocket', 'asset_updated' => is_wp_error( $error ) ? '0' : '1', 'asset_action' => $command ], admin_url( 'options-general.php' ) ) . ( 'restore' === $command ? '#history' : '#assets' ) );
		exit;
	}

	/** Recognizable choices use the same validated rule shape as the expert ID path. */
	private function rule_from_form( array $rule, array $input ): array {
		if ( isset( $input['rule_expert'] ) && '1' === $input['rule_expert'] ) {
			return $rule;
		}
		$fields = [ 'post' => 'rule_page', 'post_type' => 'rule_post_type', 'template' => 'rule_template' ];
		$field = is_string( $rule['scope'] ?? null ) ? ( $fields[ $rule['scope'] ] ?? null ) : null;
		if ( $field && isset( $input[ $field ] ) ) {
			$rule['value'] = $input[ $field ];
		}
		return $rule;
	}

	/** True for changed publication, false for identical rules, or an error. */
	public function publish( array $draft, bool $confirmed, ?string $expected_revision = null ) {
		if ( ! current_user_can( 'noobrocket_manage_options' ) || ! $confirmed ) {
			return new \WP_Error( 'asset_confirm', __( 'Review your preview and confirm publication first.', 'noobrocket' ) );
		}
		$rules = $this->rules->validate( $draft['rules'] ?? null );
		if ( is_wp_error( $rules ) ) {
			return $rules;
		}
		return $this->rules->with_edit_lock( function () use ( $draft, $rules, $expected_revision ) {
			if ( null !== $expected_revision ) {
				wp_cache_delete( get_current_user_id(), 'user_meta' );
				$current_draft = $this->draft();
				if ( ! hash_equals( Rules::draft_revision( $current_draft ), $expected_revision ) || Rules::draft_revision( $draft ) !== $expected_revision ) {
					return new \WP_Error( 'asset_draft_conflict', __( 'Your private draft changed after this review. Review it again before publishing.', 'noobrocket' ) );
				}
			}
			$current = $this->rules->get();
			if ( ( $draft['base'] ?? null ) !== $current ) {
				return new \WP_Error( 'asset_conflict', __( 'Published rules changed while you edited. Discard your draft and start from the current rules.', 'noobrocket' ) );
			}
			if ( $rules === $current ) {
				delete_user_meta( get_current_user_id(), Frontend::user_key( 'draft' ) );
				return false;
			}
			update_option( Rules::OPTION, $rules, false );
			if ( $this->rules->get() !== $rules ) {
				return new \WP_Error( 'asset_write', __( 'Rules could not be saved. Your draft remains available.', 'noobrocket' ) );
			}
			delete_user_meta( get_current_user_id(), Frontend::user_key( 'draft' ) );
			return true;
		} );
	}

	private function open_form( string $command, array $snapshot = [] ): void {
		$metadata = '';
		if ( $snapshot ) {
			$name = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $snapshot['time'] );
			$metadata = sprintf( ' data-noobrocket-operation-name="%s" data-snapshot-name="%s" data-snapshot-save-evicts="%s"', esc_attr( sprintf( __( 'Restore %s', 'noobrocket' ), $name ) ), esc_attr( $name ), $this->snapshots->save_may_evict( $snapshot['id'] ) ? '1' : '0' );
		}
		printf( '<form method="post" action="%s"%s%s%s>', esc_url( admin_url( 'admin-post.php' ) ), 'add' === $command ? ' id="noobrocket-rule-editor" data-noobrocket-unfinished' : '', $metadata, 'preview' === $command ? ' rel="noopener"' : '' );
		wp_nonce_field( 'noobrocket_assets' );
		printf( '<input type="hidden" name="action" value="noobrocket_assets"><input type="hidden" name="command" value="%s">', esc_attr( $command ) );
		if ( in_array( $command, [ 'add', 'remove', 'discard', 'publish', 'preview' ], true ) ) {
			$draft = $this->draft();
			$revision = 'discard' === $command ? $this->discard_revision( $draft ) : Rules::draft_revision( $draft );
			printf( '<input type="hidden" name="draft_revision" value="%s">', esc_attr( $revision ) );
		}
		if ( $snapshot ) {
			printf( '<input type="hidden" name="review_token" value="%s">', esc_attr( $this->snapshots->review_token( $snapshot ) ) );
		}
	}

	public function render(): void {
		if ( ! current_user_can( 'noobrocket_manage_options' ) ) {
			return;
		}
		$draft     = $this->draft();
		$published = $this->rules->get();
		$inventory = get_user_meta( get_current_user_id(), Frontend::user_key( 'inventory' ), true );
		$inventory = is_array( $inventory ) ? $inventory : [];
		$entry = get_user_meta( get_current_user_id(), Frontend::user_key( 'entry' ), true );
		$entry = is_array( $entry ) ? $entry : [];
		$inspected_page = ! empty( $inventory['url'] ) ? url_to_postid( $inventory['url'] ) : 0;
		if ( isset( $_GET['asset_type'], $_GET['asset_handle'] ) && is_string( $_GET['asset_type'] ) && is_string( $_GET['asset_handle'] ) && in_array( $_GET['asset_type'], [ 'style', 'script' ], true ) && preg_match( '/^[a-zA-Z0-9_.:-]{1,200}$/D', $_GET['asset_handle'] ) ) {
			$page = isset( $_GET['asset_page'] ) && is_scalar( $_GET['asset_page'] ) ? absint( $_GET['asset_page'] ) : 0;
			$entry = [ 'type' => $_GET['asset_type'], 'handle' => $_GET['asset_handle'], 'scope' => 'post', 'value' => $page ? (string) $page : '', 'expert' => $page && 'page' !== get_post_type( $page ) ];
		}
		$notice = $this->notice( 'assets' );
		require NOOBROCKET_PATH . 'views/assets.php';
	}

	private function notice( string $route ): string {
		$key = 'noobrocket_asset_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		if ( is_array( $notice ) && ( $notice['route'] ?? '' ) === $route ) {
			delete_transient( $key );
			return (string) $notice['message'];
		}
		if ( 'assets' === $route && is_string( $notice ) ) {
			delete_transient( $key );
			return $notice;
		}
		return '';
	}

	private function post_label( int $id ): string {
		$title = get_the_title( $id );
		return sprintf( __( '%1$s (#%2$d)', 'noobrocket' ), $title ?: __( 'Untitled or unavailable post', 'noobrocket' ), $id );
	}

	private function scope_label( array $rule ): string {
		switch ( $rule['scope'] ) {
			case 'post':
				return $this->post_label( (int) $rule['value'] );
			case 'post_type':
				$type = get_post_type_object( $rule['value'] );
				return sprintf( __( '%s and its archive', 'noobrocket' ), $type ? $type->labels->name : $rule['value'] );
			case 'template':
				$templates = wp_get_theme()->get_page_templates();
				return sprintf( __( 'Template: %s', 'noobrocket' ), 'default' === $rule['value'] ? __( 'Default template', 'noobrocket' ) : ( $templates[ $rule['value'] ] ?? $rule['value'] ) );
		}
		return __( 'Every frontend page', 'noobrocket' );
	}

	private function setting_label( string $key ): string {
		return wp_strip_all_tags( ( $this->setting_fields[ $key ]['label'] ?? '' ) ?: ucwords( str_replace( '_', ' ', $key ) ) );
	}

	private function setting_value( string $key, $value ): string {
		$field = $this->setting_fields[ $key ] ?? [];
		if ( null === $value ) {
			return __( 'Default (not stored)', 'noobrocket' );
		}
		if ( 'sanitize_checkbox' === ( $field['sanitize_callback'] ?? '' ) || is_bool( $value ) ) {
			return $value ? __( 'Enabled', 'noobrocket' ) : __( 'Disabled', 'noobrocket' );
		}
		if ( is_scalar( $value ) && isset( $field['choices'][ $value ] ) ) {
			return wp_strip_all_tags( $field['choices'][ $value ] );
		}
		if ( is_array( $value ) ) {
			if ( ! $value ) {
				return __( 'None', 'noobrocket' );
			}
			$items = [];
			foreach ( $value as $item ) {
				if ( is_scalar( $item ) ) {
					$items[] = isset( $field['choices'][ $item ] ) ? wp_strip_all_tags( $field['choices'][ $item ] ) : (string) $item;
				} else {
					$items[] = wp_json_encode( $item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
				}
			}
			return implode( "\n", $items );
		}
		return '' === (string) $value ? __( 'None', 'noobrocket' ) : (string) $value;
	}

	public function render_history(): void {
		if ( ! current_user_can( 'noobrocket_manage_options' ) ) {
			return;
		}
		$snapshots = $this->snapshots->all();
		$notice = $this->notice( 'history' );
		require NOOBROCKET_PATH . 'views/history.php';
	}
}
