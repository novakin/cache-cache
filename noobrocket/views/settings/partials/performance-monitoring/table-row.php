<?php
/**
 * Performance monitor row.
 *
 * @since 3.20
 */

defined( 'ABSPATH' ) || exit;

$noobrocket_data_array                 = (array) $data;
$noobrocket_data_array['is_running']   = $data->is_running();
$noobrocket_data_array['is_dashboard'] = false;
$noobrocket_ri_blurred                 = '';
$noobrocket_img_url                    = esc_url( NOOBROCKET_ASSETS_IMG_URL );

// Get pre-formatted metrics from Render class.
$noobrocket_formatted_metrics = $data->formatted_metrics ?? [];
?>
<tr class="noobrocket-ri-item noobrocket-ri-item-result <?php echo esc_attr( $noobrocket_data_array['item_classes']['row'] ); ?>" data-performance-monitoring-id="<?php echo esc_attr( $data->id ); ?>" >
	<td class="noobrocket-ri-item-toggle <?php echo esc_attr( $noobrocket_data_array['item_classes']['td'] ); ?>">
		<div class="icon-frame noobrocket-ri-item-toggle-single <?php echo ! $noobrocket_data_array['noobrocket_can_show_advanced_indicators'] ? 'hide' : ''; ?>">
		</div>
	</td>
	<td class="noobrocket-ri-item-score">
		<?php
		$this->render_performance_score( $noobrocket_data_array );
		?>
	</td>
	<td class="noobrocket-ri-item-title">
		<?php
		$noobrocket_css_class = $this->is_title_truncated( $data->title ) ? 'noobrocket-btn-with-tool-tip' : '';
		?>
		<a href="<?php echo esc_url( $data->url ); ?>" target="_blank" rel="noopener" class="<?php echo esc_attr( $noobrocket_css_class ); ?>">
			<span class="noobrocket-ri-title">
				<span class="noobrocket-ri-title-truncate">
					<?php echo esc_html( $data->title ); ?>
				</span>
			</span>
			<span class="noobrocket-ri-dot">&middot;</span>
			<span class="noobrocket-ri-date">
				<?php
				if ( $data->is_running() ) {
					echo esc_html( __( 'Analyzing your page (~1 min)', 'noobrocket' ) );
				} else {
					echo esc_html( human_time_diff( $data->modified, time() ) . ' ' . __( 'ago', 'noobrocket' ) );
				}
				?></span><?php // phpcs:ignore Squiz.PHP.EmbeddedPhp.ContentAfterEnd ?>
			<?php if ( '' !== $noobrocket_css_class ) : ?>
			<div class="noobrocket-tooltip">
				<div class="noobrocket-tooltip-content">
					<?php echo esc_html( $data->title ); ?>
				</div>
			</div>
			<?php endif; ?>
		</a>
	</td>
	<td class="noobrocket-ri-item-actions <?php echo esc_attr( $noobrocket_data_array['item_classes']['td'] ); ?>">
		<?php
		$noobrocket_insights_retest_button_args = [
			'label'      => __( 'Re-Test', 'noobrocket' ),
			'attributes' => [
				'class'       => 'noobrocket-icon-bold-refresh noobrocket-ri-action noobrocket-action-speed_radar_refresh',
				'aria-label'  => __( 'Re-test', 'noobrocket' ),
				'data-source' => 're-test add-on page',
			],
		];

		// Retest button should be disabled if the score is zero or this row is still running.
		if ( $data->is_running() || ! $data->has_credit ) {
			$noobrocket_insights_retest_button_args['attributes']['class']   .= ' noobrocket-ri-action--disabled';
			$noobrocket_insights_retest_button_args['attributes']['disabled'] = true;
		}

		if ( ! $data->has_credit ) {
			$noobrocket_insights_retest_button_args['attributes']['class']   .= ' noobrocket-btn-with-tool-tip';
			$noobrocket_insights_retest_button_args['tooltip']                = __( 'You’ve reached your free monthly plan limit. Upgrade now to unlock unlimited on-demand tests.', 'noobrocket' );
			$noobrocket_insights_retest_button_args['attributes']['disabled'] = true;
		}

		$this->render_action_button(
			'button',
			'speed_radar_refresh',
			$noobrocket_insights_retest_button_args
		);

		$noobrocket_insights_show_report_btn_args = [
			'label'      => __( 'See GTmetrix Report', 'noobrocket' ),
			'url'        => $data->report_url,
			'attributes' => [
				'target'                      => '_blank',
				'class'                       => 'noobrocket-ri-action noobrocket-ri-report',
				'data-performance-monitoring-row-id' => $data->id,
			],
		];
		$noobrocket_report_url_icon_state         = '';
		if ( empty( $data->report_url ) ) {
			$noobrocket_report_url_icon_state                                 = 'noobrocket-ri-action--disabled';
			$noobrocket_insights_show_report_btn_args['attributes']['class'] .= ' ' . $noobrocket_report_url_icon_state;
			$noobrocket_insights_show_report_btn_args['attributes']['target'] = '';
			$noobrocket_insights_show_report_btn_args['url']                  = '';
		} elseif ( ! $data->can_access_report() ) {
			$noobrocket_insights_show_report_btn_args['attributes']['class'] .= ' noobrocket-btn-with-tool-tip noobrocket-ri-action--disabled';
			$noobrocket_insights_show_report_btn_args['attributes']['target'] = '';
			$noobrocket_insights_show_report_btn_args['tooltip']              = __( 'Upgrade your plan to see the report', 'noobrocket' );
			$noobrocket_insights_show_report_btn_args['url']                  = '';
		}

		$this->render_action_button(
			'link',
			'speed_radar_delete',
			[
				'label'      => '',
				'url'        => $data->delete_url(),
				'attributes' => [
					'class'      => 'noobrocket-btn-with-tool-tip noobrocket-icon-trash noobrocket-ri-action',
					'aria-label' => __( 'Delete', 'noobrocket' ),
				],
			]
		);
		?>
	</td>
</tr>
<?php if ( $noobrocket_data_array['noobrocket_can_show_advanced_indicators'] ) : ?>
<tr class="noobrocket-ri-details <?php echo esc_attr( $noobrocket_data_array['details_classes']['row'] ); ?>" id="ri_details_<?php echo $noobrocket_data_array['id']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view. ?>">
	<td colspan="4" class="details-section-td <?php echo esc_attr( $noobrocket_data_array['details_classes']['td'] ); ?>">
		<div class="details-section">
			<div class="details-header">
				<div>
					<div class="metrics-header">
						<div class="metric-label">
							<p>LCP</p>
							<div class="info-icon">
								<img src="<?php echo $noobrocket_img_url;// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>ri-info.svg" alt="">
								<div class="noobrocket-tooltip">
									<div class="noobrocket-tooltip-content">
										<?php echo esc_html__( 'Time until the largest visible content element renders and the main content becomes visible.', 'noobrocket' ); ?>
									</div>
								</div>
							</div>
						</div>
						<div class="metric-label">
							<p>TBT</p>
							<div class="info-icon">
								<img src="<?php echo $noobrocket_img_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>ri-info.svg" alt="">
								<div class="noobrocket-tooltip">
									<div class="noobrocket-tooltip-content">
										<?php echo esc_html__( 'Total time the main thread is blocked before the page becomes interactive during loading.', 'noobrocket' ); ?>
									</div>
								</div>
							</div>
						</div>
						<div class="metric-label">
							<p>CLS</p>
							<div class="info-icon">
								<img src="<?php echo $noobrocket_img_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>ri-info.svg" alt="">
								<div class="noobrocket-tooltip">
									<div class="noobrocket-tooltip-content">
										<?php echo esc_html__( 'Total amount of unexpected layout shifts during page loading, affecting visual stability.', 'noobrocket' ); ?>
									</div>
								</div>
							</div>
						</div>
						<div class="metric-label">
							<p>TTFB</p>
							<div class="info-icon">
								<img src="<?php echo $noobrocket_img_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>ri-info.svg" alt="">
								<div class="noobrocket-tooltip">
									<div class="noobrocket-tooltip-content">
										<?php echo esc_html__( 'Time from the request until the server responds, determining how soon the page starts loading.', 'noobrocket' ); ?>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div class="details-content">
				<div class="detail-row">
					<div class="row-right">
						<div class="metric-values <?php echo esc_attr( $noobrocket_ri_blurred ); ?>">
							<?php foreach ( $noobrocket_formatted_metrics as $noobrocket_metric ) : ?>
								<div class="metric-value <?php echo esc_attr( $noobrocket_metric['class'] ); ?>">
									<p><?php echo esc_html( $noobrocket_metric['formatted'] ); ?></p>
									<?php if ( '' !== $noobrocket_ri_blurred ) : ?>
									<div class="noobrocket-tooltip">
										<div class="noobrocket-tooltip-content">
											<?php echo esc_html__( 'Upgrade your plan to see more details.', 'noobrocket' ); ?>
										</div>
									</div>
									<?php endif; ?>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			</div>
			<div class="row-left">
				<div class="report-link <?php echo esc_attr( $noobrocket_report_url_icon_state ); ?>">
					<?php
					$this->render_action_button(
						'link',
						'gtmetrix_open',
						$noobrocket_insights_show_report_btn_args
					);
					?>
					<div class="icon-frame"></div>
				</div>
			</div>
		</div>
	</td>
</tr>
<?php endif; ?>
