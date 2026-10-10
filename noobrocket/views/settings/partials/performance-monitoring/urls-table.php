<?php
/**
 * Performance Monitoring URLs Table partial.
 *
 * @since 3.20
 *
 * @var array $data {
 *     Data for the performance monitoring URLs table.
 *
 *     @type array $items List of performance monitoring records.
 * }
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="noobrocket-optionHeader">
	<h3 class="noobrocket-title2"><?php esc_html_e( 'Performance Summary', 'noobrocket' ); ?></h3>
	<?php if ( ! empty( $data['help'] ) ) : ?>
	<a href="<?php echo esc_url( $data['help']['url'] ); ?>" data-beacon-id="<?php echo esc_attr( $data['help']['id'] ); ?>" data-nbr_track_button="Need Help" data-nbr_track_context="Addons" class="noobrocket-infoAction noobrocket-infoAction--help noobrocket-icon-help" target="_blank"><?php esc_html_e( 'Need Help?', 'noobrocket' ); ?></a>
	<?php endif; ?>
</div>

<?php
$noobrocket_insights_quota_banner_class = 'noobrocket-notice noobrocket-ri-notice noobrocket-ri-free-limit-notice';
if ( ! isset( $data['show_quota_banner'] ) || ! $data['show_quota_banner'] ) {
	$noobrocket_insights_quota_banner_class .= ' hidden';
}
?>
<div class="<?php echo esc_attr( $noobrocket_insights_quota_banner_class ); ?>" id="noobrocket-ri-quota-banner">
	<div class="noobrocket-notice-container">
		<div class="noobrocket-notice-description noobrocket-notice-70">
			<?php
			printf(
			// Translators: %1$s = opening strong tag, %2$s = closing strong tag.
				esc_html__( '%1$sCongrats!%2$s You fully enjoyed your free plan. Upgrade to keep testing, or wait for your free limit to reset.', 'noobrocket' ),
				'<strong>',
				'</strong>'
			);
			?>
		</div>
		<a class="noobrocket-notice-close" href="<?php echo esc_url( $data['upgrade_url'] ); ?>">
			<?php esc_html_e( 'Upgrade Now', 'noobrocket' ); ?>
		</a>
	</div>
</div>

<?php if ( ! empty( $data['can_add_pages'] ) && ! empty( $data['is_free'] ) && ! $data['show_quota_banner'] ) : ?>
	<p class="noobrocket-ri-summary-info">
		<?php
		printf(
		// Translators: %1$s = opening strong tag, %2$s: number of pages, %3$s = closing strong tag, %4$s: number of tests available.
			esc_html__( 'You can analyze up to %1$s%2$s pages%3$s and run %1$s%4$s tests per month%3$s. Want more?', 'noobrocket' ),
			'<strong>',
			esc_html( $data['noobrocket_insights_addon_limit'] ), // number of pages.
			'</strong>',
			esc_html( $data['noobrocket_insights_addon_limit'] ) // total number of tests available.
		);
		?>
		<a href="<?php echo esc_url( $data['upgrade_url'] ); ?>"><?php esc_html_e( 'Upgrade Now', 'noobrocket' ); ?></a>
	</p>
<?php endif; ?>


<table class="noobrocket-data-table widefat noobrocket-ri-urls-table <?php echo empty( $data['items'] ) ? 'hidden' : ''; ?>" >
	<tbody>
		<?php
		if ( ! empty( $data['items'] ) ) :
			$this->render_global_score_row( $data['global_score'] );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
			foreach ( $data['items'] as $noobrocket_insights_record ) {
				$this->render_performance_monitoring_list_row( $noobrocket_insights_record, false );
			}
			?>
		<?php endif; ?>
	</tbody>
</table>


<div class="noobrocket-ri-add-section">
	<input type="text"
			class="noobrocket-speed-radar-input"
			placeholder="<?php esc_attr_e( 'Enter a page URL to monitor', 'noobrocket' ); ?>"
			id="noobrocket-speed-radar-url-input" />

	<div id="nbr_noobrocket_insights_add_page_btn_wrapper">
		<?php
		$this->render_add_page_btn( 'performance-monitoring', $data );
		?>
	</div>
</div>
