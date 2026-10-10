<?php
/**
 * Renewal expired banner.
 *
 * @since 3.7.5
 */

defined( 'ABSPATH' ) || exit;

$data = isset( $data ) ? $data : []; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
?>
<section class="noobrocket-renewal-expired-banner" id="noobrocket-renewal-banner">
	<div class="banner-copy">
		<h3 class="noobrocket-expired-title"><?php esc_html_e( 'Your Cache-Cache license is expired!', 'noobrocket' ); ?></h3>
		<div class="noobrocket-renewal-expired-banner-container">
			<div class="noobrocket-expired-message">
				<p>
					<?php
					printf(
					// translators: %1$s = <strong>, %2$s = </strong>.
						esc_html__( 'Your website could be much faster if it could take advantage of our %1$snew features and enhancements%2$s. 🚀', 'noobrocket' ),
						'<strong>',
						'</strong>'
					);
					?>
				</p>
				<?php if ( ! empty( $data['message'] ) ) : ?>
				<p><?php echo $data['message']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<div class="noobrocket-expired-cta-container">
		<a href="<?php echo esc_url( $data['renewal_url'] ); ?>" class="noobrocket-renew-cta" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Renew now', 'noobrocket' ); ?></a>
	</div>

	<button class="noobrocket-notice-close noobrocket-icon-close" id="noobrocket-dismiss-renewal"><span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice', 'noobrocket' ); ?></span></button>
</section>
