<?php
/**
 * Banned website banner.
 *
 * @since 3.20.4
 */

defined( 'ABSPATH' ) || exit;

$data = isset( $data ) ? $data : []; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
?>
<section class="noobrocket-renewal-expired-banner revoked-website-banner" id="noobrocket-renewal-banner">
	<div class="banner-copy">
		<h3 class="noobrocket-expired-title"><?php esc_html_e( 'Your Cache-Cache license has been revoked!', 'noobrocket' ); ?></h3>
		<div class="noobrocket-renewal-expired-banner-container">
			<div class="noobrocket-expired-message">
				<?php if ( ! empty( $data['message'] ) ) : ?>
				<p><?php echo $data['message']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<div class="noobrocket-expired-cta-container">
		<a href="<?php echo esc_url( $data['purchase_url'] ); ?>" class="noobrocket-renew-cta" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Cache-Cache is free', 'noobrocket' ); ?></a>
	</div>
</section>
