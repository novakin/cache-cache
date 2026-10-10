<?php
/**
 * Performance Score view.
 */

defined( 'ABSPATH' ) || exit;

$noobrocket_insights_item_is_blurred = false;
$noobrocket_opening_anchor_tag       = $data['is_dashboard'] ? '<a href="' . esc_url( admin_url( 'options-general.php?page=' . NOOBROCKET_PLUGIN_SLUG ) . '#noobrocket_insights' ) . '">' : '';
$noobrocket_closing_anchor_tag       = $data['is_dashboard'] ? '</a>' : '';

?>
<div class="noobrocket-percentage-indicator">
	<?php if ( ( isset( $data['status'] ) && 'in-progress' === $data['status'] ) || ! empty( $data['is_running'] ) ) : ?>
		<div class="noobrocket-loading-container">
			<img class="noobrocket-loading-img" src="<?php echo esc_url( noobrocket_get_constant( 'NOOBROCKET_ASSETS_IMG_URL', '' ) . 'orange-loading.svg' ); ?>"/>
		</div>
	<?php elseif ( isset( $data['status'] ) && 'failed' === $data['status'] ) : ?>
		<?php echo $noobrocket_opening_anchor_tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<div class="noobrocket-percentage-circle  status-red <?php echo $noobrocket_insights_item_is_blurred ? 'blurred' : ''; ?>">
				<span class="noobrocket-failed-score noobrocket-icon-exclamation"></span>
			</div>
		<?php echo $noobrocket_closing_anchor_tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php else : ?>
		<?php echo $noobrocket_opening_anchor_tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<div class="noobrocket-percentage-circle <?php echo esc_html( $data['status-color'] ?? '' ); ?> <?php echo $noobrocket_insights_item_is_blurred ? 'blurred' : ''; ?> <?php echo 100 === $data['score'] ? 'noobrocket-centralize-100-score' : ''; ?>">
				<?php echo esc_html( $data['score'] ); ?>
			</div>
		<?php echo $noobrocket_closing_anchor_tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php endif; ?>
	<?php if ( ( isset( $data['status'] ) && 'failed' === $data['status'] ) || $noobrocket_insights_item_is_blurred ) : ?>
		<div class="noobrocket-tooltip">
			<div class="noobrocket-tooltip-content">
				<?php echo 'failed' === $data['status'] ? esc_html__( 'Something went wrong with this URL', 'noobrocket' ) : esc_html__( 'Upgrade your plan to see your score', 'noobrocket' ); ?>
			</div>
		</div>
	<?php endif; ?>
</div>
