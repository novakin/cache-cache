<?php
/**
 * Number field template.
 *
 * @since 3.0
 *
 * @param array $data {
 *     Checkbox Field arguments.
 *
 *     @type string $id              Field identifier.
 *     @type string $label           Field label.
 *     @type string $container_class Field container class.
 *     @type string $value           Field value.
 *     @type string $description     Field description.
 * }
 */

defined( 'ABSPATH' ) || exit;

?>

<div class="noobrocket-field noobrocket-field--text noobrocket-field--number <?php echo esc_attr( $data['container_class'] ); ?>">
	<div class="noobrocket-text noobrocket-text--number">
		<label for="<?php echo esc_attr( $data['id'] ); ?>"><?php echo $data['label']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view. ?></label>
		<input type="number" id="<?php echo esc_attr( $data['id'] ); ?>" name="noobrocket_settings[<?php echo esc_attr( $data['id'] ); ?>]" value="<?php echo esc_attr( $data['value'] ); ?>"<?php if ( ! empty( $data['description'] ) ) : ?> aria-describedby="<?php echo esc_attr( $data['id'] . '-description' ); ?>"<?php endif; ?>>
	</div>

	<?php if ( ! empty( $data['description'] ) ) : ?>
	<div class="noobrocket-field-description" id="<?php echo esc_attr( $data['id'] . '-description' ); ?>">
		<?php echo $data['description']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view. ?>
	</div>
	<?php endif; ?>
</div>
