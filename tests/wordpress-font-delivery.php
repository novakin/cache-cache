<?php
/** Native font owners with modeled WordPress/queue/HTTP boundaries and owned temporary files. */
declare(strict_types=1);
namespace NoobRocket\Logger { class Logger { public static function debug( ...$args ): void {} public static function error( ...$args ): void {} } }
namespace NoobRocket\Engine\Media\Fonts\Context {
	class OptimizationContext { public $allowed = true; public function is_allowed(): bool { return $this->allowed; } }
	class SaasContext { public function is_allowed(): bool { return false; } }
}
namespace {
	$fixture = sys_get_temp_dir() . '/nbr-font-delivery-' . bin2hex( random_bytes( 5 ) );
	mkdir( $fixture );
	define( 'NOOBROCKET_CACHE_ROOT_PATH', $fixture . '/' );
	define( 'NOOBROCKET_CACHE_ROOT_URL', 'https://site.test/wp-content/cache/noobrocket/' );
	define( 'WP_CONTENT_DIR', dirname( $fixture ) );
	define( 'MINUTE_IN_SECONDS', 60 );
	$requests = $scheduled = $transients = $filters = $cleaned = $events = [];
	$hosting = true;
	$remote_success = true;
	$remote_delay = 150000;
	$checks = 0;
	$wp = (object) [ 'request' => 'article' ];
	function font_check( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); } ++$GLOBALS['checks']; }
	function noobrocket_get_constant( $name, $default = null ) { return defined( $name ) ? constant( $name ) : $default; }
	function get_current_blog_id() { return 1; }
	function noobrocket_get_filesystem_perms() { return 0644; }
	function wp_generate_uuid4() { return bin2hex( random_bytes( 16 ) ); }
	function noobrocket_mkdir_p( $path ) { return is_dir( $path ) || mkdir( $path, 0777, true ); }
	function get_transient( $key ) { return $GLOBALS['transients'][ $key ] ?? false; }
	function set_transient( $key, $value, $expires = 0 ) { $GLOBALS['transients'][ $key ] = $value; return true; }
	function wp_safe_remote_get( $url, $args ) {
		$GLOBALS['requests'][] = [ $url, $args ];
		usleep( $GLOBALS['remote_delay'] );
		return [ 'response' => [ 'code' => $GLOBALS['remote_success'] ? 200 : 503 ], 'body' => false !== strpos( $url, 'googleapis' ) ? '@font-face{font-family:Fixture;src:url(https://fonts.gstatic.com/fixture.woff2)}' : 'fixture-font-bytes' ];
	}
	function wp_remote_retrieve_response_code( $response ) { return $response['response']['code']; }
	function wp_remote_retrieve_body( $response ) { return $response['body']; }
	function wp_parse_url( $url, $part = -1 ) { return parse_url( $url, $part ); }
	function content_url( $path ) { return 'https://site.test/wp-content' . $path; }
	function wpm_apply_filters_typed( $type, $hook, $value, ...$args ) { return $GLOBALS['filters'][ $hook ] ?? $value; }
	function apply_filters( $hook, $value, ...$args ) { return $value; }
	function home_url( $path = '' ) { return 'https://site.test/' . ltrim( $path, '/' ); }
	function add_query_arg( $args, $url ) { return $url; }
	function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES ); }
	function noobrocket_clean_files( $urls ) { $GLOBALS['cleaned'] = array_merge( $GLOBALS['cleaned'], (array) $urls ); }
	function do_action( $hook, ...$args ) { $GLOBALS['events'][] = [ $hook, $args ]; }
	function as_has_scheduled_action( $hook, $args, $group ) { return isset( $GLOBALS['scheduled'][ $hook . serialize( $args ) ] ); }
	function as_enqueue_async_action( $hook, $args, $group, $unique = false ) { $GLOBALS['scheduled'][ $hook . serialize( $args ) ] = [ $hook, $args, $group, $unique ]; return count( $GLOBALS['scheduled'] ); }
	function as_schedule_single_action( $timestamp, $hook, $args, $group ) { $GLOBALS['scheduled'][ $hook . serialize( $args ) ] = [ $hook, $args, $group, true, $timestamp ]; return count( $GLOBALS['scheduled'] ); }
	class FontFilesystem {
		public function exists( $path ) { return file_exists( $path ); }
		public function is_readable( $path ) { return is_readable( $path ); }
		public function size( $path ) { clearstatcache( true, $path ); return is_file( $path ) ? filesize( $path ) : 0; }
		public function get_contents( $path ) { return is_file( $path ) ? file_get_contents( $path ) : false; }
		public function put_contents( $path, $content, $mode = false ) { return false !== file_put_contents( $path, $content ); }
		public function delete( $path ) { return ! file_exists( $path ) || unlink( $path ); }
	}
	$root = dirname( __DIR__ ) . '/noobrocket/inc/';
	$font_root = in_array( '--measure', $argv, true ) && getenv( 'NOOBROCKET_FONT_FIXTURE_SOURCE' ) ? rtrim( getenv( 'NOOBROCKET_FONT_FIXTURE_SOURCE' ), '/\\' ) . '/' : $root;
	require $root . 'classes/admin/class-options-data.php';
	require $root . 'classes/event-management/subscriber-interface.php';
	require $root . 'Engine/Common/Queue/QueueInterface.php';
	require $root . 'Engine/Common/Queue/AbstractASQueue.php';
	require $root . 'Engine/Common/AbstractFileSystem.php';
	require $root . 'Engine/Common/Head/ElementTrait.php';
	require $root . 'Engine/Optimization/RegexTrait.php';
	require $root . 'Engine/Media/Fonts/FontsTrait.php';
	require $font_root . 'Engine/Media/Fonts/Filesystem.php';
	require $font_root . 'Engine/Media/Fonts/Admin/Data.php';
	require $font_root . 'Engine/Media/Fonts/Frontend/Controller.php';
	$options = new NoobRocket\Admin\Options_Data( [ 'host_fonts_locally' => 1 ] );
	$filesystem = new NoobRocket\Engine\Media\Fonts\Filesystem( new FontFilesystem() );
	$data = new NoobRocket\Engine\Media\Fonts\Admin\Data( $options, $filesystem );
	$context = new NoobRocket\Engine\Media\Fonts\Context\OptimizationContext();
	$controller = new NoobRocket\Engine\Media\Fonts\Frontend\Controller( $context, new NoobRocket\Engine\Media\Fonts\Context\SaasContext(), $filesystem, $data );
	$url = 'https://fonts.googleapis.com/css2?family=Fixture';
	$tag = '<link id="fixture-font" rel="stylesheet" href="' . $url . '" media="all">';
	$page = '<html><head><link rel="preconnect" href="https://fonts.googleapis.com">' . $tag . '</head><body>Font fixture.</body></html>';
	try {
		$start = microtime( true );
		$cold = $controller->rewrite_fonts_for_optimizations( $page );
		$cold_ms = ( microtime( true ) - $start ) * 1000;
		$before_requests = count( $requests );
		if ( in_array( '--measure', $argv, true ) ) {
			if ( $scheduled ) { $data->prepare_font( ...array_values( current( $scheduled )[1] ) ); }
			$start = microtime( true );
			$controller->rewrite_fonts_for_optimizations( $page );
			echo json_encode( [ 'cold_ms' => round( $cold_ms, 3 ), 'prepared_ms' => round( ( microtime( true ) - $start ) * 1000, 3 ), 'cold_http_calls' => $before_requests ] ), PHP_EOL;
			return;
		}
		font_check( $cold === $page && ! $requests, 'cold visitor keeps original link/preconnect without HTTP downloads' );
		font_check( 1 === count( $scheduled ), 'cold sheet queues preparation' );
		font_check( current( $scheduled )[3], 'queue uses native unique action admission' );
		$controller->rewrite_fonts_for_optimizations( $page );
		font_check( 1 === count( $scheduled ), 'repeat cold visit does not duplicate pending page/sheet job' );
		$data->prepare_font( ...array_values( current( $scheduled )[1] ) );
		font_check( 2 === count( $requests ) && [ 'https://site.test/article' ] === $cleaned, 'background task prepares CSS/font and invalidates only waiting page' );
		font_check( count( $events ) && 'noobrocket_font_css_prepared' === end( $events )[0], 'successful preparation announces font-hint invalidation' );
		$prepared = $controller->rewrite_fonts_for_optimizations( $page );
		font_check( false !== strpos( $prepared, '/google-fonts/css/' ) && false !== strpos( $prepared, 'id="fixture-font"' ) && false !== strpos( $prepared, 'media="all"' ) && false === strpos( $prepared, 'rel="preconnect"' ), 'prepared href changes while attributes survive and unused provider hint goes away' );
		font_check( 2 === count( $requests ), 'prepared visitor reuses complete files without HTTP' );
		$quoted_boundary = '<link data-note="href=\'https://fonts.googleapis.com/css2?family=Fake\' >" href="' . $url . '" rel="stylesheet" onload="if(window.value > 0){this.media=\'all\'}">';
		$output = $controller->rewrite_fonts_for_optimizations( $quoted_boundary );
		font_check( false !== strpos( $output, 'data-note="href=\'https://fonts.googleapis.com/css2?family=Fake\' >"' ) && false !== strpos( $output, 'onload="if(window.value > 0){this.media=\'all\'}"' ) && false !== strpos( $output, '/google-fonts/css/' ), 'quoted tag-like boundaries and fake href data retain exact attributes while real href is rewritten' );
		$fake_href = '<link data-note="href=\'' . $url . '\'" href="/ordinary.css" rel="stylesheet">';
		font_check( ! $controller->owns_font_link( $fake_href ) && $controller->rewrite_fonts_for_optimizations( $fake_href ) === $fake_href, 'quoted data cannot impersonate a hosted font href' );
		$dollar_url = $url . '&text=$10';
		$dollar_path = $fixture . '/fonts/1/google-fonts/css/' . $filesystem->hash_to_path( md5( $dollar_url ) ) . '.css';
		noobrocket_mkdir_p( dirname( $dollar_path ) );
		file_put_contents( $dollar_path, 'body{font-family:Fixture}' );
		font_check( false !== strpos( $controller->rewrite_fonts_for_optimizations( str_replace( $url, $dollar_url, $tag ) ), 'text=$10' ), 'literal dollar/font-text parameter survives markup replacement' );
		foreach ( [ 'media="print" onload="this.media=\'all\'"', 'media="screen and (min-width: 40em)"', 'disabled', 'rel="alternate stylesheet" title="optional"', 'nonce="fixture-nonce" data-custom="kept"' ] as $attributes ) {
			$custom = '<link href="' . $url . '" ' . $attributes . '>';
			$output = $controller->rewrite_fonts_for_optimizations( $custom );
			font_check( false !== strpos( $output, $attributes ) && false !== strpos( $output, '/google-fonts/css/' ), 'link attribute semantics retained: ' . $attributes );
			$filters['noobrocket_host_fonts_locally_inline_css'] = true;
			$output = $controller->rewrite_fonts_for_optimizations( $custom );
			if ( false === strpos( $attributes, 'nonce=' ) ) { font_check( false !== strpos( $output, '<link' ) && false !== strpos( $output, $attributes ), 'conditional/non-stylesheet delivery is not forced inline' ); }
			unset( $filters['noobrocket_host_fonts_locally_inline_css'] );
		}
		$integrity = str_replace( 'media="all"', 'integrity="sha384-original" crossorigin="anonymous"', $tag );
		font_check( $controller->rewrite_fonts_for_optimizations( $integrity ) === $integrity && ! $controller->owns_font_link( $integrity ), 'SRI-constrained sheet remains original and unclaimed' );
		$conditional = '<!--[if IE]>' . $tag . '<![endif]-->';
		$output = $controller->rewrite_fonts_for_optimizations( $conditional . $tag );
		font_check( false !== strpos( $output, $conditional ) && 1 === substr_count( $output, '/google-fonts/css/' ), 'conditional comment remains exact when the same ordinary link is rewritten' );
		$script_and_fallback = '<script type="text/template">' . $tag . '</script><noscript>' . $tag . '</noscript>';
		$output = $controller->rewrite_fonts_for_optimizations( $script_and_fallback . $tag );
		font_check( false !== strpos( $output, $script_and_fallback ) && 1 === substr_count( $output, '/google-fonts/css/' ), 'script template and noscript fallback remain exact when an ordinary duplicate link is rewritten' );
		$filters['noobrocket_host_fonts_locally_inline_css'] = true;
		$output = $controller->rewrite_fonts_for_optimizations( str_replace( 'media="all"', 'media="all" nonce="fixture-nonce"', $tag ) );
		font_check( false !== strpos( $output, '<style ' ) && false !== strpos( $output, 'nonce="fixture-nonce"' ) && false !== strpos( $output, 'id="fixture-font"' ), 'eligible inline sheet preserves nonce/id and exact local CSS' );
		unset( $filters['noobrocket_host_fonts_locally_inline_css'] );
		$items = [ [ 'open_tag' => '<link', 'rel' => 'stylesheet', 'href' => $url . '&cold=1', 'media' => 'print', 'onload' => "this.media='all'" ] ];
		font_check( $controller->rewrite_fonts_in_head( $items ) === $items, 'pending generated head link never acquires an empty href or disappears' );
		$filters['noobrocket_host_fonts_locally_inline_css'] = true;
		font_check( $controller->rewrite_fonts_in_head( $items ) === $items, 'pending head sheet remains intact when inline requested' );
		$disabled_head = [ [ 'open_tag' => '<link', 'rel' => 'stylesheet', 'href' => $url, 0 => 'disabled' ] ];
		$updated_head = $controller->rewrite_fonts_in_head( $disabled_head );
		font_check( 'disabled' === $updated_head[0][0] && '<link' === $updated_head[0]['open_tag'] && false !== strpos( $updated_head[0]['href'], '/google-fonts/css/' ), 'generated head boolean attributes stay intact without forced inline output' );
		unset( $filters['noobrocket_host_fonts_locally_inline_css'] );
		$filters['noobrocket_exclude_locally_host_fonts'] = [ 'family=Fixture' ];
		font_check( $controller->rewrite_fonts_for_optimizations( $page ) === $page, 'exclusion preserves original link and provider hint' );
		$before = count( $requests );
		$data->prepare_font( $url . '&excluded=1', 'https://site.test/article' );
		font_check( $before === count( $requests ), 'queued preparation rechecks changed exclusions' );
		unset( $filters['noobrocket_exclude_locally_host_fonts'] );
		$context->allowed = false;
		font_check( $controller->rewrite_fonts_for_optimizations( $page ) === $page, 'disabled/bypass context preserves source' );
		$options->set( 'host_fonts_locally', 0 );
		$data->prepare_font( $url . '&disabled=1', 'https://site.test/article' );
		font_check( $before === count( $requests ), 'queued preparation respects hosting disabled before execution' );
		$options->set( 'host_fonts_locally', 1 );
		$data->prepare_font( 'https://evil.test/css2?family=Fixture', 'https://site.test/article' );
		$data->prepare_font( $url, 'https://elsewhere.test/article' );
		font_check( $before === count( $requests ) && 1 === count( $cleaned ), 'worker rejects external stylesheet/cache invalidation ownership' );
		$remote_success = false;
		$remote_delay = 0;
		$data->prepare_font( $url . '&failed=1', 'https://site.test/article' );
		font_check( 1 === count( $cleaned ), 'failed preparation leaves page delivery intact' );
		$retry = end( $scheduled );
		font_check( 1 === $retry[1][2] && $retry[4] > time() + 300, 'failed preparation retries after cooldown without visitor involvement' );
		$before_jobs = count( $scheduled );
		$data->prepare_font( $url . '&failed=1', 'https://site.test/article', 2 );
		font_check( $before_jobs === count( $scheduled ), 'persistent failure stops after three preparation attempts' );
		unset( $transients[ 'noobrocket_font_failure_' . md5( $url . '&failed=1' ) ] );
		$remote_success = true;
		$data->prepare_font( ...$retry[1] );
		font_check( 2 === count( $cleaned ), 'retry recovers delivery and invalidates waiting page after provider recovery' );
		echo 'PASS: ', $checks, ' native font delivery assertions.', PHP_EOL;
	} finally {
		foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $fixture, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST ) as $file ) { $file->isDir() ? rmdir( $file->getPathname() ) : unlink( $file->getPathname() ); }
		rmdir( $fixture );
	}
}
