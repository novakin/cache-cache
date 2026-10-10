<?php
declare(strict_types=1);

namespace NoobRocket\Engine\CDN\Render;

use NoobRocket\Abstract_Render;
use NoobRocket\Engine\CDN\Cache;
use NoobRocket\Engine\CDN\Context;
use NoobRocket\Admin\Options;
use NoobRocket\Admin\Options_Data;
use NoobRocket\Engine\CDN\HostedCDN\SubscriptionController;
use NoobRocket\Engine\Common\Utils;
use NoobRocket\Engine\Admin\Beacon\Beacon;
use NoobRocket\Engine\CDN\HostedCDN\Database\Queries\HostedCDN as HostedCDNQuery;
use NoobRocket\Engine\License\API\User;
use NoobRocket\Engine\Tracking\TrackingTrait;

/**
 * Handles business logic for CDN driver sections, exclusion fields,
 * and rendering of CDN-specific UI components (tabs, status indicator, upsell).
 *
 * @since 3.22
 */
class Controller extends Abstract_Render {
	use TrackingTrait;

	/**
	 * Option name used to store forced pause tracking state.
	 *
	 * @var string
	 */
	private const FORCED_OFF_TRACKING_OPTION = 'noobrocket_noobrocketcdn_forced_pause_state';

	/**
	 * Beacon instance.
	 *
	 * @var Beacon
	 */
	private $beacon;

	/**
	 * CDN context instance.
	 *
	 * @var Context
	 */
	private $context;

	/**
	 * Options_Data instance.
	 *
	 * @var Options_Data
	 */
	private $options;

	/**
	 * Options API instance.
	 *
	 * @var Options
	 */
	private $options_api;

	/**
	 * HostedCDNQuery instance.
	 *
	 * @var HostedCDNQuery
	 */
	private $cdn_query;

	/**
	 * HostedCDN Subscription controller instance.
	 *
	 * @var SubscriptionController
	 */
	private $subscription_controller;

	/**
	 * Page count for HostedCDN Free tier.
	 *
	 * @var int
	 */
	private $page_count = 0;

	/**
	 * User instance
	 *
	 * @var User
	 */
	private $user;

	/**
	 * Cache instance
	 *
	 * @var Cache
	 */
	private $cache;

	/**
	 * Constructor.
	 *
	 * @param Beacon                 $beacon        Beacon instance.
	 * @param string                 $template_path Path to the view templates.
	 * @param Context                $context       Context instance.
	 * @param Options_Data           $options  Options_Data instance.
	 * @param Options                $options_api Options API instance.
	 * @param HostedCDNQuery         $cdn_query HostedCDNQuery instance.
	 * @param SubscriptionController $subscription_controller HostedCDN Subscription controller instance.
	 * @param User                   $user          User instance.
	 * @param Cache                  $cache Cache instance.
	 */
	public function __construct(
		Beacon $beacon,
		string $template_path,
		Context $context,
		Options_Data $options,
		Options $options_api,
		HostedCDNQuery $cdn_query,
		SubscriptionController $subscription_controller,
		User $user,
		Cache $cache
	) {
		parent::__construct( $template_path );

		$this->beacon                  = $beacon;
		$this->context                 = $context;
		$this->options                 = $options;
		$this->options_api             = $options_api;
		$this->cdn_query               = $cdn_query;
		$this->subscription_controller = $subscription_controller;
		$this->user                    = $user;
		$this->cache                   = $cache;
	}

	/**
	 * Adds the applied CDN state to the "Your CDN" (BYOCDN) section, when present.
	 *
	 * Also seeds `is_forced_off => false` as the default — the key hosting
	 * compatibility subscribers (e.g. {@see \NoobRocket\ThirdParty\Hostings\OneCom::disable_cdn_mode_toggle()})
	 * can override to `true` later in the same `noobrocket_cdn_driver_sections` filter chain.
	 *
	 * Also overrides the section's `status_indicator.is_active` (initially set from
	 * whether CNAMEs are configured, in {@see \NoobRocket\Engine\Admin\Settings\Page::cdn_section()})
	 * to instead match the mode toggle's own on/off state, so the "Your CDN is active
	 * on your website" message only shows on page load when the toggle is actually on -
	 * matching {@see get_byocdn_status_indicator_html()}, which drives the same message
	 * after an AJAX toggle.
	 *
	 * Also exposes `show_no_cname_warning`, true only when BYOCDN is the actually
	 * applied mode (not merely tab-selected) and no CNAME has been configured yet
	 * — the tab otherwise lets a user turn the driver on with nothing to rewrite.
	 *
	 * @since 3.23.3
	 *
	 * @param array $sections CDN driver sections.
	 *
	 * @return array
	 */
	public function add_applied_cdn_state_to_cdn_section( array $sections ): array {
		if ( ! isset( $sections['cdn_section'] ) ) {
			return $sections;
		}

		$applied_cdn_state = $this->context->get_applied_cdn_state();
		$is_active         = Context::BYOCDN_TYPE === $applied_cdn_state;

		$sections['cdn_section']['applied_cdn_state']     = $applied_cdn_state;
		$sections['cdn_section']['is_active']             = $is_active;
		$sections['cdn_section']['is_forced_off']         = false;
		$sections['cdn_section']['toggle_tooltip']        = $this->get_toggle_forced_off_tooltip();
		$sections['cdn_section']['show_no_cname_warning'] = $is_active
			&& empty( $this->options->get( 'cdn_cnames', [] ) );

		if ( isset( $sections['cdn_section']['status_indicator'] ) ) {
			$sections['cdn_section']['status_indicator']['is_active'] = $is_active;
		}

		return $sections;
	}

	/**
	 * Adds the HostedCDN Paid section to the CDN driver sections.
	 *
	 * @since 3.22
	 *
	 * @param array $sections CDN driver sections.
	 *
	 * @return array
	 */
	public function add_noobrocketcdn_paid_section( array $sections ): array {
		if ( ! $this->subscription_controller->is_paid() ) {
			return $sections;
		}

		$cdn_beacon = $this->beacon->get_suggest( 'noobrocketcdn' );

		$status_indicator_data           = $this->get_status_indicator_data( 1, $this->is_subscription_loading(), false );
		$status_indicator_data['class'] .= ' noobrocket-cdn-status-pronounced noobrocketcdn';

		$noobrocketcdn_state = $this->context->get_noobrocketcdn_state();
		$is_forced_off   = $this->should_reject_noobrocketcdn_activation();

		$sections['noobrocketcdn_paid_section'] = [
			'title'             => __( 'HostedCDN', 'noobrocket' ),
			'type'              => 'noobrocketcdn_paid',
			'class'             => [ 'noobrocketcdn' ],
			'page'              => 'page_cdn',
			'help'              => [
				'id'  => $cdn_beacon['id'],
				'url' => $cdn_beacon['url'],
			],
			'status_indicator'  => $status_indicator_data,
			'applied_cdn_state' => $this->context->get_applied_cdn_state(),
			'noobrocketcdn_state'   => $noobrocketcdn_state,
			'is_forced_off'     => $is_forced_off,
			'toggle_tooltip'    => $this->get_noobrocketcdn_toggle_forced_off_tooltip(),
			// Forced off means HostedCDN can't actually be running (expired/banned licence,
			// cancelled subscription) - is_forced_off() already stops CDN delivery on the
			// front end via maybe_turn_off_noobrocketcdn_for_inactive_subscription(), so the toggle should
			// show off rather than checked-but-disabled.
			'is_active'         => Context::HOSTEDCDN_PAID_TYPE === $noobrocketcdn_state && ! $is_forced_off,
		];

		return $sections;
	}

	/**
	 * Adds the HostedCDN Free section to the CDN driver sections.
	 *
	 * @since 3.22
	 *
	 * @param array $sections CDN driver sections.
	 *
	 * @return array
	 */
	public function add_noobrocketcdn_free_section( array $sections ): array {
		if ( $this->subscription_controller->is_paid() ) {
			return $sections;
		}

		$classes                 = [ 'noobrocketcdn' ];
		$is_subscription_loading = $this->is_subscription_loading();
		$this->page_count        = count( $this->get_items() );
		$cta_heading             = sprintf(
		// translators: %1$s = opening strong tag, %2$s = closing strong tag.
			__( '%1$sWant full-site Content Delivery coverage?%2$s Extend HostedCDN to all your pages with unlimited bandwidth.', 'noobrocket' ),
			'<strong>',
			'</strong>'
		);

		$cta_heading_max_limit = sprintf(
		// translators: %1$s = opening strong tag, %2$s = number of pages allowed, %3$s = closing strong tag.
			__( '%1$sNice work! You’re using HostedCDN on %2$s key pages!%3$s ', 'noobrocket' ),
			'<strong>',
			$this->context->get_free_page_limit(),
			'</strong>'
		);

		$cta_description = __( 'Upgrade to HostedCDN Pro to extend faster content delivery across all your pages from 100+ edge locations worldwide.', 'noobrocket' );

		$limit_reached = $this->page_count >= $this->context->get_free_page_limit();
		$is_forced_off = $this->should_reject_noobrocketcdn_activation();

		// Disable input field and buttons when 3 pages are added, a subscription is being created, or activation is forced off (e.g. an expired licence).
		// Deliberately not disabled merely for being paused (CDN toggled off) - add_page() supports
		// adding pages while paused (leaving the CDN off), so the UI must allow it too.
		if ( $limit_reached || $is_subscription_loading || $is_forced_off ) {
			$classes[] = 'noobrocket-cdn-built-in--disabled';
		}

		$cdn_beacon = $this->beacon->get_suggest( 'noobrocketcdn_free' );

		$noobrocketcdn_state = $this->context->get_noobrocketcdn_state();
		$is_white_label  = (bool) noobrocket_get_constant( 'NOOBROCKET_WHITE_LABEL_ACCOUNT' );

		$upgrade_url = '';
		if ( ! $is_white_label && ! $this->user->is_reseller_account() && noobrocket_is_live_site() ) {
			$upgrade_url = $this->subscription_controller->get_express_checkout_url();
		}

		$sections['noobrocketcdn_free_section'] = [
			'title'             => __( 'HostedCDN', 'noobrocket' ),
			'type'              => 'noobrocketcdn_free',
			'class'             => $classes,
			'page'              => 'page_cdn',
			'help'              => [
				'id'  => $cdn_beacon['id'],
				'url' => $cdn_beacon['url'],
			],
			'status_indicator'  => $this->get_status_indicator_data( $this->page_count, $is_subscription_loading ),
			'cta_data'          => [
				'cta_heading'           => $cta_heading,
				'cta_heading_max_limit' => $cta_heading_max_limit,
				'cta_description'       => $cta_description,
				'is_visible'            => $this->page_count > 0 && ! $this->user->is_reseller_account(),
				'is_expanded'           => $limit_reached,
				'limit_reached'         => $limit_reached,
			],
			'limit_reached'     => $limit_reached,
			'applied_cdn_state' => $this->context->get_applied_cdn_state(),
			'noobrocketcdn_state'   => $noobrocketcdn_state,
			'is_forced_off'     => $is_forced_off,
			'toggle_tooltip'    => $this->get_noobrocketcdn_toggle_forced_off_tooltip(),
			// Forced off means HostedCDN can't actually be running (expired/banned licence,
			// cancelled subscription) - is_forced_off() already stops CDN delivery on the
			// front end via maybe_turn_off_noobrocketcdn_for_inactive_subscription(), so the toggle should
			// show off rather than checked-but-disabled.
			//
			// HOSTEDCDN_STATE_ONGOING_FREE (a subscription still being created) counts as
			// active too - cdn/cdn_type/cdn_state are already persisted as on by the time
			// create_subscription() starts, so any render during the ~30s+ creation window
			// (a reload, another tab) must keep the toggle checked rather than showing it
			// as off just because get_noobrocketcdn_state() reports the ongoing marker instead
			// of the tier itself. $is_forced_off is true throughout that same window too
			// (should_reject_noobrocketcdn_activation() treats is_subscription_loading() as a
			// reason to reject/disable, correctly disabling the toggle here), so it can't
			// gate is_active() as-is without also masking this case - "loading" is the one
			// forced-off reason that means "checked and disabled", not "off and disabled".
			'is_active'         => in_array( $noobrocketcdn_state, [ Context::HOSTEDCDN_FREE_TYPE, Context::HOSTEDCDN_STATE_ONGOING_FREE ], true ) && ( ! $is_forced_off || $is_subscription_loading ),
			'upgrade_url'       => $upgrade_url,
		];

		return $sections;
	}

	/**
	 * Adds the Purge CDN Cache section to the CDN driver sections.
	 *
	 * @since 3.22
	 *
	 * @param array $sections CDN driver sections.
	 *
	 * @return array
	 */
	public function add_purge_cdn_cache_section( array $sections ): array {
		$cdn_beacon = $this->beacon->get_suggest( 'purge_cdn' );

		if ( ! empty( $_SERVER['REQUEST_URI'] ) ) {
			$referer_url = filter_var( wp_unslash( $_SERVER['REQUEST_URI'] ), FILTER_SANITIZE_URL );
		}
		$classes = [ 'noobrocketcdn', 'noobrocketcdn-shared-section' ];

		$sections['purge_cdn_cache_section'] = [
			// translators: %s is the CDN driver, wrapped in a span for JS targeting.
			'title'       => sprintf( __( 'Purge %s Cache', 'noobrocket' ), '<span class="noobrocketcdn-driver-js">HostedCDN</span>' ),
			'type'        => 'purge_cdn_cache_section',
			'description' => sprintf(
			// translators: %s = CDN driver, wrapped in a span for JS targeting.
				__( 'Purges %s cached resources for your website.', 'noobrocket' ),
				'<span class="noobrocketcdn-driver-js">HostedCDN</span>'
			),
			'purge_url'   => Utils::get_nonce_post_url( 'noobrocket_purge_noobrocketcdn' ),
			'page'        => 'page_cdn',
			'help'        => [
				'id'  => $cdn_beacon['id'],
				'url' => $cdn_beacon['url'],
			],
			'class'       => $classes,
		];

		if ( $this->context->is_noobrocketcdn() && $this->should_disable_element_for_noobrocketcdn() ) {
			$sections['purge_cdn_cache_section']['class'][] = 'noobrocket-cdn-disabled';
		}

		return $sections;
	}

	/**
	 * Adds the Exclude CDN section to the CDN driver sections.
	 *
	 * @since 3.22
	 *
	 * @param array $sections CDN driver sections.
	 *
	 * @return array
	 */
	public function add_exclude_cdn_section( array $sections ): array {
		$cdn_exclude_beacon       = $this->beacon->get_suggest( 'exclude_cdn' );
		$noobrocketcdn_exclude_beacon = $this->beacon->get_suggest( 'exclude_cdn_noobrocketcdn' );

		$is_noobrocketcdn = $this->context->is_noobrocketcdn();
		$initial_url  = $is_noobrocketcdn ? $noobrocketcdn_exclude_beacon['url'] : $cdn_exclude_beacon['url'];
		$initial_id   = $is_noobrocketcdn ? $noobrocketcdn_exclude_beacon['id'] : $cdn_exclude_beacon['id'];

		$sections['exclude_cdn_section'] = [
			// translators: %s is the CDN driver, wrapped in a span for JS targeting.
			'title' => sprintf( __( 'Manage %s Exclusions', 'noobrocket' ), '<span class="noobrocketcdn-driver-js">HostedCDN</span>' ),
			'type'  => 'nocontainer_with_title',
			'help'  => [
				'id'            => $initial_id,
				'url'           => $initial_url,
				'noobrocketcdn_url' => $noobrocketcdn_exclude_beacon['url'],
				'noobrocketcdn_id'  => $noobrocketcdn_exclude_beacon['id'],
				'other_cdn_url' => $cdn_exclude_beacon['url'],
				'other_cdn_id'  => $cdn_exclude_beacon['id'],
			],
			'page'  => 'page_cdn',
			'class' => [ 'cdn-shared-section' ],
		];

		if ( $this->context->is_noobrocketcdn() && $this->should_disable_element_for_noobrocketcdn() ) {
			$sections['exclude_cdn_section']['class'][] = 'noobrocket-cdn-disabled';
		}

		return $sections;
	}

	/**
	 * Adds exclusion fields for CDN to the settings fields array.
	 *
	 * @since 3.22
	 *
	 * @param array $fields Existing settings fields array.
	 *
	 * @return array Modified fields array with CDN exclusion fields appended.
	 */
	public function add_exclusions_fields( array $fields ): array {
		$exclusion_fields = [];

		if ( Context::HOSTEDCDN_PAID_TYPE === $this->context->get_driver() ) {
			$exclusion_fields['cdn_reject_pages'] = [
				'type'              => 'textarea_with_container',
				'label'             => __( 'Exclude Pages from CDN', 'noobrocket' ),
				'description'       => __( 'Specify URL(s) of pages that should not get served via CDN (one per line).', 'noobrocket' ),
				'helper'            => __( 'Use (.*) wildcards to exclude all files of a given file type located at a specific path.', 'noobrocket' ),
				'placeholder'       => '/path/to/page',
				'section'           => 'exclude_cdn_section',
				'page'              => 'page_cdn',
				'default'           => [],
				'class'             => [
					'noobrocket-cdn-exclusions',
					'noobrocketcdn',
					'noobrocketcdn-shared-section',
				],
				'sanitize_callback' => 'sanitize_textarea',
			];
		}

		$exclusion_fields['cdn_reject_files'] = [
			'type'              => 'textarea_with_container',
			'label'             => __( 'Exclude Files from CDN', 'noobrocket' ),
			'description'       => __( 'Specify URL(s) of files that should not get served via CDN (one per line).', 'noobrocket' ),
			'helper'            => __( 'The domain part of the URL will be stripped automatically.<br>Use (.*) wildcards to exclude all files of a given file type located at a specific path.', 'noobrocket' ),
			'placeholder'       => '/wp-content/plugins/some-plugins/(.*).css',
			'section'           => 'exclude_cdn_section',
			'page'              => 'page_cdn',
			'default'           => [],
			'class'             => [ 'cdn-shared-section', 'noobrocket-cdn-exclusions' ],
			'sanitize_callback' => 'sanitize_textarea',
		];

		// Disable exclusions fields when subscription is processing.
		foreach ( array_keys( $exclusion_fields ) as $field ) {
			if ( $this->context->is_noobrocketcdn() && $this->should_disable_element_for_noobrocketcdn() ) {
				$exclusion_fields[ $field ]['class'][]    = 'noobrocket-cdn-disabled';
				$exclusion_fields[ $field ]['attributes'] = [
					'disabled' => 'disabled',
				];
			}
		}

		return array_merge( $fields, $exclusion_fields );
	}

	/**
	 * Renders the built-in CDN page list table.
	 *
	 * Builds row data and delegates to the table-list generic partial.
	 *
	 * @since 3.22
	 *
	 * @return void
	 */
	public function render_built_in_page_list(): void {
		if ( 0 === $this->page_count ) {
			return;
		}

		echo $this->get_built_in_page_list(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view.
	}

	/**
	 * Gets the status indicator HTML for the current HostedCDN tier.
	 *
	 * Used to refresh the indicator after a REST action (mode toggle, add/delete page),
	 * so it must reflect whichever tier the site is actually on - not just the free tier,
	 * since the mode-toggle endpoint is shared by both.
	 *
	 * @param int $pages_count            Number of pages currently using HostedCDN.
	 *
	 * @return string The rendered status indicator HTML.
	 */
	public function get_status_indicator_html( int $pages_count ): string {
		$is_paid = $this->subscription_controller->is_paid();
		$data    = $this->get_status_indicator_data( $pages_count, $this->is_subscription_loading(), ! $is_paid );

		if ( $data['no_status_indicator'] ) {
			return '';
		}

		if ( $is_paid ) {
			// Mirrors the modifier classes add_noobrocketcdn_paid_section() adds, so a
			// REST-refreshed indicator keeps the same "boxed" paid-tier styling.
			$data['class'] .= ' noobrocket-cdn-status-pronounced noobrocketcdn';
		}

		return $this->render_parts_with_data( 'cdn/cdn-status-indicator', $data, true );
	}

	/**
	 * Gets the status indicator HTML for the "Other CDN" (BYOCDN) section.
	 *
	 * Unlike HostedCDN's indicator, BYOCDN only has two states: active - showing
	 * the same "Your CDN is active on your website" message displayed on page
	 * load - or inactive, showing no status message at all. There is no
	 * paused/loading/tiered text to account for, so this deliberately doesn't
	 * reuse {@see get_status_indicator_data()}, which is HostedCDN-specific.
	 *
	 * @param bool $is_active Whether BYOCDN is the currently applied CDN mode.
	 *
	 * @return string The rendered status indicator HTML, or an empty string when inactive.
	 */
	public function get_byocdn_status_indicator_html( bool $is_active ): string {
		$data = [
			'is_active'   => $is_active,
			'status_text' => __( 'Your CDN is active on your website', 'noobrocket' ),
			'class'       => '',
		];

		return $this->render_parts_with_data( 'cdn/cdn-status-indicator', $data, true );
	}

	/**
	 * Gets the built-in page list for the CDN settings.
	 *
	 * Generates a partial table list view using the 'noobrocket_cdn_free_page_rows'
	 * hook to populate the rows of the table.
	 *
	 * @return string The rendered HTML string of the built-in page list table.
	 */
	public function get_built_in_page_list(): string {
		$table_data = [
			'rows_hook' => 'noobrocket_cdn_free_page_rows',
		];

		return $this->generate( 'partials/table-list', $table_data );
	}

	/**
	 * Renders the built-in CDN page list rows.
	 *
	 * Builds each row and renders them via the table-list-row generic partial.
	 *
	 * @since 3.22
	 *
	 * @return void
	 */
	public function render_built_in_page_rows(): void {
		$pages = $this->get_items();

		foreach ( $pages as $page ) {
			echo $this->generate( 'partials/table-list-row', $this->build_page_row( $page ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view.
		}
	}

	/**
	 * Renders the HostedCDN CTA banner.
	 *
	 * @param bool $display Whether to display the CTA. Default true.
	 *
	 * @return bool
	 * @since 3.22
	 */
	public function maybe_display_noobrocketcdn_cta( bool $display = true ): bool {
		if ( ! $display ) {
			return false;
		}

		if ( $this->is_subscription_loading() ) {
			return false;
		}

		if ( $this->user->is_reseller_account() ) {
			return false;
		}

		return true;
	}

	/**
	 * Removes the "NEW" badge from the Content Delivery tab for existing paid HostedCDN subscribers.
	 *
	 * Paid users already know HostedCDN — the badge is only relevant for users
	 * discovering the service for the first time via the free tier.
	 *
	 * @since 3.22
	 *
	 * @param string $badge Current badge label.
	 *
	 * @return string Empty string for paid subscribers, original badge otherwise.
	 */
	public function maybe_hide_cdn_tab_badge( string $badge ): string {
		if ( $this->subscription_controller->is_paid() ) {
			return '';
		}

		return $badge;
	}

	/**
	 * Synchronizes forced pause state and emits tracking events on transitions.
	 *
	 * @param \WP_Screen $screen Current admin screen.
	 *
	 * @return void
	 */
	public function maybe_sync_forced_off_tracking_state( \WP_Screen $screen ): void {
		if ( 'settings_page_noobrocket' !== $screen->id || ! current_user_can( 'noobrocket_manage_options' ) ) {
			return;
		}
		$is_forced  = $this->context->is_forced_off();
		$stored     = $this->get_forced_off_tracking();
		$was_forced = (bool) ( $stored['tracking'] ?? false );

		// Bail out when state hasn't changed to avoid unnecessary option updates and tracking events.
		if ( $is_forced === $was_forced ) {
			return;
		}

		$stored['tracking'] = $is_forced;
		update_option( self::FORCED_OFF_TRACKING_OPTION, $stored, false );

		// Clear whole cache.
		$this->cache->clear_all_cache();

		$settings = $this->options_api->get( 'settings', [] );

		// Resume leg: the forced-off condition has just cleared.
		if ( ! $is_forced ) {
			$cdn_mode = $this->context->get_cdn_state( $settings['cdn_state'] );

			// Bail out and don't track when BYOCDN is the applied mode.
			if ( Context::BYOCDN_TYPE === $cdn_mode ) {
				return;
			}

			// Bail out and don't track when mode automatcially switches to nothing after cancellation.
			if ( Context::CDN_STATE_NOTHING === $cdn_mode ) {
				return;
			}

			// An active paid subscription means it was renewed/reactivated;
			// otherwise the NoobRocket license itself must be what got fixed.
			$trigger = $this->subscription_controller->is_paid() ? 'pro_purchase' : 'license_renewal';

			$this->track_event(
				'HostedCDN Mode Changed',
				[
					'cdn_mode'   => $cdn_mode,
					'cdn_status' => $this->context->get_cdn_status(),
					'trigger'    => $trigger,
				]
			);

			return;
		}

		$pre_expiry_mode = $this->context->get_cdn_state( $settings['cdn_state'] );

		$this->track_event(
			'HostedCDN Forced Off',
			[
				'reason'          => $this->context->get_forced_off_reason(),
				'pre_expiry_mode' => $pre_expiry_mode,
			]
		);
	}

	/**
	 * Renders the CDN driver tabs.
	 *
	 * @since 3.22
	 *
	 * @return void
	 */
	public function render_cdn_driver_tabs(): void {

		$driver            = $this->context->get_driver();
		$applied_cdn_state = $this->context->get_applied_cdn_state();
		$cdn_type          = $this->options->get( 'cdn_type', Context::HOSTEDCDN_TYPE );

		// should_reject_noobrocketcdn_activation() only says whether HostedCDN itself can run -
		// it says nothing about which driver is actually selected, so it must only blank the
		// tab highlight when HostedCDN is that selected driver. Applying it unconditionally
		// would also clear the "Other CDN" tab's highlight for a site on BYOCDN that merely
		// has an unrelated, forced-off HostedCDN subscription.
		if ( Context::HOSTEDCDN_TYPE === $cdn_type && $this->should_reject_noobrocketcdn_activation() ) {
			$cdn_type = Context::CDN_STATE_NOTHING;
		}

		$data = [
			'cdn_type'         => $cdn_type,
			'display_tabs'     => ! $this->is_cdn_type_filtered(),
			'noobrocketcdn_mode'   => Context::HOSTEDCDN_PAID_TYPE === $driver ? 'HostedCDN Paid' : 'HostedCDN Free',
			'noobrocketcdn_active' => Context::HOSTEDCDN_TYPE === $applied_cdn_state,
			'byocdn_active'    => Context::BYOCDN_TYPE === $applied_cdn_state,
		];

		echo $this->generate( 'partials/cdn/cdn-driver-tabs', $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view.
	}

	/**
	 * Renders the expired NoobRocket license notice.
	 *
	 * @since 3.22
	 *
	 * @return void
	 */
	public function render_expired_nbr_licence_notice(): void {
		if ( ! $this->should_display_licence_expired_notice() ) {
			return;
		}

		$data = [
			'renewal_url' => $this->user->get_renewal_url(),
			'is_reseller' => $this->user->is_reseller_account(),
		];

		echo $this->generate( 'partials/cdn/noobrocket-licence-expired-notice', $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view.
	}

	/**
	 * Renders the reseller-banned notice.
	 *
	 * @since 3.23.1
	 *
	 * @return void
	 */
	public function render_reseller_banned_notice(): void {
		if ( ! $this->user->is_reseller_license_banned() ) {
			return;
		}

		echo $this->generate( 'partials/cdn/noobrocket-licence-banned-notice', [] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view.
	}

	/**
	 * Filter the cdn_state option to turn off CDN for users with inactive subscriptions.
	 *
	 * If the user has an inactive subscription, this will force the CDN option to be false,
	 * effectively stopping CDN functionality until they renew or reactivate their subscription.
	 *
	 * @since 3.22
	 *
	 * @param mixed $cdn Current value of the CDN option.
	 *
	 * @return mixed False if the user has an inactive subscription, original value otherwise.
	 */
	public function maybe_turn_off_noobrocketcdn_for_inactive_subscription( $cdn ) {
		// Bail early if not on HostedCDN driver to avoid unnecessary checks.
		if ( ! $this->context->is_noobrocketcdn() ) {
			return $cdn;
		}

		if ( $this->context->is_forced_off() ) {
			$stored = $this->get_forced_off_tracking();

			// Prevent unnecessary DB write on every request.
			if ( empty( $stored['persistent'] ) ) {
				$stored['persistent'] = true;
				update_option( self::FORCED_OFF_TRACKING_OPTION, $stored, false );

				// Clear whole cache.
				$this->cache->clear_all_cache();
			}

			return false;
		}

		return $cdn;
	}

	/**
	 * Determines whether the HostedCDN Free "add page" controls (homepage button,
	 * URL input, add button) should be disabled.
	 *
	 * Mirrors the condition {@see add_noobrocketcdn_free_section()} uses for the
	 * `noobrocket-cdn-built-in--disabled` class, so the REST mode-toggle response
	 * ({@see \NoobRocket\Engine\CDN\HostedCDN\Rest::save_cdn_mode()}) can keep the
	 * add-page controls in sync without a page reload. Deliberately excludes
	 * being merely paused (CDN toggled off) - add_page() supports adding pages
	 * while paused, so pausing alone must not disable these controls.
	 *
	 * @return bool True if the add-page controls should be disabled, false otherwise.
	 */
	public function should_disable_free_add_page(): bool {
		$page_count    = count( $this->get_items() );
		$limit_reached = $page_count >= $this->context->get_free_page_limit();

		return $limit_reached || $this->is_subscription_loading() || $this->should_reject_noobrocketcdn_activation();
	}

	/**
	 * Determines whether a UI element should be disabled for HostedCDN.
	 *
	 * Returns true only when all of the following are true: the current context
	 * is HostedCDN, the CDN is paused, the user has an active subscription,
	 * the license is invalid, and the subscription is in a loading state.
	 *
	 * @return bool True if the element should be disabled, false otherwise.
	 */
	public function should_disable_element_for_noobrocketcdn(): bool {
		return $this->is_subscription_loading()
			|| $this->is_cdn_paused()
			|| $this->should_display_licence_expired_notice()
			|| ! $this->subscription_controller->has_active_subscription();
	}

	/**
	 * Determines whether activating HostedCDN (free or paid) should be rejected.
	 *
	 * Used both to gate the `save_cdn_mode()` REST activation request and to decide
	 * whether the mode toggle checkbox itself is disabled in the UI — the two must
	 * agree, or the REST endpoint accepts a request the checkbox never lets a user send.
	 *
	 * Deliberately omits the mere fact that `cdn` is currently `0` — not yet turned
	 * on, or previously turned off by the user themselves — since that's the normal
	 * starting point for the very transition this method exists to gate; treating it
	 * as a rejection reason would always reject activation. What it does reject is
	 * {@see is_forced_off()}'s narrower, involuntary-suppression conditions (a
	 * cancelled paid subscription, an invalid free-tier licence): toggling the
	 * checkbox can't fix either of those, so unlike the merely-off case there's no
	 * legitimate activation path through the toggle to preserve.
	 *
	 * The banned-reseller term is added here specifically, and not to either of the
	 * other two predicates that also touch licence validity:
	 * - Not {@see should_display_licence_expired_notice()}: its own
	 *   `! is_reseller_license_banned()` exclusion is deliberate, so the expired-licence
	 *   banner never stacks on top of the banned-reseller one.
	 * - Not {@see is_forced_off()} (even though it's now also included below):
	 *   keeping the ban term here as its own OR branch, rather than folding it into
	 *   is_forced_off(), matters because is_forced_off() only catches a ban
	 *   incidentally, via its `is_free() && is_license_invalid()` branch (a ban
	 *   implies `is_revoked()`, a strict superset) - and that only fires for the
	 *   free tier. A banned reseller on an active *paid* plan has no guarantee
	 *   HostedCDN's own billing-subscription status has flipped to reflect it, so
	 *   is_forced_off() can miss it entirely there. The explicit term here is
	 *   what makes a paid-tier ban reject activation regardless.
	 *
	 * @return bool True if activation should be rejected, false otherwise.
	 */
	public function should_reject_noobrocketcdn_activation(): bool {
		return $this->is_subscription_loading()
			|| $this->should_display_licence_expired_notice()
			|| $this->user->is_reseller_license_banned()
			|| $this->context->is_forced_off();
	}

	/**
	 * Determines whether the HostedCDN status indicator should show as paused.
	 *
	 * Mirrors should_reject_noobrocketcdn_activation() but omits is_subscription_loading():
	 * a subscription being created shouldn't show as paused, its own "Creating your
	 * subscription..." status text already covers that case.
	 *
	 * is_cdn_paused() alone only means the toggle itself is off; it's paired here with
	 * has_active_subscription() (the user deliberately paused an otherwise-active
	 * subscription) or is_in_grace_period() (a cancelled subscription still resolves the
	 * toggle to off live, but should read as paused rather than blank while it winds down).
	 *
	 * @since 3.22
	 *
	 * @return bool True if the status indicator should show as paused, false otherwise.
	 */
	private function show_pause_state(): bool {
		return (
				$this->is_cdn_paused()
				&&
				(
					$this->subscription_controller->has_active_subscription()
					||
					$this->subscription_controller->is_in_grace_period()
				)
			)
			|| (
				$this->should_display_licence_expired_notice()
				|| $this->user->is_reseller_license_banned()
				|| $this->context->is_forced_off()
			);
	}

	/**
	 * Tooltip shown on the BYOCDN mode toggle when it's forced off by a hosting
	 * compatibility layer (e.g. {@see \NoobRocket\ThirdParty\Hostings\OneCom::disable_cdn_mode_toggle()}),
	 * explaining why the user can't switch it themselves.
	 *
	 * HostedCDN's own Free/Paid toggles are never forced off for this reason - see
	 * {@see get_noobrocketcdn_toggle_forced_off_tooltip()} for their (licence/subscription-based)
	 * forced-off tooltip.
	 *
	 * @return string
	 */
	private function get_toggle_forced_off_tooltip(): string {
		return __( 'This option is managed by your host and can’t be changed here.', 'noobrocket' );
	}

	/**
	 * Tooltip copy explaining why the HostedCDN mode toggle (Free or Paid) is forced off.
	 *
	 * Mirrors the precedence order of {@see should_reject_noobrocketcdn_activation()}
	 * exactly, so the toggle's disabled state and its tooltip text can never disagree:
	 * 1. Subscription creation in progress.
	 * 2. Expired/revoked NoobRocket licence (non-banned reseller).
	 * 3. Banned reseller licence.
	 * 4. Subscription forced-paused - a cancelled paid plan, or another involuntary
	 *    suppression {@see is_forced_off()} covers that isn't already caught above.
	 *
	 * @return string Tooltip copy, or an empty string when nothing forces the toggle off.
	 */
	private function get_noobrocketcdn_toggle_forced_off_tooltip(): string {
		if ( $this->is_subscription_loading() ) {
			return __( 'HostedCDN is currently being activated. Please wait, this should only take a moment.', 'noobrocket' );
		}

		if ( $this->should_display_licence_expired_notice() ) {
			return __( 'Renew to use HostedCDN Free.', 'noobrocket' );
		}

		if ( $this->user->is_reseller_license_banned() ) {
			return __( 'Contact support to find out how to restore access.', 'noobrocket' );
		}

		if ( $this->subscription_controller->is_paid() && $this->subscription_controller->is_in_grace_period() ) {
			return __( 'Cancelling your subscription.', 'noobrocket' );
		}

		if ( $this->context->is_forced_off() ) {
			return __( 'Renew to use HostedCDN.', 'noobrocket' ); // This copy needs to be updated.
		}

		return '';
	}

	/**
	 * Disables HostedCDN for paid subscriptions after cancellation.
	 *
	 * @since 3.23.4
	 *
	 * @return void
	 */
	public function maybe_disable_noobrocketcdn_paid_after_cancellation(): void {
		// Read fresh rather than through the per-request Options_Data snapshot, which can be
		// stale relative to another admin_init callback's same-request write (e.g.
		// DataManagerSubscriber::handle_noobrocketcdn_checkout_parameter()) - Context's own
		// get_applied_cdn_state() would otherwise classify a frozen, possibly-outdated value.
		$settings = $this->options_api->get( 'settings', [] );

		$screen = get_current_screen();
		if ( ! $screen || 'settings_page_noobrocket' !== $screen->id ||
			Context::HOSTEDCDN_TYPE !== $this->context->get_applied_cdn_state( $settings['cdn_state'] ?? null ) ) {
			return;
		}

		// Bail out if there is an active subscription.
		if ( $this->subscription_controller->has_active_subscription() ) {
			return;
		}

		// Bail out if the subscription is paid and is still within the cancellation grace period.
		if ( $this->subscription_controller->is_paid() && $this->subscription_controller->is_in_grace_period() ) {
			return;
		}

		if ( $this->subscription_controller->is_license_invalid() ) {
			return;
		}

		$forced_off_cache_status = $this->get_forced_off_tracking();

		// Bail out if forced off status has been reset.
		if ( empty( $forced_off_cache_status['persistent'] ) ) {
			return;
		}

		// Update the forced off tracking option to indicate the forced off has been resolved.
		$forced_off_cache_status['persistent'] = false;
		update_option( self::FORCED_OFF_TRACKING_OPTION, $forced_off_cache_status, false );

		// Set new CDN state to "nothing".
		$settings['cdn_state'] = Context::CDN_STATE_NOTHING;
		$this->options_api->set( 'settings', $settings );

		$this->track_event(
			'HostedCDN Mode Changed',
			array_merge(
				$this->get_tracking_data( $settings['cdn_state'] ),
				[ 'trigger' => 'pro_cancellation' ]
			)
		);
	}

	/**
	 * Localizes the `cdn_mode`/`cdn_status` tracking axis properties for JS-side
	 * Mixpanel tracking, independently of {@see \NoobRocket\Engine\Tracking\Tracking::localize_optin_status()}.
	 *
	 * @return void
	 */
	public function localize_tracking_data(): void {
		wp_localize_script( 'noobrocket-settings-common', 'noobrocket_cdn_mixpanel_data', $this->get_tracking_data() );
	}

	/**
	 * Gets the `cdn_mode` and `cdn_status` tracking axis values together.
	 *
	 * @param string|null $cdn_state Optional. Overrides the persisted `cdn_state`, for a caller
	 *                               that just wrote a new mode and needs the values computed
	 *                               against it rather than the stale, per-request options snapshot.
	 *
	 * @return array{cdn_mode: string, cdn_status: string}
	 */
	private function get_tracking_data( ?string $cdn_state = null ): array {
		return [
			'cdn_mode'   => $this->context->get_cdn_state( $cdn_state ),
			'cdn_status' => $this->context->get_cdn_status( $cdn_state ),
		];
	}

	/**
	 * Filters status indicator texts for the free HostedCDN tier.
	 *
	 * Hooked to {@see 'noobrocket_noobrocketcdn_status_indicator_texts'} at priority 10.
	 * Activates the status text once pages have been added, and surfaces an
	 * expiry notice when the NoobRocket licence is invalid.
	 *
	 * @param array $texts                   Text strings for the status indicator.
	 * @param int   $pages_count             Number of pages using HostedCDN.
	 * @param bool  $is_subscription_loading Whether the subscription is loading.
	 * @param bool  $free                    Whether this is for the free version.
	 *
	 * @return array
	 */
	public function get_free_status_indicator_texts( array $texts, int $pages_count, bool $is_subscription_loading, bool $free ): array {
		if ( ! $free ) {
			return $texts;
		}

		$is_paused = $this->is_cdn_paused() && $this->subscription_controller->has_active_subscription();

		if ( $is_paused ) {
			$texts['details'] = sprintf(
			// translators: %1$s = opening <strong> tag, %2$s = closing </strong> tag.
				__( '%1$sStart with your homepage and add up to 2 more key pages.%2$s Includes unlimited traffic across 10 edge locations.', 'noobrocket' ),
				'<strong>',
				'</strong>'
			);
		}

		if ( $pages_count > 0 ) {
			$texts['details']             = '';
			$texts['no_status_indicator'] = true;
		}

		if ( $this->subscription_controller->is_license_invalid() ) {
			$texts['class']              .= ' noobrocket-cdn-status--expired';
			$texts['details']             = __( 'Renew now to keep using HostedCDN Free.', 'noobrocket' );
			$texts['no_status_indicator'] = false;
		}

		if ( $this->user->is_reseller_license_banned() ) {
			$texts['details'] = '';
		}

		return $texts;
	}

	/**
	 * Filters status indicator texts for the paid HostedCDN tier.
	 *
	 * Hooked to {@see 'noobrocket_noobrocketcdn_status_indicator_texts'} at priority 10.
	 * Activates the paid status text and surfaces a cancellation notice during
	 * the grace period.
	 *
	 * @param array $texts                   Text strings for the status indicator.
	 * @param int   $pages_count             Number of pages using HostedCDN.
	 * @param bool  $is_subscription_loading Whether the subscription is loading.
	 * @param bool  $free                    Whether this is for the free version.
	 *
	 * @return array
	 */
	public function get_paid_status_indicator_texts( array $texts, int $pages_count, bool $is_subscription_loading, bool $free ): array {
		if ( $free ) {
			return $texts;
		}

		$texts['details']     = '';
		$texts['status_text'] = __( 'HostedCDN delivers every page of your site from 100+ edge locations worldwide.', 'noobrocket' );

		if ( $this->subscription_controller->is_in_grace_period() ) {
			$texts['status_text']         = '';
			$texts['no_status_indicator'] = true;
		}

		return $texts;
	}


	/**
	 * Renders the cancelled-banned notice.
	 *
	 * @since 3.23.4
	 *
	 * @return void
	 */
	public function render_cancelled_banner_notice(): void {
		if ( ! current_user_can( 'noobrocket_manage_options' ) ) {
			return;
		}

		if ( ! $this->subscription_controller->is_in_grace_period() ) {
			return;
		}

		$this->track_event(
			'HostedCDN Pending Cancellation Banner Viewed',
			[
				'stage'          => 'grace_period',
				// The grace period's actual length/end date isn't currently exposed
				// by the HostedCDN subscription API so null is used here instead.
				'days_remaining' => null,
			]
		);

		echo $this->generate( 'partials/cdn/noobrocket-cancelled-notice', [] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view.
	}

	/**
	 * Displays an admin notice when fresh-install Pro subscription detection failed after all retries.
	 *
	 * @return void
	 */
	public function render_pro_detection_failure_notice(): void {
		if ( ! current_user_can( 'noobrocket_manage_options' ) ) {
			return;
		}

		if ( 'settings_page_noobrocket' !== get_current_screen()->id ) {
			return;
		}

		if ( ! get_transient( 'noobrocket_cdn_pro_detection_failed' ) ) {
			return;
		}

		$retry_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=noobrocket_retry_pro_detection' ),
			'noobrocket_retry_pro_detection'
		);

		$notice_data = [
			'retry_url' => $retry_url,
		];

		echo $this->generate( 'partials/cdn/cdn-pro-retry-notice', $notice_data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view.
	}

	/**
	 * Reads the forced off tracking option, migrating the legacy bool format to the current array format.
	 *
	 * @return array
	 */
	private function get_forced_off_tracking(): array {
		$stored = get_option( self::FORCED_OFF_TRACKING_OPTION, [] );

		// Migrate legacy bool value: the option used to be stored as a plain bool.
		if ( is_bool( $stored ) ) {
			return [ 'tracking' => $stored ];
		}

		return (array) $stored;
	}

	/**
	 * Builds a table-list-row data array from a page object.
	 *
	 * @since 3.22
	 *
	 * @param object $page Page object with url and title properties.
	 *
	 * @return array Row data for the table-list-row partial.
	 */
	private function build_page_row( object $page ): array {
		$disable_class = $this->is_subscription_loading() ? 'noobrocket-cdn-disabled' : '';
		$delete_button = '<button type="button" class="noobrocket-table-list__delete ' . esc_attr( $disable_class ) . '" data-id="' . esc_attr( $page->id ) . '" aria-label="' . esc_attr__( 'Remove page', 'noobrocket' ) . '">'
			. '<span class="noobrocket-icon-trash"></span>'
			. '</button>';

		return [
			'columns' => [
				[
					'content' => '<a href="' . esc_url( $page->url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $page->title ) . '</a>',
				],
				[
					'type'    => 'actions',
					'content' => $delete_button,
				],
			],
		];
	}

	/**
	 * Retrieves the list of pages added to HostedCDN for the free plan.
	 *
	 * @since 3.22
	 *
	 * @return array List of page objects with id, url, and title properties.
	 */
	private function get_items(): array {
		$query_params = [
			'orderby' => 'modified',
			'order'   => 'asc',
			'number'  => 20,
		];

		return $this->cdn_query->query( $query_params );
	}

	/**
	 * Checks if subscription is currently processing.
	 *
	 * @since 3.22
	 *
	 * @return bool True if the subscription is processing, false otherwise.
	 */
	private function is_subscription_loading(): bool {
		return $this->subscription_controller->is_subscription_creation_loading();
	}

	/**
	 * Checks if the NoobRocket license is expired for a free HostedCDN subscriber.
	 *
	 * @return bool True when the expired licence notice should be displayed.
	 */
	private function should_display_licence_expired_notice(): bool {
		return $this->context->is_noobrocketcdn() &&
				$this->subscription_controller->is_free() &&
				$this->subscription_controller->is_license_invalid() &&
				! $this->user->is_reseller_license_banned();
	}

	/**
	 * Checks if the CDN type is currently filtered.
	 *
	 * @since 3.22
	 *
	 * @return bool True if the CDN type is filtered, false otherwise.
	 */
	private function is_cdn_type_filtered(): bool {
		$allowed_cdn_types = [ 'noobrocketcdn', 'byocdn' ];

		/**
		 * Pre-filter cdn_type option.
		 *
		 * @since 3.22
		 *
		 * @param mixed $cdn_type Filtered CDN type.
		 */
		$cdn_type = wpm_apply_filters_typed( 'string|null', 'pre_get_noobrocket_option_cdn_type', null, '' );

		if ( null !== $cdn_type && in_array( $cdn_type, $allowed_cdn_types, true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get status indicator data for HostedCDN.
	 *
	 * Retrieves an array of data used to render the HostedCDN status indicator,
	 * including status text, details, and various flags based on the current
	 * subscription state.
	 *
	 * @param int  $pages_count            Number of pages currently using HostedCDN.
	 * @param bool $is_subscription_loading Whether the subscription is currently being processed.
	 * @param bool $free                    Whether this is for the free version of HostedCDN. Default true.
	 *
	 * @return array {
	 *     Array of status indicator data.
	 *
	 *     @type string $class                  CSS class to apply to the status indicator. Empty string or ' noobrocket-cdn-status--paused'.
	 *     @type bool   $is_active              Whether HostedCDN is active.
	 *     @type string $status_text            Current status text to display.
	 *     @type string $details                Details text describing the current status.
	 *     @type string $active_status_text     Text to display when HostedCDN is active.
	 *     @type string $paused_status_text     Text to display when HostedCDN is paused.
	 *     @type string $paused_details.        Details text to display when HostedCDN is not active.
	 *     @type bool   $is_paused              Whether HostedCDN is currently paused.
	 *     @type int    $pages_count            Number of pages currently using HostedCDN.
	 *     @type bool   $is_subscription_loading Whether the subscription is currently being processed.
	 *     @type bool   $hide_pause_btn         Whether to hide the pause button.
	 * }
	 */
	private function get_status_indicator_data( int $pages_count, bool $is_subscription_loading, bool $free = true ): array {
		$texts = [
			'status_text'         => '',
			'details'             => sprintf(
			// translators: %1$s = opening <strong> tag, %2$s = closing </strong> tag, %3$s = line breaking <br /> tag.
				__( '%1$sOne more step to a faster website:%2$s add your most important page, and HostedCDN Free will speed it up for your visitors everywhere.', 'noobrocket' ),
				'<strong>',
				'</strong>'
			),
			'class'               => '',
			'no_status_indicator' => false,
		];

		/**
		 * Filters the status indicator text strings for HostedCDN.
		 *
		 * Free- and paid-tier callbacks are registered by default to apply
		 * tier-specific overrides. Additional callbacks may be added to
		 * customise the indicator for custom subscription states.
		 *
		 * @param array $texts                   {
		 *     Text strings and CSS modifier class for the status indicator.
		 *
		 *     @type string $paused_status_text Text displayed when CDN is paused.
		 *     @type string $active_status_text Text displayed when CDN is active.
		 *     @type string $paused_details     Details shown while CDN is paused.
		 *     @type string $status_text        Currently-shown status text (empty = inactive state).
		 *     @type string $details            Currently-shown details text.
		 *     @type string $class              CSS modifier class(es) for the indicator element.
		 * }
		 * @param int   $pages_count             Number of pages currently using HostedCDN.
		 * @param bool  $is_subscription_loading Whether the subscription is currently being processed.
		 * @param bool  $free                    Whether this is for the free version of HostedCDN.
		 *
		 * @since 3.22.0.2
		 */
		$texts = wpm_apply_filters_typed( 'array', 'noobrocket_noobrocketcdn_status_indicator_texts', $texts, $pages_count, $is_subscription_loading, $free );

		if ( $is_subscription_loading ) {
			$texts['status_text']         = __( 'Creating your subscription...', 'noobrocket' );
			$texts['details']             = __( 'Please wait, HostedCDN will be ready and active shortly. This usually takes about 90 seconds.', 'noobrocket' );
			$texts['no_status_indicator'] = false;
		}

		$is_paused = $this->show_pause_state();

		if ( $is_paused ) {
			$texts['class'] .= ' noobrocket-cdn-status--paused';
		}

		return [
			'class'                   => $texts['class'],
			'is_active'               => true,
			'status_text'             => $texts['status_text'],
			'details'                 => $texts['details'],
			'is_paused'               => $is_paused,
			'pages_count'             => $pages_count,
			'is_subscription_loading' => $is_subscription_loading,
			'hide_pause_btn'          => ( $is_subscription_loading || 0 === $pages_count ) && ! $is_paused,
			'no_status_indicator'     => $texts['no_status_indicator'],
		];
	}

	/**
	 * Check if cdn should pause or not.
	 *
	 * @return bool
	 */
	private function is_cdn_paused(): bool {
		return Context::HOSTEDCDN_TYPE !== $this->context->get_applied_cdn_state();
	}
}
