<?php
/**
 * Enable Google Fonts template.
 *
 * @since 3.7
 */

defined( 'ABSPATH' ) || exit;

?>
<section id="noobrocket-google-fonts-enabler" class="noobrocket-settings-card noobrocket-tools-surface" aria-labelledby="noobrocket-google-fonts-title">
	<div class="noobrocket-tools">
		<div class="noobrocket-tools-col">
			<h2 id="noobrocket-google-fonts-title" class="noobrocket-title3 noobrocket-tools-label"><?php esc_html_e( 'Enable Google Font Optimization', 'noobrocket' ); ?></h2>
			<div class="noobrocket-field-description noobrocket-hide-on-click"><?php esc_html_e( 'Improves font performance and combines multiple font requests to reduce the number of HTTP requests.', 'noobrocket' ); ?></div>
			<div class="noobrocket-field-description noobrocket-hide-on-click">
				<?php
				printf(
					// translators: %1$s = opening link tag, %2$s = closing link tag.
					esc_html__( 'This is a one-time action and this button will be removed afterwards. %1$sMore info%2$s', 'noobrocket' ),
					'<a href="' . esc_url( $data['beacon']['url'] ) . '" data-beacon-article="' . esc_attr( $data['beacon']['id'] ) . '" target="_blank" rel="noopener noreferrer">',
					'</a>'
					);
				?>
			</div>
			<div class="noobrocket-field-description noobrocket-field noobrocket-isHidden noobrocket-show-on-click">
				<?php
				printf(
					// translators: %1$s = opening link tag, %2$s = closing link tag.
					esc_html__( 'Google Fonts Optimization is now enabled for your site. %1$sMore info%2$s', 'noobrocket' ),
					'<a href="' . esc_url( $data['beacon']['url'] ) . '" data-beacon-article="' . esc_attr( $data['beacon']['id'] ) . '" target="_blank" rel="noopener noreferrer">',
					'</a>'
					);
				?>
			</div>
		</div>
		<div class="noobrocket-tools-col">
			<button type="button" id="noobrocket-action-noobrocket_enable_google_fonts" class="noobrocket-button noobrocket-button--icon noobrocket-button--small noobrocket-button--purple noobrocket-icon-refresh">
				<?php esc_html_e( 'Optimize Google Fonts', 'noobrocket' ); ?>
			</button>
		</div>
	</div>
</section>
