<?php
/**
 * Mobile Cache Template.
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="noobrocket-tools">
	<div class="noobrocket-tools-col">
		<div class="noobrocket-title3 noobrocket-tools-label noobrocket-icon-export"><?php esc_html_e( 'Mobile Cache', 'noobrocket' ); ?></div>
		<div class="noobrocket-field-description" id="nbr_mobile_cache_default">
			<?php
			echo esc_html__( 'Speed your site for mobile visitors.', 'noobrocket' );
			?>
			<br>
			<?php
			printf(
				// translators: %1$s = opening link tag, %2$s = closing link tag.
				esc_html__( 'This is a one-time action and this button will be removed afterwards. %1$sMore info%2$s', 'noobrocket' ),
				'<a href="' . esc_url( $data['url'] ) . '" data-beacon-article="' . esc_attr( $data['id'] ) . '" target="_blank" rel="noopener noreferrer">',
				'</a>'
			);
			?>
		</div>
		<div class="noobrocket-field-description noobrocket-field noobrocket-isHidden" id="nbr_mobile_cache_response">
			<?php
			echo esc_html__( 'Mobile Cache is now enabled for your site.', 'noobrocket' );
			?>
		</div>
	</div>
	<div class="noobrocket-tools-col">
		<button id="nbr_enable_mobile_cache" class="noobrocket-button noobrocket-button--icon noobrocket-button--small noobrocket-button--purple noobrocket-icon-refresh">
			<?php esc_html_e( 'Enable Mobile Cache', 'noobrocket' ); ?>
		</button>
	</div>
</div>
