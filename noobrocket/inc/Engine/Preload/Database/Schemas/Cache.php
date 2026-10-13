<?php

namespace NoobRocket\Engine\Preload\Database\Schemas;

use NoobRocket\Dependencies\BerlinDB\Database\Schema;

class Cache extends Schema {

	/**
	 * Array of database column objects
	 *
	 * @var array
	 */
	public $columns = [

		// ID column.
		[
			'name'     => 'id',
			'type'     => 'bigint',
			'length'   => '20',
			'unsigned' => true,
			'extra'    => 'auto_increment',
			'primary'  => true,
			'sortable' => true,
		],


		// URL    column.
		[
			'name'       => 'url',
			'type'       => 'varchar',
			'length'     => '2000',
			'default'    => null,
			'cache_key'  => true,
			'searchable' => true,
			'sortable'   => false,
		],

		// Atomic discovery key. Existing rows may retain NULL.
		[
			'name'       => 'url_hash',
			'type'       => 'char',
			'length'     => '64',
			'default'    => null,
			'allow_null' => true,
		],

		// STATUS column.
		[
			'name'       => 'status',
			'type'       => 'varchar',
			'length'     => '255',
			'default'    => null,
			'cache_key'  => true,
			'searchable' => true,
			'sortable'   => false,
		],

		// Failure diagnostics; empty for legacy rows or jobs without a reason.
		[
			'name'    => 'failure_reason',
			'type'    => 'varchar',
			'length'  => '32',
			'default' => '',
		],

		// MODIFIED column.
		[
			'name'       => 'modified',
			'type'       => 'timestamp',
			'default'    => '0000-00-00 00:00:00',
			'created'    => true,
			'date_query' => true,
			'sortable'   => true,
		],

		// LAST_ACCESSED column.
		[
			'name'       => 'last_accessed',
			'type'       => 'timestamp',
			'default'    => '0000-00-00 00:00:00',
			'created'    => true,
			'date_query' => true,
			'sortable'   => true,
		],

		// IS_LOCKED column.
		[
			'name'       => 'is_locked',
			'type'       => 'tinyint',
			'length'     => '1',
			'default'    => 0,
			'cache_key'  => true,
			'searchable' => true,
			'sortable'   => true,
		],

	];
}
