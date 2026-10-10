<?php
/** Preload outcomes, staged refresh and exact discovery; native owners with in-memory boundaries. */
declare(strict_types=1);

define( 'ABSPATH', rtrim( getenv( 'NOOBROCKET_WORDPRESS_SOURCE' ) ?: dirname( __DIR__ ) . '/.artifacts/wordpress', '/\\' ) . '/' );
define( 'WPINC', 'wp-includes' );
define( 'NOOBROCKET_SLUG', 'noobrocket_settings' );
define( 'MINUTE_IN_SECONDS', 60 );
require ABSPATH . 'wp-includes/class-wp-http.php';
require ABSPATH . 'wp-includes/class-wp-error.php';
require dirname( __DIR__ ) . '/noobrocket/vendor/autoload.php';

$fixture = [ 'paused' => false, 'status' => 200, 'publish' => [], 'requests' => [], 'actions' => [], 'count_queries' => [], 'request_filter' => false, 'events' => [] ];
function get_option( $name, $default = false ) { return 'noobrocket_preload_paused' === $name ? $GLOBALS['fixture']['paused'] : $default; }
function get_transient( $name ) { return true; }
function set_transient( $name, $value, $expiration ) {}
function current_time( $type, $gmt = false ) { return gmdate( 'Y-m-d H:i:s' ); }
function apply_filters( $name, $value, ...$args ) {
	if ( 'noobrocket_preload_delay_between_requests' === $name ) { return 1; }
	if ( 'noobrocket_preload_url_request_args' === $name && $GLOBALS['fixture']['request_filter'] ) {
		$value['blocking'] = false; $value['redirection'] = 5; $value['timeout'] = 90;
	}
	return $value;
}
function absint( $value ) { return abs( (int) $value ); }
function user_trailingslashit( $value ) { return rtrim( $value, '/' ) . '/'; }
function trailingslashit( $value ) { return rtrim( $value, '/\\' ) . '/'; }
function untrailingslashit( $value ) { return rtrim( $value, '/\\' ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function home_url( $path = '' ) { return 'https://example.test/blog/' . ltrim( $path, '/' ); }
function add_query_arg( $args, $url ) { return $url; }
function is_user_logged_in() { return false; }
function do_action( $hook, ...$args ) { $GLOBALS['fixture']['events'][] = [ $hook, $args ]; }
function noobrocket_get_constant( $name ) { return constant( $name ); }
function get_noobrocket_parse_url( $url ) { return array_merge( [ 'scheme' => '', 'host' => '', 'path' => '/', 'query' => '' ], parse_url( $url ) ); }
function noobrocket_is_stale_refresh_enabled( $options ) { return ! empty( $options['cache_stale_refresh'] ); }
function noobrocket_get_cache_lifespan( $options ) { return 60; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_remote_retrieve_response_code( $response ) { return $response['response']['code'] ?? 0; }
function wp_safe_remote_get( $url, $args ) {
	$GLOBALS['fixture']['requests'][] = [ $url, $args ];
	foreach ( $GLOBALS['fixture']['publish'] as $path ) { file_put_contents( $path, 'complete published cache' ); }
	return 'error' === $GLOBALS['fixture']['status'] ? new WP_Error( 'http_request_failed', 'fixture' ) : [ 'response' => [ 'code' => $GLOBALS['fixture']['status'] ] ];
}
function as_has_scheduled_action( $hook, $args, $group ) {
	foreach ( $GLOBALS['fixture']['actions'] as $action ) {
		if ( $hook === $action['hook'] && $group === $action['group'] && ( [] === $args || $args === $action['args'] ) && in_array( $action['status'], [ 'pending', 'in-progress' ], true ) ) { return true; }
	}
	return false;
}
class ActionScheduler_Store {
	const STATUS_PENDING = 'pending';
	const STATUS_RUNNING = 'in-progress';
}
class ActionScheduler {
	public static function is_initialized( $method ) { return true; }
	public static function store() {
		return new class {
			public function query_actions( $query, $type ) {
				$GLOBALS['fixture']['count_queries'][] = [ $query, $type ];
				if ( 'count' !== $type ) { throw new LogicException( 'Action objects must not be hydrated for counts.' ); }
				return count( array_filter( $GLOBALS['fixture']['actions'], static function ( $action ) use ( $query ) {
					return $query['hook'] === $action['hook'] && $query['group'] === $action['group'] && $query['status'] === $action['status'];
				} ) );
			}
		};
	}
}
class WP_Filesystem_Direct { public function exists( $path ) { return file_exists( $path ); } }
class OutcomeQuery extends NoobRocket\Engine\Preload\Database\Queries\Cache {
	public $row;
	public $pending_batch;
	public $missing_rows = false;
	public $stuck = [];
	public function __construct( $url ) { $this->row = (object) [ 'id' => 1, 'url' => $url, 'status' => 'in-progress' ]; }
	public function query( $query = [], bool $use_cache = true ) {
		if ( $this->missing_rows || ( isset( $query['url'] ) && $query['url'] !== $this->row->url ) ) {
			return [];
		}
		return [ $this->row ];
	}
	public function update_item( $id = 0, $data = [] ) { foreach ( $data as $key => $value ) { $this->row->$key = $value; } return true; }
	public function get_pending_jobs( int $total = 45 ) { $this->pending_batch = $total; return []; }
	public function get_outdated_in_progress_jobs( int $delay = 3 ) { return $this->stuck; }
	public function create_or_update( array $resource ) { $this->row->status = $resource['status'] ?? $this->row->status; return 1; }
}
class DiscoveryFixture {
	use NoobRocket\Engine\Preload\Controller\CheckExcludedTrait;
	public function resolve( $url ) { return $this->get_site_page_url( $url ); }
}
function outcome_check( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
	++$GLOBALS['checks'];
}
$checks = 0;
$temporary = sys_get_temp_dir() . '/noobrocket-preload-outcome-' . bin2hex( random_bytes( 6 ) );
mkdir( $temporary . '/example.test/blog/article', 0777, true );
define( 'NOOBROCKET_CACHE_PATH', $temporary . '/' );
$GLOBALS['wp_rewrite'] = (object) [ 'pagination_base' => 'page' ];
$GLOBALS['wp'] = (object) [ 'request' => 'article' ];
$url = 'https://example.test/blog/article';
$desktop = $temporary . '/example.test/blog/article/index-https.html';
$mobile = $temporary . '/example.test/blog/article/index-mobile-https.html';
$options = new NoobRocket\Admin\Options_Data( [ 'cache_ssl' => 1, 'do_caching_mobile_files' => 1, 'manual_preload' => 1, 'cache_stale_refresh' => 1 ] );
$queue = new NoobRocket\Engine\Preload\Controller\Queue();
$query = new OutcomeQuery( $url );
$controller = new NoobRocket\Engine\Preload\Controller\PreloadUrl( $options, $queue, $query, new WP_Filesystem_Direct() );
try {
	foreach ( [ 'uncacheable' => 200, 'redirect' => 302, 'server-error' => 503, 'transport-error' => 'error' ] as $case => $status ) {
		$fixture['status'] = $status; $fixture['publish'] = []; $query->row->status = 'in-progress';
		$controller->preload_url( $url );
		outcome_check( 'failed' === $query->row->status, $case . ' response cannot claim a warm cache' );
	}
	$query->missing_rows = true;
	$query->row->status = 'in-progress';
	$before = count( $fixture['requests'] );
	set_error_handler( static function ( $severity, $message ) { throw new ErrorException( $message, 0, $severity ); } );
	try {
		$controller->preload_url( $url );
		outcome_check( 'in-progress' === $query->row->status && count( $fixture['requests'] ) > $before, 'a missing discovery row causes no warning or invalid status update' );
	} finally {
		restore_error_handler();
		$query->missing_rows = false;
	}
	$fixture['status'] = 200; $fixture['publish'] = [ $desktop ]; $fixture['request_filter'] = true;
	$controller->preload_url( $url );
	outcome_check( 'failed' === $query->row->status, 'desktop publication alone does not complete required mobile coverage' );
	$last_request = end( $fixture['requests'] );
	outcome_check( true === $last_request[1]['blocking'] && 0 === $last_request[1]['redirection'] && 20 === $last_request[1]['timeout'], 'filter cannot turn verified publication into unbounded or fire-and-forget traffic' );
	$fixture['publish'] = [ $desktop, $mobile ];
	$controller->preload_url( $url );
	outcome_check( 'completed' === $query->row->status, 'actual desktop and mobile publication completes the warm-up' );
	$fixture['paused'] = true; $query->row->status = 'in-progress'; $before = count( $fixture['requests'] );
	$controller->preload_url( $url );
	outcome_check( 'in-progress' === $query->row->status && $before === count( $fixture['requests'] ), 'paused work preserves state and makes no request' );
	$fixture['paused'] = false;
	$fixture['actions'] = [ [ 'hook' => 'noobrocket_preload_job_preload_url', 'group' => 'noobrocket-preload', 'args' => [ $url ], 'status' => 'in-progress' ] ];
	outcome_check( $queue->has_remaining_tasks(), 'a running-only native action prevents premature completion' );
	outcome_check( 1 === $queue->get_active_preload_count() && 2 === count( $fixture['count_queries'] ), 'active count uses two native count operations and includes running work' );
	$query->stuck = [ $query->row ];
	$controller->process_pending_jobs();
	outcome_check( 'in-progress' === $query->row->status && $query->pending_batch <= 44, 'running URL stays alive and consumes staging capacity' );
	$fixture['actions'][0]['status'] = 'complete';
	outcome_check( ! $queue->has_remaining_tasks() && 0 === $queue->get_active_preload_count(), 'completed actions neither block finish nor consume capacity' );
	$controller->process_pending_jobs();
	outcome_check( 'failed' === $query->row->status, 'a genuinely stuck row without an active action fails' );

	$subscriber_class = new ReflectionClass( NoobRocket\Engine\Preload\Subscriber::class );
	$subscriber = $subscriber_class->newInstanceWithoutConstructor();
	foreach ( [ 'options' => $options, 'queue' => $queue, 'query' => $query ] as $name => $value ) {
		$property = $subscriber_class->getProperty( $name ); $property->setAccessible( true ); $property->setValue( $subscriber, $value );
	}
	outcome_check( $subscriber->refresh_expired_cache( false, $url ) && 'pending' === $query->row->status && 1 === count( $fixture['actions'] ), 'refresh enters existing pending storage without direct scheduler fan-out' );
	$query->row->status = 'in-progress';
	$subscriber->update_cache_row();
	outcome_check( 'in-progress' === $query->row->status, 'optimization/discovery hook cannot prematurely complete a warm-up' );
	$subscriber->cache_file_published( $desktop );
	outcome_check( 'completed' === $query->row->status, 'successful outer publication owns normal cache completion' );
	$query->row->url = 'https://example.test/blog';
	$query->row->status = 'pending';
	$query->make_status_complete( 'https://example.test/blog/?ignored=1' );
	outcome_check( 'completed' === $query->row->status, 'homepage completion uses the same query and trailing-slash normalization as stored rows' );
	$query->row->url = $url;
	foreach ( [ 'empty' => '', 'stale' => 'old mobile HTML' ] as $case => $contents ) {
		file_put_contents( $mobile, $contents );
		if ( 'stale' === $case ) {
			touch( $mobile, time() - 90 );
		}
		$query->row->status = 'pending';
		$subscriber->cache_file_published( $desktop );
		outcome_check( 'pending' === $query->row->status, $case . ' mobile companion cannot complete desktop publication' );
	}
	file_put_contents( $mobile, 'fresh mobile HTML' );
	touch( $mobile, time() );
	$subscriber->cache_file_published( $desktop );
	outcome_check( 'completed' === $query->row->status, 'fresh complete desktop and mobile publication completes the row' );
	$options->set( 'cache_webp', 1 );
	$options->set( 'cache_stale_refresh', 0 );
	$webp_desktop = dirname( $desktop ) . '/index-https-webp.html';
	file_put_contents( $webp_desktop, '' );
	outcome_check( ! $controller->is_already_cached( $url ), 'an empty legacy WebP file cannot claim published cache bytes' );
	file_put_contents( $webp_desktop, 'complete legacy WebP HTML' );
	outcome_check( $controller->is_already_cached( $url ), 'a complete legacy WebP variant remains a cache hit' );
	unlink( $webp_desktop );
	touch( dirname( $desktop ) . '/.no-webp' );
	outcome_check( $controller->is_already_cached( $url ), 'legacy no-WebP marker preserves the complete plain fallback' );
	file_put_contents( $desktop, '' );
	outcome_check( ! $controller->is_already_cached( $url ), 'legacy no-WebP marker cannot certify an empty plain fallback' );
	file_put_contents( $desktop, 'fresh desktop HTML' );
	$options->set( 'cache_webp', 0 );
	$options->set( 'cache_stale_refresh', 1 );
	$nested_directory = $temporary . '/example.test/blog/index-news/article';
	mkdir( $nested_directory, 0777, true );
	$nested_desktop = $nested_directory . '/index-https.html';
	file_put_contents( $nested_desktop, 'fresh desktop HTML' );
	file_put_contents( $nested_directory . '/index-mobile-https.html', 'fresh mobile HTML' );
	$query->row->url = 'https://example.test/blog/index-news/article';
	$query->row->status = 'pending';
	$GLOBALS['wp']->request = 'index-news/article';
	$subscriber->cache_file_published( $nested_desktop );
	outcome_check( 'completed' === $query->row->status, 'mobile companion selection never changes an index-prefixed parent directory' );
	$GLOBALS['wp']->request = 'article';
	$query->row->url = $url;

	$discovery = new DiscoveryFixture();
	foreach ( [ '/blog/article', 'article', '//example.test/blog/article', $url, 'https://example.test:443/blog/article' ] as $page ) {
		outcome_check( '' !== $discovery->resolve( $page ), 'selected site resolves ' . $page );
	}
	foreach ( [ 'https://example.test.evil/blog/article', 'https://evil.test/https://example.test/blog/article', 'https://user@example.test/blog/article', '/blog-other/article', '/other/article', '/blog/../private', '/blog/%2e%2e/private', 'https://example.test:444/blog/article', 'http://example.test/blog/article', '/blog/article#fragment' ] as $page ) {
		outcome_check( '' === $discovery->resolve( $page ), 'automatic discovery rejects ' . $page );
	}
	echo 'PASS: ', $checks, ' preload outcome and discovery assertions.', PHP_EOL;
} finally {
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $temporary, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $iterator as $item ) { $item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() ); }
	rmdir( $temporary );
}
