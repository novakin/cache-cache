<?php
/**
 * Checkbox template.
 *
 * @since 3.0
 *
 * @param array $data {
 *     Checkbox Field arguments.
 *
 *     @type string $id          Field identifier.
 *     @type string $parent      Parent field identifier.
 *     @type string $label       Field label.
 *     @type string $value       Field value.
 *     @type string $description Field description.
 *     @type string $input_attr  Attributes for the input field.
 *     @type array  $warning {
 *         Warning panel content.
 *
 *         @type string $title        Warning title.
 *         @type string $description  Warning description.
 *         @type string $button_label Warning Button label.
 *     }
 * }
 */

defined( 'ABSPATH' ) || exit;
$noobrocket_describedby = [];
foreach ( [ 'description', 'helper' ] as $part ) {
	if ( ! empty( $data[ $part ] ) ) {
		$noobrocket_describedby[] = $data['id'] . '-' . $part;
	}
}

?>

<?php if ( ! empty( $data['warning'] ) ) : ?>
<div class="noobrocket-warningContainer">
<?php endif; ?>

	<div class="noobrocket-field noobrocket-field--checkbox <?php echo esc_attr( $data['container_class'] ); ?>"<?php echo $data['parent']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $data['parent'] escaped with esc_attr ?>>
		<div class="noobrocket-checkbox">
			<input type="checkbox" id="<?php echo esc_attr( $data['id'] ); ?>" class="" name="noobrocket_settings[<?php echo esc_attr( $data['id'] ); ?>]" value="1" <?php checked( $data['value'], 1 ); ?>
			<?php if ( $noobrocket_describedby ) : ?> aria-describedby="<?php echo esc_attr( implode( ' ', $noobrocket_describedby ) ); ?>"<?php endif; ?>
			<?php echo $data['input_attr']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view. ?>>
			<label for="<?php echo esc_attr( $data['id'] ); ?>">
				<?php echo $data['label']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view. ?>
				<?php if ( ! empty( $data['count_label'] ) ) : ?>
					<span class="noobrocket-field-count"><?php echo esc_html( $data['count_label'] ); ?></span>
				<?php endif; ?>
			</label>
			<?php if ( ! empty( $data['tooltip'] ) ) { ?>
			<div class="noobrocket-tooltip">
				<div class="noobrocket-tooltip-content">
					<?php echo esc_html( $data['tooltip'] ); ?>
				</div>
			</div>
			<?php } ?>
		</div>

		<?php if ( ! empty( $data['description'] ) ) : ?>
		<div class="noobrocket-field-description" id="<?php echo esc_attr( $data['id'] . '-description' ); ?>">
			<?php echo $data['description']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view. ?>
		</div>
		<?php endif; ?>
	</div>

<?php if ( ! empty( $data['warning'] ) ) : ?>
	<div class="noobrocket-fieldWarning">
		<div class="noobrocket-fieldWarning-title noobrocket-icon-important">
			<?php echo esc_html( $data['warning']['title'] ); ?>
		</div>
		<?php if ( isset( $data['warning']['description'] ) ) : ?>
			<div class="noobrocket-fieldWarning-description">
				<?php echo $data['warning']['description']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view. ?>
			</div>
		<?php endif; ?>
		<button type="button" class="noobrocket-button noobrocket-button--small noobrocket-button--icon noobrocket-icon-check"><?php echo esc_html( $data['warning']['button_label'] ); ?></button>
	</div>
<?php endif; ?>

<?php if ( ! empty( $data['helper'] ) ) : ?>
	<div class="noobrocket-field-description noobrocket-field-description-helper noobrocket-icon-important" id="<?php echo esc_attr( $data['id'] . '-helper' ); ?>">
		<?php echo $data['helper']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view. ?>
	</div>
<?php endif; ?>

<?php if ( ! empty( $data['warning'] ) ) : ?>
</div>
	<?php
	endif;
	/**
	 * Fires after displaying the checkbox field on the settings page
	 *
	 * @since 3.9
	 *
	 * @param string $id ID of the field.
	 */
	do_action( 'noobrocket_after_settings_checkbox', $data['id'] );
