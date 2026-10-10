<?php
/**
 * Single Recommendation Item template.
 *
 * @since 3.21
 *
 * @param array $data {
 *     Data for a single recommendation item.
 *
 *     @type string $option_slug    Option slug identifier (e.g., 'delay_js').
 *     @type string $title          Recommendation title.
 *     @type string $description    Description text explaining the recommendation.
 *     @type string $learn_more_url URL for "More info" link.
 *     @type string $icon_slug      Icon identifier for the recommendation.
 *     @type array  $impact_tags    Array of impact tags with values (e.g., ['LCP' => 100, 'TBT' => 25]).
 *     @type int    $priority       Priority order for the recommendation.
 * }
 */

defined( 'ABSPATH' ) || exit;

$noobrocket_option_slug    = $data['option_slug'];
$noobrocket_title          = $data['title'];
$noobrocket_description    = $data['description'];
$noobrocket_learn_more_url = $data['learn_more_url'];
$noobrocket_icon_slug      = $data['icon_slug'];
$noobrocket_impact_tags    = $data['impact_tags'];
?>
<div class="noobrocket-recommendation-item">
	<div class="noobrocket-recommendation-item__inner">
		<div class="noobrocket-recommendation-item__wrapper">
			<div class="noobrocket-recommendation-item__header">
				<div class="noobrocket-recommendation-item__title-row">
					<span class="noobrocket-recommendation-item__title">
						<?php echo esc_html( $noobrocket_title ); ?>
					</span>
					<?php
					$this->render_action_button(
						'link',
						'',
						[
							'label'      => __( 'Activate', 'noobrocket' ),
							'url'        => esc_url( $data['section'] ),
							'attributes' => [
								'class'               => 'noobrocket-recommendation-item__activate',
								'data-recommendation' => esc_attr( $noobrocket_option_slug ),
							],
						]
					);
					?>
				</div>

				<?php if ( ! empty( $noobrocket_impact_tags ) ) : ?>
					<div class="noobrocket-recommendation-item__impact">
						<span class="noobrocket-recommendation-item__impact-label">
							<?php esc_html_e( 'Impact on', 'noobrocket' ); ?>
						</span>
						<div class="noobrocket-recommendation-item__impact-tags">
							<?php foreach ( $noobrocket_impact_tags as $noobrocket_metric => $noobrocket_value ) : ?>
								<span class="noobrocket-impact-tag" data-impact-value="<?php echo esc_attr( $noobrocket_value ); ?>">
									<?php echo esc_html( $noobrocket_metric ); ?>
								</span>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>
			</div>

			<p class="noobrocket-recommendation-item__description">
				<?php echo esc_html( $noobrocket_description ); ?>
				<?php if ( ! empty( $noobrocket_learn_more_url ) ) : ?>
					<a href="<?php echo esc_url( $noobrocket_learn_more_url ); ?>"
						target="_blank"
						rel="noopener noreferrer"
						class="noobrocket-recommendation-item__more-info">
						<?php esc_html_e( 'More info', 'noobrocket' ); ?>
					</a>
				<?php endif; ?>
			</p>
		</div>
	</div>
</div>
