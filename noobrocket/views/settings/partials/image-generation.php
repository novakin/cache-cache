<?php
/** Native image queue status; submit actions remain outside the settings form. */
defined( 'ABSPATH' ) || exit;
$supported = (bool) array_filter( $data['capabilities'] );
$has_work = $data['active'] || $data['pending'];
$cursor = (int) ( $data['cursor'] ?? 0 );
$last_run = (int) ( $data['last_run'] ?? 0 );
$next_run = (int) ( $data['next_run'] ?? 0 );
$batch_overdue = $has_work && $next_run && $next_run < time() - 300;
$scan_complete = ! $has_work && $cursor > 0 && $last_run > 0;
$resume_scan = $data['active'] || ( $data['pending'] && $cursor > 0 );
$status_label = __( 'No images queued', 'noobrocket' );
$status_class = 'is-neutral';
if ( ! $data['enabled'] ) {
	$status_label = __( 'Off', 'noobrocket' );
} elseif ( ! $supported ) {
	$status_label = __( 'Unavailable', 'noobrocket' );
	$status_class = 'is-warning';
} elseif ( $data['failed'] ) {
	$status_label = __( 'Needs attention', 'noobrocket' );
	$status_class = 'is-error';
} elseif ( $has_work && ! $next_run ) {
	$status_label = __( 'Needs scheduling', 'noobrocket' );
	$status_class = 'is-warning';
} elseif ( $batch_overdue ) {
	$status_label = __( 'Scheduled batch overdue', 'noobrocket' );
	$status_class = 'is-warning';
} elseif ( $data['active'] ) {
	$status_label = __( 'Scan queued', 'noobrocket' );
} elseif ( $data['pending'] ) {
	$status_label = __( 'Images queued', 'noobrocket' );
} elseif ( $scan_complete ) {
	$status_label = __( 'Library scan complete', 'noobrocket' );
	$status_class = 'is-success';
}
?>
<section class="noobrocket-surface noobrocket-image-generation" aria-labelledby="noobrocket-image-title">
	<div class="noobrocket-surface-head"><h2 id="noobrocket-image-title"><svg class="noobrocket-icon" aria-hidden="true"><use href="#nbr-icon-image"/></svg><?php esc_html_e( 'Modern images', 'noobrocket' ); ?></h2><span class="noobrocket-badge <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_label ); ?></span></div>
	<div class="noobrocket-card-body">
		<p><?php
			if ( $data['capabilities']['avif'] && $data['capabilities']['webp'] ) {
				esc_html_e( 'Generate AVIF and WebP locally. JPEG and PNG originals stay available.', 'noobrocket' );
			} elseif ( $data['capabilities']['avif'] ) {
				esc_html_e( 'Generate AVIF locally. JPEG and PNG originals stay available.', 'noobrocket' );
			} elseif ( $data['capabilities']['webp'] ) {
				esc_html_e( 'Generate WebP locally. JPEG and PNG originals stay available.', 'noobrocket' );
			} else {
				esc_html_e( 'JPEG and PNG originals stay available. Your server cannot generate AVIF or WebP.', 'noobrocket' );
			}
		?></p>
		<p class="noobrocket-helper"><?php
			if ( ! $data['enabled'] ) {
				esc_html_e( 'Enable image delivery in Media and save. Then scan the library here to convert existing images.', 'noobrocket' );
			} elseif ( ! $supported ) {
				esc_html_e( 'Ask your host to enable AVIF or WebP encoding in GD or Imagick.', 'noobrocket' );
			} elseif ( $has_work && ! $next_run ) {
				echo esc_html( $resume_scan ? __( 'No generation batch is scheduled. Resume the library scan to queue one.', 'noobrocket' ) : __( 'No generation batch is scheduled. Scan the library to queue one.', 'noobrocket' ) );
			} elseif ( $batch_overdue ) {
				esc_html_e( 'The scheduled image batch is over five minutes late. Check WordPress cron; pending work is preserved.', 'noobrocket' );
			} elseif ( $data['active'] ) {
				esc_html_e( 'The library scan is waiting for a WordPress cron batch. Recorded progress does not mean a batch is running now.', 'noobrocket' );
			} elseif ( $data['pending'] ) {
				esc_html_e( 'Image generation is queued for WordPress cron.', 'noobrocket' );
			} elseif ( $scan_complete ) {
				esc_html_e( 'The last library scan finished. New uploads are queued automatically.', 'noobrocket' );
			} else {
				esc_html_e( 'New uploads are queued automatically. Scan the library to convert existing images.', 'noobrocket' );
			}
		?></p>
		<?php if ( $data['enabled'] && $data['pending'] ) : ?><p class="noobrocket-helper"><?php
			/* translators: %s: number of media items awaiting generation, not generated image files. */
			echo esc_html( sprintf( _n( '%s media item awaiting generation.', '%s media items awaiting generation.', $data['pending'], 'noobrocket' ), number_format_i18n( $data['pending'] ) ) );
		?></p><?php endif; ?>
		<?php if ( $data['enabled'] && $data['failed'] ) : ?><p class="noobrocket-helper"><?php
			/* translators: %s: number of media items that could not be converted. */
			echo esc_html( sprintf( _n( '%s media item failed; its original is unchanged.', '%s media items failed; their originals are unchanged.', $data['failed'], 'noobrocket' ), number_format_i18n( $data['failed'] ) ) );
			if ( $data['enabled'] && $supported ) {
				echo ' ' . esc_html( $has_work ? __( 'Finish the current scan, then scan again to retry failed items.', 'noobrocket' ) : __( 'Scan the library again to retry failed items.', 'noobrocket' ) );
			}
		?></p><?php endif; ?>
		<?php if ( $last_run ) : ?><p class="noobrocket-helper"><?php
			/* translators: %s: localized date and time of the last image-generation batch. */
			echo esc_html( sprintf( __( 'Last batch: %s', 'noobrocket' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_run ) ) );
		?></p><?php endif; ?>
		<div class="noobrocket-action-group"><button class="noobrocket-button" type="submit" form="noobrocket-image-actions" <?php disabled( ! $data['enabled'] || ! $supported ); ?>><?php echo esc_html( $resume_scan ? __( 'Resume library scan', 'noobrocket' ) : __( 'Scan media library', 'noobrocket' ) ); ?></button></div>
		<?php if ( $data['enabled'] && $supported ) : ?><p class="noobrocket-helper"><?php esc_html_e( 'Uses saved Media settings.', 'noobrocket' ); ?></p><?php endif; ?>
		<details class="noobrocket-status-evidence"><summary><?php esc_html_e( 'Image queue and encoder details', 'noobrocket' ); ?></summary>
			<p><?php esc_html_e( 'Requires PHP GD or Imagick with AVIF or WebP encoding, a writable uploads directory and working WordPress cron. Node.js and Chromium are not required for images.', 'noobrocket' ); ?></p>
			<p><?php esc_html_e( 'Only versions that save at least 5% are kept. A completed scan does not mean every image has a new format.', 'noobrocket' ); ?></p>
			<dl class="noobrocket-key-values">
				<?php foreach ( [ 'avif' => __( 'AVIF encoder', 'noobrocket' ), 'webp' => __( 'WebP encoder', 'noobrocket' ) ] as $format => $label ) : ?>
				<div>
					<dt><?php echo esc_html( $label ); ?></dt>
					<dd><?php echo esc_html( $data['capabilities'][ $format ] ? __( 'Available', 'noobrocket' ) : __( 'Unavailable', 'noobrocket' ) ); ?></dd>
				</div>
				<?php endforeach; ?>
				<div><dt><?php esc_html_e( 'Pending media items', 'noobrocket' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $data['pending'] ) ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Failed media items', 'noobrocket' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $data['failed'] ) ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Successful encodes recorded', 'noobrocket' ); ?></dt><dd><?php echo esc_html( number_format_i18n( (int) ( $data['generated'] ?? 0 ) ) ); ?></dd></div>
			</dl>
			<p class="noobrocket-helper"><?php esc_html_e( 'The encode total is historical, including image sizes and formats. It is not a count of media items or files currently on disk.', 'noobrocket' ); ?></p>
			<?php if ( $data['enabled'] && $supported && $has_work && $next_run ) : ?><p><?php
				/* translators: %s: scheduled date and time. Scheduling is not proof that WordPress cron ran. */
				echo esc_html( sprintf( __( 'Next batch scheduled for: %s. WordPress cron must run to process it.', 'noobrocket' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_run ) ) );
			?></p><?php endif; ?>
		</details>
	</div>
	<div class="noobrocket-status-navigation"><a href="#media"><?php echo esc_html( $data['enabled'] ? __( 'Media settings', 'noobrocket' ) : __( 'Configure image delivery', 'noobrocket' ) ); ?></a></div>
</section>
