<?php
/**
 * Renewal expired banner with OCD disabled.
 *
 * @since 3.11.5
 */

defined( 'ABSPATH' ) || exit;

$data = isset( $data ) ? $data : []; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
?>
<section class="noobrocket-renewal-expired-banner" id="noobrocket-renewal-banner">
	<div class="banner-copy">
		<h3 class="noobrocket-expired-title"><?php esc_html_e( 'The Optimize CSS Delivery feature is disabled.', 'noobrocket' ); ?></h3>
		<div class="noobrocket-renewal-expired-banner-container">
			<div class="noobrocket-expired-message">
				<p>
				<?php esc_html_e( 'You can no longer use the Remove Unused CSS or Load CSS asynchronously options.', 'noobrocket' ); ?>
					<br>
					<?php
					printf(
						// translators: %1$s = <strong>, %2$s = </strong>.
						esc_html__( 'You need an %1$sactive license%2$s to keep optimizing your CSS delivery, which addresses a PageSpeed Insights recommendation and improves your page performance.', 'noobrocket' ),
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
