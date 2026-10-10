<?php
/**
 * HostedCDN Paid (Pro) section template.
 *
 * Displays the status indicator for the paid CDN plan.
 *
 * @since 3.22
 *
 * @param array $data {
 *     Section data.
 *
 *     @type string $id          Section identifier.
 *     @type string $title       Section title.
 *     @type string $description Section description.
 *     @type string $class       Section classes.
 *     @type string $help        Data to pass to beacon.
 *     @type string $page        Page section identifier.
 *     @type array  $status_indicator Data for the CDN status indicator partial.
 *     @type bool   $is_active    Whether HostedCDN Pro is the currently applied CDN mode.
 *     @type bool   $is_forced_off Whether the mode toggle must be disabled (subscription loading, expired/banned licence).
 *     @type string $toggle_tooltip Tooltip shown on the mode toggle when $is_forced_off is true.
 * }
 */

defined( 'ABSPATH' ) || exit;

$noobrocket_noobrocketcdn_paid_active = $data['is_active'];
?>

<?php // noobrocket-cdn-active-indicator is kept in sync with JS (cdn-driver.js), which reads it off .noobrocket-optionHeader to find the previously-active mode on a failed switch — it no longer drives any visible styling on this header itself. ?>
<div class="noobrocket-optionHeader noobrocket-optionHeader--cdn-driver <?php echo esc_attr( $data['class'] ); ?><?php echo $noobrocket_noobrocketcdn_paid_active ? ' noobrocket-cdn-active-indicator' : ''; ?>">
	<div class="noobrocket-optionHeader__title-group">
		<h3 class="noobrocket-title2 noobrocket-title2--orange"><?php echo esc_html( $data['title'] ); ?></h3>
		<span class="noobrocket-badge noobrocket-badge--blue"><?php esc_html_e( 'Pro', 'noobrocket' ); ?></span>
		<?php
		$this->render_parts_with_data(
			'cdn/cdn-mode-toggle',
			[
				'id'            => 'noobrocket-noobrocketcdn-paid-toggle',
				'cdn_mode'      => 'noobrocketcdn_paid',
				'checked'       => $noobrocket_noobrocketcdn_paid_active,
				'is_forced_off' => $data['is_forced_off'],
				'label'         => __( 'Enable HostedCDN Paid', 'noobrocket' ),
				'tooltip'       => $data['toggle_tooltip'],
			]
		);
		?>
	</div>
	<?php if ( ! empty( $data['help'] ) ) : ?>
	<a href="<?php echo esc_url( $data['help']['url'] ); ?>" data-beacon-id="<?php echo esc_attr( $data['help']['id'] ); ?>" data-nbr_track_button="Need Help" data-nbr_track_context="Settings" class="noobrocket-infoAction noobrocket-infoAction--help noobrocket-icon-help" target="_blank"><?php esc_html_e( 'Need Help?', 'noobrocket' ); ?></a>
	<?php endif; ?>
</div>

<div class="<?php echo esc_attr( $data['class'] ); ?>">
	<?php
	/**
	 * Fires before the HostedCDN paid status indicator is displayed.
	 *
	 * @since 3.23.3
	 */
	do_action( 'noobrocket_cdn_paid_before_status_indicator' );
	?>
</div>

<?php
if ( ! $data['status_indicator']['no_status_indicator'] ) {
	$this->render_parts_with_data( 'cdn/cdn-status-indicator', $data['status_indicator'] );
}
?>
