<?php
/**
 * Licence expired notice partial.
 *
 * Renders a notice banner prompting the user to renew their NoobRocket licence
 * to continue using HostedCDN.
 *
 * @param array $data {
 *     Notice data.
 *
 *     @type string $renewal_url URL to the licence renewal page.
 *     @type bool   $is_reseller Whether the account is a reseller account.
 * }
 *
 * @since 3.22
 */

?>

<div class="noobrocket-noobrocketcdn-notice noobrocket-cdn-expired__notice noobrocket-field" id="noobrocket-cdn-licence-banner">
	<div class="noobrocket-notice-container noobrocket-flex">
		<div class="noobrocket-notice-70">
			<p>
				<?php
					printf(
						// translators: %1$s = opening <strong> tag, %2$s = closing </strong> tag.
						esc_html__( '%1$sYour Cache-Cache license has expired%2$s. Renew now to keep using HostedCDN Free.', 'noobrocket' ),
						'<strong>',
						'</strong>'
					);
					?>
			</p>
		</div>
		<?php if ( empty( $data['is_reseller'] ) ) : ?>
			<a target="_blank" rel="noopener noreferrer" class="noobrocket-noobrocketcdn-btn" href="<?php echo esc_url( $data['renewal_url'] ); ?>">
				<?php esc_html_e( 'Renew', 'noobrocket' ); ?>
			</a>
		<?php endif; ?>
	</div>
</div>
