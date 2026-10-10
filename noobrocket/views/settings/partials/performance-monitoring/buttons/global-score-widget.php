<?php
/**
 * Global score widget "Add Page" button template.
 */

defined( 'ABSPATH' ) || exit;

$noobrocket_insights_add_button_args = [
	'label'      => $data['pages_num'] ? __( 'Add Pages', 'noobrocket' ) : __( 'Add Homepage', 'noobrocket' ),
	'parameters' => [
		'type' => 'all',
	],
	'url'        => '#noobrocket_insights',
	'attributes' => [
		'class'       => 'noobrocket-button noobrocket-button--icon noobrocket-button--small noobrocket-button--purple noobrocket-icon-plus noobrocket-button--no-min-width noobrocket-ri-add-url-button noobrocket-ri-global-score-add-url-button',
		'data-source' => 'dashboard',
	],
];

// Add tooltip if reach max URL and disable btn.
if ( $data['reach_max_url'] ) {
	$noobrocket_insights_add_button_args['url']                    = '';
	$noobrocket_insights_add_button_args['attributes']['class']   .= ' noobrocket-btn-with-tool-tip disabled';
	$noobrocket_insights_add_button_args['attributes']['disabled'] = 'disabled';
	$noobrocket_insights_add_button_args['tooltip']                = esc_html__( 'You have reached your maximum page limit', 'noobrocket' );
}

$this->render_action_button(
	'link',
	$data['pages_num'] ? '' : 'noobrocket_noobrocket_insights_add_homepage',
	$noobrocket_insights_add_button_args
);
