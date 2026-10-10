<?php
/** Native HTTP response-header gate; isolated loopback PHP server, no WordPress site. */
declare(strict_types=1);

if ( 'cli-server' === PHP_SAPI && '1' === getenv( 'NOOBROCKET_CACHE_HEADER_FIXTURE' ) ) {
	require dirname( __DIR__ ) . '/noobrocket/inc/classes/traits/trait-memoize.php';
	require dirname( __DIR__ ) . '/noobrocket/inc/classes/Buffer/class-config.php';
	require dirname( __DIR__ ) . '/noobrocket/inc/classes/Buffer/class-tests.php';
	function noobrocket_mkdir_p( $path ) { return true; }
	class HeaderGate extends NoobRocket\Buffer\Tests {
		public function is_404() { return false; }
		public function is_search() { return false; }
		public function is_feed_uri() { return false; }
	}
	http_response_code( 200 );
	$case = $_GET['case'] ?? '';
	if ( 'public' === $case ) { header( 'Cache-Control: public, max-age=300' ); }
	if ( 'private' === $case ) { header( 'Cache-Control: private' ); }
	if ( 'no-store' === $case ) { header( 'Cache-Control: max-age=0, no-store' ); }
	if ( 'mixed' === $case ) {
		header( 'Cache-Control: public', false );
		header( 'cAcHe-CoNtRoL: max-age=0, PrIvAtE="Set-Cookie"', false );
	}
	if ( 'late' === $case ) { header( 'Cache-Control: public' ); }
	if ( 'cookie' === $case ) { header( 'Set-Cookie: harmless=fixture; SameSite=Lax' ); }
	if ( 'prefix' === $case ) { header( 'Cache-Control: no-store-extra=1' ); }
	$config = new NoobRocket\Buffer\Config( [ 'config_dir_path' => sys_get_temp_dir() ] );
	$gate = new HeaderGate( $config );
	// Models a late response hook, immediately before the outer publication gate.
	if ( 'late' === $case ) { header( 'Cache-Control: no-store' ); }
	$optimizable = $gate->can_process_buffer( '<html>' . str_repeat( 'public content ', 30 ) . '</html>' );
	$allowed = $gate->can_cache_response();
	header( 'Content-Type: application/json' );
	echo json_encode( [ 'allowed' => $allowed, 'optimizable' => $optimizable ] );
	return;
}

$listener = stream_socket_server( 'tcp://127.0.0.1:0', $error, $message );
if ( ! $listener ) { throw new RuntimeException( 'Could not allocate an isolated loopback endpoint.' ); }
$address = stream_socket_get_name( $listener, false );
fclose( $listener );
$pipes = [];
$environment = getenv();
$environment['NOOBROCKET_CACHE_HEADER_FIXTURE'] = '1';
$process = proc_open( [ PHP_BINARY, '-S', $address, __FILE__ ], [ 0 => [ 'pipe', 'r' ], 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ], $pipes, null, $environment );
if ( ! is_resource( $process ) ) { throw new RuntimeException( 'Could not launch header fixture.' ); }
fclose( $pipes[0] );
stream_set_blocking( $pipes[1], false );
stream_set_blocking( $pipes[2], false );
$checks = 0;
try {
	$started = false;
	for ( $attempt = 0; $attempt < 50; ++$attempt ) {
		$socket = @stream_socket_client( 'tcp://' . $address, $error, $message, 0.1 );
		if ( $socket ) { fclose( $socket ); $started = true; break; }
		usleep( 50000 );
	}
	if ( ! $started ) { throw new RuntimeException( 'Header fixture did not start.' ); }
	foreach ( [ 'absent' => true, 'public' => true, 'private' => false, 'no-store' => false, 'mixed' => false, 'late' => false, 'cookie' => true, 'prefix' => true ] as $case => $expected ) {
		$body = file_get_contents( 'http://' . $address . '/?case=' . $case );
		$result = json_decode( $body, true );
		if ( ! is_array( $result ) || $expected !== ( $result['allowed'] ?? null ) || true !== ( $result['optimizable'] ?? null ) ) {
			throw new RuntimeException( 'FAIL: native Cache-Control gate for ' . $case . ': ' . substr( $body, 0, 200 ) );
		}
		++$checks;
	}
	echo 'PASS: ', $checks, ' native response privacy assertions.', PHP_EOL;
} finally {
	proc_terminate( $process );
	foreach ( [ 1, 2 ] as $index ) { fclose( $pipes[ $index ] ); }
	proc_close( $process );
}
