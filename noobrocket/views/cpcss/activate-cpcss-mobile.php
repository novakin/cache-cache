<?php
/**
 * Activate CPCSS mobile template.
 *
 * @since 3.6
 */

defined( 'ABSPATH' ) || exit;

?>
<div id="noobrocket-mobile_cpcss_view" class="noobrocket-tools">
	<div class="noobrocket-tools-col">
		<div class="noobrocket-title3 noobrocket-tools-label noobrocket-icon-stack"><?php esc_html_e( 'Load CSS asynchronously for mobile', 'noobrocket' ); ?></div>
		<div class="noobrocket-field-description noobrocket-hide-on-click"><?php esc_html_e( 'Your website currently uses the same Critical Path CSS for both desktop and mobile.', 'noobrocket' ); ?></div>
		<div class="noobrocket-field-description noobrocket-hide-on-click"><?php esc_html_e( 'Click the button to enable mobile-specific CPCSS for your site.', 'noobrocket' ); ?></div>
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
				esc_html__( 'Your site is now using mobile-specific critical path CSS. %1$sMore info%2$s', 'noobrocket' ),
				'<a href="' . esc_url( $data['beacon']['url'] ) . '" data-beacon-article="' . esc_attr( $data['beacon']['id'] ) . '" target="_blank" rel="noopener noreferrer">',
				'</a>'
				);
			?>
		</div>
	</div>
	<div class="noobrocket-tools-col">
		<button id="noobrocket-action-noobrocket_enable_mobile_cpcss" class="noobrocket-button noobrocket-button--icon noobrocket-button--small noobrocket-button--purple noobrocket-icon-refresh">
			<?php esc_html_e( 'Generate Mobile Specific CPCSS', 'noobrocket' ); ?>
		</button>
	</div>
</div>
