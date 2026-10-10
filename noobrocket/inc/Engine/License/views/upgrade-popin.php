<?php
/**
 * Upgrade section template.
 *
 * @since 3.7.3
 *
 * @var array $data
 * @var object $this
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="noobrocket-Popin noobrocket-Popin-Upgrade">
	<div class="noobrocket-Popin-header">
		<h2 class="noobrocket-title1"><?php esc_html_e( 'Speed Up More Websites', 'noobrocket' ); ?></h2>
		<button class="noobrocket-Popin-close noobrocket-Popin-Upgrade-close noobrocket-icon-close"></button>
	</div>
	<div class="noobrocket-Popin-content">
		<p>
		<?php
		// translators: %1$s = opening strong tag, %2$s = closing strong tag.
		printf( esc_html__( 'You can use Cache-Cache on more websites by upgrading your license. To upgrade, simply pay the %1$sprice difference%2$s between your current and new licenses, as shown below.', 'noobrocket' ), '<strong>', '</strong>' );
		?>
		</p>
		<p>
		<?php
		// translators: %1$s = opening strong tag, %2$s = closing strong tag.
		printf( esc_html__( '%1$sN.B.%2$s: Upgrading your license does not change your expiration date', 'noobrocket' ), '<strong>', '</strong>' );
		?>
		</p>
		<div class="noobrocket-Popin-flex">
			<?php
			foreach ( $data['upgrades'] as $noobrocket_upgrade_type => $noobrocket_upgrade ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $this->generate(
					'upgrade-item',
					[
						'type'            => $noobrocket_upgrade_type, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						'item'            => $noobrocket_upgrade, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						'is_promo_active' => $data['is_promo_active'], // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					]
				);
			}
			?>
		</div>
	</div>
</div>
