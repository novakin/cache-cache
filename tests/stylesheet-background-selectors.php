<?php
/** Actual mapping formatter; modeled filters, no WordPress bootstrap or writes. */
declare(strict_types=1);

require dirname( __DIR__ ) . '/noobrocket/vendor/autoload.php';
function apply_filters( $hook, $value, ...$args ) { return $value; }
function selector_check( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
	++$GLOBALS['checks'];
}
$checks = 0;
$formatter = new \NoobRocket\Engine\Media\Lazyload\CSS\Front\MappingFormatter();
$cases = [
	':is(.a,.b) .c:not(.x),:is(.a,.b) .c>.d' => ':is(.a,.b) .c:not(.x),:is(.a,.b) .c>.d',
	':where(.a,.b):not(.x,.y),:where(.a,.b):has(>.c,.d)' => ':where(.a,.b):not(.x,.y),:where(.a,.b):has(>.c,.d)',
	'[data-value="a,b"],[data-value="a,b"] .child' => '[data-value="a,b"],[data-value="a,b"] .child',
	"[data-value='a,b'],[data-value='a,b'] .child" => "[data-value='a,b'],[data-value='a,b'] .child",
	'[data-value="a\\\",b"],.other' => '[data-value="a\\\",b"],.other',
	'.escaped\\,comma,.other' => '.escaped\\,comma,.other',
	'.a/* , ( ) */,.b' => '.a/* , ( ) */,.b',
	':is(.a,.b), :is(.a,.b)' => ':is(.a,.b)',
	'.a:hover, .a,.b::before,.parent>' => '.a,.b,.parent>*',
	':is(.a,.b' => ':is(.a,.b',
	'.a),.b' => '.a),.b',
	'[data-value="a,b],.other' => '[data-value="a,b],.other',
];
foreach ( $cases as $input => $expected ) {
	$pair = $formatter->format( [ [ 'selector' => $input, 'hash' => 'fixture', 'url' => 'https://selector-images.test/hero.png' ] ] )[0];
	selector_check( $expected === $pair['selector'], 'complete selector list retained/deduplicated: ' . $input );
	selector_check( $input . "{--noobrocket-bg-fixture: url('https://selector-images.test/hero.png');}" === $pair['style'], 'CSS rule preserves the original selector bytes' );
}
if ( in_array( '--export', $argv, true ) ) {
	$selectors = [
		':is(.a,.b) .c:not(.x),:is(.a,.b) .c>.d',
		':where(.a,.b):has(>.child,.missing),:where(.a,.b):not(.x,.y)',
		'[data-value="a,b"],[data-value="a,b"] .child',
		'.escaped\\,comma,.other',
	];
	$pairs = [];
	foreach ( $selectors as $index => $selector ) {
		$pairs[] = $formatter->format( [ [ 'selector' => $selector, 'hash' => 'fixture' . $index, 'url' => 'https://selector-images.test/hero' . $index . '.png' ] ] )[0];
	}
	echo json_encode( [ 'pairs' => $pairs ], JSON_THROW_ON_ERROR );
} else {
	echo 'PASS: ' . $checks . " stylesheet-background selector checks\n";
}
