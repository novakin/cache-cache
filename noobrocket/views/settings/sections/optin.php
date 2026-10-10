<?php
/**
 * Optin section template.
 *
 * @param array $data {
 *    @type int $current_value Current value of the optin option.
 * }
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="noobrocket-fieldsContainer">
	<fieldset class="noobrocket-fieldsContainer-fieldset">
		<div class="noobrocket-field noobrocket-field--radio">
			<div class="noobrocket-radio">
				<input type="checkbox" id="analytics_enabled" class="" name="noobrocket_mixpanel_optin" value="1" <?php checked( $data['current_value'], 1 ); ?>>
				<label for="analytics_enabled" class="">
					<span data-l10n-active="On"
						data-l10n-inactive="Off" class="noobrocket-radio-ui"></span>
					<?php esc_html_e( 'Rocket Analytics', 'noobrocket' ); ?>
				</label>
			</div>
			<div class="noobrocket-field-description">
				<?php
				// translators: %1$s = opening <a> tag, %2$s = closing </a> tag.
				printf( esc_html__( 'I agree to share anonymous data with the development team to help improve Cache-Cache. %1$sWhat info will we collect?%2$s', 'noobrocket' ), '<button class="noobrocket-js-popin">', '</button>' );
				?>
			</div>
		</div>
	</fieldset>
</div>
