<?php
/**
 * Global Score Widget view.
 */

defined( 'ABSPATH' ) || exit;

$context = isset( $data['context'] ) ? $data['context'] : 'sidebar'; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
?>

<div class="noobrocket-global-score-widget-wrapper" data-context="<?php echo esc_attr( $context ); ?>">
	<div class="noobrocket-optionHeader">
		<h3 class="noobrocket-title2">
			<?php echo esc_html__( 'Performance monitoring Score', 'noobrocket' ); ?>
		</h3>
	</div>
	<div class="noobrocket-fieldsContainer">
		<fieldset class="noobrocket-fieldsContainer-fieldset">
			<div class="noobrocket-field noobrocket-global-score-widget">
				<?php $this->render_parts_with_data( 'performance-monitoring/global-score-widget-content', $data ); ?>
			</div>
		</fieldset>
	</div>
</div>
