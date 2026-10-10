<?php
/**
 * Critical path CSS section in NoobRocket metabox.
 *
 * @since 3.6
 *
 * @data array {
 *     Data to populate the template.
 *
 *     @type string $disabled_description Description to explain why the buttons are disabled.
 * }
 */

defined( 'ABSPATH' ) || exit;

$noobrocket_disabled_description = empty( $data['disabled_description'] );
?>
<div class="inside">
	<h3><?php esc_html_e( 'Critical Path CSS', 'noobrocket' ); ?></h3>
	<div id="noobrocket-metabox-cpcss-content">
		<?php do_action( 'noobrocket_metabox_cpcss_content' ); ?>
	</div>
</div>
<div id="cpcss_response_notice" class="components-notice is-notice is-warning<?php echo $noobrocket_disabled_description ? ' hidden' : ''; ?>">
	<div class="components-notice__content">
		<?php if ( ! $noobrocket_disabled_description ) : ?>
			<p><?php echo esc_html( $data['disabled_description'] ); ?></p>
		<?php endif; ?>
	</div>
</div>
