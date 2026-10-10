<?php
/** Actual post callbacks with modeled WordPress/dependency boundaries; no site bootstrap. */
declare(strict_types=1);

$checks = 0;
$permalinks = [];
$post_types = [];
$revision_checks = [];
$parent_url = 'https://revision-fixture.test/article/';
$deletion_enabled = true;
define( 'NOOBROCKET_SLUG', 'noobrocket_settings' );

function revision_check( $condition, string $message ): void {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
	++$GLOBALS['checks'];
}
function wp_is_post_revision( $id ) {
	$GLOBALS['revision_checks'][] = $id;
	// Core autosaves use the revision post type too.
	return $id >= 2000 ? 10 : false;
}
function get_post_type( $id ) { $GLOBALS['post_types'][] = $id; return $id >= 2000 ? 'revision' : ( 99 === $id ? 'attachment' : 'post' ); }
function get_permalink( $id ) {
	$GLOBALS['permalinks'][] = $id;
	return 98 === $id ? false : ( 10 === $id ? $GLOBALS['parent_url'] : 'https://revision-fixture.test/?p=' . $id );
}
function untrailingslashit( $url ) { return rtrim( $url, '/\\' ); }
function apply_filters( $hook, $value, ...$args ) { return $value; }
function noobrocket_get_constant( $name, $default = '' ) { return defined( $name ) ? constant( $name ) : $default; }
function noobrocket_apply_filter_and_deprecated( $hook, $args, ...$deprecated ) { return $GLOBALS['deletion_enabled']; }

class RevisionHintFactory {
	public $deleted = [];
	public $query_calls = 0;
	public $allowed = true;
	public function get_context() { return $this; }
	public function is_allowed() { return $this->allowed; }
	public function queries() { ++$this->query_calls; return $this; }
	public function delete_by_url( $url ) { $this->deleted[] = $url; }
}
class RevisionInvalidationProbe {
	public $deleted = [];
	public $invalidated = [];
	public $enabled = true;
	public function is_enabled() { return $this->enabled; }
	public function delete_url( $url ) { $this->deleted[] = $url; }
	public function delete_used_css( $url ) { $this->deleted[] = $url; }
	public function invalidate_urls( $urls ) { $this->invalidated[] = array_values( $urls ); }
}

$root = dirname( __DIR__ ) . '/noobrocket/inc/';
foreach ( [
	'classes/event-management/subscriber-interface.php', 'classes/admin/class-options-data.php',
	'Logger/LoggerAware.php', 'Logger/LoggerAwareInterface.php', 'Engine/Preload/Controller/CheckExcludedTrait.php',
	'Engine/Common/PerformanceHints/Admin/Controller.php', 'Engine/Common/PerformanceHints/Admin/Subscriber.php',
	'Engine/Preload/Subscriber.php', 'Engine/Optimization/LocalCSS/Subscriber.php', 'Engine/Optimization/RUCSS/Admin/Subscriber.php',
] as $file ) { require $root . $file; }

$factories = [];
foreach ( [ 'atf', 'lrc', 'fonts', 'preconnect' ] as $name ) { $factories[ $name ] = new RevisionHintFactory(); }
$controller = new NoobRocket\Engine\Common\PerformanceHints\Admin\Controller( array_values( $factories ) );
$preload_probe = new RevisionInvalidationProbe();
$css_probe = new RevisionInvalidationProbe();
$rucss_probe = new RevisionInvalidationProbe();
$settings_probe = new RevisionInvalidationProbe();
$create = static function ( $class, array $properties ) {
	$reflection = new ReflectionClass( $class );
	$instance = $reflection->newInstanceWithoutConstructor();
	foreach ( $properties as $name => $value ) {
		$property = $reflection->getProperty( $name );
		$property->setAccessible( true );
		$property->setValue( $instance, $value );
	}
	return $instance;
};
$hints = $create( NoobRocket\Engine\Common\PerformanceHints\Admin\Subscriber::class, [ 'controller' => $controller ] );
$preload = $create( NoobRocket\Engine\Preload\Subscriber::class, [
	'options' => new NoobRocket\Admin\Options_Data( [ 'manual_preload' => 1 ] ), 'clear_cache' => $preload_probe,
] );
$local_css = $create( NoobRocket\Engine\Optimization\LocalCSS\Subscriber::class, [ 'jobs' => $css_probe ] );
$rucss = $create( NoobRocket\Engine\Optimization\RUCSS\Admin\Subscriber::class, [ 'settings' => $settings_probe, 'used_css' => $rucss_probe ] );
$invoke = static function ( $subscriber, $hook, $id ) {
	$callback = $subscriber::get_subscribed_events()[ $hook ];
	$subscriber->{is_array( $callback ) ? $callback[0] : $callback}( $id );
};
$reset = static function () use ( $factories, $preload_probe, $css_probe, $rucss_probe ) {
	$GLOBALS['permalinks'] = [];
	$GLOBALS['post_types'] = [];
	$GLOBALS['revision_checks'] = [];
	foreach ( $factories as $factory ) { $factory->deleted = []; $factory->query_calls = 0; }
	foreach ( [ $preload_probe, $css_probe, $rucss_probe ] as $probe ) { $probe->deleted = []; $probe->invalidated = []; }
};
$old_urls = new ReflectionProperty( $local_css, 'old_urls' );
$old_urls->setAccessible( true );

// Repeat each registered hook path, including a modeled autosave (2021).
foreach ( range( 2001, 2021 ) as $id ) {
	foreach ( [ 'wp_trash_post', 'delete_post', 'clean_post_cache', 'wp_update_comment_count' ] as $hook ) {
		$invoke( $hints, $hook, $id );
		$invoke( $rucss, $hook, $id );
	}
	$invoke( $preload, 'delete_post', $id );
	foreach ( [ 'pre_post_update', 'post_updated', 'before_delete_post', 'wp_trash_post' ] as $hook ) { $invoke( $local_css, $hook, $id ); }
}
revision_check( [] === $permalinks && [] === $post_types, 'revision/autosave hooks return before permalink and post-type lookups' );
foreach ( $factories as $name => $factory ) {
	revision_check( 0 === $factory->query_calls && [] === $factory->deleted, $name . ': revisions invoke no query owner or delete' );
}
revision_check( [] === $preload_probe->deleted, 'revisions do not delete preload URLs' );
revision_check( [] === $css_probe->invalidated && [] === $old_urls->getValue( $local_css ), 'revisions neither capture old URLs nor invalidate local CSS' );
revision_check( [] === $rucss_probe->deleted, 'dormant RUCSS callback rejects revisions when explicitly invoked' );

$reset();
foreach ( [ 'wp_trash_post', 'delete_post', 'clean_post_cache', 'wp_update_comment_count' ] as $hook ) {
	$invoke( $hints, $hook, 10 );
	$invoke( $rucss, $hook, 10 );
}
foreach ( $factories as $name => $factory ) {
	revision_check( array_fill( 0, 4, untrailingslashit( $parent_url ) ) === $factory->deleted, $name . ': regular parent update/deletion/comment hooks still invalidate' );
}
revision_check( array_fill( 0, 4, untrailingslashit( $parent_url ) ) === $rucss_probe->deleted, 'enabled legacy RUCSS parent callbacks retain behavior' );
$invoke( $preload, 'delete_post', 10 );
revision_check( [ $parent_url ] === $preload_probe->deleted, 'regular parent deletion still removes its preload URL' );
$invoke( $local_css, 'pre_post_update', 10 );
$before = $parent_url;
$parent_url = 'https://revision-fixture.test/renamed/';
$invoke( $local_css, 'post_updated', 10 );
revision_check( [ [ $before, $parent_url ] ] === $css_probe->invalidated, 'parent slug update invalidates captured old and current local CSS URLs' );
revision_check( [] === $old_urls->getValue( $local_css ), 'parent update releases captured URL' );
$invoke( $local_css, 'before_delete_post', 10 );
$invoke( $local_css, 'wp_trash_post', 10 );
revision_check( [ $parent_url ] === $css_probe->invalidated[1] && [ $parent_url ] === $css_probe->invalidated[2], 'parent deletion/trash still invalidate current local CSS URL' );

$reset();
$controller->delete_post( 99 );
$rucss->delete_used_css_on_update_or_delete( 99 );
revision_check( [] === $permalinks, 'existing hint/RUCSS attachment guards stop before permalink work' );
foreach ( $factories as $factory ) { revision_check( 0 === $factory->query_calls, 'attachments invoke no hint query owner' ); }
$preload->delete_post_preload_cache( 99 );
$local_css->remember_post_url( 99 );
$local_css->invalidate_post( 99 );
revision_check( [ 'https://revision-fixture.test/?p=99' ] === $preload_probe->deleted && count( $css_probe->invalidated ) === 1, 'existing attachment behavior in preload/local CSS remains unchanged' );

$reset();
foreach ( $factories as $factory ) { $factory->allowed = false; }
$settings_probe->enabled = false;
$disabled_preload = $create( NoobRocket\Engine\Preload\Subscriber::class, [
	'options' => new NoobRocket\Admin\Options_Data( [ 'manual_preload' => 0 ] ), 'clear_cache' => $preload_probe,
] );
foreach ( [ 10, 2001 ] as $id ) {
	$controller->delete_post( $id );
	$rucss->delete_used_css_on_update_or_delete( $id );
	$disabled_preload->delete_post_preload_cache( $id );
}
revision_check( [] === $revision_checks && [] === $permalinks, 'disabled contexts/settings retain their earlier no-work exit' );
foreach ( $factories as $factory ) { revision_check( 0 === $factory->query_calls, 'disabled hints invoke no query owner' ); $factory->allowed = true; }
revision_check( [] === $preload_probe->deleted && [] === $rucss_probe->deleted, 'disabled preload/RUCSS retain rows' );
$settings_probe->enabled = true;
$deletion_enabled = false;
$rucss->delete_used_css_on_update_or_delete( 10 );
revision_check( [] === $revision_checks && [] === $rucss_probe->deleted, 'RUCSS deletion filter retains its earlier exit' );
$deletion_enabled = true;

$reset();
$controller->delete_post( 98 );
$preload->delete_post_preload_cache( 98 );
$rucss->delete_used_css_on_update_or_delete( 98 );
foreach ( $factories as $factory ) { revision_check( 0 === $factory->query_calls, 'false permalink invokes no hint query owner' ); }
revision_check( [] === $preload_probe->deleted && [] === $rucss_probe->deleted, 'false permalink retains preload/RUCSS rows' );
echo 'PASS: ', $checks, ' modeled revision/autosave and parent-invalidation assertions.', PHP_EOL;
