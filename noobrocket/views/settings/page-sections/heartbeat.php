<?php
/**
 * Heartbeat section template.
 *
 * @since  3.2
 * @author Grégory Viguier
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
		<h1 class="noobrocket-title1 noobrocket-icon-heartbeat-hover" tabindex="-1"><?php echo esc_html( $data['title'] ); ?></h1>
	</div>
	<?php $this->render_settings_sections( $data['id'] ); ?>
</div>
