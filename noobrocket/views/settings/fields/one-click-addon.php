<?php
/**
 * One-click add-on block template.
 *
 * @since 3.0
 *
 * @param array $data {
 *     Checkbox Field arguments.
 *
 * @type string $id Field identifier.
 * @type string $label Add-on label.
 * @type string $title Add-on title.
 * @type string $description Add-on description.
 * @type string $logo Add-on logo URL.
 * @type string $value Field value.
 * }
 */

defined( 'ABSPATH' ) || exit;
$noobrocket_addon_icons = [ 'cache_logged_user' => 'cache', 'varnish_auto_purge' => 'server', 'cache_webp' => 'image' ];

?>
<fieldset class="noobrocket-fieldsContainer-fieldset">

	<div class="add-on-container noobrocket-integration-row <?php echo esc_attr( $data['container_class'] ); ?>">
		<div class="noobrocket-field">
			<div class="noobrocket-flex">
				<h3 class="noobrocket-title3 noobrocket-addon-heading" id="<?php echo esc_attr( $data['id'] . '-heading' ); ?>">
					<?php if ( isset( $noobrocket_addon_icons[ $data['id'] ] ) ) : ?>
						<svg class="noobrocket-icon noobrocket-addon-symbol" aria-hidden="true"><use href="#nbr-icon-<?php echo esc_attr( $noobrocket_addon_icons[ $data['id'] ] ); ?>"/></svg>
					<?php endif; ?>
					<?php echo esc_html( $data['label'] ); ?>
				</h3>
				<?php
				/**
				 * Filters the display of the input
				 *
				 * @since 3.0
				 * @author Remy Perona
				 *
				 * @param bool $display True to display, false otherwise.
				 */
				if ( apply_filters( 'noobrocket_display_input_' . $data['id'], true ) ) :
					?>
					<div class="noobrocket-radio noobrocket-radio--reverse">
						<input type="checkbox" id="<?php echo esc_attr( $data['id'] ); ?>" aria-labelledby="<?php echo esc_attr( $data['id'] . '-heading' ); ?>" aria-describedby="<?php echo esc_attr( $data['id'] . '-description' ); ?>" class="" name="noobrocket_settings[<?php echo esc_attr( $data['id'] ); ?>]" value="1" <?php checked( $data['value'], 1 ); ?> <?php echo $data['input_attr'];//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view. ?>>
						<label for="<?php echo esc_attr( $data['id'] ); ?>" class="">
							<span data-l10n-active="On" data-l10n-inactive="Off" class="noobrocket-radio-ui"></span>
						<span class="screen-reader-text"><?php esc_html_e( 'Add-on status', 'noobrocket' ); ?></span>
						</label>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<div class="noobrocket-field noobrocket-addon">
			<div class="noobrocket-flex">
				<div class="noobrocket-addon-text" id="<?php echo esc_attr( $data['id'] . '-description' ); ?>">
					<?php if ( ! empty( $data['title'] ) ) : ?>
						<div class="noobrocket-addon-title">
							<?php echo esc_attr( $data['title'] ); ?>
						</div>
					<?php endif; ?>
					<?php if ( ! empty( $data['description'] ) ) : ?>
						<div class="noobrocket-field-description">
							<?php echo $data['description']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view. ?>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
</fieldset>
