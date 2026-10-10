<?php
/**
 * Clean section template.
 *
 * @data array {
 *     Data to populate the template.
 *
 *     @type string $action Link action.
 *     @type string $title  Section title.
 *     @type string $label  Button label.
 * }
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="noobrocket-field">
	<h4 class="noobrocket-title3"><?php echo esc_html( $data['title'] ); ?></h4>
	<?php if ( ! empty( $data['description'] ) ) { ?>
		<p><?php echo esc_html( $data['description'] ); ?></p>
	<?php } ?>
	<?php
	$this->render_action_button(
			'link',
			$data['action'],
			[
				'label'      => $data['label'],
				'attributes' => [
					'class' => 'noobrocket-button noobrocket-button--icon noobrocket-button--no-min-width noobrocket-button--small noobrocket-icon-trash',
				],
			]
	);
	?>
</div>
