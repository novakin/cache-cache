<?php
/**
 * Import form template.
 *
 * @since 3.0
 *
 * @param array $data {
 *     Import form data.
 *
 *     @type array  $upload_dir  Array containing data about the upload dir, and an error key and message if needed.
 *     @type string $size        Max upload size in a human readable format.
 *     @type string $bytes       Raw max upload size.
 *     @type string $action      WordPress action associated with the form.
 *     @type string $submit_text Content for the submit button.
 * }
 */

defined( 'ABSPATH' ) || exit;

$noobrocket_review = get_transient( 'noobrocket_import_review_' . get_current_user_id() );
if ( ! empty( $data['review_only'] ) && is_array( $noobrocket_review ) && isset( $noobrocket_review['current'], $noobrocket_review['settings'], $noobrocket_review['token'] ) ) :
	$noobrocket_changes = [];
	foreach ( array_unique( array_merge( array_keys( $noobrocket_review['current'] ), array_keys( $noobrocket_review['settings'] ) ) ) as $noobrocket_key ) {
		if ( ! array_key_exists( $noobrocket_key, $noobrocket_review['current'] ) || ! array_key_exists( $noobrocket_key, $noobrocket_review['settings'] ) || $noobrocket_review['current'][ $noobrocket_key ] !== $noobrocket_review['settings'][ $noobrocket_key ] ) {
			$noobrocket_changes[] = $noobrocket_key;
		}
	}
	$noobrocket_import_value = static function ( $options, $key ) use ( $data ) {
		if ( ! array_key_exists( $key, $options ) ) {
			return __( 'Not set', 'noobrocket' );
		}
		if ( in_array( $key, [ 'cloudflare_zone_id', 'cloudflare_old_settings' ], true ) || preg_match( '/(?:^|_)(?:key|secret|token|password|email|credentials?|license|consumer)(?:_|$)/i', $key ) ) {
			return empty( $options[ $key ] ) ? __( 'Not set', 'noobrocket' ) : __( 'Private value (hidden)', 'noobrocket' );
		}
		if ( is_scalar( $options[ $key ] ) && isset( $data['choices'][ $key ][ $options[ $key ] ] ) ) {
			return wp_strip_all_tags( $data['choices'][ $key ][ $options[ $key ] ] );
		}
		if ( in_array( $data['types'][ $key ] ?? '', [ 'checkbox', 'one_click_addon', 'noobrocket_addon' ], true ) ) {
			return empty( $options[ $key ] ) ? __( 'Off', 'noobrocket' ) : __( 'On', 'noobrocket' );
		}
		if ( [] === $options[ $key ] || '' === $options[ $key ] ) {
			return __( 'Not set', 'noobrocket' );
		}
		if ( is_array( $options[ $key ] ) && array_keys( $options[ $key ] ) === range( 0, count( $options[ $key ] ) - 1 ) ) {
			$items = [];
			foreach ( $options[ $key ] as $value ) {
				$items[] = is_scalar( $value ) ? ( $data['choices'][ $key ][ $value ] ?? (string) $value ) : wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			}
			return implode( "\n", $items );
		}
		return is_string( $options[ $key ] ) ? $options[ $key ] : wp_json_encode( $options[ $key ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	};
	?>
	<section class="noobrocket-import-review" aria-labelledby="noobrocket-import-review-title">
		<h2 id="noobrocket-import-review-title"><?php esc_html_e( 'Review imported settings', 'noobrocket' ); ?></h2>
		<p><?php echo esc_html( $noobrocket_review['filename'] ); ?></p>
		<p><?php esc_html_e( 'These changes replace saved settings after confirmation. Integration credentials are hidden. Asset rules and settings history are excluded. This review expires after 10 minutes; saved settings must still match the reviewed state.', 'noobrocket' ); ?></p>
		<?php if ( $noobrocket_changes ) : ?>
			<p><?php echo esc_html( sprintf( __( 'Changed settings: %s', 'noobrocket' ), number_format_i18n( count( $noobrocket_changes ) ) ) ); ?></p>
			<div class="noobrocket-import-differences" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'Imported settings comparison', 'noobrocket' ); ?>">
				<table class="widefat striped">
					<thead><tr><th scope="col"><?php esc_html_e( 'Setting', 'noobrocket' ); ?></th><th scope="col"><?php esc_html_e( 'Saved value', 'noobrocket' ); ?></th><th scope="col"><?php esc_html_e( 'Imported value', 'noobrocket' ); ?></th></tr></thead>
					<tbody><?php foreach ( $noobrocket_changes as $noobrocket_key ) : ?>
						<tr><th scope="row" data-noobrocket-import-setting="<?php echo esc_attr( $noobrocket_key ); ?>"><?php echo esc_html( $data['labels'][ $noobrocket_key ] ?? str_replace( '_', ' ', $noobrocket_key ) ); ?></th><td data-label="<?php esc_attr_e( 'Saved value', 'noobrocket' ); ?>"><?php echo esc_html( $noobrocket_import_value( $noobrocket_review['current'], $noobrocket_key ) ); ?></td><td data-label="<?php esc_attr_e( 'Imported value', 'noobrocket' ); ?>"><?php echo esc_html( array_key_exists( $noobrocket_key, $noobrocket_review['settings'] ) ? $noobrocket_import_value( $noobrocket_review['settings'], $noobrocket_key ) : __( 'Removed', 'noobrocket' ) ); ?></td></tr>
					<?php endforeach; ?></tbody>
				</table>
			</div>
		<?php else : ?>
			<p><?php esc_html_e( 'The imported settings match the saved settings.', 'noobrocket' ); ?></p>
		<?php endif; ?>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="POST" class="noobrocket-action-group">
			<?php wp_nonce_field( $data['action'], $data['action'] . '_nonce' ); ?>
			<input type="hidden" name="action" value="<?php echo esc_attr( $data['action'] ); ?>">
			<input type="hidden" name="noobrocket_import_review" value="<?php echo esc_attr( $noobrocket_review['token'] ); ?>">
			<button type="submit" class="noobrocket-button" name="noobrocket_import_cancel" value="1"><?php esc_html_e( 'Cancel import', 'noobrocket' ); ?></button>
			<button type="submit" class="noobrocket-button noobrocket-button-danger"><?php esc_html_e( 'Replace saved settings', 'noobrocket' ); ?></button>
		</form>
	</section>
	<?php
endif;

if ( ! empty( $data['review_only'] ) ) {
	return;
}

if ( ! empty( $data['upload_dir']['error'] ) ) {
	?>
	<div class="error"><p><?php esc_html_e( 'Before you can upload your import file, you will need to fix the following error:', 'noobrocket' ); ?></p>
	<p><strong><?php echo esc_html( $data['upload_dir']['error'] ); ?></strong></p></div>
	<?php
} else {
	?>
	<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="POST" enctype="multipart/form-data" class="noobrocket-tools noobrocket-tools-import" aria-labelledby="noobrocket-import-settings-title">
		<div class="noobrocket-tools-col noobrocket-tools-copy">
			<h2 class="noobrocket-title3 noobrocket-tools-label" id="noobrocket-import-settings-title"><?php esc_html_e( 'Import settings', 'noobrocket' ); ?></h2>
			<p class="noobrocket-field-description"><?php esc_html_e( 'Choose a Cache-Cache JSON export with its original filename. Upload it to review changes before replacing saved settings.', 'noobrocket' ); ?></p>
		</div>
		<div class="noobrocket-tools-import-row">
			<div class="noobrocket-upload">
				<label for="upload"><?php esc_html_e( 'Settings export file', 'noobrocket' ); ?></label>
				<input type="file" accept=".json" id="upload" name="import" size="25" required aria-describedby="noobrocket-import-help" />
			</div>
			<div class="noobrocket-tools-import-submit">
			<?php
			wp_nonce_field( $data['action'], $data['action'] . '_nonce' );
			?>
			<button type="submit" class="noobrocket-button noobrocket-button-primary"><?php esc_html_e( 'Upload and review changes', 'noobrocket' ); ?></button>
			</div>
		</div>
		<small id="noobrocket-import-help" class="noobrocket-field-description">
			<?php
			// translators: %s is the maximum upload size set on the current server.
			printf( esc_html__( 'Choose a file from your computer (maximum size: %s)', 'noobrocket' ), esc_html( $data['size'] ) );
			?>
		</small>
		<input type="hidden" name="max_file_size" value="<?php echo esc_attr( $data['bytes'] ); ?>" />
		<input type="hidden" name="action" value="<?php echo esc_attr( $data['action'] ); ?>" />
	</form>
	<?php
}
