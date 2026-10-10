<?php
/**
 * Cache lifespan block template.
 *
 * @since 3.0
 */

defined( 'ABSPATH' ) || exit;

$noobrocket_purge_interval = get_noobrocket_option( 'purge_cron_interval', 10 );
$noobrocket_purge_unit     = get_noobrocket_option( 'purge_cron_unit', 'HOUR_IN_SECONDS' );

?>

<div class="noobrocket-field--cache">
	<div class="noobrocket-cache-lifespan-row" role="group" aria-labelledby="noobrocket-cache-lifespan-label">
		<label id="noobrocket-cache-lifespan-label" for="purge_cron_interval"><?php echo esc_html( $data['label'] ); ?></label>
		<div class="noobrocket-cache-lifespan-controls">
			<input type="number" min="0" id="purge_cron_interval" name="noobrocket_settings[purge_cron_interval]" value="<?php echo esc_attr( $noobrocket_purge_interval ); ?>"<?php if ( ! empty( $data['description'] ) ) : ?> aria-describedby="noobrocket-cache-lifespan-description"<?php endif; ?>>
			<label for="purge_cron_unit" class="screen-reader-text"><?php esc_html_e( 'Cache lifespan unit', 'noobrocket' ); ?></label>
			<select id="purge_cron_unit" name="noobrocket_settings[purge_cron_unit]"<?php if ( ! empty( $data['description'] ) ) : ?> aria-describedby="noobrocket-cache-lifespan-description"<?php endif; ?>>
			<?php foreach ( $data['choices'] as $value => $label ) : // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, $noobrocket_purge_unit ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
			</select>
		</div>
	</div>
	<?php if ( ! empty( $data['description'] ) ) : ?>
	<p class="noobrocket-field-description" id="noobrocket-cache-lifespan-description">
		<?php echo $data['description']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Existing rendered field description. ?>
	</p>
	<?php endif; ?>
	<?php if ( ! empty( $data['helper'] ) ) : ?>
	<details class="noobrocket-field-disclosure noobrocket-cache-lifespan-guidance">
		<summary><?php esc_html_e( 'When to use a shorter lifespan', 'noobrocket' ); ?></summary>
		<p class="noobrocket-field-description"><?php echo $data['helper']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Existing rendered nonce guidance. ?></p>
	</details>
	<?php endif; ?>
</div>
