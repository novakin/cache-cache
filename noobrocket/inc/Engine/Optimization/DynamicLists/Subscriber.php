<?php
declare(strict_types=1);

namespace NoobRocket\Engine\Optimization\DynamicLists;

use NoobRocket\Event_Management\Subscriber_Interface;

class Subscriber implements Subscriber_Interface {
	/**
	 * DynamicLists instance
	 *
	 * @var DynamicLists
	 */
	private $dynamic_lists;

	/**
	 * Instantiate the class
	 *
	 * @param DynamicLists $dynamic_lists DynamicLists instance.
	 */
	public function __construct( DynamicLists $dynamic_lists ) {
		$this->dynamic_lists = $dynamic_lists;
	}

	/**
	 * Events this subscriber listens to.
	 *
	 * @return array
	 */
	public static function get_subscribed_events() {
		return [
			'noobrocket_deactivation'                          => [ 'clear_schedule_lists_update', 10, 2 ],
			'noobrocket_cache_ignored_parameters'              => 'add_cache_ignored_parameters',
			'noobrocket_minify_excluded_external_js'           => 'add_minify_excluded_external_js',
			'noobrocket_move_after_combine_js'                 => 'add_move_after_combine_js',
			'noobrocket_excluded_inline_js_content'            => 'add_combine_js_excluded_inline',
			'noobrocket_preload_exclude_urls'                  => 'add_preload_exclusions',
			'noobrocket_exclude_js'                            => 'add_js_exclude_files',
			'noobrocket_plugins_to_deactivate'                 => 'add_incompatible_plugins_to_deactivate',
			'noobrocket_staging_list'                          => 'add_staging_exclusions',
			'noobrocket_lrc_exclusions'                        => 'add_lrc_exclusions',
			'noobrocket_mixpanel_tracked_options'              => 'add_mixpanel_tracked_options',
			'noobrocket_upgrade'                            => 'update_lists_from_files',
			'update_option_noobrocket_settings'                => [ 'refresh_lists_for_release', 10, 2 ],
			'noobrocket_before_rollback'                       => 'maybe_update_lists',
			'noobrocket_exclude_locally_host_fonts'            => 'add_media_fonts_exclusions',
			'noobrocket_insights_add_homepage_expiry_interval' => 'add_noobrocket_insights_add_homepage_expiry_interval',
		];
	}

	/**
	 * Registers the REST dynamic lists update route
	 *
	 * @return void
	 */
	public function register_rest_route() {
		$this->dynamic_lists->register_rest_route();
	}

	/**
	 * Add REST data to our localize script data.
	 *
	 * @param array $data Localize script data.
	 * @return array
	 */
	public function add_dynamic_lists_script( $data ) {
		$data['rest_url']   = rest_url( 'noobrocket/v1/dynamic_lists/update/?_locale=user' );
		$data['rest_nonce'] = wp_create_nonce( 'wp_rest' );

		return $data;
	}

	/**
	 * Scheduling the dynamic lists update cron event.
	 */
	public function schedule_lists_update() {
		$this->dynamic_lists->schedule_lists_update();
	}

	/**
	 * Clear the dynamic lists update cron event.
	 *
	 *  @return void
	 */
	public function clear_schedule_lists_update( $sites_number = 0, bool $network_wide = false ) {
		\NoobRocket\Engine\Deactivation\Deactivation::for_each_deactivated_site(
			[ $this->dynamic_lists, 'clear_schedule_lists_update' ],
			$network_wide
		);
	}

	/**
	 * Update dynamic lists from API.
	 *
	 * * @return void
	 */
	public function update_lists() {
		$this->dynamic_lists->update_lists_from_remote();
	}

	/**
	 * Displays the dynamic lists update section on tools tab
	 *
	 * @return void
	 */
	public function display_update_lists_section() {
		$this->dynamic_lists->display_update_lists_section();
	}

	/**
	 * Add the cached ignored parameters to the array
	 *
	 * @param array $params Array of ignored parameters.
	 *
	 * @return array
	 */
	public function add_cache_ignored_parameters( array $params = [] ): array {
		return array_merge( $params, $this->dynamic_lists->get_cache_ignored_parameters() );
	}

	/**
	 * Add the excluded external JS patterns to the array
	 *
	 * @param array $excluded Array of excluded patterns.
	 *
	 * @return array
	 */
	public function add_minify_excluded_external_js( array $excluded = [] ): array {
		return array_merge( $excluded, $this->dynamic_lists->get_js_minify_excluded_external() );
	}

	/**
	 * Add the JS patterns to move after the combine JS file to the array
	 *
	 * @param array $excluded Array of patterns to move.
	 *
	 * @return array
	 */
	public function add_move_after_combine_js( array $excluded = [] ): array {
		return array_merge( $excluded, $this->dynamic_lists->get_js_move_after_combine() );
	}

	/**
	 * Add the excluded inline JS patterns to the array
	 *
	 * @param array $excluded Array of excluded patterns.
	 *
	 * @return array
	 */
	public function add_combine_js_excluded_inline( array $excluded = [] ): array {

		return array_merge( $excluded, $this->dynamic_lists->get_combine_js_excluded_inline() );
	}

	/**
	 * Add the preload exclusions to the array
	 *
	 * @param array $excluded Array of ignored URL regex.
	 *
	 * @return array
	 */
	public function add_preload_exclusions( array $excluded = [] ): array {
		return array_merge( $excluded, $this->dynamic_lists->get_preload_exclusions() );
	}

	/**
	 * Add the js files exclusions to the array
	 *
	 * @param array $js_files Array of files.
	 *
	 * @return array
	 */
	public function add_js_exclude_files( array $js_files = [] ): array {
		return array_merge( $js_files, $this->dynamic_lists->get_js_exclude_files() );
	}

	/**
	 * Add incompatible plugins to the array
	 *
	 * @param array $plugins Array of $plugins.
	 *
	 * @return array
	 */
	public function add_incompatible_plugins_to_deactivate( $plugins = [] ): array {
		return array_merge( (array) $plugins, $this->dynamic_lists->get_incompatible_plugins() );
	}

	/**
	 * Add the staging exclusions to the array
	 *
	 * @param array $stagings Array of staging urls.
	 *
	 * @return array
	 */
	public function add_staging_exclusions( $stagings = [] ): array {
		return array_merge( (array) $stagings, (array) $this->dynamic_lists->get_stagings() );
	}

	/**
	 * Add the LRC exclusions to the array
	 *
	 * @param array $exclusions Array of LRC exclusions.
	 *
	 * @return array
	 */
	public function add_lrc_exclusions( $exclusions ): array {
		return array_merge( (array) $exclusions, $this->dynamic_lists->get_lrc_exclusions() );
	}

	/**
	 * Update dynamic lists from JSON files.
	 *
	 * @return void
	 */
	public function update_lists_from_files() {
		$this->dynamic_lists->update_lists_from_files();
	}

	/**
	 * Refresh bundled compatibility lists when the product release changes.
	 *
	 * @param mixed $previous_options Previously saved settings.
	 * @param array $options Updated settings.
	 * @return void
	 */
	public function refresh_lists_for_release( $previous_options, $options ) {
		if ( ( $previous_options['version'] ?? '' ) === ( $options['version'] ?? '' ) ) {
			return;
		}

		$this->dynamic_lists->update_lists_from_files();
	}

	/**
	 * Update dynamic lists during rollback to versions < 3.18.
	 *
	 * @return void
	 */
	public function maybe_update_lists(): void {
		if ( version_compare( noobrocket_get_constant( 'NOOBROCKET_LASTVERSION' ), '3.18', '>=' ) ) {
			return;
		}

		$this->dynamic_lists->update_lists_from_remote();
	}

	/**
	 * Add the media fonts exclusion to the array
	 *
	 * @param array $exclusions Array of Media fonts exclusions.
	 *
	 * @return array
	 */
	public function add_media_fonts_exclusions( array $exclusions ): array {
		return array_merge( (array) $exclusions, $this->dynamic_lists->get_exclude_media_fonts() );
	}

	/**
	 * Add the MixPanel tracked options to the array
	 *
	 * @param array $options Array of tracked options.
	 *
	 * @return array
	 */
	public function add_mixpanel_tracked_options( array $options ): array {
		return array_unique( array_merge( (array) $options, $this->dynamic_lists->get_mixpanel_tracked_options() ) );
	}

	/**
	 * Add the Performance monitoring auto-add homepage expiry interval.
	 *
	 * @since 3.20.3
	 *
	 * @param int $interval Number of days before license expiry.
	 * @return int
	 */
	public function add_noobrocket_insights_add_homepage_expiry_interval( int $interval ): int {
		return $this->dynamic_lists->get_noobrocket_insights_add_homepage_expiry_interval();
	}
}
