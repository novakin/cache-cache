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
foreach ( [ 'inc/Engine/Common/Cache/FilesystemCache.php', 'inc/Engine/Media/Lazyload/CSS/Subscriber.php', 'inc/Engine/Media/Lazyload/CSS/Front/FileResolver.php', 'inc/Engine/Media/Lazyload/CSS/Front/ContentFetcher.php' ] as $file ) {
	if ( hash_file( 'sha256', $source . '/' . $file ) !== hash_file( 'sha256', $fixture_plugin . '/' . $file ) ) {
		fwrite( STDERR, "Copy the current background-CSS owners into the disposable plugin before this check.\n" );
		exit( 2 );
	}
}
$temporary = $wordpress . '/wp-content/cc-bg-' . bin2hex( random_bytes( 4 ) );
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
$request_args = [];
$extra_sources = [];
$source_url = 'https://192.0.2.8/styles/theme.css';
$source_css = '.hero{width:64px;height:64px;background-image:url(https://background-fixture.invalid/hero.png);color:red}';
$http = static function ( $pre, $args, $url ) use ( $source_url, $source_css, &$requests, &$blocked, &$request_args, &$extra_sources ) {
	if ( $url === $source_url . '?ver=7&theme=light' || $url === $source_url . '?ver=7&theme=dark' ) {
		++$requests;
		$request_args[] = [ $url, $args ];
		return [ 'headers' => [], 'body' => strpos( $url, 'theme=dark' ) !== false ? str_replace( 'color:red', 'color:black', $source_css ) : $source_css, 'response' => [ 'code' => 200, 'message' => 'OK' ], 'cookies' => [] ];
	}
	if ( array_key_exists( $url, $extra_sources ) ) {
		++$requests;
		$request_args[] = [ $url, $args ];
		return false === $extra_sources[ $url ] ? new WP_Error( 'background_fixture_rejected', 'Owned rejected stylesheet.' )
			: [ 'headers' => [], 'body' => $extra_sources[ $url ], 'response' => [ 'code' => 200, 'message' => 'OK' ], 'cookies' => [] ];
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
	$check( ! empty( $request_args[0][1]['reject_unsafe_urls'] ), 'native stylesheet fetch uses WordPress safe URL and redirect policy' );
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
	$check( $container->get( 'lazyload_css_context' )->is_allowed(), 'native background context becomes eligible again after request-local option restoration' );
	// Real local files, including a relative import, still use filesystem delivery.
	$local_path = $temporary . '/local.css';
	file_put_contents( $temporary . '/import.css', '.imported{color:orange}' );
	file_put_contents( $local_path, '@import "import.css";.local{background-image:url(https://background-fixture.invalid/local.png);color:blue}' );
	$local_url = content_url( substr( $normalize( $local_path ), strlen( $normalize( WP_CONTENT_DIR ) ) ) );
	$local_html = '<html><head><link rel="stylesheet" href="' . $local_url . '"></head></html>';
	$local_output = apply_filters( 'noobrocket_buffer', $local_html );
	$local_asset = WP_CONTENT_DIR . substr( wp_parse_url( $stylesheet( $local_output ), PHP_URL_PATH ), strlen( wp_parse_url( WP_CONTENT_URL, PHP_URL_PATH ) ) );
	$check( $stylesheet( $local_output ) !== $local_url && $requests === 2, 'readable local stylesheet publishes without HTTP' );
	$check( strpos( file_get_contents( $local_asset ), 'color:orange' ) !== false && strpos( file_get_contents( $local_asset ), 'color:blue' ) !== false, 'native local import and source rules survive stylesheet preparation' );
	// A readable PHP source must be requested by URL, with the original HTML-decoded query.
	$php_path = $temporary . '/dynamic.PHP';
	file_put_contents( $php_path, '<?php /* PRIVATE_PHP_SOURCE */ echo "dynamic CSS";' );
	$php_url = content_url( substr( $normalize( $php_path ), strlen( $normalize( WP_CONTENT_DIR ) ) ) ) . '?theme=dark&size=2';
	$extra_sources[ $php_url ] = str_replace( 'color:red', 'color:purple', $source_css );
	$php_html = '<html><head><link rel="stylesheet" href="' . htmlspecialchars( $php_url, ENT_QUOTES, 'UTF-8' ) . '"></head></html>';
	$php_output = apply_filters( 'noobrocket_buffer', $php_html );
	$php_asset = WP_CONTENT_DIR . substr( wp_parse_url( $stylesheet( $php_output ), PHP_URL_PATH ), strlen( wp_parse_url( WP_CONTENT_URL, PHP_URL_PATH ) ) );
	$check( $stylesheet( $php_output ) !== $php_url && $request_args[2][0] === $php_url && ! empty( $request_args[2][1]['reject_unsafe_urls'] ), 'native readable PHP stylesheet uses its query-dependent safe URL' );
	$check( strpos( file_get_contents( $php_asset ), 'color:purple' ) !== false && strpos( file_get_contents( $php_asset ), 'PRIVATE_PHP_SOURCE' ) === false, 'published PHP stylesheet contains response CSS rather than executable source' );
	$relative_url = wp_parse_url( $php_url, PHP_URL_PATH ) . '?theme=relative&size=3';
	$absolute_relative_url = WP_Http::make_absolute_url( $relative_url, home_url( '/' ) );
	$extra_sources[ $absolute_relative_url ] = $source_css;
	$relative_html = '<html><head><link rel="stylesheet" href="' . htmlspecialchars( $relative_url, ENT_QUOTES, 'UTF-8' ) . '"></head></html>';
	$check( $stylesheet( apply_filters( 'noobrocket_buffer', $relative_html ) ) !== $relative_url && $request_args[3][0] === $absolute_relative_url, 'native root-relative PHP URL retains query and configured origin' );
	$cdn_url = '//192.0.2.8/styles/theme.css?ver=7&theme=light';
	$absolute_cdn_url = WP_Http::make_absolute_url( $cdn_url, home_url( '/' ) );
	$extra_sources[ $absolute_cdn_url ] = $source_css;
	$allow_cdn = static function ( $external, $host, $url ) use ( $absolute_cdn_url ) { return $external || $url === $absolute_cdn_url; };
	add_filter( 'http_request_host_is_external', $allow_cdn, 10, 3 );
	try {
		$cdn_html = '<html><head><link rel="stylesheet" href="' . htmlspecialchars( $cdn_url, ENT_QUOTES, 'UTF-8' ) . '"></head></html>';
		$check( $stylesheet( apply_filters( 'noobrocket_buffer', $cdn_html ) ) !== $cdn_url && $request_args[4][0] === $absolute_cdn_url, 'native protocol-relative CDN stylesheet uses the configured scheme' );
	} finally { remove_filter( 'http_request_host_is_external', $allow_cdn ); }
	$attempted_mapping = false;
	$forbid_mapping = static function () use ( &$attempted_mapping ) { $attempted_mapping = true; throw new RuntimeException( 'Unsupported source reached filesystem mapping.' ); };
	add_filter( 'noobrocket_asset_url', $forbid_mapping );
	try {
		$old_request_uri = $_SERVER['REQUEST_URI'];
		$_SERVER['REQUEST_URI'] = '/products/item/';
		foreach ( [ 'file://' . $normalize( $local_path ), 'ftp://192.0.2.8/private.css', 'data:text/css,.private{color:red}', 'C:/private/styles.css', ltrim( wp_parse_url( $local_url, PHP_URL_PATH ), '/' ), 'dynamic.php?theme=nested', '../styles.php?theme=nested' ] as $unsupported ) {
			$unsupported_html = '<html><head><link rel="stylesheet" href="' . $unsupported . '"></head></html>';
			$unsupported_output = $owner->create_lazy_css_files( [ 'html' => $unsupported_html, 'css_files' => [ $unsupported ] ] );
			$check( $unsupported_html === $unsupported_output['html'] && ! $attempted_mapping, 'native unsupported scheme stops before filesystem mapping' );
		}
	} finally { $_SERVER['REQUEST_URI'] = $old_request_uri; remove_filter( 'noobrocket_asset_url', $forbid_mapping ); }
	$unsupported = 'file://' . $normalize( $local_path );
	$unsupported_html = '<html><head><link rel="stylesheet" href="' . $unsupported . '"></head></html>';
	$unsupported_output = apply_filters( 'noobrocket_buffer', $unsupported_html );
	$check( $stylesheet( $unsupported_output ) === $unsupported && strpos( $unsupported_output, 'const noobrocket_pairs = [];' ) !== false, 'unsupported file wrapper retains original stylesheet without lazy publication' );
	$private_url = 'http://127.0.0.2/private.css';
	$check( false === wp_http_validate_url( $private_url ), 'native WordPress rejects an unapproved loopback destination' );
	$extra_sources[ $private_url ] = false;
	$private_html = '<html><head><link rel="stylesheet" href="' . $private_url . '"></head></html>';
	$private_output = apply_filters( 'noobrocket_buffer', $private_html );
	$check( $stylesheet( $private_output ) === $private_url && strpos( $private_output, 'const noobrocket_pairs = [];' ) !== false && end( $request_args )[0] === $private_url && ! empty( end( $request_args )[1]['reject_unsafe_urls'] ), 'rejected private HTTP resource keeps original delivery under safe request policy' );
	$redirect_rejected = false;
	try { WP_Http::validate_redirects( $private_url ); } catch ( Exception $error ) { $redirect_rejected = true; }
	$check( $redirect_rejected, 'native safe redirect callback rejects an unapproved private destination without transport' );
	WP_Http::validate_redirects( $source_url . '?ver=7&theme=light' );
	$check( true, 'native safe redirect callback accepts the WordPress-approved source' );
	// WordPress owns development-origin exceptions, including the configured home port.
	$dev_home = 'http://127.0.0.2:8891';
	$dev_home_option = static function () use ( $dev_home ) { return $dev_home; };
	add_filter( 'pre_option_home', $dev_home_option );
	try {
		$dev_url = $dev_home . '/dynamic.php?theme=dev';
		$check( $dev_url === wp_http_validate_url( $dev_url ), 'native configured home origin permits its development port' );
		$extra_sources[ $dev_url ] = $source_css;
		$dev_html = '<html><head><link rel="stylesheet" href="' . $dev_url . '"></head></html>';
		$check( $stylesheet( apply_filters( 'noobrocket_buffer', $dev_html ) ) !== $dev_url && end( $request_args )[0] === $dev_url && ! empty( end( $request_args )[1]['reject_unsafe_urls'] ), 'WordPress-approved development PHP origin remains optimizable' );
	} finally { remove_filter( 'pre_option_home', $dev_home_option ); }
	$dev_url = 'http://127.0.0.3:8080/filtered.css';
	$allow_dev = static function ( $external, $host, $url ) use ( &$dev_url ) { return $external || $url === $dev_url; };
	add_filter( 'http_request_host_is_external', $allow_dev, 10, 3 );
	try {
		$check( $dev_url === wp_http_validate_url( $dev_url ), 'native host exception permits an approved private development origin' );
		$extra_sources[ $dev_url ] = $source_css;
		$dev_html = '<html><head><link rel="stylesheet" href="' . $dev_url . '"></head></html>';
		$check( $stylesheet( apply_filters( 'noobrocket_buffer', $dev_html ) ) !== $dev_url && ! empty( end( $request_args )[1]['reject_unsafe_urls'] ), 'approved host exception keeps safe stylesheet fetching' );
		if ( version_compare( $GLOBALS['wp_version'], '5.9', '>=' ) ) {
			$dev_url = 'http://127.0.0.3:8892/filtered.css';
			$allow_dev_port = static function ( $ports ) { $ports[] = 8892; return $ports; };
			add_filter( 'http_allowed_safe_ports', $allow_dev_port );
			try {
				$extra_sources[ $dev_url ] = $source_css;
				$check( $dev_url === wp_http_validate_url( $dev_url ), 'native safe-port filter permits its approved development port' );
				$dev_html = '<html><head><link rel="stylesheet" href="' . $dev_url . '"></head></html>';
				$check( $stylesheet( apply_filters( 'noobrocket_buffer', $dev_html ) ) !== $dev_url && ! empty( end( $request_args )[1]['reject_unsafe_urls'] ), 'approved custom port remains optimizable through safe HTTP' );
			} finally { remove_filter( 'http_allowed_safe_ports', $allow_dev_port ); }
		}
	} finally { remove_filter( 'http_request_host_is_external', $allow_dev ); }
	$owner->clear_generated_css();
	$check( ! is_dir( $cache->get_root_path() ), 'production purge owner removes its generated background cache' );
	$check( $blocked === 0, 'native pipeline attempted no unowned HTTP requests' );
	echo "PASS: $checks native stylesheet-background assertions; registered pipeline, real files, $requests mocked fetches, zero unowned HTTP.\n";
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
