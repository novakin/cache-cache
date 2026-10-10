<?php
/** Production lazyload footer with modeled WordPress boundaries; no site bootstrap or writes. */
declare(strict_types=1);

namespace NoobRocket\Engine\Media\Lazyload {
	/** CLI cannot retain response headers; model only this owner's HTTP boundary. */
	function headers_list() { return $GLOBALS['fixture_headers']; }
}
namespace {
$root = dirname( __DIR__ );
$checks = 0;
$filters = [];
$filtered_tag = '';
$tag_calls = 0;
$fixture_headers = [];
$asset_path = $root . '/noobrocket/assets/js/';
define( 'SCRIPT_DEBUG', in_array( '--debug', $argv, true ) );
define( 'NOOBROCKET_ASSETS_JS_URL', 'https://lazy-runtime.test/' );
define( 'NOOBROCKET_ASSETS_JS_PATH', $asset_path );

function bootstrap_check( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
	++$GLOBALS['checks'];
}
function apply_filters( $hook, $value, ...$args ) {
	if ( isset( $GLOBALS['filters'][ $hook ] ) ) { $value = $GLOBALS['filters'][ $hook ]( $value, ...$args ); }
	if ( 'noobrocket_lazyload_script_tag' === $hook ) {
		$GLOBALS['filtered_tag'] = $value;
		++$GLOBALS['tag_calls'];
	}
	return $value;
}
function apply_filters_deprecated( $hook, $args, ...$unused ) { return apply_filters( $hook, ...$args ); }
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, $args ); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_html__( $value, $domain ) { return $value; }
function noobrocket_get_constant( $key, $default = null ) {
	if ( 'NOOBROCKET_ASSETS_JS_PATH' === $key ) { return $GLOBALS['asset_path']; }
	return defined( $key ) ? constant( $key ) : $default;
}
function is_admin() { return false; }
function is_feed() { return false; }
function is_preview() { return false; }
function is_search() { return false; }

require $root . '/noobrocket/vendor/autoload.php';
require $root . '/noobrocket/inc/classes/admin/class-options-data.php';

$pages = [];
$tags = [];
$response_headers = [];
$render = static function ( $name, $content = '', $settings = [], $scenario_filters = [], $policy = '', $opt_in = true ) use ( &$pages, &$tags, &$response_headers ) {
	$GLOBALS['filters'] = $scenario_filters;
	// Optimization scenarios opt in deliberately; default scenarios leave the filter unset.
	if ( $opt_in ) {
		$GLOBALS['filters']['noobrocket_lazyload_gate_runtime'] = static function () { return true; };
	}
	$GLOBALS['tag_calls'] = 0;
	$GLOBALS['fixture_headers'] = [];
	$subscriber = new \NoobRocket\Engine\Media\Lazyload\Subscriber(
		new \NoobRocket\Admin\Options_Data( array_merge( [ 'lazyload' => 1, 'lazyload_iframes' => 1 ], $settings ) ),
		new \NoobRocket\Dependencies\NoobRocketLazyload\Assets(),
		new \NoobRocket\Dependencies\NoobRocketLazyload\Image(),
		new \NoobRocket\Dependencies\NoobRocketLazyload\Iframe()
	);
	ob_start();
	$subscriber->insert_nojs_style();
	$subscriber->insert_lazyload_script();
	$subscriber->insert_youtube_thumbnail_script();
	$footer = ob_get_clean();
	bootstrap_check( 1 === $GLOBALS['tag_calls'], $name . ': script-tag filter evaluated exactly once' );
	bootstrap_check( false !== strpos( $footer, 'window.lazyLoadOptions' ), $name . ': production configuration retained' );
	bootstrap_check( false !== strpos( $footer, 'noobrocket-lazyload-nojs-css' ), $name . ': production noscript style retained' );
	bootstrap_check( false !== strpos( $footer, $GLOBALS['filtered_tag'] ), $name . ': footer retains original filtered script markup' );
	$tags[ $name ] = $GLOBALS['filtered_tag'];
	$meta = '';
	if ( $policy ) {
		preg_match( '#<script>(.*?)</script>#s', $footer, $configuration );
		$csp = 'script-src \'sha256-' . base64_encode( hash( 'sha256', $configuration[1], true ) ) . '\' https://lazy-runtime.test';
		if ( 'trusted-types' === $policy ) { $csp = "require-trusted-types-for 'script'"; }
		if ( in_array( $policy, [ 'meta', 'trusted-types' ], true ) ) {
			$meta = '<meta http-equiv="Content-Security-Policy" content="' . esc_attr( $csp ) . '">';
		} else {
			$header = 'report-only' === $policy ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy';
			if ( 'late-header' !== $policy ) { $GLOBALS['fixture_headers'] = [ $header . ': ' . $csp ]; }
			$response_headers[ $name ] = [ $header => $csp ];
		}
	}
	$original = '<!doctype html><html><head>' . $meta . '<meta name="viewport" content="width=device-width,initial-scale=1"><style>img,iframe,.noobrocket-lazyload{width:32px;height:32px}#custom-container{width:100%;height:160px;overflow:auto}</style><title>Lazyload bootstrap fixture</title></head><body>' . $content . $footer . '</body></html>';
	$pages[ $name ] = $subscriber->gate_lazyload_runtime( $original );
	bootstrap_check( 1 === $GLOBALS['tag_calls'], $name . ': buffer gate does not rerun script-tag hook' );
	if ( ! $opt_in ) {
		bootstrap_check( $original === $pages[ $name ], $name . ': default delivery keeps original HTML bytes' );
	}
	if ( $policy && 'report-only' !== $policy ) {
		bootstrap_check( $original === $pages[ $name ], $name . ': enforcing CSP keeps original HTML bytes' );
	}
	bootstrap_check( $subscriber->gate_lazyload_runtime( '<p>No emitted footer runtime remains.</p>' ) === '<p>No emitted footer runtime remains.</p>', $name . ': absent emitted runtime leaves buffer unchanged' );
	return $pages[ $name ];
};

$render( 'default-empty', '', [], [], 'late-header', false );
$render( 'default-initial', '<img id="initial" data-lazy-src="https://lazy-images.test/initial.png">', [], [], 'late-header', false );
$footer = $render( 'empty' );
bootstrap_check( false !== strpos( $footer, '<template>' . $tags['empty'] . '</template>' ), 'empty: runtime is inert until the buffer-owned bootstrap finds targets' );
$render( 'eager', '<img id="eager" loading="eager" fetchpriority="high" src="https://lazy-images.test/eager.png"><iframe src="https://lazy-frames.test/eager"></iframe>' );
$render( 'initial', '<img id="initial" data-lazy-src="https://lazy-images.test/initial.png">' );
$render( 'youtube', '<div class="rll-youtube-player" data-id="dQw4w9WgXcQ" data-src="https://www.youtube.com/embed/dQw4w9WgXcQ" data-query="rel=0" data-alt="Video thumbnail"></div>', [ 'lazyload_youtube' => 1 ] );
$render( 'native-images', '<img id="native" loading="lazy" src="https://lazy-images.test/native.png">', [], [ 'noobrocket_use_native_lazyload_images' => static function () { return true; } ] );
$render( 'native-runtime', '', [], [ 'noobrocket_use_native_lazyload' => static function () { return true; } ] );
$filtered = $render( 'filtered', '<div id="custom-container"></div><div id="outside"></div>', [], [
	'noobrocket_lazyload_threshold' => static function () { return 17; },
	'noobrocket_lazyload_script_args' => static function ( $args ) {
		$args['elements'] = [ 'custom' => 'img.filtered-target' ];
		$args['options'] = [
			'container' => 'document.getElementById("custom-container")',
			'thresholds' => '"21px"',
			'callback_loaded' => 'function(element){element.dataset.filteredCallback="loaded";}',
			'unsupported_fixture_option' => 'true',
		];
		return $args;
	},
	'noobrocket_lazyload_script_tag' => static function ( $tag ) {
		$runtime = file_get_contents( $GLOBALS['asset_path'] . 'lazyload/17.8.3/lazyload' . ( SCRIPT_DEBUG ? '' : '.min' ) . '.js' );
		$integrity = 'sha384-' . base64_encode( hash( 'sha384', $runtime, true ) );
		$tag = preg_replace( '#src="[^"]+"#', 'src="https://lazy-runtime.test/filtered-runtime.js"', $tag );
		return str_replace( '<script ', '<script id="filtered-runtime" nonce="fixture-nonce" integrity="' . $integrity . '" crossorigin="anonymous" fetchpriority="low" data-fixture="preserved" onload="window.filteredRuntimeLoads=(window.filteredRuntimeLoads||0)+1" ', $tag );
	},
] );
bootstrap_check( false !== strpos( $filtered, 'filteredCallback' ) && false !== strpos( $filtered, '21px' ), 'filtered: raw callback and threshold options retained' );
bootstrap_check( false === strpos( $filtered, 'unsupported_fixture_option' ), 'filtered: existing allowed-option boundary retained' );

foreach ( [ 'meta', 'header', 'trusted-types', 'report-only' ] as $policy ) {
	$render( 'csp-' . $policy, '', [], [], $policy );
}

// Exercise the shipped DelayJS transform, then its real browser runtime. Only
// the configured exclusion list is modeled; parser/execution code stays native.
$delay = ( new \ReflectionClass( \NoobRocket\Engine\Optimization\DelayJS\HTML::class ) )->newInstanceWithoutConstructor();
$excluded = new \ReflectionProperty( $delay, 'excluded' );
$excluded->setAccessible( true );
$excluded->setValue( $delay, [] );
preg_match( '#<script(?<attr>[^>]+)>(?<content>.*?)</script>#s', $tags['filtered'], $match );
$delayed_tag = $delay->replace_scripts( $match );
bootstrap_check( false !== strpos( $delayed_tag, 'data-noobrocket-src=' ) && false !== strpos( $delayed_tag, 'text/noobrocketlazyloadscript' ), 'DelayJS transforms the filtered custom runtime URL using the shipped parser' );
$pages['delayed-filtered'] = str_replace( $tags['filtered'], $delayed_tag, $pages['filtered'] );
$delay_runtime = file_get_contents( $asset_path . 'lazyload-scripts.min.js' );
$pages['delayed-filtered'] = str_replace( '<head>', '<head><script>window.addEventListener("noobrocket-allScriptsLoaded",function(){window.delayDone=true;});</script><script>' . $delay_runtime . '</script>', $pages['delayed-filtered'] );

// Model Combine's removal/relocation output; native combining itself has a
// separate fixture. The owned bootstrap must tolerate its now-empty template.
$pages['combined-empty-template'] = str_replace( '<template>' . $tags['empty'] . '</template>', '<template></template>', $pages['empty'] );
$pages['combined-empty-template'] = str_replace( '</body>', $tags['empty'] . '</body>', $pages['combined-empty-template'] );

foreach ( [
	'nonasync' => static function ( $tag ) { return str_replace( ' async ', ' ', $tag ); },
	'explicit-type' => static function ( $tag ) { return str_replace( '<script ', '<script type="text/javascript" ', $tag ); },
	'defer' => static function ( $tag ) { return str_replace( '<script ', '<script defer ', $tag ); },
	'malformed' => static function ( $tag ) { return str_replace( '<script ', '<script-foo ', $tag ); },
	'complex' => static function ( $tag ) { return $tag . '<script>window.extraFilteredScript=true;</script>'; },
	'inline' => static function () { return '<script>window.customInlineLazyload=true;</script>'; },
] as $name => $filter ) {
	$output = $render( $name, '', [], [ 'noobrocket_lazyload_script_tag' => $filter ] );
	bootstrap_check( false !== strpos( $output, $tags[ $name ] ), $name . ': unsupported filtered tag retains its original eager delivery' );
}

$asset_path = __DIR__ . '/missing-bootstrap-assets/';
$missing = $render( 'missing-bootstrap' );
bootstrap_check( false !== strpos( $missing, $tags['missing-bootstrap'] ), 'missing bootstrap: original async runtime remains available' );

if ( in_array( '--export', $argv, true ) ) {
	echo json_encode( [ 'pages' => $pages, 'tags' => $tags, 'headers' => $response_headers ], JSON_THROW_ON_ERROR );
} else {
	echo 'PASS: lazyload bootstrap footer (', $checks, SCRIPT_DEBUG ? ' assertions; debug' : ' assertions; minified', "; production owner/configuration, modeled WordPress boundary)\n";
}
}
