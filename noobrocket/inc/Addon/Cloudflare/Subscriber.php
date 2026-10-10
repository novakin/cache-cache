<?php
declare(strict_types=1);

namespace NoobRocket\Addon\Cloudflare;

use WP_Post;
use WP_Error;
use NoobRocket\Event_Management\Subscriber_Interface;
use NoobRocket\Admin\{Options, Options_Data};
use NoobRocket\Addon\Cloudflare\Auth\AuthFactoryInterface;

class Subscriber implements Subscriber_Interface {
	/**
	 * Cloudflare instance.
	 *
	 * @var Cloudflare
	 */
	private $cloudflare;

	/**
	 * Options Data instance.
	 *
	 * @var Options_Data
	 */
	private $options;

	/**
	 * Options instance.
	 *
	 * @var Options
	 */
	private $options_api;

	/**
	 * Authentication factory.
	 *
	 * @var AuthFactoryInterface
	 */
	protected $auth_factory;

	/**
	 * Allow only the provider owner to clear a successfully restored backup.
	 *
	 * @var bool
	 */
	private $clearing_restored_backup = false;

	/**
	 * Creates an instance of the Cloudflare Subscriber.
	 *
	 * @param Cloudflare           $cloudflare Cloudflare instance.
	 * @param Options_Data         $options NoobRocket options instance.
	 * @param Options              $options_api Options instance.
	 * @param AuthFactoryInterface $auth_factory Authentication factory.
	 */
	public function __construct( Cloudflare $cloudflare, Options_Data $options, Options $options_api, AuthFactoryInterface $auth_factory ) {
		$this->options      = $options;
		$this->options_api  = $options_api;
		$this->cloudflare   = $cloudflare;
		$this->auth_factory = $auth_factory;
	}

	/**
	 * Returns an array of events that this subscriber wants to listen to.
	 *
	 * @return array
	 */
	public static function get_subscribed_events() {
		$slug = noobrocket_get_constant( 'NOOBROCKET_SLUG', 'noobrocket_settings' );

		return [
			'noobrocket_varnish_ip'                         => 'set_varnish_localhost',
			'noobrocket_varnish_purge_request_host'         => 'set_varnish_purge_request_host',
			'noobrocket_cron_deactivate_cloudflare_devmode' => 'deactivate_devmode',
			'noobrocket_after_clean_domain'                 => 'auto_purge',
			'after_noobrocket_clean_post'                   => [ 'auto_purge_by_url', 10, 3 ],
			'after_noobrocket_clean_home'                   => [ 'purge_url', 10, 2 ],
			'after_noobrocket_clean_files'                  => 'purge_url',
			'admin_post_noobrocket_purge_cloudflare'        => 'purge_cache',
			'init'                                      => [ 'set_real_ip', 1 ],
			'update_option_' . $slug                    => [
				[ 'save_cloudflare_options', 10, 2 ],
				[ 'update_dev_mode', 11, 2 ],
			],
			'pre_update_option_' . $slug                => [
				[ 'change_auth', 8, 2 ],
				[ 'delete_connection_transient', 10, 2 ],
				[ 'save_cloudflare_old_settings', 10, 2 ],
				[ 'display_settings_notice', 11, 2 ],
			],
			'noobrocket_buffer'                             => [ 'protocol_rewrite', PHP_INT_MAX ],
			'wp_calculate_image_srcset'                 => [ 'protocol_rewrite_srcset', PHP_INT_MAX ],
			'noobrocket_cdn_helper_addons'                  => 'add_cdn_helper_message',
		];
	}

	/**
	 * Sets the Varnish IP to localhost if Cloudflare is active.
	 *
	 * @param string|array $varnish_ip Varnish IP.
	 *
	 * @return array
	 */
	public function set_varnish_localhost( $varnish_ip ) {
		if ( ! $this->should_filter_varnish() ) {
			return $varnish_ip;
		}

		if ( is_string( $varnish_ip ) ) {
			$varnish_ip = (array) $varnish_ip;
		}

		$varnish_ip[] = 'localhost';

		return $varnish_ip;
	}

	/**
	 * Sets the Host header to the website domain if Cloudflare is active.
	 *
	 * @param string $host the host header value.
	 *
	 * @return string
	 */
	public function set_varnish_purge_request_host( $host ) {
		if ( ! $this->should_filter_varnish() ) {
			return $host;
		}

		return wp_parse_url( home_url(), PHP_URL_HOST );
	}

	/**
	 * Checks if we should filter the value for the Varnish purge.
	 *
	 * @return bool
	 */
	private function should_filter_varnish(): bool {
		// This filter is documented in inc/Addon/Varnish.php.
		return apply_filters( 'do_noobrocket_varnish_http_purge', false ) // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
			||
			$this->options->get( 'varnish_auto_purge', 0 );
	}

	/**
	 * Automatically set Cloudflare development mode value to off after 3 hours to reflect Cloudflare behaviour.
	 *
	 * @return void
	 */
	public function deactivate_devmode() {
		$this->options->set( 'cloudflare_devmode', 0 );
		$this->options_api->set( 'settings', $this->options->get_options() );
	}

	/**
	 * Purge Cloudflare cache automatically if Cache Everything is set as a Page Rule.
	 *
	 * @return void
	 */
	public function auto_purge() {
		$zone_id = $this->get_auto_purge_zone();
		if ( '' === $zone_id ) {
			return;
		}

		// Purge CloudFlare.
		$this->cloudflare->purge_cloudflare( $zone_id );
	}

	/**
	 * Resolve the saved target for trusted local-cache synchronization, including cron.
	 *
	 * Manual endpoints keep their separate nonce and capability checks.
	 *
	 * @return string Validated zone ID, or an empty string when synchronization is unavailable.
	 */
	private function get_auto_purge_zone(): string {
		$settings = $this->options_api->get( 'settings', [] );
		if ( empty( $settings['do_cloudflare'] ) || empty( $settings['cloudflare_zone_id'] ) ) {
			return '';
		}

		$this->options->set_values( $settings );
		$zone_id = (string) $settings['cloudflare_zone_id'];
		if ( true !== $this->validate_connection( $settings ) ) {
			return '';
		}

		$cache_everything = $this->cloudflare->has_page_rule( 'cache_everything', $zone_id );
		return true === $cache_everything ? $zone_id : '';
	}

	/**
	 * Validate one submitted or saved connection without reusing another target's result.
	 *
	 * @param array $settings Connection settings for this operation.
	 * @return bool|WP_Error
	 */
	private function validate_connection( array $settings ) {
		$this->cloudflare->change_auth( $this->auth_factory->create( $settings ) );
		$zone_id = (string) ( $settings['cloudflare_zone_id'] ?? '' );
		return '' === $zone_id ? $this->cloudflare->is_auth_valid( '' ) : $this->cloudflare->check_connection( $zone_id );
	}

	/**
	 * Purge Cloudflare cache URLs automatically if Cache Everything is set as a Page Rule.
	 *
	 * @param WP_Post $post       The post object.
	 * @param array   $purge_urls URLs cache files to remove.
	 * @param string  $lang       The post language.
	 *
	 * @return void
	 */
	public function auto_purge_by_url( $post, $purge_urls, $lang ) {
		$zone_id = $this->get_auto_purge_zone();
		if ( '' === $zone_id ) {
			return;
		}

		// Add home URL and feeds URLs to Cloudflare clean cache URLs list.
		$purge_urls[] = get_noobrocket_i18n_home_url( $lang );
		$feed_urls    = [];
		$feed_urls[]  = get_feed_link();
		$feed_urls[]  = get_feed_link( 'comments_' );

		// this filter is documented in inc/functions/files.php.
		$feed_urls  = apply_filters( 'noobrocket_clean_home_feeds', $feed_urls );
		$purge_urls = array_unique( array_merge( $purge_urls, $feed_urls ) );

		// Purge CloudFlare.
		$this->cloudflare->purge_by_url( $post, $purge_urls, $lang, $zone_id );
	}

	/**
	 * Purge specific Cloudflare cache URLs automatically.
	 *
	 * Used as the callback for both after_noobrocket_clean_home( $root, $lang ) and after_noobrocket_clean_files( $urls ).
	 *
	 * @param array|string $urls_or_root Array of URLs to purge when triggered by after_noobrocket_clean_files, or the home cache root path when triggered by after_noobrocket_clean_home.
	 * @param string       $lang         The language to purge, only used when triggered by after_noobrocket_clean_home.
	 *
	 * @return void
	 */
	public function purge_url( $urls_or_root, $lang = '' ) {
		$zone_id = $this->get_auto_purge_zone();
		if ( '' === $zone_id ) {
			return;
		}

		// after_noobrocket_clean_files passes an array of URLs directly; after_noobrocket_clean_home passes a cache root path, so purge the home URL for that lang instead.
		$purge_urls = is_array( $urls_or_root ) ? $urls_or_root : [ get_noobrocket_i18n_home_url( $lang ) ];
		$purge_urls = array_unique( array_filter( $purge_urls ) );

		if ( empty( $purge_urls ) ) {
			return;
		}

		// Purge CloudFlare.
		$this->cloudflare->purge_by_url( null, $purge_urls, $lang, $zone_id );
	}

	/**
	 * Purge CloudFlare cache.
	 *
	 * @return void
	 */
	public function purge_cache_no_die() {
		if ( ! current_user_can( 'noobrocket_purge_cloudflare_cache' ) ) {
			return;
		}

		$connection = $this->cloudflare->check_connection();

		if ( is_wp_error( $connection ) ) {
			$cf_purge_result = [
				'result'  => 'error',
				'message' => sprintf(
					// translators: %1$s = <strong>, %2$s = </strong>, %3$s = CloudFare API return message.
					__( '%1$sCache-Cache:%2$s %3$s', 'noobrocket' ),
					'<strong>',
					'</strong>',
					$connection->get_error_message()
				),
			];

			set_transient( get_current_user_id() . '_cloudflare_purge_result', $cf_purge_result );

			return;
		}

		// Purge CloudFlare.
		$cf_purge        = $this->cloudflare->purge_cloudflare();
		$cf_purge_result = [
			'result'  => 'success',
			'message' => sprintf(
				// translators: %1$s = <strong>, %2$s = </strong>.
				__( '%1$sCache-Cache:%2$s Cloudflare cache successfully purged.', 'noobrocket' ),
				'<strong>',
				'</strong>'
			),
		];

		if ( is_wp_error( $cf_purge ) ) {
			$cf_purge_result = [
				'result'  => 'error',
				'message' => sprintf(
					// translators: %1$s = <strong>, %2$s = </strong>, %3$s = CloudFare API return message.
					__( '%1$sCache-Cache:%2$s %3$s', 'noobrocket' ),
					'<strong>',
					'</strong>',
					$cf_purge->get_error_message()
				),
			];
		}

		set_transient( get_current_user_id() . '_cloudflare_purge_result', $cf_purge_result );
	}

	/**
	 * Purge CloudFlare cache.
	 *
	 * @return void
	 */
	public function purge_cache() {
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'noobrocket_purge_cloudflare' ) ) {
			wp_nonce_ays( '' );
		}

		$this->purge_cache_no_die();

		wp_safe_redirect( esc_url_raw( wp_get_referer() ) );
		defined( 'WPMEDIA_IS_TESTING' ) ? wp_die() : exit;
	}

	/**
	 * Set Real IP from CloudFlare.
	 *
	 * @return void
	 */
	public function set_real_ip() {
		Cloudflare::set_ip_rewrite();
	}

	/**
	 * Save Cloudflare dev mode admin option.
	 *
	 * @param string $value New value for Cloudflare dev mode.
	 * @param string $zone_id Validated transaction target.
	 *
	 * @return string[]
	 */
	private function save_cloudflare_devmode( $value, string $zone_id ) {
		$result = $this->cloudflare->set_devmode( $value, $zone_id );

		if ( is_wp_error( $result ) ) {
			return [
				'result'  => 'error',
				// translators: %s is the message returned by the CloudFlare API.
				'message' => sprintf( __( 'Cloudflare development mode error: %s', 'noobrocket' ), $result->get_error_message() ),
			];
		}

		return [
			'result'  => 'success',
			// translators: %s is the message returned by the CloudFlare API.
			'message' => sprintf( __( 'Cloudflare development mode %s', 'noobrocket' ), $result ),
		];
	}

	/**
	 * Save Cloudflare cache_level admin option.
	 *
	 * @param string $value New value for Cloudflare cache_level.
	 * @param string $zone_id Validated transaction target.
	 *
	 * @return string[]
	 */
	private function save_cache_level( $value, string $zone_id ) {
		// Set Cache Level to Aggressive.
		$result = $this->cloudflare->set_cache_level( $value, $zone_id );

		if ( is_wp_error( $result ) ) {
			return [
				'result'  => 'error',
				// translators: %s is the message returned by the CloudFlare API.
				'message' => sprintf( __( 'Cloudflare cache level error: %s', 'noobrocket' ), $result->get_error_message() ),
			];
		}

		$level = $value;

		if ( 'aggressive' === $result ) {
			$level = _x( 'standard', 'Cloudflare caching level', 'noobrocket' );
		}

		return [
			'result'  => 'success',
			// translators: %s is the caching level returned by the CloudFlare API.
			'message' => sprintf( __( 'Cloudflare cache level set to %s', 'noobrocket' ), $level ),
		];
	}

	/**
	 * Save Cloudflare rocket loader admin option.
	 *
	 * @param string $value New value for Cloudflare rocket loader.
	 * @param string $zone_id Validated transaction target.
	 *
	 * @return string[]
	 */
	private function save_rocket_loader( $value, string $zone_id ) {
		$result = $this->cloudflare->set_rocket_loader( $value, $zone_id );

		if ( is_wp_error( $result ) ) {
			return [
				'result'  => 'error',
				// translators: %s is the message returned by the CloudFlare API.
				'message' => sprintf( __( 'Cloudflare rocket loader error: %s', 'noobrocket' ), $result->get_error_message() ),
			];
		}

		return [
			'result'  => 'success',
			// translators: %s is the message returned by the CloudFlare API.
			'message' => sprintf( __( 'Cloudflare rocket loader %s', 'noobrocket' ), $result ),
		];
	}

	/**
	 * Save Cloudflare browser cache ttl admin option.
	 *
	 * @param int $value New value for Cloudflare browser cache ttl.
	 * @param string $zone_id Validated transaction target.
	 *
	 * @return string[]
	 */
	private function save_browser_cache_ttl( $value, string $zone_id ) {
		$result = $this->cloudflare->set_browser_cache_ttl( $value, $zone_id );

		if ( is_wp_error( $result ) ) {
			return [
				'result'  => 'error',
				// translators: %s is the message returned by the CloudFlare API.
				'message' => sprintf( __( 'Cloudflare browser cache error: %s', 'noobrocket' ), $result->get_error_message() ),
			];
		}

		return [
			'result'  => 'success',
			// translators: %s is the message returned by the CloudFlare API.
			'message' => sprintf( __( 'Cloudflare browser cache set to %s', 'noobrocket' ), $result ),
		];
	}

	/**
	 * Save Cloudflare auto settings admin option.
	 *
	 * @param int    $auto_settings New value for Cloudflare auto_settings.
	 * @param string $old_settings Cloudflare cloudflare_old_settings.
	 * @param string $zone_id Validated transaction target.
	 *
	 * @return array<int, array<string>>
	 */
	private function save_cloudflare_auto_settings( $auto_settings, $old_settings, string $zone_id ) {
		$backup = is_string( $old_settings ) ? json_decode( $old_settings, true ) : null;
		if ( ! is_array( $backup ) || ( $backup['zone_id'] ?? '' ) !== $zone_id || ! $this->valid_previous_settings( $backup['settings'] ?? null ) ) {
			return [
				[
					'result'  => 'error',
					'message' => __( 'Cloudflare settings were not changed: a complete backup for this zone is unavailable. Reconnect the previous zone to restore its settings.', 'noobrocket' ),
				],
			];
		}

		$settings = $backup['settings'];
		if ( 0 !== (int) $auto_settings ) {
			$settings = [
				'cache_level'       => 'aggressive',
				'rocket_loader'     => 'off',
				'browser_cache_ttl' => 31536000,
			];
		}

		// Keep each provider outcome: a partial application is not a successful transaction.
		return [
			$this->save_cache_level( $settings['cache_level'], $zone_id ),
			$this->save_rocket_loader( $settings['rocket_loader'], $zone_id ),
			$this->save_browser_cache_ttl( $settings['browser_cache_ttl'], $zone_id ),
		];
	}

	/**
	 * Require every value the integration changes before recording or restoring it.
	 *
	 * @param mixed $settings Previous provider settings.
	 * @return bool
	 */
	private function valid_previous_settings( $settings ): bool {
		return is_array( $settings )
			&& in_array( $settings['cache_level'] ?? null, [ 'aggressive', 'basic', 'simplified' ], true )
			&& in_array( $settings['rocket_loader'] ?? null, [ 'on', 'off' ], true )
			&& isset( $settings['browser_cache_ttl'] )
			&& ( is_int( $settings['browser_cache_ttl'] ) || is_string( $settings['browser_cache_ttl'] ) )
			&& false !== filter_var( $settings['browser_cache_ttl'], FILTER_VALIDATE_INT, [ 'options' => [ 'min_range' => 0 ] ] );
	}

	/**
	 * Keep a connection failure visible in the existing provider feedback.
	 *
	 * @param WP_Error $error Failure to report.
	 * @return void
	 */
	private function record_settings_error( WP_Error $error ) {
		$result = get_transient( get_current_user_id() . '_cloudflare_update_settings' );
		$result = is_array( $result ) ? $result : [];
		$result[] = [ 'result' => 'error', 'message' => $error->get_error_message() ];
		set_transient(
			get_current_user_id() . '_cloudflare_update_settings',
			$result
		);
	}

	/**
	 * Update the development mode value on Cloudflare
	 *
	 * @param array $old_value An array of previous values for the settings.
	 * @param array $value     An array of submitted values for the settings.
	 *
	 * @return void
	 */
	public function update_dev_mode( $old_value, $value ) {
		if ( ! current_user_can( 'noobrocket_manage_options' ) ) {
			return;
		}

		if ( ! isset( $old_value['cloudflare_devmode'], $value['cloudflare_devmode'] ) ) {
			return;
		}

		if ( (int) $old_value['cloudflare_devmode'] === (int) $value['cloudflare_devmode'] ) {
			return;
		}

		if ( empty( $value['do_cloudflare'] ) ) {
			return;
		}

		$connection = $this->validate_connection( $value );

		if ( is_wp_error( $connection ) ) {
			$this->record_settings_error( $connection );
			return;
		}

		$result = [
			'pre' => sprintf(
				'%1$sNoobRocket:%2$s',
				'<strong>',
				'</strong>&nbsp;'
			),
		];
		$update = get_transient( get_current_user_id() . '_cloudflare_update_settings' );

		if ( false !== $update ) {
			$result = $update;
		}

		$result[] = $this->save_cloudflare_devmode( $value['cloudflare_devmode'], (string) $value['cloudflare_zone_id'] );

		set_transient( get_current_user_id() . '_cloudflare_update_settings', $result );
	}

	/**
	 * Save Cloudflare admin options.
	 *
	 * @param array $old_value An array of previous values for the settings.
	 * @param array $value     An array of submitted values for the settings.
	 *
	 * @return void
	 */
	public function save_cloudflare_options( $old_value, $value ) {
		if ( ! current_user_can( 'noobrocket_manage_options' ) ) {
			return;
		}

		if ( ! isset( $old_value['cloudflare_auto_settings'], $value['cloudflare_auto_settings'] ) ) {
			return;
		}

		if ( (int) $old_value['cloudflare_auto_settings'] === (int) $value['cloudflare_auto_settings'] ) {
			return;
		}

		if ( empty( $value['do_cloudflare'] ) ) {
			return;
		}

		$connection = $this->validate_connection( $value );

		if ( is_wp_error( $connection ) ) {
			$this->record_settings_error( $connection );
			return;
		}

		$result = [
			'pre' => sprintf(
				// translators: %1$s = strong opening tag, %2$s = strong closing tag.
				__( '%1$sCache-Cache:%2$s Optimal settings activated for Cloudflare:', 'noobrocket' ),
				'<strong>',
				'</strong>'
			) . '<br>',
		];

		if ( 0 === (int) $value['cloudflare_auto_settings'] ) {
			$result['pre'] = sprintf(
				// translators: %1$s = strong opening tag, %2$s = strong closing tag.
				__( '%1$sCache-Cache:%2$s Optimal settings deactivated for Cloudflare, reverted to previous settings:', 'noobrocket' ),
				'<strong>',
				'</strong>'
			) . '<br>';
		}

		$outcomes = $this->save_cloudflare_auto_settings( $value['cloudflare_auto_settings'], $value['cloudflare_old_settings'] ?? '', (string) $value['cloudflare_zone_id'] );
		$result = array_merge( $result, $outcomes );

		set_transient( get_current_user_id() . '_cloudflare_update_settings', $result );
		if ( 0 === (int) $value['cloudflare_auto_settings'] && ! in_array( 'error', array_column( $outcomes, 'result' ), true ) ) {
			$this->clear_restored_backup( $value );
		}
	}

	/**
	 * Release recovery metadata only after all remote values have been restored.
	 *
	 * @param array $restored Settings whose backup was restored.
	 * @return void
	 */
	private function clear_restored_backup( array $restored ) {
		$stored = $this->options_api->get( 'settings', [] );
		if ( $stored !== $restored ) {
			// Another settings change won ownership; do not write an older full settings snapshot.
			return;
		}

		$stored['cloudflare_old_settings'] = '';
		$this->clearing_restored_backup = true;
		try {
			$this->options_api->set( 'settings', $stored );
		} finally {
			$this->clearing_restored_backup = false;
		}

		if ( $this->options_api->get( 'settings', [] ) === $restored ) {
			$this->record_settings_error( new WP_Error( 'cloudflare_backup_clear_failed', __( 'Cloudflare settings were restored, but their recovery backup could not be cleared. The original backup has been retained.', 'noobrocket' ) ) );
		}
	}

	/**
	 * Save Cloudflare old settings when the auto settings option is enabled.
	 *
	 * @param array $value     Submitted settings.
	 * @param array $old_value Previously saved settings.
	 *
	 * @return array
	 */
	public function save_cloudflare_old_settings( $value, $old_value ) {
		if ( $this->clearing_restored_backup ) {
			return $value;
		}

		if ( ! current_user_can( 'noobrocket_manage_options' ) ) {
			return $value;
		}

		// The backup belongs to the provider transaction, not the submitted hidden field.
		$value['cloudflare_old_settings'] = $old_value['cloudflare_old_settings'] ?? '';
		if ( empty( $value['do_cloudflare'] ) || ! isset( $value['cloudflare_auto_settings'], $old_value['cloudflare_auto_settings'] ) ) {
			return $value;
		}

		$zone_id      = (string) ( $value['cloudflare_zone_id'] ?? '' );
		$zone_changed = $zone_id !== (string) ( $old_value['cloudflare_zone_id'] ?? '' );
		if ( $zone_changed && ! empty( $old_value['cloudflare_auto_settings'] ) && ! empty( $value['cloudflare_auto_settings'] ) ) {
			foreach ( [ 'cloudflare_zone_id', 'cloudflare_email', 'cloudflare_api_key' ] as $field ) {
				$value[ $field ] = $old_value[ $field ] ?? '';
			}
			if ( function_exists( 'add_settings_error' ) ) {
				add_settings_error( 'general', 'cloudflare_backup_zone_changed', __( 'The Cloudflare connection was not changed while optimal settings are active. Restore the previous zone before switching; its connection and backup have been retained.', 'noobrocket' ), 'error' );
			}
			return $value;
		}

		if ( (int) $value['cloudflare_auto_settings'] === (int) $old_value['cloudflare_auto_settings'] || 0 === (int) $value['cloudflare_auto_settings'] ) {
			return $value;
		}

		if ( '' !== $value['cloudflare_old_settings'] ) {
			$backup = is_string( $value['cloudflare_old_settings'] ) ? json_decode( $value['cloudflare_old_settings'], true ) : null;
			if ( is_array( $backup ) && ( $backup['zone_id'] ?? '' ) === $zone_id && $this->valid_previous_settings( $backup['settings'] ?? null ) ) {
				// Re-enabling after an incomplete restore keeps the original recoverable values.
				return $value;
			}

			$value['cloudflare_auto_settings'] = $old_value['cloudflare_auto_settings'];
			if ( function_exists( 'add_settings_error' ) ) {
				add_settings_error( 'general', 'cloudflare_recovery_pending', __( 'Cloudflare optimal settings were not enabled because a previous recovery backup is unresolved. Restore the previous zone before replacing its backup.', 'noobrocket' ), 'error' );
			}
			return $value;
		}

		$connection  = $this->validate_connection( $value );
		$cf_settings = is_wp_error( $connection ) ? $connection : $this->cloudflare->get_settings( $zone_id );
		if ( is_wp_error( $cf_settings ) || ! $this->valid_previous_settings( $cf_settings ) ) {
			// Do not apply settings without a recoverable snapshot or replace an existing one with an empty backup.
			$value['cloudflare_auto_settings'] = $old_value['cloudflare_auto_settings'];
			if ( function_exists( 'add_settings_error' ) ) {
				add_settings_error( 'general', 'cloudflare_backup_unavailable', __( 'Cloudflare optimal settings were not enabled because the previous zone settings could not be backed up. Existing recovery settings were retained.', 'noobrocket' ), 'error' );
			}
			return $value;
		}

		$value['cloudflare_old_settings'] = wp_json_encode(
			[
				'zone_id'  => $zone_id,
				'settings' => [
					'cache_level'       => $cf_settings['cache_level'],
					'rocket_loader'     => $cf_settings['rocket_loader'],
					'browser_cache_ttl' => (int) $cf_settings['browser_cache_ttl'],
				],
			]
		);

		return $value;
	}

	/**
	 * Change the authentication.
	 *
	 * @param array $value     An array of previous values for the settings.
	 * @param array $old_value An array of submitted values for the settings.
	 *
	 * @return mixed
	 */
	public function change_auth( $value, $old_value ) {
		$auth = $this->auth_factory->create( $value );
		$this->cloudflare->change_auth( $auth );
		return $value;
	}

	/**
	 * Delete the transient CF connection status when API Key, Email or Zone ID is changed
	 *
	 * @param array $value     An array of previous values for the settings.
	 * @param array $old_value An array of submitted values for the settings.
	 *
	 * @return array
	 */
	public function delete_connection_transient( $value, $old_value ) {

		$fields = [
			'cloudflare_api_key',
			'cloudflare_email',
			'cloudflare_zone_id',
			'cloudflare_devmode',
			'cloudflare_auto_settings',
			'cloudflare_protocol_rewrite',
		];

		$change = false;

		foreach ( $fields as $field ) {
			$change |= ! isset( $old_value[ $field ], $value[ $field ] ) || $old_value[ $field ] !== $value[ $field ];
		}

		if ( ! $change ) {
			return $value;
		}

		delete_transient( get_current_user_id() . '_cloudflare_update_settings' );
		delete_transient( 'noobrocket_cloudflare_is_api_keys_valid' );

		return $value;
	}

	/**
	 * Display the error notice.
	 *
	 * @param array $value     Submitted settings.
	 * @param array $old_value Previously saved settings.
	 *
	 * @return mixed
	 */
	public function display_settings_notice( $value, $old_value ) {

		if ( empty( $value['do_cloudflare'] ) || ! key_exists( 'cloudflare_zone_id', $value ) ) {
			return $value;
		}

		// An unrelated settings save does not validate an unchanged connection again.
		$connection_changed = empty( $old_value['do_cloudflare'] );
		foreach ( [ 'cloudflare_email', 'cloudflare_api_key', 'cloudflare_zone_id' ] as $field ) {
			if ( ( $value[ $field ] ?? '' ) !== ( $old_value[ $field ] ?? '' ) ) {
				$connection_changed = true;
				break;
			}
		}

		if ( ! $connection_changed ) {
			return $value;
		}

		$connection = $this->cloudflare->check_connection( $value['cloudflare_zone_id'] );

		if ( is_wp_error( $connection ) && function_exists( 'add_settings_error' ) ) {
			add_settings_error( 'general', 'cloudflare_api_key_invalid', __( 'Cache-Cache: ', 'noobrocket' ) . '</strong>' . $connection->get_error_message() . '<strong>', 'error' );
		}

		return $value;
	}

	/**
	 * Remove HTTP protocol on script, link, img and form tags.
	 *
	 * @param string $buffer HTML content.
	 *
	 * @return string
	 */
	public function protocol_rewrite( $buffer ) {
		if ( ! $this->can_protocol_rewrite() ) {
			return $buffer;
		}

		$return = preg_replace( "/(<(script|link|img|form)(?!.*?[\"']\bcanonical\b[\"'])([^>]*)(href|src|action)=[\"'])https?:\\/\\//i", '$1//', $buffer );

		if ( $return ) {
			$buffer = $return;
		}

		return $buffer;
	}

	/**
	 * Remove HTTP protocol on srcset attribute generated by WordPress
	 *
	 * @param array $sources an Array of images sources for srcset.
	 *
	 * @return array
	 */
	public function protocol_rewrite_srcset( $sources ) {
		if ( ! $this->can_protocol_rewrite() ) {
			return $sources;
		}

		if ( empty( $sources ) ) {
			return $sources;
		}

		foreach ( $sources as $i => $source ) {
			$sources[ $i ]['url'] = str_replace( [ 'http:', 'https:' ], '', $source['url'] );
		}

		return $sources;
	}

	/**
	 * Can rewrite protocol
	 *
	 * @return bool
	 */
	private function can_protocol_rewrite(): bool {
		return $this->options->get( 'do_cloudflare', 0 )
		&&
		(
			$this->options->get( 'cloudflare_protocol_rewrite', 0 )
			||
			apply_filters( 'do_noobrocket_protocol_rewrite', false ) // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		);
	}

	/**
	 * Add the helper message on the CDN settings.
	 *
	 * @param string[] $addons Name from the addon that requires the helper message.
	 * @return string[]
	 */
	public function add_cdn_helper_message( array $addons ): array {
		$addons[] = 'Cloudflare';
		return $addons;
	}
}
