<?php
declare(strict_types=1);

namespace NoobRocket\Engine\Preload\Database\Tables;

use NoobRocket\Engine\Common\Database\Tables\AbstractTable;

class Cache extends AbstractTable {
	/**
	 * Table name
	 *
	 * @var string
	 */
	protected $name = 'nbr_noobrocket_cache';

	/**
	 * Database version key (saved in _options or _sitemeta)
	 *
	 * @var string
	 */
	protected $db_version_key = 'nbr_noobrocket_cache_version';

	/**
	 * Database version
	 *
	 * @var int
	 */
	protected $version = 20261013;

	/**
	 * Key => value array of versions => methods.
	 *
	 * @var array
	 */
	protected $upgrades = [
		20220927 => 'add_is_locked_column',
		20261009 => 'add_status_locked_modified_index',
		20261010 => 'add_discovery_key',
		20261013 => 'add_failure_reason',
	];

	/**
	 * Hook into queries, admin screens, and more!
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		parent::__construct();
		add_action( 'noobrocket_preload_activation', [ $this, 'maybe_upgrade' ] );
	}

	/**
	 * Setup the database schema
	 *
	 * @return void
	 */
	protected function set_schema() {
		$this->schema = "
			id               bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			url              varchar(2000)       NOT NULL default '',
			url_hash         char(64) CHARACTER SET ascii COLLATE ascii_bin NULL default NULL,
			status           varchar(255)        NOT NULL default '',
			failure_reason   varchar(32)         NOT NULL default '',
			modified         timestamp           NOT NULL default '0000-00-00 00:00:00',
			last_accessed    timestamp           NOT NULL default '0000-00-00 00:00:00',
			is_locked        tinyint(1)          NOT NULL default 0,
			PRIMARY KEY (id),
			KEY url (url(191)),
			UNIQUE KEY url_hash (url_hash),
			KEY modified (modified),
			KEY last_accessed (last_accessed),
			KEY status_locked_modified (status(32), is_locked, modified)";
	}

	/**
	 * Trigger recreation of cache table if not exist.
	 *
	 * @return void
	 */
	public function maybe_trigger_recreate_table() {
		if ( $this->exists() ) {
			return;
		}

		delete_option( $this->db_version_key );
	}

	/**
	 * Add is_locked column.
	 *
	 * @return bool
	 */
	public function add_is_locked_column() {
		$hash_column_exists = $this->column_exists( 'is_locked' );

		$created = true;

		if ( ! $hash_column_exists ) {
			$created &= $this->get_db()->query( "ALTER TABLE {$this->table_name} ADD COLUMN is_locked tinyint(1) NOT NULL default 0 AFTER last_accessed" );
		}

		return $this->is_success( $created );
	}

	/**
	 * Index native pending admission and sparse status counts.
	 *
	 * The short status prefix supports older utf8mb4 index-size limits.
	 *
	 * @return bool
	 */
	public function add_status_locked_modified_index() {
		if ( $this->index_exists( 'status_locked_modified' ) ) {
			return true;
		}

		$db = $this->get_db();
		if ( ! $db ) {
			return false;
		}

		$result = $db->query( "ALTER TABLE {$this->table_name} ADD INDEX status_locked_modified (status(32), is_locked, modified)" );

		return false !== $result && $this->index_exists( 'status_locked_modified' );
	}

	/**
	 * Enforce atomic discovery without rewriting existing rows or duplicates.
	 *
	 * A full URL exceeds older utf8mb4 index limits. The digest indexes every
	 * byte while nullable legacy keys preserve installed row identities.
	 *
	 * @return bool
	 */
	public function add_discovery_key() {
		$db = $this->get_db();
		if ( ! $db ) {
			return false;
		}

		if ( ! $this->column_exists( 'url_hash' ) ) {
			$db->query( "ALTER TABLE {$this->table_name} ADD COLUMN url_hash char(64) CHARACTER SET ascii COLLATE ascii_bin NULL default NULL" );
			if ( ! $this->column_exists( 'url_hash' ) ) {
				return false;
			}
		}

		if ( ! $this->index_exists( 'url_hash' ) ) {
			$db->query( "ALTER TABLE {$this->table_name} ADD UNIQUE INDEX url_hash (url_hash)" );
		}

		$indexes = $db->get_results( $db->prepare( "SHOW INDEXES FROM {$this->table_name} WHERE Key_name = %s", 'url_hash' ) );
		if ( ! is_array( $indexes ) || 1 !== count( $indexes ) ) {
			return false;
		}

		$index = $indexes[0];
		return 0 === (int) $index->Non_unique
			&& 'url_hash' === $index->Column_name
			&& null === $index->Sub_part;
	}

	/** Add bounded diagnostics without rewriting existing jobs. */
	public function add_failure_reason(): bool {
		if ( $this->column_exists( 'failure_reason' ) ) {
			return true;
		}
		$db = $this->get_db();
		if ( ! $db ) {
			return false;
		}
		$db->query( "ALTER TABLE {$this->table_name} ADD COLUMN failure_reason varchar(32) NOT NULL default ''" );
		return $this->column_exists( 'failure_reason' );
	}

	/**
	 * Truncate cache table.
	 *
	 * @return bool
	 */
	public function truncate_cache_table(): bool {
		if ( ! $this->exists() ) {
			return false;
		}

		return parent::truncate();
	}
}
