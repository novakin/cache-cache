<?php
/** Actual shared beacon subscriber/processor; modeled core responses, no site bootstrap. */
declare(strict_types=1);

require dirname( __DIR__ ) . '/noobrocket/inc/classes/event-management/subscriber-interface.php';
require dirname( __DIR__ ) . '/noobrocket/inc/Engine/Common/PerformanceHints/AJAX/Processor.php';
require dirname( __DIR__ ) . '/noobrocket/inc/Engine/Common/PerformanceHints/AJAX/Subscriber.php';

class BeaconNonceResponse extends RuntimeException {
	public $payload;
	public function __construct( $payload ) { $this->payload = $payload; }
}
function nocache_headers() { ++$GLOBALS['uncached']; }
function wp_create_nonce( $action ) {
	if ( 'noobrocket_beacon' !== $action ) { throw new RuntimeException( 'Wrong nonce action.' ); }
	return $GLOBALS['visitor_nonce'];
}
function wp_send_json_success( $payload ) { throw new BeaconNonceResponse( $payload ); }
function check_ajax_referer( $action, $field ) {
	if ( 'noobrocket_beacon' !== $action || 'noobrocket_beacon_nonce' !== $field || $GLOBALS['expired'] ) {
		throw new RuntimeException( 'nonce rejected' );
	}
	++$GLOBALS['verified'];
}
function wp_unslash( $value ) { return stripslashes( $value ); }
function beacon_nonce_check( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
	++$GLOBALS['checks'];
}

$checks = 0;
$uncached = 0;
$verified = 0;
$calls = 0;
$expired = false;
$controller = new class {
	public function check_data() {
		check_ajax_referer( 'noobrocket_beacon', 'noobrocket_beacon_nonce' );
		++$GLOBALS['calls'];
		return [ 'lcp' => false ];
	}
	public function add_data() {
		check_ajax_referer( 'noobrocket_beacon', 'noobrocket_beacon_nonce' );
		++$GLOBALS['calls'];
		return [ 'lcp' => [ 'status' => 'completed' ] ];
	}
};
$factory = new class( $controller ) {
	private $controller;
	public function __construct( $controller ) { $this->controller = $controller; }
	public function get_ajax_controller() { return $this->controller; }
};
$subscriber = new NoobRocket\Engine\Common\PerformanceHints\AJAX\Subscriber(
	new NoobRocket\Engine\Common\PerformanceHints\AJAX\Processor( [ $factory ] )
);
$events = $subscriber::get_subscribed_events();
foreach ( [ 'wp_ajax_nopriv_noobrocket_beacon_nonce' => 'guest', 'wp_ajax_noobrocket_beacon_nonce' => 'authenticated' ] as $event => $visitor ) {
	beacon_nonce_check( 'refresh_nonce' === $events[ $event ], $visitor . ': refresh endpoint registered' );
	$visitor_nonce = $visitor . '-current-nonce';
	try { $subscriber->{$events[ $event ]}(); } catch ( BeaconNonceResponse $response ) {
		beacon_nonce_check( [ 'nonce' => $visitor_nonce ] === $response->payload && 0 === $calls, $visitor . ': fresh visitor nonce without collector calls' );
	}
}
beacon_nonce_check( 2 === $uncached && 0 === $verified, 'Refresh disables caching and needs no expired nonce.' );

$_POST = [ 'results' => '{}' ];
foreach ( [ 'check_data' => [ 'lcp' => false ], 'add_data' => [ 'lcp' => [ 'status' => 'completed' ] ] ] as $method => $payload ) {
	$expired = true;
	$before = $calls;
	try { $subscriber->$method(); throw new RuntimeException( 'Expired collector nonce was accepted.' ); } catch ( RuntimeException $response ) {
		beacon_nonce_check( 'nonce rejected' === $response->getMessage() && $before === $calls, $method . ': expired nonce still rejected before collector work' );
	}
	$expired = false;
	try { $subscriber->$method(); } catch ( BeaconNonceResponse $response ) {
		beacon_nonce_check( $payload === $response->payload && $calls === $before + 1, $method . ': valid collector contract preserved' );
	}
}
echo "PASS: $checks beacon nonce registration/boundary checks; modeled core, no site or writes.\n";
