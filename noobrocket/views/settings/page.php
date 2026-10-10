<?php
/** Cache-Cache settings shell; native settings and actions keep their own submission paths. */
defined( 'ABSPATH' ) || exit;
$noobrocket_saved = false;
foreach ( get_settings_errors() as $noobrocket_notice ) {
	if ( 'settings_updated' === ( $noobrocket_notice['code'] ?? '' ) && in_array( $noobrocket_notice['type'] ?? '', [ 'success', 'updated' ], true ) ) {
		$noobrocket_saved = true;
		break;
	}
}
?>
<div class="noobrocket-wrap wrap noobrocket-app" data-noobrocket-settings-updated="<?php echo ! empty( $_GET['settings-updated'] ) ? 'true' : 'false'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only evidence of the native Settings API redirect. ?>">
	<?php // WordPress options-head.php renders Settings API notices once before this shell. ?>
	<a class="noobrocket-skip-link" href="#noobrocket-main"><?php esc_html_e( 'Skip to settings', 'noobrocket' ); ?></a>
	<div class="noobrocket-toasts" aria-live="polite" aria-relevant="additions" data-dismiss="<?php esc_attr_e( 'Dismiss this notice', 'noobrocket' ); ?>"
		data-success-label="<?php esc_attr_e( 'Success', 'noobrocket' ); ?>" data-error-label="<?php esc_attr_e( 'Error', 'noobrocket' ); ?>"
		data-warning-label="<?php esc_attr_e( 'Warning', 'noobrocket' ); ?>" data-info-label="<?php esc_attr_e( 'Information', 'noobrocket' ); ?>"></div>
	<svg class="noobrocket-icons" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><defs>
		<symbol id="nbr-icon-grid" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></symbol>
		<symbol id="nbr-icon-cache" viewBox="0 0 24 24"><path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 12 9 5 9-5M3 16l9 5 9-5"/></symbol>
		<symbol id="nbr-icon-code" viewBox="0 0 24 24"><path d="m8 6-6 6 6 6m8-12 6 6-6 6m-3-15-2 18"/></symbol>
		<symbol id="nbr-icon-image" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8" cy="8" r="1.5"/><path d="m3 17 5-5 4 4 3-3 6 6"/></symbol>
		<symbol id="nbr-icon-globe" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c5 5 5 13 0 18-5-5-5-13 0-18Z"/></symbol>
		<symbol id="nbr-icon-cloud" viewBox="0 0 24 24"><path d="M6 18a4 4 0 0 1-.5-8A6.5 6.5 0 0 1 18 9a4.5 4.5 0 0 1 0 9H6Z"/></symbol>
		<symbol id="nbr-icon-shield" viewBox="0 0 24 24"><path d="m12 3 8 3v6c0 4-4 7-8 9-4-2-8-5-8-9V6l8-3Z"/><path d="m8 12 3 3 5-6"/></symbol>
		<symbol id="nbr-icon-sliders" viewBox="0 0 24 24"><path d="M4 6h7m4 0h5M4 12h2m4 0h10M4 18h10m4 0h2"/><circle cx="13" cy="6" r="2"/><circle cx="8" cy="12" r="2"/><circle cx="16" cy="18" r="2"/></symbol>
		<symbol id="nbr-icon-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></symbol>
		<symbol id="nbr-icon-server" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="7" rx="2"/><rect x="3" y="14" width="18" height="7" rx="2"/><path d="M7 6.5h.01M7 17.5h.01M11 6.5h6m-6 11h6"/></symbol>
		<symbol id="nbr-icon-menu" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></symbol>
	</defs></svg>
	<div class="noobrocket-brand-bar">
		<div class="noobrocket-brand" role="img" aria-label="Cache-Cache">
			<img class="noobrocket-brand-logo noobrocket-brand-logo--light" src="<?php echo esc_url( NOOBROCKET_ASSETS_IMG_URL . 'cache-cache-horizontal-light.svg' ); ?>" width="760" height="164" alt="" aria-hidden="true">
			<img class="noobrocket-brand-logo noobrocket-brand-logo--dark" src="<?php echo esc_url( NOOBROCKET_ASSETS_IMG_URL . 'cache-cache-horizontal-dark.svg' ); ?>" width="760" height="164" alt="" aria-hidden="true">
		</div>
		<div class="noobrocket-brand-actions">
			<div class="noobrocket-navigation-preferences"><label class="noobrocket-appearance" for="noobrocket-appearance"><span><?php esc_html_e( 'Appearance', 'noobrocket' ); ?></span><select id="noobrocket-appearance"><option value="system"><?php esc_html_e( 'System', 'noobrocket' ); ?></option><option value="light"><?php esc_html_e( 'Light', 'noobrocket' ); ?></option><option value="dark"><?php esc_html_e( 'Dark', 'noobrocket' ); ?></option></select></label></div>
			<span class="noobrocket-edition"><?php echo esc_html( sprintf( __( 'Version %s', 'noobrocket' ), noobrocket_get_constant( 'NOOBROCKET_VERSION' ) ) ); ?></span>
			<button type="button" class="noobrocket-menu-toggle" aria-expanded="false" aria-controls="noobrocket-navigation"><svg class="noobrocket-icon" aria-hidden="true"><use href="#nbr-icon-menu"/></svg><span><?php esc_html_e( 'Settings', 'noobrocket' ); ?></span></button>
		</div>
	</div>
	<div class="noobrocket-body">
		<nav class="noobrocket-Header" id="noobrocket-navigation" aria-label="<?php esc_attr_e( 'Cache-Cache settings', 'noobrocket' ); ?>">
			<div class="noobrocket-Header-nav"><?php $this->render_navigation(); ?></div>
		</nav>
		<div class="noobrocket-Content" id="noobrocket-main" role="region" aria-label="<?php esc_attr_e( 'Cache-Cache settings', 'noobrocket' ); ?>" tabindex="-1">
			<div class="noobrocket-global-feedback"><hr class="wp-header-end" hidden></div>
			<form action="options.php" method="POST" id="<?php echo esc_attr( $data['slug'] ); ?>_options">
				<?php settings_fields( $data['slug'] ); ?>
				<?php $this->render_form_sections(); ?>
				<?php $this->render_hidden_fields(); ?>
			</form>
			<form id="noobrocket-database-cleanup" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="noobrocket_database_cleanup">
				<?php wp_nonce_field( 'noobrocket_database_cleanup' ); ?>
				<div data-noobrocket-cleanup-scope>
				<?php foreach ( [ 'database_revisions', 'database_auto_drafts', 'database_trashed_posts', 'database_spam_comments', 'database_trashed_comments', 'database_all_transients', 'database_optimize_tables' ] as $noobrocket_category ) : ?>
					<?php if ( get_noobrocket_option( $noobrocket_category, 0 ) ) : ?><input type="hidden" name="noobrocket_cleanup[]" value="<?php echo esc_attr( $noobrocket_category ); ?>"><?php endif; ?>
				<?php endforeach; ?>
				</div>
			</form>
			<?php $this->render_tools_section( $data['tools_page'] ); ?>
			<?php do_action( 'noobrocket_settings_assets_content' ); ?>
			<?php do_action( 'noobrocket_settings_history_content' ); ?>
				<div class="noobrocket-settings-footer" id="noobrocket-settings-save">
					<p role="status" aria-atomic="true" data-noobrocket-save-state
						data-clean="<?php esc_attr_e( 'No unsaved changes', 'noobrocket' ); ?>"
						data-dirty="<?php esc_attr_e( 'You have unsaved changes', 'noobrocket' ); ?>"
						data-saving="<?php esc_attr_e( 'Saving…', 'noobrocket' ); ?>"
						data-saved="<?php esc_attr_e( 'Settings saved.', 'noobrocket' ); ?>"
						data-saved-evidence="<?php echo $noobrocket_saved ? 'true' : 'false'; ?>"
						data-discard="<?php esc_attr_e( 'You have unsaved settings. Discard them and continue?', 'noobrocket' ); ?>"
						data-pending="<?php esc_attr_e( 'Working…', 'noobrocket' ); ?>"
						data-schedule-off="<?php esc_attr_e( 'Off', 'noobrocket' ); ?>"
						data-unfinished="<?php esc_attr_e( 'You have unfinished asset rule input. Leave this page and lose that input?', 'noobrocket' ); ?>"
						data-replace-rule="<?php esc_attr_e( 'Replace your unfinished asset rule input with this asset?', 'noobrocket' ); ?>"
					><?php echo esc_html( $noobrocket_saved ? __( 'Settings saved.', 'noobrocket' ) : __( 'No unsaved changes', 'noobrocket' ) ); ?></p>
					<div class="noobrocket-save-actions noobrocket-action-group">
						<button type="button" class="noobrocket-button" id="noobrocket-options-cancel" hidden disabled
							data-noobrocket-confirm-cancel="<?php esc_attr_e( 'Discard unsaved optimization settings from all sections? Saved settings and private asset drafts will stay unchanged.', 'noobrocket' ); ?>"
							data-noobrocket-cancel-url="<?php echo esc_url( admin_url( 'options-general.php?page=' . $data['slug'] ) ); ?>"
						><?php esc_html_e( 'Cancel', 'noobrocket' ); ?></button>
						<input type="submit" form="<?php echo esc_attr( $data['slug'] ); ?>_options" class="noobrocket-button" id="noobrocket-options-submit" value="<?php esc_attr_e( 'Save optimization settings', 'noobrocket' ); ?>">
					</div>
					<div class="noobrocket-save-summary"><details><summary data-noobrocket-pending-summary data-label="<?php esc_attr_e( 'Review pending settings', 'noobrocket' ); ?>"><?php esc_html_e( 'Review pending settings', 'noobrocket' ); ?></summary><p><?php esc_html_e( 'Save applies to changed settings across all sections. Asset drafts and publication use their own actions.', 'noobrocket' ); ?></p><ul data-noobrocket-changed-sections></ul></details></div>
				</div>
		</div>
	</div>
	<div class="noobrocket-Popin-overlay"></div>
	<dialog id="noobrocket-cleanup-dialog" class="noobrocket-dialog" aria-labelledby="noobrocket-cleanup-title" aria-describedby="noobrocket-cleanup-description">
		<h2 id="noobrocket-cleanup-title" tabindex="-1"><?php esc_html_e( 'Review database cleanup', 'noobrocket' ); ?></h2>
		<p id="noobrocket-cleanup-description"><?php esc_html_e( 'Selected database data will be permanently deleted. Back up your database before continuing.', 'noobrocket' ); ?></p>
		<ul data-noobrocket-cleanup-selection></ul>
		<p data-noobrocket-cleanup-empty hidden><?php esc_html_e( 'Select at least one cleanup category before continuing.', 'noobrocket' ); ?></p>
		<p><?php esc_html_e( 'Cleanup does not save settings or change the schedule. Settings history cannot recover deleted data. Counts may change before cleanup runs.', 'noobrocket' ); ?></p>
		<details data-noobrocket-cleanup-pending hidden><summary><?php esc_html_e( 'Pending edits will be discarded when cleanup starts', 'noobrocket' ); ?></summary><ul data-noobrocket-cleanup-changes></ul></details>
		<div class="noobrocket-action-group">
			<button type="button" class="noobrocket-button" data-noobrocket-cleanup-cancel><?php esc_html_e( 'Cancel', 'noobrocket' ); ?></button>
			<button type="button" class="noobrocket-button noobrocket-button-danger" data-noobrocket-cleanup-confirm><?php esc_html_e( 'Run selected cleanup', 'noobrocket' ); ?></button>
		</div>
	</dialog>
	<dialog id="noobrocket-operation-dialog" class="noobrocket-dialog" aria-labelledby="noobrocket-operation-title" aria-describedby="noobrocket-operation-description">
		<h2 id="noobrocket-operation-title" tabindex="-1" data-review="<?php esc_attr_e( 'Review: %s', 'noobrocket' ); ?>"><?php esc_html_e( 'Review pending action', 'noobrocket' ); ?></h2>
		<p id="noobrocket-operation-description"
			data-saved="<?php esc_attr_e( 'This action runs without saving pending settings. Save settings first, then run it again. Continuing leaves this page and loses any changes listed below.', 'noobrocket' ); ?>"
			data-restore="<?php esc_attr_e( 'Restoring this snapshot replaces saved settings and discards the pending edits listed below. Cancel to keep editing, or save settings first and review the restore again.', 'noobrocket' ); ?>"
		></p>
		<p data-noobrocket-restore-retention hidden><?php esc_html_e( 'Saving settings first can remove this oldest snapshot from history. Cancel to keep your edits, or restore this snapshot and discard them.', 'noobrocket' ); ?></p>
		<details><summary><?php esc_html_e( 'Changes you would leave behind', 'noobrocket' ); ?></summary><ul data-noobrocket-operation-changes></ul></details>
		<div class="noobrocket-action-group">
			<button type="button" class="noobrocket-button" data-noobrocket-operation-cancel><?php esc_html_e( 'Cancel', 'noobrocket' ); ?></button>
			<button type="button" class="noobrocket-button" data-noobrocket-operation-save><?php esc_html_e( 'Save settings first', 'noobrocket' ); ?></button>
			<button type="button" class="noobrocket-button" data-noobrocket-operation-continue data-saved="<?php esc_attr_e( 'Run %s without saving pending settings', 'noobrocket' ); ?>" data-restore="<?php esc_attr_e( 'Restore snapshot and discard pending edits', 'noobrocket' ); ?>"><?php esc_html_e( 'Continue without saving pending settings', 'noobrocket' ); ?></button>
		</div>
	</dialog>
	<?php do_action( 'noobrocket_settings_page_footer' ); ?>
</div>
