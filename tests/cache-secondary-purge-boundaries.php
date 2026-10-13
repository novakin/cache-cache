<?php
/** Native hooks/parser/filesystem and actual writer paths; owned files, no site/database/HTTP bootstrap. */
declare(strict_types=1);

$wordpress = isset( $argv[1] ) ? realpath( $argv[1] ) : false;
if ( ! $wordpress || ! is_file( $wordpress . '/wp-includes/plugin.php' ) ) {
	throw new RuntimeException( 'Provide WordPress core for native hooks and filesystem classes.' );
}
define( 'ABSPATH', $wordpress . '/' );
define( 'NOOBROCKET_SLUG', 'noobrocket_settings' );
$root = sys_get_temp_dir() . '/noobrocket-secondary-purge-' . bin2hex( random_bytes( 8 ) );
mkdir( $root . '/pages/example.test', 0777, true );
mkdir( $root . '/config' );
define( 'NOOBROCKET_CACHE_PATH', $root . '/pages/' );
require ABSPATH . 'wp-includes/plugin.php';
require ABSPATH . 'wp-includes/http.php';
require ABSPATH . 'wp-includes/class-wp-error.php';
require ABSPATH . 'wp-includes/class-wp-post.php';
require ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
require ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
require dirname( __DIR__ ) . '/noobrocket/vendor/autoload.php';
require dirname( __DIR__ ) . '/noobrocket/inc/functions/formatting.php';
require dirname( __DIR__ ) . '/noobrocket/inc/functions/files.php';

function noobrocket_get_constant( $name, $default = null ) { return defined( $name ) ? constant( $name ) : $default; }
function get_noobrocket_option( $name, $default = false ) { return 'secret_cache_key' === $name ? 'owned-secret' : $default; }
function get_the_time( $format, $post ) { return '2026-10-11'; }
function get_year_link( $year ) { return 'https://example.test/2026/'; }
function get_month_link( $year, $month ) { return 'https://example.test/2026/10/'; }
function get_day_link( $year, $month, $day ) { return 'https://example.test/2026/10/11/'; }
function get_object_taxonomies( $type, $output ) { return []; }
function get_post_type( $id ) { return 'post'; }
function wp_normalize_path( $path ) { return str_replace( '\\', '/', $path ); }
function untrailingslashit( $path ) { return rtrim( $path, '/\\' ); }
function is_ssl() { return false; }
function is_admin() { return false; }

class SecondaryPurgeFilesystem extends WP_Filesystem_Direct {
	public $selected = [];
	public function delete( $path, $recursive = false, $type = false ) {
		$this->selected[] = wp_normalize_path( (string) realpath( $path ) );
		return false;
	}
}
function secondary_purge_check( $condition, string $message ): void {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
	++$GLOBALS['checks'];
}
function secondary_purge_owner( WP_Filesystem_Direct $filesystem ): NoobRocket\Engine\Cache\Purge {
	$purge = ( new ReflectionClass( NoobRocket\Engine\Cache\Purge::class ) )->newInstanceWithoutConstructor();
	$property = new ReflectionProperty( $purge, 'filesystem' );
	$property->setAccessible( true );
	$property->setValue( $purge, $filesystem );
	return $purge;
}
function secondary_purge_file( string $path ): string {
	if ( ! is_dir( dirname( $path ) ) ) { mkdir( dirname( $path ), 0777, true ); }
	file_put_contents( $path, 'owned fixture' );
	return wp_normalize_path( (string) realpath( $path ) );
}
function secondary_purge_writer_path( string $request ): string {
	foreach ( [ NoobRocket\Buffer\Config::class, NoobRocket\Buffer\Tests::class ] as $class ) {
		$memo = new ReflectionProperty( $class, 'memoized' );
		$memo->setAccessible( true );
		$memo->setValue( null, [] );
	}
	foreach ( [ 'config_dir_path', 'server' ] as $name ) {
		$state = new ReflectionProperty( NoobRocket\Buffer\Config::class, $name );
		$state->setAccessible( true );
		$state->setValue( null, null );
	}
	$config = new NoobRocket\Buffer\Config( [ 'config_dir_path' => $GLOBALS['root'] . '/config', 'server' => [ 'HTTP_HOST' => 'example.test', 'REQUEST_URI' => $request, 'REQUEST_METHOD' => 'GET' ] ] );
	$tests = new NoobRocket\Buffer\Tests( $config, [ 'cookies' => [], 'get' => [], 'post' => [] ] );
	$writer = new NoobRocket\Buffer\Cache( $tests, $config, [ 'cache_dir_path' => NOOBROCKET_CACHE_PATH ] );
	return wp_normalize_path( $writer->get_cache_path( [ 'webp' => false ] ) );
}
$checks = 0;
$filesystem = new SecondaryPurgeFilesystem( null );
$purge = secondary_purge_owner( $filesystem );
$subscriber = new NoobRocket\Engine\Cache\PurgeActionsSubscriber( new NoobRocket\Admin\Options_Data( [] ), $purge );
$events = new NoobRocket\Event_Management\Event_Manager();
$events->add_subscriber( $subscriber );
$GLOBALS['wp_rewrite'] = (object) [ 'pagination_base' => 'page' ];
$url = 'https://example.test/../sibling/';
$dates = static function () use ( &$url ) { return [ $url ]; };
add_filter( 'noobrocket_post_dates_urls', $dates );
file_put_contents( $root . '/config/example.test.php', '<?php $noobrocket_config_complete=true;' );
$actual = new WP_Filesystem_Direct( null );
$actual_purge = secondary_purge_owner( $actual );
$public_only = 'public-only' === ( $argv[2] ?? '' );
try {
	$sentinel = secondary_purge_file( $root . '/pages/sibling/sentinel.txt' );
	if ( ! $public_only ) {
		do_action( 'after_noobrocket_clean_post', new WP_Post( (object) [ 'ID' => 1 ] ) );
		secondary_purge_check( [] === $filesystem->selected && is_file( $sentinel ), 'registered after-post purge never selects a sibling bucket' );
	}
	foreach ( [ '/archive/', '/News/MixedCase/', '/%C3%A9/', '/%c3%a9/', '/has%20space/', '/escaped%2Fslash/', '/%252e%252e/double/', '/missing/../archive/' ] as $request ) {
		$target = secondary_purge_file( secondary_purge_writer_path( $request ) );
		$filesystem->selected = [];
		$url = 'https://example.test' . $request;
		if ( ! $public_only ) {
			do_action( 'after_noobrocket_clean_post', new WP_Post( (object) [ 'ID' => 1 ] ) );
			secondary_purge_check( [ $target ] === $filesystem->selected && is_file( $target ), $request . ': registered purge selects the actual native writer path without deleting' );
			$actual_purge->purge_url( $url );
			secondary_purge_check( ! is_file( $target ) && is_file( $sentinel ), $request . ': actual content purge removes owned bytes only' );
			secondary_purge_file( $target );
		}
		$result = noobrocket_clean_files( $url, $actual, false );
		secondary_purge_check( $result[ $url ] && ! is_file( $target ) && is_file( $sentinel ), $request . ': public purge follows the same native writer path' );
	}
	foreach ( [ 'public', 'content' ] as $owner ) {
		if ( $public_only && 'content' === $owner ) { continue; }
		foreach ( [ false, true ] as $canonical_present ) {
			$slug = '/query-' . $owner . ( $canonical_present ? '-canonical' : '-only' );
			$variants = [];
			if ( $canonical_present ) { $variants[] = secondary_purge_file( secondary_purge_writer_path( $slug ) ); }
			foreach ( [ '?test=1', '?test=2', '?test=two%20words' ] as $query ) {
				$variants[] = secondary_purge_file( secondary_purge_writer_path( $slug . $query ) );
			}
			$variants[] = secondary_purge_file( $root . '/pages/example.test-alice-owned-secret' . $slug . '#test=1/index-mobile-https.html_gzip' );
			$neighbor = secondary_purge_file( secondary_purge_writer_path( $slug . '-neighbor?test=1' ) );
			$child = secondary_purge_file( secondary_purge_writer_path( $slug . '/child?test=1' ) );
			$foreign = secondary_purge_file( $root . '/pages/example.test-alice-foreign-secret' . $slug . '#test=1/index.html' );
			_noobrocket_get_cache_dirs( '', '', true );
			$url = 'https://example.test' . $slug;
			if ( 'public' === $owner ) {
				$result = noobrocket_clean_files( $url, $actual, false );
				secondary_purge_check( $result[ $url ], 'public query-only and canonical purges report actual removal' );
			} else { $actual_purge->purge_url( $url ); }
			secondary_purge_check( [] === array_filter( $variants, 'is_file' ) && is_file( $neighbor ) && is_file( $foreign ) && is_file( $sentinel ), $owner . ': nontrailing query siblings purge without selecting slug prefixes or foreign buckets' );
			if ( 'content' === $owner ) { secondary_purge_check( is_file( $child ), 'content query purge preserves descendant pages' ); }
		}
	}
	if ( $public_only ) {
		echo 'PASS: ', $checks, ' public purge/native writer assertions.', PHP_EOL;
		return;
	}
	$target = secondary_purge_file( secondary_purge_writer_path( '/%C3%A9/' ) );
	$actual_purge->purge_url( 'https://example.test/é/' );
	secondary_purge_check( ! is_file( $target ), 'literal Unicode content URL selects its browser-encoded cache' );
	$variants = [];
	foreach ( [ 'index.html', 'index.html_gzip', 'index-mobile-https-webp.html', 'index-mobile-https.html_gzip', '#query/index.html', 'page/2/index.html' ] as $name ) {
		$variants[] = secondary_purge_file( $root . '/pages/example.test/variants/' . $name );
	}
	$variants[] = secondary_purge_file( $root . '/pages/example.test-alice-owned-secret/variants/index.html' );
	$foreign = secondary_purge_file( $root . '/pages/example.test-alice-foreign-secret/variants/index.html' );
	_noobrocket_get_cache_dirs( '', '', true );
	$actual_purge->purge_url( 'https://example.test/variants/?ignored=1', true );
	secondary_purge_check( [] === array_filter( $variants, 'is_file' ) && is_file( $foreign ) && is_file( $sentinel ), 'scheme/device/WebP/query/pagination/current-user variants purge; foreign buckets survive' );
	$public = secondary_purge_file( $root . '/pages/example.test/index-mobile-https.html' );
	$user = secondary_purge_file( $root . '/pages/example.test-alice-owned-secret/index-mobile-https.html' );
	$child = secondary_purge_file( $root . '/pages/example.test/child/index.html' );
	$actual_purge->purge_url( 'https://example.test/', false, true );
	secondary_purge_check( ! is_file( $public ) && is_file( $user ) && is_file( $child ) && is_file( $sentinel ), 'guest homepage hint publication preserves authenticated buckets, children and sibling sites' );
	$public = secondary_purge_file( $root . '/pages/example.test/hint-page/index-https.html_gzip' );
	$user = secondary_purge_file( $root . '/pages/example.test-alice-owned-secret/hint-page/index-https.html_gzip' );
	$child = secondary_purge_file( $root . '/pages/example.test/hint-page/child/index.html' );
	$actual_purge->purge_url( 'https://example.test/hint-page/', false, true );
	secondary_purge_check( ! is_file( $public ) && is_file( $user ) && is_file( $child ) && is_file( $sentinel ), 'guest post hint publication preserves authenticated page variants and descendants' );
	if ( 'WIN' !== strtoupper( substr( PHP_OS, 0, 3 ) ) ) {
		foreach ( [ 'redirected', 'linked-page/page', 'query/#query/outside' ] as $name ) {
			$link = $root . '/pages/example.test/' . $name;
			if ( ! is_dir( dirname( $link ) ) ) { mkdir( dirname( $link ), 0777, true ); }
			secondary_purge_check( symlink( $root . '/pages/sibling', $link ), 'create owned directory link ' . $name );
		}
		secondary_purge_file( $root . '/pages/example.test/linked-page/index.html' );
		secondary_purge_file( $root . '/pages/example.test/query/#query/index.html' );
		$alias_target = secondary_purge_file( $root . '/pages/example.test/alias-target/index.html' );
		secondary_purge_check( symlink( dirname( $alias_target ), $root . '/pages/example.test/internal-alias' ), 'create owned same-bucket directory alias' );
		$actual_purge->purge_url( 'https://example.test/redirected/', true );
		$actual_purge->purge_url( 'https://example.test/linked-page/', true );
		$actual_purge->purge_url( 'https://example.test/query/', true );
		$actual_purge->purge_url( 'https://example.test/internal-alias/', true );
		secondary_purge_check( is_file( $alias_target ), 'a root directory alias does not invalidate a different URL inside its bucket' );
		secondary_purge_check( is_file( $sentinel ), 'root/pagination/nested-query directory links never visit their targets' );
		$query_target = secondary_purge_file( $root . '/pages/example.test/alias-target/query#test=1/index.html' );
		$link = $root . '/pages/example.test/linked-query#test=1';
		secondary_purge_check( symlink( dirname( $query_target ), $link ), 'create sibling-query directory alias' );
		$actual_purge->purge_url( 'https://example.test/linked-query' );
		noobrocket_clean_files( 'https://example.test/linked-query', $actual, false );
		secondary_purge_check( is_link( $link ) && is_file( $query_target ), 'sibling-query aliases are not followed or selected' );
		$actual_purge->purge_url( 'https://example.test/internal-alias/query' );
		noobrocket_clean_files( 'https://example.test/internal-alias/query', $actual, false );
		secondary_purge_check( is_file( $query_target ), 'query scans through a parent alias never select another URL' );
	} else {
		echo "SKIP: directory-link proof requires Linux filesystem.\n";
	}
	echo 'PASS: ', $checks, ' secondary purge/native writer assertions.', PHP_EOL;
} finally {
	$events->remove_subscriber( $subscriber );
	remove_filter( 'noobrocket_post_dates_urls', $dates );
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $iterator as $entry ) {
		if ( $entry->isLink() || ! $entry->isDir() ) { unlink( $entry->getPathname() ); }
		else { rmdir( $entry->getPathname() ); }
	}
	rmdir( $root );
}
