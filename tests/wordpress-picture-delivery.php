<?php
/**
 * Native image delivery checks with temporary local derivatives and unchanged settings.
 * php tests/wordpress-picture-delivery.php /absolute/path/to/disposable/wordpress [empty-export-directory]
 */
$wordpress = isset( $argv[1] ) ? realpath( $argv[1] ) : false;
if ( ! $wordpress || ! is_file( $wordpress . '/wp-load.php' ) || ! is_file( $wordpress . '/.noobrocket-acceptance-site' ) ) {
	fwrite( STDERR, "Provide a disposable WordPress installation with the .noobrocket-acceptance-site marker.\n" );
	exit( 2 );
}
$export = isset( $argv[2] ) ? realpath( $argv[2] ) : false;
if ( isset( $argv[2] ) && ( ! $export || ! is_dir( $export ) || count( scandir( $export ) ) > 2 ) ) {
	fwrite( STDERR, "The optional browser export directory must exist and be empty.\n" );
	exit( 2 );
}
putenv( 'NOOBROCKET_TEST_PHASE=picture-delivery' );
$_SERVER['HTTP_HOST'] = '127.0.0.1:8765';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'POST';
define( 'WP_ADMIN', true );
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
require $wordpress . '/wp-load.php';
http_response_code( 200 );

$checks = [];
function picture_delivery_check( $condition, $message ) {
	global $checks;
	if ( ! $condition ) {
		throw new RuntimeException( 'FAIL: ' . $message );
	}
	$checks[] = $message;
}
function picture_delivery_sources( $html ) {
	$sources = [];
	$tags = new WP_HTML_Tag_Processor( $html );
	while ( $tags->next_tag( 'SOURCE' ) ) {
		$sources[] = [
			'type' => $tags->get_attribute( 'type' ),
			'srcset' => $tags->get_attribute( 'srcset' ),
			'sizes' => $tags->get_attribute( 'sizes' ),
			'lazy_srcset' => $tags->get_attribute( 'data-lazy-srcset' ),
		];
	}
	return $sources;
}

$fixture = tempnam( sys_get_temp_dir(), 'noobrocket-picture-test-' );
unlink( $fixture );
mkdir( $fixture );
mkdir( $fixture . '/images' );
mkdir( $fixture . '/config' );
// Tiny decodable assets. AVIF is WPT images/green.avif; JPEG/WebP are canvas exports.
$image_bytes = [
	'jpg' => base64_decode( '/9j/4AAQSkZJRgABAQAAAQABAAD/4gHYSUNDX1BST0ZJTEUAAQEAAAHIAAAAAAQwAABtbnRyUkdCIFhZWiAH4AABAAEAAAAAAABhY3NwAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAQAA9tYAAQAAAADTLQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAlkZXNjAAAA8AAAACRyWFlaAAABFAAAABRnWFlaAAABKAAAABRiWFlaAAABPAAAABR3dHB0AAABUAAAABRyVFJDAAABZAAAAChnVFJDAAABZAAAAChiVFJDAAABZAAAAChjcHJ0AAABjAAAADxtbHVjAAAAAAAAAAEAAAAMZW5VUwAAAAgAAAAcAHMAUgBHAEJYWVogAAAAAAAAb6IAADj1AAADkFhZWiAAAAAAAABimQAAt4UAABjaWFlaIAAAAAAAACSgAAAPhAAAts9YWVogAAAAAAAA9tYAAQAAAADTLXBhcmEAAAAAAAQAAAACZmYAAPKnAAANWQAAE9AAAApbAAAAAAAAAABtbHVjAAAAAAAAAAEAAAAMZW5VUwAAACAAAAAcAEcAbwBvAGcAbABlACAASQBuAGMALgAgADIAMAAxADb/2wBDAAMCAgICAgMCAgIDAwMDBAYEBAQEBAgGBgUGCQgKCgkICQkKDA8MCgsOCwkJDRENDg8QEBEQCgwSExIQEw8QEBD/2wBDAQMDAwQDBAgEBAgQCwkLEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBD/wAARCAACAAIDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAj/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAABwj/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCcgAem9//Z' ),
	'webp' => base64_decode( 'UklGRhwCAABXRUJQVlA4WAoAAAAgAAAAAQAAAQAASUNDUMgBAAAAAAHIAAAAAAQwAABtbnRyUkdCIFhZWiAH4AABAAEAAAAAAABhY3NwAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAQAA9tYAAQAAAADTLQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAlkZXNjAAAA8AAAACRyWFlaAAABFAAAABRnWFlaAAABKAAAABRiWFlaAAABPAAAABR3dHB0AAABUAAAABRyVFJDAAABZAAAAChnVFJDAAABZAAAAChiVFJDAAABZAAAAChjcHJ0AAABjAAAADxtbHVjAAAAAAAAAAEAAAAMZW5VUwAAAAgAAAAcAHMAUgBHAEJYWVogAAAAAAAAb6IAADj1AAADkFhZWiAAAAAAAABimQAAt4UAABjaWFlaIAAAAAAAACSgAAAPhAAAts9YWVogAAAAAAAA9tYAAQAAAADTLXBhcmEAAAAAAAQAAAACZmYAAPKnAAANWQAAE9AAAApbAAAAAAAAAABtbHVjAAAAAAAAAAEAAAAMZW5VUwAAACAAAAAcAEcAbwBvAGcAbABlACAASQBuAGMALgAgADIAMAAxADZWUDggLgAAANABAJ0BKgIAAgABQCYloAJ0ugH4AAOwAP7zaZf+bAgZofTB/6aR40jxpHymgAA=' ),
	'avif' => base64_decode( 'AAAAIGZ0eXBhdmlmAAAAAGF2aWZtaWYxbWlhZk1BMUIAAAD5bWV0YQAAAAAAAAAvaGRscgAAAAAAAAAAcGljdAAAAAAAAAAAAAAAAFBpY3R1cmVIYW5kbGVyAAAAAA5waXRtAAAAAAABAAAAHmlsb2MAAAAARAAAAQABAAAAAQAAASEAAAAfAAAAKGlpbmYAAAAAAAEAAAAaaW5mZQIAAAAAAQAAYXYwMUNvbG9yAAAAAGppcHJwAAAAS2lwY28AAAAUaXNwZQAAAAAAAAACAAAAAgAAABBwaXhpAAAAAAMICAgAAAAMYXYxQ4EADAAAAAATY29scm5jbHgAAgACAAIAAAAAF2lwbWEAAAAAAAAAAQABBAECgwQAAAAnbWRhdAoJAAAAAAZ//MAgMhIQAMAAAAKAAAIDUu3nF/L+deA=' ),
];
$files = [
	'hero.jpg', 'hero.avif', 'hero.webp', 'small.jpg', 'small.avif', 'small.webp', 'large.jpg', 'large.avif', 'large.webp',
	'original.jpg', 'appended.jpg', 'appended.jpg.avif', 'appended.jpg.webp',
	'partial-small.jpg', 'partial-small.avif', 'partial-small.webp', 'partial-large.jpg', 'partial-large.webp',
	'avif-only.jpg', 'avif-only.avif', 'directory.jpg',
];
foreach ( $files as $file ) {
	file_put_contents( $fixture . '/images/' . $file, $image_bytes[ pathinfo( $file, PATHINFO_EXTENSION ) ] );
}
mkdir( $fixture . '/images/directory.webp' );
file_put_contents( $fixture . '/outside.jpg', $image_bytes['jpg'] );
file_put_contents( $fixture . '/outside.webp', $image_bytes['webp'] );
file_put_contents( $fixture . '/config/picture-cache.test.php', '<?php $noobrocket_cache_webp = 0; $noobrocket_cache_reject_cookies = ""; $noobrocket_cache_ignored_parameters = []; $noobrocket_config_complete = true;' );
$url = 'http://picture-images.test/media/';
$local_url = site_url( '/wp-content/noobrocket-picture-fixture/' );
$local_path = wp_parse_url( $local_url, PHP_URL_PATH );
$associations = function ( $paths ) use ( $fixture, $local_url ) {
	return [ '//picture-images.test/media/' => $fixture . '/images/', preg_replace( '#^https?:#', '', $local_url ) => $fixture . '/images/' ] + $paths;
};
add_filter( 'noobrocket_url_to_path_associations', $associations );
$cdn_enabled = static function () { return 1; };
// The live CDN bridge reads published settings; this fixture supplies its own CDN instance.
add_filter( 'pre_get_noobrocket_option_cdn', $cdn_enabled, PHP_INT_MAX );
$original_accept = $_SERVER['HTTP_ACCEPT'] ?? null;
$original_cache_state = [];
foreach ( [ \NoobRocket\Buffer\Config::class => [ 'memoized', 'config_dir_path', 'server' ], \NoobRocket\Buffer\Tests::class => [ 'memoized' ] ] as $class => $properties ) {
	foreach ( $properties as $name ) {
		$property = new ReflectionProperty( $class, $name );
		$property->setAccessible( true );
		$original_cache_state[] = [ $property, $property->getValue() ];
	}
}

try {
	$converter = new \NoobRocket\Engine\Media\Picture\Subscriber( new \NoobRocket\Admin\Options_Data( [ 'image_delivery' => 1 ] ) );
	$plain = '<img id="plain" src="' . $url . 'hero.jpg" width="960" height="540" alt="Hero &amp; text" class="hero" loading="eager" fetchpriority="high" decoding="async" data-custom="preserved">';
	$converted = $converter->convert( $plain );
	$sources = picture_delivery_sources( $converted );
	picture_delivery_check( false !== strpos( $converted, $plain ), 'fallback image retains every original byte and attribute' );
	picture_delivery_check( 2 === count( $sources ) && [ 'image/avif', 'image/webp' ] === array_column( $sources, 'type' ), 'AVIF source precedes WebP' );
	$lcp = \NoobRocket\Engine\Media\Picture\Subscriber::synchronize_lcp( (object) [ 'type' => 'img', 'src' => $url . 'hero.jpg' ], $converted );
	picture_delivery_check( 'picture' === $lcp->type && 'image/avif' === $lcp->sources[0]->type, 'old image LCP records use delivered picture sources' );
	$controller = ( new ReflectionClass( \NoobRocket\Engine\Media\AboveTheFold\Frontend\Controller::class ) )->newInstanceWithoutConstructor();
	$preload = new ReflectionMethod( $controller, 'generate_lcp_link_tag_with_sources' );
	$preload->setAccessible( true );
	$links = $preload->invoke( $controller, $lcp );
	picture_delivery_check( 1 === substr_count( $links['tags'], '<link ' ) && false !== strpos( $links['tags'], 'type="image/avif"' ) && false !== strpos( $links['tags'], 'hero.avif' ) && false === strpos( $links['tags'], 'hero.jpg' ), 'LCP preload targets only the preferred MIME format without duplicate original downloads' );
	picture_delivery_check( $url . 'hero.avif' === $sources[0]['srcset'] && $url . 'hero.webp' === $sources[1]['srcset'], 'single-image sources reference existing local derivatives' );
	picture_delivery_check( $converted === $converter->convert( $converted ), 'second conversion preserves existing pictures' );
	picture_delivery_check( $plain === ( new \NoobRocket\Engine\Media\Picture\Subscriber( new \NoobRocket\Admin\Options_Data( [] ) ) )->convert( $plain ), 'default delivery leaves HTML untouched' );
	picture_delivery_check( $plain === ( new \NoobRocket\Engine\Media\Picture\Subscriber( new \NoobRocket\Admin\Options_Data( [ 'image_delivery' => 0 ] ) ) )->convert( $plain ), 'explicit off leaves HTML untouched' );
	$policy = noobrocket_free_settings( [ 'image_delivery' => 1, 'cache_webp' => 1 ] );
	picture_delivery_check( 1 === $policy['image_delivery'] && 0 === $policy['cache_webp'], 'native delivery disables legacy browser-specific WebP caching' );
	$events = $converter::get_subscribed_events();
	picture_delivery_check( isset( $events['noobrocket_buffer'] ) && [ 'convert', 1 ] === $events['noobrocket_buffer'], 'picture generation precedes CDN, LCP and lazyload' );
	$container = apply_filters( 'noobrocket_container', null );
	picture_delivery_check( $container->get( 'picture_subscriber' ) instanceof \NoobRocket\Engine\Media\Picture\Subscriber, 'production container registers the native delivery subscriber' );

	$responsive = '<img id="responsive" src="' . $url . 'hero.jpg" srcset="' . $url . 'small.jpg 480w, ' . $url . 'large.jpg 960w" sizes="100vw" width="960" height="540" alt="Responsive">';
	$responsive_output = $converter->convert( $responsive );
	$responsive_lcp = \NoobRocket\Engine\Media\Picture\Subscriber::synchronize_lcp( (object) [ 'type' => 'img-srcset', 'src' => $url . 'hero.jpg' ], $responsive_output );
	$responsive_links = $preload->invoke( $controller, $responsive_lcp );
	picture_delivery_check( false !== strpos( $responsive_links['tags'], 'imagesrcset=' ) && false !== strpos( $responsive_links['tags'], 'imagesizes="100vw"' ), 'LCP preload preserves responsive candidate selection' );
	$sources = picture_delivery_sources( $responsive_output );
	picture_delivery_check( false !== strpos( $responsive_output, $responsive ), 'responsive fallback src, srcset and sizes remain unchanged' );
	picture_delivery_check( $url . 'small.avif 480w, ' . $url . 'large.avif 960w' === $sources[0]['srcset'] && $url . 'small.webp 480w, ' . $url . 'large.webp 960w' === $sources[1]['srcset'], 'both complete source sets retain responsive width descriptors' );
	picture_delivery_check( [ '100vw', '100vw' ] === array_column( $sources, 'sizes' ), 'generated sources preserve responsive sizes' );
	$density = '<img src="' . $url . 'hero.jpg" srcset="' . $url . 'small.jpg 1x, ' . $url . 'large.jpg 2x" alt="Density">';
	$sources = picture_delivery_sources( $converter->convert( $density ) );
	picture_delivery_check( $url . 'small.avif 1x, ' . $url . 'large.avif 2x' === $sources[0]['srcset'], 'density srcset retains original descriptors' );
	$partial = '<img id="webp" src="' . $url . 'partial-small.jpg" srcset="' . $url . 'partial-small.jpg 480w, ' . $url . 'partial-large.jpg 960w" sizes="100vw" alt="Complete WebP only">';
	$partial_output = $converter->convert( $partial );
	$sources = picture_delivery_sources( $partial_output );
	picture_delivery_check( 1 === count( $sources ) && 'image/webp' === $sources[0]['type'] && $url . 'partial-small.webp 480w, ' . $url . 'partial-large.webp 960w' === $sources[0]['srcset'], 'incomplete AVIF set is omitted while complete WebP remains' );
	$missing = '<img id="fallback" src="' . $url . 'original.jpg" alt="No derivative">';
	picture_delivery_check( $missing === $converter->convert( $missing ), 'missing derivatives preserve original HTML' );
	$missing_candidate = '<img src="' . $url . 'hero.jpg" srcset="' . $url . 'small.jpg 480w, ' . $url . 'original.jpg 960w" alt="Missing responsive candidate">';
	picture_delivery_check( $missing_candidate === $converter->convert( $missing_candidate ), 'incomplete responsive sets never mix modern and original formats' );
	$appended = '<img src="' . $url . 'appended.jpg" alt="Appended derivative">';
	$sources = picture_delivery_sources( $converter->convert( $appended ) );
	picture_delivery_check( $url . 'appended.jpg.avif' === $sources[0]['srcset'] && $url . 'appended.jpg.webp' === $sources[1]['srcset'], 'appended derivative filenames are supported' );
	$sources = picture_delivery_sources( $converter->convert( '<img src="' . $url . 'avif-only.jpg" alt="AVIF only">' ) );
	picture_delivery_check( 1 === count( $sources ) && 'image/avif' === $sources[0]['type'], 'AVIF-only image keeps its original fallback' );
	$query = '<img src="' . $url . 'hero.jpg?v=2&amp;size=full#fragment" alt="Query">';
	$sources = picture_delivery_sources( $converter->convert( $query ) );
	picture_delivery_check( $url . 'hero.avif?v=2&size=full#fragment' === $sources[0]['srcset'] && false !== strpos( $converter->convert( $query ), $query ), 'queries, fragments and HTML encoding survive derivative delivery' );
	$root_relative = '<img src="' . $local_path . 'hero.jpg" alt="Root relative">';
	$sources = picture_delivery_sources( $converter->convert( $root_relative ) );
	picture_delivery_check( $local_path . 'hero.avif' === $sources[0]['srcset'], 'root-relative local URLs retain their URL form' );

	$protected = [
		'existing picture' => '<picture><source type="image/webp" srcset="' . $url . 'hero.webp">' . $plain . '</picture>',
		'noscript fallback' => '<noscript>' . $plain . '</noscript>',
		'inline JavaScript' => '<script>const html = \'' . $plain . '\';</script>',
		'inline stylesheet' => '<style>/* ' . $plain . ' */</style>',
		'HTML comment' => '<!-- ' . $plain . ' -->',
		'template' => '<template>' . $plain . '</template>',
		'SVG foreign content' => '<svg><foreignObject>' . $plain . '</foreignObject></svg>',
		'already lazy data-src' => '<img src="' . $url . 'hero.jpg" data-src="' . $url . 'large.jpg" alt="Third-party lazyload">',
		'already lazy data-lazy-src' => '<img src="' . $url . 'hero.jpg" data-lazy-src="' . $url . 'large.jpg" alt="Existing lazyload">',
		'attribute-contained markup' => '<div data-template="' . htmlspecialchars( $plain, ENT_QUOTES ) . '">Attribute text</div>',
		'raw attribute-contained markup' => "<div data-template='" . $plain . "'>Attribute text</div>",
	];
	foreach ( $protected as $name => $html ) {
		picture_delivery_check( $html === $converter->convert( $html ), $name . ' is untouched' );
	}
	$boundaries = [
		'external host' => 'https://external.test/media/hero.jpg',
		'host suffix' => 'http://picture-images.test.evil/media/hero.jpg',
		'URL credentials' => 'http://picture-images.test@external.test/media/hero.jpg',
		'path prefix' => 'http://picture-images.test/media-extra/hero.jpg',
		'traversal' => $url . '../outside.jpg',
		'encoded traversal' => $url . '%2e%2e/outside.jpg',
		'encoded separator traversal' => $url . '..%2foutside.jpg',
		'directory sidecar' => $url . 'directory.jpg',
	];
	foreach ( $boundaries as $name => $src ) {
		$html = '<img src="' . $src . '" alt="Boundary">';
		picture_delivery_check( $html === $converter->convert( $html ), $name . ' cannot produce a local derivative source' );
	}

	$cdn = new \NoobRocket\Engine\CDN\CDN( new \NoobRocket\Admin\Options_Data( [ 'cdn' => 1, 'cdn_cnames' => [ 'https://picture-cdn.test' ], 'cdn_zone' => [ 'images' ] ] ) );
	$cdn_original = '<img src="' . $local_url . 'hero.jpg" srcset="' . $local_url . 'small.jpg 480w, ' . $local_url . 'large.jpg 960w" sizes="100vw" width="960" height="540" alt="CDN">';
	$cdn_output = $cdn->rewrite_srcset( $cdn->rewrite( $converter->convert( $cdn_original ) ) );
	$sources = picture_delivery_sources( $cdn_output );
	picture_delivery_check( 2 === count( $sources ) && 0 === strpos( $sources[0]['srcset'], 'https://picture-cdn.test/' ) && 0 === strpos( $sources[1]['srcset'], 'https://picture-cdn.test/' ), 'actual CDN image zone rewrites AVIF and WebP sources' );
	picture_delivery_check( false !== strpos( $cdn_output, 'src="https://picture-cdn.test/' ) && false !== strpos( $cdn_output, 'large.avif 960w' ) && false !== strpos( $cdn_output, 'large.webp 960w' ), 'CDN fallback and full source sets share the configured host' );
	$lazy_original = str_replace( 'id="responsive"', 'id="lazy"', $responsive );
	$lazy_image = new \NoobRocket\Dependencies\NoobRocketLazyload\Image();
	$lazy_picture = $converter->convert( $lazy_original );
	$lazy_output = $lazy_image->lazyloadPictures( $lazy_picture, $lazy_picture );
	$sources = picture_delivery_sources( $lazy_output );
	picture_delivery_check( [ null, null ] === array_column( $sources, 'srcset' ) && $url . 'small.avif 480w, ' . $url . 'large.avif 960w' === $sources[0]['lazy_srcset'], 'actual lazyload defers generated source sets' );
	picture_delivery_check( false !== strpos( $lazy_output, 'data-lazy-src="' . $url . 'hero.jpg"' ) && false !== strpos( $lazy_output, '<noscript>' . $lazy_original . '</noscript>' ), 'actual lazyload retains the original noscript fallback' );
	picture_delivery_check( $lazy_output === $converter->convert( $lazy_output ), 'later conversion leaves lazyload output untouched' );

	$accepts = [ 'text/html', 'text/html,image/webp', 'text/html,image/avif,image/webp' ];
	$input = '<html><body>' . $responsive . $missing . str_repeat( '<p>Native image cache fixture.</p>', 12 ) . '</body></html>';
	$expected_output = $converter->convert( $input );
	foreach ( $accepts as $accept ) {
		$_SERVER['HTTP_ACCEPT'] = $accept;
		foreach ( [ \NoobRocket\Buffer\Config::class, \NoobRocket\Buffer\Tests::class ] as $class ) {
			$property = new ReflectionProperty( $class, 'memoized' );
			$property->setAccessible( true );
			$property->setValue( null, [] );
		}
		foreach ( [ 'config_dir_path', 'server' ] as $name ) {
			$property = new ReflectionProperty( \NoobRocket\Buffer\Config::class, $name );
			$property->setAccessible( true );
			$property->setValue( null, null );
		}
		$config = new \NoobRocket\Buffer\Config( [ 'config_dir_path' => $fixture . '/config', 'server' => [ 'HTTP_HOST' => 'picture-cache.test', 'REQUEST_URI' => '/native/', 'REQUEST_METHOD' => 'GET', 'HTTP_ACCEPT' => $accept, 'HTTP_USER_AGENT' => '' ] ] );
		$cache = new \NoobRocket\Buffer\Cache( new \NoobRocket\Buffer\Tests( $config, [ 'cookies' => [], 'get' => [], 'post' => [] ] ), $config, [ 'cache_dir_path' => $fixture . '/pages' ] );
		$output = $converter->convert( $input );
		picture_delivery_check( $output === $expected_output, 'Accept ' . $accept . ': delivery HTML is identical' );
		picture_delivery_check( false === strpos( $cache->get_cache_path(), '-webp' ), 'Accept ' . $accept . ': cache lookup uses the regular filename' );
		$result = $cache->maybe_process_buffer( $output );
		$directory = $fixture . '/pages/picture-cache.test/native';
		picture_delivery_check( is_file( $directory . '/index.html' ) && ! is_file( $directory . '/index-webp.html' ) && ! is_file( $directory . '/.no-webp' ), 'Accept ' . $accept . ': writer produces one regular cache variant' );
		$cached = file_get_contents( $directory . '/index.html' );
		picture_delivery_check( ! preg_match( '/<!-- (?:NoobRocket|Rocket) (?:has|no) webp -->/', $cached . $result ), 'Accept ' . $accept . ': native HTML has no legacy WebP marker' );
		$cached_body = preg_replace( '/\n<!-- (?:Performance optimized by [^>]+|Cached for great performance) - Debug: cached@\d+ -->$/', '', $cached );
		picture_delivery_check( $expected_output === $cached_body, 'Accept ' . $accept . ': cached HTML body remains identical' );
		if ( function_exists( 'gzencode' ) ) {
			picture_delivery_check( gzdecode( file_get_contents( $directory . '/index.html_gzip' ) ) === $cached, 'Accept ' . $accept . ': gzip contains the same regular HTML' );
		}
	}
	if ( $export ) {
		foreach ( $files as $file ) {
			copy( $fixture . '/images/' . $file, $export . '/' . $file );
		}
		$assets = new \NoobRocket\Dependencies\NoobRocketLazyload\Assets();
		file_put_contents( $export . '/delivery.json', json_encode( [
			'responsive' => $responsive_output, 'webp' => $partial_output, 'fallback' => $missing, 'lazy' => $lazy_output, 'cdn' => $cdn_output,
			'lazy_script' => $assets->getInlineLazyloadScript( [ 'elements' => [ 'image' => 'img[data-lazy-src]' ] ] ),
		], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
	}
} finally {
	if ( null === $original_accept ) {
		unset( $_SERVER['HTTP_ACCEPT'] );
	} else {
		$_SERVER['HTTP_ACCEPT'] = $original_accept;
	}
	foreach ( $original_cache_state as $state ) {
		$state[0]->setValue( null, $state[1] );
	}
	remove_filter( 'noobrocket_url_to_path_associations', $associations );
	remove_filter( 'pre_get_noobrocket_option_cdn', $cdn_enabled, PHP_INT_MAX );
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $fixture, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $iterator as $file ) {
		if ( $file->isDir() ) {
			rmdir( $file->getPathname() );
		} else {
			unlink( $file->getPathname() );
		}
	}
	rmdir( $fixture );
}
echo 'PASS: ', count( $checks ), ' native image delivery assertions.', PHP_EOL;
