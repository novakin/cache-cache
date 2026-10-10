<?php
declare(strict_types=1);

namespace NoobRocket\Engine\Cache\Abilities;

use NoobRocket\Engine\Abilities\AbilitiesInterface;
use NoobRocket\Engine\Tracking\TrackingTrait;

class ClearWebsiteCache implements AbilitiesInterface {
	use TrackingTrait;

	/**
	 * Registers the ability to clear website cache.
	 */
	public function register(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'noobrocket/clear-website-cache',
			[
				'label'               => __( 'Clear website cache', 'noobrocket' ),
				'description'         => _x(
					'Clears the full Cache-Cache cache for the entire website. Use this when the user asks to clear, purge, or refresh all cached pages for the site. This is a site-wide destructive cache action. Do not use it for a single page; use clear-url-cache instead. Confirmation is required before calling',
					'Ability description',
					'noobrocket'
					),
				'category'            => 'noobrocket-cache',
				'input_schema'        => [],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'success' => [
							'type'        => 'boolean',
							'description' => __( 'Indicates whether the website cache was successfully cleared', 'noobrocket' ),
						],
						'error'   => [
							'type'        => 'string',
							'description' => __( 'Reason the cache could not be cleared, empty on success.', 'noobrocket' ),
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
						'destructive' => false,
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
	 * Executes the ability to clear website cache.
	 *
	 * @return array
	 */
	public function execute(): array {
		$this->track_event(
			'MCP Ability Executed',
			[
				'ability' => 'noobrocket/clear-website-cache',
				'context' => 'wp_plugin_mcp',
			]
		);

		if ( noobrocket_is_importing() ) {
			return [
				'success' => false,
				'error'   => __( 'Unable to clear cache: a content import is currently in progress.', 'noobrocket' ),
			];
		}

		$result = noobrocket_clean_domain();

		if ( false === $result ) {
			return [
				'success' => false,
				'error'   => __( 'Unable to clear cache: no cacheable URLs were found for this site.', 'noobrocket' ),
			];
		}

		return [
			'success' => true,
			'error'   => '',
		];
	}
}
