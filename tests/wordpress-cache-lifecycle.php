<?php
/** Native deactivation entry and real policy files; WordPress state stays modeled and no site is booted. */
declare(strict_types=1);

class CacheLifecycleContainer {
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
	class_alias( CacheLifecycleContainer::class, $class_name );
}
class CacheLifecycleHost {
	public static function get_host_service() { return ''; }
}
class_alias( CacheLifecycleHost::class, 'NoobRocket\\ThirdParty\\Hostings\\HostResolver' );

$root = dirname( __DIR__ );
$temporary = sys_get_temp_dir() . '/noobrocket-cache-lifecycle-' . bin2hex( random_bytes( 6 ) );
mkdir( $temporary );
define( 'ABSPATH', rtrim( getenv( 'NOOBROCKET_WORDPRESS_SOURCE' ) ?: $root . '/.artifacts/wordpress', '/\\' ) . '/' );
define( 'NOOBROCKET_FILE', $root . '/noobrocket/noobrocket.php' );
define( 'NOOBROCKET_PATH', $root . '/noobrocket/' );
define( 'WP_PLUGIN_DIR', $root );
define( 'WPMU_PLUGIN_DIR', $root . '/unused-mu-plugins' );
define( 'NOOBROCKET_CONFIG_PATH', $temporary . '/' );
define( 'NOOBROCKET_SLUG', 'noobrocket_settings' );
define( 'COOKIEHASH', 'fixture' );
define( 'LOGGED_IN_COOKIE', 'wordpress_logged_in_fixture' );
require ABSPATH . 'wp-includes/plugin.php';
require ABSPATH . 'wp-includes/formatting.php';
require ABSPATH . 'wp-includes/class-wp-error.php';
require ABSPATH . 'wp-includes/class-wp-role.php';
require NOOBROCKET_PATH . 'vendor/autoload.php';
require NOOBROCKET_PATH . 'inc/constants.php';
require NOOBROCKET_PATH . 'inc/functions/formatting.php';
require NOOBROCKET_PATH . 'inc/functions/files.php';

$state = [ 'blog' => 42, 'network' => 5, 'multisite' => true, 'stack' => [], 'queries' => [], 'actions' => [], 'events' => [], 'independent' => [ 105 ], 'fail_blog' => null, 'checks' => 0 ];
$wp_plugin_paths = [];
$is_apache = false;
$_GET = [];
function is_multisite() { return $GLOBALS['state']['multisite']; }
function get_current_blog_id() { return $GLOBALS['state']['blog']; }
function get_current_network_id() { return $GLOBALS['state']['network']; }
function get_sites( $arguments ) { $GLOBALS['state']['queries'][] = $arguments; return [ 1, 42, 105 ]; }
function switch_to_blog( $id ) { $GLOBALS['state']['stack'][] = get_current_blog_id(); $GLOBALS['state']['blog'] = $id; }
function restore_current_blog() { $GLOBALS['state']['blog'] = array_pop( $GLOBALS['state']['stack'] ); }
function get_option( $name, $default = false ) {
	if ( 'noobrocket_settings' === $name ) {
		if ( get_current_blog_id() === $GLOBALS['state']['fail_blog'] ) { throw new RuntimeException( 'Injected policy failure.' ); }
		return [ 'secret_cache_key' => 'fixture' ];
	}
	if ( 'active_plugins' === $name ) { return in_array( get_current_blog_id(), $GLOBALS['state']['independent'], true ) ? [ 'noobrocket/noobrocket.php' ] : []; }
	if ( 'permalink_structure' === $name ) { return '/%postname%/'; }
	return $default;
}
function update_option() {}
function delete_transient( $name ) { $GLOBALS['state']['actions'][] = [ 'transient', get_current_blog_id(), $name ]; }
function delete_site_transient() {}
function noobrocket_get_home_url() { return 'https://site' . get_current_blog_id() . '.example.test'; }
function get_noobrocket_i18n_subdomains() { return []; }
function noobrocket_get_ignored_parameters() { return []; }
function get_noobrocket_cache_mandatory_cookies() { return ''; }
function get_noobrocket_cache_dynamic_cookies() { return []; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function wp_normalize_path( $path ) { return str_replace( '\\', '/', $path ); }
function flush_noobrocket_htaccess() {}
function noobrocket_renew_box() {}
function flush_rewrite_rules() {}
function wp_clear_scheduled_hook( $name ) { unset( $GLOBALS['state']['events'][ get_current_blog_id() ][ $name ] ); }
function wp_next_scheduled( $name ) { return $GLOBALS['state']['events'][ get_current_blog_id() ][ $name ] ?? false; }
function wp_unschedule_event( $timestamp, $name ) { wp_clear_scheduled_hook( $name ); }
function as_unschedule_all_actions( $hook, $arguments, $group ) { $GLOBALS['state']['actions'][] = [ 'cancel', get_current_blog_id(), $group, $hook ]; }
function as_unschedule_action( $hook, $arguments, $group ) { $GLOBALS['state']['actions'][] = [ 'cancel', get_current_blog_id(), $group, $hook ]; }
function get_role( $name ) { return $GLOBALS['state']['roles'][ get_current_blog_id() ] ?? null; }
function wp_roles() { return new class { public function remove_cap( $name, $capability ) {} }; }
function cache_lifecycle_check( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
	++$GLOBALS['state']['checks'];
}
function seed_cache_policies(): void {
	foreach ( [ 1, 42, 105, 999 ] as $id ) {
		file_put_contents( NOOBROCKET_CONFIG_PATH . 'site' . $id . '.example.test.php', '<?php $noobrocket_config_complete = true;' );
		$GLOBALS['state']['roles'][ $id ] = new WP_Role( 'administrator', [ 'noobrocket_manage_options' => true, 'foreign_capability' => true ] );
		$GLOBALS['state']['events'][ $id ] = [ 'noobrocket_purge_time_event' => 1, 'noobrocket_cache_dir_size_check' => 1, 'noobrocket_preload_process_pending' => 1, 'noobrocket_preload_clean_rows_time_event' => 1, 'noobrocket_preload_revert_old_failed_rows' => 1, 'noobrocket_generate_images' => 1, 'noobrocket_update_dynamic_lists' => 1, 'noobrocket_performance_hints_cleanup' => 1, 'action_scheduler_run_queue_noobrocket_rucss' => 1, 'foreign_event' => 1 ];
	}
}

$expiration = ( new ReflectionClass( NoobRocket\Engine\Cache\PurgeExpired\Subscriber::class ) )->newInstanceWithoutConstructor();
$preload = ( new ReflectionClass( NoobRocket\Engine\Preload\Subscriber::class ) )->newInstanceWithoutConstructor();
$activation = ( new ReflectionClass( NoobRocket\Engine\Preload\Activation\Activation::class ) )->newInstanceWithoutConstructor();
( new ReflectionProperty( $preload, 'activation' ) )->setValue( $preload, $activation );
( new ReflectionProperty( $preload, 'queue' ) )->setValue( $preload, new NoobRocket\Engine\Preload\Controller\Queue() );
$manager = new NoobRocket\Event_Management\Event_Manager();
$manager->add_subscriber( $expiration );
$manager->add_subscriber( $preload );
$picture = ( new ReflectionClass( NoobRocket\Engine\Media\Picture\Generator::class ) )->newInstanceWithoutConstructor();
$dynamic = ( new ReflectionClass( NoobRocket\Engine\Optimization\DynamicLists\Subscriber::class ) )->newInstanceWithoutConstructor();
$lists = ( new ReflectionClass( NoobRocket\Engine\Optimization\DynamicLists\DynamicLists::class ) )->newInstanceWithoutConstructor();
( new ReflectionProperty( $dynamic, 'dynamic_lists' ) )->setValue( $dynamic, $lists );
$fonts = ( new ReflectionClass( NoobRocket\Engine\Media\Fonts\Admin\Subscriber::class ) )->newInstanceWithoutConstructor();
$fonts_data = ( new ReflectionClass( NoobRocket\Engine\Media\Fonts\Admin\Data::class ) )->newInstanceWithoutConstructor();
( new ReflectionProperty( $fonts, 'data' ) )->setValue( $fonts, $fonts_data );
$hints = new NoobRocket\Engine\Common\PerformanceHints\Cron\Subscriber( new NoobRocket\Engine\Common\PerformanceHints\Cron\Controller( [] ) );
$jobs = ( new ReflectionClass( NoobRocket\Engine\Common\JobManager\Cron\Subscriber::class ) )->newInstanceWithoutConstructor();
foreach ( [ $picture, $dynamic, $fonts, $hints, $jobs ] as $subscriber ) { $manager->add_subscriber( $subscriber ); }
( new NoobRocket\Engine\Capabilities\Manager() )->deactivate();
$deactivations = [];
add_action( 'noobrocket_deactivation', static function ( $remaining, $network_wide ) use ( &$deactivations ) { $deactivations[] = [ $remaining, $network_wide ]; }, 100, 2 );

try {
	seed_cache_policies();
	NoobRocket\Engine\Deactivation\Deactivation::deactivate_plugin( true );
	cache_lifecycle_check( ! is_file( NOOBROCKET_CONFIG_PATH . 'site1.example.test.php' ) && ! is_file( NOOBROCKET_CONFIG_PATH . 'site42.example.test.php' ), 'network deactivation removes real policies for every blog losing the plugin' );
	cache_lifecycle_check( is_file( NOOBROCKET_CONFIG_PATH . 'site105.example.test.php' ) && is_file( NOOBROCKET_CONFIG_PATH . 'site999.example.test.php' ), 'independently active and other-network policies survive' );
	cache_lifecycle_check( [ [ 2, true ] ] === $deactivations, 'public deactivation fires once with the actual remaining policy count' );
	cache_lifecycle_check( 42 === get_current_blog_id() && [] === $state['stack'], 'network cleanup restores blog context' );
	foreach ( $state['queries'] as $arguments ) {
		cache_lifecycle_check( [ 'fields' => 'ids', 'number' => 0, 'network_id' => 5 ] === $arguments, 'all live cleanup owners enumerate only the full affected network' );
	}
	foreach ( [ 1, 42 ] as $id ) {
		cache_lifecycle_check( [ 'foreign_event' => 1 ] === $state['events'][ $id ], 'owned expiry/preload/size crons stop only on losing blog ' . $id );
	}
	foreach ( [ 105, 999 ] as $id ) {
		cache_lifecycle_check( count( $state['events'][ $id ] ) === 10, 'retained blog ' . $id . ' keeps all background schedules' );
	}
	$cancelled_blogs = array_column( array_filter( $state['actions'], static function ( $action ) { return 'cancel' === $action[0] && 'noobrocket-preload' === $action[2]; } ), 1 );
	cache_lifecycle_check( [ 1, 42 ] === $cancelled_blogs, 'preload scheduler cancellation follows only losing sites' );
	$font_blogs = array_column( array_filter( $state['actions'], static function ( $action ) { return 'cancel' === $action[0] && 'noobrocket_fonts_data_collection' === $action[3]; } ), 1 );
	cache_lifecycle_check( [ 1, 42 ] === $font_blogs, 'font scheduler cancellation follows only losing sites' );
	$state['blog'] = 105;
	seed_cache_policies();
	NoobRocket\Engine\Deactivation\Deactivation::deactivate_plugin( true );
	cache_lifecycle_check( $state['roles'][105]->has_cap( 'noobrocket_manage_options' ) && is_file( NOOBROCKET_CONFIG_PATH . 'site105.example.test.php' ), 'independently active originating blog retains actual WordPress role authority and cache policy' );
	foreach ( [ 1, 42 ] as $id ) {
		cache_lifecycle_check( ! $state['roles'][ $id ]->has_cap( 'noobrocket_manage_options' ) && $state['roles'][ $id ]->has_cap( 'foreign_capability' ), 'losing blog ' . $id . ' removes only owned capabilities from the actual WordPress role' );
	}
	cache_lifecycle_check( 105 === get_current_blog_id() && [] === $state['stack'] && $state['roles'][999]->has_cap( 'noobrocket_manage_options' ), 'role cleanup restores independent origin and retains other-network authority' );
	$state['blog'] = 42;
	$state['queries'] = [];
	$deactivations = [];
	seed_cache_policies();
	NoobRocket\Engine\Deactivation\Deactivation::deactivate_plugin( false );
	cache_lifecycle_check( ! is_file( NOOBROCKET_CONFIG_PATH . 'site42.example.test.php' ) && is_file( NOOBROCKET_CONFIG_PATH . 'site1.example.test.php' ), 'site-only deactivation deletes only the selected real policy' );
	cache_lifecycle_check( [] === $state['queries'] && [ [ 3, false ] ] === $deactivations, 'site-only deactivation never enumerates neighbors or replays its event' );
	seed_cache_policies();
	$state['fail_blog'] = 1;
	try {
		NoobRocket\Engine\Deactivation\Deactivation::deactivate_plugin( true );
		throw new RuntimeException( 'Injected failure did not reach the native policy owner.' );
	} catch ( RuntimeException $error ) {
		cache_lifecycle_check( 'Injected policy failure.' === $error->getMessage(), 'native policy failure propagates without pretending success' );
	}
	cache_lifecycle_check( 42 === get_current_blog_id() && [] === $state['stack'], 'callback failure still restores original blog context' );
	echo 'PASS: ', $state['checks'], ' native deactivation and real policy-file assertions.', PHP_EOL;
} finally {
	foreach ( new DirectoryIterator( $temporary ) as $entry ) { if ( $entry->isFile() ) { unlink( $entry->getPathname() ); } }
	rmdir( $temporary );
}
