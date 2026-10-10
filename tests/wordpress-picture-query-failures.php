<?php
/** Native wpdb refusal and recovery at each registered image-generator read boundary. */
$wordpress = isset( $argv[1] ) ? realpath( $argv[1] ) : false;
if ( ! $wordpress || ! is_file( $wordpress . '/.noobrocket-acceptance-site' ) || ! is_file( $wordpress . '/wp-load.php' ) || ! function_exists( 'imagecreatetruecolor' ) || ! function_exists( 'imagewebp' ) ) {
	fwrite( STDERR, "Provide a marked copied-plugin WordPress site and PHP GD with WebP.\n" );
	exit( 2 );
}
$source = dirname( __DIR__ ) . '/noobrocket';
$installed = $wordpress . '/wp-content/plugins/noobrocket';
$normalize = static function ( $path ) { return str_replace( '\\', '/', $path ); };
if ( ! realpath( $installed ) || $normalize( realpath( $installed ) ) !== $normalize( $installed ) || realpath( $source ) === realpath( $installed ) ) {
	throw new RuntimeException( 'Native image proof requires a separate plugin copy.' );
}
$owner_file = '/inc/Engine/Media/Picture/Generator.php';
if ( hash_file( 'sha256', $source . $owner_file ) !== hash_file( 'sha256', $installed . $owner_file ) ) { throw new RuntimeException( 'Copy the current image generator before native proof.' ); }
$complete = false;
register_shutdown_function( static function () use ( &$complete ) {
	register_shutdown_function( static function () use ( &$complete ) {
		if ( ! $complete ) { fwrite( STDERR, "FAIL: native picture query fixture did not complete.\n" ); exit( 2 ); }
	} );
} );
$temporary = $wordpress . '/wp-content/pq-' . bin2hex( random_bytes( 4 ) );
if ( ! mkdir( $temporary ) || ! mkdir( $temporary . '/uploads' ) ) { throw new RuntimeException( 'Cannot create owned image fixture files.' ); }
define( 'NOOBROCKET_CACHE_ROOT_PATH', $temporary . '/cache/' );
define( 'NOOBROCKET_CONFIG_PATH', $temporary . '/config/' );
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
define( 'WP_ADMIN', true );
$_SERVER['HTTP_HOST'] = '127.0.0.1';
$_SERVER['REQUEST_URI'] = '/wp-admin/';
$_SERVER['REQUEST_METHOD'] = 'POST';
require $wordpress . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
wp_set_current_user( 1 );
$container = apply_filters( 'noobrocket_container', null );
$generator = $container->get( 'picture_generator' );
$saved = get_option( 'noobrocket_image_generation' );
$saved_cron = get_option( 'cron' );
$saved_purge_count = $GLOBALS['wp_actions']['noobrocket_after_clean_domain'] ?? null;
$suppress = $wpdb->suppress_errors( true );
$enabled = static function () { return true; };
$uploads = static function ( $value ) use ( $temporary ) {
	$value['path'] = $value['basedir'] = $temporary . '/uploads';
	$value['url'] = $value['baseurl'] = content_url( substr( $temporary, strlen( WP_CONTENT_DIR ) ) . '/uploads' );
	$value['subdir'] = '';
	$value['error'] = false;
	return $value;
};
add_filter( 'pre_get_noobrocket_option_image_delivery', $enabled );
add_filter( 'upload_dir', $uploads );
$checks = 0;
$check = static function ( $condition, $message ) use ( &$checks ) {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
	++$checks;
};
$ids = [];
$make_attachment = static function ( $name, $seed ) use ( $temporary, &$ids ) {
	$source_file = $temporary . '/uploads/' . $name . '.png';
	$image = imagecreatetruecolor( 64, 64 );
	mt_srand( $seed );
	for ( $y = 0; $y < 64; ++$y ) { for ( $x = 0; $x < 64; ++$x ) { imagesetpixel( $image, $x, $y, mt_rand( 0, 0xffffff ) ); } }
	if ( ! imagepng( $image, $source_file, 0 ) ) { throw new RuntimeException( 'Cannot write native PNG source.' ); }
	imagedestroy( $image );
	$id = wp_insert_attachment( [ 'post_mime_type' => 'image/png', 'post_title' => 'Owned picture query ' . $name, 'post_status' => 'inherit' ], $source_file, 0, true );
	if ( is_wp_error( $id ) ) { throw new RuntimeException( 'Cannot create owned attachment.' ); }
	$ids[] = $id;
	wp_update_attachment_metadata( $id, [ 'file' => basename( $source_file ), 'width' => 64, 'height' => 64, 'sizes' => [] ] );
	update_post_meta( $id, '_picture_query_owned', 'retained' );
	return $id;
};
$refuse = null;
$purge = null;
try {
	$check( $generator instanceof NoobRocket\Engine\Media\Picture\Generator && has_action( 'noobrocket_generate_images', [ $generator, 'process_batch' ] ) !== false, 'production container and cron hook supply the registered image owner' );
	$check( $normalize( ( new ReflectionClass( $generator ) )->getFileName() ) === $normalize( $installed . $owner_file ), 'native image owner loads from the selected copied plugin' );
	$check( (bool) array_filter( $generator->capabilities() ), 'native image editor supports an actual modern format' );
	$check( 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_noobrocket_image_pending'" ), 'disposable site has no unrelated pending image work' );
	foreach ( [ 1 => 'initial pending', 2 => 'library scan', 3 => 'post-scan pending', 4 => 'completion pending' ] as $stage => $label ) {
		$id = $make_attachment( 'r' . $stage, 79 + $stage );
		if ( 1 === $stage ) { update_post_meta( $id, '_noobrocket_image_pending', 2 ); }
		$before_meta = get_post_meta( $id );
		$state = [ 'active' => true, 'cursor' => $id - 1, 'generated' => 7, 'last_run' => 31, 'last_error' => '', 'dirty' => true ];
		update_option( 'noobrocket_image_generation', $state, false );
		unset( $GLOBALS['wp_actions']['noobrocket_after_clean_domain'] ); // Each batch normally starts in a fresh cron request.
		$reads = $refusals = $purges = 0;
		$purge = static function () use ( &$purges ) { ++$purges; };
		add_action( 'before_noobrocket_clean_domain', $purge );
		$refuse = static function ( $query ) use ( &$reads, &$refusals, $stage, $wpdb ) {
			if ( 0 !== strpos( $query, "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = " ) && 0 !== strpos( $query, "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment'" ) ) { return $query; }
			if ( ++$reads !== $stage ) { return $query; }
			++$refusals;
			return str_replace( [ "FROM {$wpdb->postmeta}", "FROM {$wpdb->posts}" ], 'FROM ' . $wpdb->prefix . 'picture_query_missing', $query );
		};
		add_filter( 'query', $refuse, PHP_INT_MAX );
		wp_clear_scheduled_hook( 'noobrocket_generate_images' );
		do_action( 'noobrocket_generate_images' );
		remove_filter( 'query', $refuse, PHP_INT_MAX );
		$refuse = null;
		$failed = get_option( 'noobrocket_image_generation' );
		$check( 1 === $refusals && true === $failed['active'] && $state['cursor'] === $failed['cursor'], $label . ': native SQL refusal retains scan activity and cursor' );
		$check( 'database_read_failed' === $failed['last_error'] && true === $failed['dirty'] && wp_next_scheduled( 'noobrocket_generate_images' ) && 0 === $purges, $label . ': failed read records retry without domain purge' );
		if ( $stage <= 2 ) {
			$check( $before_meta === get_post_meta( $id ) && 7 === $failed['generated'] && 31 === $failed['last_run'], $label . ': early native failure preserves pending attempts and saved history' );
		} elseif ( 3 === $stage ) {
			$check( '0' === get_post_meta( $id, '_noobrocket_image_pending', true ) && 7 === $failed['generated'] && 31 === $failed['last_run'], 'post-scan refusal retains newly queued native metadata without publishing scan exhaustion' );
		} else {
			$owned = get_post_meta( $id, '_noobrocket_image_files', true );
			$published = 0;
			foreach ( is_array( $owned ) ? $owned : [] as $relative => $record ) {
				if ( empty( $record['digest'] ) ) { continue; }
				++$published;
				$check( is_file( $temporary . '/uploads/' . $relative ) && hash_file( 'sha256', $temporary . '/uploads/' . $relative ) === $record['digest'], 'late failed read retains actual native-editor published bytes and ownership' );
			}
			$check( $published > 0 && 7 + $published === $failed['generated'] && ! metadata_exists( 'post', $id, '_noobrocket_image_pending' ), 'late refusal records actual completed encodes rather than discarding them' );
		}
		$lock = fopen( $temporary . '/uploads/.noobrocket-image-generator.lock', 'c' );
		$check( flock( $lock, LOCK_EX | LOCK_NB ), $label . ': native failed read releases the generator lock' );
		flock( $lock, LOCK_UN ); fclose( $lock );
		$wpdb->last_error = 'stale failure from the previous query';
		for ( $batch = 0; $batch < 5 && ( get_option( 'noobrocket_image_generation' )['active'] || metadata_exists( 'post', $id, '_noobrocket_image_pending' ) ); ++$batch ) {
			wp_clear_scheduled_hook( 'noobrocket_generate_images' );
			do_action( 'noobrocket_generate_images' );
		}
		$recovered = get_option( 'noobrocket_image_generation' );
		$owned = get_post_meta( $id, '_noobrocket_image_files', true );
		$published = count( array_filter( is_array( $owned ) ? $owned : [], static function ( $record ) { return is_array( $record ) && ! empty( $record['digest'] ); } ) );
		$check( false === $recovered['active'] && $id === $recovered['cursor'] && '' === $recovered['last_error'] && false === $recovered['dirty'] && ! metadata_exists( 'post', $id, '_noobrocket_image_pending' ), $label . ': healthy native reads finish the preserved scan and pending work' );
		$check( $published > 0 && 7 + $published === $recovered['generated'] && 1 === $purges && ! wp_next_scheduled( 'noobrocket_generate_images' ) && 'retained' === get_post_meta( $id, '_picture_query_owned', true ), $label . ': recovery counts completed output once, publishes once, stops retry and retains unrelated attachment metadata' );
		remove_action( 'before_noobrocket_clean_domain', $purge );
		$purge = null;
		wp_delete_attachment( $id, true );
	}
	foreach ( [ 'empty', 'nonempty' ] as $queue ) {
		$id = $make_attachment( 'backfill-' . $queue, 85 );
		if ( 'nonempty' === $queue ) { update_post_meta( $id, '_noobrocket_image_pending', 2 ); }
		$state = [ 'active' => false, 'cursor' => $id + 77, 'generated' => 7, 'last_run' => 31, 'last_error' => '', 'dirty' => false ];
		update_option( 'noobrocket_image_generation', $state, false );
		$before_meta = get_post_meta( $id );
		unset( $GLOBALS['wp_actions']['noobrocket_after_clean_domain'] );
		$purges = 0;
		$purge = static function () use ( &$purges ) { ++$purges; };
		add_action( 'before_noobrocket_clean_domain', $purge );
		$refuse = static function ( $query ) use ( $wpdb ) {
			return 0 === strpos( $query, "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = " ) ? str_replace( "FROM {$wpdb->postmeta}", 'FROM ' . $wpdb->prefix . 'picture_query_missing', $query ) : $query;
		};
		add_filter( 'query', $refuse, PHP_INT_MAX );
		$generator->queue_library();
		remove_filter( 'query', $refuse, PHP_INT_MAX );
		$refuse = null;
		$requested = get_option( 'noobrocket_image_generation' );
		$check( true === $requested['active'] && true === $requested['backfill_pending'] && $state['cursor'] === $requested['cursor'] && 7 === $requested['generated'] && 31 === $requested['last_run'], 'native explicit backfill retains history and deferred intent while the pending queue is unknown: ' . $queue );
		$check( $before_meta === get_post_meta( $id ) && 'database_read_failed' === $requested['last_error'] && wp_next_scheduled( 'noobrocket_generate_images' ) && 0 === $purges, 'native failed backfill schedules retry without metadata mutation or purge: ' . $queue );
		for ( $batch = 0; $batch < 5 && get_option( 'noobrocket_image_generation' )['active']; ++$batch ) {
			wp_clear_scheduled_hook( 'noobrocket_generate_images' );
			do_action( 'noobrocket_generate_images' );
		}
		$recovered = get_option( 'noobrocket_image_generation' );
		$owned = get_post_meta( $id, '_noobrocket_image_files', true );
		$published = count( array_filter( is_array( $owned ) ? $owned : [], static function ( $record ) { return is_array( $record ) && ! empty( $record['digest'] ); } ) );
		$check( false === $recovered['active'] && false === $recovered['backfill_pending'] && ( 'empty' === $queue ? $id : $state['cursor'] ) === $recovered['cursor'] && $published > 0 && 7 + $published === $recovered['generated'], 'native healthy backfill chooses fresh full scan for empty work and existing resume cursor for pending work: ' . $queue );
		$check( '' === $recovered['last_error'] && 1 === $purges && ! wp_next_scheduled( 'noobrocket_generate_images' ) && ! metadata_exists( 'post', $id, '_noobrocket_image_pending' ), 'native backfill recovery completes and publishes once: ' . $queue );
		remove_action( 'before_noobrocket_clean_domain', $purge );
		$purge = null;
		wp_delete_attachment( $id, true );
	}
	$state = [ 'active' => false, 'cursor' => 77, 'generated' => 17, 'last_run' => 31, 'last_error' => '', 'dirty' => false ];
	update_option( 'noobrocket_image_generation', $state, false );
	wp_clear_scheduled_hook( 'noobrocket_generate_images' );
	$lock = fopen( $temporary . '/uploads/.noobrocket-image-generator.lock', 'c' );
	if ( ! flock( $lock, LOCK_EX | LOCK_NB ) ) { throw new RuntimeException( 'Cannot hold owned native generator lock.' ); }
	try {
		$generator->settings_changed( [ 'image_delivery' => 0 ], [ 'image_delivery' => 1 ] );
		$check( $state === get_option( 'noobrocket_image_generation' ) && wp_next_scheduled( 'noobrocket_generate_images' ), 'native contended settings activation schedules retry without overwriting worker state' );
		$generator->settings_changed( [ 'image_delivery' => 1 ], [ 'image_delivery' => 0 ] );
		$check( $state === get_option( 'noobrocket_image_generation' ) && ! wp_next_scheduled( 'noobrocket_generate_images' ), 'native disabled settings clear retry even when the generator lock is held' );
	} finally { flock( $lock, LOCK_UN ); fclose( $lock ); }
} finally {
	if ( $refuse ) { remove_filter( 'query', $refuse, PHP_INT_MAX ); }
	if ( $purge ) { remove_action( 'before_noobrocket_clean_domain', $purge ); }
	foreach ( $ids as $id ) { if ( get_post( $id ) ) { wp_delete_attachment( $id, true ); } }
	if ( false === $saved ) { delete_option( 'noobrocket_image_generation' ); } else { update_option( 'noobrocket_image_generation', $saved, false ); }
	if ( false === $saved_cron ) { delete_option( 'cron' ); } else { update_option( 'cron', $saved_cron ); }
	if ( null === $saved_purge_count ) { unset( $GLOBALS['wp_actions']['noobrocket_after_clean_domain'] ); } else { $GLOBALS['wp_actions']['noobrocket_after_clean_domain'] = $saved_purge_count; }
	$wpdb->suppress_errors( $suppress );
	remove_filter( 'pre_get_noobrocket_option_image_delivery', $enabled );
	remove_filter( 'upload_dir', $uploads );
	if ( $normalize( realpath( dirname( $temporary ) ) ) !== $normalize( realpath( $wordpress . '/wp-content' ) ) || is_link( $temporary ) ) { throw new RuntimeException( 'Owned native picture cleanup containment failed.' ); }
	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $temporary, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST ) as $file ) {
		if ( $file->isDir() && ! $file->isLink() ) { rmdir( $file->getPathname() ); } else { unlink( $file->getPathname() ); }
	}
	rmdir( $temporary );
}
$check( $saved === get_option( 'noobrocket_image_generation' ) && $saved_cron === get_option( 'cron' ), 'native fixture restores exact queue state and cron storage' );
$complete = true;
echo 'PASS: native picture query refusal/recovery, ', $checks, " assertions; registered owner, native wpdb/editor, owned files and database.\n";
