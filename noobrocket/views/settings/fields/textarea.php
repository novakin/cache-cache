<?php
/**
 * Textarea field template.
 *
 * @since 3.0
 *
 * @param array $data {
 *     Checkbox Field arguments.
 *
 *     @type string $id          Field identifier.
 *     @type string $label       Field label.
 *     @type string $value       Field value.
 *     @type string $description Field description.
 *     @type string $helper      Field helper text.
 *     @type string $placeholder Field placeholder.
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

<div class="noobrocket-field noobrocket-field--textarea <?php echo esc_attr( $data['container_class'] ); ?>"<?php echo $data['parent']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $data['parent'] escaped with esc_attr. ?>>
	<?php if ( ! empty( $data['label'] ) ) : ?>
		<label for="<?php echo esc_attr( $data['id'] ); ?>" class="<?php echo ! empty( $data['disclosure'] ) ? 'screen-reader-text' : 'noobrocket-field-description-label'; ?>"><?php echo $data['label']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $data['label'] escaped with esc_attr. ?></label>
	<?php endif; ?>
	<?php if ( ! empty( $data['description'] ) ) : ?>
		<div class="noobrocket-field-description" id="<?php echo esc_attr( $data['id'] . '-description' ); ?>">
			<?php echo $data['description']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view. ?>
		</div>
	<?php endif; ?>
	<div class="noobrocket-textarea">
		<textarea id="<?php echo esc_attr( $data['id'] ); ?>" name="noobrocket_settings[<?php echo esc_attr( $data['id'] ); ?>]"<?php if ( $noobrocket_describedby ) : ?> aria-describedby="<?php echo esc_attr( implode( ' ', $noobrocket_describedby ) ); ?>"<?php endif; ?><?php if ( ! empty( $data['labelledby'] ) ) : ?> aria-labelledby="<?php echo esc_attr( $data['labelledby'] ); ?>"<?php endif; ?> placeholder="<?php echo esc_attr( $data['placeholder'] ); ?>"><?php echo esc_textarea( $data['value'] ); ?></textarea>
	</div>
	<?php if ( ! empty( $data['helper'] ) ) : ?>
		<div class="noobrocket-field-description noobrocket-field-description-helper" id="<?php echo esc_attr( $data['id'] . '-helper' ); ?>">
			<?php echo $data['helper']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view. ?>
		</div>
	<?php endif; ?>
	<?php
		/**
		 * Fires after the display of a textarea field on NoobRocket settings page
		 *
		 * The dynamic portion of the name corresponds to the field ID
		 *
		 * @since 3.7
		 */
		do_action( "noobrocket_after_textarea_field_{$data['id']}" );
	?>
</div>
