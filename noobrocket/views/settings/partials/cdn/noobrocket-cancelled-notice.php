<?php
/**
 * Pro user cancelled notice.
 *
 * Renders a notice banner informing the user that HostedCDN is being cancelled.
 *
 * @since 3.23.3
 */

?>

<div class="noobrocket-noobrocketcdn-notice noobrocket-cdn-expired__notice" id="noobrocket-cdn-cancelled-banner">
	<div class="noobrocket-notice-container">
		<div class="noobrocket-notice-70">
			<p>
			<?php
				printf(
					// translators: %1$s = opening <strong> tag, %2$s = closing </strong> tag.
					esc_html__( '%1$sHostedCDN Pro subscription is being cancelled.%2$s HostedCDN Pro can’t be reactivated until the process is complete.', 'noobrocket' ),
					'<strong>',
					'</strong>'
				);
				?>
			</p>
		</div>
	</div>
</div>
