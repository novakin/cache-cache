<?php
/** Standalone uninstall isolation checks; mocked WordPress primitives, no site writes. */
declare(strict_types=1);

$fixture = [
	'multisite' => true,
	'blog' => 89,
	'stack' => [],
	'sites' => range( 1, 105 ),
	'site_queries' => [],
	'options' => [],
	'post_meta' => [],
	'user_meta' => [],
	'events' => [],
	'files' => [],
	'fail_blog' => null,
	'network_options' => [ 'noobrocket_database_optimization_batch_legacy' => [], 'foreign_batch_legacy' => [], 'noobrocket_database_optimization_999_batch_other' => [] ],
	'site_transients' => [ 'noobrocket_database_optimization_process_lock' => true, 'foreign_process_lock' => true ],
];
function is_multisite() { return $GLOBALS['fixture']['multisite']; }
function get_current_blog_id() { return $GLOBALS['fixture']['blog']; }
function get_current_network_id() { return 1; }
function get_sites( $args ) {
	$GLOBALS['fixture']['site_queries'][] = $args;
	return array_slice( $GLOBALS['fixture']['sites'], 0, empty( $args['number'] ) ? null : $args['number'] );
}
function switch_to_blog( $id ) {
	$GLOBALS['fixture']['stack'][] = get_current_blog_id();
	$GLOBALS['fixture']['blog'] = (int) $id;
}
function restore_current_blog() { $GLOBALS['fixture']['blog'] = array_pop( $GLOBALS['fixture']['stack'] ); }
function trailingslashit( $path ) { return rtrim( $path, '/\\' ) . '/'; }
function delete_site_transient( $name ) { unset( $GLOBALS['fixture']['site_transients'][ $name ] ); }
function delete_site_option( $name ) { unset( $GLOBALS['fixture']['network_options'][ $name ] ); }
function delete_transient( $name ) {}
function delete_option( $name ) {
	if ( get_current_blog_id() === $GLOBALS['fixture']['fail_blog'] ) {
		throw new RuntimeException( 'Injected option deletion failure' );
	}
	$GLOBALS['fixture']['options'][ get_current_blog_id() ][] = $name;
}
function delete_metadata( $type, $id, $key, $value, $all ) {
	$GLOBALS['fixture']['user_meta'][ get_current_blog_id() ][] = $key;
}
function delete_post_meta_by_key( $key ) { $GLOBALS['fixture']['post_meta'][ get_current_blog_id() ][] = $key; }
function wp_next_scheduled( $event ) { return 1; }
function wp_clear_scheduled_hook( $event ) { $GLOBALS['fixture']['events'][ get_current_blog_id() ][] = $event; }
function wp_delete_file( $path ) { $GLOBALS['fixture']['files'][] = $path; }
class NetworkFixtureDatabase {
	public $options = 'fixture_options';
	public $sitemeta = 'fixture_sitemeta';
	public function esc_like( $value ) { return addcslashes( $value, '_%\\' ); }
	public function prepare( $query, ...$args ) {
		foreach ( $args as $arg ) { $query = preg_replace_callback( '/%[sd]/', static function ( $match ) use ( $arg ) { return '%d' === $match[0] ? (string) (int) $arg : "'" . str_replace( "'", "''", $arg ) . "'"; }, $query, 1 ); }
		return $query;
	}
	public function get_col( $query ) {
		if ( ! preg_match( "/LIKE '([^']+)'/", $query, $match ) ) { return []; }
		$prefix = str_replace( '\\', '', rtrim( $match[1], '%' ) );
		return array_values( array_filter( array_keys( $GLOBALS['fixture']['network_options'] ), static function ( $key ) use ( $prefix ) { return 0 === strpos( $key, $prefix ); } ) );
	}
	public function query( $query ) { return true; }
}
class NetworkFixtureTable {
	public $deleted = [];
	public $fail_blog;
	public function exists() { return ! in_array( get_current_blog_id(), $this->deleted, true ); }
	public function uninstall() {
		if ( get_current_blog_id() === $this->fail_blog ) { throw new RuntimeException( 'Injected table deletion failure' ); }
		$this->deleted[] = get_current_blog_id();
	}
}
$wpdb = new NetworkFixtureDatabase();
foreach ( $fixture['sites'] as $id ) {
	$fixture['network_options'][ 'noobrocket_database_optimization_' . $id . '_batch_first' ] = [ 'database_revisions' ];
	$fixture['network_options'][ 'noobrocket_database_optimization_' . $id . '_batch_second' ] = [ 'database_spam_comments' ];
	$fixture['site_transients'][ 'noobrocket_database_optimization_' . $id . '_process_lock' ] = true;
}
require dirname( __DIR__ ) . '/noobrocket/inc/Engine/NoobRocketUninstall.php';
$checks = 0;
function network_uninstall_check( bool $condition, string $message ): void {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
	$GLOBALS['checks']++;
}
function network_uninstaller( array $tables = [] ): NoobRocketUninstall {
	// These paths need not exist; wp_delete_file is a recording stub.
	return new NoobRocketUninstall( sys_get_temp_dir() . '/noobrocket-uninstall-fixture-missing/cache', sys_get_temp_dir() . '/noobrocket-uninstall-fixture-missing/config', $tables );
}
$table = new NetworkFixtureTable();
network_uninstaller( [ $table ] )->uninstall();
network_uninstall_check( 105 === count( $fixture['options'] ) && 105 === count( $table->deleted ), 'network uninstall reaches every blog, including IDs beyond the WordPress default limit' );
network_uninstall_check( 89 === get_current_blog_id() && [] === $fixture['stack'], 'network uninstall restores the original blog' );
network_uninstall_check( [ 'foreign_batch_legacy', 'noobrocket_database_optimization_999_batch_other' ] === array_keys( $fixture['network_options'] ), 'network uninstall clears every owned site batch and legacy batch; foreign and out-of-scope site queues remain' );
network_uninstall_check( [ 'foreign_process_lock' ] === array_keys( $fixture['site_transients'] ), 'native owned process locks cleared without a shared cache flush' );
network_uninstall_check( 8 === count( $fixture['files'] ) && count( $fixture['files'] ) === count( array_unique( $fixture['files'] ) ), 'shared cache and configuration cleanup runs once' );
foreach ( $fixture['sites'] as $id ) {
	network_uninstall_check( in_array( 'noobrocket_asset_rules', $fixture['options'][ $id ], true ) && in_array( 'noobrocket_settings_snapshots', $fixture['options'][ $id ], true ) && in_array( 'noobrocket_image_generation', $fixture['options'][ $id ], true ), 'site ' . $id . ' removes module options' );
	network_uninstall_check( in_array( 'noobrocket_generate_images', $fixture['events'][ $id ], true ), 'site ' . $id . ' removes image generation events' );
	network_uninstall_check( in_array( 'noobrocket_database_optimization_' . $id . '_cron', $fixture['events'][ $id ], true ), 'site ' . $id . ' removes only its dynamic database process event' );
	network_uninstall_check( in_array( 'noobrocket_database_optimization_results', $fixture['options'][ $id ], true ) && in_array( 'noobrocket_database_optimization_start', $fixture['options'][ $id ], true ), 'site ' . $id . ' removes cleanup progress/start options' );
	network_uninstall_check( in_array( 'noobrocket_asset_draft_' . $id, $fixture['user_meta'][ $id ], true ) && in_array( 'noobrocket_asset_inventory_' . $id, $fixture['user_meta'][ $id ], true ), 'site ' . $id . ' removes its network-account draft and inventory' );
	network_uninstall_check( in_array( 'noobrocket_asset_entry_' . $id, $fixture['user_meta'][ $id ], true ), 'site ' . $id . ' removes only its unfinished asset entry' );
	network_uninstall_check( in_array( '_noobrocket_image_pending', $fixture['post_meta'][ $id ], true ) && ! in_array( '_noobrocket_image_files', $fixture['post_meta'][ $id ], true ), 'site ' . $id . ' clears the queue while retaining ownership metadata for derivative references' );
	network_uninstall_check( ! in_array( 'wp_rocket_settings', $fixture['options'][ $id ], true ) && ! in_array( 'rocket_generate_images', $fixture['events'][ $id ], true ), 'site ' . $id . ' leaves foreign options and events alone' );
}
foreach ( $fixture['site_queries'] as $query ) {
	network_uninstall_check( 'ids' === $query['fields'] && 0 === $query['number'], 'network enumeration has no implicit blog limit' );
}
$fixture['fail_blog'] = 37;
try {
	network_uninstaller()->uninstall();
	throw new LogicException( 'Expected injected data failure' );
} catch ( RuntimeException $error ) {
	network_uninstall_check( 'Injected option deletion failure' === $error->getMessage() && 89 === get_current_blog_id() && [] === $fixture['stack'], 'failed per-blog data cleanup still restores original blog context' );
}
$fixture['fail_blog'] = null;
$table = new NetworkFixtureTable();
$table->fail_blog = 37;
try {
	network_uninstaller( [ $table ] )->uninstall();
	throw new LogicException( 'Expected injected table failure' );
} catch ( RuntimeException $error ) {
	network_uninstall_check( 'Injected table deletion failure' === $error->getMessage() && 89 === get_current_blog_id() && [] === $fixture['stack'], 'failed per-blog table cleanup still restores original blog context' );
}
$fixture['multisite'] = false;
$fixture['options'] = [];
$fixture['site_queries'] = [];
network_uninstaller()->uninstall();
network_uninstall_check( [ 89 ] === array_keys( $fixture['options'] ) && [] === $fixture['site_queries'], 'single-site uninstall removes only the current site without network enumeration' );
echo 'PASS: ', $checks, ' uninstall network assertions.', PHP_EOL;
