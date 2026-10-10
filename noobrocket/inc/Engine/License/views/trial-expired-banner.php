<?php
/**
 * Trial expired banner.
 *
 * @since 3.23.5
 */

defined( 'ABSPATH' ) || exit;

$data = isset( $data ) ? $data : []; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
?>
<section class="noobrocket-renewal-expired-banner" id="noobrocket-renewal-banner">
	<div class="banner-copy">
		<h3 class="noobrocket-expired-title"><?php esc_html_e( 'Your Cache-Cache trial has ended', 'noobrocket' ); ?></h3>
		<div class="noobrocket-renewal-expired-banner-container">
			<div class="noobrocket-expired-message">
				<p>
					<?php esc_html_e( 'Your site is no longer optimized, and key features have stopped working. You\'ve also lost plugin updates and support. Buy Cache-Cache now to get it all back.', 'noobrocket' ); ?>
				</p>
			</div>
		</div>
	</div>
	<div class="noobrocket-expired-cta-container">
		<a href="<?php echo esc_url( $data['renewal_url'] ); ?>" class="noobrocket-renew-cta" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Get Cache-Cache back', 'noobrocket' ); ?></a>
	</div>

	<button class="noobrocket-notice-close noobrocket-icon-close" id="noobrocket-dismiss-renewal"><span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice', 'noobrocket' ); ?></span></button>
</section>
