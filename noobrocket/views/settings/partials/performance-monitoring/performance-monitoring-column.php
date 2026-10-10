<?php
/**
 * Performance monitoring column content for post listing pages.
 *
 * @since 3.20.1
 *
 * @var array $data {
 *     Template data.
 *
 *     @type string      $data['nbr_noobrocket_insights_url'] The URL of the post.
 *     @type object|null $data['nbr_noobrocket_row']          Database row object for the URL (null if not tracked).
 *     @type bool        $data['nbr_has_credit']          Whether the user has credit available.
 *     @type bool        $data['nbr_can_add_pages']       Whether the user can add more pages (based on plan limits).
 *     @type bool        $data['nbr_is_free_user']        Whether the user is on the free plan.
 *     @type bool        $data['is_draft']                Whether the post is a draft.
 *     @type int         $data['nbr_post_id']             The ID of the post.
 * }
 */

defined( 'ABSPATH' ) || exit;

// If row doesn't exist, show "Test the page" link.
if ( null === $data['nbr_noobrocket_row'] ) :
	// For not-tracked rows always render the button.
	// The click handler will decide whether to show the limit message or proceed.
	?>
	<div class="noobrocket-ri-column noobrocket-ri-not-tracked" data-url="<?php echo esc_attr( $data['nbr_noobrocket_insights_url'] ); ?>" data-has-credit="<?php echo esc_attr( $data['nbr_has_credit'] ? '1' : '0' ); ?>" data-can-add-pages="<?php echo esc_attr( $data['nbr_can_add_pages'] ? '1' : '0' ); ?>" data-post-id="<?php echo esc_attr( $data['nbr_post_id'] ); ?>">
		<?php if ( $data['is_draft'] ) : ?>
			<div class="noobrocket-btn-with-tool-tip">
		<?php endif; ?>
		<button 
			type="button"
			class="noobrocket-ri-test-page <?php echo $data['is_draft'] ? 'noobrocket-ri-no-credit' : ''; ?>"
			data-url="<?php echo esc_attr( $data['nbr_noobrocket_insights_url'] ); ?>"
			data-post-id="<?php echo esc_attr( $data['nbr_post_id'] ); ?>"
			<?php echo $data['is_draft'] ? 'disabled' : ''; ?>
		>
			<?php esc_html_e( 'Test the page', 'noobrocket' ); ?>
		</button>
		<?php if ( $data['is_draft'] ) : ?>
				<div class="noobrocket-tooltip">
					<div class="noobrocket-tooltip-content">
						<?php esc_html_e( 'This page is a draft and cannot be tested.', 'noobrocket' ); ?>
					</div>
				</div>
			</div>
		<?php endif; ?>
		<?php // Store the limit message HTML hidden in the column for per-row usage by JS. ?>
		<div class="noobrocket-ri-limit-html" style="display: none;">
			<?php echo wp_kses_post( $data['nbr_limit_reached_message'] ); ?>
		</div>

		<div class="noobrocket-ri-message" style="display: none;"></div>
	</div>
	<?php
	return;
endif;

?>

<div class="noobrocket-ri-column" data-performance-monitoring-id="<?php echo esc_attr( $data['nbr_noobrocket_row']->id ); ?>" data-url="<?php echo esc_attr( $data['nbr_noobrocket_insights_url'] ); ?>" data-has-credit="<?php echo esc_attr( $data['nbr_has_credit'] ? '1' : '0' ); ?>" data-can-add-pages="<?php echo esc_attr( $data['nbr_can_add_pages'] && ! $data['is_draft'] ? '1' : '0' ); ?>" data-post-id="<?php echo esc_attr( $data['nbr_post_id'] ); ?>">
	<?php
	// Helper: always render the re-test button (JS will handle credit checks on click).
	$render_retest_button = function () use ( $data ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
		?>
		<button type="button" class="noobrocket-ri-retest-link noobrocket-icon-bold-refresh" data-url="<?php echo esc_attr( $data['nbr_noobrocket_insights_url'] ); ?>" data-post-id="<?php echo esc_attr( $data['nbr_post_id'] ); ?>" data-source="re-test post type listing">
			<?php esc_html_e( 'Re-test', 'noobrocket' ); ?>
		</button>
		<?php
	};

	// We keep the credit message HTML available (hidden) for JS to show it on click
	// for the specific row only. Do not render it on page load.
	$render_credit_message = function () use ( $data ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
		?>
		<div class="noobrocket-ri-limit-html" style="display: none;">
			<?php echo wp_kses_post( $data['nbr_limit_reached_message'] ); ?>
		</div>
		<?php
	};
	?>
	<?php if ( $data['nbr_is_running'] ) : ?>
		<!-- Loading state -->
		<div class="noobrocket-ri-loading noobrocket-btn-with-tool-tip">
			<img class="noobrocket-loading-img" src="<?php echo esc_url( noobrocket_get_constant( 'NOOBROCKET_ASSETS_IMG_URL', '' ) . 'orange-loading.svg' ); ?>" alt="<?php esc_attr_e( 'Loading...', 'noobrocket' ); ?>"/>
			<div class="noobrocket-tooltip">
				<div class="noobrocket-tooltip-content">
					<?php echo esc_html__( 'Analyzing your page (~1 min).', 'noobrocket' ); ?>
				</div>
			</div>
		</div>
	<?php elseif ( $data['nbr_has_results'] ) : ?>
		<!-- Results state -->
		<?php if ( $data['nbr_is_blurred'] ) : ?>
			<!-- Blurred score - show score with tooltip and actions (Re-test clickable, View Details disabled with tooltip) -->
			<div class="noobrocket-ri-blurred">
				<div class="noobrocket-btn-with-tool-tip">
					<?php $this->render_performance_score( $data['nbr_score_data'] ); ?>
					<div class="noobrocket-tooltip">
						<div class="noobrocket-tooltip-content">
							<?php
							esc_html_e( 'Upgrade your plan to see your score', 'noobrocket' );
							?>
						</div>
					</div>
				</div>
				
				<div class="noobrocket-ri-actions-wrapper">
					<?php
					$render_retest_button();
					?>
					<div class="noobrocket-btn-with-tool-tip">
						<span class="noobrocket-ri-view-details-link noobrocket-icon-report noobrocket-ri-disabled">
							<?php esc_html_e( 'View Details', 'noobrocket' ); ?>
						</span>
						<div class="noobrocket-tooltip">
							<div class="noobrocket-tooltip-content">
								<?php esc_html_e( 'Upgrade to view detailed metrics', 'noobrocket' ); ?>
							</div>
						</div>
					</div>
					<?php
					$render_credit_message();
					?>
				</div>
			</div>
		<?php else : ?>
			<!-- Normal score with actions -->
			<div class="noobrocket-ri-score-wrapper noobrocket-btn-with-tool-tip">
				<?php $this->render_performance_score( $data['nbr_score_data'] ); ?>
				<div class="noobrocket-tooltip">
					<div class="noobrocket-tooltip-content">
						<?php
						// translators: %s = human-readable time difference (e.g., "5 minutes").
						printf( esc_html__( 'Tested %s ago', 'noobrocket' ), esc_html( human_time_diff( $data['nbr_noobrocket_row']->modified, time() ) ) );
						?>
					</div>
				</div>
			</div>
			
			<div class="noobrocket-ri-actions-wrapper">
				<?php
				$render_retest_button();

				// View details link - only show if test is completed successfully.
				if ( ! empty( $data['nbr_noobrocket_row']->id ) && $data['nbr_can_access_report'] ) :
					?>
					<a href="<?php echo esc_url( $data['nbr_view_details_url'] ); ?>" class="noobrocket-ri-view-details-link noobrocket-icon-report" target="_blank" rel="noopener" data-performance-monitoring-id="<?php echo esc_attr( $data['nbr_noobrocket_row']->id ); ?>">
						<?php esc_html_e( 'View Details', 'noobrocket' ); ?>
					</a>
				<?php else : ?>
					<span class="noobrocket-ri-view-details-link noobrocket-icon-report noobrocket-ri-disabled">
						<?php esc_html_e( 'View Details', 'noobrocket' ); ?>
					</span>
					<?php
				endif;

				$render_credit_message();
				?>
			</div>
		<?php endif; ?>
	<?php else : ?>
		<!-- Failed or unknown state -->
		<div class="noobrocket-ri-score-wrapper">
			<div class="noobrocket-btn-with-tool-tip">
				<div class="noobrocket-percentage-indicator">
					<div class="noobrocket-percentage-circle status-red">
						<span class="noobrocket-failed-score noobrocket-icon-exclamation"></span>
					</div>
				</div>
				<div class="noobrocket-tooltip">
					<div class="noobrocket-tooltip-content">
						<?php esc_html_e( 'Something went wrong with this URL', 'noobrocket' ); ?>
					</div>
				</div>
			</div>
		</div>
		
		<div class="noobrocket-ri-actions-wrapper">
			<?php
			$render_retest_button();
			?>
			<div class="noobrocket-btn-with-tool-tip">
				<span class="noobrocket-ri-view-details-link noobrocket-icon-report noobrocket-ri-disabled">
					<?php esc_html_e( 'View Details', 'noobrocket' ); ?>
				</span>
				<div class="noobrocket-tooltip">
					<div class="noobrocket-tooltip-content">
						<?php esc_html_e( 'Test failed - no details available', 'noobrocket' ); ?>
					</div>
				</div>
			</div>
			<?php $render_credit_message(); ?>
		</div>
	<?php endif; ?>
	
	<div class="noobrocket-ri-message" style="display: none;"></div>
</div>