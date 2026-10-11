<?php
/** Isolated native resource-fallback/CSP/beacon regressions; no WordPress bootstrap or HTTP. */
declare(strict_types=1);
namespace NoobRocket\Logger { class Logger { public static function debug( ...$args ): void {} } }
namespace NoobRocket\Engine\Media\Fonts {
	function rename( $from, $to ) { return $GLOBALS['font_filesystem']->move( $from, $to ); }
}
namespace {
	$checks = 0;
	$requests = [];
	$remote_code = 200;
	$remote_body = 'body';
	$transients = [];
	$writes = [];
	$runtime_version = '';
	function resource_check( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); } ++$GLOBALS['checks']; }
	function noobrocket_get_constant( $key, $default = null ) { return defined( $key ) ? constant( $key ) : $default; }
	function get_current_blog_id() { return 1; }
	function noobrocket_get_filesystem_perms() { return 0644; }
	function wp_generate_uuid4() { return bin2hex( random_bytes( 16 ) ); }
	function noobrocket_mkdir_p() { return true; }
	function get_transient( $key ) { return $GLOBALS['transients'][ $key ] ?? false; }
	function set_transient( $key, $value, $expires = 0 ) { $GLOBALS['transients'][ $key ] = $value; return true; }
	function wp_safe_remote_get( $url, $args ) {
		$GLOBALS['requests'][] = [ $url, $args ];
		if ( false !== strpos( $url, 'googleapis' ) ) { return [ 'response' => [ 'code' => $GLOBALS['remote_code'] ], 'body' => '@font-face{src:url(https://fonts.gstatic.com/test.woff2)}' ]; }
		return [ 'response' => [ 'code' => $GLOBALS['remote_code'] ], 'body' => $GLOBALS['remote_body'] . $url ];
	}
	function wp_remote_retrieve_response_code( $response ) { return $response['response']['code'] ?? 0; }
	function wp_remote_retrieve_body( $response ) { return $response['body'] ?? ''; }
	function is_wp_error( $value ) { return $value instanceof WP_Error; }
	function wp_parse_url( $url, $part = -1 ) { return parse_url( $url, $part ); }
	function get_noobrocket_parse_url( $url ) { return parse_url( $url ); }
	function site_url() { return 'https://site.test/'; }
	function get_noobrocket_i18n_uri() { return []; }
	function noobrocket_put_content( $path, $bytes ) { $GLOBALS['writes'][ $path ] = $bytes; return true; }
	function content_url( $path ) { return 'https://site.test/wp-content' . $path; }
	function wpm_apply_filters_typed( $type, $hook, $value ) { return $value; }
	function wpm_apply_filters_typesafe( $hook, $value ) { return 'noobrocket_delay_js_version_js_script' === $hook ? $GLOBALS['runtime_version'] : $value; }
	function noobrocket_bypass() { return false; }
	function is_noobrocket_post_excluded_option() { return false; }
	function check_ajax_referer() {}
	function wp_unslash( $value ) { return $value; }
	function esc_url_raw( $value ) { return $value; }
	function untrailingslashit( $value ) { return rtrim( $value, '/' ); }
	function sanitize_text_field( $value ) { return $value; }
	function wp_json_encode( $value ) { return json_encode( $value ); }
	function current_time() { return '2026-10-09 00:00:00'; }
	class WP_Error {}
	class ResourceFilesystem {
		public $fail_font = true;
		public $missing_runtime = false;
		public $fail_move = false;
		public $files = [];
		public function exists( $path ) { return ! $this->missing_runtime && ( false !== strpos( $path, 'lazyload-scripts' ) || isset( $this->files[ $path ] ) ); }
		public function is_readable( $path ) { return isset( $this->files[ $path ] ) || isset( $GLOBALS['writes'][ $path ] ); }
		public function size( $path ) { return strlen( $this->files[ $path ] ?? '' ); }
		public function get_contents( $path ) { return $this->files[ $path ] ?? $GLOBALS['writes'][ $path ] ?? ( $this->missing_runtime ? false : 'window.runtime=true;' ); }
		public function put_contents( $path, $bytes ) { if ( $this->fail_font && false !== strpos( $path, '.woff2.' ) ) { $this->files[ $path ] = 'partial'; return false; } $this->files[ $path ] = $bytes; return true; }
		public function move( $from, $to ) { if ( $this->fail_move || isset( $this->files[ $to ] ) ) { return false; } $this->files[ $to ] = $this->files[ $from ]; unset( $this->files[ $from ] ); return true; }
		public function delete( $path ) { unset( $this->files[ $path ] ); return true; }
	}
	class DelayOptions { public function get( $key, $default = null ) { return 'delay_js' === $key ? 1 : $default; } }
	class DelayLists { public function get_lists() { return (object) [ 'delay_js_exclusions' => [] ]; } }
	class HintContext { public function is_allowed() { return true; } }
	class HintQuery { public $saved; public function get_row() { return false; } public function add_item( $item ) { $this->saved = $item; return true; } }

	$root = dirname( __DIR__ ) . '/noobrocket/inc/';
	define( 'WP_CONTENT_DIR', '/fixture/wp-content' );
	define( 'NOOBROCKET_CACHE_ROOT_PATH', '/fixture/wp-content/cache/noobrocket/' );
	define( 'NOOBROCKET_CACHE_ROOT_URL', 'https://site.test/wp-content/cache/noobrocket/' );
	define( 'NOOBROCKET_PATH', dirname( __DIR__ ) . '/noobrocket/' );
	require $root . 'classes/event-management/subscriber-interface.php';
	require $root . 'Engine/Common/AbstractFileSystem.php';
	require $root . 'Engine/Media/Fonts/Filesystem.php';
	require $root . 'Engine/Optimization/AssetsLocalCache.php';
	require $root . 'Engine/Optimization/RegexTrait.php';
	require $root . 'Engine/Support/CommentTrait.php';
	require $root . 'Engine/Optimization/DelayJS/HTML.php';
	require $root . 'Engine/Optimization/DelayJS/Subscriber.php';
	require $root . 'Engine/Optimization/UrlTrait.php';
	class ResourceUrlOwner {
		use NoobRocket\Engine\Optimization\UrlTrait;
		public function external( $url ) { return $this->is_external_file( $url ); }
	}
	require $root . 'Engine/Common/PerformanceHints/AJAX/AJAXControllerTrait.php';
	require $root . 'Engine/Common/PerformanceHints/AJAX/ControllerInterface.php';
	require $root . 'Engine/Optimization/LazyRenderContent/AJAX/Controller.php';

	$filesystem = new ResourceFilesystem();
	$font_filesystem = $filesystem;
	$fonts = new NoobRocket\Engine\Media\Fonts\Filesystem( $filesystem );
	$font_url = 'https://fonts.googleapis.com/css2?family=Test';
	resource_check( ! $fonts->write_font_css( $font_url, 'google-fonts' ), 'failed font write retains original provider delivery' );
	resource_check( ! $filesystem->files, 'incomplete font family never publishes CSS containing missing local font' );
	$before = count( $requests );
	resource_check( ! $fonts->write_font_css( $font_url, 'google-fonts' ) && $before === count( $requests ), 'failure cooldown avoids repeated remote work' );
	$transients = [];
	$filesystem->fail_font = false;
	resource_check( $fonts->write_font_css( $font_url, 'google-fonts' ), 'later successful preparation recovers after cooldown' );
	$css = implode( '', array_filter( $filesystem->files, static function ( $bytes, $path ) { return '.css' === substr( $path, -4 ); }, ARRAY_FILTER_USE_BOTH ) );
	resource_check( false !== strpos( $css, 'https://site.test/wp-content/cache/noobrocket/' ) && 2 === count( $filesystem->files ), 'published CSS references a readable populated local font' );
	$transients = [];
	$filesystem->fail_move = true;
	resource_check( ! $fonts->write_font_css( $font_url . '&publish=fail', 'google-fonts' ) && 2 === count( $filesystem->files ), 'failed final rename keeps original CSS delivery and removes temporary files' );
	$filesystem->fail_move = false;
	foreach ( $requests as [ $url, $args ] ) { resource_check( $args['timeout'] > 0 && $args['timeout'] <= 3 && isset( $args['limit_response_size'] ), 'cold requests have timeout and byte bounds' ); }
	$deadline = new ReflectionProperty( $fonts, 'download_deadline' );
	$deadline->setAccessible( true );
	$deadline->setValue( $fonts, microtime( true ) - 1 );
	$before = count( $requests );
	resource_check( $fonts->write_font_css( $font_url . '&new=1', 'google-fonts' ) && $before + 1 === count( $requests ), 'a new background preparation receives a fresh bounded budget and reuses ready font bytes' );
	foreach ( [ 404, 429, 503 ] as $code ) {
		$remote_code = $code;
		$cache = new NoobRocket\Engine\Optimization\AssetsLocalCache( '/asset-fixture/', $filesystem );
		$before = count( $writes );
		resource_check( '' === $cache->get_content( 'https://cdn.test/style.css?status=' . $code ) && $before === count( $writes ), 'HTTP ' . $code . ' body never enters optimization cache' );
	}
	$remote_code = 200;
	$url_owner = new ResourceUrlOwner();
	resource_check( ! $url_owner->external( 'https://site.test/asset.css' ) && ! $url_owner->external( '/asset.css' ), 'same-site and relative resources keep native local handling' );
	resource_check( $url_owner->external( 'https://site.test.evil/asset.css' ) && $url_owner->external( 'https://cdn.test/asset.css?domain=site.test' ), 'host suffix and embedded query strings never imply local ownership' );
	$cache = new NoobRocket\Engine\Optimization\AssetsLocalCache( '/asset-fixture/', $filesystem );
	$a = 'https://cdn.test/style.css?theme=a';
	$b = 'https://cdn.test/style.css?theme=b';
	resource_check( $cache->get_filepath( $a ) !== $cache->get_filepath( $b ), 'query-dependent remote assets have distinct cache identities' );
	resource_check( $cache->get_content( $a ) !== $cache->get_content( $b ), 'query-dependent assets retain their original fetch bytes' );
	$before = count( $requests );
	resource_check( $cache->get_content( $a ) && $before === count( $requests ), 'usable cached remote resource is reused without HTTP' );
	$remote_body = str_repeat( 'x', 2 * 1024 * 1024 );
	resource_check( '' === $cache->get_content( 'https://cdn.test/too-big.css' ), 'possibly truncated response is rejected' );

	$html = ( new ReflectionClass( NoobRocket\Engine\Optimization\DelayJS\HTML::class ) )->newInstanceWithoutConstructor();
	foreach ( [ 'options' => new DelayOptions(), 'data_manager' => new DelayLists() ] as $property => $value ) {
		$reflection = new ReflectionProperty( $html, $property );
		$reflection->setAccessible( true );
		$reflection->setValue( $html, $value );
	}
	$subscriber = new NoobRocket\Engine\Optimization\DelayJS\Subscriber( $html, $filesystem );
	$page = '<html><head><meta http-equiv="Content-Security-Policy" content="script-src &apos;self&apos; &apos;nonce-test&apos;"><script nonce="test" src="/app.js"></script></head><body></body></html>';
	resource_check( $page === $subscriber->add_delay_js_script( $subscriber->delay_js( $page ) ), 'enforcing meta CSP leaves original scripts and runtime untouched' );
	$report_only = str_replace( 'Content-Security-Policy"', 'Content-Security-Policy-Report-Only"', $page );
	$delayed = $subscriber->add_delay_js_script( $subscriber->delay_js( $report_only ) );
	resource_check( false !== strpos( $delayed, 'data-noobrocket-src="/app.js"' ) && false !== strpos( $delayed, 'window.runtime=true;' ), 'report-only CSP retains supported delay delivery' );
	foreach ( [
		'<html><body><script src="/app.js"></script></body></html>',
		'<html><header>Navigation</header><body><script src="/app.js"></script></body></html>',
		'<html><!-- <head> --><body><script src="/app.js"></script></body></html>',
		'<html><body><script>window.tagLikeHead = "<head>";</script><script src="/app.js"></script></body></html>',
		'<html><body><textarea><head></textarea><script src="/app.js"></script></body></html>',
		'<html><body><div data-note="<head>"></div><script src="/app.js"></script></body></html>',
		'<html><template><template></template><head></head></template><body><script src="/app.js"></script></body></html>',
		'<html><body><script src="/app.js"></script><plaintext><head></body></html>',
		'<html><head-panel>Custom element</head-panel><body><script src="/app.js"></script></body></html>',
		'<html><body><script src="/app.js"></script><div data-note="unterminated <head> tail',
		"<html><body><script src=\"/app.js\"></script><div data-note='unterminated <head> tail",
	] as $headless ) {
		resource_check( $headless === $subscriber->add_delay_js_script( $subscriber->delay_js( $headless ) ), 'missing real head retains executable originals without an unavailable runtime' );
	}
	$uppercase = '<HTML><HEAD data-note="quoted > attribute"><script src="/app.js"></script></HEAD><BODY></BODY></HTML>';
	$transformed = $subscriber->add_delay_js_script( $subscriber->delay_js( $uppercase ) );
	resource_check( false !== strpos( $transformed, '<HEAD data-note="quoted > attribute"><script>' ) && false !== strpos( $transformed, 'data-noobrocket-src="/app.js"' ), 'delay supports uppercase head and respects quoted tag contents' );
	$runtime_path = NOOBROCKET_PATH . 'assets/js/lazyload-scripts.min.js';
	$runtime_bytes = 'window.runtimeDollar="$0:$1";window.runtimeSlash="\\\\value";';
	$filesystem->files[ $runtime_path ] = $runtime_bytes;
	resource_check( false !== strpos( $subscriber->add_delay_js_script( $subscriber->delay_js( $report_only ) ), '<script>' . $runtime_bytes . '</script>' ), 'runtime insertion preserves replacement-like dollar/backslash bytes verbatim' );
	unset( $filesystem->files[ $runtime_path ] );
	foreach ( [
		'<script src=/app.js></script>' => 'data-noobrocket-src=/app.js',
		'<script src=/app.js?a=1&amp;b=2></script>' => 'data-noobrocket-src=/app.js?a=1&amp;b=2',
		'<script type=module src="/app.js"></script>' => 'data-noobrocket-type=module',
		'<script type=mod&#117;le src=/app.js></script>' => 'data-noobrocket-type=mod&#117;le',
		'<script data-type="application/ld+json" data-src="/original.js" src=/app.js></script>' => 'data-noobrocket-src=/app.js',
		'<script data-note="type=application/ld+json src=/wrong.js >" src="/app.js?a=1&amp;b=2"></script>' => 'data-noobrocket-src="/app.js?a=1&amp;b=2"',
	] as $original => $expected ) {
		$transformed = $subscriber->delay_js( '<html><head>' . $original . '</head></html>' );
		resource_check( false !== strpos( $transformed, $expected ), 'delay handles real quoted/unquoted attributes without changing assignment bytes: ' . $original );
	}
	foreach ( [
		'<script type=application/ld+json>{"kind":"fixture"}</script>',
		'<script type="application/ld+json"/ id="fixture-data">{"kind":"fixture"}</script>',
		'<script type="application/ld+json"id="fixture-data">{"kind":"fixture"}</script>',
		'<script id="fixture-data"type="application/ld+json">{"kind":"fixture"}</script>',
		'<script type="application/ld+json" type="text/javascript">{"kind":"fixture"}</script>',
		'<script type="text/javascript" type="application/ld+json">{"kind":"fixture"}</script>',
		'<script type="application&#47;ld+json">{"kind":"fixture"}</script>',
	] as $original ) {
		resource_check( false !== strpos( $subscriber->delay_js( '<html><head>' . $original . '</head></html>' ), $original ), 'non-JavaScript types retain their original tag and contents' );
	}
	$transformed = $subscriber->delay_js( '<html><head><script type src=/app.js></script></head></html>' );
	resource_check( false !== strpos( $transformed, 'data-noobrocket-src=/app.js' ) && false === strpos( $transformed, 'data-noobrocket-type' ), 'bare empty type does not leave a duplicate executable type attribute' );
	foreach ( [
		'<script type="module"/ src="/app.js"></script>',
		'<script type="module"src="/app.js"></script>',
		'<script src="/app.js"type="module"></script>',
	] as $original ) {
		$transformed = $subscriber->delay_js( '<html><head>' . $original . '</head></html>' );
		resource_check( false !== strpos( $transformed, 'data-noobrocket-type="module"' ) && false !== strpos( $transformed, 'data-noobrocket-src="/app.js"' ), 'module attributes keep their native type and source across slash/adjacent boundaries' );
	}
	$original = '<script data-note="type=application/ld+json src=/wrong.js >"src="/app.js?a=1&amp;b=2" id="exact-fixture"></script>';
	$transformed = $subscriber->delay_js( '<html><head>' . $original . '</head></html>' );
	resource_check( false !== strpos( $transformed, 'data-note="type=application/ld+json src=/wrong.js >"data-noobrocket-src="/app.js?a=1&amp;b=2" id="exact-fixture"' ), 'quoted noise and original value/adjacent bytes survive source renaming' );
	$runtime_version = '-empty';
	$filesystem->files[ NOOBROCKET_PATH . 'assets/js/lazyload-scripts-empty.min.js' ] = '';
	resource_check( $report_only === $subscriber->delay_js( $report_only ), 'unusable selected runtime preserves original scripts even when default runtime exists' );
	$runtime_version = '';
	$filesystem->missing_runtime = true;
	resource_check( $report_only === $subscriber->delay_js( $report_only ), 'missing runtime preserves scripts before making their type inert' );

	$query = new HintQuery();
	$controller = ( new ReflectionClass( NoobRocket\Engine\Optimization\LazyRenderContent\AJAX\Controller::class ) )->newInstanceWithoutConstructor();
	foreach ( [ 'query' => $query, 'context' => new HintContext() ] as $property => $value ) {
		$reflection = new ReflectionProperty( $controller, $property );
		$reflection->setAccessible( true );
		$reflection->setValue( $controller, $value );
	}
	$hashes = array_map( 'md5', range( 1, 40 ) );
	$_POST = [ 'url' => 'https://site.test/article/', 'is_mobile' => 'false', 'results' => json_encode( [ 'lrc' => $hashes ] ), 'status' => 'success' ];
	$controller->add_data();
	resource_check( array_slice( $hashes, 0, 20 ) === json_decode( $query->saved['below_the_fold'], true ), 'native LRC stores only its declared first twenty valid entries' );
	$_POST['results'] = json_encode( [ 'lrc' => array_merge( [ 'bad', [], $hashes[0], $hashes[0] ], $hashes ) ] );
	$controller->add_data();
	resource_check( array_slice( $hashes, 0, 20 ) === json_decode( $query->saved['below_the_fold'], true ), 'duplicates/malformed entries do not consume valid-entry cap' );
	$query->saved = null;
	$_POST['results'] = str_repeat( 'x', 65537 );
	$controller->add_data();
	resource_check( null === $query->saved, 'oversized raw beacon is rejected before decoding or storage' );
	$_POST['results'] = [ 'bad' ];
	$controller->add_data();
	resource_check( null === $query->saved, 'non-string raw beacon is rejected safely' );
	$_POST['results'] = json_encode( [ 'lrc' => array_fill( 0, 1001, $hashes[0] ) ] );
	$controller->add_data();
	resource_check( null === $query->saved, 'oversized hash collection does not enter iteration/storage' );
	echo 'PASS: optimizer resource fallbacks (', $checks, " assertions; isolated native owners)\n";
}
