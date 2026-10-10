<?php
namespace NoobRocket\Engine\Media\PreloadFonts\Admin;

use NoobRocket\Admin\Options_Data;
use NoobRocket\Admin\Options as Options_API;
use NoobRocket\Engine\Media\PreloadFonts\Database\Table\PreloadFonts;
use NoobRocket\Engine\Media\PreloadFonts\Database\Queries\PreloadFonts as PreloadFontsQuery;

class Settings {
	/**
	 * NoobRocket options instance.
	 *
	 * @var Options_Data
	 */
	private $options;

	/**
	 * NoobRocket Options API Instance.
	 *
	 * @var Options_API
	 */
	private $options_api;

	/** @var PreloadFonts */
	private $table;

	/** @var PreloadFontsQuery */
	private $query;

	/**
	 * Creates an instance of the class.
	 *
	 * @param Options_Data $option_data NoobRocket Options instance.
	 * @param Options_API  $options_api NoobRocket Options API instance.
	 * @param PreloadFonts $table Font hint table owner.
	 * @param PreloadFontsQuery $query Font hint query-cache owner.
	 */
	public function __construct( Options_Data $option_data, Options_API $options_api, PreloadFonts $table, PreloadFontsQuery $query ) {
		$this->options     = $option_data;
		$this->options_api = $options_api;
		$this->table       = $table;
		$this->query       = $query;
	}

	/** Font delivery changes make formerly collected URLs unsafe to preload. */
	public function clear_font_hints(): void {
		if ( false !== $this->table->delete_all() ) {
			$this->query->invalidate_cache();
		}
	}

	/**
	 * Enables the auto preload fonts option if the old preload fonts option is not empty.
	 *
	 * This function checks the value of the `preload_fonts` option.
	 * If it contains a non-empty value, it updates the `auto_preload_fonts` option to `true`.
	 * This is useful for ensuring that automatic font preloading is enabled based on legacy settings.
	 *
	 * @return void
	 */
	public function maybe_enable_auto_preload_fonts(): void {
		$options = $this->options_api->get( 'settings', [] );
		if ( empty( $options['preload_fonts'] ) ) {
			return;
		}

		$this->options->set( 'auto_preload_fonts', true );
		$this->options_api->set( 'settings', $this->options->get_options() );
	}
}
