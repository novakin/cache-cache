<?php
/** Concurrent executable policy readers/writer in one owned temporary directory. */
declare(strict_types=1);

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'FS_CHMOD_FILE', 0644 );
require dirname( __DIR__ ) . '/noobrocket/inc/functions/files.php';
function noobrocket_get_constant( $name, $default = null ) { return defined( $name ) ? constant( $name ) : $default; }

if ( '--read' === ( $argv[1] ?? '' ) ) {
	$target = $argv[2];
	file_put_contents( $argv[3], 'reader-ready' );
	$seen = [];
	for ( $iteration = 0; $iteration < 1600; ++$iteration ) {
		$noobrocket_config_complete = false;
		$noobrocket_cache_reject_cookies = '';
		include $target;
		if ( true !== $noobrocket_config_complete || ! in_array( $noobrocket_cache_reject_cookies, [ 'old-private-policy', 'new-private-policy' ], true ) ) {
			throw new RuntimeException( 'Concurrent reader observed incomplete policy.' );
		}
		$seen[ $noobrocket_cache_reject_cookies ] = true;
		usleep( 1000 );
	}
	echo json_encode( [ 'reads' => $iteration, 'seen' => array_keys( $seen ) ] );
	exit( 0 );
}
class AtomicPolicyFilesystem {
	public function put_contents( $path, $bytes, $permissions ) {
		$stream = fopen( $path, 'wb' );
		if ( ! $stream ) { return false; }
		for ( $offset = 0; $offset < strlen( $bytes ); $offset += 64 ) {
			fwrite( $stream, substr( $bytes, $offset, 64 ) );
			usleep( 250 );
		}
		fclose( $stream );
		return chmod( $path, $permissions );
	}
}
$temporary = sys_get_temp_dir() . '/noobrocket-atomic-policy-' . bin2hex( random_bytes( 6 ) );
mkdir( $temporary );
$target = $temporary . '/example.test.php';
$ready = $temporary . '/reader-ready';
$old = '<?php $noobrocket_cache_reject_cookies = "old-private-policy"; /* ' . str_repeat( 'complete policy ', 20 ) . ' */ $noobrocket_config_complete = true;';
$new = str_replace( 'old-private-policy', 'new-private-policy', $old );
file_put_contents( $target, $old );
$pipes = [];
$process = proc_open( [ PHP_BINARY, __FILE__, '--read', $target, $ready ], [ 0 => [ 'pipe', 'r' ], 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ], $pipes );
if ( ! is_resource( $process ) ) { throw new RuntimeException( 'Could not launch the isolated reader.' ); }
fclose( $pipes[0] );
try {
	for ( $attempt = 0; $attempt < 100 && ! is_file( $ready ); ++$attempt ) { usleep( 10000 ); }
	if ( ! is_file( $ready ) ) { throw new RuntimeException( 'Concurrent reader did not become ready.' ); }
	$filesystem = new AtomicPolicyFilesystem();
	$published = 0;
	$refused = 0;
	for ( $iteration = 0; $iteration < 120; ++$iteration ) {
		if ( noobrocket_put_php_content( $target, 0 === $iteration % 2 ? $new : $old, $filesystem ) ) {
			++$published;
		} else {
			// Windows can refuse replacement while an include holds the target open.
			// Retaining complete old bytes is the required failure behavior.
			++$refused;
			if ( ! in_array( file_get_contents( $target ), [ $old, $new ], true ) ) {
				throw new RuntimeException( 'Refused replacement damaged the prior policy.' );
			}
		}
	}
	$output = stream_get_contents( $pipes[1] );
	$error = stream_get_contents( $pipes[2] );
	fclose( $pipes[1] ); fclose( $pipes[2] );
	$exit = proc_close( $process );
	$process = null;
	$result = json_decode( $output, true );
	if ( 0 !== $exit || '' !== $error || ! is_array( $result ) || 1600 !== $result['reads'] || 2 !== count( $result['seen'] ) || $published < 1 ) {
		throw new RuntimeException( 'Concurrent policy reader failed: ' . substr( $error . $output, 0, 400 ) );
	}
	if ( [] !== glob( $temporary . '/.nbr-php-*' ) ) { throw new RuntimeException( 'Temporary policy bytes remain.' ); }
	echo 'PASS: 1,600 native includes during 120 replacement attempts (', $published, ' published, ', $refused, ' safely refused); complete old/new policies only.', PHP_EOL;
} finally {
	if ( is_resource( $process ) ) {
		proc_terminate( $process );
		foreach ( [ 1, 2 ] as $index ) { if ( is_resource( $pipes[ $index ] ) ) { fclose( $pipes[ $index ] ); } }
		proc_close( $process );
	}
	foreach ( glob( $temporary . '/*' ) ?: [] as $path ) { unlink( $path ); }
	foreach ( glob( $temporary . '/.nbr-php-*' ) ?: [] as $path ) { unlink( $path ); }
	rmdir( $temporary );
}
