<?php
namespace NoobRocket\Traits;

trait Config_Updater {
	/**
	 * Update htaccess and NoobRocket config file if the option was modified.
	 *
	 * @since 3.1
	 * @author Remy Perona
	 *
	 * @param string $old_value Option's previous value.
	 * @param string $value     Option's new value.
	 * @return void
	 */
	public function after_update_single_option( $old_value, $value ) {
		if ( $old_value !== $value ) {
			$this->flush_htaccess();
			$this->generate_config_file();
		}
	}

	/**
	 * Sets the htaccess update request
	 *
	 * @since 3.1
	 * @author Remy Perona
	 *
	 * @return void
	 */
	protected function flush_htaccess() {
		wp_cache_set( 'noobrocket_flush_htaccess', 1 );
	}

	/**
	 * Sets NoobRocket config file update request
	 *
	 * @since 3.1
	 * @author Remy Perona
	 *
	 * @return void
	 */
	protected function generate_config_file() {
		wp_cache_set( 'noobrocket_generate_config_file', 1 );
	}

	/**
	 * Performs the files update if requested
	 *
	 * @since 3.1
	 * @author Remy Perona
	 *
	 * @return void
	 */
	public function maybe_update_config() {
		if ( wp_cache_get( 'noobrocket_flush_htaccess' ) ) {
			flush_noobrocket_htaccess();
			wp_cache_delete( 'noobrocket_flush_htaccess' );
		}

		if ( wp_cache_get( 'noobrocket_generate_config_file' ) ) {
			\noobrocket_generate_config_file();
			wp_cache_delete( 'noobrocket_generate_config_file' );
		}
	}
}
