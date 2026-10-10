<?php
/** Native registered background-CSS publication on a marked, copied-plugin site. */
$wordpress = isset( $argv[1] ) ? realpath( $argv[1] ) : false;
if ( ! $wordpress || ! is_file( $wordpress . '/wp-load.php' ) || ! is_file( $wordpress . '/.noobrocket-acceptance-site' ) ) {
	fwrite( STDERR, "Provide a marked disposable WordPress installation with a copied plugin.\n" );
	exit( 2 );
}
$fixture_plugin = $wordpress . '/wp-content/plugins/noobrocket';
$source = dirname( __DIR__ ) . '/noobrocket';
$normalize = static function ( $path ) { return str_replace( '\\', '/', $path ); };
if ( ! realpath( $fixture_plugin ) || $normalize( realpath( $fixture_plugin ) ) !== $normalize( $fixture_plugin ) || realpath( $fixture_plugin ) === realpath( $source ) ) {
	fwrite( STDERR, "The selected site must contain a real plugin copy, not a source junction.\n" );
	exit( 2 );
}
foreach ( [ 'inc/Engine/Common/Cache/FilesystemCache.php', 'inc/Engine/Media/Lazyload/CSS/Subscriber.php' ] as $file ) {
	if ( hash_file( 'sha256', $source . '/' . $file ) !== hash_file( 'sha256', $fixture_plugin . '/' . $file ) ) {
		fwrite( STDERR, "Copy the current background-CSS owners into the disposable plugin before this check.\n" );
		exit( 2 );
	}
}
$temporary = $wordpress . '/wp-content/cache-cache-background-fixture-' . bin2hex( random_bytes( 8 ) );
if ( ! mkdir( $temporary ) ) { throw new RuntimeException( 'Cannot create the owned fixture directory.' ); }
define( 'NOOBROCKET_CACHE_ROOT_PATH', $temporary . '/cache/' );
define( 'NOOBROCKET_CONFIG_PATH', $temporary . '/config/' );
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
$_SERVER['HTTP_HOST'] = '127.0.0.1';
$_SERVER['REQUEST_URI'] = '/cache-cache-background-fixture/';
// The fixture-owned empty cache root prevents an early hit; frontend eligibility requires GET.
$_SERVER['REQUEST_METHOD'] = 'GET';
putenv( 'NOOBROCKET_TEST_PHASE=stylesheet-background-publication' );
require_once $wordpress . '/wp-includes/plugin.php';

$checks = 0;
$requests = 0;
$blocked = 0;
$source_url = 'https://192.0.2.8/styles/theme.css';
$source_css = '.hero{width:64px;height:64px;background-image:url(https://background-fixture.invalid/hero.png);color:red}';
$http = static function ( $pre, $args, $url ) use ( $source_url, $source_css, &$requests, &$blocked ) {
	if ( $url === $source_url . '?ver=7&theme=light' || $url === $source_url . '?ver=7&theme=dark' ) {
		++$requests;
		return [ 'headers' => [], 'body' => strpos( $url, 'theme=dark' ) !== false ? str_replace( 'color:red', 'color:black', $source_css ) : $source_css, 'response' => [ 'code' => 200, 'message' => 'OK' ], 'cookies' => [] ];
	}
	++$blocked;
	return new WP_Error( 'background_fixture_http', 'Unowned HTTP is blocked by the native background fixture.' );
};
add_filter( 'pre_http_request', $http, PHP_INT_MAX - 1, 3 );
$disable_async = static function () { return false; };
add_filter( 'action_scheduler_allow_async_request_runner', $disable_async );
// Admit only these mocked documentation-address resources, without DNS or real HTTP.
$allow_owned_source = static function ( $external, $host, $url ) use ( $source_url ) {
	return $external || $url === $source_url . '?ver=7&theme=light' || $url === $source_url . '?ver=7&theme=dark';
};
add_filter( 'http_request_host_is_external', $allow_owned_source, 10, 3 );
$enabled = true;
$enable_backgrounds = static function () use ( &$enabled ) { return $enabled; };
$buffer = null;
$check = static function ( $condition, $message ) use ( &$checks ) {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
	++$checks;
};
$stylesheet = static function ( $html ) {
	if ( ! preg_match( '/<link\b[^>]*href="([^"]+)"/i', $html, $match ) ) { return ''; }
	return html_entity_decode( $match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
};

try {
	require $wordpress . '/wp-load.php';
	// Keep request/async guards through WordPress shutdown, then restore our callbacks.
	register_shutdown_function( static function () use ( $http, $disable_async, &$blocked ) {
		remove_filter( 'pre_http_request', $http, PHP_INT_MAX - 1 );
		remove_filter( 'action_scheduler_allow_async_request_runner', $disable_async );
		if ( $blocked ) { throw new RuntimeException( 'Native background fixture blocked an unowned HTTP request.' ); }
	} );
	add_filter( 'pre_get_noobrocket_option_lazyload_css_bg_img', $enable_backgrounds );
	$check( $normalize( realpath( WP_CONTENT_DIR ) ) === $normalize( $wordpress . '/wp-content' ), 'owned fixture paths are inside this disposable site content directory' );
	$container = apply_filters( 'noobrocket_container', null );
	$owner = $container->get( 'lazyload_css_subscriber' );
	$extract = $container->get( 'common_extractcss_subscriber' );
	$cache = $container->get( 'lazyload_css_cache' );
	$check( $owner instanceof \NoobRocket\Engine\Media\Lazyload\CSS\Subscriber, 'production service container supplies the native background subscriber' );
	$check( strpos( $normalize( ( new ReflectionClass( $owner ) )->getFileName() ), $normalize( $fixture_plugin ) . '/' ) === 0, 'loaded subscriber belongs to the selected copied plugin' );
	$check( has_filter( 'noobrocket_buffer', [ $owner, 'maybe_replace_css_images' ] ) === 110000, 'native buffer hook registers the production subscriber at its actual priority' );
	foreach ( [ [ $extract, 'extract_css_files_from_html', 11 ], [ $extract, 'extract_inline_css_from_html', 14 ], [ $owner, 'create_lazy_css_files', 18 ], [ $owner, 'create_lazy_inline_css', 21 ], [ $owner, 'add_lazy_tag', 24 ] ] as $callback ) {
		$check( has_filter( 'noobrocket_generate_lazyloaded_css', [ $callback[0], $callback[1] ] ) === $callback[2], 'native generation pipeline registers ' . $callback[1] . ' at its production priority' );
	}
	$check( $container->get( 'lazyload_css_context' )->is_allowed(), 'native anonymous frontend context allows enabled background optimization' );
	// Exercise the registered callback without executing unrelated optimizer owners.
	$buffer = $GLOBALS['wp_filter']['noobrocket_buffer'];
	$owned_buffer = new WP_Hook();
	$owned_buffer->add_filter( 'noobrocket_buffer', [ $owner, 'maybe_replace_css_images' ], 110000, 1 );
	$GLOBALS['wp_filter']['noobrocket_buffer'] = $owned_buffer;
	$html = '<!doctype html><html><HEAD><link rel="stylesheet" href="' . $source_url . '?ver=7&amp;theme=light"><style>.inline{background-image:url(https://background-fixture.invalid/inline.png)}</style><!-- </head> --><script>const example="</head>";</script></HEAD><body><div class="hero"></div><div class="inline"></div></body></html>';
	$first = apply_filters( 'noobrocket_buffer', $html );
	$first_url = $stylesheet( $first );
	$check( $first_url !== $stylesheet( $html ) && preg_match( '/\.[a-f0-9]{64}\.css\?/', $first_url ), 'registered pipeline delivers a revisioned stylesheet URL' );
	$check( strpos( $first_url, WP_CONTENT_URL . '/' ) === 0 && strpos( $first_url, '\\' ) === false && strpos( $first_url, $normalize( $wordpress ) ) === false, 'generated URL stays under the public content URL without leaking a local filesystem path' );
	$check( $requests === 1 && $blocked === 0, 'native fetcher requests only the owned stylesheet once on a miss' );
	$check( strpos( $first, '.inline{background-image:var(--noobrocket-bg-' ) !== false, 'native extraction also transforms inline backgrounds' );
	$check( substr_count( $first, 'id="noobrocket-lazyload-bg-container"' ) === 1, 'native generation supplies one background recovery container' );
	$check( strpos( $first, 'id="noobrocket-lazyload-bg-container"' ) > strpos( $first, 'const example="</head>";' ) && strpos( $first, 'id="noobrocket-lazyload-bg-container"' ) < strpos( $first, '</HEAD>' ), 'recovery tags precede the real uppercase head boundary rather than a comment or script string' );
	$asset_path = WP_CONTENT_DIR . substr( wp_parse_url( $first_url, PHP_URL_PATH ), strlen( wp_parse_url( WP_CONTENT_URL, PHP_URL_PATH ) ) );
	$check( is_file( $asset_path ) && is_readable( $asset_path ), 'generated URL maps to a readable real file inside the disposable content directory' );
	$bytes = file_get_contents( $asset_path );
	$check( strpos( $bytes, 'var(--noobrocket-bg-' ) !== false && strpos( $bytes, 'color:red' ) !== false, 'native transformer publishes complete non-background CSS and lazy custom properties' );
	$check( preg_match( '/const noobrocket_pairs = (\[.*?\]); const noobrocket_excluded_pairs =/s', $first, $mapping_match ) === 1, 'native tag generator exposes its recovery mapping' );
	$mapping = json_decode( $mapping_match[1], true );
	$check( count( $mapping ) === 2, 'linked and inline background extraction both reach the delivered mapping' );
	foreach ( $mapping as $item ) {
		$check( strpos( $bytes . $first, 'var(--noobrocket-bg-' . $item['hash'] . ')' ) !== false, 'every delivered mapping has its exact generated CSS custom property' );
		$check( strpos( $first, $item['style'] ) !== false, 'noscript recovery retains each native generated background style' );
	}
	$check( preg_match( '/<noscript>\s*<style id="noobrocket-lazyload-bg-nostyle">(.*?)<\/style>\s*<\/noscript>/s', $first, $fallback ) === 1 && strpos( $fallback[1], 'hero.png' ) !== false && strpos( $fallback[1], 'inline.png' ) !== false, 'actual noscript tag contains linked and inline recovery CSS' );
	$second = apply_filters( 'noobrocket_buffer', $html );
	$check( $stylesheet( $second ) === $first_url && $requests === 1, 'next native render reuses the exact stylesheet URL without another source fetch' );
	$check( file_get_contents( $asset_path ) === $bytes, 'cache hit preserves previously published immutable stylesheet bytes' );
	parse_str( wp_parse_url( $first_url, PHP_URL_QUERY ), $query );
	$check( $query === [ 'ver' => '7', 'theme' => 'light' ], 'HTML-escaped original query survives without a page-time parameter' );
	$dark = apply_filters( 'noobrocket_buffer', str_replace( 'theme=light', 'theme=dark', $html ) );
	$dark_url = $stylesheet( $dark );
	$dark_path = WP_CONTENT_DIR . substr( wp_parse_url( $dark_url, PHP_URL_PATH ), strlen( wp_parse_url( WP_CONTENT_URL, PHP_URL_PATH ) ) );
	$check( $dark_url !== $first_url && $requests === 2, 'different query variant gets its own native publication and one source fetch' );
	$check( strpos( file_get_contents( $dark_path ), 'color:black' ) !== false && file_get_contents( $asset_path ) === $bytes, 'query-specific CSS publishes independently while old pages keep their original asset' );
	$light_again = apply_filters( 'noobrocket_buffer', $html );
	$dark_again = apply_filters( 'noobrocket_buffer', str_replace( 'theme=light', 'theme=dark', $html ) );
	$check( $stylesheet( $light_again ) === $first_url && $stylesheet( $dark_again ) === $dark_url && $requests === 2, 'alternating native query variants reuse independent publication pointers' );
	$records = [];
	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $cache->get_root_path(), FilesystemIterator::SKIP_DOTS ) ) as $file ) {
		$check( strpos( $file->getFilename(), '.nbr-css-' ) !== 0, 'native atomic writer removes its publication temporary files' );
		if ( substr( $file->getFilename(), -5 ) === '.json' ) { $records[] = json_decode( file_get_contents( $file->getPathname() ), true ); }
	}
	$check( count( $records ) === 2, 'actual cache has two independently owned query-aware publication records' );
	foreach ( $records as $record ) {
		$check( $record['revision'] === hash( 'sha256', strpos( $record['source'], 'theme=dark' ) !== false ? file_get_contents( $dark_path ) : $bytes ), 'actual publication revision covers all stylesheet bytes' );
	}
	$headless = '<html><body><link rel="stylesheet" href="' . $source_url . '?ver=7&amp;theme=light"><div class="hero"></div></body></html>';
	$check( apply_filters( 'noobrocket_buffer', $headless ) === $headless && $requests === 2, 'native headless markup retains original delivery without fetching or rewriting' );
	$enabled = false;
	$check( apply_filters( 'noobrocket_buffer', $html ) === $html, 'native disabled context preserves original markup' );
	$enabled = true;
	$owner->clear_generated_css();
	$check( ! is_dir( $cache->get_root_path() ), 'production purge owner removes its generated background cache' );
	$check( $blocked === 0, 'native pipeline attempted no unowned HTTP requests' );
	echo "PASS: $checks native stylesheet-background assertions; registered pipeline, real files, two owned fetches, zero unowned HTTP.\n";
} finally {
	if ( $buffer ) { $GLOBALS['wp_filter']['noobrocket_buffer'] = $buffer; }
	remove_filter( 'pre_get_noobrocket_option_lazyload_css_bg_img', $enable_backgrounds );
	remove_filter( 'http_request_host_is_external', $allow_owned_source );
	if ( is_dir( $temporary ) ) {
		$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $temporary, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $iterator as $file ) {
			if ( $file->isDir() && ! $file->isLink() ) { rmdir( $file->getPathname() ); }
			else { unlink( $file->getPathname() ); }
		}
		rmdir( $temporary );
	}
}
