<?php
/**
 * Pro manual retry notice partial.
 *
 * Renders a notice banner prompting the user to manually retry their cdn pro activation
 * to continue using HostedCDN.
 *
 * @param array $data {
 *     Notice data.
 *
 *     @type string $retry_url URL to the retry the pro activation.
 * }
 *
 * @since 3.23.4
 */

?>

<div class="noobrocket-noobrocketcdn-notice noobrocket-cdn-expired__notice noobrocket-field" id="noobrocket-cdn-licence-banner">
	<div class="noobrocket-notice-container noobrocket-flex">
		<div class="noobrocket-notice-70">
			<p>
				<?php
					printf(
						// translators: %1$s = opening <strong> tag, %2$s = closing </strong> tag.
						esc_html__( '%1$sOops, we couldn\'t confirm your HostedCDN Pro subscription%2$s. No worries: let\'s do a manual check.', 'noobrocket' ),
						'<strong>',
						'</strong>'
					);
					?>
			</p>
		</div>
		
		<a target="_blank" id="noobrocket-noobrocketcdn-retry-pro-detection" rel="noopener noreferrer" class="noobrocket-noobrocketcdn-btn noobrocket-noobrocketcdn-btn_retry" href="<?php echo esc_url( $data['retry_url'] ); ?>">
			<?php esc_html_e( 'Retry', 'noobrocket' ); ?>
		</a>
	
	</div>
</div>
