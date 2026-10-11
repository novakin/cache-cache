<?php
/** Standalone native-editor/queue contracts and real filesystem publication/ownership checks. */
declare(strict_types=1);

$fixture = sys_get_temp_dir() . '/noobrocket-image-generator-' . bin2hex( random_bytes( 6 ) );
mkdir( $fixture );
$attachments = [];
$postmeta = [];
$options = [];
$events = [];
$capabilities = [ 'avif' => true, 'webp' => true ];
$editor_state = [ 'fail' => false, 'collision' => false, 'wrong_format' => false, 'larger' => false ];
$encodes = 0;
$purges = 0;
$authorized = false;
function apply_filters( $hook, $value ) { return $value; }
function wp_upload_dir() { return [ 'basedir' => $GLOBALS['fixture'], 'error' => false ]; }
function wp_normalize_path( $path ) { return str_replace( '\\', '/', $path ); }
function trailingslashit( $path ) { return rtrim( $path, '/\\' ) . '/'; }
function wp_image_editor_supports( $args ) { return $GLOBALS['capabilities'][ substr( $args['mime_type'], 6 ) ] ?? false; }
function get_post_mime_type( $id ) { return $GLOBALS['attachments'][ $id ]['mime'] ?? false; }
function get_attached_file( $id ) { return $GLOBALS['attachments'][ $id ]['file'] ?? false; }
function wp_get_attachment_metadata( $id ) { return $GLOBALS['attachments'][ $id ]['metadata'] ?? false; }
function get_option( $name, $default = false ) { return $GLOBALS['options'][ $name ] ?? $default; }
function update_option( $name, $value ) { $GLOBALS['options'][ $name ] = $value; return true; }
function get_post_meta( $id, $key ) { return $GLOBALS['postmeta'][ $id ][ $key ] ?? ''; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['postmeta'][ $id ][ $key ] = $value; return true; }
function delete_post_meta( $id, $key ) { unset( $GLOBALS['postmeta'][ $id ][ $key ] ); return true; }
function metadata_exists( $type, $id, $key ) { return array_key_exists( $key, $GLOBALS['postmeta'][ $id ] ?? [] ); }
function wp_next_scheduled( $hook ) { return $GLOBALS['events'][ $hook ] ?? false; }
function wp_schedule_single_event( $time, $hook ) { $GLOBALS['events'][ $hook ] = $time; return true; }
function wp_clear_scheduled_hook( $hook ) { unset( $GLOBALS['events'][ $hook ] ); }
function wp_generate_uuid4() { return bin2hex( random_bytes( 16 ) ); }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function noobrocket_clean_domain() { $GLOBALS['purges']++; }
function current_user_can() { return $GLOBALS['authorized']; }
function esc_html__( $value ) { return $value; }
function wp_die( $message, $title = '', $args = [] ) { throw new RuntimeException( $message, $args['response'] ?? 0 ); }
class WP_Error {}
class GeneratorDatabase {
	public $posts = 'posts';
	public $postmeta = 'postmeta';
	public $last_error = '';
	public $reads = 0;
	public $fail_at = 0;
	public $on_failed_read = null;
	public function prepare( $sql, ...$args ) { return [ $sql, $args ]; }
	public function get_col( $query ) {
		$this->last_error = '';
		if ( ++$this->reads === $this->fail_at ) {
			$callback = $this->on_failed_read;
			$this->on_failed_read = null;
			if ( $callback ) { $callback(); }
			$this->last_error = 'Lost connection during owned SELECT';
			return [];
		}
		list( $sql, $args ) = $query;
		if ( false !== strpos( $sql, 'SELECT ID FROM' ) ) {
			$ids = [];
			foreach ( $GLOBALS['attachments'] as $id => $attachment ) {
				if ( $id > $args[0] && in_array( $attachment['mime'], [ 'image/jpeg', 'image/png' ], true ) ) { $ids[] = $id; }
			}
			sort( $ids );
			return array_slice( $ids, 0, 20 );
		}
		$ids = [];
		foreach ( $GLOBALS['postmeta'] as $id => $meta ) { if ( array_key_exists( $args[0], $meta ) ) { $ids[] = $id; } }
		sort( $ids );
		return array_slice( $ids, 0, 2 );
	}
	public function get_var( $query ) {
		$this->last_error = '';
		$count = 0;
		foreach ( $GLOBALS['postmeta'] as $meta ) { if ( array_key_exists( $query[1][0], $meta ) ) { $count++; } }
		return $count;
	}
}
class NativeEditorFixture {
	private $source;
	public function __construct( $source ) { $this->source = $source; }
	public function save( $path, $mime ) {
		$GLOBALS['encodes']++;
		if ( $GLOBALS['editor_state']['fail'] ) { return new WP_Error(); }
		if ( $GLOBALS['editor_state']['wrong_format'] ) { $path = preg_replace( '/\.[^.]+$/', '.png', $path ); $mime = 'image/png'; }
		$bytes = $GLOBALS['editor_state']['larger'] ? str_repeat( file_get_contents( $this->source ), 2 ) : generator_encoded_bytes( $this->source, substr( $mime, 6 ) );
		file_put_contents( $path, $bytes );
		if ( $GLOBALS['editor_state']['collision'] ) { file_put_contents( $this->source . '.' . substr( $mime, 6 ), 'Foreign concurrent sidecar' ); }
		return [ 'path' => $path, 'mime-type' => $mime ];
	}
}
function wp_get_image_editor( $source ) { return new NativeEditorFixture( $source ); }
function generator_encoded_bytes( $source, $format ) { return substr( hash( 'sha256', 'image/' . $format . ':' . file_get_contents( $source ) ), 0, 8 ); }
$wpdb = new GeneratorDatabase();
$root = dirname( __DIR__ ) . '/noobrocket/inc/';
require $root . 'classes/event-management/subscriber-interface.php';
require $root . 'classes/admin/class-options-data.php';
require $root . 'Engine/Media/Picture/Generator.php';
$checks = 0;
function generator_check( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
	$GLOBALS['checks']++;
}
function generator_batch( $generator ) { unset( $GLOBALS['events']['noobrocket_generate_images'] ); $generator->process_batch(); }
function generator_attachment( $id, $name, $mime = 'image/jpeg', $metadata = [] ) {
	$file = $GLOBALS['fixture'] . '/' . $name;
	file_put_contents( $file, 'Original fixture ' . $name );
	$GLOBALS['attachments'][ $id ] = [ 'file' => $file, 'mime' => $mime, 'metadata' => $metadata ];
	return $file;
}
try {
	$generator = new NoobRocket\Engine\Media\Picture\Generator( new NoobRocket\Admin\Options_Data( [ 'image_delivery' => 1 ] ) );
	$disabled = new NoobRocket\Engine\Media\Picture\Generator( new NoobRocket\Admin\Options_Data( [ 'image_delivery' => 0 ] ) );
	foreach ( [ 1 => 'initial pending', 2 => 'library scan', 3 => 'pending after scan', 4 => 'completion pending' ] as $stage => $label ) {
		$attachments = $postmeta = $options = $events = [];
		$encodes = $purges = 0;
		$wpdb->reads = 0;
		$wpdb->fail_at = $stage;
		generator_attachment( 21, 'read-failure-' . $stage . '.jpg' );
		$state = [ 'active' => true, 'cursor' => 20, 'generated' => 7, 'last_run' => 31, 'last_error' => '', 'dirty' => true ];
		$options['noobrocket_image_generation'] = $state;
		$postmeta[21] = [ 'unrelated' => 'retained' ];
		if ( 1 === $stage ) { $postmeta[21]['_noobrocket_image_pending'] = 2; }
		$before_meta = $postmeta;
		generator_batch( $generator );
		$failed = $options['noobrocket_image_generation'];
		generator_check( true === $failed['active'] && 20 === $failed['cursor'], $label . ': a failed SELECT preserves scan activity and cursor' );
		generator_check( 7 + ( 4 === $stage ? 2 : 0 ) === $failed['generated'] && true === $failed['dirty'], $label . ': failure retains historical totals, actual completed encodes and dirty publication' );
		generator_check( 'database_read_failed' === $failed['last_error'] && 1 === count( $events ) && 0 === $purges, $label . ': failure is recorded and retried without purging' );
		generator_check( 4 === $stage ? $failed['last_run'] > 31 : 31 === $failed['last_run'], $label . ': only actual encoding work advances the recorded batch time' );
		if ( $stage <= 2 ) {
			generator_check( $before_meta === $postmeta && 0 === $encodes, $label . ': early failure leaves pending attempts and attachment metadata intact' );
		} elseif ( 3 === $stage ) {
			generator_check( 0 === $postmeta[21]['_noobrocket_image_pending'] && 'retained' === $postmeta[21]['unrelated'] && 0 === $encodes, 'post-scan failure retains newly queued work without treating an unknown queue as empty' );
		} else {
			generator_check( ! metadata_exists( 'post', 21, '_noobrocket_image_pending' ) && 2 === count( $postmeta[21]['_noobrocket_image_files'] ), 'late failure keeps completed owned output rather than rolling back successful generation' );
		}
		$lock = fopen( $fixture . '/.noobrocket-image-generator.lock', 'c' );
		generator_check( flock( $lock, LOCK_EX | LOCK_NB ), $label . ': failed read releases the existing generator lock' );
		flock( $lock, LOCK_UN );
		fclose( $lock );
		$wpdb->fail_at = 0;
		$wpdb->last_error = 'stale previous query failure';
		for ( $batch = 0; $batch < 5 && ( $options['noobrocket_image_generation']['active'] || metadata_exists( 'post', 21, '_noobrocket_image_pending' ) ); $batch++ ) { generator_batch( $generator ); }
		$recovered = $options['noobrocket_image_generation'];
		generator_check( false === $recovered['active'] && 21 === $recovered['cursor'] && ! metadata_exists( 'post', 21, '_noobrocket_image_pending' ), $label . ': healthy reads finish the preserved scan and queue' );
		generator_check( 9 === $recovered['generated'] && '' === $recovered['last_error'] && false === $recovered['dirty'] && 1 === $purges && ! $events, $label . ': recovery clears the read error and publishes exactly once without recounting completed output' );
	}
	foreach ( [ 'queue_library', 'settings_changed' ] as $caller ) {
		$attachments = $postmeta = $options = $events = [];
		$encodes = $purges = 0;
		$wpdb->reads = 0;
		$wpdb->fail_at = 1;
		generator_attachment( 21, 'caller-failure-' . $caller . '.jpg' );
		$state = [ 'active' => false, 'cursor' => 77, 'generated' => 10, 'last_run' => 31, 'last_error' => '', 'dirty' => false ];
		$options['noobrocket_image_generation'] = $state;
		$postmeta[21] = [ '_noobrocket_image_pending' => 1, 'unrelated' => 'retained' ];
		$before_meta = $postmeta;
		if ( 'queue_library' === $caller ) { $generator->queue_library(); }
		else { $generator->settings_changed( [ 'image_delivery' => 0 ], [ 'image_delivery' => 1 ] ); }
		$failed = $options['noobrocket_image_generation'];
		generator_check( 77 === $failed['cursor'] && 10 === $failed['generated'] && false === $failed['dirty'] && 31 === $failed['last_run'], $caller . ': failed pending read cannot reset existing scan history' );
		generator_check( 'database_read_failed' === $failed['last_error'] && 1 === count( $events ) && $before_meta === $postmeta && 0 === $purges, $caller . ': unknown pending work retains metadata and schedules retry' );
		generator_check( ( 'queue_library' === $caller ) === $failed['active'], $caller . ': only an explicit backfill request starts scan activity' );
		$wpdb->fail_at = 0;
		for ( $batch = 0; $batch < 5 && ( $options['noobrocket_image_generation']['active'] || metadata_exists( 'post', 21, '_noobrocket_image_pending' ) ); $batch++ ) { generator_batch( $generator ); }
		generator_check( false === $options['noobrocket_image_generation']['active'] && 77 === $options['noobrocket_image_generation']['cursor'] && 12 === $options['noobrocket_image_generation']['generated'] && '' === $options['noobrocket_image_generation']['last_error'] && 1 === $purges && ! $events, $caller . ': healthy nonempty retry preserves the resume cursor, completes retained work and invalidates once' );
	}
	foreach ( [ 0, 1, 2, 3, 4 ] as $recovery_failure ) {
		$attachments = $postmeta = $options = $events = [];
		$encodes = $purges = 0;
		$wpdb->reads = 0;
		$wpdb->fail_at = 1;
		generator_attachment( 21, 'empty-backfill-' . $recovery_failure . '.jpg' );
		$options['noobrocket_image_generation'] = [ 'active' => false, 'cursor' => 77, 'generated' => 10, 'last_run' => 31, 'last_error' => '', 'dirty' => false ];
		$generator->queue_library();
		$requested = $options['noobrocket_image_generation'];
		generator_check( 77 === $requested['cursor'] && 10 === $requested['generated'] && 0 === $encodes, 'unknown explicit backfill preserves completed history before selecting resume or restart' );
		$wpdb->reads = 0;
		$wpdb->fail_at = $recovery_failure;
		generator_batch( $generator );
		$wpdb->fail_at = 0;
		for ( $batch = 0; $batch < 5 && ( $options['noobrocket_image_generation']['active'] || metadata_exists( 'post', 21, '_noobrocket_image_pending' ) ); ++$batch ) { generator_batch( $generator ); }
		$recovered = $options['noobrocket_image_generation'];
		generator_check( false === $recovered['active'] && 21 === $recovered['cursor'] && 12 === $recovered['generated'] && 2 === $encodes && metadata_exists( 'post', 21, '_noobrocket_image_files' ), 'unknown then empty backfill visits attachments before the completed cursor, including repeated read failures at stage ' . $recovery_failure );
		generator_check( empty( $recovered['backfill_pending'] ) && '' === $recovered['last_error'] && 1 === $purges && ! $events, 'completed explicit backfill clears deferred intent and publishes once' );
	}
	$attachments = $postmeta = $options = $events = [];
	$encodes = $purges = 0;
	$wpdb->reads = 0;
	$wpdb->fail_at = 1;
	generator_attachment( 21, 'settings-interleave.jpg' );
	$state = [ 'active' => false, 'cursor' => 77, 'generated' => 10, 'last_run' => 31, 'last_error' => '', 'dirty' => false ];
	$options['noobrocket_image_generation'] = $state;
	$postmeta[21] = [ '_noobrocket_image_pending' => 1 ];
	$wpdb->on_failed_read = static function () use ( $generator ) { generator_batch( $generator ); };
	$generator->settings_changed( [ 'image_delivery' => 0 ], [ 'image_delivery' => 1 ] );
	generator_check( 0 === $encodes && 10 === $options['noobrocket_image_generation']['generated'] && 31 === $options['noobrocket_image_generation']['last_run'], 'settings failure holds the existing lock against a controlled actual-worker interleave' );
	$wpdb->fail_at = 0;
	generator_batch( $generator );
	generator_check( 2 === $encodes && 12 === $options['noobrocket_image_generation']['generated'] && 1 === $purges && ! $events, 'healthy worker after settings failure retains actual progress and publishes once' );
	$before = $options['noobrocket_image_generation'];
	$wpdb->reads = 0;
	$lock = fopen( $fixture . '/.noobrocket-image-generator.lock', 'c' );
	flock( $lock, LOCK_EX );
	try { $generator->settings_changed( [ 'image_delivery' => 0 ], [ 'image_delivery' => 1 ] ); }
	finally { flock( $lock, LOCK_UN ); fclose( $lock ); }
	generator_check( 0 === $wpdb->reads && $before === $options['noobrocket_image_generation'] && 1 === count( $events ), 'contended settings activation schedules retry without reading or rewriting worker state' );
	$attachments = $postmeta = $options = $events = [];
	$encodes = $purges = 0;
	$wpdb->reads = $wpdb->fail_at = 0;
	$original = generator_attachment( 1, 'photo-scaled.jpg', 'image/jpeg', [ 'original_image' => 'photo.jpg', 'sizes' => [ 'small' => [ 'file' => 'photo-150.jpg' ], 'medium' => [ 'file' => 'photo-300.jpg' ], 'unsafe' => [ 'file' => '../outside.jpg' ] ] ] );
	foreach ( [ 'photo.jpg', 'photo-150.jpg', 'photo-300.jpg' ] as $name ) { file_put_contents( $fixture . '/' . $name, 'Original ' . $name ); }
	$metadata = $attachments[1]['metadata'];
	generator_check( $metadata === $disabled->queue_attachment( $metadata, 1 ) && ! $events && ! $postmeta, 'disabled delivery never queues or encodes uploads' );
	generator_check( $metadata === $generator->queue_attachment( $metadata, 1 ) && 0 === $encodes && 1 === count( $events ), 'new upload queues without frontend encoding or changing attachment metadata' );
	$generator->queue_attachment( $metadata, 1 );
	generator_check( 1 === count( $events ), 'cron requests deduplicate' );
	$lock = fopen( $fixture . '/.noobrocket-image-generator.lock', 'c' );
	flock( $lock, LOCK_EX );
	generator_batch( $generator );
	generator_check( 0 === $encodes && 1 === count( $events ), 'actual OS lock prevents a concurrent generator from encoding' );
	flock( $lock, LOCK_UN );
	fclose( $lock );
	generator_batch( $generator );
	generator_check( 4 === $encodes && 1 === $generator->status()['pending'] && 0 === $purges, 'one batch performs at most four encodes and preserves warm pages until the family completes' );
	generator_batch( $generator );
	generator_check( 8 === $encodes && 0 === $generator->status()['pending'] && 1 === $purges, 'second batch completes the family and invalidates pages once' );
	foreach ( [ 'photo-scaled.jpg', 'photo.jpg', 'photo-150.jpg', 'photo-300.jpg' ] as $name ) {
		generator_check( is_file( $fixture . '/' . $name . '.avif' ) && is_file( $fixture . '/' . $name . '.webp' ), 'each attachment-family file has appended AVIF/WebP sidecars' );
	}
	generator_check( 'Original fixture photo-scaled.jpg' === file_get_contents( $original ) && ! is_file( $fixture . '/photo-scaled.webp' ), 'original preserved and stem-colliding derivative never created' );
	generator_check( ! glob( $fixture . '/.noobrocket-image-*.avif' ) && ! glob( $fixture . '/.noobrocket-image-*.webp' ), 'all encoding temporary files removed after atomic publication' );
	$original_time = filemtime( $original . '.avif' );
	file_put_contents( $original, 'Regenerated original' );
	touch( $original, $original_time );
	clearstatcache();
	$before = $encodes;
	$generator->queue_attachment( $metadata, 1 );
	generator_batch( $generator );
	generator_check( $before + 2 === $encodes && generator_encoded_bytes( $original, 'avif' ) === file_get_contents( $original . '.avif' ) && generator_encoded_bytes( $original, 'webp' ) === file_get_contents( $original . '.webp' ), 'same-second metadata regeneration refreshes owned sidecars from changed source bytes' );
	$before = $encodes;
	$generator->queue_attachment( $metadata, 1 );
	generator_batch( $generator );
	generator_check( $before === $encodes, 'unchanged source and fresh derivatives do not encode again' );
	$legacy_key = basename( $original ) . '.avif';
	$postmeta[1]['_noobrocket_image_files'][ $legacy_key ] = $postmeta[1]['_noobrocket_image_files'][ $legacy_key ]['digest'];
	$before = $encodes;
	$generator->queue_attachment( $metadata, 1 );
	generator_batch( $generator );
	generator_check( $before + 1 === $encodes && is_array( $postmeta[1]['_noobrocket_image_files'][ $legacy_key ] ) && $postmeta[1]['_noobrocket_image_files'][ $legacy_key ]['source_digest'] === hash_file( 'sha256', $original ), 'legacy digest-only ownership refreshes once and acquires source identity' );
	file_put_contents( $original . '.webp', 'Later foreign replacement' );
	touch( $original . '.webp', time() - 5 );
	touch( $original . '.avif', time() - 5 );
	file_put_contents( $original, 'Second regeneration' );
	clearstatcache();
	$generator->queue_attachment( $metadata, 1 );
	generator_batch( $generator );
	generator_check( 'Later foreign replacement' === file_get_contents( $original . '.webp' ) && generator_encoded_bytes( $original, 'avif' ) === file_get_contents( $original . '.avif' ), 'source changes refresh owned output while preserving a later external replacement' );
	$postmeta[1]['_noobrocket_image_files'][ $legacy_key ] = $postmeta[1]['_noobrocket_image_files'][ $legacy_key ]['digest'];
	$generator->delete_generated_files( 1 );
	generator_check( is_file( $original ) && is_file( $original . '.webp' ) && 'Later foreign replacement' === file_get_contents( $original . '.webp' ) && ! is_file( $original . '.avif' ), 'deletion accepts legacy ownership and removes only unchanged sidecars, preserving original and external replacement' );
	$png = generator_attachment( 2, 'existing.png', 'image/png' );
	file_put_contents( $png . '.webp', 'Existing external WebP' );
	$generator->queue_attachment( [], 2 );
	generator_batch( $generator );
	generator_check( 'Existing external WebP' === file_get_contents( $png . '.webp' ) && 1 === count( $postmeta[2]['_noobrocket_image_files'] ), 'pre-existing external derivative stays unchanged and never becomes owned' );
	$generator->delete_generated_files( 2 );
	generator_check( is_file( $png . '.webp' ) && ! is_file( $png . '.avif' ), 'attachment cleanup preserves unowned external sidecar' );
	$gif = generator_attachment( 3, 'animated.gif', 'image/gif' );
	$generator->queue_attachment( [], 3 );
	generator_check( ! metadata_exists( 'post', 3, '_noobrocket_image_pending' ), 'animated GIFs do not undergo lossy still-image conversion' );
	$capabilities = [ 'avif' => false, 'webp' => false ];
	$generator->queue_attachment( [], 2 );
	$before = $encodes;
	generator_batch( $generator );
	generator_check( $before === $encodes && 'unsupported_encoders' === $generator->status()['last_error'] && 1 === $generator->status()['pending'] && ! $events, 'unsupported codecs report capabilities and preserve resumable queue without a busy cron loop' );
	$capabilities = [ 'avif' => true, 'webp' => true ];
	$generator->queue_library();
	generator_batch( $generator );
	generator_check( is_file( $png . '.avif' ) && 'Existing external WebP' === file_get_contents( $png . '.webp' ), 'capability recovery resumes queued media and preserves external files' );
	$race = generator_attachment( 4, 'race.jpg' );
	$editor_state['collision'] = true;
	$generator->queue_attachment( [], 4 );
	generator_batch( $generator );
	generator_check( 'Foreign concurrent sidecar' === file_get_contents( $race . '.avif' ) && ! metadata_exists( 'post', 4, '_noobrocket_image_files' ), 'exclusive publication cannot overwrite or claim a sidecar created during encoding' );
	$editor_state['collision'] = false;
	$error_file = generator_attachment( 5, 'error.jpg' );
	$editor_state['fail'] = true;
	$generator->queue_attachment( [], 5 );
	for ( $batch = 0; $batch < 3; $batch++ ) { generator_batch( $generator ); }
	generator_check( ! metadata_exists( 'post', 5, '_noobrocket_image_pending' ) && 'encode_failed' === get_post_meta( 5, '_noobrocket_image_error' ), 'encoding failures stop after three attempts and remain visible' );
	$editor_state['fail'] = false;
	$editor_state['wrong_format'] = true;
	$generator->queue_attachment( [], 5 );
	generator_batch( $generator );
	generator_check( ! glob( $fixture . '/.noobrocket-image-*.png' ) && ! is_file( $error_file . '.avif' ), 'mismatched editor output never publishes incorrect MIME and cleans owned temporary output' );
	$editor_state['wrong_format'] = false;
	$generator->settings_changed( [ 'image_delivery' => 1 ], [ 'image_delivery' => 0 ] );
	generator_check( ! $events && metadata_exists( 'post', 5, '_noobrocket_image_pending' ), 'disabling clears cron while preserving resumable queue' );
	$_SERVER['REQUEST_METHOD'] = 'POST';
	try { $generator->handle_backfill(); generator_check( false, 'unauthorized backfill must fail' ); }
	catch ( RuntimeException $error ) { generator_check( 403 === $error->getCode(), 'backfill requires native administrator capability before nonce or queue work' ); }
	$generator->delete_generated_files( 5 );
	$generator->queue_library();
	for ( $batch = 0; $batch < 20 && ( $generator->status()['active'] || $generator->status()['pending'] ); $batch++ ) { generator_batch( $generator ); }
	generator_check( ! $generator->status()['active'] && 0 === $generator->status()['pending'] && 5 === $generator->status()['cursor'], 'library backfill reaches its cursor endpoint and drains all attachments' );
	generator_check( ! is_file( $gif . '.webp' ) && ! is_file( $gif . '.avif' ), 'backfill also preserves animated GIF originals' );
	$replacement = generator_attachment( 6, 'replacement.jpg' );
	$generator->queue_attachment( [], 6 );
	generator_batch( $generator );
	$original_time = filemtime( $replacement . '.avif' );
	file_put_contents( $replacement, 'Media replacement without metadata regeneration' );
	touch( $replacement, $original_time );
	clearstatcache();
	$before = $encodes;
	$generator->queue_library();
	for ( $batch = 0; $batch < 20 && ( $generator->status()['active'] || $generator->status()['pending'] ); $batch++ ) { generator_batch( $generator ); }
	generator_check( $before + 2 === $encodes && generator_encoded_bytes( $replacement, 'avif' ) === file_get_contents( $replacement . '.avif' ) && generator_encoded_bytes( $replacement, 'webp' ) === file_get_contents( $replacement . '.webp' ), 'explicit backfill discovers changed source bytes even when timestamps remain equal' );
	file_put_contents( $replacement, 'Race during owned refresh' );
	$editor_state['collision'] = true;
	$generator->queue_attachment( [], 6 );
	generator_batch( $generator );
	generator_check( 'Foreign concurrent sidecar' === file_get_contents( $replacement . '.avif' ) && 'Foreign concurrent sidecar' === file_get_contents( $replacement . '.webp' ) && $postmeta[6]['_noobrocket_image_files']['replacement.jpg.avif']['digest'] !== hash_file( 'sha256', $replacement . '.avif' ), 'a foreign replacement during owned refresh is neither overwritten nor claimed' );
	$editor_state['collision'] = false;
	generator_batch( $generator );
	$larger = generator_attachment( 7, 'larger.png', 'image/png' );
	$editor_state['larger'] = true;
	$generator->queue_attachment( [], 7 );
	$before = $encodes;
	generator_batch( $generator );
	generator_check( $before + 2 === $encodes && ! is_file( $larger . '.avif' ) && ! is_file( $larger . '.webp' ) && ! metadata_exists( 'post', 7, '_noobrocket_image_pending' ), 'larger encodes are intentional completed skips and never preferred artifacts' );
	generator_check( ! empty( $postmeta[7]['_noobrocket_image_files']['larger.png.avif']['skipped'] ) && ! metadata_exists( 'post', 7, '_noobrocket_image_error' ), 'skip records carry source identity without reporting an encode failure' );
	$generator->queue_attachment( [], 7 );
	generator_batch( $generator );
	generator_check( $before + 2 === $encodes, 'unchanged skipped sources never encode repeatedly' );
	$editor_state['larger'] = false;
	file_put_contents( $larger, 'Changed source permits new beneficial encodes' );
	$generator->queue_attachment( [], 7 );
	generator_batch( $generator );
	generator_check( is_file( $larger . '.avif' ) && is_file( $larger . '.webp' ), 'changed source resets intentional skip and can publish beneficial derivatives' );
	$editor_state['larger'] = true;
	file_put_contents( $larger, 'Another source replacement' );
	$generator->queue_attachment( [], 7 );
	$before_purges = $purges;
	generator_batch( $generator );
	generator_check( ! is_file( $larger . '.avif' ) && ! is_file( $larger . '.webp' ) && $purges === $before_purges + 1, 'unbeneficial replacement removes only previous owned output and invalidates its delivered markup' );
	$editor_state['larger'] = false;
	echo 'PASS: native image generator (', $checks, " assertions; standalone editor contracts and filesystem)\n";
} finally {
	// Fixture contains only files created above; no recursive deletion or external paths.
	foreach ( glob( $fixture . '/*' ) as $file ) { if ( is_file( $file ) ) { unlink( $file ); } }
	foreach ( glob( $fixture . '/.*' ) as $file ) { if ( is_file( $file ) ) { unlink( $file ); } }
	rmdir( $fixture );
}
