<?php
/**
 * Text template.
 *
 * @since 3.0
 *
 * @param array $data {
 *     Checkbox Field arguments.
 *
 *     @type string $id              Field identifier.
 *     @type string $parent          Parent field identifier.
 *     @type string $label           Field label.
 *     @type string $container_class Field container class.
 *     @type string $value           Field value.
 *     @type string $description     Field description.
 *     @type string $input_attr      Attributes for the input field.
 *     @type bool   $secret          Whether to conceal this field by default.
 * }
 */

defined( 'ABSPATH' ) || exit;
$noobrocket_describedby = [];
$noobrocket_secret     = ! empty( $data['secret'] );
$noobrocket_input_type = $noobrocket_secret ? 'password' : ( 'cloudflare_email' === $data['id'] ? 'email' : 'text' );
if ( $noobrocket_secret ) {
	// translators: %s is the credential field label.
	$noobrocket_show_label = sprintf( __( 'Show %s', 'noobrocket' ), $data['label'] );
	// translators: %s is the credential field label.
	$noobrocket_hide_label = sprintf( __( 'Hide %s', 'noobrocket' ), $data['label'] );
}
foreach ( [ 'description', 'helper' ] as $part ) {
	if ( ! empty( $data[ $part ] ) ) {
		$noobrocket_describedby[] = $data['id'] . '-' . $part;
	}
}

?>

<div class="noobrocket-field noobrocket-field--text <?php echo esc_attr( $data['container_class'] ); ?>">
	<div class="noobrocket-text">
		<label for="<?php echo esc_attr( $data['id'] ); ?>"><?php echo esc_html( $data['label'] ); ?></label>
		<?php if ( $noobrocket_secret ) : ?><div class="noobrocket-secret-field"><?php endif; ?>
		<input type="<?php echo esc_attr( $noobrocket_input_type ); ?>" id="<?php echo esc_attr( $data['id'] ); ?>" class="" name="noobrocket_settings[<?php echo esc_attr( $data['id'] ); ?>]" value="<?php echo esc_attr( $data['value'] ); ?>"<?php if ( $noobrocket_describedby ) : ?> aria-describedby="<?php echo esc_attr( implode( ' ', $noobrocket_describedby ) ); ?>"<?php endif; ?><?php echo $data['input_attr']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view. ?>>
		<?php if ( $noobrocket_secret ) : ?>
			<button type="button" class="noobrocket-secret-toggle hide-if-no-js" data-noobrocket-secret-toggle
				aria-controls="<?php echo esc_attr( $data['id'] ); ?>" aria-pressed="false"
				data-show="<?php esc_attr_e( 'Show', 'noobrocket' ); ?>" data-hide="<?php esc_attr_e( 'Hide', 'noobrocket' ); ?>"
				data-show-label="<?php echo esc_attr( $noobrocket_show_label ); ?>" data-hide-label="<?php echo esc_attr( $noobrocket_hide_label ); ?>"
				aria-label="<?php echo esc_attr( $noobrocket_show_label ); ?>"><?php esc_html_e( 'Show', 'noobrocket' ); ?></button>
		</div>
		<?php endif; ?>
		<?php if ( ! empty( $data['description'] ) ) : ?>
		<div class="noobrocket-field-description" id="<?php echo esc_attr( $data['id'] . '-description' ); ?>">
			<?php echo $data['description']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view. ?>
		</div>
		<?php endif; ?>
		<?php if ( ! empty( $data['helper'] ) ) : ?>
		<div class="noobrocket-field-description noobrocket-field-description-helper" id="<?php echo esc_attr( $data['id'] . '-helper' ); ?>">
			<?php echo $data['helper']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view. ?>
		</div>
		<?php endif; ?>
	</div>
</div>
