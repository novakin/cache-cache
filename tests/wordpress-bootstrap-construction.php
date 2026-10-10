<?php
/** Native sanitization, isolated schema/query state and registered startup services. */
$wordpress = isset( $argv[1] ) ? realpath( $argv[1] ) : false;
if ( PHP_SAPI !== 'cli' || ! $wordpress || ! is_file( $wordpress . '/wp-load.php' ) || ! is_file( $wordpress . '/.noobrocket-acceptance-site' ) ) {
	fwrite( STDERR, "Provide a marked disposable WordPress installation with a copied plugin.\n" );
	exit( 2 );
}
$source = dirname( __DIR__ ) . '/noobrocket';
$installed = $wordpress . '/wp-content/plugins/noobrocket';
$normalize = static function ( $path ) { return str_replace( '\\', '/', $path ); };
if ( ! realpath( $installed ) || $normalize( realpath( $installed ) ) !== $normalize( $installed ) || realpath( $source ) === realpath( $installed ) ) {
	fwrite( STDERR, "The disposable site must contain a real plugin copy, not a source junction.\n" );
	exit( 2 );
}
foreach ( [
	'inc/Dependencies/BerlinDB/Database/Schema.php', 'inc/Engine/Admin/Settings/Page.php',
	'inc/Engine/Admin/Settings/ServiceProvider.php', 'inc/Engine/Preload/ServiceProvider.php',
] as $file ) {
	if ( ! is_file( $installed . '/' . $file ) || hash_file( 'sha256', $source . '/' . $file ) !== hash_file( 'sha256', $installed . '/' . $file ) ) {
		fwrite( STDERR, "Copy the current bootstrap owners into the disposable plugin before this check.\n" );
		exit( 2 );
	}
}
define( 'WP_ADMIN', true );
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
$_SERVER['SERVER_PORT'] = parse_url( 'http://' . $_SERVER['HTTP_HOST'], PHP_URL_PORT ) ?: 80;
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'POST';
$completed = false;
register_shutdown_function( static function () use ( &$completed ) {
	if ( ! $completed ) {
		fwrite( STDERR, "FAIL: native bootstrap fixture did not complete.\n" );
		exit( 2 );
	}
} );
require $wordpress . '/wp-load.php';
ini_set( 'display_errors', 'stderr' );
error_reporting( E_ALL );

use NoobRocket\Dependencies\BerlinDB\Database\Column;
use NoobRocket\Dependencies\BerlinDB\Database\Schema;
use NoobRocket\Engine\Preload\Database\Schemas\Cache as CacheSchema;
use NoobRocket\Engine\Preload\Database\Queries\Cache as CacheQuery;
use NoobRocket\Engine\Admin\Settings\Page;

class BootstrapFixtureExternalSchema extends CacheSchema {}

$checks = 0;
$check = static function ( $condition, $message ) use ( &$checks ) {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
	++$checks;
};
$container = apply_filters( 'noobrocket_container', null );
$check( $container instanceof NoobRocket\Dependencies\League\Container\Container, 'native registered container is active' );
$templates = new ReflectionProperty( Schema::class, 'column_templates' );
$templates->setAccessible( true );
$templates->setValue( null, [] );
$make = static function ( array $definitions ) {
	$schema = ( new ReflectionClass( CacheSchema::class ) )->newInstanceWithoutConstructor();
	$schema->columns = $definitions;
	$schema->__construct();
	return $schema;
};
$metadata = static function ( Column $column ) {
	$data = $column->to_array();
	if ( is_array( $data['validate'] ) && isset( $data['validate'][0] ) && is_object( $data['validate'][0] ) ) {
		$data['validate'][0] = get_class( $data['validate'][0] );
	}
	return $data;
};

$schemas = [
	CacheSchema::class,
	NoobRocket\Engine\Optimization\RUCSS\Database\Schemas\UsedCSS::class,
	NoobRocket\Engine\CDN\HostedCDN\Database\Schemas\HostedCDN::class,
	NoobRocket\Engine\Admin\PerformanceMonitoring\Database\Schemas\PerformanceMonitoring::class,
	NoobRocket\Engine\Media\AboveTheFold\Database\Schemas\AboveTheFold::class,
];
foreach ( $schemas as $class ) {
	$raw = ( new ReflectionClass( $class ) )->getDefaultProperties()['columns'];
	$before = count( $templates->getValue() );
	$first = new $class();
	$second = new $class();
	$check( $before + 1 === count( $templates->getValue() ), $class . ' reuses one native sanitized template' );
	foreach ( $raw as $index => $definition ) {
		$expected = new Column( $definition );
		$check( $metadata( $expected ) === $metadata( $first->columns[ $index ] ) && $metadata( $expected ) === $metadata( $second->columns[ $index ] ), $class . ' preserves complete column metadata' );
		$check( $first->columns[ $index ] !== $second->columns[ $index ], $class . ' preserves distinct column objects' );
	}
}

$raw = [ [ 'name' => 'value', 'type' => 'datetime', 'default' => '2026-10-10 10:00:00', 'aliases' => [ 'alias' ], 'relationships' => [ 'related' ] ] ];
$first = $make( $raw );
$first->columns[0]->default = '2026-10-11 10:00:00';
$first->columns[0]->aliases[] = 'changed';
$first->columns[0]->caps['select'] = 'manage_options';
$first->columns[0]->relationships[] = 'changed';
$second = $make( $raw );
$third = $make( $raw );
$check( '2026-10-10 10:00:00' === $second->columns[0]->default && [ 'alias' ] === $second->columns[0]->aliases && [ 'related' ] === $second->columns[0]->relationships && 'exist' === $second->columns[0]->caps['select'], 'first-instance scalar and nested array mutations cannot change the template' );
$check( $second->columns[0] === $second->columns[0]->validate[0] && $third->columns[0] === $third->columns[0]->validate[0], 'internal validators bind to each cloned column' );
$second->columns[0]->default = '2026-10-12 10:00:00';
$check( '2026-10-12 10:00:00' === call_user_func( $second->columns[0]->validate, '' ) && '2026-10-10 10:00:00' === call_user_func( $third->columns[0]->validate, '' ), 'cloned validators read their own mutable default' );
$changed = $raw;
$changed[0]['default'] = '2026-10-13 10:00:00';
$check( '2026-10-13 10:00:00' === $make( $changed )->columns[0]->default, 'changed raw definitions get new metadata' );

// Plain definitions are invariant under native KSES allow-list changes.
$saved_tags = $allowedtags;
$saved_entities = $allowedentitynames;
try {
	$allowedtags = [];
	$allowedentitynames = [];
	$check( $metadata( new Column( $raw[0] ) ) === $metadata( $make( $raw )->columns[0] ), 'plain cached values match native sanitization with different KSES globals' );
} finally {
	$allowedtags = $saved_tags;
	$allowedentitynames = $saved_entities;
}

$count = count( $templates->getValue() );
$html = [ [ 'name' => 'value', 'type' => 'varchar', 'default' => '<b>safe</b><script>bad</script>&amp;' ] ];
$check( $metadata( new Column( $html[0] ) ) === $metadata( $make( $html )->columns[0] ) && $count === count( $templates->getValue() ), 'markup/entity definitions keep native construction and sanitization' );
$object = new Column( [ 'name' => 'object', 'type' => 'varchar' ] );
$check( $object === $make( [ $object ] )->columns[0] && $count === count( $templates->getValue() ), 'existing Column inputs preserve original identity' );
$with_object = [ [ 'name' => 'object_value', 'type' => 'varchar', 'custom' => (object) [ 'value' => 1 ] ] ];
$check( $with_object[0]['custom'] === $make( $with_object )->columns[0]->custom && $count === count( $templates->getValue() ), 'nested object inputs retain original construction' );
new BootstrapFixtureExternalSchema();
new BootstrapFixtureExternalSchema();
$check( $count === count( $templates->getValue() ), 'third-party subclasses retain original construction' );

foreach ( [ 'pre_kses', 'wp_kses_allowed_html', 'kses_allowed_protocols', 'block_parser_class', 'sanitize_key', 'all' ] as $hook ) {
	$calls = 0;
	$callback = static function ( $value ) use ( &$calls ) { ++$calls; return $value; };
	add_filter( $hook, $callback );
	try {
		$before = count( $templates->getValue() );
		$probe = $raw;
		$probe[0]['comment'] = $hook;
		$expected = $metadata( new Column( $probe[0] ) );
		$native_calls = $calls;
		$calls = 0;
		$first = $make( $probe );
		$first_calls = $calls;
		$second = $make( $probe );
		$check( $expected === $metadata( $first->columns[0] ) && $expected === $metadata( $second->columns[0] ) && $before === count( $templates->getValue() ), $hook . ' custom filter preserves original metadata and disables reuse' );
		$check( $native_calls === $first_calls && 2 * $native_calls === $calls, $hook . ' custom callback counts match each native original construction' );
	} finally {
		remove_filter( $hook, $callback );
	}
}
$suffix = '';
$custom = static function ( $value ) use ( &$suffix ) { return $value . $suffix; };
add_filter( 'pre_kses', $custom, 20 );
try {
	$suffix = '-one';
	$one = $make( [ [ 'name' => 'filtered', 'type' => 'varchar', 'comment' => 'comment' ] ] );
	$suffix = '-two';
	$two = $make( [ [ 'name' => 'filtered', 'type' => 'varchar', 'comment' => 'comment' ] ] );
	$check( 'comment-one' === $one->columns[0]->comment && 'comment-two' === $two->columns[0]->comment, 'stateful custom KSES output is never reused' );
} finally {
	remove_filter( 'pre_kses', $custom, 20 );
}

$context_schema = null;
$context = static function () use ( &$context_schema, $make, $raw ) { $context_schema = $make( $raw ); };
$before = count( $templates->getValue() );
add_action( 'bootstrap_fixture_context', $context );
try {
	do_action( 'bootstrap_fixture_context' );
	$check( $before + 1 === count( $templates->getValue() ) && $metadata( $context_schema->columns[0] ) === $metadata( $make( $raw )->columns[0] ), 'current hook context gets independent sanitized templates' );
} finally {
	remove_action( 'bootstrap_fixture_context', $context );
}
$saved_charset = $wpdb->charset;
$saved_collate = $wpdb->collate;
try {
	$wpdb->charset = 'utf8';
	$wpdb->collate = 'utf8_general_ci';
	$changed_db = $make( $raw );
	$check( 'utf8' === $changed_db->columns[0]->encoding && 'utf8_general_ci' === $changed_db->columns[0]->collation, 'database charset/collation changes get current metadata' );
} finally {
	$wpdb->charset = $saved_charset;
	$wpdb->collate = $saved_collate;
}
$check( $saved_charset === $make( $raw )->columns[0]->encoding && $saved_collate === $make( $raw )->columns[0]->collation, 'database context restoration gets original metadata' );

// Actual provider resolutions retain independent mutable queries and state during nested work.
$outer = $container->get( 'preload_caches_query' );
$inner = $container->get( 'preload_caches_query' );
$query_columns = new ReflectionProperty( NoobRocket\Dependencies\BerlinDB\Database\Query::class, 'columns' );
$query_columns->setAccessible( true );
$outer_columns = $query_columns->getValue( $outer );
$inner_columns = $query_columns->getValue( $inner );
$check( $outer instanceof CacheQuery && $inner instanceof CacheQuery && $outer !== $inner && $outer_columns[0] !== $inner_columns[0], 'registered queries and their columns remain independent' );
$outer_columns[0]->comment = 'outer-only';
$check( 'outer-only' !== $inner_columns[0]->comment, 'one registered query cannot change another query column' );
$nested = false;
$callback = static function ( $query ) use ( $outer, $inner, &$nested ) {
	if ( $query === $outer && ! $nested ) {
		$nested = true;
		$inner->query( [ 'status' => 'bootstrap-inner-impossible', 'count' => true ], false );
	}
};
add_action( 'pre_get_caches', $callback );
try {
	$outer_result = $outer->query( [ 'status' => 'bootstrap-outer-impossible', 'number' => 3 ], false );
	$check( '' === $wpdb->last_error, 'nested read-only queries complete against the native database' );
	$check( $nested && [] === $outer_result && 'bootstrap-outer-impossible' === $outer->query_vars['status'] && 3 === $outer->query_vars['number'] && 'bootstrap-inner-impossible' === $inner->query_vars['status'] && true === $inner->query_vars['count'], 'nested native query work preserves each query state and results' );
} finally {
	remove_action( 'pre_get_caches', $callback );
}

$table = $container->get( 'preload_caches_table' );
$check( $table === $container->get( 'preload_caches_table' ), 'preload table subscriber is shared' );
$subscriptions = 0;
foreach ( $wp_filter['noobrocket_preload_activation']->callbacks as $registered ) {
	foreach ( $registered as $callback ) {
		if ( [ $table, 'maybe_upgrade' ] === $callback['function'] ) { ++$subscriptions; }
	}
}
$check( 1 === $subscriptions, 'shared table registers one preload activation callback' );
$page = $container->get( 'settings_page' );
$check( $page instanceof Page, 'native settings page resolves with literal null legacy dependencies' );
$parameters = ( new ReflectionMethod( Page::class, '__construct' ) )->getParameters();
$check( $parameters[9]->getType()->allowsNull() && $parameters[10]->getType()->allowsNull() && $parameters[9]->isDefaultValueAvailable() && $parameters[10]->isDefaultValueAvailable(), 'legacy constructor positions retain nullable optional types' );

if ( 'multisite' === ( $argv[2] ?? '' ) ) {
	$check( is_multisite(), 'multisite phase requires a real disposable network' );
	$original_blog = get_current_blog_id();
	$original_name = $table->get_name();
	$site = wp_insert_site( [ 'domain' => get_network()->domain, 'path' => '/bootstrap-fixture-' . bin2hex( random_bytes( 6 ) ) . '/', 'network_id' => get_current_network_id() ] );
	$check( ! is_wp_error( $site ) && $site > 0, 'owned temporary blog initialized' );
	try {
		switch_to_blog( $site );
		$check( $table === $container->get( 'preload_caches_table' ) && $wpdb->get_blog_prefix( $site ) . 'nbr_noobrocket_cache' === $table->get_name() && $original_name !== $table->get_name(), 'shared table subscriber follows native blog switch' );
		$switched_query = $container->get( 'preload_caches_query' );
		$switched_columns = $query_columns->getValue( $switched_query );
		$check( $outer !== $switched_query && $outer_columns[0] !== $switched_columns[0] && $wpdb->charset === $switched_columns[0]->encoding && $wpdb->collate === $switched_columns[0]->collation, 'switched blog keeps independent query columns and current database metadata' );
	} finally {
		if ( get_current_blog_id() !== $original_blog ) { restore_current_blog(); }
		require_once ABSPATH . 'wp-admin/includes/ms.php';
		wpmu_delete_blog( $site, true );
	}
	$check( $original_blog === get_current_blog_id() && $original_name === $table->get_name(), 'shared table subscriber restores original blog context' );
}
echo $checks . ' native bootstrap construction assertions passed; PHP ' . PHP_VERSION . '; WordPress ' . $wp_version . ".\n";
$completed = true;
