<?php
/**
 * Rocket Insight Tab "Add Page" button template.
 */

defined( 'ABSPATH' ) || exit;

$noobrocket_insights_add_page_button_args = [
	'label'      => __( 'Add page', 'noobrocket' ),
	'attributes' => [
		'class'       => 'noobrocket-button noobrocket-button--icon noobrocket-button--small noobrocket-button--purple noobrocket-ri-add-url-button',
		'id'          => 'add_page_speed_radar',
		'data-source' => 'add-on page',
	],
	'icon'       => [
		'data' => '<span class="noobrocket-icon-plus"></span>',
	],
];
if ( $data['reach_max_url'] ) {
	$noobrocket_insights_add_page_button_args['attributes']['class']   .= ' noobrocket-btn-with-tool-tip disabled';
	$noobrocket_insights_add_page_button_args['attributes']['disabled'] = 'disabled';
	$noobrocket_insights_add_page_button_args['url']                    = '#';
	$noobrocket_insights_add_page_button_args['tooltip']                = esc_html__( 'You have reached your maximum page limit', 'noobrocket' );
}
$this->render_action_button(
	'button',
	'add_page_speed_radar',
	$noobrocket_insights_add_page_button_args
);
