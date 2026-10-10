<?php
declare(strict_types=1);

namespace NoobRocket\Engine\Plugin;

use NoobRocket\Event_Management\Subscriber_Interface;

class OptionsBackupSubscriber implements Subscriber_Interface {

	/**
	 * Options backup service.
	 *
	 * @var OptionsBackup
	 */
	private $backup;

	/**
	 * Constructor.
	 *
	 * @param OptionsBackup $backup Options backup service.
	 */
	public function __construct( OptionsBackup $backup ) {
		$this->backup = $backup;
	}

	/**
	 * {@inheritdoc}
	 */
	public static function get_subscribed_events(): array {
		return [
			'noobrocket_upgrade' => [ 'purge_backups', 1, 2 ],
		];
	}

	/**
	 * Deletes existing settings backups once, when updating to 3.23.3.3 or later.
	 *
	 * @param string $new_version Incoming plugin version.
	 * @param string $old_version Currently installed plugin version.
	 */
	public function purge_backups( string $new_version, string $old_version ): void {
		if ( version_compare( $old_version, '3.23.3.3', '>=' ) ) {
			return;
		}

		$this->backup->delete_all();
	}
}
