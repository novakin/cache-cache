<?php
/**
 * CDN driver tabs partial.
 *
 * Renders the tab switcher between HostedCDN / Your own CDN.
 *
 * @param array $data {
 *     Notice data.
 *
 *     @type string $cdn_type The currently active CDN type.
 *     @type bool $display_tabs Whether to display the tabs (if false, both tabs will be disabled).
 *     @type bool $noobrocketcdn_active Whether HostedCDN (free or paid) is the currently applied CDN mode.
 *     @type bool $byocdn_active Whether "Your own CDN" is the currently applied CDN mode.
 * }
 *
 * @since 3.22
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="noobrocket-cdn-tabs <?php echo ! $data['display_tabs'] ? 'noobrocket-isHidden' : ''; ?>">
	<button
		type="button"
		class="noobrocket-cdn-tabs__tab <?php echo 'noobrocketcdn' === $data['cdn_type'] ? 'noobrocket-cdn-tabs__tab--active' : ''; ?><?php echo $data['noobrocketcdn_active'] ? ' noobrocket-cdn-active-indicator' : ''; ?>"
		data-cdn-driver="noobrocketcdn"
		data-title="<?php esc_attr_e( 'HostedCDN', 'noobrocket' ); ?>"
		data-cdn-mode="<?php echo esc_attr( $data['noobrocketcdn_mode'] ); ?>"
	>
		<?php esc_html_e( 'HostedCDN', 'noobrocket' ); ?>
		<span class="noobrocket-cdn-active-label"><?php esc_html_e( 'Active', 'noobrocket' ); ?></span>
	</button>

	<span class="noobrocket-cdn-tabs__divider"></span>

	<div class="noobrocket-cdn-tabs__tab-wrapper">
		<button
			type="button"
			class="noobrocket-cdn-tabs__tab <?php echo 'byocdn' === $data['cdn_type'] ? 'noobrocket-cdn-tabs__tab--active' : ''; ?><?php echo $data['byocdn_active'] ? ' noobrocket-cdn-active-indicator' : ''; ?>"
			data-cdn-driver="your-own-cdn"
			data-title="<?php esc_attr_e( 'Your CDN', 'noobrocket' ); ?>"
			data-cdn-mode="Other CDN"
		>
			<?php esc_html_e( 'Other CDN', 'noobrocket' ); ?>
			<span class="noobrocket-cdn-active-label"><?php esc_html_e( 'Active', 'noobrocket' ); ?></span>
		</button>
	</div>
</div>
