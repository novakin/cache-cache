<?php
/** A complete settings group: heading, help and controls share one surface. */
defined( 'ABSPATH' ) || exit;
$is_disclosure = ! empty( $data['disclosure'] );
$configured    = false;
if ( $is_disclosure ) {
	foreach ( $data['fields'] ?? [] as $field ) {
		if ( ! empty( $field['value'] ) ) {
			$configured = true;
			break;
		}
	}
}
?>
<?php if ( $is_disclosure ) : ?>
<details class="noobrocket-settings-card noobrocket-settings-disclosure <?php echo esc_attr( $data['class'] ); ?>" aria-labelledby="noobrocket-group-<?php echo esc_attr( $data['id'] ); ?>">
	<summary class="noobrocket-optionHeader">
		<h2 class="noobrocket-title2" id="noobrocket-group-<?php echo esc_attr( $data['id'] ); ?>"><?php echo esc_html( $data['title'] ); ?></h2>
		<?php if ( $configured ) : ?><span class="noobrocket-badge is-neutral"><?php esc_html_e( 'Configured', 'noobrocket' ); ?></span><?php endif; ?>
	</summary>
<?php else : ?>
<section class="noobrocket-settings-card <?php echo esc_attr( $data['class'] ); ?>" aria-labelledby="noobrocket-group-<?php echo esc_attr( $data['id'] ); ?>">
	<header class="noobrocket-optionHeader">
		<h2 class="noobrocket-title2" id="noobrocket-group-<?php echo esc_attr( $data['id'] ); ?>"><?php echo esc_html( $data['title'] ); ?></h2>
		<?php if ( ! empty( $data['help'] ) ) : ?><a href="<?php echo esc_url( $data['help']['url'] ); ?>" data-beacon-id="<?php echo esc_attr( $data['help']['id'] ); ?>" class="noobrocket-infoAction" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Help', 'noobrocket' ); ?></a><?php endif; ?>
	</header>
<?php endif; ?>
	<div class="noobrocket-fieldsContainer <?php echo esc_attr( $data['class'] ); ?>">
		<?php if ( '' !== trim( $data['description'] ) ) : ?><div class="noobrocket-fieldsContainer-description"><?php echo $data['description']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Existing rendered description. ?></div><?php endif; ?>
		<fieldset class="noobrocket-fieldsContainer-fieldset">
			<legend class="screen-reader-text"><?php echo esc_html( $data['title'] ); ?></legend>
			<?php $this->render_settings_fields( $data['page'], $data['id'] ); ?>
		</fieldset>
		<?php if ( 'post_cleanup_section' === $data['id'] ) : ?>
		<div class="noobrocket-database-actions">
			<p><?php esc_html_e( 'Cleanup permanently removes the selected data. It does not save settings or change the schedule. Back up your database first.', 'noobrocket' ); ?></p>
			<button type="button" class="noobrocket-button noobrocket-button-danger hide-if-no-js" data-noobrocket-cleanup-review><?php esc_html_e( 'Review and run cleanup', 'noobrocket' ); ?></button>
			<div class="hide-if-js">
				<p><?php esc_html_e( 'Without JavaScript, cleanup uses saved categories only. Save category changes first, then return here to confirm cleanup.', 'noobrocket' ); ?></p>
				<label><input type="checkbox" form="noobrocket-database-cleanup" name="confirmed" value="1" required id="noobrocket-cleanup-confirmed"> <?php esc_html_e( 'I have backed up the database and confirm cleanup of the saved categories.', 'noobrocket' ); ?></label>
				<button type="submit" form="noobrocket-database-cleanup" class="noobrocket-button noobrocket-button-danger"><?php esc_html_e( 'Run saved cleanup categories', 'noobrocket' ); ?></button>
			</div>
		</div>
		<?php endif; ?>
		<?php foreach ( (array) ( $data['helper'] ?? [] ) as $noobrocket_helper ) : ?>
			<?php if ( '' === trim( (string) $noobrocket_helper ) ) { continue; } ?>
			<div class="noobrocket-fieldsContainer-helper"><?php echo $noobrocket_helper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Existing rendered helper. ?></div>
		<?php endforeach; ?>
		<?php if ( $is_disclosure && ! empty( $data['help'] ) ) : ?><div class="noobrocket-status-navigation"><a href="<?php echo esc_url( $data['help']['url'] ); ?>" data-beacon-id="<?php echo esc_attr( $data['help']['id'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Help', 'noobrocket' ); ?></a></div><?php endif; ?>
	</div>
<?php if ( $is_disclosure ) : ?></details><?php else : ?></section><?php endif; ?>
