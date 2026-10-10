<?php
declare(strict_types=1);

namespace NoobRocket\Engine\Admin\PerformanceMonitoring\Abilities;

use NoobRocket\Engine\Abilities\AbilitiesInterface;
use NoobRocket\Engine\Admin\PerformanceMonitoring\{
	Context\Context,
	Database\Queries\PerformanceMonitoring as Query
};
use NoobRocket\Engine\Tracking\TrackingTrait;

class RemovePageInsights implements AbilitiesInterface {
	use TrackingTrait;

	/**
	 * Context instance providing necessary dependencies and configuration.
	 *
	 * @var Context
	 */
	private $context;

	/**
	 * Database query class for interacting with Performance monitoring data.
	 *
	 * @var Query
	 */
	private $query;

	/**
	 * Constructor.
	 *
	 * @param Context $context The context instance providing necessary dependencies and configuration.
	 * @param Query   $query   The database query class for interacting with Performance monitoring data.
	 */
	public function __construct( Context $context, Query $query ) {
		$this->context = $context;
		$this->query   = $query;
	}

	/**
	 * Registers the ability to remove page insights.
	 */
	public function register(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'noobrocket/remove-page-insights',
			[
				'label'               => __( 'Remove Page Insights', 'noobrocket' ),
				'description'         => _x(
					'Permanently removes a URL from Performance monitoring monitoring and deletes its historical score data.
Use this only when the user explicitly asks to stop monitoring a page and accepts data deletion. Do not use it for pausing or retesting.
This action is destructive and non-idempotent. Before calling, explicitly confirm the exact URL with the user. Never remove multiple pages in bulk; require separate confirmation for each URL.',
					'Ability description',
					'noobrocket'
					),
				'category'            => 'noobperformance-monitoring',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'url' => [
							'type'        => 'string',
							'format'      => 'uri',
							'description' => __( 'Full URL of the page to remove from Performance monitoring monitoring.', 'noobrocket' ),
						],
					],
					'required'   => [ 'url' ],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'success' => [
							'type'        => 'boolean',
							'description' => __( 'Indicates whether the page was successfully removed', 'noobrocket' ),
						],
						'error'   => [
							'type'        => 'string',
							'description' => __( 'Error message if the page could not be removed', 'noobrocket' ),
						],
					],
				],
				'execute_callback'    => [ $this, 'execute' ],
				'permission_callback' => [ $this, 'check_permissions' ],
				'meta'                => [
					'mcp'          => [
						'public' => true,
					],
					'show_in_rest' => true,
					'annotations'  => [
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => false,
					],
				],
			]
		);
	}

	/**
	 * Checks if the current user has permission to execute this ability.
	 *
	 * @return bool
	 */
	public function check_permissions(): bool {
		return current_user_can( 'noobrocket_manage_options' );
	}

	/**
	 * Executes the ability to remove a page from Performance monitoring monitoring.
	 *
	 * @param array $input The input data containing the URL to be removed.
	 *
	 * @return array
	 */
	public function execute( $input = null ): array {
		$this->track_event(
			'MCP Ability Executed',
			[
				'ability' => 'noobrocket/remove-page-insights',
				'context' => 'wp_plugin_mcp',
			]
		);
		if ( ! $this->context->is_allowed() ) {
			return [
				'success' => false,
				'error'   => 'Performance monitoring is disabled.',
			];
		}

		$url = noobrocket_add_url_protocol( $input['url'] );

		$rows = $this->query->get_rows_by_url( $url );

		if ( empty( $rows ) ) {
			return [
				'success' => false,
				'error'   => 'URL is not currently being monitored.',
			];
		}

		// A page can have both a mobile and a desktop row; remove all of them.
		$deleted_id = null;
		foreach ( $rows as $row ) {
			if ( $this->query->delete_item( $row->id ) ) {
				$deleted_id = $row->id;
			}
		}

		// Nothing was removed (e.g. the rows were deleted concurrently); skip the side-effects.
		if ( null === $deleted_id ) {
			return [
				'success' => false,
				'error'   => 'URL is not currently being monitored.',
			];
		}

		/**
		 * Fires when a performance monitoring job is deleted.
		 *
		 * Removing a page is a single logical event: the listeners reset the global
		 * score and refresh recommendations globally, so the action is fired once even
		 * when several rows (mobile and desktop) were removed.
		 *
		 * @since 3.20
		 *
		 * @param int $id The ID of a deleted performance monitoring job.
		 */
		do_action( 'noobrocket_noobrocket_insights_job_deleted', $deleted_id );

		return [
			'success' => true,
			'error'   => '',
		];
	}
}
