<?php
/**
 * Provider integration block template.
 *
 * @since 3.0
 *
 * @param array $data {
 *     Checkbox Field arguments.
 *
 *     @type string $id          Field identifier.
 *     @type string $label       Add-on label.
 *     @type string $title       Add-on title.
 *     @type string $description Add-on description.
 *     @type string $logo        Add-on logo.
 *     @type string $value       Field value.
 * }
 */

defined( 'ABSPATH' ) || exit;

$noobrocket_settings_page = ! empty( $data['settings_page'] ) ? $data['settings_page'] : '';
?>

<fieldset class="noobrocket-fieldsContainer-fieldset">
	<div class="add-on-container noobrocket-integration-row <?php echo esc_attr( $data['container_class'] ); ?>">
	<div class="noobrocket-field">
		<div class="noobrocket-flex">
			<div class="noobrocket-integration-identity">
			<h3 class="noobrocket-title3 noobrocket-addon-heading" id="<?php echo esc_attr( $data['id'] . '-heading' ); ?>">
				<span class="noobrocket-integration-brand" aria-hidden="true"><img src="<?php echo esc_url( $data['logo']['url'] ); ?>" width="<?php echo esc_attr( $data['logo']['width'] ); ?>" height="<?php echo esc_attr( $data['logo']['height'] ); ?>" alt=""></span>
				<?php echo esc_html( $data['label'] ); ?>
			</h3>
			</div>
			<?php
			$noobrocket_default = true;
			// This filter is documented in one-click-addon.php.
			$noobrocket_display = apply_filters( 'noobrocket_display_input_' . $data['id'], $noobrocket_default );

			if ( ! is_bool( $noobrocket_display ) ) {
				$noobrocket_display = $noobrocket_default;
			}

			if ( $noobrocket_display ) :
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
		<div class="noobrocket-status-navigation">
			<?php if ( $noobrocket_settings_page ) : ?>
				<a href="#<?php echo esc_attr( $noobrocket_settings_page ); ?>" class="noobrocket-integration-setup noobrocket-toggle-button noobrocket-<?php echo esc_attr( $noobrocket_settings_page ); ?>ToggleButton"><?php esc_html_e( 'Configure connection', 'noobrocket' ); ?></a>
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
				<?php if ( ! empty( $data['helper'] ) ) : ?>
					<details class="noobrocket-integration-evidence"><summary><?php esc_html_e( 'Compatibility details', 'noobrocket' ); ?></summary>
					<div class="noobrocket-field-helper">
						<?php echo $data['helper']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view. ?>
					</div>
					</details>
				<?php endif; ?>
			</div>
		</div>
	</div>
	</div>
</fieldset>
