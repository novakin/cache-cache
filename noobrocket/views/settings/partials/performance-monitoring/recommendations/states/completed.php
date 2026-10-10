<?php
/**
 * Recommendations Completed State template.
 *
 * Shown when recommendations have been fetched and there are items to display.
 *
 * @since 3.21
 *
 * @param array $data {
 *     Data for completed state.
 *
 *     @type array $recommendations List of recommendation items.
 *     @type bool  $show_load_more  Whether to show the "Load More" button.
 * }
 */

defined( 'ABSPATH' ) || exit;

$noobrocket_ri_recommendations = $data['recommendations'];
$noobrocket_ri_show_load_more  = $data['show_load_more'];
?>
<div class="noobrocket-recommendations__list">
	<?php
	foreach ( $noobrocket_ri_recommendations as $noobrocket_ri_recommendation ) :
		$this->render_parts_with_data(
			'performance-monitoring/recommendations/item',
			$noobrocket_ri_recommendation
		);
	endforeach;
	?>
</div>

<?php if ( $noobrocket_ri_show_load_more ) : ?>
	<button type="button" class="noobrocket-recommendations__load-more" id="noobrocket-recommendations-load-more">
		<span class="noobrocket-recommendations__load-more-text">
			<?php esc_html_e( 'More Recommendations', 'noobrocket' ); ?>
		</span>
		<span class="noobrocket-recommendations__load-more-text">
			<?php esc_html_e( 'Less Recommendations', 'noobrocket' ); ?>
		</span>
	</button>
<?php endif; ?>
