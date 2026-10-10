<?php
/**
 * CDN status indicator partial.
 *
 * @since 3.22
 *
 * @param array $data {
 *     Status data.
 *
 *     @type bool   $is_active          Whether CDN is active.
 *     @type string $status_text        Main status text.
 *     @type string $details            Details text (edge locations, pages covered).
 *     @type string $paused_status_text Status text when CDN is paused.
 *     @type string $class              CSS class for the status indicator.
 *     @type bool   $is_subscription_loading  Whether the subscription is currently loading.
 *     @type bool   $is_paused  Whether the CDN is paused.
 * }
 */

defined( 'ABSPATH' ) || exit;

$noobrocket_details                 = isset( $data['details'] ) ? $data['details'] : '';
$noobrocket_class                   = isset( $data['class'] ) ? $data['class'] : '';
$noobrocket_is_subscription_loading = isset( $data['is_subscription_loading'] ) ? $data['is_subscription_loading'] : false;

if ( ! $data['is_active'] ) {
	return;
}
?>

<div class="noobrocket-cdn-status <?php echo esc_attr( $noobrocket_class ); ?>"
	data-active-details="<?php echo esc_attr( $noobrocket_details ); ?>"
	data-long-details="<?php echo strlen( $noobrocket_details ) > 120 ? '1' : '0'; ?>"
	id="nbr_cdn_status_indicator"
>
	<div class="noobrocket-cdn-indicator">
		<div class="noobrocket-cdn-indicator__info">
			<?php if ( '' !== $data['status_text'] ) : ?>
				<div class="noobrocket-cdn-indicator__status">
					<?php if ( $noobrocket_is_subscription_loading ) : ?>
						<span class="noobrocket-icon-orange-loader"></span>
					<?php endif; ?>
					<span class="noobrocket-cdn-indicator__text"><?php echo esc_html( $data['status_text'] ); ?></span>
				</div>
			<?php endif; ?>
		</div>
		<?php if ( ! empty( $noobrocket_details ) ) : ?>
			<p class="noobrocket-cdn-indicator__details">
				<?php echo $noobrocket_details; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view. ?>
			</p>
		<?php endif; ?>
	</div>
</div>
