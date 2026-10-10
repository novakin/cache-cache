<?php
/**
 * Rocket insights section template.
 *
 * @since 3.20
 *
 * @param array {
 *     Section arguments.
 *
 *     @type string $id    Page section identifier.
 *     @type string $title Page section title.
 * }
 */

defined( 'ABSPATH' ) || exit;

?>

<div id="<?php echo esc_attr( $data['id'] ); ?>" class="noobrocket-Page">
	<div class="noobrocket-sectionHeader">
		<h2 class="noobrocket-title1 noobrocket-icon-performance-monitoring-hover"><?php echo esc_html( $data['title'] ); ?></h2>
	</div>
	<div class="noobrocket-sectionHeader-description noobrocket-without-padding">
			<?php esc_html_e( 'Performance monitoring is powered by GTmetrix to run tests and generate your performance results and recommendations.', 'noobrocket' ); ?>
		</div>

	<?php do_action( 'noobrocket_insights_tab_content' ); ?>
	<?php $this->render_settings_sections( $data['id'] ); ?>
</div>

