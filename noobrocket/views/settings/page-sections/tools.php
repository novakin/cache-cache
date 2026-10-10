<?php
/**
 * Import page section template.
 *
 * @since 3.0
 */

use NoobRocket\Logger\Logger;

defined( 'ABSPATH' ) || exit;

// Debug mode.
$noobrocket_log_description = '';

if ( noobrocket_direct_filesystem()->exists( Logger::get_log_file_path() ) ) {
	$noobrocket_stats = Logger::get_log_file_stats();

	if ( ! is_wp_error( $noobrocket_stats ) ) {
		// translators: %1$s = formatted file size, %2$s = formatted number of entries (don't use %2$d).
		$noobrocket_log_description .= sprintf( esc_html__( 'Files size: %1$s. Number of entries: %2$s.', 'noobrocket' ), '<strong>' . esc_html( $noobrocket_stats['bytes'] ) . '</strong>', '<strong>' . esc_html( $noobrocket_stats['entries'] ) . '</strong>' );

		// translators: %1$s = opening <a> tag, %2$s = closing </a> tag.
		$noobrocket_log_description .= '<br/>' . sprintf( esc_html__( '%1$sDownload the file%2$s.', 'noobrocket' ), '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=noobrocket_download_debug_file' ), 'download_debug_file' ) ) . '">', '</a>' );

		// translators: %1$s = opening <a> tag, %2$s = closing </a> tag.
		$noobrocket_log_description .= ' - ' . sprintf( esc_html__( '%1$sDelete the file%2$s.', 'noobrocket' ), '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=noobrocket_delete_debug_file' ), 'delete_debug_file' ) ) . '">', '</a>' );
	}
}
?>

<div id="tools" class="noobrocket-Page">
	<div class="noobrocket-sectionHeader">
		<h1 class="noobrocket-title1 noobrocket-icon-tools" tabindex="-1"><?php esc_html_e( 'Tools', 'noobrocket' ); ?></h1>
	</div>
	<?php $this->render_import_form( true ); ?>
	<div class="noobrocket-settings-card noobrocket-tools-surface">
	<section class="noobrocket-tools noobrocket-tools-export" aria-labelledby="noobrocket-export-settings-title">
		<div class="noobrocket-tools-col noobrocket-tools-copy">
			<h2 class="noobrocket-title3 noobrocket-tools-label" id="noobrocket-export-settings-title"><?php esc_html_e( 'Export settings', 'noobrocket' ); ?></h2>
			<p class="noobrocket-field-description"><?php esc_html_e( 'Download saved settings as JSON. This file can contain integration credentials; keep it private.', 'noobrocket' ); ?></p>
		</div>
		<div class="noobrocket-tools-col noobrocket-tools-export-action">
			<?php
			$this->render_action_button(
				'link',
				'noobrocket_export',
				[
					'label'      => __( 'Download settings', 'noobrocket' ),
					'attributes' => [
						'class' => 'noobrocket-button noobrocket-button--small',
					],
				]
			);
			?>
		</div>
	</section>

	<?php $this->render_import_form(); ?>
	<p class="noobrocket-tools-scope"><?php esc_html_e( 'Asset rules and settings history are not included in settings transfers.', 'noobrocket' ); ?></p>
	</div>

	<div class="noobrocket-tools-related">
	<?php
	/**
	 * Fires after the Settings Tools page content
	 *
	 * @since 3.6
	 */
	do_action( 'noobrocket_settings_tools_content' );
	?>
	</div>

	<!-- Temporary hide the option. The logger can still be activated by adding the following to the wp-config.php file: define( 'NOOBROCKET_DEBUG', true );
	<div class="noobrocket-tools">
		<div class="noobrocket-tools-col noobrocket-radio">
			<div class="noobrocket-title3 noobrocket-tools-label">
				<input id="debug_enabled" name="noobrocket_settings[debug_enabled]" value="1"<?php checked( true, Logger::debug_enabled() ); ?> type="checkbox">
				<label for="debug_enabled">
					<span data-l10n-active="On" data-l10n-inactive="Off" class="noobrocket-radio-ui"></span>
					<?php esc_html_e( 'Debug mode', 'noobrocket' ); ?>
				</label>
			</div>

			<div class="noobrocket-field-description">
				<?php esc_html_e( 'Create a debug log file.', 'noobrocket' ); ?>
			</div>
		</div>
		<div class="noobrocket-tools-col">
			<?php echo $noobrocket_log_description; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view. ?>
		</div>
	</div>
	-->
</div>
