<?php
/**
 * Reseller-banned licence notice partial.
 *
 * Renders a notice banner informing the user that HostedCDN has been disabled
 * because their (reseller) license was banned. Unlike the expired-license
 * notice, this partial has no renewal CTA — a reseller customer cannot
 * self-resolve a ban.
 *
 * @since 3.23.1
 */

?>

<div class="noobrocket-noobrocketcdn-notice noobrocket-cdn-expired__notice noobrocket-cdn-banned__notice" id="noobrocket-cdn-banned-banner">
	<div class="noobrocket-notice-container">
		<div class="noobrocket-notice-70">
			<p>
			<?php
				printf(
					// translators: %1$s = opening <strong> tag, %2$s = closing </strong> tag.
					esc_html__( '%1$sYour Cache-Cache license has been banned%2$s. Contact support for more information.', 'noobrocket' ),
					'<strong>',
					'</strong>'
				);
				?>
			</p>
		</div>
	</div>
</div>
