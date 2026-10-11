<?php
declare(strict_types=1);

namespace NoobRocket\Engine\Abilities\Options;

use NoobRocket\Engine\Abilities\AbilitiesInterface;
use NoobRocket\Engine\Tracking\TrackingTrait;
use NoobRocket\Engine\Admin\Settings\Settings;
use NoobRocket\Admin\Options;
use WP_Error;

class SetOption implements AbilitiesInterface {
	use TrackingTrait;

	/**
	 * Allowed options instance.
	 *
	 * @var AllowedOptions
	 */
	private $allowed_options;


	/**
	 * Options that are readable via get-options but cannot be written through this ability.
	 *
	 * The option - cdn_state has two internal writers, neither of which this ability should
	 * bypass: CdnStateBridge derives it from the legacy cdn / cdn_type fields on settings
	 * save, and CDNOptionsManager::set_cdn_state() writes it directly (via
	 * DataManagerSubscriber::maybe_sync_cdn_state() and CdnStateBridge's own upgrade-time
	 * backfill) when the HostedCDN subscription tier changes outside of a cdn / cdn_type
	 * change. Neither writer syncs cdn_state back into the legacy fields. Setting it
	 * directly here would let it disagree with both of its real sources of truth, not just
	 * eventually but immediately - supporting that would mean reintroducing a state -> legacy
	 * sync, the exact bidirectional complexity this bridge was deliberately simplified away
	 * from.
	 */
	private const READ_ONLY_OPTIONS = [
		'cdn_state',
	];

	/**
	 * Options that accept integer values.
	 */
	private const INTEGER_OPTIONS = [
		'purge_cron_interval',
		'performance_monitoring_schedule_frequency',
	];


	/**
	 * Options that require special string sanitization (CSS content).
	 */
	private const STRING_OPTIONS = [
		'critical_css',
	];

	/**
	 * Options that use noobrocket_sanitize_textarea_field() for sanitization.
	 */
	private const TEXTAREA_FIELD_OPTIONS = [
		'cache_reject_uri',
		'cache_reject_cookies',
		'cache_reject_ua',
		'cache_query_strings',
		'cache_purge_pages',
		'exclude_css',
		'exclude_js',
		'exclude_inline_js',
		'exclude_defer_js',
		'exclude_lazyload',
		'delay_js_exclusions',
		'remove_unused_css_safelist',
		'cdn_reject_files',
	];

	/**
	 * Options that accept arrays with sanitize_text_field per item.
	 */
	private const ARRAY_OPTIONS = [
		'cdn_cnames',
		'cdn_zone',
		'preload_fonts',
		'dns_prefetch',
		'preload_excluded_uri',
		'prerender_excluded_uri',
		'cdn_reject_pages',
		'delay_js_exclusions_selected',
		'delay_js_exclusions_selected_exclusions',
	];

	/**
	 * Constructor.
	 *
	 * @param AllowedOptions $allowed_options Allowed options instance.
	 */
	public function __construct( AllowedOptions $allowed_options ) {
		$this->allowed_options = $allowed_options;
	}

	/**
	 * Registers the set option ability.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'noobrocket/set-option',
			[
				'label'               => __( 'Set a Cache-Cache option', 'noobrocket' ),
				'description'         => _x(
					'Writes one Cache-Cache option using option_name and option_value. update_mode defaults to update, which appends to arrays; use replace to overwrite a full array.
Use this when the user wants to enable, disable, or update a specific setting. For array-type options, always call get-options first. CDN resource scopes are positional and replace the current scope list; changing CDN hosts also keeps the scope list aligned.
Confirmation is required before calling. First show the setting name and the current value to new value. Then ask: `Confirm this change?` and wait for a clear yes or no.
A user request such as `enable it` or `disable it` is not enough confirmation. Only call this ability after the user gives an affirmative answer in the same turn.',
					'Ability description',
					'noobrocket'
					),
				'category'            => 'noobrocket-options',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'option_name'  => [
							'type'        => 'string',
							'description' => __( 'The name of the Cache-Cache option to set', 'noobrocket' ),
							'enum'        => $this->get_allowed_options(),
						],
						'option_value' => [
							'anyOf'       => [
								[ 'type' => 'string' ],
								[ 'type' => 'boolean' ],
								[ 'type' => 'integer' ],
								[
									'type'  => 'array',
									'items' => [ 'type' => 'string' ],
								],
							],
							'description' => __( 'The value to set for the specified Cache-Cache option', 'noobrocket' ),
						],
						'update_mode'  => [
							'type'        => 'string',
							'enum'        => [ 'update', 'replace' ],
							'description' => __( 'For array and textarea options: "update" (default) adds new entries to the existing list; "replace" overwrites the entire list. CDN resource scopes always replace the positional scope list.', 'noobrocket' ),
						],
					],
					'required'   => [ 'option_name', 'option_value' ],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'success'        => [
							'type'        => 'boolean',
							'description' => __( 'Indicates whether the option was successfully set', 'noobrocket' ),
						],
						'error'          => [
							'type'        => 'string',
							'description' => __( 'Error message if the option could not be set', 'noobrocket' ),
						],
						'previous_value' => [
							'anyOf'       => [
								[ 'type' => 'string' ],
								[ 'type' => 'boolean' ],
								[ 'type' => 'integer' ],
								[
									'type'  => 'array',
									'items' => [ 'type' => 'string' ],
								],
							],
							'description' => __( 'The previous value of the option before it was updated', 'noobrocket' ),
						],
						'new_value'      => [
							'anyOf'       => [
								[ 'type' => 'string' ],
								[ 'type' => 'boolean' ],
								[ 'type' => 'integer' ],
								[
									'type'  => 'array',
									'items' => [ 'type' => 'string' ],
								],
							],
							'description' => __( 'The new value of the option after it was updated', 'noobrocket' ),
						],
						'outcome'        => [
							'type' => 'string',
							'enum' => [ 'applied', 'unchanged', 'failed', 'adjusted', 'invalid' ],
						],
					],
				],
				'execute_callback'    => [ $this, 'execute' ],
				'permission_callback' => [ $this, 'check_permissions' ],
				'meta'                => [
					'show_in_rest' => true,
					'mcp'          => [
						'public' => true,
					],
					'annotations'  => [
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => true,
					],
				],
			]
		);
	}

	/**
	 * Checks if the current user has permission to set NoobRocket options.
	 *
	 * @return bool
	 */
	public function check_permissions(): bool {
		return current_user_can( 'noobrocket_manage_options' );
	}

	/**
	 * Executes the ability to set a NoobRocket option.
	 *
	 * @param array|null $input Input containing option_name, option_value, and optionally update_mode.
	 * @return array Response with success status and option values.
	 */
	public function execute( $input = null ): array {
		if ( ! $this->check_permissions() ) {
			return [ 'success' => false, 'outcome' => 'invalid', 'error' => __( 'You do not have permission to change Cache-Cache settings.', 'noobrocket' ) ];
		}
		if ( ! is_array( $input ) || ! isset( $input['option_name'] ) || ! is_string( $input['option_name'] ) || ! array_key_exists( 'option_value', $input ) ) {
			return [ 'success' => false, 'outcome' => 'invalid', 'error' => __( 'Provide an option name and its new value.', 'noobrocket' ) ];
		}
		$value = $input['option_value'];
		if ( ! is_string( $value ) && ! is_bool( $value ) && ! is_int( $value ) && ! ( is_array( $value ) && Settings::is_text_list( $value ) ) ) {
			return [ 'success' => false, 'outcome' => 'invalid', 'error' => __( 'Provide text, a boolean, a whole number or a list of text entries.', 'noobrocket' ) ];
		}
		$update_mode = array_key_exists( 'update_mode', $input ) ? $input['update_mode'] : 'update';
		if ( ! in_array( $update_mode, [ 'update', 'replace' ], true ) ) {
			return [ 'success' => false, 'outcome' => 'invalid', 'error' => __( 'Update mode must be update or replace.', 'noobrocket' ) ];
		}
		$this->track_event(
			'MCP Ability Executed',
			[
				'ability' => 'noobrocket/set-option',
				'context' => 'wp_plugin_mcp',
			]
		);
		$option_name  = $input['option_name'];
		$option_value = $input['option_value'];

		if ( ! $this->validate_option_name( $option_name ) ) {
			return [
				'success' => false,
				'outcome' => 'invalid',
				'error'   => sprintf(
					/* translators: %s: option name */
					__( 'Invalid option name: %s. This option cannot be set via the ability.', 'noobrocket' ),
					$option_name
				),
			];
		}

		$previous_value = get_noobrocket_option( $option_name );
		if ( in_array( $option_name, [ 'cdn_cnames', 'cdn_zone' ], true ) ) {
			$changes = $this->sanitize_cdn_changes( $option_name, $option_value, $update_mode, $previous_value );
			$sanitized_value = $changes instanceof WP_Error ? $changes : $changes[ $option_name ];
		} else {
			$sanitized_value = $this->sanitize_value( $option_name, $option_value, $update_mode, $previous_value );
			$changes = [ $option_name => $sanitized_value ];
		}
		if ( $sanitized_value instanceof WP_Error ) {
			return [ 'success' => false, 'outcome' => 'invalid', 'error' => $sanitized_value->get_error_message() ];
		}

		$unchanged = true;
		$previous_values = [];
		foreach ( $changes as $name => $value ) {
			$previous_values[ $name ] = get_noobrocket_option( $name );
			if ( $previous_values[ $name ] !== $value ) {
				$unchanged = false;
			}
		}
		if ( ! $unchanged ) {
			if ( 1 === count( $changes ) ) {
				update_noobrocket_option( $option_name, $sanitized_value );
			} else {
				$options_api = new Options( 'noobrocket_' );
				$stored = $options_api->get( 'settings', [] );
				$options_api->set( 'settings', array_merge( $stored, $changes ) );
			}
		}

		$saved_values = [];
		foreach ( $changes as $name => $value ) {
			$saved_values[ $name ] = get_noobrocket_option( $name );
		}
		$new_value = $saved_values[ $option_name ];
		if ( $saved_values !== $changes ) {
			return [
				'success' => false,
				'outcome' => $saved_values === $previous_values ? 'failed' : 'adjusted',
				'error' => __( 'The saved settings differ from the requested change. Review the current values before retrying.', 'noobrocket' ),
				'previous_value' => $previous_value,
				'new_value' => $new_value,
			];
		}

		return [
			'success'        => true,
			'outcome'        => $unchanged ? 'unchanged' : 'applied',
			'previous_value' => $previous_value,
			'new_value'      => $new_value,
		];
	}

	/**
	 * Returns an array of all option names writable through this ability.
	 *
	 * @return array List of writable option names.
	 */
	private function get_allowed_options(): array {
		return array_values( array_diff( $this->allowed_options->get(), self::READ_ONLY_OPTIONS ) );
	}

	/**
	 * Validates that the option name is in the allowed list.
	 *
	 * @param string $option_name The option name to validate.
	 * @return bool True if valid, false otherwise.
	 */
	private function validate_option_name( string $option_name ): bool {
		return in_array( $option_name, $this->get_allowed_options(), true );
	}

	/**
	 * Sanitizes the option value based on the option type.
	 *
	 * @param string $option_name     The option name.
	 * @param mixed  $option_value    The value to sanitize.
	 * @param string $update_mode     Either 'update' (merge into existing) or 'replace' (overwrite).
	 * @param mixed  $previous_value  The current stored value, used for merging in update mode.
	 * @return mixed Sanitized value.
	 */
	private function sanitize_value( string $option_name, $option_value, string $update_mode = 'update', $previous_value = null ) {
		// Boolean options: convert to 0 or 1.
		if ( Settings::is_boolean_option( $option_name ) ) {
			return Settings::normalize_boolean( $option_value );
		}

		// Integer options reject fractions and unsupported scalar representations.
		if ( in_array( $option_name, self::INTEGER_OPTIONS, true ) ) {
			return Settings::normalize_lifespan_interval( $option_value );
		}

		// Enum options: validate against allowed values.
		if ( Settings::get_allowed_values( $option_name ) ) {
			return Settings::normalize_enum( $option_name, $option_value );
		}

		if ( in_array( $option_name, [ 'cdn_cnames', 'cdn_zone' ], true ) ) {
			$changes = $this->sanitize_cdn_changes( $option_name, $option_value, $update_mode, $previous_value );
			return $changes instanceof WP_Error ? $changes : $changes[ $option_name ];
		}

		// String options (critical_css): strip tags and style elements.
		if ( in_array( $option_name, self::STRING_OPTIONS, true ) ) {
			if ( ! is_string( $option_value ) ) {
				return new WP_Error( 'invalid_setting_text', __( 'Provide text for this setting.', 'noobrocket' ) );
			}
			if ( empty( $option_value ) ) {
				return '';
			}
			return wp_strip_all_tags(
				str_replace( [ '<style>', '</style>' ], '', $option_value ),
				true
			);
		}

		// Local CSS safelist entries are literal substrings, never patterns.
		if ( 'local_css_safelist' === $option_name ) {
			if ( ! Settings::is_text_list( $option_value ) ) {
				return new WP_Error( 'invalid_setting_list', __( 'Provide text or a list of text entries for this setting.', 'noobrocket' ) );
			}
			$sanitized = noobrocket_sanitize_local_css_safelist( $option_value );
			if ( 'replace' === $update_mode ) {
				return $sanitized;
			}
			return array_values( array_unique( array_merge( noobrocket_sanitize_local_css_safelist( $previous_value ), $sanitized ) ) );
		}

		// Textarea field options: use noobrocket_sanitize_textarea_field.
		if ( in_array( $option_name, self::TEXTAREA_FIELD_OPTIONS, true ) ) {
			if ( ! Settings::is_text_list( $option_value ) ) {
				return new WP_Error( 'invalid_setting_list', __( 'Provide text or a list of text entries for this setting.', 'noobrocket' ) );
			}
			if ( ! function_exists( 'noobrocket_sanitize_textarea_field' ) ) {
				$sanitized = is_array( $option_value ) ? $option_value : [];
			} else {
				$sanitized = noobrocket_sanitize_textarea_field( $option_name, $option_value ) ?? [];
			}

			if ( 'replace' === $update_mode ) {
				return $sanitized;
			}

			// update mode: merge new entries into the existing list.
			$existing = is_array( $previous_value ) ? $previous_value : [];
			return array_values( array_unique( array_merge( $existing, $sanitized ) ) );
		}

		if ( 'prerender_excluded_uri' === $option_name ) {
			if ( ! Settings::is_text_list( $option_value ) ) {
				return new WP_Error( 'invalid_setting_list', __( 'Provide text or a list of text entries for this setting.', 'noobrocket' ) );
			}
			$sanitized = noobrocket_sanitize_prerender_options( [ 'prerender_excluded_uri' => $option_value ] )['prerender_excluded_uri'];
			if ( 'replace' === $update_mode ) {
				return $sanitized;
			}
			$existing = noobrocket_sanitize_prerender_options( [ 'prerender_excluded_uri' => $previous_value ] )['prerender_excluded_uri'];
			return array_values( array_unique( array_merge( $existing, $sanitized ) ) );
		}

		// Array options: sanitize each item with sanitize_text_field.
		if ( in_array( $option_name, self::ARRAY_OPTIONS, true ) ) {
			if ( ! is_array( $option_value ) || ! Settings::is_text_list( $option_value ) ) {
				return new WP_Error( 'invalid_setting_list', __( 'Provide a list of text entries for this setting.', 'noobrocket' ) );
			}

			$sanitized = array_map( 'sanitize_text_field', $option_value );

			if ( 'replace' === $update_mode ) {
				return $sanitized;
			}

			// update mode: merge new entries into the existing list.
			$existing = is_array( $previous_value ) ? $previous_value : [];
			return array_values( array_unique( array_merge( $existing, $sanitized ) ) );
		}

		// Fallback: return value as-is (should not reach here for valid options).
		return $option_value;
	}

	/**
	 * A CDN host and its resource scope are one row, written in one settings update.
	 *
	 * @param string $name Selected CDN field.
	 * @param mixed $value Submitted entries.
	 * @param string $mode Host merge/replace policy; scope vectors are always positional.
	 * @param mixed $previous Previous selected field value.
	 * @return array|WP_Error
	 */
	private function sanitize_cdn_changes( string $name, $value, string $mode, $previous ) {
		if ( ! is_array( $value ) || ! Settings::is_text_list( $value ) ) {
			return new WP_Error( 'invalid_cdn_list', __( 'Provide a list of CDN hostnames or resource scopes.', 'noobrocket' ) );
		}
		$hosts = 'cdn_cnames' === $name ? $value : get_noobrocket_option( 'cdn_cnames', [] );
		$scopes = 'cdn_zone' === $name ? $value : get_noobrocket_option( 'cdn_zone', [] );
		if ( ! is_array( $hosts ) ) {
			return new WP_Error( 'invalid_cdn_list', __( 'Provide a list of CDN hostnames or resource scopes.', 'noobrocket' ) );
		}
		if ( 'cdn_cnames' === $name && 'update' === $mode ) {
			$hosts = array_merge( is_array( $previous ) ? $previous : [], $value );
		}
		if ( 'cdn_zone' === $name ) {
			foreach ( $value as $key => $scope ) {
				if ( ! is_int( $key ) || $key < 0 || $key >= count( $hosts ) || ! in_array( $scope, Settings::get_cdn_scopes(), true ) ) {
					/* translators: %s: comma-separated list of available CDN resource scopes. */
					return new WP_Error( 'invalid_cdn_scope', sprintf( __( 'Assign one resource scope per CDN host using: %s.', 'noobrocket' ), implode( ', ', Settings::get_cdn_scopes() ) ) );
				}
			}
		}
		$rejected_hosts = [];
		$rejected_scopes = [];
		$rows = Settings::normalize_cdn_rows( $hosts, $scopes, $rejected_hosts, $rejected_scopes );
		if ( $rejected_hosts || $rejected_scopes ) {
			return new WP_Error( 'invalid_cdn_rows', __( 'CDN hosts must be valid HTTP(S) URLs or hostnames with an available resource scope.', 'noobrocket' ) );
		}
		return $rows;
	}
}
