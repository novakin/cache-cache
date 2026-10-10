<?php

namespace NoobRocket\Engine\License\API;

class User {
	/**
	 * The user object
	 *
	 * @var object
	 */
	private $user;

	/**
	 * Instantiate the class
	 *
	 * @param object|false $user The user object.
	 */
	public function __construct( $user ) {
		$this->user = is_object( $user ) ? $user : new \stdClass();
	}

	/**
	 * Set the user object.
	 *
	 * @param object $user The user object.
	 *
	 * @return void
	 */
	public function set_user( $user ) {
		$this->user = $user;
	}

	/**
	 * Gets the user license type
	 *
	 * @return int
	 */
	public function get_license_type() {
		if ( ! isset( $this->user->licence_account ) ) {
			return 0;
		}

		return (int) $this->user->licence_account;
	}

	/**
	 * Gets the user license expiration timestamp
	 *
	 * @return int
	 */
	public function get_license_expiration() {
		if ( ! isset( $this->user->licence_expiration ) ) {
			return 0;
		}

		return (int) $this->user->licence_expiration;
	}

	/**
	 * Checks if the user license is expired
	 *
	 * @return boolean
	 */
	public function is_license_expired() {
		$expiration = $this->get_license_expiration();
		return 0 !== $expiration && time() > $expiration;
	}

	/**
	 * Gets the user license creation date
	 *
	 * @return int
	 */
	public function get_creation_date() {
		if ( ! isset( $this->user->date_created ) ) {
			return time();
		}

		return (int) $this->user->date_created > 0
			? (int) $this->user->date_created
			: time();
	}

	/**
	 * Checks if user has auto-renew enabled
	 *
	 * @return boolean
	 */
	public function is_auto_renew() {
		if ( ! isset( $this->user->has_auto_renew ) ) {
			return false;
		}

		return (bool) $this->user->has_auto_renew;
	}

	/**
	 * Gets the upgrade to plus URL
	 *
	 * @return string
	 */
	public function get_upgrade_plus_url() {
		if ( ! isset( $this->user->upgrade_plus_url ) ) {
			return '';
		}

		return $this->user->upgrade_plus_url;
	}

	/**
	 * Gets the upgrade to infinite url
	 *
	 * @return string
	 */
	public function get_upgrade_infinite_url() {
		if ( ! isset( $this->user->upgrade_infinite_url ) ) {
			return '';
		}

		return $this->user->upgrade_infinite_url;
	}

	/**
	 * Gets the renewal url
	 *
	 * @return string
	 */
	public function get_renewal_url() {
		if ( ! isset( $this->user->renewal_url ) ) {
			return '';
		}

		return $this->user->renewal_url;
	}

	/**
	 * Checks if the user license has expired for more than 15 days
	 *
	 * @return boolean
	 */
	public function is_license_expired_grace_period() {
		if ( $this->is_license_expired() && ( time() - $this->get_license_expiration() > 15 * 24 * 60 * 60 ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get available upgrades from the API.
	 *
	 * @return array
	 */
	public function get_available_upgrades() {
		if ( empty( $this->user->licence->prices->upgrades ) ) {
			return [];
		}
		return (array) $this->user->licence->prices->upgrades;
	}

	/**
	 * Gets the addon license expiration timestamp
	 *
	 * @since 3.20
	 *
	 * @return int
	 */
	public function get_noobrocket_insights_license_expiration() {
		if ( ! isset( $this->user->performance_monitoring->expiration ) ) {
			return 0;
		}

		return (int) $this->user->performance_monitoring->expiration;
	}

	/**
	 * Checks if the addon license is active
	 *
	 * @param string $sku The SKU of the addon.
	 *
	 * @since 3.20
	 *
	 * @return boolean
	 */
	public function is_noobrocket_insights_addon_active( string $sku ) {
		return false;
	}

	/**
	 * Checks if license is on free plan.
	 *
	 * @param string $sku The SKU of the addon.
	 *
	 * @since 3.20
	 *
	 * @return boolean
	 */
	public function is_noobrocket_insights_free_active( string $sku ) {
		return false;
	}

	/**
	 * Retrieves the active SKU for the Performance monitoring Addon.
	 *
	 * @since 3.20
	 *
	 * @return string
	 */
	public function get_noobrocket_insights_addon_sku_active(): string {
		if ( ! isset( $this->user->performance_monitoring ) || ! isset( $this->user->performance_monitoring->active_sku ) ) {
			return 'perf-monitor-free';
		}

		return (string) $this->user->performance_monitoring->active_sku;
	}

	/**
	 * Retrieves the Performance monitoring addon upgrade SKUs based on the provided SKU.
	 *
	 * @param string $sku The SKU for which to retrieve the upgrade data.
	 *
	 * @return array
	 */
	public function get_noobrocket_insights_addon_upgrade_skus( string $sku ) {
		$plan = $this->get_noobrocket_insights_data( $sku );
		if ( ! $plan || ! isset( $plan->upgrades ) ) {
			return [];
		}

		return $plan->upgrades;
	}

	/**
	 * Retrieves the button text for the Performance monitoring addon based on the provided SKU.
	 *
	 * @param string $sku The SKU used to fetch the Performance monitoring addon data.
	 *
	 * @return string
	 */
	public function get_noobrocket_insights_addon_btn_text( string $sku ) {
		$plan = $this->get_noobrocket_insights_data( $sku );
		if ( ! $plan ) {
			return '';
		}

		$label = $plan->button->label;

		// Translate known button labels.
		if ( 'Get Advanced' === $label ) {
			return __( 'Get Performance monitoring', 'noobrocket' );
		}

		if ( 'Your plan' === $label ) {
			return __( 'Your plan', 'noobrocket' );
		}

		return $label;
	}

	/**
	 * Retrieves the URL for the Performance monitoring add-on button associated with the specified SKU.
	 *
	 * @param string $sku The SKU identifier used to fetch.
	 *
	 * @return string
	 */
	public function get_noobrocket_insights_addon_btn_url( string $sku ) {
		$plan = $this->get_noobrocket_insights_data( $sku );

		if ( ! $plan ) {
			return '';
		}

		if ( ! isset( $plan->button->url ) || '' === $plan->button->url ) {
			return '';
		}

		$url = admin_url( 'options-general.php?page=' . NOOBROCKET_PLUGIN_SLUG . '&noobrocket_insights_upgrade=true#noobrocket_insights' );

		return add_query_arg( 'dashboard_url', rawurlencode( $url ), $plan->button->url );
	}

	/**
	 * Retrieves the limit for the Performance monitoring add-on based on the provided SKU.
	 *
	 * @param string $sku The SKU used to fetch the Performance monitoring addon data.
	 *
	 * @return int
	 */
	public function get_noobrocket_insights_addon_limit( string $sku ) {
		$plan = $this->get_noobrocket_insights_data( $sku );

		if ( ! $plan || ! isset( $plan->limit ) ) {
			return 0;
		}

		return (int) $plan->limit;
	}

	/**
	 * Retrieves the subtitle for the Performance monitoring add-on based on the provided SKU.
	 *
	 * @param string $sku The SKU used to fetch the Performance monitoring addon data.
	 *
	 * @return string
	 */
	public function get_noobrocket_insights_addon_subtitle( string $sku ) {
		$plan = $this->get_noobrocket_insights_data( $sku );

		if ( ! $plan || ! isset( $plan->subtitle ) ) {
			return '';
		}

		if ( 'See how your top pages perform and quickly spot and optimize what slows your site down.' === $plan->subtitle ) {
			return __( 'See how your top pages perform and quickly spot and optimize what slows your site down.', 'noobrocket' );
		}

		return $plan->subtitle;
	}

	/**
	 * Retrieves the billing for the PMA add-on based on the provided SKU.
	 *
	 * @param string $sku The SKU used to fetch the Performance monitoring addon data.
	 *
	 * @return string
	 */
	public function get_noobrocket_insights_addon_billing( string $sku ) {
		$plan = $this->get_noobrocket_insights_data( $sku );

		if ( ! $plan || ! isset( $plan->billing ) ) {
			return '';
		}

		if ( '* Billed monthly. You can cancel at any time, each month started is due.' === $plan->billing ) {
			return __( '* Billed monthly. You can cancel at any time, each month started is due.', 'noobrocket' );
		}

		return $plan->billing;
	}


	/**
	 * Retrieves the highlights for the Performance monitoring add-on based on the provided SKU.
	 *
	 * @param string $sku The SKU used to fetch the Performance monitoring addon data.
	 *
	 * @return array
	 */
	public function get_noobrocket_insights_addon_highlights( string $sku ) {
		$plan = $this->get_noobrocket_insights_data( $sku );

		if ( ! $plan || ! isset( $plan->highlights ) ) {
			return [];
		}

		$highlights = [];

		foreach ( $plan->highlights as $highlight ) {
			if ( 'Up to 10 pages tracked' === $highlight ) {
				// translators: %1$s opening strong tag, %2$s number of pages, %3$s closing strong tag.
				$highlights [] = sprintf( __( 'Up to %1$s%2$s pages%3$s tracked', 'noobrocket' ), '<strong>', '10', '</strong>' );
				continue;
			}

			if ( 'Automatic performance monitoring' === $highlight ) {
				// translators: %1$s opening strong tag, %2$s closing strong tag.
				$highlights [] = sprintf( __( 'Automatic %1$sperformance monitoring%2$s', 'noobrocket' ), '<strong>', '</strong>' );
				continue;
			}

			if ( 'Unlimited on-demand tests' === $highlight ) {
				// translators: %1$s opening strong tag, %2$s closing strong tag.
				$highlights [] = sprintf( __( 'Unlimited %1$son-demand tests%2$s', 'noobrocket' ), '<strong>', '</strong>' );
				continue;
			}

			if ( 'Full GTmetrix performance reports' === $highlight ) {
				// translators: %1$s opening strong tag, %2$s closing strong tag.
				$highlights [] = sprintf( __( 'Full %1$sGTmetrix performance reports%2$s', 'noobrocket' ), '<strong>', '</strong>' );
				continue;
			}

			$highlights [] = $highlight;
		}

		return $highlights;
	}

	/**
	 * Checks if the Performance monitoring add-on has a promo based on the provided SKU.
	 *
	 * @param string $sku The SKU used to fetch the Performance monitoring addon data.
	 *
	 * @return bool
	 */
	public function has_noobrocket_insights_addon_promo( string $sku ) {
		return $this->get_noobrocket_insights_addon_promo( $sku ) !== false;
	}

	/**
	 * Retrieves the price for the Performance monitoring add-on based on the provided SKU.
	 *
	 * @param string $sku The SKU used to fetch the Performance monitoring addon data.
	 *
	 * @return string
	 */
	public function get_noobrocket_insights_addon_price( string $sku ) {
		$data = $this->get_noobrocket_insights_data( $sku );

		if ( ! $data || ! isset( $data->price ) ) {
			return '';
		}

		return $data->price;
	}

	/**
	 * Retrieves the promo price for the Performance monitoring add-on based on the provided SKU.
	 *
	 * @param string $sku The SKU used to fetch the Performance monitoring addon data.
	 *
	 * @return string
	 */
	public function get_noobrocket_insights_addon_promo_price( string $sku ) {
		$promo = $this->get_noobrocket_insights_addon_promo( $sku );

		if ( ! $promo || ! isset( $promo->price ) ) {
			return '';
		}

		return $promo->price;
	}

	/**
	 * Retrieves the promo name for the Performance monitoring add-on based on the provided SKU.
	 *
	 * @param string $sku The SKU used to fetch the Performance monitoring addon data.
	 *
	 * @return string
	 */
	public function get_noobrocket_insights_addon_promo_name( string $sku ) {
		$promo = $this->get_noobrocket_insights_addon_promo( $sku );

		if ( ! $promo || ! isset( $promo->name ) ) {
			return '';
		}

		if ( 'Launch Offer' === $promo->name ) {
			return __( 'Launch Offer', 'noobrocket' );
		}

		return $promo->name;
	}

	/**
	 * Retrieves the promo billing for the Performance monitoring add-on based on the provided SKU.
	 *
	 * @param string $sku The SKU used to fetch the Performance monitoring addon data.
	 *
	 * @return string
	 */
	public function get_noobrocket_insights_addon_promo_billing( string $sku ) {
		$promo = $this->get_noobrocket_insights_addon_promo( $sku );
		if ( ! $promo || ! isset( $promo->billing ) ) {
			return '';
		}

		if ( 'Launch price valid for the first 12 months, after which standard pricing applies.' === $promo->billing ) {
			return __( 'Launch price valid for the first 12 months, after which standard pricing applies.', 'noobrocket' );
		}

		return $promo->billing;
	}

	/**
	 * Retrieves the promo data for the Performance monitoring add-on based on the provided SKU.
	 *
	 * @param string $sku The SKU used to fetch the Performance monitoring addon data.
	 *
	 * @return false|object
	 */
	protected function get_noobrocket_insights_addon_promo( string $sku ) {
		$plan = $this->get_noobrocket_insights_data( $sku );

		if ( ! $plan || ! isset( $plan->promo ) ) {
			return false;
		}

		if ( ! isset( $plan->promo->expires_at ) || ( (int) $plan->promo->expires_at ) < time() ) {
			return false;
		}

		return $plan->promo;
	}

	/**
	 * Checks if the user account is from a reseller license
	 *
	 * @since 3.20
	 *
	 * @return boolean
	 */
	public function is_reseller_account() {
		if ( ! isset( $this->user->is_reseller ) ) {
			return false;
		}

		return (bool) $this->user->is_reseller;
	}

	/**
	 * Checks if the customer is on a trial license (active, cancelled or expired, until converted to paid).
	 *
	 * @return bool
	 */
	public function is_trial_customer(): bool {
		return ( $this->user->is_trial_cancelled ?? false ) || ( $this->user->is_trial_customer ?? false );
	}

	/**
	 * Retrieves the Performance monitoring plan data associated with the specified SKU.
	 *
	 * @param string $sku The SKU identifier used to find the corresponding Performance monitoring plan.
	 *
	 * @return object|null
	 */
	protected function get_noobrocket_insights_data( string $sku ) {

		if ( ! isset( $this->user->performance_monitoring ) || ! isset( $this->user->performance_monitoring->plans ) ) {
			return null;
		}

		foreach ( $this->user->performance_monitoring->plans as $plan ) {
			if ( $plan->sku === $sku ) {
				return $plan;
			}
		}
		return null;
	}

	/**
	 * Checks if the current website is revoked or not.
	 *
	 * @return bool
	 */
	public function is_revoked() {
		return $this->user->licence->is_revoked ?? false;
	}

	/**
	 * Gets the ban reason if the website is revoked.
	 *
	 * @return string
	 */
	public function ban_reason() {
		return $this->user->licence->plugin_updates_ban_reason ?? '';
	}

	/**
	 * Checks if the current website is a reseller account whose license was specifically banned.
	 *
	 * This is the single source of truth for "reseller-banned" state, combining reseller
	 * scoping with the specific `BANNED_WEBSITE` ban reason so it is not confused with
	 * other revocation causes (non-payment, fraud, chargeback, etc.).
	 *
	 * @since 3.23.1
	 *
	 * @return bool
	 */
	public function is_reseller_license_banned(): bool {
		return $this->is_reseller_account()
			&& $this->is_revoked()
			&& 'BANNED_WEBSITE' === $this->ban_reason();
	}

	/**
	 * Checks if plugin updates are available.
	 *
	 * @return bool
	 */
	public function can_update_plugin() {
		// Check if website is revoked.
		if ( $this->is_revoked() ) {
			return false;
		}

		// Check if license is expired.
		if ( $this->is_license_expired() ) {
			return false;
		}

		return true;
	}

	/**
	 * Gets the reason why plugin updates are blocked.
	 *
	 * @return string Empty string if updates are available, otherwise the reason.
	 */
	public function get_update_blocked_reason() {
		$message = __( 'There was an error updating the plugin.', 'noobrocket' );

		// Check if website is revoked first.
		if ( $this->is_revoked() ) {
			$reason_text = $this->get_blocked_reason_text( $this->ban_reason() );
			if ( ! empty( $reason_text ) ) {
				return rtrim( $message, '.' ) . ' ' . sprintf(
					/* translators: %s: ban reason */
					__( 'because %s', 'noobrocket' ),
					$reason_text
				);
			}
			return $message;
		}

		// Check if license is expired.
		if ( $this->is_license_expired() ) {
			return $message;
		}

		return '';
	}

	/**
	 * Converts a blocked reason code to human-readable text.
	 *
	 * @param string $reason_code Reason code from the API.
	 * @return string Human-readable reason text, empty string if code is unknown.
	 */
	private function get_blocked_reason_text( $reason_code ) {
		$reasons = [
			'BANNED_WEBSITE' => __( 'your website is banned', 'noobrocket' ),
		];
		if ( empty( $reason_code ) || ! isset( $reasons[ $reason_code ] ) ) {
			return '';
		}
		return $reasons[ $reason_code ];
	}

	/**
	 * Get currency from user data.
	 *
	 * @return string
	 */
	public function get_currency() {
		return $this->user->currency ?? 'USD';
	}

	/**
	 * Gets the user noobrocketcdn free url.
	 *
	 * @return string
	 */
	public function get_noobrocketcdn_free_url() {
		if ( ! isset( $this->user->noobrocketcdn->cdn_free_url ) ) {
			return '';
		}

		return (string) $this->user->noobrocketcdn->cdn_free_url;
	}
}
