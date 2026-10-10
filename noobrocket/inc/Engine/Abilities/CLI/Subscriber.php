<?php
declare(strict_types=1);

namespace NoobRocket\Engine\Abilities\CLI;

use NoobRocket\Event_Management\Subscriber_Interface;

class Subscriber implements Subscriber_Interface {
	/**
	 * Abilities WP-CLI command instance.
	 *
	 * @var Command
	 */
	private $command;

	/**
	 * Constructor.
	 *
	 * @param Command $command Abilities WP-CLI command instance.
	 */
	public function __construct( Command $command ) {
		$this->command = $command;
	}

	/**
	 * Get the events to which this subscriber wants to listen.
	 *
	 * @return array The events and their corresponding callback methods.
	 */
	public static function get_subscribed_events(): array {
		return [
			'cli_init' => 'register_commands',
		];
	}

	/**
	 * Registers the `wp noobrocket abilities-catalog` command with WP-CLI.
	 *
	 * @return void
	 */
	public function register_commands(): void {
		\WP_CLI::add_command( 'noobrocket abilities-catalog', [ $this->command, 'abilities_catalog' ] );
	}
}
