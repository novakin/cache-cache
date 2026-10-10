<?php
/**
 * Textarea field with container template.
 *
 * @since 3.22
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

?>

<div class="noobrocket-fieldsContainer <?php echo esc_attr( $data['class'] ); ?>">
	<fieldset class="noobrocket-fieldsContainer-fieldset">
		<div class="noobrocket-field noobrocket-field--textarea"<?php echo $data['parent']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $data['parent'] escaped with esc_attr. ?>>
			<?php if ( ! empty( $data['label'] ) ) : ?>
				<label for="<?php echo esc_attr( $data['id'] ); ?>" class="noobrocket-field-description-label"><?php echo $data['label']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $data['label'] escaped with esc_attr. ?></label>
			<?php endif; ?>
			<?php if ( ! empty( $data['description'] ) ) : ?>
				<div class="noobrocket-field-description">
					<?php echo $data['description']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view. ?>
				</div>
			<?php endif; ?>
			<div class="noobrocket-textarea">
				<textarea id="<?php echo esc_attr( $data['id'] ); ?>" name="noobrocket_settings[<?php echo esc_attr( $data['id'] ); ?>]" placeholder="<?php echo esc_attr( $data['placeholder'] ); ?>"><?php echo esc_textarea( $data['value'] ); ?></textarea>
			</div>
			<?php if ( ! empty( $data['helper'] ) ) : ?>
				<div class="noobrocket-field-description noobrocket-field-description-helper">
					<?php echo $data['helper']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view. ?>
				</div>
			<?php endif; ?>
		</div>
	</fieldset>
</div>


