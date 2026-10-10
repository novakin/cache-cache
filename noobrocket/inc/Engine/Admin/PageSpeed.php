<?php
declare(strict_types=1);

namespace NoobRocket\Engine\Admin;

use NoobRocket\Event_Management\Subscriber_Interface;
use WP_Error;

/** Optional, administrator-triggered Lighthouse checks for the public homepage. */
class PageSpeed implements Subscriber_Interface {
	public static function get_subscribed_events(): array {
		return [
			'noobrocket_before_dashboard_content' => [ 'render', 20 ],
			'noobrocket_settings_page_footer'     => 'render_key_form',
			'admin_post_noobrocket_pagespeed_key' => 'handle_key',
			'wp_ajax_noobrocket_pagespeed_run'    => 'run_ajax',
		];
	}

	public function render(): void {
		if ( ! current_user_can( 'noobrocket_manage_options' ) ) {
			return;
		}

		$url     = home_url( '/' );
		$results = get_option( 'noobrocket_pagespeed_results', [] );
		$key     = get_option( 'noobrocket_pagespeed_api_key', '' );
		$data    = [
			'configured' => is_string( $key ) && '' !== $key,
			'public_url' => $this->is_public_url( $url ),
			'url'        => $url,
			'results'    => [],
			'notice'     => null,
		];
		foreach ( [ 'mobile', 'desktop' ] as $strategy ) {
			if ( is_array( $results[ $strategy ] ?? null ) && $url === ( $results[ $strategy ]['url'] ?? '' ) ) {
				$data['results'][ $strategy ] = $results[ $strategy ];
			}
		}

		$notice_key = 'noobrocket_pagespeed_notice_' . get_current_user_id();
		$notice     = get_transient( $notice_key );
		delete_transient( $notice_key );
		$messages = [
			'saved'        => __( 'PageSpeed API key saved.', 'noobrocket' ),
			'removed'      => __( 'PageSpeed API key removed. Saved results are retained.', 'noobrocket' ),
			'invalid_key'  => __( 'Enter a valid Google API key. The saved key was not changed.', 'noobrocket' ),
			'store_failed' => __( 'The PageSpeed API key change could not be saved. Check database access and retry.', 'noobrocket' ),
		];
		if ( is_string( $notice ) && isset( $messages[ $notice ] ) ) {
			$data['notice'] = [
				'code'    => $notice,
				'type'    => in_array( $notice, [ 'invalid_key', 'store_failed' ], true ) ? 'error' : 'success',
				'message' => $messages[ $notice ],
			];
		}

		require NOOBROCKET_PATH . 'views/settings/partials/pagespeed.php';
	}

	/** The Overview is inside options.php's form; credentials belong to this separate form. */
	public function render_key_form(): void {
		if ( ! current_user_can( 'noobrocket_manage_options' ) ) {
			return;
		}

		echo '<form id="noobrocket-pagespeed-key" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" method="post">';
		echo '<input type="hidden" name="action" value="noobrocket_pagespeed_key">';
		wp_nonce_field( 'noobrocket_pagespeed_key', '_noobrocket_pagespeed_nonce' );
		echo '</form>';
	}

	public function handle_key(): void {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! current_user_can( 'noobrocket_manage_options' ) ) {
			wp_die( esc_html__( 'You cannot manage PageSpeed checks.', 'noobrocket' ), '', [ 'response' => 403 ] );
		}
		check_admin_referer( 'noobrocket_pagespeed_key', '_noobrocket_pagespeed_nonce' );
		$operation = isset( $_POST['operation'] ) && is_string( $_POST['operation'] ) ? wp_unslash( $_POST['operation'] ) : '';
		if ( 'remove_key' === $operation ) {
			delete_option( 'noobrocket_pagespeed_api_key' );
			$notice = '' === get_option( 'noobrocket_pagespeed_api_key', '' ) ? 'removed' : 'store_failed';
		} elseif ( 'save_key' === $operation ) {
			$key = isset( $_POST['api_key'] ) && is_string( $_POST['api_key'] ) ? trim( wp_unslash( $_POST['api_key'] ) ) : '';
			if ( preg_match( '/\A[A-Za-z0-9_-]{20,200}\z/', $key ) ) {
				// Separate ownership keeps credentials out of optimization exports and snapshots.
				update_option( 'noobrocket_pagespeed_api_key', $key, false );
				$notice = $key === get_option( 'noobrocket_pagespeed_api_key', '' ) ? 'saved' : 'store_failed';
			} else {
				$notice = 'invalid_key';
			}
		} else {
			wp_die( esc_html__( 'Unknown PageSpeed action.', 'noobrocket' ), '', [ 'response' => 400 ] );
		}

		set_transient( 'noobrocket_pagespeed_notice_' . get_current_user_id(), $notice, MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'options-general.php?page=noobrocket' ) . '#dashboard' );
		exit;
	}

	public function run_ajax(): void {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! current_user_can( 'noobrocket_manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'You cannot run PageSpeed checks.', 'noobrocket' ) ], 403 );
		}
		if ( ! check_ajax_referer( 'noobrocket_pagespeed_run', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'This PageSpeed session expired. Reload the page and retry.', 'noobrocket' ) ], 403 );
		}
		$strategy = isset( $_POST['strategy'] ) && is_string( $_POST['strategy'] ) ? wp_unslash( $_POST['strategy'] ) : '';
		$result   = $this->analyze( $strategy );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ], 400 );
		}
		wp_send_json_success( [ 'result' => $result ] );
	}

	/** Google must be able to reach the site's configured anonymous homepage. */
	public function is_public_url( string $url ): bool {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || ! in_array( strtolower( $parts['scheme'] ?? '' ), [ 'http', 'https' ], true )
			|| isset( $parts['user'] ) || isset( $parts['pass'] ) || empty( $parts['host'] )
		) {
			return false;
		}
		$host  = trim( $parts['host'], '[]' );
		$flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return false !== filter_var( $host, FILTER_VALIDATE_IP, $flags );
		}
		if ( false === strpos( $host, '.' )
			|| preg_match( '/(?:^|\.)(localhost|local|test|internal|invalid)\.?$/i', $host )
		) {
			return false;
		}
		$address = gethostbyname( $host );
		if ( $address !== $host ) {
			return false !== filter_var( $address, FILTER_VALIDATE_IP, $flags );
		}
		// The homepage is a Google parameter, not our request target; IPv6-only sites are valid too.
		foreach ( (array) @dns_get_record( $host, DNS_AAAA ) as $record ) {
			if ( is_array( $record ) && filter_var( $record['ipv6'] ?? '', FILTER_VALIDATE_IP, $flags ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Keep only scores and numeric lab metrics. Raw API errors can include credentials.
	 *
	 * @param string $strategy Mobile or desktop.
	 * @return array|WP_Error
	 */
	public function analyze( string $strategy ) {
		if ( ! in_array( $strategy, [ 'mobile', 'desktop' ], true ) ) {
			return new WP_Error( 'strategy', __( 'Choose Mobile or Desktop for the PageSpeed check.', 'noobrocket' ) );
		}
		$key = get_option( 'noobrocket_pagespeed_api_key', '' );
		if ( ! is_string( $key ) || '' === $key ) {
			return new WP_Error( 'missing_key', __( 'Save your Google API key before running a PageSpeed check.', 'noobrocket' ) );
		}
		$url = home_url( '/' );
		if ( ! $this->is_public_url( $url ) ) {
			return new WP_Error( 'private_url', __( 'PageSpeed needs a public website. Localhost and private previews cannot be checked.', 'noobrocket' ) );
		}

		$started = time();
		$lock    = (int) get_option( 'noobrocket_pagespeed_lock', 0 );
		if ( $lock && $lock < $started - 120 ) {
			$this->release_lock( $lock );
		}
		if ( ! add_option( 'noobrocket_pagespeed_lock', $started, '', false ) ) {
			return new WP_Error( 'busy', __( 'A PageSpeed check is already running. Wait for it to finish, then retry.', 'noobrocket' ) );
		}

		try {
			$endpoint = add_query_arg( [ 'url' => $url, 'strategy' => $strategy ], 'https://pagespeedonline.googleapis.com/pagespeedonline/v5/runPagespeed' );
			foreach ( [ 'performance', 'accessibility', 'best-practices', 'seo' ] as $category ) {
				$endpoint .= '&category=' . $category;
			}
			$response = wp_safe_remote_get( $endpoint, [
				'timeout'     => 90,
				'redirection' => 0,
				'cookies'     => [],
				'headers'     => [ 'X-Goog-Api-Key' => $key, 'Accept' => 'application/json' ],
			] );
			if ( is_wp_error( $response ) ) {
				return new WP_Error( 'transport', __( 'PageSpeed could not be reached. Check server connectivity and retry.', 'noobrocket' ) );
			}
			$status  = (int) wp_remote_retrieve_response_code( $response );
			$payload = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( 200 !== $status ) {
				return $this->api_error( $status, is_array( $payload ) ? $payload : [] );
			}
			$lighthouse = is_array( $payload ) ? ( $payload['lighthouseResult'] ?? [] ) : [];
			if ( ! is_array( $lighthouse ) || ! empty( $lighthouse['runtimeError'] ) ) {
				return new WP_Error( 'analysis_failed', __( 'PageSpeed could not analyze this homepage. Check that it loads publicly without a login or challenge, then retry.', 'noobrocket' ) );
			}
			$result = [ 'url' => $url, 'checked_at' => time(), 'scores' => [], 'metrics' => [] ];
			foreach ( [ 'performance', 'accessibility', 'best-practices', 'seo' ] as $category ) {
				$score = $lighthouse['categories'][ $category ]['score'] ?? null;
				$result['scores'][ $category ] = ( is_int( $score ) || is_float( $score ) ) && $score >= 0 && $score <= 1
					? (int) round( $score * 100 ) : null;
			}
			if ( ! count( array_filter( $result['scores'], 'is_int' ) ) ) {
				return new WP_Error( 'invalid_response', __( 'PageSpeed returned no usable scores. Retry the check.', 'noobrocket' ) );
			}
			foreach ( [ 'fcp' => 'first-contentful-paint', 'lcp' => 'largest-contentful-paint', 'tbt' => 'total-blocking-time', 'cls' => 'cumulative-layout-shift', 'speed_index' => 'speed-index' ] as $name => $audit ) {
				$value = $lighthouse['audits'][ $audit ]['numericValue'] ?? null;
				$result['metrics'][ $name ] = ( is_int( $value ) || is_float( $value ) ) && is_finite( (float) $value ) && $value >= 0 ? $value : null;
			}
			$results = get_option( 'noobrocket_pagespeed_results', [] );
			$results = is_array( $results ) ? $results : [];
			$results[ $strategy ] = $result;
			if ( ! update_option( 'noobrocket_pagespeed_results', $results, false ) && $results !== get_option( 'noobrocket_pagespeed_results', [] ) ) {
				return new WP_Error( 'storage', __( 'PageSpeed finished, but its result could not be saved. Check database access and retry.', 'noobrocket' ) );
			}
			return $result;
		} finally {
			$this->release_lock( $started );
		}
	}

	/** Compare ownership so a stale request cannot release a newer check's lock. */
	private function release_lock( int $started ): void {
		global $wpdb;
		$deleted = $wpdb->delete( $wpdb->options, [ 'option_name' => 'noobrocket_pagespeed_lock', 'option_value' => (string) $started ] );
		if ( $deleted ) {
			wp_cache_delete( 'noobrocket_pagespeed_lock', 'options' );
		}
	}

	private function api_error( int $status, array $payload ): WP_Error {
		$reasons = [];
		foreach ( (array) ( $payload['error']['details'] ?? [] ) as $detail ) {
			if ( is_array( $detail ) && is_string( $detail['reason'] ?? null ) ) {
				$reasons[] = $detail['reason'];
			}
		}
		if ( 429 === $status || array_intersect( [ 'RATE_LIMIT_EXCEEDED', 'QUOTA_EXCEEDED' ], $reasons ) ) {
			return new WP_Error( 'quota', __( 'Your PageSpeed API quota is exhausted. Wait and retry, or check quotas in Google Cloud.', 'noobrocket' ) );
		}
		if ( in_array( 'SERVICE_DISABLED', $reasons, true ) ) {
			return new WP_Error( 'api_disabled', __( 'Enable the PageSpeed Insights API in the Google Cloud project that owns this key.', 'noobrocket' ) );
		}
		if ( in_array( 'API_KEY_INVALID', $reasons, true ) ) {
			return new WP_Error( 'invalid_key', __( 'Google rejected this API key. Check the key and save a replacement.', 'noobrocket' ) );
		}
		if ( array_intersect( [ 'API_KEY_SERVICE_BLOCKED', 'API_KEY_HTTP_REFERRER_BLOCKED', 'API_KEY_IP_ADDRESS_BLOCKED' ], $reasons ) ) {
			return new WP_Error( 'key_restricted', __( 'This key blocks server-side PageSpeed requests. Allow the PageSpeed Insights API and your server IP in Google Cloud.', 'noobrocket' ) );
		}
		return new WP_Error( 'api_error', __( 'Google could not complete this check. Verify API access and homepage availability, then retry.', 'noobrocket' ) );
	}
}
