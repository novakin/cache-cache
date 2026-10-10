<?php
/** Actual stylesheet-background owners; modeled hooks/HTTP, owned real filesystem, no WordPress bootstrap. */
declare(strict_types=1);

namespace NoobRocket\Logger {
	class Logger { public static function debug( ...$args ): void {} public static function notice( ...$args ): void {} }
}
namespace NoobRocket\Engine\Common\Cache {
	function rename( $from, $to ) {
		$callback = $GLOBALS['before_rename'];
		if ( $callback ) { $GLOBALS['before_rename'] = null; $callback( $from, $to ); }
		return ! $GLOBALS['fail_rename'] && \rename( $from, $to );
	}
}
namespace {
	if ( ! function_exists( 'mb_substr' ) || ! function_exists( 'mb_strlen' ) ) {
		fwrite( STDERR, "The real stylesheet fetcher fixture requires the PHP mbstring extension.\n" );
		exit( 2 );
	}
	$checks = 0;
	$filters = [];
	$clock = 100;
	$before_rename = null;
	$fail_rename = false;
	$constant_overrides = [];
	$requests = [];
	$resolver_inputs = [];
	$resolver_result = '/controlled/main.css';
	$remote_css = '.remote{background-image:url(https://images.example.test/hero.png);color:green}';
	$browser_origin = $argv[1] ?? '';
	$site_origin = $browser_origin ?: 'https://site.test';
	function check( $condition, string $message ): void { if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); } ++$GLOBALS['checks']; }
	function apply_filters( $hook, $value, ...$args ) { foreach ( $GLOBALS['filters'][ $hook ] ?? [] as $callback ) { $value = $callback( $value, ...$args ); } return $value; }
	function wpm_apply_filters_typed( $type, $hook, $value, ...$args ) { return apply_filters( $hook, $value, ...$args ); }
	function wp_parse_url( $url, $part = -1 ) { return parse_url( $url, $part ); }
	function get_noobrocket_parse_url( $url ) { return parse_url( $url ) + [ 'host' => '', 'path' => '', 'query' => '', 'fragment' => '' ]; }
	function home_url( $path = '' ) { return $GLOBALS['site_origin'] . ( $path ? '/' . ltrim( $path, '/' ) : '' ); }
	function noobrocket_get_home_url() { return home_url(); }
	function get_current_blog_id() { return 1; }
	function noobrocket_get_constant( $name ) { return $GLOBALS['constant_overrides'][ $name ] ?? constant( $name ); }
	function wp_normalize_path( $path ) { return str_replace( '\\', '/', $path ); }
	function _noobrocket_normalize_path( $path ) { return str_replace( '\\', '/', $path ); }
	function noobrocket_mkdir_p( $path, $filesystem = null ) { return is_dir( $path ) || mkdir( $path, 0777, true ); }
	function noobrocket_get_filesystem_perms() { return 0644; }
	function noobrocket_url_to_path( $url ) { $GLOBALS['resolver_inputs'][] = $url; return $GLOBALS['resolver_result']; }
	function noobrocket_realpath( $path ) { return $path; }
	function wp_http_validate_url( $url ) { return 0 === strpos( $url, 'https://93.184.216.34/' ) || 0 === strpos( $url, home_url() . '/' ) ? $url : false; }
	function wp_remote_get( $url, $args = [] ) { $GLOBALS['requests'][] = [ $url, $args ]; return [ 'body' => $GLOBALS['remote_css'] ]; }
	function wp_safe_remote_get( $url, $args = [] ) { $args['reject_unsafe_urls'] = true; $GLOBALS['requests'][] = [ $url, $args ]; return [ 'body' => wp_http_validate_url( $url ) ? $GLOBALS['remote_css'] : '' ]; }
	function wp_remote_retrieve_body( $response ) { return $response['body'] ?? ''; }
	function wp_generate_uuid4() { return bin2hex( random_bytes( 16 ) ); }
	function wp_json_encode( $value ) { return json_encode( $value ); }
	function current_time() { return $GLOBALS['clock']; }
	function add_query_arg( $args, $url ) { return $url . ( $args ? '?' . http_build_query( $args ) : '' ); }
	class WP_Http {
		public static function make_absolute_url( $url, $base ) {
			if ( parse_url( $url, PHP_URL_SCHEME ) ) { return $url; }
			if ( 0 === strpos( $url, '//' ) ) { return parse_url( $base, PHP_URL_SCHEME ) . ':' . $url; }
			return rtrim( $base, '/' ) . '/' . ltrim( $url, '/' );
		}
	}
	function noobrocket_rrmdir( $path, $preserve = [], $filesystem = null ) {
		if ( ! is_dir( $path ) || in_array( $path, $preserve, true ) ) { return; }
		foreach ( new DirectoryIterator( $path ) as $file ) {
			if ( $file->isDot() ) { continue; }
			if ( $file->isDir() && ! $file->isLink() ) { noobrocket_rrmdir( $file->getPathname(), $preserve ); }
			else { unlink( $file->getPathname() ); }
		}
		rmdir( $path );
	}
	class WP_Filesystem_Direct {
		public $fail_write = false;
		public $partial_write = false;
		public $unreadable = [];
		public $reads = [];
		public function exists( $path ) { return file_exists( $path ); }
		public function is_readable( $path ) { return ! preg_match( '#^[a-z][a-z0-9+.-]*://#i', $path ) && ! in_array( $path, $this->unreadable, true ) && is_readable( $path ); }
		public function is_dir( $path ) { return is_dir( $path ); }
		public function is_file( $path ) { return is_file( $path ); }
		public function is_writable( $path ) { return is_writable( $path ); }
		public function size( $path ) { clearstatcache( true, $path ); return filesize( $path ); }
		public function get_contents( $path ) { $this->reads[] = $path; return $this->is_readable( $path ) ? file_get_contents( $path ) : false; }
		public function put_contents( $path, $bytes, $mode ) {
			if ( $this->fail_write ) { return false; }
			file_put_contents( $path, $this->partial_write ? substr( $bytes, 0, 1 ) : $bytes );
			chmod( $path, $mode );
			return true;
		}
		public function delete( $path ) { return unlink( $path ); }
	}
	$root = dirname( __DIR__ ) . '/noobrocket/inc/';
	foreach ( [
		'classes/event-management/subscriber-interface.php', 'classes/admin/class-options-data.php',
		'Logger/LoggerAware.php', 'Logger/LoggerAwareInterface.php', 'Engine/Optimization/RegexTrait.php',
		'Engine/Optimization/UrlTrait.php', 'Engine/Optimization/CSSTrait.php', 'Engine/Support/CommentTrait.php',
		'Dependencies/PathConverter/ConverterInterface.php', 'Dependencies/PathConverter/Converter.php',
		'Dependencies/Psr/SimpleCache/CacheInterface.php', 'Engine/Common/Cache/CacheInterface.php',
		'Engine/Common/Cache/FilesystemCache.php', 'Engine/Common/Context/ContextInterface.php',
		'Engine/Common/ExtractCSS/Subscriber.php',
		'Engine/Media/Lazyload/CSS/Data/LazyloadedContent.php', 'Engine/Media/Lazyload/CSS/Data/ProtectedContent.php',
		'Engine/Media/Lazyload/CSS/Data/LazyloadCSSContentFactory.php',
		'Engine/Media/Lazyload/CSS/Front/Extractor.php', 'Engine/Media/Lazyload/CSS/Front/RuleFormatter.php',
		'Engine/Media/Lazyload/CSS/Front/MappingFormatter.php', 'Engine/Media/Lazyload/CSS/Front/TagGenerator.php',
		'Engine/Media/Lazyload/CSS/Front/FileResolver.php', 'Engine/Media/Lazyload/CSS/Front/ContentFetcher.php',
		'Engine/Media/Lazyload/CSS/Subscriber.php',
	] as $file ) { require $root . $file; }
	class ControlledFetcher extends NoobRocket\Engine\Media\Lazyload\CSS\Front\ContentFetcher {
		public $content;
		public $calls = 0;
		public function __construct( $filesystem ) {
			parent::__construct( $filesystem );
			$this->content = '.hero{width:64px;height:64px;background-image:url(' . home_url() . '/hero.png);color:red}';
		}
		public function fetch( string $path, string $destination ) { ++$this->calls; return $this->content; }
	}
	class AllowedContext implements NoobRocket\Engine\Common\Context\ContextInterface {
		public function is_allowed( array $data = [] ): bool { return true; }
	}
	$temp = str_replace( '\\', '/', sys_get_temp_dir() ) . '/cache-cache-background-' . bin2hex( random_bytes( 8 ) );
	mkdir( $temp );
	define( 'WP_CONTENT_DIR', $temp );
	define( 'WP_CONTENT_URL', home_url() . '/wp-content' );
	define( 'NOOBROCKET_CACHE_ROOT_PATH', $temp . '/cache/' );
	$filesystem = new WP_Filesystem_Direct();
	$cache = new NoobRocket\Engine\Common\Cache\FilesystemCache( 'background-css', $filesystem );
	$fetcher = new ControlledFetcher( $filesystem );
	function owner( $cache, $fetcher, $filesystem ) {
		$owner = new NoobRocket\Engine\Media\Lazyload\CSS\Subscriber(
			new NoobRocket\Engine\Media\Lazyload\CSS\Front\Extractor(),
			new NoobRocket\Engine\Media\Lazyload\CSS\Front\RuleFormatter(),
			new NoobRocket\Engine\Media\Lazyload\CSS\Front\FileResolver(), $cache,
			new NoobRocket\Engine\Media\Lazyload\CSS\Front\MappingFormatter(),
			new NoobRocket\Engine\Media\Lazyload\CSS\Front\TagGenerator(), $fetcher, new AllowedContext(),
			new NoobRocket\Admin\Options_Data( [ 'lazyload_css_bg_img' => 1 ] ),
			new NoobRocket\Engine\Media\Lazyload\CSS\Data\LazyloadCSSContentFactory(), $filesystem
		);
		$owner->set_logger( new NoobRocket\Logger\Logger() );
		return $owner;
	}
	$owner = owner( $cache, $fetcher, $filesystem );
	$extract = new NoobRocket\Engine\Common\ExtractCSS\Subscriber();
	$extract->set_logger( new NoobRocket\Logger\Logger() );
	$filters['noobrocket_generate_lazyloaded_css'] = [
		[ $extract, 'extract_css_files_from_html' ], [ $extract, 'extract_inline_css_from_html' ],
		[ $owner, 'create_lazy_css_files' ], [ $owner, 'create_lazy_inline_css' ], [ $owner, 'add_lazy_tag' ],
	];
	$base_source = home_url() . '/css/main.css';
	$source = $base_source . '?ver=7&theme=light';
	$html = '<!doctype html><html><head><link rel="stylesheet" href="' . $source . '"></head><body><div class="hero"></div></body></html>';
	function stylesheet( string $html ): string { preg_match( '/href="([^"]+)"/', $html, $match ); return $match[1] ?? ''; }
	function delivered_css( $cache, string $url ): string {
		$path = WP_CONTENT_DIR . substr( parse_url( $url, PHP_URL_PATH ), strlen( '/wp-content' ) );
		return file_get_contents( $path );
	}
	try {
		$real_fetcher = new NoobRocket\Engine\Media\Lazyload\CSS\Front\ContentFetcher( $filesystem );
		$real_owner = owner( $cache, $real_fetcher, $filesystem );
		$fetch_content = new ReflectionMethod( $real_fetcher, 'fetch_content' );
		$fetch_content->setAccessible( true );
		foreach ( [ 'http://127.0.0.1/private.css', 'http://127.0.0.1/private.php', 'file:///private/styles.css', 'ftp://93.184.216.34/styles.css', 'data:text/css,.private{color:red}' ] as $unsafe ) {
			$filesystem->reads = [];
			$requests = [];
			check( ! $fetch_content->invoke( $real_fetcher, $unsafe ) && ! $filesystem->reads, 'unsafe stylesheet never enters filesystem reads: ' . $unsafe );
			foreach ( $requests as [ $requested_url, $args ] ) { check( ! empty( $args['reject_unsafe_urls'] ), 'remote stylesheet uses safe HTTP policy' ); }
		}
		$resolver = new NoobRocket\Engine\Media\Lazyload\CSS\Front\FileResolver();
		foreach ( [ 'file:///private/styles.css', 'ftp://93.184.216.34/styles.css', 'data:text/css,.private{color:red}', 'C:/private/styles.css' ] as $unsafe ) {
			$resolver_inputs = [];
			$unsafe_html = '<html><head><link rel="stylesheet" href="' . $unsafe . '"></head></html>';
			$unsafe_output = $real_owner->create_lazy_css_files( [ 'html' => $unsafe_html, 'css_files' => [ $unsafe ] ] );
			check( $unsafe_html === $unsafe_output['html'] && ! $resolver_inputs, 'unsupported source scheme stops before filesystem resolution' );
		}
		$resolver_result = $temp . '/dynamic.PHP';
		check( '' === $resolver->resolve( home_url() . '/dynamic.PHP?theme=dark' ), 'dynamic PHP stylesheet retains URL fetching rather than PHP source bytes' );
		$resolver_result = $temp . '/local.css';
		file_put_contents( $resolver_result, '.local{background-image:url(https://images.example.test/local.png);color:blue}' );
		foreach ( [ 'dynamic.PHP?theme=nested', '../styles.PHP?theme=nested', 'local.css', '?theme=nested' ] as $relative_source ) {
			$resolver_inputs = [];
			$requests = [];
			$relative_html = '<html><head><link rel="stylesheet" href="' . $relative_source . '"></head></html>';
			$relative_output = $real_owner->create_lazy_css_files( [ 'html' => $relative_html, 'css_files' => [ $relative_source ] ] );
			check( $relative_html === $relative_output['html'] && ! $resolver_inputs && ! $requests, 'document-relative stylesheet keeps browser delivery despite a conflicting readable root file' );
		}
		$filesystem->reads = [];
		$requests = [];
		check( false !== strpos( $real_fetcher->fetch( $resolver_result, $temp . '/generated.css' ), 'color:blue' ) && [ $resolver_result ] === $filesystem->reads && ! $requests, 'resolved local stylesheet still uses the local filesystem' );
		foreach ( [ home_url() . '/dynamic.PHP?theme=dark&amp;size=2', '/dynamic.PHP?theme=light&amp;size=3', '//93.184.216.34/styles.css?theme=cdn' ] as $dynamic_url ) {
			$resolver_result = false !== strpos( $dynamic_url, '.PHP' ) ? $temp . '/dynamic.PHP' : false;
			$requests = [];
			$input = '<html><head><link rel="stylesheet" href="' . $dynamic_url . '"></head></html>';
			$output = $real_owner->create_lazy_css_files( [ 'html' => $input, 'css_files' => [ $dynamic_url ] ] );
			$expected_url = WP_Http::make_absolute_url( html_entity_decode( $dynamic_url, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), home_url( '/' ) );
			check( stylesheet( $output['html'] ) !== $dynamic_url && 1 === count( $requests ) && $requests[0][0] === $expected_url && ! empty( $requests[0][1]['reject_unsafe_urls'] ), 'actual generation preserves PHP/query/CDN URL with safe fetching' );
		}
		$resolver_result = false;
		$requests = [];
		$unsafe_url = 'http://127.0.0.1/blocked.css';
		$unsafe_html = '<html><head><link rel="stylesheet" href="' . $unsafe_url . '"></head></html>';
		$unsafe_output = $real_owner->create_lazy_css_files( [ 'html' => $unsafe_html, 'css_files' => [ $unsafe_url ] ] );
		check( $unsafe_html === $unsafe_output['html'] && ! $unsafe_output['lazyloaded_images'], 'rejected remote stylesheet retains original HTML and no lazy mapping' );
		$resolver_result = '/controlled/main.css';
		$filesystem->reads = [];
		$reference_url = $cache->generate_url( $base_source );
		$constant_overrides['WP_CONTENT_DIR'] = str_replace( '/', '\\', WP_CONTENT_DIR );
		$filter_url = null;
		$filters['noobrocket_css_url'][] = static function ( $url ) use ( &$filter_url ) {
			$filter_url = $url;
			return str_replace( home_url(), 'https://cdn.test', $url );
		};
		$mixed_url = $cache->generate_url( $base_source );
		check( $reference_url === $filter_url, 'mixed Windows path separators resolve the same public URL before filtering' );
		check( str_replace( home_url(), 'https://cdn.test', $reference_url ) === $mixed_url, 'normalized cache URL preserves the existing delivery filter' );
		array_pop( $filters['noobrocket_css_url'] );
		$constant_overrides = [];
		$source_css = $fetcher->content;
		$first = $owner->maybe_replace_css_images( $html );
		$clock = 200;
		$second = $owner->maybe_replace_css_images( $html );
		check( stylesheet( $first ) === stylesheet( $second ), 'identical generated CSS reuses its URL across page clocks' );
		check( 1 === $fetcher->calls, 'ready pointer avoids refetching and regenerating CSS' );
		parse_str( parse_url( stylesheet( $second ), PHP_URL_QUERY ), $query );
		check( [ 'ver' => '7', 'theme' => 'light' ] === $query, 'original query parameters survive without a per-page timestamp' );
		$css = delivered_css( $cache, stylesheet( $first ) );
		check( false !== strpos( $css, 'var(--noobrocket-bg-' ) && false !== strpos( $first, home_url() . '/hero.png' ), 'delivered CSS and HTML carry matching background mapping' );
		check( 1 === substr_count( $first, 'id="noobrocket-lazyload-bg-container"' ), 'one real-head mapping container is delivered' );
		$reads = $filesystem->reads;
		check( ! array_filter( $reads, static function ( $path ) { return '.css' === substr( $path, -4 ); } ), 'ready hits do not read or hash full stylesheet bytes' );
		$pages = [ 'first' => $first, 'second' => $second ];
		$variant_urls = [ 'light' => stylesheet( $first ) ];
		$variant_fetches = $fetcher->calls;
		for ( $index = 0; $index < 6; ++$index ) {
			$theme = $index % 2 ? 'dark' : 'light';
			$fetcher->content = 'dark' === $theme ? str_replace( 'color:red', 'color:black', $source_css ) : $source_css;
			$variant_html = str_replace( 'theme=light', 'theme=' . $theme, $html );
			$variant = $owner->maybe_replace_css_images( $variant_html );
			if ( isset( $variant_urls[ $theme ] ) ) {
				check( $variant_urls[ $theme ] === stylesheet( $variant ), 'alternating ' . $theme . ' query variant reuses its publication' );
			} else { $variant_urls[ $theme ] = stylesheet( $variant ); }
			$expected_color = 'dark' === $theme ? 'color:black' : 'color:red';
			check( false !== strpos( delivered_css( $cache, stylesheet( $variant ) ), $expected_color ), 'query variant retains its own stylesheet content' );
			$pages[ 'variant-' . $index ] = $variant;
		}
		check( $fetcher->calls === $variant_fetches + 1, 'six alternating variants prepare only the one previously missing stylesheet' );
		$fetcher->content = $source_css;
		foreach ( [ 'uppercase' => str_replace( [ '<head>', '</head>' ], [ '<HEAD>', '</HEAD>' ], $html ),
			'comment' => str_replace( '</head>', '<!-- </head> --></head>', $html ),
			'raw text' => str_replace( '</head>', '<script>const text="</head>"</script></head>', $html ),
			'attribute' => str_replace( '<head>', '<head data-text="</head>">', $html ),
			'template' => str_replace( '</head>', '<template><head></head></template></head>', $html ),
		] as $name => $input ) {
			$output = $owner->maybe_replace_css_images( $input );
			check( 1 === substr_count( $output, 'id="noobrocket-lazyload-bg-container"' ), $name . ' selects exactly one real head' );
			$pages[ $name ] = $output;
		}
		foreach ( [ '<div class="hero"><style>.hero{background:url(hero.png)}</style></div>',
			'<html><body><!-- </head> --><style>.hero{background:url(hero.png)}</style></body></html>',
			'<html><head data-text="unfinished </head>',
		] as $input ) { check( $input === $owner->maybe_replace_css_images( $input ), 'no usable head preserves original markup and backgrounds' ); }
		$headless = '<html><body><link rel="stylesheet" href="' . $source . '"><div class="hero"></div></body></html>';
		check( $headless === $owner->maybe_replace_css_images( $headless ), 'headless linked CSS retains original delivery' );
		$pages['headless'] = $headless;
		$inline = '<html><head><style>' . $source_css . '</style></head><body><div class="hero"></div></body></html>';
		$pages['inline'] = $owner->maybe_replace_css_images( $inline );
		check( false !== strpos( $pages['inline'], 'var(--noobrocket-bg-' ), 'inline CSS also retains matching recovery mapping' );
		$pointer_method = new ReflectionMethod( $owner, 'get_mapping_file_url' );
		$pointer_method->setAccessible( true );
		$pointer_key = $pointer_method->invoke( $owner, $source );
		$pointer = $cache->generate_path( $pointer_key );
		$record = json_decode( file_get_contents( $pointer ), true );
		check( is_array( $record ) && isset( $record['revision'], $record['mapping'] ), 'publication record identifies immutable CSS and its mapping' );
		check( $record['revision'] === hash( 'sha256', $css ), 'stored revision covers all generated CSS bytes' );
		$entity_html = str_replace( $source, $base_source . '?ver=7&amp;theme=light#sheet', $html );
		$entity_output = $owner->maybe_replace_css_images( $entity_html );
		parse_str( parse_url( stylesheet( $entity_output ), PHP_URL_QUERY ), $entity_query );
		check( [ 'ver' => '7', 'theme' => 'light' ] === $entity_query, 'HTML-escaped source query preserves browser-visible arguments' );
		$fragment_html = str_replace( $source, $base_source . '#sheet', $html );
		$fragment_output = $owner->maybe_replace_css_images( $fragment_html );
		check( (bool) preg_match( '/\.[a-f0-9]{64}\.css$/', parse_url( stylesheet( $fragment_output ), PHP_URL_PATH ) ), 'source fragment cannot swallow the immutable filename revision' );
		check( false !== strpos( delivered_css( $cache, stylesheet( $fragment_output ) ), 'var(--noobrocket-bg-' ), 'fragment stylesheet still resolves complete generated CSS' );
		$remove_head = static function ( $data ) { $data['html'] = preg_replace( '#<head>.*?</head>#s', '', $data['html'] ); return $data; };
		$filters['noobrocket_generate_lazyloaded_css'][] = $remove_head;
		check( $html === $owner->maybe_replace_css_images( $html ), 'late filter removing recovery head retains complete original HTML' );
		array_pop( $filters['noobrocket_generate_lazyloaded_css'] );
		$legacy_path = $cache->generate_path( $base_source );
		$legacy_pointer = $cache->generate_path( $base_source . '.json' );
		$legacy_mapping = json_encode( $record['mapping'] );
		file_put_contents( $legacy_path, $css );
		file_put_contents( $legacy_pointer, $legacy_mapping );
		$cache->delete( $pointer_key );
		$migrated = $owner->maybe_replace_css_images( $html );
		check( stylesheet( $migrated ) !== $source && isset( json_decode( file_get_contents( $pointer ), true )['revision'] ), 'readable legacy mapping regenerates into a complete new publication' );
		check( $css === file_get_contents( $legacy_path ), 'migration retains legacy CSS for previously cached pages' );
		check( $legacy_mapping === file_get_contents( $legacy_pointer ), 'new publication leaves legacy array-only metadata unchanged for rollback' );
		$old_reader = new NoobRocket\Engine\Media\Lazyload\CSS\Front\TagGenerator();
		$legacy_tags = $old_reader->generate( json_decode( file_get_contents( $legacy_pointer ), true ) );
		check( false !== strpos( $legacy_tags, $record['mapping'][0]['style'] ), 'legacy tag reader can still consume its preserved mapping after migration' );
		$cache->delete( $pointer_key );
		$fetcher->content = str_replace( 'color:red', 'color:blue', $fetcher->content );
		$changed = $owner->maybe_replace_css_images( $html );
		check( stylesheet( $changed ) !== stylesheet( $first ), 'non-background CSS change gets a fresh immutable URL' );
		check( $css === delivered_css( $cache, stylesheet( $first ) ), 'previously rendered pages retain their exact CSS bytes' );
		$changed_record = json_decode( file_get_contents( $pointer ), true );
		$asset_path = WP_CONTENT_DIR . substr( parse_url( stylesheet( $changed ), PHP_URL_PATH ), strlen( '/wp-content' ) );
		$versioned = str_replace( '?ver=7&theme=light', '?ver=8&theme=light', $html );
		$fetcher->content = str_replace( 'color:blue', 'color:purple', $fetcher->content );
		$next_version = $owner->maybe_replace_css_images( $versioned );
		check( stylesheet( $next_version ) !== stylesheet( $changed ), 'source query version change regenerates rather than reusing an old pointer' );
		check( false !== strpos( delivered_css( $cache, stylesheet( $next_version ) ), 'color:purple' ), 'new source version delivers its current CSS bytes' );
		foreach ( [ 'missing' => null, 'empty' => '', 'corrupt' => '{broken', 'legacy' => json_encode( $changed_record['mapping'] ),
			'bad revision' => json_encode( [ 'source' => $source, 'revision' => '../escape', 'mapping' => $changed_record['mapping'] ] ),
			'bad mapping' => json_encode( [ 'source' => $source, 'revision' => $changed_record['revision'], 'mapping' => [ [] ] ] ),
		] as $name => $bytes ) {
			if ( null === $bytes ) { unlink( $pointer ); } else { file_put_contents( $pointer, $bytes ); }
			$fetcher->content = '';
			check( stylesheet( $owner->maybe_replace_css_images( $html ) ) === $source, $name . ' record with unavailable regeneration retains original CSS' );
		}
		file_put_contents( $pointer, json_encode( $changed_record ) );
		$filesystem->unreadable[] = $pointer;
		check( stylesheet( $owner->maybe_replace_css_images( $html ) ) === $source, 'unreadable pointer retains original CSS' );
		$filesystem->unreadable = [];
		foreach ( [ 'missing', 'empty', 'unreadable', 'directory' ] as $name ) {
			$saved = file_get_contents( $asset_path );
			if ( 'missing' === $name ) { unlink( $asset_path ); }
			if ( 'empty' === $name ) { file_put_contents( $asset_path, '' ); }
			if ( 'unreadable' === $name ) { $filesystem->unreadable[] = $asset_path; }
			if ( 'directory' === $name ) { unlink( $asset_path ); mkdir( $asset_path ); }
			check( stylesheet( $owner->maybe_replace_css_images( $html ) ) === $source, $name . ' immutable CSS retains original stylesheet' );
			$filesystem->unreadable = [];
			if ( 'directory' === $name ) { rmdir( $asset_path ); }
			file_put_contents( $asset_path, $saved );
		}
		$fetcher->content = str_replace( 'color:red', 'color:green', $source_css );
		foreach ( [ 'write', 'partial', 'rename' ] as $name ) {
			$filesystem->fail_write = 'write' === $name;
			$filesystem->partial_write = 'partial' === $name;
			$fail_rename = 'rename' === $name;
			check( ! $cache->set( 'https://site.test/atomic.json', '{"complete":true}' ), $name . ' publication fails honestly' );
			check( ! file_exists( $cache->generate_path( 'https://site.test/atomic.json' ) ), $name . ' failure does not publish partial bytes' );
			$filesystem->fail_write = false; $filesystem->partial_write = false; $fail_rename = false;
		}
		check( $cache->set( 'https://site.test/atomic.json', 'old' ), 'initial complete atomic record publishes' );
		$filesystem->partial_write = true;
		check( ! $cache->set( 'https://site.test/atomic.json', 'replacement' ) && 'old' === $cache->get( 'https://site.test/atomic.json' ), 'failed replacement preserves previous complete record' );
		$filesystem->partial_write = false;
		$cache->delete( $pointer_key );
		// Fail just the pointer publication after the immutable CSS is complete.
		$fail_pointer = null;
		$fail_pointer = static function ( $from, $to ) use ( $pointer, &$fail_pointer ) {
			if ( $to === $pointer ) { $GLOBALS['fail_rename'] = true; }
			else { $GLOBALS['before_rename'] = $fail_pointer; }
		};
		$before_rename = $fail_pointer;
		$pages['failed publication'] = $owner->maybe_replace_css_images( $html );
		check( stylesheet( $pages['failed publication'] ) === $source, 'failed pointer publication retains original stylesheet' );
		$before_rename = null; $fail_rename = false;
		$other = owner( $cache, $fetcher, $filesystem );
		$nested = null;
		$before_rename = static function ( $from, $to ) use ( $other, $html, $source, &$nested ) {
			$nested = $other->create_lazy_css_files( [ 'html' => $html, 'css_files' => [ $source ] ] );
		};
		$outer = $owner->create_lazy_css_files( [ 'html' => $html, 'css_files' => [ $source ] ] );
		foreach ( [ 'outer' => $outer, 'nested' => $nested ] as $name => $output ) {
			check( is_array( $output ) && ! empty( $output['lazyloaded_images'] ), $name . ' overlapping writer has its own mapping' );
			$bytes = delivered_css( $cache, stylesheet( $output['html'] ) );
			foreach ( $output['lazyloaded_images'] as $mapping ) { check( false !== strpos( $bytes, '--noobrocket-bg-' . $mapping['hash'] ), $name . ' CSS matches its exact mapping under overlapping publication' ); }
			$pages[ $name ] = $owner->add_lazy_tag( $output )['html'];
		}
		$assets = [];
		foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $cache->get_root_path(), FilesystemIterator::SKIP_DOTS ) ) as $file ) {
			check( 0 !== strpos( $file->getFilename(), '.nbr-css-' ), 'completed and failed publication removes every temporary file' );
			if ( '.css' === substr( $file->getFilename(), -4 ) ) {
				$assets[ '/wp-content' . substr( str_replace( '\\', '/', $file->getPathname() ), strlen( WP_CONTENT_DIR ) ) ] = file_get_contents( $file->getPathname() );
			}
		}
		$cache->clear();
		check( ! is_dir( $cache->get_root_path() ), 'existing domain purge removes pointer, immutable outputs and temporary files' );
		if ( $browser_origin ) { echo wp_json_encode( [ 'pages' => $pages, 'assets' => $assets, 'original' => $source_css ] ); }
		else { echo 'PASS: stylesheet-background publication (' . $checks . ' assertions; actual owners, modeled hooks, owned filesystem).' . PHP_EOL; }
	} finally { noobrocket_rrmdir( $temp ); }
}
