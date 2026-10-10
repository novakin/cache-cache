<?php
/**
 * Recommendations Widget container template.
 *
 * This is the main container that wraps the different recommendation states.
 *
 * @since 3.21
 *
 * @param array $data {
 *     Data for recommendations widget.
 *
 *     @type string $state             Current state: 'loading', 'completed', 'failed', 'success'.
 *     @type array  $recommendations   List of recommendation items (for completed state).
 *     @type bool   $show_load_more    Whether to show the "Load More" button.
 * }
 */

defined( 'ABSPATH' ) || exit;

$noobrocket_ri_state           = $data['state'];
$noobrocket_ri_recommendations = $data['recommendations'];
$noobrocket_ri_show_load_more  = $data['show_load_more'];
?>
<div class="noobrocket-recommendations" data-state="<?php echo esc_attr( $noobrocket_ri_state ); ?>">
	<div class="noobrocket-recommendations__header">
		<h3 class="noobrocket-recommendations__title">
			<?php esc_html_e( 'Recommendations', 'noobrocket' ); ?>
		</h3>
	</div>

	<div class="noobrocket-recommendations__content">
		<?php
		switch ( $noobrocket_ri_state ) {
			case 'loading':
				$this->render_part( 'performance-monitoring/recommendations/states/loading' );
				break;

			case 'failed':
				$this->render_part( 'performance-monitoring/recommendations/states/failed' );
				break;

			case 'completed':
				if ( empty( $noobrocket_ri_recommendations ) ) {
					$this->render_part( 'performance-monitoring/recommendations/states/success' );
				} else {
					$this->render_parts_with_data(
						'performance-monitoring/recommendations/states/completed',
						[
							'recommendations' => $noobrocket_ri_recommendations,
							'show_load_more'  => $noobrocket_ri_show_load_more,
						]
					);
				}
				break;
		}
		?>
	</div>
</div>
