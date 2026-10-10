<?php
/** Standalone cache publication proof; real processes and temporary files, no WordPress site writes. */
declare(strict_types=1);

$write_mode = 'normal';
$write_count = 0;
$write_delay = 0;
$compression_level = 6;
function noobrocket_mkdir_p( $path ) { return is_dir( $path ) || mkdir( $path, 0777, true ); }
function apply_filters( $hook, $value ) { return $GLOBALS['compression_level']; }
function noobrocket_put_content( $path, $content ) {
	$GLOBALS['write_count']++;
	if ( $GLOBALS['write_delay'] ) { usleep( $GLOBALS['write_delay'] ); }
	if ( 'fail_plain' === $GLOBALS['write_mode'] || ( 'fail_gzip' === $GLOBALS['write_mode'] && 2 === $GLOBALS['write_count'] ) ) { return false; }
	if ( 'throw_gzip' === $GLOBALS['write_mode'] && 2 === $GLOBALS['write_count'] ) { throw new RuntimeException( 'Injected gzip write failure' ); }
	return false !== file_put_contents( $path, $content );
}
require dirname( __DIR__ ) . '/noobrocket/inc/classes/Buffer/class-abstract-buffer.php';
require dirname( __DIR__ ) . '/noobrocket/inc/classes/Buffer/class-cache.php';
function cache_writer( string $root ): array {
	$class = new ReflectionClass( NoobRocket\Buffer\Cache::class );
	$cache = $class->newInstanceWithoutConstructor();
	$property = $class->getProperty( 'cache_dir_path' );
	$property->setAccessible( true );
	$property->setValue( $cache, $root . '/pages/' );
	$method = $class->getMethod( 'write_cache_file' );
	$method->setAccessible( true );
	return [ $cache, $method ];
}
function writer_content( int $id ): string { return '<html>BEGIN-' . $id . ':' . str_repeat( 'Payload-' . $id . ';', 30000 ) . ':END-' . $id . '</html>'; }
if ( '--write' === ( $argv[1] ?? '' ) ) {
	$write_delay = 80000;
	list( $cache, $method ) = cache_writer( $argv[2] );
	$method->invoke( $cache, $argv[2] . '/pages/example.test/index.html', writer_content( (int) $argv[3] ) );
	exit( 0 );
}
if ( '--hold' === ( $argv[1] ?? '' ) ) {
	$lock = fopen( $argv[2] . '/pages/.locks/' . md5( $argv[2] . '/pages/example.test/index.html' ) . '.lock', 'c' );
	flock( $lock, LOCK_EX );
	file_put_contents( $argv[2] . '/holder-ready', 'ready' );
	while ( true ) { usleep( 100000 ); }
}
$fixture = sys_get_temp_dir() . '/noobrocket-cache-write-' . bin2hex( random_bytes( 6 ) );
mkdir( $fixture . '/pages/example.test', 0777, true );
$target = $fixture . '/pages/example.test/index.html';
$checks = 0;
$processes = [];
function cache_write_check( bool $condition, string $message ): void {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
	$GLOBALS['checks']++;
}
function writer_child( string $mode, string $root, int $id = 0 ): array {
	$pipes = [];
	$process = proc_open( [ PHP_BINARY, __FILE__, $mode, $root, (string) $id ], [ 0 => [ 'pipe', 'r' ], 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ], $pipes );
	if ( ! is_resource( $process ) ) { throw new RuntimeException( 'Could not start cache writer fixture.' ); }
	fclose( $pipes[0] );
	return [ $process, $pipes ];
}
function writer_finish( array $child ): void {
	list( $process, $pipes ) = $child;
	$output = stream_get_contents( $pipes[1] ) . stream_get_contents( $pipes[2] );
	fclose( $pipes[1] ); fclose( $pipes[2] );
	$exit = proc_close( $process );
	if ( 0 !== $exit || '' !== $output ) { throw new RuntimeException( 'Cache child failed: exit ' . $exit . ' ' . substr( $output, 0, 800 ) ); }
}
try {
	list( $cache, $method ) = cache_writer( $fixture );
	$result = $method->invoke( $cache, $target, writer_content( 0 ) );
	cache_write_check( true === $result && writer_content( 0 ) === file_get_contents( $target ) && writer_content( 0 ) === gzdecode( file_get_contents( $target . '_gzip' ) ), 'successful publication returns success and writes matching complete plain and gzip bytes' );
	for ( $id = 1; $id <= 20; $id++ ) { $processes[] = writer_child( '--write', $fixture, $id ); }
	foreach ( $processes as $child ) { writer_finish( $child ); }
	$processes = [];
	$plain = file_get_contents( $target );
	$complete = false;
	for ( $id = 1; $id <= 20; $id++ ) { $complete = $complete || writer_content( $id ) === $plain; }
	cache_write_check( $complete && $plain === gzdecode( file_get_contents( $target . '_gzip' ) ), 'twenty concurrent writers publish one complete response with a matching gzip companion' );
	cache_write_check( [] === glob( dirname( $target ) . '/.nbr-*' ), 'concurrent writers remove their temporary files' );
	$lock_path = $fixture . '/pages/.locks/' . md5( $target ) . '.lock';
	$lock = fopen( $lock_path, 'c' );
	flock( $lock, LOCK_EX );
	writer_finish( writer_child( '--write', $fixture, 21 ) );
	cache_write_check( $plain === file_get_contents( $target ) && $plain === gzdecode( file_get_contents( $target . '_gzip' ) ), 'held native mutex prevents a second writer from replacing either variant' );
	flock( $lock, LOCK_UN ); fclose( $lock );
	foreach ( [ -100, 100 ] as $level ) {
		$compression_level = $level;
		$write_count = 0;
		$method->invoke( $cache, $target, writer_content( $level ) );
		cache_write_check( writer_content( $level ) === gzdecode( file_get_contents( $target . '_gzip' ) ), 'out-of-range compression level clamps to a usable encoder level' );
	}
	$compression_level = 6;
	$baseline = file_get_contents( $target );
	$baseline_gzip = file_get_contents( $target . '_gzip' );
	$write_mode = 'fail_plain'; $write_count = 0;
	$result = $method->invoke( $cache, $target, writer_content( 22 ) );
	cache_write_check( false === $result && $baseline === file_get_contents( $target ) && $baseline_gzip === file_get_contents( $target . '_gzip' ) && [] === glob( dirname( $target ) . '/.nbr-*' ), 'failed plain temporary write returns failure, preserves the prior response and removes partial files' );
	$write_mode = 'fail_gzip'; $write_count = 0;
	$result = $method->invoke( $cache, $target, writer_content( 23 ) );
	clearstatcache();
	cache_write_check( true === $result && writer_content( 23 ) === file_get_contents( $target ) && ! is_file( $target . '_gzip' ) && [] === glob( dirname( $target ) . '/.nbr-*' ), 'failed optional gzip write still reports published plain bytes and removes an older gzip companion' );
	$write_mode = 'normal'; $write_count = 0;
	$method->invoke( $cache, $target, writer_content( 24 ) );
	$write_mode = 'throw_gzip'; $write_count = 0;
	try { $method->invoke( $cache, $target, writer_content( 25 ) ); throw new LogicException( 'Expected injected gzip failure' ); }
	catch ( RuntimeException $error ) { cache_write_check( 'Injected gzip write failure' === $error->getMessage(), 'injected encoder write exception reaches the caller' ); }
	clearstatcache();
	cache_write_check( writer_content( 25 ) === file_get_contents( $target ) && ! is_file( $target . '_gzip' ), 'gzip write exception cannot leave an older companion beside newly published plain bytes' );
	cache_write_check( [] === glob( dirname( $target ) . '/.nbr-*' ) && flock( $lock = fopen( $lock_path, 'c' ), LOCK_EX | LOCK_NB ), 'exception removes temporary files and releases the OS mutex' );
	flock( $lock, LOCK_UN ); fclose( $lock );
	$write_mode = 'normal'; $write_count = 0;
	$method->invoke( $cache, $target, writer_content( 26 ) );
	$holder = writer_child( '--hold', $fixture );
	$processes[] = $holder;
	$deadline = microtime( true ) + 5;
	while ( ! is_file( $fixture . '/holder-ready' ) && microtime( true ) < $deadline ) { usleep( 20000 ); clearstatcache(); }
	cache_write_check( is_file( $fixture . '/holder-ready' ), 'separate process acquires the real cache mutex' );
	proc_terminate( $holder[0] );
	foreach ( [ 1, 2 ] as $pipe ) { fclose( $holder[1][ $pipe ] ); }
	proc_close( $holder[0] );
	$processes = [];
	writer_finish( writer_child( '--write', $fixture, 27 ) );
	cache_write_check( writer_content( 27 ) === file_get_contents( $target ) && writer_content( 27 ) === gzdecode( file_get_contents( $target . '_gzip' ) ), 'a terminated lock owner cannot leave a permanent publication lock' );
	echo 'PASS: ', $checks, " cache writer assertions; real concurrent processes and filesystem.\n";
} finally {
	foreach ( $processes as $child ) { if ( is_resource( $child[0] ) ) { proc_terminate( $child[0] ); foreach ( [ 1, 2 ] as $pipe ) { if ( is_resource( $child[1][ $pipe ] ) ) { fclose( $child[1][ $pipe ] ); } } proc_close( $child[0] ); } }
	foreach ( [ $fixture . '/pages/example.test', $fixture . '/pages/.locks' ] as $directory ) { foreach ( glob( $directory . '/*' ) as $file ) { unlink( $file ); } foreach ( glob( $directory . '/.nbr-*' ) as $file ) { unlink( $file ); } rmdir( $directory ); }
	if ( is_file( $fixture . '/holder-ready' ) ) { unlink( $fixture . '/holder-ready' ); }
	rmdir( $fixture . '/pages' ); rmdir( $fixture );
}
