<?php
/**
 * Promo banner.
 *
 * @since 3.7.4
 */

defined( 'ABSPATH' ) || exit;

$data = isset( $data ) ? $data : []; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
?>
<div class="noobrocket-promo-banner" id="noobrocket-promo-banner">
	<div>
		<h3 class="noobrocket-promo-title">
			<span class="noobrocket-promo-discount">
				<?php
				// translators: %s = promotion discount percentage.
				printf( esc_html__( '%s off', 'noobrocket' ), esc_html( $data['discount_percent'] . '%' ) );
				?>
			</span>
			<?php
			// translators: %s = promotion name.
			printf( esc_html__( '%s promotion is live!', 'noobrocket' ), esc_html( $data['name'] ) );
			?>
		</h3>
		<p class="noobrocket-promo-message"><?php echo wp_kses_post( $data['message'] ); ?></p>
	</div>
	<div class="noobrocket-promo-cta-block">
		<p class="noobrocket-promo-deal"><?php esc_html_e( 'Hurry Up! Deal ends in:', 'noobrocket' ); ?></p>
		<ul class="noobrocket-promo-countdown" id="noobrocket-promo-countdown">
			<li class="noobrocket-countdown-item"><span class="noobrocket-countdown-value noobrocket-countdown-days"><?php echo esc_html( $data['countdown']['days'] ); ?></span> <?php esc_html_e( 'Days', 'noobrocket' ); ?></li>
			<li class="noobrocket-countdown-item"><span class="noobrocket-countdown-value noobrocket-countdown-hours"><?php echo esc_html( $data['countdown']['hours'] ); ?></span> <?php esc_html_e( 'Hours', 'noobrocket' ); ?></li>
			<li class="noobrocket-countdown-item"><span class="noobrocket-countdown-value noobrocket-countdown-minutes"><?php echo esc_html( $data['countdown']['minutes'] ); ?></span> <?php esc_html_e( 'Minutes', 'noobrocket' ); ?></li>
			<li class="noobrocket-countdown-item"><span class="noobrocket-countdown-value noobrocket-countdown-seconds"><?php echo esc_html( $data['countdown']['seconds'] ); ?></span> <?php esc_html_e( 'Seconds', 'noobrocket' ); ?></li>
		</ul>
		<button class="noobrocket-promo-cta noobrocket-popin-upgrade-toggle"><?php esc_html_e( 'Upgrade now', 'noobrocket' ); ?></button>
	</div>
	<button class="noobrocket-notice-close noobrocket-icon-close" id="noobrocket-dismiss-promotion"><span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice', 'noobrocket' ); ?></span></button>
</div>
