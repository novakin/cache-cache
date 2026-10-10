<?php
declare(strict_types=1);

namespace NoobRocket\Engine\Admin\PerformanceMonitoring\Context;

use NoobRocket\Admin\Options_Data;
use NoobRocket\Engine\Common\Context\ContextInterface;
use NoobRocket\Engine\License\API\User;
use NoobRocket\Engine\Admin\PerformanceMonitoring\Database\Queries\PerformanceMonitoring as RIQuery;
use NoobRocket\Engine\License\API\RemoteSettings;

/**
 * Performance monitoring Context
 *
 * Provides context for Performance monitoring operations
 */
class Context implements ContextInterface {
	/**
	 * Plugin options instance.
	 *
	 * @var Options_Data
	 */
	private $options;

	/**
	 * User client API instance.
	 *
	 * @var User
	 */
	private $user;

	/**
	 * Performance monitoring query instance.
	 *
	 * @var RIQuery
	 */
	private $ri_query;

	/**
	 * Remote settings instance.
	 *
	 * @var RemoteSettings
	 */
	private $remote_settings;

	/**
	 * Constructor.
	 *
	 * @param Options_Data   $options Options instance.
	 * @param User           $user User client API instance.
	 * @param RIQuery        $ri_query    Performance monitoring query instance.
	 * @param RemoteSettings $remote_settings Remote settings instance.
	 */
	public function __construct( Options_Data $options, User $user, RIQuery $ri_query, RemoteSettings $remote_settings ) {
		$this->options         = $options;
		$this->user            = $user;
		$this->ri_query        = $ri_query;
		$this->remote_settings = $remote_settings;
	}

	/**
	 * Check if Performance monitoring is enabled.
	 *
	 * @param array $data Context data.
	 * @return bool
	 */
	public function is_allowed( array $data = [] ): bool {
		$enabled = current_user_can( 'noobrocket_manage_options' ) || wp_doing_cron();

		/**
		 * Filters Performance monitoring addon enable status.
		 *
		 * @param boolean $enabled Current status, default is true.
		 */
		$enabled = wpm_apply_filters_typed( 'boolean', 'noobrocket_noobrocket_insights_enabled', $enabled );

		// Block for non-live installations.
		if ( $enabled && ! noobrocket_is_live_site() ) {
			return false;
		}

		return $enabled;
	}

	/**
	 * Check if the current user is on the free plan or not.
	 *
	 * @return bool
	 */
	public function is_free_user(): bool {
		return $this->user->is_noobrocket_insights_free_active( $this->user->get_noobrocket_insights_addon_sku_active() );
	}

	/**
	 * Determines if scheduling for Performance monitoring is allowed.
	 *
	 * @return bool True if Performance monitoring is enabled, false otherwise.
	 */
	public function is_schedule_allowed(): bool {
		return (bool) $this->options->get( 'performance_monitoring', 0 );
	}

	/**
	 * Check if current installation is a non-live site.
	 *
	 * This will block Performance monitoring functionality for localhost installations.
	 *
	 * @since 3.20
	 *
	 * @return bool True if is non-live installation, false otherwise.
	 */
	public function is_reseller_or_non_live(): bool {
		// Hide for non-live installations.
		if ( ! noobrocket_is_live_site() ) {
			return true;
		}

		return false;
	}

	/**
	 * Checks if adding a new page is allowed based on user license and current URL count.
	 *
	 * @return bool True if adding a page is allowed, false otherwise.
	 */
	public function is_adding_page_allowed(): bool {
		$current_url_count = $this->ri_query->get_total_count();
		$max_urls          = $this->user->get_noobrocket_insights_addon_limit( $this->user->get_noobrocket_insights_addon_sku_active() );
		return $current_url_count < $max_urls;
	}

	/**
	 * Checks if remote setting is enabled.
	 *
	 * @return bool True if remote setting is enabled, false otherwise.
	 */
	public function is_remote_setting_enabled(): bool {
		return $this->remote_settings->is_noobrocket_insights_remote_setting_enabled();
	}
}
