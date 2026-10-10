<?php
/**
 * Post edit metabox options.
 *
 * @data array {
 *     Data to populate the template.
 *
 *     @type bool $excluded_url Whether the current URL is excluded from cache or not.
 *     @type array $fields Array of arrays to populate the fields.
 * }
 */

defined( 'ABSPATH' ) || exit;

wp_nonce_field( 'noobrocket_box_option', '_noobrocketnonce', false, true );
?>

<div class="misc-pub-section">
	<input name="noobrocket_post_nocache" id="noobrocket_post_nocache" type="checkbox" title="<?php esc_html_e( 'Never cache this page', 'noobrocket' ); ?>" <?php checked( $data['excluded_url'], true ); ?>><label for="noobrocket_post_nocache"><?php esc_html_e( 'Never cache this page', 'noobrocket' ); ?></label>
</div>

<div class="misc-pub-section">
	<p><?php esc_html_e( 'Activate these options on this post:', 'noobrocket' ); ?></p>
	<?php
	foreach ( $data['fields'] as $noobrocket_field ) {
		?>
		<input name="noobrocket_post_exclude_hidden[<?php echo esc_attr( $noobrocket_field['id'] ); ?>]" type="hidden" value="on">
		<input name="noobrocket_post_exclude[<?php echo esc_attr( $noobrocket_field['id'] ); ?>]" id="noobrocket_post_exclude_<?php echo esc_attr( $noobrocket_field['id'] ); ?>" type="checkbox"<?php echo $noobrocket_field['title']; ?><?php echo $noobrocket_field['checked']; ?><?php echo $noobrocket_field['disabled']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view. ?>>
		<label for="noobrocket_post_exclude_<?php echo esc_attr( $noobrocket_field['id'] ); ?>"<?php echo $noobrocket_field['title']; ?><?php echo $noobrocket_field['class']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view. ?>><?php echo esc_html( $noobrocket_field['label'] ); ?></label><br>

		<?php
	}
	?>

	<p class="rkt-note">
	<?php
	// translators: %1$s = opening strong tag, %2$s = closing strong tag.
	printf( esc_html__( '%1$sNote:%2$s None of these options will be applied if this post has been excluded from cache in the global cache settings.', 'noobrocket' ), '<strong>', '</strong>' );
	?>
	</p>
</div>

<?php
/**
 * Fires after NoobRocket’s metabox.
 *
 * @since 3.6
 */
do_action( 'noobrocket_after_options_metabox' );
