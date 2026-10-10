<?php
/**
 * Deactivation intent form template.
 *
 * @since 3.0
 *
 * $data array {
 *     Data to populate the form.
 *
 *     @type string $form_action URL to submit the deactivation form.
 * }
 */

defined( 'ABSPATH' ) || exit;

?>
<div class="noobrocket-modal" id="noobrocket-deactivation-modal" aria-hidden="true">
	<div class="noobrocket-modal-overlay" tabindex="-1" data-micromodal-close>
		<div class="noobrocket-modal-container" role="dialog" aria-modal="true" aria-labelledby="noobrocket-deactivation-modal-title" >
			<header class="noobrocket-modal-header">
				<h2 class="noobrocket-modal-title" id="noobrocket-deactivation-modal-title"><?php esc_html_e( 'Facing an issue?', 'noobrocket' ); ?></h2>
			</header>
			<div>
				<p><?php esc_html_e( 'It is not always necessary to deactivate Cache-Cache when facing any issues. Most of them can be fixed by deactivating only some options.', 'noobrocket' ); ?></p>
				<p>
					<?php
					printf(
						// translators: %1$s = opening strong tag, %2$s = closing strong tag.
						esc_html__( 'Our advice? Instead of deactivating Cache-Cache, use our %1$sSafe Mode%2$s to quickly disable LazyLoad, File Optimization, and CDN options. Then check to see if your issue is resolved.', 'noobrocket' ),
						'<strong>',
						'</strong>'
					);
					?>
				</p>
				<p><strong><?php esc_html_e( 'Do you want to use our Safe Mode to troubleshoot Cache-Cache?', 'noobrocket' ); ?></strong></p>
				<form id="noobrocket-deactivation-intent-form" method="post" action="
				<?php
					echo esc_attr( $data['form_action'] );
				?>
				">
					<input name="rest_auth_nonce" type="hidden" value="
					<?php
					echo esc_attr(
						wp_create_nonce( 'wp_rest' )
					);
					?>
					" />
					<ul>
						<li>
							<input type="radio" id="safe_mode" value="safe_mode" name="mode" checked />
							<label for="safe_mode">
								<?php
								printf(
									// translators: %1$s = opening strong tag, %2$s = closing strong tag.
									esc_html__( 'Yes, apply "%1$sSafe Mode%2$s"', 'noobrocket' ),
									'<strong>',
									'</strong>'
								);
								?>
							</label>
						</li>
						<li class="noobrocket-sub-list">
							<input type="checkbox" id="export_settings" name="export_settings" checked="checked" />
							<label for="export_settings">
								<?php
								printf(
										// translators: %1$s = opening strong tag, %2$s = closing strong tag.
										esc_html__( 'and export Cache-Cache settings %1$s(Recommended as current settings will be deleted)%2$s', 'noobrocket' ),
								'<strong>',
									'</strong>'
								);
								?>
							</label>
						</li>
						<li>
							<input type="radio" id="deactivate" value="deactivate" name="mode" /><label for="deactivate"><?php esc_html_e( 'No, deactivate and snooze this message for', 'noobrocket' ); ?></label>
							<select name="snooze">
								<option value="1"><?php esc_html_e( '1 day', 'noobrocket' ); ?></option>
								<option value="7"><?php esc_html_e( '7 days', 'noobrocket' ); ?></option>
								<option value="30"><?php esc_html_e( '30 days', 'noobrocket' ); ?></option>
								<option value="0"><?php esc_html_e( 'Forever', 'noobrocket' ); ?></option>
							</select>
						</li>
					</ul>
					<?php wp_nonce_field( 'noobrocket_deactivation' ); ?>
					<div class="noobrocket-modal-footer">
						<button aria-label="Close modal" class="noobrocket-modal-button noobrocket-modal-cancel" data-micromodal-close><?php esc_html_e( 'Cancel', 'noobrocket' ); ?></button>
						<input type="submit" class="noobrocket-modal-button noobrocket-modal-confirm" value="<?php esc_attr_e( 'Confirm', 'noobrocket' ); ?>" />
					</div>
				</form>
			</div>
		</div>
	</div>
</div>
