<?php
/** Shared beacon admission must reject bad payloads before any feature decodes them. */
require dirname( __DIR__ ) . '/noobrocket/inc/Engine/Common/PerformanceHints/AJAX/Processor.php';
function __( $text, $domain ) { return $text; }
function wp_unslash( $value ) { return stripslashes( $value ); }
function wp_send_json_error( $payload, $status ) { throw new RuntimeException( 'error:' . $status ); }
function wp_send_json_success( $payload ) { throw new RuntimeException( 'success' ); }
$calls = 0;
$controller = new class {
	public function add_data(): array { ++$GLOBALS['calls']; return [ 'accepted' => true ]; }
	public function check_data(): array { ++$GLOBALS['calls']; return [ 'exists' => true ]; }
};
$factory = new class( $controller ) {
	private $controller;
	public function __construct( $controller ) { $this->controller = $controller; }
	public function get_ajax_controller() { return $this->controller; }
};
$processor = new NoobRocket\Engine\Common\PerformanceHints\AJAX\Processor( [ $factory ] );
$checks = 0;
foreach ( [ null, [], 1, str_repeat( 'x', 65537 ), '[', '[]', 'null', '42', '"string"', '{}' ] as $input ) {
	$_POST = null === $input ? [] : [ 'results' => $input ];
	$before = $calls;
	try { $processor->add_data(); } catch ( RuntimeException $result ) {
		$valid = '{}' === $input;
		if ( $result->getMessage() !== ( $valid ? 'success' : 'error:400' ) || $calls !== $before + (int) $valid ) {
			throw new RuntimeException( 'Invalid shared beacon admission result.' );
		}
		++$checks;
	}
}
$_POST = [];
try { $processor->check_data(); } catch ( RuntimeException $result ) {
	if ( 'success' !== $result->getMessage() || 2 !== $calls ) { throw new RuntimeException( 'Read-only data checks must remain independent of beacon submission.' ); }
	++$checks;
}
echo "PASS: $checks shared performance-hint boundary checks; no WordPress writes.\n";
