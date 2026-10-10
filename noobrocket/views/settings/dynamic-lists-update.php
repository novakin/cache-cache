<?php
/**
 * Dynamic lists update template.
 *
 * @since 3.11.4
 */

defined( 'ABSPATH' ) || exit;

?>

<div class="noobrocket-tools">
	<div class="noobrocket-tools-col">
		<div class="noobrocket-title3 noobrocket-tools-label noobrocket-icon-export"><?php esc_html_e( 'Update Inclusion and Exclusion Lists', 'noobrocket' ); ?></div>
		<div class="noobrocket-field-description">
			<?php
			printf(
				// translators: %1$s = opening link tag, %2$s = closing link tag.
				esc_html__( 'Compatibility lists are downloaded automatically every week. Click the button if you want to update them manually. %1$sMore info%2$s', 'noobrocket' ),
				'<a href="' . esc_url( $data['beacon']['url'] ) . '" data-beacon-article="' . esc_attr( $data['beacon']['id'] ) . '" target="_blank" rel="noopener noreferrer">',
				'</a>'
			);
			?>
		</div>
		<div id="noobrocket-update-exclusion-msg" class="noobrocket-field-description"></div>
	</div>
	<div class="noobrocket-tools-col">
		<button id="noobrocket-update-exclusion-list" class="noobrocket-button noobrocket-button--icon noobrocket-button--small noobrocket-button--purple noobrocket-icon-refresh">
			<?php esc_html_e( 'Update lists', 'noobrocket' ); ?>
		</button>
	</div>
</div>
