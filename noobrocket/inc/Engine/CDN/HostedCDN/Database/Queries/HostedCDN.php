<?php
declare(strict_types=1);

namespace NoobRocket\Engine\CDN\HostedCDN\Database\Queries;

use NoobRocket\Engine\CDN\HostedCDN\Database\Rows\HostedCDN as HostedCDNRow;
use NoobRocket\Engine\CDN\HostedCDN\Database\Schemas\HostedCDN as HostedCDNSchema;
use NoobRocket\Engine\Common\Database\Queries\AbstractQuery;
use NoobRocket\Engine\Common\Page\PageHandlerTrait;

/**
 * HostedCDN Query class.
 */
class HostedCDN extends AbstractQuery {
	use PageHandlerTrait;

	/**
	 * Name of the database table to query.
	 *
	 * @var string
	 */
	protected $table_name = 'nbr_noobrocket_cdn';

	/**
	 * String used to alias the database table in MySQL statement.
	 *
	 * Keep this short, but descriptive. I.E. "tr" for term relationships.
	 *
	 * This is used to avoid collisions with JOINs.
	 *
	 * @var string
	 */
	protected $table_alias = 'nbr_cdn';

	/**
	 * Name of class used to setup the database schema.
	 *
	 * @var string
	 */
	protected $table_schema = HostedCDNSchema::class;

	/**
	 * Name for a single item.
	 *
	 * Use underscores between words. I.E. "term_relationship"
	 *
	 * This is used to automatically generate action hooks.
	 *
	 * @var string
	 */
	protected $item_name = 'noobrocket_cdn';

	/**
	 * Plural version for a group of items.
	 *
	 * Use underscores between words. I.E. "term_relationships"
	 *
	 * This is used to automatically generate action hooks.
	 *
	 * @var string
	 */
	protected $item_name_plural = 'noobrocket_cdn';

	/**
	 * Name of class used to turn IDs into first-class objects.
	 *
	 * This is used when looping through return values to guarantee their shape.
	 *
	 * @var mixed
	 */
	protected $item_shape = HostedCDNRow::class;

	/**
	 * Get all pages from the database.
	 *
	 * @return HostedCDNRow[]
	 */
	public function get_all(): array {
		return $this->query( [] );
	}

	/**
	 * Get a page by URL.
	 *
	 * @param string $url The URL to search for.
	 *
	 * @return HostedCDNRow|false
	 */
	public function get_by_url( string $url ) {
		$normalized_url = $this->normalize_url_path_encoding( untrailingslashit( $url ) );

		$items = $this->query(
			[
				'url' => $normalized_url,
			]
		);

		if ( empty( $items ) ) {
			return false;
		}

		return $items[0];
	}

	/**
	 * Check if the url is found in the database.
	 *
	 * @param string $url The URL to search for.
	 *
	 * @return bool
	 */
	public function is_url_found( string $url ): bool {
		$normalized_url = $this->normalize_url_path_encoding( untrailingslashit( $url ) );

		$counter = $this->query(
			[
				'count' => true,
				'url'   => $normalized_url,
			]
		);

		return 0 < (int) $counter;
	}

	/**
	 * Get total count of pages.
	 *
	 * @param bool $bool_cache Use DB cache or not.
	 *
	 * @return int
	 */
	public function get_total_count( bool $bool_cache = true ): int {
		return (int) $this->query(
			[
				'count' => true,
			],
			$bool_cache
		);
	}

	/**
	 * Delete all rows from the table.
	 *
	 * @return bool|int
	 */
	public function delete_all_rows() {
		$db = $this->get_db();

		if ( ! $db ) {
			return false;
		}

		$prefixed_table_name = $db->prefix . $this->table_name;

		$query         = "DELETE FROM `$prefixed_table_name`";
		$rows_affected = $db->query( $query );

		return $rows_affected;
	}
}
