<?php
/** Isolated current-site scope, resumable cleanup and explicit-action contracts. */
declare(strict_types=1);
define( 'DB_NAME', 'shared_fixture' );
define( 'NOOBROCKET_SLUG', 'noobrocket_settings' );
define( 'HOUR_IN_SECONDS', 3600 );
$checks = 0;
$options = [];
$transients = [];
$multisite = false;
$authorized = true;
$deleted = [];
$fail_id = 73;
$deletion_budget = PHP_INT_MAX;
$calls = 0;
$cancelled = false;
$network_options = [];
$cleared_crons = [];
$cleared_site_transients = [];
$after_deletion = null;
$cached_posts = $cached_comments = [];
function database_check( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); } ++$GLOBALS['checks']; }
function is_multisite() { return $GLOBALS['multisite']; }
function get_current_blog_id() { return 2; }
function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
function add_option( $key, $value ) { if ( isset( $GLOBALS['options'][ $key ] ) ) { return false; } $GLOBALS['options'][ $key ] = $value; return true; }
function update_option( $key, $value ) { $GLOBALS['options'][ $key ] = $value; return true; }
function delete_option( $key ) { unset( $GLOBALS['options'][ $key ] ); return true; }
function set_transient( $key, $value, $expires = 0 ) { $GLOBALS['transients'][ $key ] = $value; return true; }
function get_transient( $key ) { return $GLOBALS['transients'][ $key ] ?? false; }
function delete_transient( $key ) { $exists = isset( $GLOBALS['transients'][ $key ] ); unset( $GLOBALS['transients'][ $key ] ); return $exists; }
function wp_cache_delete() {}
function get_current_network_id() { return 1; }
function delete_site_option( $key ) { unset( $GLOBALS['network_options'][ $key ] ); return true; }
function delete_site_transient( $key ) { $GLOBALS['cleared_site_transients'][] = $key; return true; }
function wp_clear_scheduled_hook( $key ) { $GLOBALS['cleared_crons'][] = $key; }
function __( $value ) { return $value; }
function esc_html__( $value ) { return $value; }
function current_user_can() { return $GLOBALS['authorized']; }
function wp_unslash( $value ) { return $value; }
function wp_die( $message, $title = '', $args = [] ) { throw new RuntimeException( $message, $args['response'] ?? 0 ); }
function check_admin_referer( $action ) { if ( 'noobrocket_database_cleanup' !== $action ) { throw new RuntimeException( 'Wrong cleanup nonce owner.' ); } }
function admin_url( $path ) { return 'https://site.test/wp-admin/' . $path; }
function wp_safe_redirect( $url ) { throw new DatabaseRedirect( $url ); }
function wp_delete_post_revision( $id ) {
	++$GLOBALS['calls'];
	if ( $id === $GLOBALS['fail_id'] ) { return false; }
	$GLOBALS['deleted'][] = $id;
	unset( $GLOBALS['wpdb']->ids[ $id ] );
	if ( is_callable( $GLOBALS['after_deletion'] ) ) { ( $GLOBALS['after_deletion'] )( $id ); }
	return new WP_Post();
}
function wp_delete_post( $id ) { return wp_delete_post_revision( $id ); }
function wp_delete_comment( $id ) { return (bool) wp_delete_post_revision( $id ); }
function get_post_status( $id ) { return $GLOBALS['cached_posts'][ $id ] ?? $GLOBALS['wpdb']->ids[ $id ]['post_status'] ?? false; }
function get_comment( $id ) { return isset( $GLOBALS['cached_comments'][ $id ] ) ? (object) [ 'comment_approved' => $GLOBALS['cached_comments'][ $id ] ] : null; }
class WP_Post {}
class DatabaseRedirect extends RuntimeException {}
class NoobRocket_WP_Background_Process {
	protected $action = '';
	public $data = [];
	public $saved = false;
	public $dispatched = false;
	public $busy = false;
	public function __construct() {}
	protected function is_queue_empty() { return ! $this->busy && ! $this->saved; }
	protected function is_process_running() { return $this->busy; }
	protected function time_exceeded() { return $GLOBALS['calls'] >= $GLOBALS['deletion_budget']; }
	protected function memory_exceeded() { return false; }
	protected function is_process_cancelled() { return $GLOBALS['cancelled']; }
	public function push_to_queue( $item ) { $this->data[] = $item; return $this; }
	public function save() { $this->saved = true; return $this; }
	public function dispatch() { $this->dispatched = true; return true; }
	protected function complete() {}
}
class CleanupDatabase {
	public $posts = 'wp_2_posts';
	public $comments = 'wp_2_comments';
	public $options = 'wp_2_options';
	public $sitemeta = 'network_meta';
	public $last_error = '';
	public $ids = [];
	public $queries = [];
	public $table_error = false;
	public $read_failure_id = 0;
	public function tables() { return [ 'posts' => $this->posts, 'comments' => $this->comments, 'options' => $this->options ]; }
	public function esc_like( $value ) { return addcslashes( $value, '_%\\' ); }
	public function prepare( $sql, ...$args ) {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) { $args = $args[0]; }
		foreach ( $args as $arg ) { $sql = preg_replace_callback( '/%[sd]/', static function ( $match ) use ( $arg ) { return '%d' === $match[0] ? (string) (int) $arg : "'" . str_replace( "'", "''", $arg ) . "'"; }, $sql, 1 ); }
		return $sql;
	}
	public function get_col( $sql ) {
		$this->queries[] = $sql;
		if ( false !== strpos( $sql, 'SELECT meta_key' ) || false !== strpos( $sql, 'SELECT option_name' ) ) {
			$prefix = 'noobrocket_database_optimization' . ( $GLOBALS['multisite'] ? '_' . get_current_blog_id() : '' ) . '_batch_';
			return array_values( array_filter( array_keys( $GLOBALS['multisite'] ? $GLOBALS['network_options'] : $GLOBALS['options'] ), static function ( $key ) use ( $prefix ) { return 0 === strpos( $key, $prefix ); } ) );
		}
		if ( false !== strpos( $sql, 'information_schema' ) ) { return [ 'wp_2_posts', 'foreign_application_table', 'wp_3_posts' ]; }
		if ( preg_match( '/SELECT (?:ID|comment_ID).*(?:ID|comment_ID) > (\d+).*LIMIT (\d+)/', $sql, $matches ) ) {
			return array_slice( array_values( array_filter( array_keys( $this->ids ), static function ( $id ) use ( $matches ) { return $id > (int) $matches[1]; } ) ), 0, (int) $matches[2] );
		}
		return [];
	}
	public function get_var( $sql ) {
		$this->queries[] = $sql;
		$this->last_error = '';
		if ( preg_match( '/SELECT (post_status|comment_approved) FROM (\w+) WHERE (?:ID|comment_ID) = (\d+)/', $sql, $matches ) ) {
			database_check( $matches[2] === ( 'post_status' === $matches[1] ? $this->posts : $this->comments ), 'eligibility reads only the current site table' );
			if ( (int) $matches[3] === $this->read_failure_id ) { $this->last_error = 'Injected eligibility read failure'; return null; }
			return $this->ids[ (int) $matches[3] ][ $matches[1] ] ?? null;
		}
		return 0;
	}
	public function get_results( $sql ) { $this->queries[] = $sql; return [ (object) [ 'Msg_type' => $this->table_error ? 'error' : 'status', 'Msg_text' => $this->table_error ? 'failed' : 'OK' ] ]; }
	public function delete( $table, $where ) { unset( $GLOBALS['options'][ $where['option_name'] ] ); return 1; }
}
$wpdb = new CleanupDatabase();
$root = dirname( __DIR__ ) . '/noobrocket/inc/';
require $root . 'classes/event-management/subscriber-interface.php';
require $root . 'classes/admin/class-options-data.php';
require $root . 'Engine/Admin/Database/OptimizationProcess.php';
require $root . 'Engine/Admin/Database/Optimization.php';
require $root . 'Engine/Admin/Database/Subscriber.php';
$process = new NoobRocket\Engine\Admin\Database\OptimizationProcess();
$optimization = new NoobRocket\Engine\Admin\Database\Optimization( $process );
$subscriber = new NoobRocket\Engine\Admin\Database\Subscriber( $optimization, new NoobRocket\Admin\Options_Data( [] ) );
$task = new ReflectionMethod( $process, 'task' );
$complete = new ReflectionMethod( $process, 'complete' );
$task->setAccessible( true );
$complete->setAccessible( true );
database_check( [ 'wp_2_posts' ] === $optimization::optimizable_tables(), 'foreign and sibling-site tables are excluded even from malformed query results' );
database_check( 1 === $optimization->count_cleanup_items( 'database_optimize_tables' ), 'displayed table count shares the operation enumeration' );
$task->invoke( $process, 'database_optimize_tables' );
database_check( in_array( 'OPTIMIZE TABLE `wp_2_posts`', $wpdb->queries, true ) && ! in_array( 'OPTIMIZE TABLE `foreign_application_table`', $wpdb->queries, true ), 'only owned native table reaches quoted maintenance command' );
$wpdb->table_error = true;
$task->invoke( $process, 'database_optimize_tables' );
$complete->invoke( $process );
database_check( 'failed' === $transients['noobrocket_database_optimization_process_complete']['status'], 'server OPTIMIZE error rows cannot be reported as success' );
$wpdb->table_error = false;
$multisite = true;
$condition = $optimization::transient_condition();
database_check( false === strpos( $condition, "option_name LIKE '_site" ), 'subsite cleanup never selects network transients' );
database_check( false !== strpos( $condition, 'option_name NOT LIKE' ) && false !== strpos( $condition, '_transient\\_timeout' ), 'timeout companion rows are excluded from displayed/deletion scope' );
database_check( false !== strpos( $condition, 'noobrocket\\_database\\_optimization' ), 'cleanup leases/results remain outside their own all-transients deletion' );
$subsite = new NoobRocket\Engine\Admin\Database\OptimizationProcess();
$action = new ReflectionProperty( $subsite, 'action' );
$action->setAccessible( true );
database_check( 'database_optimization_2' === $action->getValue( $subsite ), 'network queue identifiers retain current-site ownership' );
$multisite = false;
$wpdb->ids = array_fill_keys( range( 1, 137 ), true );
$deletion_budget = 10;
$state = $task->invoke( $process, 'database_revisions' );
database_check( is_array( $state ) && 10 === $state['cursor'] && 10 === count( $deleted ), 'native time budget interrupts within a category and persists cursor' );
$deletion_budget = PHP_INT_MAX;
$requests = 1;
while ( false !== $state ) {
	$next = new NoobRocket\Engine\Admin\Database\OptimizationProcess();
	$state = $task->invoke( $next, $state );
	++$requests;
	if ( $requests > 10 ) { throw new RuntimeException( 'Unbounded cleanup resume.' ); }
}
database_check( count( $deleted ) === 136 && count( array_unique( $deleted ) ) === 136 && [ 73 ] === array_keys( $wpdb->ids ), 'resumed keyset never skips or repeats eligible successful deletions; failed object retained' );
database_check( $requests >= 4 && 137 === $calls, 'large dataset spans bounded calls and every native deletion hook runs once' );
$complete->invoke( $next );
$results = $transients['noobrocket_database_optimization_process_complete'];
database_check( 136 === $results['counts']['database_revisions'] && 1 === $results['failed']['database_revisions'] && 'failed' === $results['status'], 'counts and failure status survive process instances' );
database_check( ! isset( $options['noobrocket_database_optimization_results'] ), 'completion removes its owned cumulative state' );
// A previous object's deletion hook can expose a concurrent save/restore after this batch was selected.
foreach ( [
	[ 'database_auto_drafts', 'post_status', 'auto-draft', 'draft' ],
	[ 'database_trashed_posts', 'post_status', 'trash', 'publish' ],
	[ 'database_spam_comments', 'comment_approved', 'spam', '1' ],
	[ 'database_trashed_comments', 'comment_approved', 'trash', '0' ],
	[ 'database_trashed_comments', 'comment_approved', 'post-trashed', '1' ],
] as [ $category, $column, $selected, $restored ] ) {
	$wpdb->ids = array_fill_keys( [ 1, 2, 3 ], [ $column => $selected ] );
	$cached_posts = $cached_comments = [];
	if ( 'post_status' === $column ) { $cached_posts[2] = $selected; } else { $cached_comments[2] = $selected; }
	$deleted = [];
	$calls = 0;
	$fail_id = 3;
	$after_deletion = static function ( $id ) use ( $column, $restored ) { if ( 1 === $id ) { $GLOBALS['wpdb']->ids[2][ $column ] = $restored; } };
	$task->invoke( $process, $category );
	database_check( [ 1 ] === $deleted && $restored === $wpdb->ids[2][ $column ] && isset( $wpdb->ids[3] ), $category . ': restored object and deletion veto survive the selected batch' );
	$complete->invoke( $process );
	$receipt = $transients['noobrocket_database_optimization_process_complete'];
	database_check( 1 === $receipt['counts'][ $category ] && 1 === $receipt['failed'][ $category ], $category . ': changed category is neither a deletion nor a failure' );
}
$after_deletion = null;
$cached_posts = $cached_comments = [];
$wpdb->ids = array_fill_keys( [ 1, 2, 3 ], [ 'post_status' => 'trash' ] );
$wpdb->read_failure_id = 2;
$fail_id = -1;
$deleted = [];
$calls = 0;
$task->invoke( $process, 'database_trashed_posts' );
$complete->invoke( $process );
$receipt = $transients['noobrocket_database_optimization_process_complete'];
database_check( [ 1, 3 ] === $deleted && isset( $wpdb->ids[2] ) && 2 === $receipt['counts']['database_trashed_posts'] && 1 === $receipt['failed']['database_trashed_posts'], 'failed eligibility read retains its row, reports failure and does not prevent later rows' );
$wpdb->read_failure_id = 0;
$wpdb->ids = array_fill_keys( range( 1, 51 ), [ 'post_status' => 'trash' ] );
$deleted = [];
$calls = 0;
$after_deletion = static function ( $id ) { if ( 1 === $id ) { $GLOBALS['wpdb']->ids[2]['post_status'] = 'draft'; unset( $GLOBALS['wpdb']->ids[3] ); } };
$state = $task->invoke( $process, 'database_trashed_posts' );
database_check( is_array( $state ) && 50 === $state['cursor'] && 48 === $state['count'] && 0 === $state['failed'], 'changed and missing objects advance a full batch cursor without false counts/failures' );
database_check( false === $task->invoke( $next, $state ) && isset( $wpdb->ids[2] ) && ! isset( $wpdb->ids[51] ) && 49 === count( $deleted ), 'next process resumes beyond skipped objects and reaches the final eligible row' );
$after_deletion = null;
$fail_id = 73;
$cancelled = true;
$complete->invoke( $next );
database_check( 'cancelled' === $transients['noobrocket_database_optimization_process_complete']['status'], 'native cancellation is distinguishable from successful completion' );
$transients['noobrocket_database_optimization_process_complete'] = [ 'status' => 'cancelled', 'counts' => [ 'database_trashed_posts' => 8 ], 'failed' => [ 'database_trashed_posts' => 1 ] ];
$options['noobrocket_database_optimization_results'] = [ 'counts' => [ 'database_revisions' => 7 ], 'failed' => [ 'database_revisions' => 0 ] ];
$complete->invoke( $next );
database_check( [ 'database_trashed_posts' => 8, 'database_revisions' => 7 ] === $transients['noobrocket_database_optimization_process_complete']['counts'], 'active worker completion retains earlier category totals from cancelled receipt' );
$cancelled = false;
$settings = [ 'database_revisions' => 1, 'submit_optimize' => 1, 'unrelated' => 'private pending change' ];
database_check( ! isset( $subscriber->save_optimize( $settings )['submit_optimize'] ) && ! $process->dispatched, 'settings validation strips legacy intent without launching cleanup' );
$_SERVER['REQUEST_METHOD'] = 'POST';
foreach ( [ [], [ 'confirmed' => '1', 'noobrocket_cleanup' => [ 'foreign_category' ] ], [ 'confirmed' => '1', 'noobrocket_cleanup' => [ [ 'malformed' ] ] ] ] as $post ) {
	$_POST = $post;
	try { $subscriber->handle_cleanup(); database_check( false, 'invalid cleanup must fail' ); }
	catch ( RuntimeException $error ) { database_check( 400 === $error->getCode() && ! $process->dispatched, 'invalid or unreviewed scope rejected without settings/deletion work' ); }
}
$authorized = false;
$_POST = [ 'confirmed' => '1', 'noobrocket_cleanup' => [ 'database_revisions' ], 'noobrocket_settings' => [ 'unrelated' => 'must not save' ] ];
try { $subscriber->handle_cleanup(); database_check( false, 'unauthorized action must fail' ); }
catch ( RuntimeException $error ) { database_check( 403 === $error->getCode(), 'explicit cleanup requires native capability' ); }
$authorized = true;
$options['noobrocket_settings'] = [ 'unrelated' => 'original' ];
try { $subscriber->handle_cleanup(); database_check( false, 'successful cleanup redirects' ); }
catch ( DatabaseRedirect $redirect ) { database_check( false !== strpos( $redirect->getMessage(), '#database' ) && $process->dispatched && $process->data[0]['type'] === 'database_revisions', 'confirmed same-state cleanup has its own endpoint and selected category' ); }
database_check( [ 'unrelated' => 'original' ] === $options['noobrocket_settings'], 'immediate cleanup cannot submit/save unrelated pending settings' );
database_check( ! isset( $options['noobrocket_database_optimization_start'] ), 'start lock released after dispatch' );
try { $subscriber->handle_cleanup(); database_check( false, 'overlapping cleanup must fail' ); }
catch ( RuntimeException $error ) { database_check( 409 === $error->getCode() && 1 === count( $process->data ), 'overlapping action cannot cancel or replace a reviewed scope' ); }
$multisite = true;
$network_options = [ 'noobrocket_database_optimization_2_batch_a' => [ 1 ], 'noobrocket_database_optimization_2_batch_b' => [ 2 ], 'noobrocket_database_optimization_20_batch_a' => [ 3 ], 'foreign_batch_a' => [ 4 ] ];
$optimization::clear_site_queue();
database_check( [ 'noobrocket_database_optimization_20_batch_a', 'foreign_batch_a' ] === array_keys( $network_options ), 'native lifecycle removes every owned network batch and retains neighboring/foreign queues' );
database_check( in_array( 'noobrocket_database_optimization_2_cron', $cleared_crons, true ) && in_array( 'noobrocket_database_optimization_2_process_lock', $cleared_site_transients, true ), 'dynamic current-site cron and native lock are removed exactly' );
$multisite = false;
$options['noobrocket_database_optimization_batch_a'] = [ 1 ];
$options['noobrocket_database_optimization_batch_b'] = [ 2 ];
$options['foreign_batch_a'] = [ 3 ];
$optimization::clear_site_queue();
database_check( ! isset( $options['noobrocket_database_optimization_batch_a'], $options['noobrocket_database_optimization_batch_b'] ) && isset( $options['foreign_batch_a'] ), 'single-site lifecycle deletes exact batches through native option deletion' );
echo 'PASS: database cleanup (', $checks, " assertions; isolated native ownership/resume/action)\n";
