<?php
declare(strict_types=1);

namespace NoobRocket\Engine\Media\Picture;

use NoobRocket\Admin\Options_Data;
use NoobRocket\Event_Management\Subscriber_Interface;

/** Native, site-local image generation outside frontend requests. */
class Generator implements Subscriber_Interface {
	public const MINIMUM_SAVING = 0.05;
	private const CRON = 'noobrocket_generate_images';
	private const STATE = 'noobrocket_image_generation';
	private const PENDING = '_noobrocket_image_pending';
	private const OWNED = '_noobrocket_image_files';
	private const ERROR = '_noobrocket_image_error';
	private $options;
	private $changed = false;

	public function __construct( Options_Data $options ) {
		$this->options = $options;
	}

	public static function get_subscribed_events(): array {
		return [
			'wp_generate_attachment_metadata' => [ 'queue_attachment', 20, 2 ],
			self::CRON => 'process_batch',
			'delete_attachment' => 'delete_generated_files',
			'update_option_noobrocket_settings' => [ 'settings_changed', 20, 2 ],
			'admin_post_noobrocket_image_backfill' => 'handle_backfill',
			'noobrocket_before_dashboard_content' => [ 'render', 10 ],
			'noobrocket_settings_page_footer' => 'render_action_form',
			'noobrocket_deactivation' => [ 'deactivate', 10, 2 ],
		];
	}

	public function capabilities(): array {
		return [
			'avif' => wp_image_editor_supports( [ 'mime_type' => 'image/avif' ] ),
			'webp' => wp_image_editor_supports( [ 'mime_type' => 'image/webp' ] ),
		];
	}

	public function status(): array {
		global $wpdb;
		$state = $this->state();
		$state['enabled'] = $this->enabled();
		$state['capabilities'] = $this->capabilities();
		$state['pending'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s", self::PENDING ) );
		$state['failed'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s", self::ERROR ) );
		$state['next_run'] = (int) wp_next_scheduled( self::CRON );
		return $state;
	}

	public function render(): void {
		if ( current_user_can( 'noobrocket_manage_options' ) ) {
			$data = $this->status();
			require NOOBROCKET_PATH . 'views/settings/partials/image-generation.php';
		}
	}

	public function render_action_form(): void {
		if ( ! current_user_can( 'noobrocket_manage_options' ) ) {
			return;
		}
		echo '<form id="noobrocket-image-actions" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" method="post">';
		echo '<input type="hidden" name="action" value="noobrocket_image_backfill">';
		wp_nonce_field( 'noobrocket_image_backfill', '_noobrocket_image_nonce' );
		echo '</form>';
	}

	public function deactivate( $sites_number = 0, bool $network_wide = false ): void {
		\NoobRocket\Engine\Deactivation\Deactivation::for_each_deactivated_site(
			static function () {
				wp_clear_scheduled_hook( self::CRON );
			},
			$network_wide
		);
	}

	/** Save only queue metadata; WordPress saves the completed attachment metadata next. */
	public function queue_attachment( $metadata, $attachment_id ) {
		if ( $this->enabled() && is_array( $metadata ) && in_array( get_post_mime_type( $attachment_id ), [ 'image/jpeg', 'image/png' ], true ) ) {
			$this->enqueue( (int) $attachment_id );
			$this->schedule();
		}
		return $metadata;
	}

	/** Explicit backfill starts once and resumes the existing cursor and pending attachments. */
	public function queue_library(): array {
		if ( ! $this->enabled() ) {
			return $this->status();
		}
		$lock = $this->lock( false );
		if ( ! $lock ) {
			return $this->status();
		}
		try {
			$state = $this->state();
			if ( ! $state['active'] ) {
				$pending = $this->pending();
				if ( null === $pending ) {
					$state['active'] = true;
					$this->retry_failed_read( $state );
					return $this->status();
				}
				if ( ! $pending ) {
					$state['cursor'] = 0;
				}
			}
			$state['active'] = true;
			$state['last_error'] = '';
			update_option( self::STATE, $state, false );
			$this->schedule();
		} finally {
			$this->unlock( $lock );
		}
		return $this->status();
	}

	public function settings_changed( $old, $new ): void {
		if ( ! is_array( $new ) || ! in_array( $new['image_delivery'] ?? 0, [ true, 1, '1' ], true ) ) {
			wp_clear_scheduled_hook( self::CRON );
			return;
		}
		$state = $this->state();
		if ( $state['active'] ) {
			$this->schedule();
			return;
		}
		$pending = $this->pending();
		if ( null === $pending ) {
			$this->retry_failed_read( $state );
		} elseif ( $pending ) {
			$this->schedule();
		}
	}

	public function handle_backfill(): void {
		if ( ! in_array( $_SERVER['REQUEST_METHOD'] ?? '', [ 'POST' ], true ) || ! current_user_can( 'noobrocket_manage_options' ) ) {
			wp_die( esc_html__( 'You cannot queue Cache-Cache image generation.', 'noobrocket' ), '', [ 'response' => 403 ] );
		}
		check_admin_referer( 'noobrocket_image_backfill', '_noobrocket_image_nonce' );
		$this->queue_library();
		wp_safe_redirect( admin_url( 'options-general.php?page=noobrocket' ) . '#dashboard' );
		exit;
	}

	/** Four encodes and a twenty-second soft deadline per cron run; never encode in HTML filters. */
	public function process_batch(): void {
		if ( ! $this->enabled() ) {
			return;
		}
		$lock = $this->lock( false );
		if ( ! $lock ) {
			$this->schedule();
			return;
		}
		$generated = 0;
		$this->changed = false;
		try {
			$state = $this->state();
			$formats = array_keys( array_filter( $this->capabilities() ) );
			if ( ! $formats ) {
				$state['last_error'] = 'unsupported_encoders';
				update_option( self::STATE, $state, false );
				return;
			}
			$pending = $this->pending();
			if ( null === $pending ) {
				$this->retry_failed_read( $state );
				return;
			}
			$scanned_state = $state;
			if ( ! $pending && $state['active'] ) {
				if ( ! $this->scan_library( $scanned_state ) ) {
					$this->retry_failed_read( $state );
					return;
				}
				$pending = $this->pending();
				if ( null === $pending ) {
					$this->retry_failed_read( $state );
					return;
				}
			}
			$budget = 4;
			$deadline = microtime( true ) + 20;
			foreach ( $pending as $id ) {
				if ( $budget <= 0 || microtime( true ) >= $deadline ) {
					break;
				}
				$generated += $this->generate_attachment( $id, $formats, $budget, $deadline );
			}
			$state['generated'] += $generated;
			$state['dirty'] = $state['dirty'] || $generated > 0 || $this->changed;
			$state['last_run'] = time();
			$remaining = $this->pending();
			if ( null === $remaining ) {
				$this->retry_failed_read( $state );
				return;
			}
			// Commit scan exhaustion only after the pending queue can be read successfully.
			$state['cursor'] = $scanned_state['cursor'];
			$state['active'] = $scanned_state['active'];
			$state['last_error'] = '';
			update_option( self::STATE, $state, false );
			if ( $remaining || $state['active'] ) {
				$this->schedule();
			} elseif ( $state['dirty'] ) {
				// Publish a completed scan once; purging every four encodes would repeatedly invalidate local CSS.
				noobrocket_clean_domain();
				$state['dirty'] = false;
				update_option( self::STATE, $state, false );
			}
		} finally {
			$this->unlock( $lock );
		}
	}

	private function generate_attachment( int $id, array $formats, int &$budget, float $deadline ): int {
		$files = $this->attachment_files( $id );
		if ( ! $files ) {
			delete_post_meta( $id, self::PENDING );
			return 0;
		}
		$owned = get_post_meta( $id, self::OWNED, true );
		$owned = is_array( $owned ) ? $owned : [];
		$generated = 0;
		$complete = true;
		$error = false;
		foreach ( $files as $file ) {
			if ( $budget <= 0 || microtime( true ) >= $deadline ) {
				$complete = false;
				break;
			}
			$source_digest = @hash_file( 'sha256', $file );
			if ( ! $source_digest ) {
				$error = true;
				$complete = false;
				continue;
			}
			foreach ( $formats as $format ) {
				$target = $file . '.' . $format;
				$relative = $this->relative_path( $file ) . '.' . $format;
				$ownership = $owned[ $relative ] ?? null;
				if ( is_array( $ownership ) && ! empty( $ownership['skipped'] ) && ( $ownership['source_digest'] ?? null ) === $source_digest ) {
					continue;
				}
				$existing_digest = false;
				if ( is_file( $target ) && ! is_link( $target ) ) {
					$relative = $this->relative_path( $target );
					$ownership = $owned[ $relative ] ?? null;
					// Legacy digest-only records remain owned, but refresh once to establish source identity.
					$existing_digest = is_string( $ownership ) ? $ownership : ( is_array( $ownership ) ? ( $ownership['digest'] ?? false ) : false );
					if ( ! $relative || ! is_string( $existing_digest )
						|| ! hash_equals( $existing_digest, (string) @hash_file( 'sha256', $target ) )
						|| ( is_array( $ownership ) && ( $ownership['source_digest'] ?? null ) === $source_digest
							&& filesize( $target ) <= floor( filesize( $file ) * ( 1 - self::MINIMUM_SAVING ) ) ) ) {
						continue;
					}
				}
				if ( $budget <= 0 || microtime( true ) >= $deadline ) {
					$complete = false;
					continue;
				}
				--$budget;
				$digest = ( file_exists( $target ) || is_link( $target ) ) && ! $existing_digest ? false : $this->encode( $file, $target, $format, $existing_digest );
				if ( null === $digest ) {
					$owned[ $relative ] = [ 'source_digest' => $source_digest, 'skipped' => true ];
					update_post_meta( $id, self::OWNED, $owned );
					continue;
				}
				if ( ! $digest ) {
					$error = true;
					$complete = false;
					continue;
				}
				$relative = $this->relative_path( $target );
				if ( ! $relative || ! $digest ) {
					@unlink( $target );
					$error = true;
					$complete = false;
					continue;
				}
				// Content identity prevents attachment deletion from removing a later external replacement.
				$owned[ $relative ] = [ 'digest' => $digest, 'source_digest' => $source_digest ];
				update_post_meta( $id, self::OWNED, $owned );
				++$generated;
			}
		}
		if ( $complete ) {
			delete_post_meta( $id, self::PENDING );
			delete_post_meta( $id, self::ERROR );
		} elseif ( $error ) {
			$attempts = (int) get_post_meta( $id, self::PENDING, true ) + 1;
			if ( $attempts >= 3 ) {
				delete_post_meta( $id, self::PENDING );
				update_post_meta( $id, self::ERROR, 'encode_failed' );
			} else {
				update_post_meta( $id, self::PENDING, $attempts );
			}
		}
		return $generated;
	}

	private function encode( string $source, string $target, string $format, $existing_digest = false ) {
		$stem = dirname( $source ) . '/.noobrocket-image-' . wp_generate_uuid4();
		$temp = $stem . '.' . $format;
		$saved = null;
		try {
			$editor = wp_get_image_editor( $source );
			if ( is_wp_error( $editor ) ) {
				return false;
			}
			$saved = $editor->save( $temp, 'image/' . $format );
			if ( is_wp_error( $saved ) || ( $saved['path'] ?? '' ) !== $temp || ( $saved['mime-type'] ?? '' ) !== 'image/' . $format || ! is_file( $temp ) || ! filesize( $temp ) ) {
				return false;
			}
			if ( filesize( $temp ) > floor( filesize( $source ) * ( 1 - self::MINIMUM_SAVING ) ) ) {
				if ( $existing_digest ) {
					if ( ! is_file( $target ) || is_link( $target ) || ! hash_equals( $existing_digest, (string) @hash_file( 'sha256', $target ) ) || ! @unlink( $target ) ) {
						return false;
					}
					$this->changed = true;
				} elseif ( file_exists( $target ) || is_link( $target ) ) {
					return false;
				}
				return null; // Intentional skip, not a failed encode to retry.
			}
			$digest = hash_file( 'sha256', $temp );
			if ( $existing_digest ) {
				// Keep the previous bytes available until a completed replacement is ready; external replacements stay intact.
				return $digest && is_file( $target ) && ! is_link( $target )
					&& hash_equals( $existing_digest, (string) @hash_file( 'sha256', $target ) ) && @rename( $temp, $target ) ? $digest : false;
			}
			// A same-directory hard link publishes complete bytes atomically and cannot replace an existing sidecar.
			return $digest && ! file_exists( $target ) && ! is_link( $target ) && @link( $temp, $target ) ? $digest : false;
		} catch ( \Throwable $error ) {
			return false;
		} finally {
			if ( is_file( $temp ) ) {
				@unlink( $temp );
			}
			if ( is_array( $saved ) && is_string( $saved['path'] ?? null ) && preg_match( '~^' . preg_quote( $stem, '~' ) . '\.[a-z0-9]+$~i', $saved['path'] ) && is_file( $saved['path'] ) ) {
				@unlink( $saved['path'] );
			}
		}
	}

	/** Include the displayed original, pre-scaling original and every recorded thumbnail. */
	private function attachment_files( int $id ): array {
		if ( ! in_array( get_post_mime_type( $id ), [ 'image/jpeg', 'image/png' ], true ) ) {
			return [];
		}
		$file = get_attached_file( $id, true );
		if ( ! is_string( $file ) || ! $this->relative_path( $file ) ) {
			return [];
		}
		$metadata = wp_get_attachment_metadata( $id );
		$files = [ $file ];
		$names = [];
		if ( ! empty( $metadata['original_image'] ) ) {
			$names[] = $metadata['original_image'];
		}
		foreach ( $metadata['sizes'] ?? [] as $size ) {
			$names[] = $size['file'] ?? '';
		}
		foreach ( $names as $name ) {
			if ( is_string( $name ) && basename( $name ) === $name && false === strpos( $name, '\\' ) ) {
				$files[] = dirname( $file ) . '/' . $name;
			}
		}
		return array_values( array_unique( array_filter( $files, function ( $path ) {
			return preg_match( '/\.(?:jpe?g|png)$/i', $path ) && $this->relative_path( $path );
		} ) ) );
	}

	public function delete_generated_files( $attachment_id ): void {
		$lock = $this->lock( true );
		if ( ! $lock ) {
			return;
		}
		try {
			$owned = get_post_meta( (int) $attachment_id, self::OWNED, true );
			$uploads = wp_upload_dir( null, false );
			foreach ( is_array( $owned ) ? $owned : [] as $relative => $ownership ) {
				$digest = is_string( $ownership ) ? $ownership : ( is_array( $ownership ) ? ( $ownership['digest'] ?? false ) : false );
				if ( ! is_string( $relative ) || ! is_string( $digest ) || ! preg_match( '/\.(?:jpe?g|png)\.(?:avif|webp)$/i', $relative ) ) {
					continue;
				}
				$file = trailingslashit( $uploads['basedir'] ) . $relative;
				if ( $relative === $this->relative_path( $file ) && ! is_link( $file ) && hash_equals( $digest, (string) @hash_file( 'sha256', $file ) ) ) {
					@unlink( $file );
				}
			}
			delete_post_meta( (int) $attachment_id, self::PENDING );
			delete_post_meta( (int) $attachment_id, self::OWNED );
			delete_post_meta( (int) $attachment_id, self::ERROR );
		} finally {
			$this->unlock( $lock );
		}
	}

	private function relative_path( string $file ) {
		$uploads = wp_upload_dir( null, false );
		$root = realpath( $uploads['basedir'] );
		$path = realpath( $file );
		if ( ! $root || ! $path || ! is_file( $path ) ) {
			return false;
		}
		$prefix = trailingslashit( wp_normalize_path( $root ) );
		$path = wp_normalize_path( $path );
		return 0 === strpos( $path, $prefix ) ? substr( $path, strlen( $prefix ) ) : false;
	}

	private function scan_library( array &$state ): bool {
		global $wpdb;
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type IN ('image/jpeg','image/png') AND ID > %d ORDER BY ID ASC LIMIT 20", $state['cursor'] ) );
		if ( $wpdb->last_error ) {
			return false;
		}
		foreach ( $ids as $id ) {
			$this->enqueue( (int) $id );
			$state['cursor'] = (int) $id;
		}
		if ( count( $ids ) < 20 ) {
			$state['active'] = false;
		}
		return true;
	}

	private function enqueue( int $id ): void {
		if ( ! metadata_exists( 'post', $id, self::PENDING ) ) {
			update_post_meta( $id, self::PENDING, 0 );
		}
		delete_post_meta( $id, self::ERROR );
	}

	/** Null means the native read failed; an empty array alone proves no pending work. */
	private function pending(): ?array {
		global $wpdb;
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s ORDER BY post_id ASC LIMIT 2", self::PENDING ) );
		return $wpdb->last_error ? null : array_map( 'intval', $ids );
	}

	private function retry_failed_read( array $state ): void {
		$state['last_error'] = 'database_read_failed';
		update_option( self::STATE, $state, false );
		$this->schedule();
	}

	private function state(): array {
		$state = get_option( self::STATE, [] );
		return ( is_array( $state ) ? $state : [] ) + [ 'active' => false, 'cursor' => 0, 'generated' => 0, 'last_run' => 0, 'last_error' => '', 'dirty' => false ];
	}

	private function enabled(): bool {
		return in_array( $this->options->get( 'image_delivery', 0 ), [ true, 1, '1' ], true );
	}

	private function schedule(): void {
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_single_event( time() + 30, self::CRON );
		}
	}

	/** A stable OS lock prevents concurrent cron invocations from encoding or publishing together. */
	private function lock( bool $wait ) {
		$uploads = wp_upload_dir( null, false );
		if ( ! empty( $uploads['error'] ) || ! is_dir( $uploads['basedir'] ) ) {
			return false;
		}
		$lock = @fopen( trailingslashit( $uploads['basedir'] ) . '.noobrocket-image-generator.lock', 'c' );
		if ( ! $lock ) {
			return false;
		}
		if ( ! flock( $lock, LOCK_EX | ( $wait ? 0 : LOCK_NB ) ) ) {
			fclose( $lock );
			return false;
		}
		return $lock;
	}

	private function unlock( $lock ): void {
		flock( $lock, LOCK_UN );
		fclose( $lock );
	}
}
