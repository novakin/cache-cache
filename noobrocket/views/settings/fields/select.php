<?php
/**
 * Select field template.
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
 *     @type array  $choices {
 *          Option choices.
 *
 *          @type string $value Option value.
 *          @type string $label Option label.
 *     }
 * }
 */

defined( 'ABSPATH' ) || exit;

if ( 'local_css_mode' === $data['id'] ) :
	$mode_titles = [ 'off' => __( 'Original CSS', 'noobrocket' ), 'critical' => __( 'Critical CSS', 'noobrocket' ), 'used' => __( 'Used CSS', 'noobrocket' ) ];
	$mode_descriptions = [ 'off' => __( 'Keep your existing stylesheets.', 'noobrocket' ), 'critical' => __( 'Prioritize styles for the first screen.', 'noobrocket' ), 'used' => __( 'Generate styles used by each page.', 'noobrocket' ) ];
?>
<div class="noobrocket-field noobrocket-css-mode"<?php echo $data['parent']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Existing escaped parent attributes. ?>>
	<fieldset id="local_css_mode" class="noobrocket-mode-fieldset" aria-describedby="local_css_mode-requirements local_css_mode-description">
		<legend class="noobrocket-field-description-label"><?php echo esc_html( $data['label'] ); ?></legend>
		<div class="noobrocket-mode-grid">
			<?php foreach ( $data['choices'] as $value => $label ) : ?>
			<label class="noobrocket-mode-option" for="noobrocket-css-<?php echo esc_attr( $value ); ?>">
				<input id="noobrocket-css-<?php echo esc_attr( $value ); ?>" type="radio" name="noobrocket_settings[local_css_mode]" value="<?php echo esc_attr( $value ); ?>" <?php checked( $value, $data['value'] ); ?>>
				<span><strong><?php echo esc_html( $mode_titles[ $value ] ?? $label ); ?></strong><span><?php echo esc_html( $mode_descriptions[ $value ] ?? '' ); ?></span></span>
			</label>
			<?php endforeach; ?>
		</div>
	</fieldset>
	<div class="noobrocket-css-readiness" id="local_css_mode-requirements">
		<p><?php esc_html_e( 'Critical CSS and Used CSS need Node.js, Chromium and a scheduled worker on your WordPress server.', 'noobrocket' ); ?> <?php
			// translators: %1$s = opening link tag, %2$s = closing link tag.
			printf( __( '%1$sLocal CSS setup instructions%2$s', 'noobrocket' ), '<a href="' . esc_url( plugins_url( 'docs/index.html#css', NOOBROCKET_FILE ) ) . '" target="_blank" rel="noopener noreferrer">', '</a>' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Only escaped URL and translated link label are emitted.
		?></p>
	</div>
	<?php if ( ! empty( $data['description'] ) ) : ?><div class="noobrocket-field-description" id="local_css_mode-description"><?php echo $data['description']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Existing rendered description. ?></div><?php endif; ?>
</div>
<?php
	return;
endif;
?>

<div class="noobrocket-field noobrocket-field--select <?php echo esc_attr( $data['container_class'] ); ?>"<?php echo $data['parent']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $data['parent'] escaped with esc_attr. ?>>
	<div class="noobrocket-select">
		<label for="<?php echo esc_attr( $data['id'] ); ?>"><?php echo esc_html( $data['label'] ); ?></label>
		<select id="<?php echo esc_attr( $data['id'] ); ?>" name="noobrocket_settings[<?php echo esc_attr( $data['id'] ); ?>]"<?php if ( ! empty( $data['description'] ) ) : ?> aria-describedby="<?php echo esc_attr( $data['id'] . '-description' ); ?>"<?php endif; ?><?php echo $data['input_attr']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view. ?>>
		<?php foreach ( $data['choices'] as $value => $label ) : // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound ?>
			<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, $data['value'] ); ?>><?php echo esc_html( $label ); ?></option>
		<?php endforeach; ?>
		</select>
	</div>

	<?php if ( ! empty( $data['tooltip'] ) ) { ?>
		<div class="noobrocket-tooltip">
			<div class="noobrocket-tooltip-content">
				<?php echo esc_html( $data['tooltip'] ); ?>
			</div>
		</div>
	<?php } ?>

	<?php if ( ! empty( $data['description'] ) ) : ?>
		<div class="noobrocket-field-description" id="<?php echo esc_attr( $data['id'] . '-description' ); ?>">
			<?php echo $data['description']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view. ?>
		</div>
	<?php endif; ?>
</div>
