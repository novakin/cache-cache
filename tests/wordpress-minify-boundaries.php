<?php
/** Actual optimization owners with owned temporary files and modeled HTTP; no site bootstrap. */
declare(strict_types=1);
namespace NoobRocket\Logger { class Logger { public static function debug( ...$args ): void {} public static function error( ...$args ): void {} } }
namespace {
	if ( ! extension_loaded( 'mbstring' ) ) { throw new \RuntimeException( 'PHP mbstring is required by the shipped CSS path converter.' ); }
	$fixture = sys_get_temp_dir() . '/noobrocket-minify-boundaries-' . bin2hex( random_bytes( 6 ) );
	mkdir( $fixture );
	define( 'NOOBROCKET_MINIFY_CACHE_PATH', $fixture . '/min/' );
	define( 'NOOBROCKET_MINIFY_CACHE_URL', 'https://site.test/min/' );
	$checks = 0;
	$cdn_hosts = [];
	$requests = [];
	$request_kinds = [];
	$dynamic_hook = 'script_loader_src';
	$dynamic_filters = [];
	$bypass = false;
	$logged_in = false;
	$pagenow = 'index.php';
	$remote_status = 200;
	$remote_bytes = 'window.nativeOrder = [...(window.nativeOrder || []), "remote"]; window.nativeRemoteRuns = (window.nativeRemoteRuns || 0) + 1;';
	function minify_check( $condition, $message ) { if ( ! $condition ) { throw new \RuntimeException( 'FAIL: ' . $message ); } ++$GLOBALS['checks']; }
	function apply_filters( $hook, $value, ...$args ) { return 'noobrocket_cdn_hosts' === $hook ? $GLOBALS['cdn_hosts'] : ( $GLOBALS['dynamic_filters'][ $hook ] ?? $value ); }
	function wp_parse_url( $url, $part = -1 ) { return parse_url( $url, $part ); }
	function get_noobrocket_parse_url( $url ) { return parse_url( $url ); }
	function content_url() { return 'https://site.test/wp-content'; }
	function site_url( $path = '' ) { return 'https://site.test/' . ltrim( $path, '/' ); }
	function wp_http_validate_url( $url ) { return filter_var( $url, FILTER_VALIDATE_URL ); }
	function get_noobrocket_i18n_uri() { return []; }
	function get_current_blog_id() { return 1; }
	function create_noobrocket_uniqid() { return 'fixture'; }
	function noobrocket_get_constant( $key, $default = null ) { return defined( $key ) ? constant( $key ) : $default; }
	function noobrocket_realpath( $path ) { return $path; }
	function noobrocket_add_url_protocol( $url ) { return 0 === strpos( $url, '//' ) ? 'https:' . $url : $url; }
	function noobrocket_url_to_path( $url, $zones = [] ) { return $GLOBALS['fixture'] . '/' . basename( parse_url( $url, PHP_URL_PATH ) ); }
	function noobrocket_extract_url_component( $url, $part ) { return parse_url( $url, $part ); }
	function noobrocket_bypass() { return $GLOBALS['bypass']; }
	function is_user_logged_in() { return $GLOBALS['logged_in']; }
	function current_filter() { return $GLOBALS['dynamic_hook']; }
	function remove_query_arg( $key, $query ) { parse_str( $query, $args ); unset( $args[ $key ] ); return http_build_query( $args ); }
	function noobrocket_mkdir_p( $path ) { return is_dir( $path ) || mkdir( $path, 0777, true ); }
	function noobrocket_put_content( $path, $content ) { return false !== file_put_contents( $path, $content ); }
	function wp_safe_remote_get( $url, $args ) { $GLOBALS['requests'][] = $url; return 'transport' === $GLOBALS['remote_status'] ? new \WP_Error() : [ 'response' => [ 'code' => $GLOBALS['remote_status'] ], 'body' => $GLOBALS['remote_bytes'] ]; }
	function wp_remote_get( $url ) { $GLOBALS['requests'][] = $url; $GLOBALS['request_kinds'][] = 'remote'; return 'transport' === $GLOBALS['remote_status'] ? new \WP_Error() : [ 'response' => [ 'code' => $GLOBALS['remote_status'] ], 'body' => $GLOBALS['remote_bytes'] ]; }
	function wp_remote_retrieve_response_code( $response ) { return is_wp_error( $response ) ? '' : ( $response['response']['code'] ?? '' ); }
	function wp_remote_retrieve_body( $response ) { return is_wp_error( $response ) ? '' : ( $response['body'] ?? '' ); }
	class WP_Error {}
	function is_wp_error( $response ) { return $response instanceof \WP_Error; }
	function add_query_arg( $key, $value, $url ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . $key . '=' . $value; }
	function esc_url( $url ) { return $url; }
	function wp_scripts() { return $GLOBALS['wp_scripts']; }
	class MinifyFilesystem {
		public function exists( $path ) { return file_exists( $path ); }
		public function is_readable( $path ) { return is_readable( $path ); }
		public function get_contents( $path ) { return is_readable( $path ) ? file_get_contents( $path ) : ''; }
		public function mtime( $path ) { return filemtime( $path ); }
	}
	function noobrocket_direct_filesystem() { return $GLOBALS['filesystem']; }
	require dirname( __DIR__ ) . '/noobrocket/vendor/autoload.php';
	require dirname( __DIR__ ) . '/noobrocket/inc/classes/admin/class-options-data.php';
	class MinifyLists extends \NoobRocket\Engine\Optimization\DynamicLists\DynamicLists {
		public $templates = [];
		public function __construct() {}
		public function get_exclude_js_templates(): array { return $this->templates; }
	}
	class MinifyDefer extends \NoobRocket\Engine\Optimization\DeferJS\DeferJS {
		public function __construct() {}
		public function get_excluded(): array { return []; }
	}
	class MinifyOwner extends \NoobRocket\Engine\Optimization\Minify\JS\Minify {
		public function external( $url ) { return $this->is_external_file( $url ); }
	}
	class MinifyCSSOwner extends \NoobRocket\Engine\Optimization\Minify\CSS\Minify {
		public function import_external( $url ) { return $this->is_external_path( $url ); }
	}
	class ImportOriginOwner {
		use \NoobRocket\Engine\Optimization\CSSTrait;
		public function import_external( $url ) { return $this->is_external_path( $url ); }
		public function import_contents( $url ) { return $this->get_internal_file_contents( $url, $GLOBALS['fixture'] ); }
	}
	$filesystem = new MinifyFilesystem();
	$wp_scripts = (object) [ 'registered' => [], 'queue' => [] ];
	$options = new \NoobRocket\Admin\Options_Data( [ 'minify_js_key' => 'fixture', 'exclude_js' => [ '/keep\\.js', '/unused\\.js' ] ] );
	$lists = new MinifyLists();
	$cache = new \NoobRocket\Engine\Optimization\AssetsLocalCache( $fixture . '/', $filesystem );
	$minify = new MinifyOwner( $options, $cache, $lists );
	$new_combine = static function () use ( $options, $cache, $lists ) {
		return new \NoobRocket\Engine\Optimization\Minify\JS\Combine( $options, new \NoobRocket\Dependencies\Minify\JS(), $cache, new MinifyDefer(), $lists );
	};
	$document = static function ( $tags ) { return '<!doctype html><html><head>' . $tags . '</head><body></body></html>'; };
	$export = [ 'pages' => [], 'assets' => [], 'dynamic_resources' => [] ];
	try {
		foreach ( [ 'https://site.test.other/app.js', 'https://remote.test/site.test/app.js', '//site.test.other/app.js', 'https://remote.test/app.js?host=site.test' ] as $url ) {
			minify_check( $minify->external( $url ), 'foreign host remains external: ' . $url );
		}
		foreach ( [ 'https://site.test/app.js', '//site.test/app.js', 'https://SITE.TEST./app.js', '/app.js', 'app.js' ] as $url ) {
			minify_check( ! $minify->external( $url ), 'local URL retains local classification: ' . $url );
		}
		$cdn_hosts = [ 'cdn.test' ];
		minify_check( ! $minify->external( 'https://cdn.test/app.js' ) && $minify->external( 'https://cdn.test.other/app.js' ), 'explicit CDN host keeps exact host semantics' );
		$cdn_hosts = [];
		$css = new MinifyCSSOwner( $options, $cache );
		$imports = new ImportOriginOwner();
		$dimensions = new \NoobRocket\Engine\Media\ImageDimensions\ImageDimensions( $options, $filesystem );
		$image_external = new \ReflectionMethod( $dimensions, 'is_external_file' );
		$image_external->setAccessible( true );
		foreach ( [ 'https://site.test.other/asset.css', '//site.test.other/asset.css', 'https://remote.test/site.test/asset.css', 'https://site.test@remote.test/asset.css' ] as $url ) {
			minify_check( $css->import_external( $url ) && $imports->import_external( $url ) && $image_external->invoke( $dimensions, $url ), 'CSS imports and dimensions keep foreign origin external: ' . $url );
		}
		foreach ( [ '/asset.css', '//site.test/asset.css', 'https://SITE.TEST./asset.css' ] as $url ) {
			minify_check( ! $css->import_external( $url ) && ! $imports->import_external( $url ) && ! $image_external->invoke( $dimensions, $url ), 'CSS imports and dimensions retain local URL semantics: ' . $url );
		}
		$cdn_hosts = [ 'cdn.test' ];
		minify_check( ! $css->import_external( '//cdn.test/asset.css' ) && ! $imports->import_external( '//cdn.test/asset.css' ) && ! $image_external->invoke( $dimensions, '//cdn.test/image.png' ), 'CSS imports and dimensions honor explicit CDN host' );
		$cdn_hosts = [];
		minify_check( [ 'https://remote.test/site.test/import.css', false ] === $imports->import_contents( 'https://remote.test/site.test/import.css' ), 'foreign CSS import never reads a same-path local stylesheet' );
		foreach ( [
			'module-double' => '<script type="module" src="https://remote.test/modules/app.js"></script>',
			'module-single' => "<script type='module' src='https://remote.test/modules/app.js'></script>",
			'module-unquoted' => '<script type=module src="https://remote.test/modules/app.js"></script>',
			'module-entity' => '<script type=mod&#117;le src="https://remote.test/modules/app.js"></script>',
			'optout' => '<script data-no-minify src="https://remote.test/keep.js"></script>',
			'configured-exclusion' => '<script src="https://remote.test/keep.js"></script>',
		] as $name => $tag ) {
			$page = $document( $tag );
			foreach ( [ 'minify' => $minify, 'combine' => $new_combine() ] as $mode => $owner ) {
				$before = count( $requests );
				$output = $owner->optimize( $page );
				minify_check( false !== strpos( $output, $tag ) && $before === count( $requests ), $name . ': ' . $mode . ' retains original tag without fetching its content' );
				$export['pages'][ $name . '-' . $mode ] = $output;
			}
		}
		$integrity = 'sha384-' . base64_encode( hash( 'sha384', $remote_bytes, true ) );
		$export['assets']['https://remote.test/protected.js'] = $remote_bytes;
		foreach ( [
			'protected-nonce' => '<script nonce="fixture" src="https://remote.test/protected.js"></script>',
			'protected-nonce-unquoted' => '<script NONCE=fixture src="https://remote.test/protected.js"></script>',
			'protected-nonce-padded' => '<script NONCE=fixture= src="https://remote.test/protected.js"></script>',
			'protected-integrity' => '<script integrity="' . $integrity . '" crossorigin=anonymous src="https://remote.test/protected.js"></script>',
			'protected-integrity-single' => "<script integrity='" . $integrity . "' crossorigin=anonymous src='https://remote.test/protected.js'></script>",
			'protected-integrity-unquoted' => '<script INTEGRITY=' . $integrity . ' crossorigin=anonymous src="https://remote.test/protected.js"></script>',
			'protected-integrity-padded' => '<script INTEGRITY=sha256-' . base64_encode( hash( 'sha256', $remote_bytes, true ) ) . ' crossorigin=anonymous src="https://remote.test/protected.js"></script>',
		] as $name => $tag ) {
			$nonce = false !== strpos( $name, 'nonce-padded' ) ? 'fixture=' : 'fixture';
			$meta = false !== strpos( $name, 'nonce' ) ? '<meta http-equiv="Content-Security-Policy" content="script-src &#39;nonce-' . $nonce . '&#39;">' : '';
			foreach ( [ 'minify' => $minify, 'combine' => $new_combine() ] as $mode => $owner ) {
				$before = count( $requests );
				$output = $owner->optimize( $document( $meta . $tag ) );
				minify_check( false !== strpos( $output, $tag ) && $before === count( $requests ), $name . ': ' . $mode . ' preserves original security attributes and URL without fetching' );
				$export['pages'][ $name . '-' . $mode ] = $output;
			}
		}
		foreach ( [
			'inline-nonce' => '<script nonce="fixture">window.nativeInlineRuns = (window.nativeInlineRuns || 0) + 1;</script>',
			'inline-nonce-unquoted' => '<script NONCE=fixture>window.nativeInlineRuns = (window.nativeInlineRuns || 0) + 1;</script>',
			'inline-module' => '<script TYPE=mod&#117;le>import { value } from "https://remote.test/modules/dep.js"; window.nativeInlineModuleValue = value;</script>',
			'inline-optout' => '<script data-no-minify>window.nativeInlineRuns = (window.nativeInlineRuns || 0) + 1;</script>',
			'inline-already-minified' => '<script data-minify="1">window.nativeInlineRuns = (window.nativeInlineRuns || 0) + 1;</script>',
		] as $name => $tag ) {
			$meta = false !== strpos( $name, 'nonce' ) ? '<meta http-equiv="Content-Security-Policy" content="script-src &#39;nonce-fixture&#39;">' : '';
			$before = count( $requests );
			$output = $new_combine()->optimize( $document( $meta . $tag ) );
			minify_check( false !== strpos( $output, $tag ) && $before === count( $requests ), $name . ': combine preserves intrinsic attributes and inline bytes without fetching' );
			$export['pages'][ $name . '-combine' ] = $output;
		}
		$inline_classic = '<script>window.nativeInlineRuns = (window.nativeInlineRuns || 0) + 1;</script>';
		$output = $new_combine()->optimize( $document( $inline_classic ) );
		minify_check( false === strpos( $output, $inline_classic ) && false !== strpos( $output, 'data-minify="1"' ), 'ordinary inline classic script still combines into optimized delivery' );
		$export['pages']['inline-classic-combine'] = $output;
		$local_bytes = 'window.nativeOrder = [...(window.nativeOrder || []), "local"]; window.nativeClassicRuns = (window.nativeClassicRuns || 0) + 1;';
		file_put_contents( $fixture . '/native.js', $local_bytes );
		file_put_contents( $fixture . '/keep.js', 'window.nativeKeptRuns = (window.nativeKeptRuns || 0) + 1;' );
		$local_tag = '<script src="https://site.test/native.js"></script>';
		foreach ( [ '</BODY>', '</BoDy   >' ] as $index => $closing ) {
			$input = '<!doctype html><html><head></head><body>' . $local_tag . $closing . '</html>';
			$output = $new_combine()->optimize( $input );
			minify_check( false !== strpos( $output, 'data-minify="1"' ) && false === strpos( $output, $local_tag ), 'combined delivery supports mixed-case/whitespace closing body tags' );
			minify_check( false !== strpos( $output, $closing ), 'combined delivery preserves the original closing tag bytes' );
			$export['pages'][ 'classic-body-case-' . $index ] = $output;
		}
		foreach ( [
			'no-body' => '',
			'comment-body' => '<!-- </body> -->',
			'script-body' => '<script data-no-minify>window.tagLikeBody = "</body>";</script>',
			'textarea-body' => '<textarea></body></textarea>',
			'attribute-body' => '<div data-note="</body>"></div>',
			'template-body' => '<template><template></template></body></template>',
			'plaintext-body' => '<plaintext></body>',
			'unterminated-double-attribute-body' => '<div data-note="unterminated </body> tail',
			'unterminated-single-attribute-body' => "<div data-note='unterminated </body> tail",
		] as $name => $fake_closing ) {
			$input = '<!doctype html><html><head></head><body>' . $local_tag . $fake_closing . '</html>';
			$before = count( $requests );
			$output = $new_combine()->optimize( $input );
			minify_check( $input === $output && $before === count( $requests ), $name . ': missing real insertion point preserves all original scripts' );
			$export['pages'][ 'classic-body-fallback-' . $name ] = $output;
		}
		$tag_like = '<script data-no-minify>window.tagLikeBody = "</body>";</script><textarea></body></textarea><!-- </body> -->';
		$input = '<!doctype html><html><head></head><body>' . $tag_like . $local_tag . '</body></html>';
		$output = $new_combine()->optimize( $input );
		minify_check( false !== strpos( $output, $tag_like ) && 1 === substr_count( $output, 'data-minify="1"' ), 'combined delivery ignores tag-like script/comment/textarea content and inserts once' );
		$export['pages']['classic-body-real-after-text'] = $output;
		$local_kept = '<script src="https://site.test/keep.js"></script>';
		minify_check( false !== strpos( $minify->optimize( $document( $local_kept ) ), $local_kept ), 'empty dynamic templates still honor configured local exclusion' );
		$lists->templates = [ 'fixture-excluded' ];
		$dynamic_tag = '<script data-fixture-excluded src="https://site.test/native.js"></script>';
		minify_check( false !== strpos( $minify->optimize( $document( $dynamic_tag ) ), $dynamic_tag ), 'dynamic template exclusions still apply' );
		$lists->templates = [];
		foreach ( [ 'minify' => $minify, 'combine' => $new_combine() ] as $mode => $owner ) {
			$input = '<script data-note="type=module" src="https://site.test/native.js"></script><script src="https://site.test.other/remote.js?variant=1"></script>';
			$output = $owner->optimize( $document( $input ) );
			minify_check( false !== strpos( $output, 'data-minify="1"' ) && false === strpos( $output, 'src="https://site.test/native.js"' ), $mode . ': classic scripts still generate optimized delivery' );
			$export['pages'][ 'classic-' . $mode ] = $output;
		}
		foreach ( [ 'minify' => $minify, 'combine' => $new_combine() ] as $mode => $owner ) {
			$input = '<meta http-equiv="Content-Security-Policy" content="script-src &#39;self&#39;"><script src="https://site.test/native.js"></script>';
			$output = $owner->optimize( $document( $input ) );
			minify_check( false !== strpos( $output, 'data-minify="1"' ), $mode . ': enforcing self-source CSP still permits supported classic optimization' );
			$export['pages'][ 'classic-self-csp-' . $mode ] = $output;
		}
		minify_check( in_array( 'https://site.test.other/remote.js?variant=1', $requests, true ), 'suffix host uses remote request instead of reading a same-path local file' );
		$export['assets']['https://site.test/native.js'] = $local_bytes;
		foreach ( [ '503', 'transport', 'empty' ] as $failure ) {
			$recovered_bytes = $remote_bytes;
			$remote_status = '503' === $failure ? 503 : ( 'transport' === $failure ? 'transport' : 200 );
			if ( 'empty' === $failure ) { $remote_bytes = ''; }
			$remote_url = 'https://remote.test/unavailable-' . $failure . '.js';
			$mixed = $document( '<script src="https://site.test/native.js"></script><script src="' . $remote_url . '"></script>' );
			$output = $new_combine()->optimize( $mixed );
			minify_check( $mixed === $output, $failure . ': unavailable remote source retains every original script instead of publishing a partial bundle' );
			$export['pages']['classic-fallback-' . $failure] = $output;
			$export['assets'][ $remote_url ] = $recovered_bytes;
			$remote_bytes = $recovered_bytes;
		}
		$remote_status = 200;
		file_put_contents( $fixture . '/empty.js', '' );
		$mixed = $document( '<script src="https://site.test/native.js"></script><script src="https://site.test/empty.js"></script>' );
		$output = $new_combine()->optimize( $mixed );
		minify_check( $mixed === $output, 'empty local source retains all original scripts' );
		$export['pages']['local-fallback'] = $output;
		$export['assets']['https://site.test/empty.js'] = '';
		$dynamic = new \NoobRocket\Engine\Optimization\CacheDynamicResource( $options, $fixture . '/dynamic/', 'https://site.test/dynamic/' );
		minify_check( [ 'style_loader_src' => [ 'cache_dynamic_resource', 16 ], 'script_loader_src' => [ 'cache_dynamic_resource', 16 ] ] === $dynamic::get_subscribed_events(), 'dynamic owner remains registered for both loader source filters' );
		foreach ( [ 'js', 'css' ] as $format ) {
			$dynamic_hook = 'js' === $format ? 'script_loader_src' : 'style_loader_src';
			foreach ( [ 'https://site.test/wp-admin/admin-ajax.php', 'https://site.test/wp-content/static.' . $format, 'https://site.test/wp-content/dynamic.php?ver=7&variant=private', 'https://remote.test/wp-content/dynamic.php', 'https://site.test.other/wp-content/dynamic.php' ] as $source ) {
				$before = count( $requests );
				minify_check( $source === $dynamic->cache_dynamic_resource( $source ) && $before === count( $requests ), $format . ': excluded source retains its URL without fetching: ' . $source );
			}
			$source = 'https://site.test/wp-content/context-' . $format . '.php';
			foreach ( [ 'bypass', 'logged_in', 'login' ] as $context ) {
				$bypass = 'bypass' === $context;
				$logged_in = 'logged_in' === $context;
				$pagenow = 'login' === $context ? 'wp-login.php' : 'index.php';
				$before = count( $requests );
				minify_check( $source === $dynamic->cache_dynamic_resource( $source ) && $before === count( $requests ), $format . ': disallowed ' . $context . ' context does not fetch' );
			}
			$bypass = false; $logged_in = false; $pagenow = 'index.php';
			foreach ( [ '404', '500', 'transport', 'empty' ] as $failure ) {
				$name = 'dynamic-' . $format . '-' . $failure;
				$source = 'https://site.test/wp-content/' . $name . '.php?ver=7';
				$target = $fixture . '/dynamic/1/wp-content/' . $name . '.' . $format;
				$healthy = 'js' === $format ? 'window.dynamicHealthyRuns = (window.dynamicHealthyRuns || 0) + 1;' : '#dynamic { color: rgb(0, 128, 0); width: 64px; }';
				$poison = 'js' === $format ? 'window.dynamicErrorApplied = true;' : '#dynamic { color: rgb(255, 0, 0); width: 128px; }';
				$remote_status = 'empty' === $failure ? 200 : ( 'transport' === $failure ? 'transport' : (int) $failure );
				$remote_bytes = 'empty' === $failure ? '' : $poison;
				$before = count( $requests );
				$original = $dynamic->cache_dynamic_resource( $source );
				minify_check( $source === $original, $name . ': failed fetch preserves original URL' );
				minify_check( ! file_exists( $target ) && ! file_exists( $target . '.gz' ), $name . ': failed fetch publishes neither primary nor gzip cache' );
				minify_check( $before + 1 === count( $requests ) && $source === end( $requests ) && 'remote' === end( $request_kinds ), $name . ': cold fetch preserves the existing wp_remote_get policy' );
				$mime = 'js' === $format ? 'application/javascript' : 'text/css';
				$page = static function ( $url ) use ( $format ) { return '<!doctype html><html><head>' . ( 'js' === $format ? '<script src="' . $url . '"></script>' : '<link rel="stylesheet" href="' . $url . '">' ) . '</head><body><div id="dynamic">dynamic</div></body></html>'; };
				$export['dynamic_resources'][] = [ 'name' => $name, 'format' => $format, 'phase' => 'failure', 'html' => $page( $original ), 'requested_url' => $original, 'responses' => [ $original => [ 'status' => $remote_status, 'contentType' => $mime, 'body' => $remote_bytes ] ] ];
				$remote_status = '404' === $failure ? '200' : 200;
				$remote_bytes = $healthy;
				$recovered = $dynamic->cache_dynamic_resource( $source );
				minify_check( 'https://site.test/dynamic/1/wp-content/' . $name . '.' . $format === $recovered && $before + 2 === count( $requests ), $name . ': same source retries and uses a healthy static URL' );
				minify_check( is_readable( $target ) && $healthy === file_get_contents( $target ), $name . ': healthy bytes are published through the actual owner' );
				minify_check( is_readable( $target . '.gz' ) && $healthy === gzdecode( file_get_contents( $target . '.gz' ) ), $name . ': gzip companion contains only healthy bytes' );
				$export['dynamic_resources'][] = [ 'name' => $name, 'format' => $format, 'phase' => 'recovered', 'html' => $page( $recovered ), 'requested_url' => $recovered, 'responses' => [ $recovered => [ 'status' => 200, 'contentType' => $mime, 'body' => file_get_contents( $target ) ] ] ];
				$remote_status = 500; $remote_bytes = $poison;
				$warm = $dynamic->cache_dynamic_resource( $source );
				minify_check( $recovered === $warm && $before + 2 === count( $requests ) && $healthy === file_get_contents( $target ), $name . ': readable warm cache retains healthy delivery without fetching' );
				$export['dynamic_resources'][] = [ 'name' => $name, 'format' => $format, 'phase' => 'warm', 'html' => $page( $warm ), 'requested_url' => $warm, 'responses' => [ $warm => [ 'status' => 200, 'contentType' => $mime, 'body' => file_get_contents( $target ) ] ] ];
			}
		}
		$dynamic_filters = [ 'noobrocket_exclude_static_dynamic_resources' => [ '/wp-content/filter-excluded.php' ] ];
		$filtered = new \NoobRocket\Engine\Optimization\CacheDynamicResource( $options, $fixture . '/dynamic/', 'https://site.test/dynamic/' );
		$source = 'https://site.test/wp-content/filter-excluded.php';
		$before = count( $requests );
		minify_check( $source === $filtered->cache_dynamic_resource( $source ) && $before === count( $requests ), 'configured dynamic exclusion remains a no-fetch fallback' );
		$dynamic_filters = [ 'noobrocket_dynamic_resource_cache_filename' => 'filtered.css', 'noobrocket_css_url' => 'https://cdn.test/filtered.css' ];
		$remote_status = 200; $remote_bytes = '#dynamic { color: green; }';
		minify_check( 'https://cdn.test/filtered.css' === $dynamic->cache_dynamic_resource( 'https://site.test/wp-content/filter-delivery.php' ) && $remote_bytes === file_get_contents( $fixture . '/dynamic/1/filtered.css' ), 'dynamic filename and delivery URL filters retain existing behavior' );
		$dynamic_filters = [];
		foreach ( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $fixture . '/min', \FilesystemIterator::SKIP_DOTS ) ) as $file ) {
			if ( 'js' === $file->getExtension() ) { $export['assets'][ 'https://site.test/min/' . str_replace( '\\', '/', substr( $file->getPathname(), strlen( $fixture . '/min/' ) ) ) ] = file_get_contents( $file->getPathname() ); }
		}
		if ( '--export' === ( $argv[1] ?? '' ) ) { echo json_encode( $export, JSON_THROW_ON_ERROR ); }
		else { echo 'PASS: actual optimizer boundaries (', $checks, " assertions; owned files/modeled HTTP; no WordPress bootstrap)\n"; }
	} finally {
		foreach ( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $fixture, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::CHILD_FIRST ) as $file ) { if ( $file->isDir() ) { rmdir( $file->getPathname() ); } else { unlink( $file->getPathname() ); } }
		rmdir( $fixture );
	}
}
