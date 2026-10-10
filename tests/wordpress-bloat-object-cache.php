<?php
/** Standalone frontend bloat and existing object-cache contract checks; no site writes. */
declare(strict_types=1);

$state = [ 'admin' => false, 'feed' => false, 'embed' => false, 'preview' => false, 'bypass' => false, 'logged_in' => false, 'block_theme' => false, 'block_widget' => false ];
$styles = (object) [ 'queue' => [], 'registered' => [] ];
$scripts = (object) [ 'queue' => [], 'registered' => [] ];
$removed_actions = [];
$dequeued = [];
$cache_calls = [];
$cache_values = [];
$cache_state = [ 'external' => false, 'set' => true, 'get' => true, 'delete' => true, 'throw_get' => false ];
function apply_filters( $hook, $value ) { return $value; }
function is_admin() { return $GLOBALS['state']['admin']; }
function is_feed() { return $GLOBALS['state']['feed']; }
function is_embed() { return $GLOBALS['state']['embed']; }
function is_preview() { return $GLOBALS['state']['preview']; }
function noobrocket_bypass() { return $GLOBALS['state']['bypass']; }
function is_user_logged_in() { return $GLOBALS['state']['logged_in']; }
function wp_is_block_theme() { return $GLOBALS['state']['block_theme']; }
function is_active_widget() { return $GLOBALS['state']['block_widget']; }
function has_blocks( $content ) { return false !== strpos( $content, '<!-- wp:' ); }
function wp_styles() { return $GLOBALS['styles']; }
function wp_scripts() { return $GLOBALS['scripts']; }
function remove_action( $hook, $callback ) { $GLOBALS['removed_actions'][] = [ $hook, $callback ]; }
function wp_dequeue_style( $handle ) { $GLOBALS['dequeued'][] = $handle; }
function wp_using_ext_object_cache() { return $GLOBALS['cache_state']['external']; }
function __( $text ) { return $text; }
function wp_generate_uuid4() { static $sequence = 0; return 'fixture-' . ++$sequence; }
function wp_cache_set( $key, $value, $group, $ttl ) {
	$GLOBALS['cache_calls'][] = [ 'set', $key, $group, $ttl ];
	if ( $GLOBALS['cache_state']['set'] ) { $GLOBALS['cache_values'][ $group ][ $key ] = $value; }
	return $GLOBALS['cache_state']['set'];
}
function wp_cache_get( $key, $group, $force, &$found ) {
	$GLOBALS['cache_calls'][] = [ 'get', $key, $group, $force ];
	if ( $GLOBALS['cache_state']['throw_get'] ) { throw new RuntimeException( 'password=never-expose' ); }
	$found = $GLOBALS['cache_state']['get'] && isset( $GLOBALS['cache_values'][ $group ][ $key ] );
	return $found ? $GLOBALS['cache_values'][ $group ][ $key ] : false;
}
function wp_cache_delete( $key, $group ) {
	$GLOBALS['cache_calls'][] = [ 'delete', $key, $group ];
	unset( $GLOBALS['cache_values'][ $group ][ $key ] );
	return $GLOBALS['cache_state']['delete'];
}
function wp_cache_flush() { throw new RuntimeException( 'Global cache flush forbidden in diagnostics.' ); }
class WP_Post {
	public $post_content;
	public function __construct( string $content ) { $this->post_content = $content; }
}
class ConnectedCache { public function is_connected() { return true; } }
class DisconnectedCache { public function is_connected() { return false; } }
class ThrowingCache { public function is_connected() { throw new RuntimeException( 'token=never-expose' ); } }
class RequiredArgumentCache { public function is_connected( $argument ) { throw new RuntimeException( 'Must not call this method.' ); } }
class MagicCache { public function __call( $method, $arguments ) { throw new RuntimeException( 'Must not call magic methods.' ); } }

$root = dirname( __DIR__ ) . '/noobrocket/inc/';
require $root . 'classes/event-management/subscriber-interface.php';
require $root . 'classes/admin/class-options-data.php';
require $root . 'Engine/Optimization/Bloat/Subscriber.php';
require $root . 'Engine/Cache/ObjectCache/Diagnostics.php';
$checks = 0;
function bloat_cache_check( $condition, string $message ): void {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
	$GLOBALS['checks']++;
}
function bloat_subscriber( array $options = [] ) {
	return new NoobRocket\Engine\Optimization\Bloat\Subscriber( new NoobRocket\Admin\Options_Data( $options ) );
}
$enabled = [ 'remove_dashicons' => 1, 'remove_embed_assets' => 1, 'remove_block_library' => 1 ];
$wp_query = (object) [ 'posts' => [ new WP_Post( '<p>Classic article.</p>' ) ] ];
$html = '<html><head><link id="wp-block-library-css" href="/blocks.css"><style id="wp-block-library-inline-css">.wp-block-test{color:red}</style><link id="theme-css" href="/theme.css"></head><body><p>Classic article.</p><script id="wp-embed-js" src="/wp-embed.js"></script><script id="theme-js">const example = \'<link id="wp-block-library-css" href="/keep.css">\';</script><!-- <link id="wp-block-library-css" href="/comment.css"> --></body></html>';
$off = bloat_subscriber();
bloat_cache_check( $html === $off->remove_unused_assets( $html ), 'all controls default off and preserve exact markup' );
$on = bloat_subscriber( $enabled );
$optimized = $on->remove_unused_assets( $html );
bloat_cache_check( false === strpos( $optimized, 'href="/blocks.css"' ) && false === strpos( $optimized, 'id="wp-block-library-inline-css"' ) && false === strpos( $optimized, 'src="/wp-embed.js"' ), 'unused block library and embed host script removed from classic page' );
bloat_cache_check( false !== strpos( $optimized, 'href="/theme.css"' ) && false !== strpos( $optimized, 'href="/keep.css"' ) && false !== strpos( $optimized, '/comment.css' ), 'theme assets and literal markup in scripts/comments preserved' );
$normalized = $on->normalize_options( [ 'remove_dashicons' => [ 1 ], 'remove_embed_assets' => 'on', 'remove_block_library' => true, 'unrelated' => 7 ] );
bloat_cache_check( 0 === $normalized['remove_dashicons'] && 0 === $normalized['remove_embed_assets'] && 1 === $normalized['remove_block_library'] && 7 === $normalized['unrelated'], 'controls reject malformed imported values and preserve unrelated settings' );
foreach ( [ 'admin', 'feed', 'embed', 'preview', 'bypass' ] as $guard ) {
	$state[ $guard ] = true;
	bloat_cache_check( $html === bloat_subscriber( $enabled )->remove_unused_assets( $html ), $guard . ' response retains assets' );
	$state[ $guard ] = false;
}
foreach ( [ 'block_theme', 'block_widget' ] as $guard ) {
	$state[ $guard ] = true;
	bloat_cache_check( false !== strpos( bloat_subscriber( $enabled )->remove_unused_assets( $html ), 'href="/blocks.css"' ), $guard . ' preserves block library' );
	$state[ $guard ] = false;
}
foreach ( [ '<!-- wp:paragraph --><p>Block content</p><!-- /wp:paragraph -->', '[shortcode]', '' ] as $content ) {
	$wp_query->posts = '' === $content ? [] : [ new WP_Post( $content ) ];
	bloat_cache_check( false !== strpos( bloat_subscriber( $enabled )->remove_unused_assets( $html ), 'href="/blocks.css"' ), 'blocks, dynamic shortcodes or missing query proof preserve library' );
}
$wp_query->posts = [ new WP_Post( '<p>Classic article.</p>' ) ];
foreach ( [ '<div class="wp-block-gallery">Gallery</div>', '<noscript><div class="wp-block-gallery">Fallback gallery</div></noscript>' ] as $block_markup ) {
	bloat_cache_check( false !== strpos( bloat_subscriber( $enabled )->remove_unused_assets( $html . $block_markup ), 'href="/blocks.css"' ), 'rendered block classes, including no-script fallback, preserve library' );
}
$rendered = bloat_subscriber( $enabled );
bloat_cache_check( 'block output' === $rendered->remember_block( 'block output' ), 'block observer preserves output' );
bloat_cache_check( false !== strpos( $rendered->remove_unused_assets( $html ), 'href="/blocks.css"' ), 'blocks rendered by theme or plugin preserve library' );
$styles->queue = [ 'wp-block-library', 'theme' ];
$styles->registered = [ 'theme' => (object) [ 'deps' => [ 'intermediate' ] ], 'intermediate' => (object) [ 'deps' => [ 'wp-block-library' ] ] ];
bloat_cache_check( false !== strpos( $on->remove_unused_assets( $html ), 'href="/blocks.css"' ), 'indirect style dependency preserves library' );
$styles->queue = [ 'dashicons', 'theme' ];
$styles->registered = [ 'theme' => (object) [ 'deps' => [ 'dashicons' ] ] ];
$on->remove_dashicons();
bloat_cache_check( [] === $dequeued, 'retained theme requiring Dashicons preserves stylesheet' );
$styles->queue = [ 'dashicons' ];
$state['logged_in'] = true;
$on->remove_dashicons();
bloat_cache_check( [] === $dequeued, 'logged-in visitor retains Dashicons for admin bar' );
$state['logged_in'] = false;
$on->remove_dashicons();
bloat_cache_check( [ 'dashicons' ] === $dequeued, 'anonymous visitor can remove unneeded Dashicons' );
$embed_html = $html . '<iframe class="wp-embedded-content" src="/article/embed/"></iframe>';
bloat_cache_check( false !== strpos( $on->remove_unused_assets( $embed_html ), 'src="/wp-embed.js"' ), 'core WordPress embeds retain host script' );
$scripts->queue = [ 'theme' ];
$scripts->registered = [ 'theme' => (object) [ 'deps' => [ 'wp-embed' ] ] ];
bloat_cache_check( false !== strpos( $on->remove_unused_assets( $html ), 'src="/wp-embed.js"' ), 'dependent scripts retain embed host loader' );
$on->remove_embed_discovery();
bloat_cache_check( [ [ 'wp_head', 'wp_oembed_add_discovery_links' ] ] === $removed_actions, 'only public discovery links removed; embed APIs/content filters untouched' );

$diagnostics = new NoobRocket\Engine\Cache\ObjectCache\Diagnostics();
$wp_object_cache = new stdClass();
$report = $diagnostics->status();
bloat_cache_check( ! $report['external'] && $report['health'] && null === $report['connected'], 'request-local cache reports healthy without inventing persistent provider' );
$first_key = $cache_calls[0][1];
bloat_cache_check( [ [ 'set', $first_key, 'noobrocket-health', 30 ], [ 'get', $first_key, 'noobrocket-health', true ], [ 'delete', $first_key, 'noobrocket-health' ] ] === $cache_calls && empty( $cache_values['noobrocket-health'] ), 'probe uses one short-lived owned key and deletes it' );
$cache_state['external'] = true;
$wp_object_cache = new ConnectedCache();
$report = $diagnostics->status();
bloat_cache_check( $report['external'] && $report['health'] && true === $report['connected'] && 'ConnectedCache' === $report['provider'], 'existing connected provider detected without installing a client or drop-in' );
bloat_cache_check( $first_key !== $cache_calls[3][1], 'separate probes have distinct owned keys' );
$wp_object_cache = new DisconnectedCache();
bloat_cache_check( ! $diagnostics->status()['health'], 'provider disconnect overrides local readback success' );
$wp_object_cache = new ThrowingCache();
$report = $diagnostics->status();
bloat_cache_check( ! $report['health'] && false === strpos( json_encode( $report ), 'never-expose' ), 'provider errors report failure without exposing exception secrets' );
foreach ( [ new RequiredArgumentCache(), new MagicCache() ] as $wp_object_cache ) {
	$report = $diagnostics->status();
	bloat_cache_check( $report['health'] && null === $report['connected'], 'unknown connection signatures and magic methods are not called' );
}
$wp_object_cache = new ConnectedCache();
foreach ( [ 'set', 'get', 'delete' ] as $failure ) {
	$cache_state[ $failure ] = false;
	bloat_cache_check( ! $diagnostics->status()['health'], 'failed cache ' . $failure . ' reports unhealthy' );
	$cache_state[ $failure ] = true;
}
$cache_state['throw_get'] = true;
$report = $diagnostics->status();
bloat_cache_check( ! $report['health'] && empty( $cache_values['noobrocket-health'] ) && 'delete' === end( $cache_calls )[0], 'probe read exception still deletes only its own key' );
bloat_cache_check( false === strpos( json_encode( $report ), 'never-expose' ), 'probe failure details never expose provider exception content' );
echo 'PASS: frontend bloat and existing object cache (', $checks, " assertions; standalone)\n";
