<?php
/**
 * Renewal soon banner.
 *
 * @since 3.7.5
 */

defined( 'ABSPATH' ) || exit;

$data = isset( $data ) ? $data : []; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
?>
<div class="noobrocket-renewal-banner">
	<ul class="noobrocket-promo-countdown" id="noobrocket-renew-countdown">
		<li class="noobrocket-countdown-item"><span class="noobrocket-countdown-value noobrocket-countdown-days"><?php echo esc_html( $data['countdown']['days'] ); ?></span> <?php esc_html_e( 'Days', 'noobrocket' ); ?></li>
		<li class="noobrocket-countdown-item"><span class="noobrocket-countdown-value noobrocket-countdown-hours"><?php echo esc_html( $data['countdown']['hours'] ); ?></span> <?php esc_html_e( 'Hours', 'noobrocket' ); ?></li>
		<li class="noobrocket-countdown-item"><span class="noobrocket-countdown-value noobrocket-countdown-minutes"><?php echo esc_html( $data['countdown']['minutes'] ); ?></span> <?php esc_html_e( 'Minutes', 'noobrocket' ); ?></li>
		<li class="noobrocket-countdown-item"><span class="noobrocket-countdown-value noobrocket-countdown-seconds"><?php echo esc_html( $data['countdown']['seconds'] ); ?></span> <?php esc_html_e( 'Seconds', 'noobrocket' ); ?></li>
	</ul>
	<div class="noobrocket-renew-message">
		<p>
			<?php
			printf(
			// translators: %1$s = <strong>, %2$s = </strong>.
				esc_html__( 'Your %1$sCache-Cache license is about to expire%2$s: you will soon lose access to product updates and support.', 'noobrocket' ),
				'<strong>',
				'</strong>'
			);
			?>
		</p>
		<?php if ( isset( $data['more_info'] ) && $data['more_info'] ) : ?>
		<p><?php echo $data['message']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
		<?php endif; ?>
	</div>
	<div class="noobrocket-renew-cta-container">
		<a href="<?php echo esc_url( $data['renewal_url'] ); ?>" class="noobrocket-renew-cta" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Renew now', 'noobrocket' ); ?></a>
	</div>
</div>
