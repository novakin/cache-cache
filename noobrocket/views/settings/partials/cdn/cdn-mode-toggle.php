<?php
/**
 * CDN mode toggle partial.
 *
 * Reuses the existing noobrocket-radio toggle component (Addons page / Analytics opt-in)
 * to switch between CDN modes (HostedCDN Free, HostedCDN Paid, Other CDN).
 *
 * @since 3.23
 *
 * @param array $data {
 *     Toggle data.
 *
 *     @type string $id            Input/label id.
 *     @type string $cdn_mode      CDN mode identifier (noobrocketcdn_free|noobrocketcdn_paid|byocdn).
 *     @type bool   $checked       Whether this mode is currently active.
 *     @type bool   $is_forced_off Whether the toggle must be disabled.
 *     @type string $label         Accessible label for the toggle.
 *     @type string $tooltip       Tooltip text shown when the toggle is forced off.
 * }
 */

defined( 'ABSPATH' ) || exit;

/**
 * Filters whether a CDN mode toggle should be displayed at all.
 *
 * Unlike $is_forced_off (which keeps the toggle visible but disabled), this
 * lets a hosting compatibility layer remove the toggle entirely when that
 * CDN mode is not a relevant choice at all on that host (e.g. One.com,
 * which manages its own CDN and forces the BYOCDN state regardless of what
 * NoobRocket's settings say).
 *
 * @since 3.23.4
 *
 * @param bool   $display  Whether to display the toggle. Default true.
 * @param string $cdn_mode CDN mode identifier (noobrocketcdn_free|noobrocketcdn_paid|byocdn).
 */
if ( ! wpm_apply_filters_typed( 'boolean', 'noobrocket_display_cdn_mode_toggle', true, $data['cdn_mode'] ) ) {
	return;
}
?>

<div class="noobrocket-cdn-title-divider"></div>
<div class="noobrocket-radio noobrocket-cdn-mode-toggle">
	<input
		type="checkbox"
		class="noobrocket-cdn-mode-toggle__input"
		id="<?php echo esc_attr( $data['id'] ); ?>"
		data-cdn-mode="<?php echo esc_attr( $data['cdn_mode'] ); ?>"
		<?php checked( $data['checked'] ); ?>
		<?php disabled( ! empty( $data['is_forced_off'] ) ); ?>
	/>
	<label for="<?php echo esc_attr( $data['id'] ); ?>">
		<span class="screen-reader-text"><?php echo esc_html( $data['label'] ); ?></span>
		<span class="noobrocket-radio-ui" data-l10n-active="<?php esc_attr_e( 'On', 'noobrocket' ); ?>" data-l10n-inactive="<?php esc_attr_e( 'Off', 'noobrocket' ); ?>"></span>
	</label>
	<?php if ( ! empty( $data['is_forced_off'] ) && ! empty( $data['tooltip'] ) ) : ?>
	<div class="noobrocket-tooltip">
		<div class="noobrocket-tooltip-content">
			<?php echo esc_html( $data['tooltip'] ); ?>
		</div>
	</div>
	<?php endif; ?>
</div>
