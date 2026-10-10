<?php
/**
 * Actual local CSS selection/delivery with modeled WordPress and job boundaries.
 * No WordPress bootstrap, database or HTTP. --render supplies owned browser HTML.
 */
namespace NoobRocket\Engine\Media\Fonts\Frontend {
	class Controller {
		public function owns_font_link( string $tag ): bool {
			return false !== strpos( $tag, '/native-font.css' );
		}
	}
}

namespace NoobRocket\Engine\Optimization\LocalCSS {
	class Jobs {
		public $styles = [];
		public $row;
		public function valid_url( string $url ): bool { return true; }
		public function find( string $url, bool $mobile = false ) { return $this->row; }
		public function enqueue( string $url, bool $mobile, string $mode, string $hash, array $styles, array $safelist ): bool {
			$this->styles = $styles;
			$this->row = (object) [ 'queue_name' => 'local-' . $mode, 'hash' => $hash, 'status' => 'local-pending' ];
			return true;
		}
		public function invalidate_urls( array $urls ): void { $this->row = null; }
	}
}

namespace {
	$origin = isset( $argv[2] ) ? rtrim( $argv[2], '/' ) : 'https://fixture.invalid';
	$options = [ 'local_css_mode' => 'used' ];
	$filters = [];
	$checks = 0;
	$wp = (object) [ 'request' => '' ];
	$_GET = [];
	$_COOKIE = [];
	function get_noobrocket_option( $name, $default = false ) { return $GLOBALS['options'][ $name ] ?? $default; }
	function apply_filters( $name, $default ) { return $GLOBALS['filters'][ $name ] ?? $default; }
	function is_admin() { return false; }
	function is_user_logged_in() { return false; }
	function is_feed() { return false; }
	function is_preview() { return false; }
	function is_404() { return false; }
	function is_singular() { return false; }
	function noobrocket_bypass() { return false; }
	function noobrocket_get_constant( $name ) { return false; }
	function get_noobrocket_cache_dynamic_cookies() { return []; }
	function get_noobrocket_cache_mandatory_cookies() { return []; }
	function get_noobrocket_cache_reject_cookies() { return []; }
	function is_noobrocket_post_excluded_option( $name ) { return false; }
	function is_noobrocket_generate_caching_mobile_files() { return false; }
	function home_url( $path = '/' ) { return $GLOBALS['origin'] . $path; }
	function wp_parse_url( $url ) { return parse_url( $url ); }
	function wp_json_encode( $data ) { return json_encode( $data ); }
	function esc_attr( $text ) { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
	class WP_Http {
		public static function make_absolute_url( $url, $base ) {
			if ( parse_url( $url, PHP_URL_HOST ) ) { return $url; }
			if ( '/' === substr( $url, 0, 1 ) ) {
				$parts = parse_url( $base );
				return $parts['scheme'] . '://' . $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' ) . $url;
			}
			return preg_replace( '#[^/]*$#', '', $base ) . $url;
		}
	}

	require __DIR__ . '/../noobrocket/vendor/autoload.php';
	require __DIR__ . '/../noobrocket/inc/Engine/Optimization/LocalCSS/Frontend.php';
	$jobs = new \NoobRocket\Engine\Optimization\LocalCSS\Jobs();
	$owner = new \NoobRocket\Engine\Optimization\LocalCSS\Frontend( $jobs, new \NoobRocket\Engine\Media\Fonts\Frontend\Controller() );
	$selection = new \ReflectionMethod( $owner, 'styles' );
	$selection->setAccessible( true );
	function check( $condition, $message ) {
		if ( ! $condition ) { throw new \RuntimeException( 'FAIL: ' . $message ); }
		$GLOBALS['checks']++;
	}
	function sheet( $href, $attributes = '' ) { return '<link rel="stylesheet" href="' . $href . '"' . $attributes . '>'; }
	function document( $body, $head = '' ) { return '<!doctype html><html><head><title>π CSS selection</title>' . $head . '</head><body><main class="hero">Visible hero</main>' . $body . '</body></html>'; }
	$active = sheet( $origin . '/active.css', ' id="active" media="screen" nonce="preserved"' );
	$inert = sheet( $origin . '/inert.css' );
	$body = '<template><template>' . $inert . '</template></template><textarea>' . $inert . '</textarea><div data-preview=\'' . $inert . '\'></div>';
	$html = document( $body, '<meta data-head="</head>">' . $active );
	if ( ( $argv[1] ?? '' ) === '--render' ) {
		$owner->optimize( $html );
		echo json_encode( [ 'html' => $html, 'styles' => $jobs->styles ] ), "\n";
		exit( 0 );
	}
	if ( ( $argv[1] ?? '' ) === '--render-ready' ) {
		$options['local_css_mode'] = $argv[3];
		$results = json_decode( stream_get_contents( STDIN ), true );
		$owner->optimize( $html );
		$temp = tempnam( sys_get_temp_dir(), 'noobrocket-css-boundary-' );
		try {
			file_put_contents( $temp, $results[0]['css'] );
			$results[0]['path'] = $temp;
			$results[0]['uri'] = $origin . '/generated-' . $options['local_css_mode'] . '.css';
			$jobs->row->status = 'local-completed';
			$jobs->row->css = json_encode( [ 'results' => $results ] );
			echo json_encode( [ 'html' => $owner->optimize( $html ) ] ), "\n";
		} finally {
			unlink( $temp );
		}
		exit( 0 );
	}

	$cases = [
		'template' => '<template>' . $inert . '</template>',
		'nested template' => '<template><template>' . $inert . '</template>' . $inert . '</template>',
		'quoted attribute' => '<div data-preview=\'' . $inert . '\'></div>',
		'comment' => '<!--' . $inert . '-->',
		'noptimize block' => '<!-- noptimize -->' . $inert . '<!-- /noptimize -->',
	];
	foreach ( [ 'script', 'style', 'noscript', 'textarea', 'title', 'xmp', 'iframe', 'noembed', 'noframes' ] as $raw ) {
		$cases[ $raw ] = '<' . $raw . ' data-example=">">' . $inert . '</' . $raw . '>';
	}
	foreach ( $cases as $name => $markup ) {
		$input = document( $markup, $active );
		$styles = $selection->invoke( $owner, $input, $origin . '/' );
		check( array_column( $styles, 'href' ) === [ $origin . '/active.css' ], $name . ' excludes non-stylesheet bytes' );
		check( substr( $input, $styles[0]['offset'], strlen( $styles[0]['tag'] ) ) === $active, $name . ' retains original byte offsets and tag attributes' );
	}
	foreach ( [ '<!--' . $inert, '<div data-preview=\'' . $inert, '<template>' . $inert, '<textarea>' . $inert, '<plaintext>' . $inert ] as $unfinished ) {
		$styles = $selection->invoke( $owner, document( $unfinished, $active ), $origin . '/' );
		check( array_column( $styles, 'href' ) === [ $origin . '/active.css' ], 'unfinished/raw-to-EOF content cannot introduce a stylesheet' );
	}
	$base = '<base href="' . $origin . '/real/">';
	$fake_base = '<base href="' . $origin . '/wrong/">';
	foreach ( [ '<template>' . $fake_base . '</template>', '<!--' . $fake_base . '-->', '<textarea>' . $fake_base . '</textarea>', '<div data-preview=\'' . $fake_base . '\'></div>' ] as $markup ) {
		$styles = $selection->invoke( $owner, document( $markup . $base . sheet( 'relative.css' ) ), $origin . '/' );
		check( $styles[0]['href'] === $origin . '/real/relative.css', 'only an active base changes stylesheet resolution' );
	}
	$skip = sheet( $origin . '/disabled.css', ' disabled' ) . sheet( $origin . '/integrity.css', ' integrity="sha256-test"' ) . sheet( $origin . '/print.css', ' media="print"' ) . sheet( $origin . '/native-font.css' );
	check( array_column( $selection->invoke( $owner, document( $skip, $active ), $origin . '/' ), 'href' ) === [ $origin . '/active.css' ], 'disabled, integrity, media and native-font exclusions remain owned' );
	$filters['noobrocket_rucss_external_exclusions'] = [ '/active.css' ];
	check( [] === $selection->invoke( $owner, document( '', $active ), $origin . '/' ), 'filtered exclusions remain effective' );
	$filters = [];
	$options['exclude_css'] = [ '/active\\.css' ];
	check( [] === $selection->invoke( $owner, document( '', $active ), $origin . '/' ), 'configured CSS exclusions remain effective' );
	unset( $options['exclude_css'] );
	$headless = '<div data-preview="</head>">' . $active . '</div>';
	check( $owner->optimize( $headless ) === $headless && ! $jobs->styles, 'a fake closing head cannot enqueue optimization' );
	check( $owner->optimize( $html ) === $html && array_column( $jobs->styles, 'href' ) === [ $origin . '/active.css' ], 'actual cold pipeline queues only active styles and preserves originals' );
	$temp = tempnam( sys_get_temp_dir(), 'noobrocket-css-boundary-' );
	try {
		file_put_contents( $temp, '.hero{color:red}' );
		$jobs->row->status = 'local-completed';
		$jobs->row->css = json_encode( [ 'results' => [ [ 'href' => $origin . '/active.css', 'path' => $temp, 'uri' => $origin . '/generated.css' ] ] ] );
		$optimized = $owner->optimize( $html );
		check( false !== strpos( $optimized, '/generated.css' ) && false === strpos( $optimized, '/active.css' ), 'completed owner rewrites the active sheet' );
		check( false !== strpos( $optimized, $body ), 'completed delivery preserves inert markup byte-for-byte' );
		check( false !== strpos( $optimized, 'nonce="preserved"' ) && false !== strpos( $optimized, 'media="screen"' ), 'completed delivery preserves active attributes' );
		$options['local_css_mode'] = 'critical';
		$jobs->row = null;
		$owner->optimize( $html );
		$jobs->row->status = 'local-completed';
		$jobs->row->css = json_encode( [ 'results' => [ [ 'href' => $origin . '/active.css', 'path' => $temp, 'css' => '.hero{color:red}' ] ] ] );
		$critical = $owner->optimize( $html );
		check( false !== strpos( $critical, '<meta data-head="</head>"><style data-noobrocket-local-css="critical"' ), 'critical CSS uses the active link offset after fake closing-head bytes' );
		check( false !== strpos( $critical, $body ) && false !== strpos( $critical, '<noscript>' . $active . '</noscript>' ), 'critical delivery preserves inert content and original noscript fallback' );
		check( $owner->has_critical_css() && false !== strpos( $critical, 'nonce="preserved"' ), 'critical readiness and source nonce survive structural selection' );
	} finally {
		unlink( $temp );
	}
	echo 'PASS local CSS stylesheet boundaries: ', $checks, " assertions; modeled WP/jobs, real selection/delivery\n";
}
