<?php
/** Actual inline-background owner with modeled WordPress helpers; no site bootstrap or writes. */
declare(strict_types=1);

$root = dirname( __DIR__ );
$checks = 0;
$filters = [];

function background_check( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
	++$GLOBALS['checks'];
}
function apply_filters( $hook, $value, ...$args ) {
	return isset( $GLOBALS['filters'][ $hook ] ) ? $GLOBALS['filters'][ $hook ]( $value, ...$args ) : $value;
}
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, $args ); }

require $root . '/noobrocket/vendor/autoload.php';
require $root . '/noobrocket/inc/classes/admin/class-options-data.php';
set_error_handler( static function ( $severity, $message, $file, $line ) {
	if ( error_reporting() & $severity ) { throw new ErrorException( $message, 0, $severity, $file, $line ); }
	return false;
} );

$image = new \NoobRocket\Dependencies\NoobRocketLazyload\Image();
$transform = static function ( $html, $scenario_filters = [] ) use ( $image ) {
	$GLOBALS['filters'] = $scenario_filters;
	return $image->lazyloadBackgroundImages( $html, $html );
};

$unchanged_styles = [
	'background-image:url(https://inline-images.test/one.png),url(https://inline-images.test/two.png);color:red',
	'background-image:url("https://inline-images.test/one.png"), linear-gradient(red, blue);color:red',
	'background-image:linear-gradient(red, blue),url(https://inline-images.test/two.png)',
	'background-image:url(https://inline-images.test/one.png),none,url(https://inline-images.test/two.png)!important',
	'background-image:url(https://inline-images.test/one.png);background-image:url(https://inline-images.test/two.png),linear-gradient(red,blue)',
	'background-image:url(https://inline-images.test/one.png);background-image:none',
	'background-image:image-set(url(https://inline-images.test/one.png) 1x,url(https://inline-images.test/two.png) 2x)',
	'background-image:var(--background)',
	'background-image:url("https://inline-images.test/escaped\\name.png")',
	'background-image:url(https://inline-images.test/escaped\\).png)',
	'background-image:url(&quot;https://inline-images.test/entity(name).png&quot;)',
	'background-image:url(data:image/png;base64,AAAA)',
	'background:url(https://inline-images.test/one.png) center/cover',
];
foreach ( $unchanged_styles as $style ) {
	$html = '<div class="original" style=\'' . $style . '\'></div>';
	background_check( $html === $transform( $html ), 'unsupported or layered value remains byte-identical: ' . $style );
}

$single_urls = [
	'url(https://inline-images.test/one.png)' => 'https://inline-images.test/one.png',
	'url("https://inline-images.test/quoted(name).png")' => 'https://inline-images.test/quoted(name).png',
	"url('https://inline-images.test/single(name).png')" => 'https://inline-images.test/single(name).png',
	'url( https://inline-images.test/space.png )' => 'https://inline-images.test/space.png',
	'url("https://inline-images.test/comma,a;b.png")' => 'https://inline-images.test/comma,a;b.png',
	'url(&quot;https://inline-images.test/entity.png&quot;)' => 'https://inline-images.test/entity.png',
];
foreach ( $single_urls as $css => $url ) {
	$html = '<section class="original" style="color:red;background-image:' . $css . ';background-position:center"></section>';
	// Literal opposite quote characters are also valid in HTML attributes.
	if ( false !== strpos( $css, '"' ) ) { $html = '<section class="original" style=\'color:red;background-image:' . $css . ';background-position:center\'></section>'; }
	$output = $transform( $html );
	background_check( false !== strpos( $output, 'data-bg="' . esc_attr( $url ) . '"' ), 'single URL retained: ' . $css );
	background_check( false !== strpos( $output, 'class="original noobrocket-lazyload"' ), 'single URL keeps original classes' );
	background_check( false !== strpos( $output, 'style=' ) && false !== strpos( $output, 'color:red;background-position:center' ), 'unrelated declarations retain exact bytes' );
}

$important = '<div style="background-image:url(https://inline-images.test/important.png) !important;color:red"></div>';
background_check( $important === $transform( $important ), 'important declaration remains byte-identical because data-bg cannot restore priority' );
$no_semicolon = '<div style="background-image:url(https://inline-images.test/end.png)"></div>';
background_check( false !== strpos( $transform( $no_semicolon ), 'data-bg="https://inline-images.test/end.png"' ), 'final declaration without semicolon remains eligible' );

foreach ( [
	'<div data-style="background-image:url(https://inline-images.test/fake.png)"></div>',
	'<div title=\'style="background-image:url(https://inline-images.test/fake.png)"\'></div>',
	'<div data-no-lazy="1" style="background-image:url(https://inline-images.test/excluded.png)"></div>',
	'<div class="skip-lazy" style="background-image:url(https://inline-images.test/excluded.png)"></div>',
	'<div style="background-image:url(https://inline-images.test/wpcf7_captcha/excluded.png)"></div>',
] as $html ) {
	background_check( $html === $transform( $html ), 'real-attribute boundary and existing exclusions retained' );
}
$source_filter = [ 'noobrocket_lazyload_excluded_src' => static function ( $values ) { return array_merge( $values, [ '/filtered.png' ] ); } ];
$excluded = '<div style="background-image:url(https://inline-images.test/filtered.png)"></div>';
background_check( $excluded === $transform( $excluded, $source_filter ), 'filtered source exclusion retained' );

$avada = new \NoobRocket\ThirdParty\Themes\Avada( new \NoobRocket\Admin\Options_Data( [] ) );
$avada_filter = [ 'noobrocket_lazyload_bg_images_regex' => [ $avada, 'fix_regex_lazyload_bg_images' ] ];
$avada_single = '<div style="--awb-background-image:url(https://inline-images.test/avada.png);color:red"></div>';
$avada_output = $transform( $avada_single, $avada_filter );
background_check( false !== strpos( $avada_output, 'data-bg="https://inline-images.test/avada.png"' ) && false !== strpos( $avada_output, 'style="color:red"' ), 'actual Avada filter retains single-URL custom-property support' );
$avada_layers = '<div style="--awb-background-image:url(https://inline-images.test/one.png),url(https://inline-images.test/two.png);color:red"></div>';
background_check( $avada_layers === $transform( $avada_layers, $avada_filter ), 'Avada layered value remains byte-identical' );
background_check( false !== strpos( $transform( $no_semicolon, [ 'noobrocket_lazyload_bg_images_regex' => static function () { return '('; } ] ), 'data-bg=' ), 'invalid regex filter retains safe default fallback' );

if ( in_array( '--export', $argv, true ) ) {
	$content = '<div id="url-layers" class="box" style="background-image:url(https://inline-images.test/front.png),url(https://inline-images.test/back.png);background-position:center"></div>';
	$content .= '<div id="gradient-layer" class="box" style="background-image:url(https://inline-images.test/overlay.png),linear-gradient(red,blue)"></div>';
	$content .= '<div id="gradient-first" class="box" style="background-image:linear-gradient(red,blue),url(https://inline-images.test/under.png)"></div>';
	$content .= '<div id="excluded" class="box" data-no-lazy="1" style="background-image:url(https://inline-images.test/eager.png)"></div>';
	$content .= '<div style="height:2000px"></div><div id="single" class="box" style=\'color:red;background-image:url("https://inline-images.test/single(name).png");background-position:center\'></div>';
	$configuration = ( new \NoobRocket\Dependencies\NoobRocketLazyload\Assets() )->getInlineLazyloadScript( [ 'elements' => [ 'background_image' => '.noobrocket-lazyload' ], 'threshold' => 0 ] );
	echo json_encode( [
		'original' => $content,
		'converted' => $transform( $content ),
		'configuration' => $configuration,
	], JSON_THROW_ON_ERROR );
} else {
	echo 'PASS: ' . $checks . " inline-background checks\n";
}
