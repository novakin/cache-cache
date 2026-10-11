<?php
/** Actual nonnative picture owner; modeled WordPress filters, no bootstrap or writes. */
declare(strict_types=1);

require dirname( __DIR__ ) . '/noobrocket/vendor/autoload.php';
$filters = [];
$checks = 0;
function apply_filters( $hook, $value, ...$args ) { return $GLOBALS['filters'][ $hook ] ?? $value; }
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $value ) { return esc_attr( $value ); }
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, $args ); }
function picture_check( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
	++$GLOBALS['checks'];
}
function first_image( $html ) {
	preg_match( '#<img\b[^>]*>#', $html, $match );
	return $match[0] ?? '';
}
function first_image_attribute( $html, $attribute ) {
	preg_match( '#\b' . preg_quote( $attribute, '#' ) . '="([^"]*)"#', first_image( $html ), $match );
	return $match[1] ?? null;
}
$image = new \NoobRocket\Dependencies\NoobRocketLazyload\Image();
$export = [ 'pictures' => [] ];
foreach ( range( 0, 9 ) as $index ) {
	$alt = 'Price $' . $index . '.45';
	$title = 'Path C:\\prices\\$' . $index . '\\1';
	$img = '<img src="https://picture-images.test/photo.png" alt="' . $alt . '" title="' . $title . '">';
	$html = '<picture><source srcset="https://picture-images.test/photo.png 1x, https://picture-images.test/photo-2x.png 2x">' . $img . '</picture>';
	$output = $image->lazyloadPictures( $html, $html );
	picture_check( $alt === first_image_attribute( $output, 'alt' ), 'first visible picture image retains dollar-number alt ' . $index );
	picture_check( $title === first_image_attribute( $output, 'title' ), 'first visible picture image retains literal backslashes/title ' . $index );
	picture_check( false !== strpos( $output, '<noscript>' . $img . '</noscript>' ), 'noscript image stays byte-identical ' . $index );
	picture_check( false !== strpos( $output, '<source data-lazy-srcset="https://picture-images.test/photo.png 1x, https://picture-images.test/photo-2x.png 2x">' ), 'responsive source retains normal lazy delivery ' . $index );
	$existing = '<noscript id="existing">' . $img . '</noscript>';
	picture_check( false !== strpos( $image->lazyloadPictures( $html . $existing, $html . $existing ), $existing ), 'preexisting noscript is never transformed ' . $index );
	$plain = $image->lazyloadImages( $img, $img, false );
	picture_check( $alt === first_image_attribute( $plain, 'alt' ), 'ordinary nonpicture image retains alt ' . $index );
	$filters = [ 'noobrocket_lazyload_noscript' => false ];
	$without_noscript = $image->lazyloadPictures( $html, $html );
	picture_check( false === strpos( $without_noscript, '<noscript' ) && $alt === first_image_attribute( $without_noscript, 'alt' ), 'disabled noscript retains literal visible image ' . $index );
	$filters = [];
	$export['pictures'][] = [ 'html' => $output, 'alt' => $alt, 'title' => $title, 'original' => $html ];
}
$excluded = '<picture><source srcset="https://picture-images.test/photo.png"><img data-no-lazy="1" src="https://picture-images.test/photo.png" alt="Price $0.45"></picture>';
picture_check( $excluded === $image->lazyloadPictures( $excluded, $excluded ), 'attribute-excluded picture remains byte-identical' );
$filters = [ 'noobrocket_lazyload_excluded_src' => [ 'https://picture-images.test/photo.png' ] ];
picture_check( $html === $image->lazyloadPictures( $html, $html ), 'source-excluded picture remains byte-identical' );
$filters = [];
$ordinary = '<picture><source srcset="https://picture-images.test/photo.png"><img src="https://picture-images.test/photo.png" alt="Price USD0.45"></picture>';
picture_check( 'Price USD0.45' === first_image_attribute( $image->lazyloadPictures( $ordinary, $ordinary ), 'alt' ), 'ordinary price control retains its text' );
$export['excluded'] = $excluded;
$export['configuration'] = ( new \NoobRocket\Dependencies\NoobRocketLazyload\Assets() )->getInlineLazyloadScript( [ 'elements' => [ 'img[data-lazy-src]' ], 'threshold' => 300 ] );
if ( in_array( '--export', $argv, true ) ) {
	echo json_encode( $export, JSON_THROW_ON_ERROR );
} else {
	echo 'PASS: ' . $checks . " literal picture replacement checks\n";
}
