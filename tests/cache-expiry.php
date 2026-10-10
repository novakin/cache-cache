<?php
/** Expiry traversal regression: actual owner and owned filesystem, modeled WordPress boundaries. */
declare(strict_types=1);

namespace NoobRocket\Buffer {
	class Cache {
		public static function can_generate_caching_files() { return $GLOBALS['fixture']['enabled']; }
	}
}
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'HOUR_IN_SECONDS', 3600 );
	require dirname( __DIR__ ) . '/noobrocket/inc/functions/files.php';
	require dirname( __DIR__ ) . '/noobrocket/inc/Engine/Cache/PurgeExpired/PurgeExpiredCache.php';
	$fixture = [ 'enabled' => true, 'urls' => [ 'https://example.test/site/', 'https://second.test/site/' ], 'events' => [], 'refresh' => [], 'checks' => 0 ];
	function get_noobrocket_option( $name, $default = false ) { return 'secret_cache_key' === $name ? 'site-secret' : $default; }
	function get_noobrocket_i18n_uri() { return $GLOBALS['fixture']['urls']; }
	function get_noobrocket_parse_url( $url ) { return parse_url( $url ); }
	function wpm_apply_filters_typed( $type, $hook, $value, ...$arguments ) { return $value; }
	function apply_filters( $hook, $value, ...$arguments ) {
		if ( 'noobrocket_refresh_expired_cache' === $hook ) {
			$GLOBALS['fixture']['refresh'][] = $arguments[0];
			return true;
		}
		return $value;
	}
	function do_action( $hook, ...$arguments ) { $GLOBALS['fixture']['events'][ $hook ][] = $arguments; }
	function untrailingslashit( $value ) { return rtrim( $value, '/' ); }
	function set_url_scheme( $url, $scheme ) { return preg_replace( '/^https?:/', $scheme . ':', $url ); }
	class ExpiryFilesystem {
		public $fail_path;
		public $publish_path;
		public function exists( $path ) { return file_exists( $path ); }
		public function delete( $path ) {
			$normalized = str_replace( '\\', '/', $path );
			if ( $normalized === str_replace( '\\', '/', (string) $this->fail_path ) ) { return false; }
			if ( ! file_exists( $path ) ) { return false; }
			$result = is_dir( $path ) ? rmdir( $path ) : unlink( $path );
			if ( $result && $normalized === str_replace( '\\', '/', (string) $this->publish_path ) ) { file_put_contents( $path, 'newly published cache' ); }
			return $result;
		}
	}
	function cache_expiry_check( $condition, $message ) {
		if ( ! $condition ) { throw new \RuntimeException( 'FAIL: ' . $message ); }
		++$GLOBALS['fixture']['checks'];
	}
	function cache_expiry_file( $relative, $age = 7200 ) {
		$path = $GLOBALS['temporary'] . '/' . $relative;
		if ( ! is_dir( dirname( $path ) ) ) { mkdir( dirname( $path ), 0777, true ); }
		file_put_contents( $path, 'owned cache fixture' );
		touch( $path, time() - $age );
		return $path;
	}
	$temporary = sys_get_temp_dir() . '/cache-cache-expiry-' . bin2hex( random_bytes( 6 ) );
	mkdir( $temporary );
	$filesystem = new ExpiryFilesystem();
	$purger = new \NoobRocket\Engine\Cache\PurgeExpired\PurgeExpiredCache( $temporary );
	$property = new \ReflectionProperty( $purger, 'filesystem' );
	$property->setAccessible( true );
	$property->setValue( $purger, $filesystem );
	try {
		$variants = [ 'index-https.html', 'index-https.html_gzip', 'index-mobile-https.html', 'index-mobile-https.html_gzip' ];
		for ( $page = 0; $page < 200; ++$page ) {
			foreach ( $variants as $name ) { cache_expiry_file( 'example.test/site/group/article-' . $page . '/' . $name ); }
		}
		$fresh = cache_expiry_file( 'example.test/site/fresh/index-https.html', 0 );
		$failed = cache_expiry_file( 'example.test/site/failed/index-https.html' );
		$filesystem->fail_path = $failed;
		$concurrent = cache_expiry_file( 'example.test/site/concurrent/index-https.html' );
		$filesystem->publish_path = $concurrent;
		$foreign = cache_expiry_file( 'example.test.evil/site/index-https.html' );
		$foreign_secret = cache_expiry_file( 'example.test-alice-foreign-secret/site/index-https.html' );
		$other_site = cache_expiry_file( 'example.test/other-site/index-https.html' );
		$logged = cache_expiry_file( 'example.test-alice-site-secret/site/index-https.html' );
		$second = cache_expiry_file( 'second.test/site/index-https.html' );
		$stale = [];
		foreach ( $variants as $name ) { $stale[] = cache_expiry_file( 'example.test/site/stale/' . $name, 3700 ); }
		$query = cache_expiry_file( 'example.test/site/query#fixture/index-https.html', 3700 );
		$outside = cache_expiry_file( 'outside/index-https.html' );
		$link = $temporary . '/example.test/site/outside-link';
		$linked = @symlink( $temporary . '/outside', $link );
		clearstatcache();
		$purger->purge_expired_files( 3600, true );
		cache_expiry_check( ! is_dir( $temporary . '/example.test/site/group' ), 'expired deep and wide page trees and all four variants are removed' );
		cache_expiry_check( is_file( $fresh ) && is_file( $failed ), 'fresh files and deletion failures survive the scan' );
		cache_expiry_check( 'newly published cache' === file_get_contents( $concurrent ), 'cache published during expiry retains its directory and new bytes' );
		cache_expiry_check( is_file( $foreign ) && is_file( $foreign_secret ) && is_file( $other_site ), 'neighboring hosts, foreign user buckets and other site paths remain untouched' );
		cache_expiry_check( ! is_file( $logged ) && ! is_file( $query ), 'logged-in and query variants are deleted without stale admission' );
		cache_expiry_check( [ 'https://example.test/site/stale/' ] === $fixture['refresh'] && 4 === count( array_filter( $stale, 'is_file' ) ), 'one refresh decision retains canonical plain/gzip/mobile variants inside the stale grace' );
		cache_expiry_check( is_file( $outside ), 'outside-owned cache is never visited, including when reachable through a symlink' );
		$deleted = $fixture['events']['noobrocket_after_automatic_cache_purge'][0][0];
		$public = array_values( array_filter( $deleted, static function ( $entry ) { return ! $entry['logged_in']; } ) );
		cache_expiry_check( 3 === count( $deleted ) && 202 === count( $public[0]['files'] ) && ! is_file( $second ), 'public purge hooks keep every successfully deleted page path, separate user bucket and additional home root' );
		$reported = array_map( static function ( $path ) { return str_replace( '\\', '/', $path ); }, $public[0]['files'] );
		cache_expiry_check( count( $reported ) === count( array_unique( $reported ) ) && ! in_array( str_replace( '\\', '/', dirname( $failed ) ), $reported, true ), 'purge reports each directory once and excludes unsuccessful deletions' );
		foreach ( $stale as $path ) { touch( $path, time() - 7300 ); }
		clearstatcache();
		$purger->purge_expired_files( 3600, true );
		cache_expiry_check( 1 === count( $fixture['refresh'] ) && 0 === count( array_filter( $stale, 'is_file' ) ), 'hard stale deadline removes every variant without re-admission' );
		$untouched = cache_expiry_file( 'example.test/site/disabled/index-https.html' );
		$fixture['enabled'] = false;
		$purger->purge_expired_files( 3600 );
		cache_expiry_check( is_file( $untouched ), 'cache generation veto prevents expiry deletion' );
		$fixture['enabled'] = true;
		$purger->purge_expired_files( 0 );
		cache_expiry_check( is_file( $untouched ), 'disabled lifespan leaves existing files intact' );
		echo 'PASS: ', $fixture['checks'], ' expiry traversal assertions; symlink path ', $linked ? 'verified' : 'unavailable on this host', '.', PHP_EOL;
	} finally {
		if ( is_link( $link ) ) { unlink( $link ); }
		$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $temporary, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $iterator as $item ) { $item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() ); }
		rmdir( $temporary );
	}
}
