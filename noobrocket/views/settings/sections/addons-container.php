<?php
/**
 * Settings fields container template.
 *
 * @since 3.0
 *
 * @param array $data {
 *     Fields container data.
 *
 *     @type string $id          Section identifier.
 *     @type string $title       Section title.
 *     @type string $description Section description.
 *     @type string $page        Page section identifier.
 * }
 */

defined( 'ABSPATH' ) || exit;

?>

<section class="noobrocket-settings-card noobrocket-integration-group" aria-labelledby="noobrocket-group-<?php echo esc_attr( $data['id'] ); ?>">
<header class="noobrocket-optionHeader">
	<h2 class="noobrocket-title2" id="noobrocket-group-<?php echo esc_attr( $data['id'] ); ?>"><?php echo esc_html( $data['title'] ); ?></h2>
	<?php if ( ! empty( $data['help'] ) ) : ?>
	<button type="button" data-beacon-id="<?php echo esc_attr( $data['help'] ); ?>" data-nbr_track_button="Need Help" data-nbr_track_context="Addons" class="noobrocket-infoAction noobrocket-infoAction--help noobrocket-icon-help"><?php esc_html_e( 'Need Help?', 'noobrocket' ); ?></button>
	<?php endif; ?>
</header>

<div class="noobrocket-fieldsContainer">
	<div class="noobrocket-fieldsContainer-description">
		<?php echo esc_html( $data['description'] ); ?>
	</div>
	<?php $this->render_settings_fields( $data['page'], $data['id'] ); ?>
</div>
</section>
