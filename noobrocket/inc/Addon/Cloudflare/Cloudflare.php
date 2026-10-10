<?php
declare(strict_types=1);

namespace NoobRocket\Addon\Cloudflare;

use CloudFlare\IpRewrite;
use DateTimeImmutable;
use WP_Error;
use WP_Post;
use NoobRocket\Addon\Cloudflare\Auth\AuthInterface;
use NoobRocket\Admin\Options_Data;
use NoobRocket\Addon\Cloudflare\API\Endpoints;

class Cloudflare {
	/**
	 * Options Data instance.
	 *
	 * @var Options_Data
	 */
	private $options;

	/**
	 * Endpoints instance.
	 *
	 * @var Endpoints
	 */
	private $endpoints;

	/**
	 * Constructor
	 *
	 * @param Options_Data $options NoobRocket options instance.
	 * @param Endpoints    $endpoints Endpoints instance.
	 */
	public function __construct( Options_Data $options, Endpoints $endpoints ) {
		$this->endpoints = $endpoints;
		$this->options   = $options;
	}

	/**
	 * Check valid connection with Cloudflare
	 *
	 * @param string $zone_id Cloudflare zone ID.
	 * @return bool|mixed|WP_Error
	 */
	public function check_connection( string $zone_id = '' ) {
		// A submitted target uses the current credentials, never another connection's cached result.
		if ( '' !== $zone_id ) {
			return $this->is_auth_valid( $zone_id );
		}

		$is_valid = get_transient( 'noobrocket_cloudflare_is_api_keys_valid' );
		if ( false === $is_valid ) {

			if ( '' === $zone_id ) {
				$zone_id = $this->options->get( 'cloudflare_zone_id', '' );
			}

			$is_valid = $this->is_auth_valid( $zone_id );

			set_transient( 'noobrocket_cloudflare_is_api_keys_valid', $is_valid, 2 * WEEK_IN_SECONDS );
		}

		return $is_valid;
	}

	/**
	 * Validate Cloudflare input data.
	 *
	 * @param string $zone_id Cloudflare zone ID.
	 *
	 * @return bool|WP_Error true if credentials are ok, WP_Error otherwise.
	 */
	public function is_auth_valid( string $zone_id ) {
		if ( empty( $zone_id ) ) {
			$msg = __( 'Missing Cloudflare Zone ID.', 'noobrocket' );

			$msg .= ' ' . sprintf(
				/* translators: %1$s = opening link; %2$s = closing link */
				__( 'Read the %1$sdocumentation%2$s for further guidance.', 'noobrocket' ),
				// translators: Documentation exists in EN, FR; use localized URL if applicable.
				'<a href="' . esc_url( get_noobrocket_documentation_url() ) . '" rel="noopener noreferrer" target="_blank">',
				'</a>'
			);

			return new WP_Error( 'cloudflare_no_zone_id', $msg );
		}

		$result = $this->endpoints->get_zones( $zone_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$zone_found = false;
		$site_url   = get_site_url();

		if ( function_exists( 'domain_mapping_siteurl' ) ) {
			$site_url = domain_mapping_siteurl( $site_url );
		}

		$parsed_url = wp_parse_url( $site_url );

		if ( is_object( $result ) && isset( $result->name, $parsed_url['host'] ) && is_string( $result->name ) ) {
			$host = strtolower( rtrim( $parsed_url['host'], '.' ) );
			$zone = strtolower( rtrim( $result->name, '.' ) );
			$valid_zone = filter_var( $zone, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME );

			$zone_found = $valid_zone && ( $host === $zone || substr( $host, -strlen( '.' . $zone ) ) === '.' . $zone );
		}

		if ( ! $zone_found ) {
			$msg = __( 'It looks like your domain is not set up on Cloudflare.', 'noobrocket' );

			$msg .= ' ' . sprintf(
				/* translators: %1$s = opening link; %2$s = closing link */
				__( 'Read the %1$sdocumentation%2$s for further guidance.', 'noobrocket' ),
				// translators: Documentation exists in EN, FR; use localized URL if applicable.
				'<a href="' . esc_url( get_noobrocket_documentation_url() ) . '" rel="noopener noreferrer" target="_blank">',
				'</a>'
			);

			return new WP_Error( 'cloudflare_zone_not_found', $msg );
		}

		return true;
	}

	/**
	 * Checks if CF has the $action_value set as a Page Rule.
	 *
	 * @param string $action_value Action value.
	 * @param string $zone_id Explicit operation target; defaults to the saved zone.
	 *
	 * @return mixed true/false if $action_value was found or not, WP_Error otherwise.
	 */
	public function has_page_rule( $action_value, string $zone_id = '' ) {
		$result = $this->endpoints->list_pagerules( $zone_id ?: $this->options->get( 'cloudflare_zone_id', '' ), 'active' );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$page_rule = wp_json_encode( $result );

		return (bool) preg_match( '/' . $action_value . '/', $page_rule );
	}

	/**
	 * Purge Cloudflare cache.
	 *
	 * @param string $zone_id Explicit operation target; defaults to the saved zone.
	 * @return mixed true if the purge is successful, WP_Error otherwise.
	 */
	public function purge_cloudflare( string $zone_id = '' ) {
		$result = $this->endpoints->purge( $zone_id ?: $this->options->get( 'cloudflare_zone_id', '' ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	/**
	 * Purge Cloudflare Cache by URL
	 *
	 * @param WP_Post $post       The post object.
	 * @param array   $purge_urls URLs cache files to remove.
	 * @param string  $lang       The post language.
	 * @param string  $zone_id    Explicit operation target; defaults to the saved zone.
	 *
	 * @return mixed true if the purge is successful, WP_Error otherwise
	 */
	public function purge_by_url( $post, $purge_urls, $lang, string $zone_id = '' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$result = $this->endpoints->purge_files( $zone_id ?: $this->options->get( 'cloudflare_zone_id', '' ), $purge_urls );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	/**
	 * Set the Browser Cache TTL in Cloudflare.
	 *
	 * @param string $value Value for Cloudflare browser cache TTL.
	 * @param string $zone_id Explicit operation target; defaults to the saved zone.
	 *
	 * @return mixed Value if the update is successful, WP_Error otherwise.
	 */
	public function set_browser_cache_ttl( $value, string $zone_id = '' ) {
		$result = $this->endpoints->update_browser_cache_ttl( $zone_id ?: $this->options->get( 'cloudflare_zone_id', '' ), (int) $value );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->convert_time( (int) $value );
	}

	/**
	 * Convert value in seconds to seconds/minutes/hours/days
	 *
	 * @param int $value Value in seconds.
	 *
	 * @return string
	 */
	private function convert_time( int $value ): string {
		$base   = new DateTimeImmutable( '@0' );
		$time   = new DateTimeImmutable( "@$value" );
		$format = '%a ' . __( 'days', 'noobrocket' );

		if ( 60 > $value ) {
			$format = '%s ' . __( 'seconds', 'noobrocket' );
		} elseif ( 3600 > $value ) {
			$format = '%i ' . __( 'minutes', 'noobrocket' );
		} elseif ( 86400 > $value ) {
			$format = '%h ' . __( 'hours', 'noobrocket' );
		}

		return $base->diff( $time )->format( $format );
	}

	/**
	 * Set the Cloudflare Rocket Loader.
	 *
	 * @param string $value Value for Cloudflare Rocket Loader.
	 * @param string $zone_id Explicit operation target; defaults to the saved zone.
	 *
	 * @return mixed Value if the update is successful, WP_Error otherwise.
	 */
	public function set_rocket_loader( $value, string $zone_id = '' ) {
		$result = $this->endpoints->update_rocket_loader( $zone_id ?: $this->options->get( 'cloudflare_zone_id', '' ), $value );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $value;
	}

	/**
	 * Set the Cloudflare Minification.
	 *
	 * @param string $value Value for Cloudflare minification.
	 * @param string $zone_id Explicit operation target; defaults to the saved zone.
	 *
	 * @return mixed Value if the update is successful, WP_Error otherwise.
	 */
	public function set_minify( $value, string $zone_id = '' ) {
		$cf_minify_settings = [
			'css'  => $value,
			'html' => $value,
			'js'   => $value,
		];

		$result = $this->endpoints->update_minify( $zone_id ?: $this->options->get( 'cloudflare_zone_id', '' ), $cf_minify_settings );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $value;
	}

	/**
	 * Set the Cloudflare Caching level.
	 *
	 * @param string $value Value for Cloudflare caching level.
	 * @param string $zone_id Explicit operation target; defaults to the saved zone.
	 *
	 * @return mixed Value if the update is successful, WP_Error otherwise.
	 */
	public function set_cache_level( $value, string $zone_id = '' ) {
		$result = $this->endpoints->change_cache_level( $zone_id ?: $this->options->get( 'cloudflare_zone_id', '' ), $value );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $value;
	}

	/**
	 * Set the Cloudflare Development mode.
	 *
	 * @param string $value Value for Cloudflare development mode.
	 * @param string $zone_id Explicit operation target; defaults to the saved zone.
	 *
	 * @return mixed Value if the update is successful, WP_Error otherwise.
	 */
	public function set_devmode( $value, string $zone_id = '' ) {
		if ( 0 === (int) $value ) {
			$value = 'off';
		} else {
			$value = 'on';
		}

		$result = $this->endpoints->change_development_mode( $zone_id ?: $this->options->get( 'cloudflare_zone_id', '' ), $value );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		switch ( $value ) {
			case 'on':
				wp_schedule_single_event( time() + 3 * HOUR_IN_SECONDS, 'noobrocket_cron_deactivate_cloudflare_devmode' );
				break;
			case 'off':
				$next_event = wp_next_scheduled( 'noobrocket_cron_deactivate_cloudflare_devmode' );

				if ( false !== $next_event ) {
					wp_unschedule_event( $next_event, 'noobrocket_cron_deactivate_cloudflare_devmode' );
				}
				break;
		}

		return $value;
	}

	/**
	 * Get all the current Cloudflare settings for a given domain.
	 *
	 * @param string $zone_id Explicit operation target; defaults to the saved zone.
	 * @return array|WP_Error Array of Cloudflare settings, WP_Error if any error connection to Cloudflare.
	 */
	public function get_settings( string $zone_id = '' ) {
		$cf_settings = $this->endpoints->get_settings( $zone_id ?: $this->options->get( 'cloudflare_zone_id', '' ) );

		if ( is_wp_error( $cf_settings ) ) {
			return $cf_settings;
		}
		if ( ! is_array( $cf_settings ) ) {
			return new WP_Error( 'cloudflare_invalid_settings', __( 'Cloudflare unexpected response', 'noobrocket' ) );
		}

		$browser_cache_ttl = null;
		$cache_level       = '';
		$rocket_loader     = '';
		$cf_minify         = '';

		foreach ( $cf_settings as $cloudflare_option ) {
			if ( ! is_object( $cloudflare_option ) || ! isset( $cloudflare_option->id, $cloudflare_option->value ) ) {
				return new WP_Error( 'cloudflare_invalid_settings', __( 'Cloudflare unexpected response', 'noobrocket' ) );
			}
			switch ( $cloudflare_option->id ) {
				case 'browser_cache_ttl':
					$browser_cache_ttl = $cloudflare_option->value;
					break;
				case 'cache_level':
					$cache_level = $cloudflare_option->value;
					break;
				case 'rocket_loader':
					$rocket_loader = $cloudflare_option->value;
					break;
				case 'minify':
					$cf_minify = $cloudflare_option->value;
					break;
			}
		}
		$cf_minify_value = 'on';

		if (
			! is_object( $cf_minify )
			||
			'off' === ( $cf_minify->js ?? 'off' )
			||
			'off' === ( $cf_minify->css ?? 'off' )
			||
			'off' === ( $cf_minify->html ?? 'off' )
		) {
			$cf_minify_value = 'off';
		}

		$cf_settings_array = [
			'cache_level'       => $cache_level,
			'minify'            => $cf_minify_value,
			'rocket_loader'     => $rocket_loader,
			'browser_cache_ttl' => $browser_cache_ttl,
		];

		return $cf_settings_array;
	}

	/**
	 * Get Cloudflare IPs. No API validation needed, all exceptions returns the default CF IPs array.
	 *
	 * @return object Result of API request if successful, default CF IPs otherwise.
	 */
	public function get_cloudflare_ips() {
		$cf_ips = get_transient( 'noobrocket_cloudflare_ips' );

		if ( false !== $cf_ips ) {
			return $cf_ips;
		}

		$cf_ips = $this->endpoints->get_ips();

		if ( is_wp_error( $cf_ips ) ) {
			// Set default IPs from Cloudflare if call to Cloudflare /ips API does not contain a success.
			// Prevents from making API calls on each page load.
			$cf_ips = $this->get_default_ips();
		}

		set_transient( 'noobrocket_cloudflare_ips', $cf_ips, 2 * WEEK_IN_SECONDS );

		return $cf_ips;
	}

	/**
	 * Get default Cloudflare IPs.
	 *
	 * @return object Default Cloudflare connecting IPs.
	 */
	private function get_default_ips() {
		$cf_ips = (object) [
			'ipv4_cidrs' => [],
			'ipv6_cidrs' => [],
		];

		$cf_ips->ipv4_cidrs = [
			'173.245.48.0/20',
			'103.21.244.0/22',
			'103.22.200.0/22',
			'103.31.4.0/22',
			'141.101.64.0/18',
			'108.162.192.0/18',
			'190.93.240.0/20',
			'188.114.96.0/20',
			'197.234.240.0/22',
			'198.41.128.0/17',
			'162.158.0.0/15',
			'104.16.0.0/12',
			'104.24.0.0/14',
			'172.64.0.0/13',
			'131.0.72.0/22',
		];

		$cf_ips->ipv6_cidrs = [
			'2400:cb00::/32',
			'2606:4700::/32',
			'2803:f800::/32',
			'2405:b500::/32',
			'2405:8100::/32',
			'2a06:98c0::/29',
			'2c0f:f248::/32',
		];

		return $cf_ips;
	}

	/**
	 * Sets the Cloudflare IP Rewrite
	 *
	 * @return IpRewrite
	 */
	public static function set_ip_rewrite() {
		static $instance = null;

		if ( is_null( $instance ) ) {
			$instance = new IpRewrite();

			return $instance;
		}

		return $instance;
	}

	/**
	 * Fixes Cloudflare Flexible SSL redirect loop
	 *
	 * @return void
	 */
	public static function fix_cf_flexible_ssl() {
		$ip_rewrite = self::set_ip_rewrite();

		if ( $ip_rewrite->isCloudFlare() ) {
			// Fixes Flexible SSL.
			if ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && 'https' === $_SERVER['HTTP_X_FORWARDED_PROTO'] ) {
				$_SERVER['HTTPS'] = 'on';
			}
		}
	}

	/**
	 * Change client auth.
	 *
	 * @param AuthInterface $auth Client auth.
	 *
	 * @return void
	 */
	public function change_auth( AuthInterface $auth ) {
		$this->endpoints->change_auth( $auth );
	}
}
