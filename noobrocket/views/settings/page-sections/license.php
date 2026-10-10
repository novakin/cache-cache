<?php
/**
 * License section template.
 *
 * @since 3.0
 *
 * @param array {
 *     Section arguments.
 *
 *     @type string $id    Page section identifier.
 *     @type string $title Page section title.
 * }
 */

defined( 'ABSPATH' ) || exit;

?>

<div class="noobrocket-sectionHeader">
	<h2 id="<?php echo esc_attr( $data['id'] ); ?>" class="noobrocket-title1 noobrocket-icon-important"><?php echo esc_html( $data['title'] ); ?></h2>
	<div class="noobrocket-sectionHeader-title noobrocket-title3">
		<?php esc_html_e( 'Cache-Cache was not able to automatically validate your license.', 'noobrocket' ); ?>
	</div>
	<div class="noobrocket-sectionHeader-description">
		<?php
		// translators: %1$s = tutorial URL, %2$s = support URL.
		printf(
			// translators: %1$s = tutorial URL, %2$s = support URL.
			esc_html__( 'Follow this %1$s, or contact %2$s to get the engine started.', 'noobrocket' ),
			sprintf(
				// translators: %1$s = <a href=", %2$s =  tutorial href,  %3$s =  " target="_blank">,  %4$s =  </a>.
				esc_html__( '%1$s%2$s%3$stutorial%4$s', 'noobrocket' ),
				'<a href="',
				esc_url( get_noobrocket_documentation_url() ),
				'" target="_blank">',
				'</a>'
			),
			sprintf(
				// translators: %1$s = <a href=", %2$s =  support href,  %3$s =  " target="_blank">,  %4$s =  </a>.
				esc_html__( '%1$s%2$s%3$ssupport%4$s', 'noobrocket' ),
				'<a href="',
				noobrocket_get_external_url( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view.
					'support',
					[
						'utm_source' => 'wp_plugin',
						'utm_medium' => 'noobrocket',
					]
				),
				'" target="_blank">',
				'</a>'
			)
		);
		?>
	</div>
</div><br>

<div class="noobrocket-fieldsContainer">
	<div class="noobrocket-fieldsContainer-fieldset noobrocket-fieldsContainer-fieldset--split">
		<?php $this->render_settings_sections( $data['id'] ); ?>
	</div>
</div>
