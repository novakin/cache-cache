<?php
declare(strict_types=1);

namespace NoobRocket\Engine\Media\Fonts\Admin;

use NoobRocket\Engine\Admin\Settings\Settings as AdminSettings;

class Settings {
	/**
	 * Adds the host fonts locally option to NoobRocket options array
	 *
	 * @since 3.19 adds auto preload fonts
	 *
	 * @param array $options NoobRocket options array.
	 *
	 * @return array
	 */
	public function add_option( array $options ): array {
		$options['host_fonts_locally'] = 0;
		$options['auto_preload_fonts'] = 0;

		return $options;
	}

	/**
	 * Sanitizes the option value when saving from the settings page
	 *
	 * @since 3.19 adds auto preload fonts
	 *
	 * @param array         $input    Array of sanitized values after being submitted by the form.
	 * @param AdminSettings $settings Settings class instance.
	 *
	 * @return array
	 */
	public function sanitize_option_value( array $input, AdminSettings $settings ): array {
		$input['host_fonts_locally'] = $settings->sanitize_checkbox( $input, 'host_fonts_locally' );
		$input['auto_preload_fonts'] = $settings->sanitize_checkbox( $input, 'auto_preload_fonts' );

		return $input;
	}
}
