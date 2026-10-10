<?php
/**
 * Select field template.
 *
 * @since 3.10
 *
 * @param array $data {
 *     Radio buttons  arguments.
 *
 *     @type string $id              Field identifier.
 *     @type string $label           Field label.
 *     @type string $container_class Field container class.
 *     @type string $value           Field value.
 *     @type string $description     Field description.
 *     @type array  $options {
 *          Option options.
 *
 *          @type string $description Option value.
 *          @type string $label Option label.
 *          @type array  $sub_fields fields to show when option is selected.
 *     }
 * }
 */

defined( 'ABSPATH' ) || exit;

?>
<div id = '<?php echo esc_attr( $data['id'] ); ?>' class="noobrocket-field noobrocket-radio-buttons <?php echo esc_attr( $data['container_class'] ); ?>" data-default="<?php echo ( ! empty( $data['default'] ) ? 'noobrocket-radio-' . esc_attr( $data['default'] ) : '' ); ?>" <?php echo $data['parent']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $data['parent'] escaped with esc_attr. ?>>
	<div class="noobrocket-radio-buttons-container">
		<?php foreach ( $data['options'] as $value => $option ) : // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound ?>
			<button id ="noobrocket-radio-<?php echo esc_attr( $value ); ?>" class="noobrocket-button noobrocket-button--gray <?php echo ( $value === $data['value'] ? 'radio-active' : '' ); ?> <?php echo ( ! empty( $option['warning'] ) ? 'has-warning' : '' ); ?>"
					data-value="<?php echo esc_attr( $value ); ?>"
					<?php echo ( ! empty( $option['disabled'] ) ? 'disabled=' . esc_attr( $option['disabled'] ) : '' ); ?>
					>
				<?php echo esc_html( $option['label'] ); ?>
			</button>
		<?php endforeach; ?>
	</div>
	<?php foreach ( $data['options'] as $value => $option ) : // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound ?>
		<?php if ( ! empty( $option['warning'] ) ) : ?>
			<div class="noobrocket-fieldWarning noobrocket-radio-warning" data-parent="noobrocket-radio-<?php echo esc_attr( $value ); ?>">
				<div class="noobrocket-fieldWarning-title noobrocket-icon-important">
					<?php echo esc_html( $option['warning']['title'] ); ?>
				</div>
				<?php if ( isset( $option['warning']['description'] ) ) : ?>
					<div class="noobrocket-fieldWarning-description">
						<?php echo $option['warning']['description']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view. ?>
					</div>
				<?php endif; ?>
				<button type="button" class="noobrocket-button noobrocket-button--small noobrocket-button--icon noobrocket-icon-check"><?php echo esc_html( $option['warning']['button_label'] ); ?></button>
			</div>
		<?php endif; ?>

	<div class="noobrocket-extra-fields-container noobrocket-field--children no-space <?php echo ( $value === $data['value'] ? 'noobrocket-isOpen' : '' ); ?>" data-parent="noobrocket-radio-<?php echo esc_attr( $value ); ?>">

		<div class="noobrocket-field-description">
			<?php if ( ! empty( $option['description'] ) ) : ?>
				<div class="noobrocket-field-description ">
					<?php echo $option['description']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view. ?>
				</div>
			<?php endif; ?>
		</div>

		<?php
		do_action(
			'noobrocket_after_settings_radio_options',
			[
				'option_id'  => $value,
				'sub_fields' => $option['sub_fields'],
			]
		);
		?>

	</div>
	<?php endforeach; ?>
</div>
