<?php
/** Native deactivation/event/queue lifecycle with modeled multisite storage; no WordPress bootstrap. */
declare(strict_types=1);

class LifecycleContainer {
	public function __construct( ...$arguments ) {}
	public function add( ...$arguments ) { return $this; }
	public function addServiceProvider( ...$arguments ) { return $this; }
	public function get( ...$arguments ) { return null; }
}
foreach ( [
	'NoobRocket\\Admin\\Options',
	'NoobRocket\\Engine\\Container\\IndexedDefinitionAggregate',
	'NoobRocket\\Dependencies\\League\\Container\\Argument\\Literal\\StringArgument',
	'NoobRocket\\Dependencies\\League\\Container\\Container',
	'NoobRocket\\Engine\\Admin\\Beacon\\ServiceProvider',
	'NoobRocket\\Engine\\Support\\ServiceProvider',
	'NoobRocket\\ServiceProvider\\Options',
	'NoobRocket\\Engine\\Deactivation\\ServiceProvider',
	'NoobRocket\\ThirdParty\\Hostings\\ServiceProvider',
] as $class_name ) {
	class_alias( LifecycleContainer::class, $class_name );
}
class LifecycleHost {
	public static function get_host_service() { return ''; }
}
class_alias( LifecycleHost::class, 'NoobRocket\\ThirdParty\\Hostings\\HostResolver' );

define( 'NOOBROCKET_SLUG', 'noobrocket_settings' );
define( 'NOOBROCKET_CACHE_ROOT_PATH', '/isolated/cache/' );
define( 'NOOBROCKET_PATH', '/isolated/plugin/' );
define( 'NOOBROCKET_FILE', '/isolated/plugin/noobrocket.php' );
define( 'NOOBROCKET_CONFIG_PATH', '/isolated/config/' );
$state = [
	'blog' => 42, 'network' => 5, 'multisite' => true, 'stack' => [],
	'sites' => range( 1, 105 ), 'options' => [], 'transients' => [],
	'network_options' => [ 5 => [], 8 => [ 'noobrocket_database_optimization_batch_foreign_network' => [ 'foreign' ] ] ],
	'site_transients' => [ 5 => [], 8 => [ 'foreign_process_lock' => true ] ],
	'events' => [], 'files' => [], 'hooks' => [], 'actions' => [], 'site_queries' => [],
	'fail_blog' => null, 'deletions' => 0, 'checks' => 0,
];
function lifecycle_check( $value, $message ) {
	if ( ! $value ) { throw new RuntimeException( 'FAIL: ' . $message ); }
	++$GLOBALS['state']['checks'];
}
function is_multisite() { return $GLOBALS['state']['multisite']; }
function get_current_blog_id() { return $GLOBALS['state']['blog']; }
function get_current_network_id() { return $GLOBALS['state']['network']; }
function get_sites( $arguments ) {
	$GLOBALS['state']['site_queries'][] = $arguments;
	return 5 === $arguments['network_id'] ? $GLOBALS['state']['sites'] : [ 999 ];
}
function switch_to_blog( $id ) {
	$GLOBALS['state']['stack'][] = [ get_current_blog_id(), get_current_network_id() ];
	$GLOBALS['state']['blog'] = (int) $id;
	$GLOBALS['wpdb']->options = 'wp_' . $id . '_options';
}
function restore_current_blog() {
	[ $GLOBALS['state']['blog'], $GLOBALS['state']['network'] ] = array_pop( $GLOBALS['state']['stack'] );
	$GLOBALS['wpdb']->options = 'wp_' . get_current_blog_id() . '_options';
}
function get_option( $key, $default = false ) { return $GLOBALS['state']['options'][ get_current_blog_id() ][ $key ] ?? $default; }
function delete_option( $key ) { unset( $GLOBALS['state']['options'][ get_current_blog_id() ][ $key ] ); return true; }
function get_transient( $key ) { return $GLOBALS['state']['transients'][ get_current_blog_id() ][ $key ] ?? false; }
function delete_transient( $key ) { unset( $GLOBALS['state']['transients'][ get_current_blog_id() ][ $key ] ); return true; }
function set_transient( $key, $value ) { $GLOBALS['state']['transients'][ get_current_blog_id() ][ $key ] = $value; return true; }
function delete_site_option( $key ) { unset( $GLOBALS['state']['network_options'][ get_current_network_id() ][ $key ] ); return true; }
function get_site_transient( $key ) { return $GLOBALS['state']['site_transients'][ get_current_network_id() ][ $key ] ?? false; }
function delete_site_transient( $key ) { unset( $GLOBALS['state']['site_transients'][ get_current_network_id() ][ $key ] ); return true; }
function wp_clear_scheduled_hook( $key ) {
	if ( get_current_blog_id() === $GLOBALS['state']['fail_blog'] ) { throw new RuntimeException( 'Injected cron failure.' ); }
	unset( $GLOBALS['state']['events'][ get_current_blog_id() ][ $key ] );
}
function wp_next_scheduled( $key ) { return $GLOBALS['state']['events'][ get_current_blog_id() ][ $key ] ?? false; }
function wp_schedule_event( $timestamp, $frequency, $key ) { $GLOBALS['state']['events'][ get_current_blog_id() ][ $key ] = $timestamp; return true; }
function wp_get_schedules() { return [ 'daily' => [ 'interval' => 86400 ], 'weekly' => [ 'interval' => 604800 ], 'monthly' => [ 'interval' => 2592000 ] ]; }
function add_filter( $hook, $callback, $priority = 10, $arguments = 1 ) { $GLOBALS['state']['hooks'][ $hook ][] = [ $callback, $arguments ]; }
function add_action( $hook, $callback, $priority = 10, $arguments = 1 ) { add_filter( $hook, $callback, $priority, $arguments ); }
function do_action( $hook, ...$arguments ) {
	$GLOBALS['state']['actions'][ $hook ] = $arguments;
	foreach ( $GLOBALS['state']['hooks'][ $hook ] ?? [] as [ $callback, $accepted ] ) {
		$callback( ...array_slice( $arguments, 0, $accepted ) );
	}
}
function apply_filters( $hook, $value ) { return $value; }
function noobrocket_get_constant( $name ) { return defined( $name ) ? constant( $name ) : ''; }
function plugin_basename( $file ) { return 'noobrocket/' . basename( $file ); }
function noobrocket_delete_config_file() {}
function _noobrocket_get_php_files_in_dir() { return []; }
function flush_noobrocket_htaccess() {}
function noobrocket_renew_box() {}
function flush_rewrite_rules() {}
function wp_delete_post_revision() { ++$GLOBALS['state']['deletions']; throw new RuntimeException( 'A cancelled queue deleted content.' ); }
function wp_delete_post() { return wp_delete_post_revision(); }
function wp_delete_comment() { return wp_delete_post_revision(); }
class LifecycleExit extends RuntimeException {}
function wp_die() { throw new LifecycleExit(); }
function check_ajax_referer() { throw new RuntimeException( 'Cancelled queue reached task authorization.' ); }
class LifecycleFilesystem {
	public function touch( $path ) { $GLOBALS['state']['files'][ $path ] = true; return true; }
	public function exists( $path ) { return isset( $GLOBALS['state']['files'][ $path ] ); }
}
function noobrocket_direct_filesystem() { return new LifecycleFilesystem(); }
class LifecycleDatabase {
	public $options = 'wp_42_options';
	public $sitemeta = 'wp_sitemeta';
	public function esc_like( $value ) { return addcslashes( $value, '_%\\' ); }
	public function prepare( $sql, ...$arguments ) {
		foreach ( $arguments as $argument ) {
			$sql = preg_replace_callback( '/%[sd]/', static function ( $match ) use ( $argument ) {
				return '%d' === $match[0] ? (string) (int) $argument : "'" . str_replace( "'", "''", $argument ) . "'";
			}, $sql, 1 );
		}
		return $sql;
	}
	private function matching_keys( $sql, $network ): array {
		preg_match( "/LIKE '([^']+)'/", $sql, $match );
		$prefix = str_replace( [ '\\_', '\\%' ], [ '_', '%' ], substr( $match[1], 0, -1 ) );
		return array_values( array_filter( array_keys( $GLOBALS['state']['network_options'][ $network ] ), static function ( $key ) use ( $prefix ) { return 0 === strpos( $key, $prefix ); } ) );
	}
	public function get_col( $sql ) {
		preg_match( '/site_id = (\d+)/', $sql, $match );
		if ( ! $match ) { throw new RuntimeException( 'Queue deletion lost network ownership.' ); }
		return $this->matching_keys( $sql, (int) $match[1] );
	}
	public function get_var( $sql ) {
		$count = 0;
		foreach ( array_keys( $GLOBALS['state']['network_options'] ) as $network ) { $count += count( $this->matching_keys( $sql, $network ) ); }
		return $count;
	}
}
$wpdb = new LifecycleDatabase();
$is_apache = false;
$_GET = [];
$root = dirname( __DIR__ ) . '/noobrocket/inc/';
require $root . 'classes/event-management/subscriber-interface.php';
require $root . 'classes/event-management/class-event-manager.php';
require $root . 'classes/admin/class-options-data.php';
require $root . 'classes/dependencies/wp-media/background-processing/wp-async-request.php';
require $root . 'classes/dependencies/wp-media/background-processing/wp-background-process.php';
require $root . 'Engine/Admin/Database/OptimizationProcess.php';
require $root . 'Engine/Admin/Database/Optimization.php';
require $root . 'Engine/Admin/Database/Subscriber.php';
require $root . 'Engine/Deactivation/Deactivation.php';

function seed_site( $id ): void {
	$identifier = 'noobrocket_database_optimization_' . $id;
	$GLOBALS['state']['network_options'][5][ $identifier . '_batch_a' ] = [ 'database_revisions' ];
	$GLOBALS['state']['network_options'][5][ $identifier . '_batch_b' ] = [ 'database_trashed_posts' ];
	$GLOBALS['state']['site_transients'][5][ $identifier . '_process_lock' ] = true;
	$GLOBALS['state']['options'][ $id ] = [ 'noobrocket_database_optimization_start' => time(), 'noobrocket_database_optimization_results' => [ 'counts' => [ 'database_revisions' => 3 ], 'failed' => [] ], 'foreign_option' => 'retained' ];
	$GLOBALS['state']['transients'][ $id ] = [ 'noobrocket_database_optimization_process' => 'running', 'foreign_transient' => 'retained' ];
	$GLOBALS['state']['events'][ $id ] = [ $identifier . '_cron' => 123, 'noobrocket_database_optimization_cron' => 123, 'noobrocket_database_optimization_time_event' => 123, 'foreign_cron' => 123 ];
}
foreach ( $state['sites'] as $site_id ) { seed_site( $site_id ); }
$state['network_options'][5]['noobrocket_database_optimization_batch_legacy'] = [ 'database_revisions' ];
$state['network_options'][5]['noobrocket_database_optimization_999_batch_outside'] = [ 'outside' ];
$state['network_options'][5]['foreign_batch'] = [ 'foreign' ];
$state['site_transients'][5]['noobrocket_database_optimization_process_lock'] = true;
$state['site_transients'][5]['foreign_process_lock'] = true;
$process = new NoobRocket\Engine\Admin\Database\OptimizationProcess();
$optimization = new NoobRocket\Engine\Admin\Database\Optimization( $process );
$options = new NoobRocket\Admin\Options_Data( [ 'schedule_automatic_cleanup' => 1, 'automatic_cleanup_frequency' => 'weekly' ] );
$subscriber = new NoobRocket\Engine\Admin\Database\Subscriber( $optimization, $options );
( new NoobRocket\Event_Management\Event_Manager() )->add_subscriber( $subscriber );

// Model core's boolean delivery through the real plugin entry and subscriber registry.
add_action( 'deactivate_noobrocket/noobrocket.php', [ NoobRocket\Engine\Deactivation\Deactivation::class, 'deactivate_plugin' ] );
do_action( 'deactivate_noobrocket/noobrocket.php', true );
lifecycle_check( [ 0, true ] === $state['actions']['noobrocket_deactivation'], 'entry propagates core network-wide flag through the existing event' );
lifecycle_check( 42 === get_current_blog_id() && ! $state['stack'], 'network cancellation restores the original blog and switch stack' );
lifecycle_check( [ 'fields' => 'ids', 'number' => 0, 'network_id' => 5 ] === $state['site_queries'][0], 'enumeration includes all 105 sites only in the affected network' );
foreach ( $state['sites'] as $site_id ) {
	$identifier = 'noobrocket_database_optimization_' . $site_id;
	lifecycle_check( ! isset( $state['network_options'][5][ $identifier . '_batch_a' ], $state['network_options'][5][ $identifier . '_batch_b' ], $state['site_transients'][5][ $identifier . '_process_lock' ] ), 'owned batches and lock removed on site ' . $site_id );
	lifecycle_check( [ 'foreign_cron' => 123 ] === $state['events'][ $site_id ], 'queue, legacy and recurring cron removed on site ' . $site_id );
	lifecycle_check( [ 'foreign_option' => 'retained' ] === $state['options'][ $site_id ] && ! isset( $state['transients'][ $site_id ]['noobrocket_database_optimization_process'] ), 'progress/start cleared without unrelated state loss on site ' . $site_id );
	lifecycle_check( 'cancelled' === $state['transients'][ $site_id ]['noobrocket_database_optimization_process_complete']['status'] && isset( $state['files'][ NOOBROCKET_CACHE_ROOT_PATH . '.' . $identifier . '_process_cancelled' ] ), 'receipt and cancellation flag follow switched site ' . $site_id );
}
lifecycle_check( ! isset( $state['network_options'][5]['noobrocket_database_optimization_batch_legacy'], $state['site_transients'][5]['noobrocket_database_optimization_process_lock'] ), 'old network queue and lock are retired' );
lifecycle_check( isset( $state['network_options'][5]['foreign_batch'], $state['network_options'][5]['noobrocket_database_optimization_999_batch_outside'], $state['network_options'][8]['noobrocket_database_optimization_batch_foreign_network'], $state['site_transients'][5]['foreign_process_lock'], $state['site_transients'][8]['foreign_process_lock'] ), 'foreign, out-of-scope and other-network storage is retained' );

$empty = new ReflectionMethod( $process, 'is_queue_empty' );
$empty->setAccessible( true );
foreach ( $state['sites'] as $site_id ) {
	switch_to_blog( $site_id );
	try {
		$reactivated = new NoobRocket\Engine\Admin\Database\OptimizationProcess();
		lifecycle_check( $empty->invoke( $reactivated ), 'reactivated native queue has no old task on site ' . $site_id );
		try { $reactivated->maybe_handle(); }
		catch ( LifecycleExit $exit ) { /* Native no-work exit is expected. */ }
	} finally { restore_current_blog(); }
}
lifecycle_check( 0 === $state['deletions'], 'reactivation cannot dispatch an old destructive task' );
$before = time();
$subscriber->database_optimization_scheduled();
$next = $state['events'][42]['noobrocket_database_optimization_time_event'];
lifecycle_check( $next >= $before + 604800 && $next <= time() + 604800, 'reactivation restores saved weekly schedule one interval ahead, not immediately' );
$subscriber->database_optimization_scheduled();
lifecycle_check( $next === $state['events'][42]['noobrocket_database_optimization_time_event'], 'existing restored schedule is not duplicated or advanced' );

// Site-only deactivation cannot cancel a neighboring or shared legacy queue.
seed_site( 42 ); seed_site( 2 );
$state['network_options'][5]['noobrocket_database_optimization_batch_legacy'] = [ 'shared' ];
$queries = count( $state['site_queries'] );
$subscriber->deactivate( 0, false );
lifecycle_check( $queries === count( $state['site_queries'] ) && isset( $state['network_options'][5]['noobrocket_database_optimization_2_batch_a'], $state['network_options'][5]['noobrocket_database_optimization_batch_legacy'] ), 'site-only deactivation does not enumerate or cancel neighbor/shared queues' );
lifecycle_check( ! isset( $state['network_options'][5]['noobrocket_database_optimization_42_batch_a'] ), 'site-only deactivation still clears its own queue' );
unset( $state['transients'][42]['noobrocket_database_optimization_process_complete'] );
$subscriber->deactivate( 0, false );
lifecycle_check( ! isset( $state['transients'][42]['noobrocket_database_optimization_process_complete'] ), 'idle deactivation does not invent a cancelled-cleanup error notice' );

seed_site( 42 ); seed_site( 2 );
$state['options'][2]['active_plugins'] = [ 'noobrocket/noobrocket.php' ];
$retained_events = $state['events'][2];
$retained_options = $state['options'][2];
unset( $state['files'][ NOOBROCKET_CACHE_ROOT_PATH . '.noobrocket_database_optimization_2_process_cancelled' ] );
$subscriber->deactivate( 0, true );
lifecycle_check( isset( $state['network_options'][5]['noobrocket_database_optimization_2_batch_a'], $state['site_transients'][5]['noobrocket_database_optimization_2_process_lock'] ), 'independently active blog preserves database queue and process lock on network deactivation' );
lifecycle_check( $retained_events === $state['events'][2] && $retained_options === $state['options'][2], 'independently active blog preserves cleanup schedules and running progress' );
lifecycle_check( ! isset( $state['files'][ NOOBROCKET_CACHE_ROOT_PATH . '.noobrocket_database_optimization_2_process_cancelled' ] ), 'independently active blog receives no cancellation marker' );
lifecycle_check( ! isset( $state['network_options'][5]['noobrocket_database_optimization_42_batch_a'] ) && 42 === get_current_blog_id() && ! $state['stack'], 'network deactivation still cancels losing blogs and restores context' );
unset( $state['options'][2]['active_plugins'] );

$state['fail_blog'] = 2;
try { $subscriber->deactivate( 0, true ); lifecycle_check( false, 'injected lifecycle failure must propagate' ); }
catch ( RuntimeException $error ) { lifecycle_check( 'Injected cron failure.' === $error->getMessage() && 42 === get_current_blog_id() && ! $state['stack'], 'failure restores switch context before propagation' ); }
$state['fail_blog'] = null;
echo 'PASS: database network lifecycle (', $state['checks'], " assertions; native entry/event/background source, 105 modeled sites)\n";
