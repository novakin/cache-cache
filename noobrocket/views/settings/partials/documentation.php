<?php
/**
 * Documentation block template.
 *
 * @since 3.0
 */

defined( 'ABSPATH' ) || exit;

?>

<div class="noobrocket-documentation">
	<i class="noobrocket-icon-book"></i>
	<h3 class="noobrocket-title2"><?php esc_html_e( 'Documentation', 'noobrocket' ); ?></h3>
	<p><?php esc_html_e( 'Read the bundled guide for setup, local generation, caching and troubleshooting.', 'noobrocket' ); ?></p>

	<?php
	$this->render_action_button(
		'link',
		'documentation',
		[
			'label'      => __( 'Read the documentation', 'noobrocket' ),
			'attributes' => [
				'target'                 => '_blank',
				'class'                  => 'noobrocket-button noobrocket-button--small noobrocket-button--blueDark',
				'data-nbr_track_help'    => 'Documentation',
				'data-nbr_track_context' => 'Sidebar',
			],
		]
	);
	?>

</div>
