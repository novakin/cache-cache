<?php
declare(strict_types=1);

namespace NoobRocket\Engine\Abilities\CLI;

use NoobRocket\Engine\Abilities\Catalog;

class Command {
	/**
	 * Abilities catalog instance.
	 *
	 * @var Catalog
	 */
	private $catalog;

	/**
	 * Constructor.
	 *
	 * @param Catalog $catalog Abilities catalog instance.
	 */
	public function __construct( Catalog $catalog ) {
		$this->catalog = $catalog;
	}

	/**
	 * Prints the NoobRocket abilities catalog as JSON.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp noobrocket abilities-catalog
	 *     [{"name":"noobrocket/get-options", ...}]
	 *
	 * @return void
	 */
	public function abilities_catalog(): void {
		\WP_CLI::line( (string) wp_json_encode( $this->catalog->get_manifest(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
	}
}
