<?php
/**
 * Sliding checkbox template.
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
 * }
 */

defined( 'ABSPATH' ) || exit;

?>

<div class="noobrocket-field noobrocket-field--radio <?php echo esc_attr( $data['container_class'] ); ?>">
	<div class="noobrocket-radio">
		<input type="checkbox" id="<?php echo esc_attr( $data['id'] ); ?>" class="" name="noobrocket_settings[<?php echo esc_attr( $data['id'] ); ?>]" value="1" <?php checked( $data['value'], 1 ); ?><?php if ( ! empty( $data['description'] ) ) : ?> aria-describedby="<?php echo esc_attr( $data['id'] . '-description' ); ?>"<?php endif; ?>>
		<label for="<?php echo esc_attr( $data['id'] ); ?>" class="">
			<span data-l10n-active="On"
				data-l10n-inactive="Off" class="noobrocket-radio-ui"></span>
			<?php echo esc_html( $data['label'] ); ?>
		</label>
	</div>

	<?php if ( ! empty( $data['description'] ) ) : ?>
		<div class="noobrocket-field-description" id="<?php echo esc_attr( $data['id'] . '-description' ); ?>">
			<?php echo $data['description']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view. ?>
		</div>
	<?php endif; ?>
</div>
