<?php
/** Registered hint invalidation with native core hooks and modeled theme/database boundaries. */
declare(strict_types=1);

$wordpress = isset( $argv[1] ) ? realpath( $argv[1] ) : false;
if ( ! $wordpress || ! is_file( $wordpress . '/wp-includes/plugin.php' ) ) {
	throw new RuntimeException( 'Provide WordPress core for native hook registration.' );
}
define( 'ABSPATH', $wordpress . '/' );
require ABSPATH . 'wp-includes/plugin.php';
require dirname( __DIR__ ) . '/noobrocket/vendor/autoload.php';

$importing = false;
function noobrocket_is_importing() { return $GLOBALS['importing']; }
function wp_get_theme() {
	return new class {
		public function get_template() { return 'owned-parent'; }
		public function get_stylesheet() { return 'owned-child'; }
	};
}
$controller = new class( [] ) extends NoobRocket\Engine\Common\PerformanceHints\Admin\Controller {
	public $clears = 0;
	public function truncate_tables() { ++$this->clears; }
};
$unused = static function ( $class ) { return ( new ReflectionClass( $class ) )->newInstanceWithoutConstructor(); };
$subscriber = new NoobRocket\Engine\Common\PerformanceHints\Admin\Subscriber(
	$controller,
	$unused( NoobRocket\Engine\Common\PerformanceHints\Admin\AdminBar::class ),
	$unused( NoobRocket\Engine\Common\PerformanceHints\Admin\Clean::class ),
	$unused( NoobRocket\Engine\Common\PerformanceHints\Admin\Notices::class )
);
$events = new NoobRocket\Event_Management\Event_Manager();
$events->add_subscriber( $subscriber );
$checks = 0;
foreach ( [
	[ [ 'action' => 'update', 'type' => 'theme', 'themes' => [ 'owned-child' ] ], false, 1 ],
	[ [ 'action' => 'update', 'type' => 'theme', 'themes' => [ 'owned-parent' ] ], false, 1 ],
	[ [ 'action' => 'update', 'type' => 'theme', 'themes' => [ 'other', 'owned-child', 'owned-parent' ] ], false, 1 ],
	[ [ 'action' => 'update', 'type' => 'theme', 'themes' => [ 'other' ] ], false, 0 ],
	[ [ 'action' => 'update', 'type' => 'plugin', 'themes' => [ 'owned-child' ] ], false, 0 ],
	[ [ 'action' => 'install', 'type' => 'theme', 'themes' => [ 'owned-child' ] ], false, 0 ],
	[ [ 'action' => 'update', 'type' => 'theme', 'themes' => [] ], false, 0 ],
	[ [ 'action' => 'update', 'type' => 'theme', 'themes' => 'owned-child' ], false, 0 ],
	[ [ 'action' => 'update', 'type' => 'theme' ], false, 0 ],
	[ null, false, 0 ],
	[ [ 'action' => 'update', 'type' => 'theme', 'themes' => [ 'owned-child' ] ], true, 0 ],
] as $case ) {
	list( $details, $importing, $expected ) = $case;
	$before = $controller->clears;
	do_action( 'upgrader_process_complete', null, $details );
	if ( $expected !== $controller->clears - $before ) { throw new RuntimeException( 'Theme update cleared the wrong hint scope.' ); }
	++$checks;
}
$importing = false;
$before = $controller->clears;
do_action( 'switch_theme' );
if ( $controller->clears !== $before + 1 ) { throw new RuntimeException( 'Existing switch-theme invalidation changed.' ); }
++$checks;
echo "PASS: $checks theme-update hint assertions; native hooks, modeled theme/database.\n";
