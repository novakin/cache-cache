<?php
/** Bounded isolated picture hot-path measurement; native source, temporary files, no WordPress bootstrap. */
declare(strict_types=1);
$source = $argv[1] ?? dirname( __DIR__, 2 ) . '/noobrocket/inc/Engine/Media/Picture/Subscriber.php';
$fixture = sys_get_temp_dir() . '/noobrocket-picture-profile-' . bin2hex( random_bytes( 6 ) );
mkdir( $fixture );
$lookups = 0;
define( 'WP_CONTENT_DIR', $fixture );
function wp_upload_dir() { ++$GLOBALS['lookups']; return [ 'basedir' => $GLOBALS['fixture'], 'baseurl' => 'https://profile.test/uploads' ]; }
function content_url() { return 'https://profile.test/content'; }
function wp_parse_url( $url ) { return parse_url( $url ); }
function home_url( $path = '' ) { return 'https://profile.test' . $path; }
function trailingslashit( $value ) { return rtrim( $value, '/' ) . '/'; }
function wp_normalize_path( $value ) { return str_replace( '\\', '/', $value ); }
function apply_filters( $hook, $value ) { return $value; }
function noobrocket_bypass() { return false; }
function esc_attr( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
$root = dirname( __DIR__, 2 ) . '/noobrocket/inc/';
require $root . 'classes/event-management/subscriber-interface.php';
require $root . 'classes/admin/class-options-data.php';
require $root . 'Engine/Media/Picture/Generator.php';
require $source;
try {
	for ( $index = 0; $index < 100; ++$index ) {
		file_put_contents( $fixture . '/' . $index . '.jpg', str_repeat( 'source', 100 ) );
		file_put_contents( $fixture . '/' . $index . '.jpg.avif', str_repeat( 'a', 200 ) );
		file_put_contents( $fixture . '/' . $index . '.jpg.webp', str_repeat( 'w', 250 ) );
	}
	$processor = new NoobRocket\Engine\Media\Picture\Subscriber( new NoobRocket\Admin\Options_Data( [ 'image_delivery' => 1 ] ) );
	if ( '--verify' === ( $argv[2] ?? '' ) ) {
		$checks = 0;
		$check = static function ( $condition, $message ) use ( &$checks ) { if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); } ++$checks; };
		$single = '<img src="https://profile.test/uploads/0.jpg">';
		$first = $processor->convert( $single );
		$check( false !== strpos( $first, 'type="image/avif"' ) && false !== strpos( $first, 'type="image/webp"' ), 'beneficial derivatives are delivered with original fallback' );
		file_put_contents( $fixture . '/0.jpg.avif', str_repeat( 'a', 650 ) );
		clearstatcache();
		$changed = $processor->convert( $single );
		$check( false === strpos( $changed, 'type="image/avif"' ) && false !== strpos( $changed, 'type="image/webp"' ), 'larger AVIF is ignored and render memo resets after files change' );
		$check( 650 === filesize( $fixture . '/0.jpg.avif' ) && is_file( $fixture . '/0.jpg.webp' ), 'delivery never removes or rewrites external sidecars' );
		file_put_contents( $fixture . '/0.jpg.webp', str_repeat( 'w', 580 ) );
		clearstatcache();
		$check( $single === $processor->convert( $single ), 'less than five percent saving leaves the useful original active' );
		$responsive = '<img src="https://profile.test/uploads/0.jpg" srcset="https://profile.test/uploads/0.jpg 300w, https://profile.test/uploads/1.jpg 600w" sizes="100vw">';
		$check( $responsive === $processor->convert( $responsive ), 'partial beneficial families cannot produce misleading responsive sources' );
		file_put_contents( $fixture . '/0.jpg.avif', str_repeat( 'a', 200 ) );
		file_put_contents( $fixture . '/0.jpg.webp', str_repeat( 'w', 250 ) );
		clearstatcache();
		$lookups = 0;
		$duplicates = $processor->convert( str_repeat( $single, 100 ) );
		$check( 2 === $lookups && 100 === substr_count( $duplicates, '<picture data-noobrocket-picture>' ), 'duplicate image URLs reuse resolution while every original tag is preserved' );
		echo 'PASS: picture benefit/memoization (', $checks, " isolated native assertions)\n";
		return;
	}
	$profile = [];
	foreach ( [ 'repeated', 'unique' ] as $shape ) {
		$tags = [];
		for ( $index = 0; $index < 100; ++$index ) { $tags[] = '<img src="https://profile.test/uploads/' . ( 'repeated' === $shape ? 0 : $index ) . '.jpg">'; }
		$html = implode( '', $tags );
		$processor->convert( $html );
		$samples = [];
		$lookups = 0;
		for ( $sample = 0; $sample < 25; ++$sample ) {
			$start = hrtime( true );
			$output = $processor->convert( $html );
			$samples[] = ( hrtime( true ) - $start ) / 1000000;
			if ( 100 !== substr_count( $output, '<picture data-noobrocket-picture>' ) ) { throw new RuntimeException( 'Changed picture output.' ); }
		}
		sort( $samples );
		$profile[ $shape ] = [ 'images' => 100, 'samples' => $samples, 'median_ms' => $samples[12], 'root_lookups_per_render' => $lookups / 25, 'output_bytes' => strlen( $output ) ];
	}
	echo json_encode( [ 'protocol' => 'noobrocket-picture-isolated-profile-v1', 'source' => $source, 'php' => PHP_VERSION, 'platform' => PHP_OS_FAMILY, 'profile' => $profile, 'limits' => 'Warm filesystem fixture; not WordPress request time, visitor paint or production host performance.' ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ), "\n";
} finally {
	foreach ( glob( $fixture . '/*' ) as $file ) { unlink( $file ); }
	rmdir( $fixture );
}
