<?php
/** Settings-facing preload hooks use the native queue without HTTP, sleep or shared-site state. */
declare(strict_types=1);

namespace NoobRocket\Engine\Preload\Controller {
	function usleep( $delay ) {
		throw new \LogicException( 'Settings callbacks must not sleep for preload requests.' );
	}
}

namespace {
	define( 'ABSPATH', rtrim( getenv( 'NOOBROCKET_WORDPRESS_SOURCE' ) ?: dirname( __DIR__ ) . '/.artifacts/wordpress', '/\\' ) . '/' );
	define( 'NOOBROCKET_SLUG', 'noobrocket_settings' );
	define( 'MINUTE_IN_SECONDS', 60 );
	require ABSPATH . 'wp-includes/plugin.php';
	require ABSPATH . 'wp-includes/class-wp-error.php';
	require dirname( __DIR__ ) . '/noobrocket/vendor/autoload.php';

	$fixture = [ 'paused' => false, 'saved' => [ 'manual_preload' => 1 ], 'actions' => [], 'http' => 0, 'purges' => 0, 'configs' => 0 ];
	$current_user = (object) [ 'ID' => 1 ];
	function get_option( $name, $default = false ) {
		if ( 'noobrocket_settings' === $name ) {
			return $GLOBALS['fixture']['saved'];
		}
		return 'noobrocket_preload_paused' === $name ? $GLOBALS['fixture']['paused'] : $default;
	}
	function get_user_meta( $id, $key, $single = false ) { return []; }
	function set_transient( $key, $value, $expiration = 0 ) {}
	function home_url( $path = '' ) { return 'https://example.test/' . ltrim( $path, '/' ); }
	function wp_json_encode( $value ) { return json_encode( $value ); }
	function noobrocket_get_constant( $name, $default = null ) { return defined( $name ) ? constant( $name ) : $default; }
	function flush_noobrocket_htaccess( $remove = false ) {}
	function noobrocket_generate_config_file() { ++$GLOBALS['fixture']['configs']; }
	function noobrocket_clean_minify( $type ) {}
	function noobrocket_clean_domain() {
		++$GLOBALS['fixture']['purges'];
		do_action( 'noobrocket_after_clean_domain' );
	}
	function noobrocket_direct_filesystem() { return new WP_Filesystem_Direct(); }
	function wp_remote_get( $url, $args = [] ) {
		++$GLOBALS['fixture']['http'];
		throw new LogicException( 'Settings callbacks must not crawl the homepage.' );
	}
	function wp_safe_remote_get( $url, $args = [] ) {
		++$GLOBALS['fixture']['http'];
		throw new LogicException( 'Settings callbacks must not synchronously warm the homepage.' );
	}
	function as_has_scheduled_action( $hook, $args, $group ) {
		foreach ( $GLOBALS['fixture']['actions'] as $action ) {
			if ( $hook === $action['hook'] && $group === $action['group'] && $args === $action['args'] ) {
				return true;
			}
		}
		return false;
	}
	function as_enqueue_async_action( $hook, $args, $group, $unique = false ) {
		$GLOBALS['fixture']['actions'][] = [ 'hook' => $hook, 'args' => $args, 'group' => $group ];
		return count( $GLOBALS['fixture']['actions'] );
	}
	function as_unschedule_all_actions( $hook, $args, $group ) {
		$GLOBALS['fixture']['actions'] = array_values( array_filter( $GLOBALS['fixture']['actions'], static function ( $action ) use ( $hook, $args, $group ) {
			return $group !== $action['group'] || ( '' !== $hook && $hook !== $action['hook'] ) || ( [] !== $args && $args !== $action['args'] );
		} ) );
	}
	class WP_Filesystem_Direct {}
	class SettingsPreloadTable extends NoobRocket\Engine\Preload\Database\Tables\Cache {
		public $truncated = 0;
		public function __construct() {}
		public function truncate_cache_table(): bool { ++$this->truncated; return true; }
	}
	class SettingsPreloadQuery extends NoobRocket\Engine\Preload\Database\Queries\Cache {
		public $pending = 0;
		public $reverted = 0;
		public function __construct() {}
		public function set_all_to_pending() { ++$this->pending; }
		public function revert_in_progress() { ++$this->reverted; }
	}
	class ActivationPreloadWorker extends NoobRocket\Engine\Preload\Controller\PreloadUrl {
		public $started = [];
		public function __construct() {}
		public function preload_url( string $url ) { $this->started[] = [ $url, current_filter() ]; }
	}
	/** Use the real service registration; only the table/database boundary is replaced. */
	class SettingsPreloadContainer extends NoobRocket\Dependencies\League\Container\Container {
		public $table;
		public function get( $id ) {
			return 'preload_caches_table' === $id ? $this->table : parent::get( $id );
		}
	}
	function settings_preload_check( $condition, $message ) {
		if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
		++$GLOBALS['checks'];
	}
	function settings_preload_save( array $old, array $new ): void {
		$GLOBALS['fixture']['saved'] = $new;
		do_action( 'update_option_noobrocket_settings', $old, $new );
	}

	require dirname( __DIR__ ) . '/noobrocket/inc/functions/admin.php';
	require dirname( __DIR__ ) . '/noobrocket/inc/functions/options.php';
	require dirname( __DIR__ ) . '/noobrocket/inc/admin/options.php';
	$checks = 0;
	$options = new NoobRocket\Admin\Options_Data( [ 'manual_preload' => 1 ] );
	$container = new SettingsPreloadContainer();
	$container->table = new SettingsPreloadTable();
	$container->add( 'options', $options );
	$provider = new NoobRocket\Engine\Preload\ServiceProvider();
	$provider->setContainer( $container );
	$provider->register();
	$settings = $container->get( 'preload_settings' );
	settings_preload_check( $settings instanceof NoobRocket\Engine\Preload\Admin\Settings, 'native service registration resolves the settings owner' );
	$queue = new NoobRocket\Engine\Preload\Controller\Queue();
	$query = new SettingsPreloadQuery();
	$initial = new NoobRocket\Engine\Preload\Controller\LoadInitialSitemap( $queue, $query, new NoobRocket\Engine\Preload\Controller\CrawlHomepage() );
	$reflection = new ReflectionClass( NoobRocket\Engine\Preload\Subscriber::class );
	$subscriber = $reflection->newInstanceWithoutConstructor();
	foreach ( [ 'options' => $options, 'queue' => $queue, 'controller' => $initial, 'clear_cache' => new NoobRocket\Engine\Preload\Controller\ClearCache( $query ) ] as $name => $value ) {
		$property = $reflection->getProperty( $name );
		$property->setAccessible( true );
		$property->setValue( $subscriber, $value );
	}
	$events = new NoobRocket\Event_Management\Event_Manager();
	$events->add_subscriber( $subscriber );
	$events->add_subscriber( $container->get( 'preload_admin_subscriber' ) );

	$old = [ 'manual_preload' => 1, 'minify_js' => 0, 'database_revisions' => 0 ];
	$new = array_merge( $old, [ 'minify_js' => 1 ] );
	settings_preload_save( $old, $new );
	settings_preload_check( 1 === $fixture['purges'] && 1 === $query->pending, 'optimization changes retain cache purge and pending-row ownership' );
	settings_preload_check( 1 === count( $fixture['actions'] ) && 'noobrocket_preload_job_preload_url' === $fixture['actions'][0]['hook'], 'optimization save queues homepage warming' );
	settings_preload_check( [ home_url() ] === $fixture['actions'][0]['args'] && 'noobrocket-preload' === $fixture['actions'][0]['group'], 'homepage action uses the existing URL hook and preload group' );
	$settings->preload_homepage();
	settings_preload_check( 1 === count( $fixture['actions'] ), 'repeated homepage warming retains native queue deduplication' );

	$fixture['actions'] = [];
	settings_preload_save( $old, array_merge( $old, [ 'database_revisions' => 1 ] ) );
	settings_preload_check( [] === $fixture['actions'] && 1 === $fixture['purges'], 'database-only save does not preload or purge page cache' );
	$subscriber->maybe_load_initial_sitemap( [ 'manual_preload' => 1 ], [ 'manual_preload' => 1 ] );
	settings_preload_check( [] === $fixture['actions'], 'unchanged preload value does not start discovery' );
	$subscriber->maybe_load_initial_sitemap( [], [ 'manual_preload' => 1 ] );
	settings_preload_check( [] === $fixture['actions'], 'missing previous preload value retains existing transition semantics' );
	settings_preload_save( [ 'manual_preload' => 0 ], [ 'manual_preload' => 1 ] );
	settings_preload_check( 1 === count( $fixture['actions'] ) && 'noobrocket_preload_job_load_initial_sitemap' === $fixture['actions'][0]['hook'], 'enabling preload queues discovery without a synchronous crawl' );
	settings_preload_save( [ 'manual_preload' => 1 ], [ 'manual_preload' => 0 ] );
	settings_preload_check( [] === $fixture['actions'] && 1 === $query->reverted, 'disabling preload retains pending-action cancellation and row recovery' );
	settings_preload_check( (bool) $options->get( 'manual_preload' ) && ! $settings->is_enabled(), 'current saved disabled state wins over the injected enabled snapshot' );

	$fixture['saved']['manual_preload'] = 1;
	$settings->clear_and_preload();
	settings_preload_check( 1 === $container->table->truncated && 2 === count( $fixture['actions'] ), 'domain reset truncates obsolete rows and queues discovery plus homepage' );
	$fixture['actions'] = [];
	$fixture['paused'] = true;
	$settings->preload_homepage();
	$settings->clear_and_preload();
	$subscriber->maybe_load_initial_sitemap( [ 'manual_preload' => 0 ], [ 'manual_preload' => 1 ] );
	settings_preload_check( [] === $fixture['actions'] && 2 === $container->table->truncated, 'paused preload admits no warm-up but still resets obsolete domain rows' );
	$fixture['paused'] = false;
	$fixture['saved']['manual_preload'] = 0;
	$settings->preload_homepage();
	$settings->clear_and_preload();
	settings_preload_check( [] === $fixture['actions'] && 3 === $container->table->truncated, 'disabled preload admits no warm-up while domain reset remains owned' );
	foreach ( [ 5, 20 ] as $priority ) {
		remove_action( 'update_option_noobrocket_settings', 'noobrocket_after_save_options', 10 );
		add_action( 'update_option_noobrocket_settings', 'noobrocket_after_save_options', $priority, 2 );
		settings_preload_save( $old, array_merge( $new, [ 'manual_preload' => 0 ] ) );
		settings_preload_check( [] === $fixture['actions'], 'mixed optimization/disable save stays disabled with callback priority ' . $priority );
		remove_action( 'update_option_noobrocket_settings', 'noobrocket_after_save_options', $priority );
		add_action( 'update_option_noobrocket_settings', 'noobrocket_after_save_options', 10, 2 );
	}
	$enable = static function () { return true; };
	add_filter( 'pre_get_noobrocket_option_manual_preload', $enable );
	settings_preload_check( $settings->is_enabled(), 'native pre-read preload override is retained' );
	$settings->preload_homepage();
	settings_preload_check( 1 === count( $fixture['actions'] ), 'native pre-read enable override permits queued warming' );
	remove_filter( 'pre_get_noobrocket_option_manual_preload', $enable );
	$fixture['actions'] = [];
	$fixture['saved']['manual_preload'] = 1;
	$disable = static function () { return false; };
	add_filter( 'get_noobrocket_option_manual_preload', $disable );
	settings_preload_check( ! $settings->is_enabled(), 'native post-read preload override is retained' );
	$settings->preload_homepage();
	settings_preload_check( [] === $fixture['actions'], 'native post-read disable override suppresses queued warming' );
	remove_filter( 'get_noobrocket_option_manual_preload', $disable );
	settings_preload_check( 0 === $fixture['http'], 'all settings-facing paths perform zero HTTP requests' );

	$worker = new ActivationPreloadWorker();
	$activation = new NoobRocket\Engine\Preload\Activation\Activation( $worker, $queue, $query, $options );
	$activation->activate();
	$fixture['actions'] = [];
	$fixture['saved'] = [];
	do_action( 'noobrocket_activation' );
	do_action( 'noobrocket_after_activation' );
	settings_preload_check( [] === $worker->started, 'native first activation never starts the blocking URL worker inline' );
	settings_preload_check( [ 'noobrocket_preload_job_load_initial_sitemap', 'noobrocket_preload_job_preload_url' ] === array_column( $fixture['actions'], 'hook' ), 'first activation preserves initial discovery then homepage queue coverage' );
	settings_preload_check( [ home_url() ] === $fixture['actions'][1]['args'] && 'noobrocket-preload' === $fixture['actions'][1]['group'], 'activation homepage uses the existing worker hook and group' );
	do_action( 'noobrocket_after_activation' );
	settings_preload_check( 2 === count( $fixture['actions'] ), 'repeated activation homepage preparation uses native unique admission' );
	$frontend = ( new ReflectionClass( NoobRocket\Engine\Preload\Frontend\Subscriber::class ) )->newInstanceWithoutConstructor();
	$preload_controller = new ReflectionProperty( $frontend, 'preload_controller' );
	$preload_controller->setAccessible( true );
	$preload_controller->setValue( $frontend, $worker );
	$events->add_subscriber( $frontend );
	do_action( $fixture['actions'][1]['hook'], ...$fixture['actions'][1]['args'] );
	settings_preload_check( [ [ home_url(), 'noobrocket_preload_job_preload_url' ] ] === $worker->started, 'queued action dispatch reaches the native frontend worker owner outside activation' );
	$fixture['actions'] = [];
	$fixture['saved'] = [ 'manual_preload' => 0 ];
	do_action( 'noobrocket_activation' );
	do_action( 'noobrocket_after_activation' );
	settings_preload_check( [] === $fixture['actions'] && 1 === count( $worker->started ), 'saved disabled preload suppresses both activation jobs despite injected enabled options' );
	$fixture['saved'] = [ 'manual_preload' => 1 ];
	$fixture['paused'] = true;
	do_action( 'noobrocket_activation' );
	do_action( 'noobrocket_after_activation' );
	settings_preload_check( [] === $fixture['actions'] && 1 === count( $worker->started ), 'paused preload suppresses both activation jobs and inline work' );
	settings_preload_check( 0 === $fixture['http'], 'settings and activation preparation perform zero HTTP requests' );

	echo 'PASS: ', $checks, ' settings preload queue and ownership assertions.', PHP_EOL;
}
