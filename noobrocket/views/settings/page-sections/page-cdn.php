<?php
/**
 * CDN section template.
 *
 * @since 3.0
 *
 * @param array {
 *     Section arguments.
 *
 *     @type string $id    Page section identifier.
 *     @type string $title Page section title.
 * }
 */

defined( 'ABSPATH' ) || exit;

?>

<div id="<?php echo esc_attr( $data['id'] ); ?>" class="noobrocket-Page">
	<div class="noobrocket-sectionHeader noobrocket-sectionHeader--cdn">
		<h1 class="noobrocket-title1 noobrocket-icon-cdn" tabindex="-1"><?php echo esc_html( $data['title'] ); ?></h1>
		<p class="noobrocket-sectionHeader-description"><?php esc_html_e( 'Connect your CDN to images, stylesheets and scripts.', 'noobrocket' ); ?></p>
		<?php
		/**
		 * Fires in the CDN section header to render the CDN driver tabs.
		 *
		 * @since 3.21.2
		 */
		do_action( 'noobrocket_cdn_driver_tabs' );
		?>
	</div>
	<?php
	/**
	 * Fires before displaying CDN sections on NoobRocket settings page
	 *
	 * @since 3.5
	 */
	do_action( 'noobrocket_before_cdn_sections' );

	$this->render_settings_sections( $data['id'] );
	/**
	 * Fires after displaying CDN sections on NoobRocket settings page
	 *
	 * @since 3.5
	 */
	do_action( 'noobrocket_after_cdn_sections' );
	$noobrocket_samples = [
		__( 'CSS', 'noobrocket' ) => plugins_url( 'assets/css/noobrocket-admin.css', NOOBROCKET_FILE ),
		__( 'JavaScript', 'noobrocket' ) => plugins_url( 'assets/js/noobrocket-admin.js', NOOBROCKET_FILE ),
		__( 'Image', 'noobrocket' ) => plugins_url( 'assets/img/icon-user-cache.svg', NOOBROCKET_FILE ),
	];
	$noobrocket_rewritten = [];
	foreach ( $noobrocket_samples as $noobrocket_type => $noobrocket_original ) {
		$noobrocket_sample = get_noobrocket_cdn_url( $noobrocket_original );
		if ( $noobrocket_sample !== $noobrocket_original ) {
			$noobrocket_rewritten[ $noobrocket_type ] = [ $noobrocket_original, $noobrocket_sample ];
		}
	}
	?>
	<details class="noobrocket-settings-card noobrocket-settings-disclosure noobrocket-cdn-verification">
		<summary class="noobrocket-optionHeader"><h2 class="noobrocket-title2"><?php esc_html_e( 'Verify saved delivery', 'noobrocket' ); ?></h2></summary>
		<p><?php esc_html_e( 'This sample uses saved settings, not pending edits. Open it after saving, then check your public page while logged out.', 'noobrocket' ); ?></p>
		<?php if ( $noobrocket_rewritten ) : ?>
			<?php foreach ( $noobrocket_rewritten as $noobrocket_type => [ $noobrocket_original, $noobrocket_sample ] ) : ?>
			<dl class="noobrocket-key-values">
				<div><dt><?php echo esc_html( $noobrocket_type ); ?></dt><dd><code><?php echo esc_html( $noobrocket_original ); ?></code></dd></div>
				<div><dt><?php esc_html_e( 'CDN sample', 'noobrocket' ); ?></dt><dd><a href="<?php echo esc_url( $noobrocket_sample ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $noobrocket_sample ); ?></a></dd></div>
			</dl>
			<?php endforeach; ?>
			<p><?php esc_html_e( 'Each sample should load without an error. In your browser network panel, confirm eligible public-page assets use the CDN and load successfully.', 'noobrocket' ); ?></p>
		<?php else : ?>
			<p><?php esc_html_e( 'No representative asset is rewritten by the saved settings. Check the enabled resource scope and exclusions, then verify an eligible asset on a public page.', 'noobrocket' ); ?></p>
		<?php endif; ?>
		<p><?php esc_html_e( 'If assets fail, disable CDN rewriting, save, and check your provider origin and hostname configuration.', 'noobrocket' ); ?></p>
		<p><?php esc_html_e( 'Your CDN provider manages delivery and cache purges.', 'noobrocket' ); ?></p>
	</details>
</div>
