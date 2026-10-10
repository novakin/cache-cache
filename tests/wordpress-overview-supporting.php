<?php
/** Isolated supporting Overview card contracts; no WordPress state or HTTP access. */
declare(strict_types=1);

define( 'ABSPATH', __DIR__ );
define( 'NOOBROCKET_FILE', dirname( __DIR__ ) . '/noobrocket/noobrocket.php' );
$allowed = true;
function current_user_can( $capability ) { return $GLOBALS['allowed']; }
function __( $text, $domain = '' ) { return $text; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $text ) { return esc_html( $text ); }
function esc_url( $text ) { return esc_html( $text ); }
function esc_html_e( $text, $domain = '' ) { echo esc_html( $text ); }
function esc_attr_e( $text, $domain = '' ) { echo esc_attr( $text ); }
function wp_create_nonce( $action ) { return 'isolated-nonce'; }
function plugins_url( $path, $file ) { return 'https://example.test/plugin/' . $path; }
function get_option( $name ) { return 'date_format' === $name ? 'Y-m-d' : 'H:i'; }
function wp_date( $format, $timestamp ) { return gmdate( $format, $timestamp ); }
function number_format_i18n( $value, $decimals ) { return number_format( $value, $decimals ); }
function disabled( $condition ) { if ( $condition ) { echo ' disabled="disabled"'; } }

$checks = 0;
function supporting_check( bool $condition, string $message ): void {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
	++$GLOBALS['checks'];
}
function supporting_render( string $partial, array $data ): array {
	ob_start();
	require dirname( __DIR__ ) . '/noobrocket/views/settings/partials/' . $partial . '.php';
	$html = ob_get_clean();
	$document = new DOMDocument();
	libxml_use_internal_errors( true );
	$document->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
	libxml_clear_errors();
	return [ $html, new DOMXPath( $document ) ];
}
function supporting_count( DOMXPath $xpath, string $selector ): int {
	return $xpath->query( $selector )->length;
}

$cache = [ 'external' => false, 'health' => true, 'connected' => null, 'provider' => 'WordPress fixture', 'message' => 'Current request probe fixture.' ];
foreach ( [ [ false, true ], [ true, true ], [ true, false ], [ false, false ] ] as [ $external, $health ] ) {
	$cache['external'] = $external;
	$cache['health'] = $health;
	[ $html, $xpath ] = supporting_render( 'object-cache-diagnostics', [ 'object_cache' => $cache ] );
	$badge = $xpath->query( '//span[contains(@class,"noobrocket-badge")]' )->item( 0 );
	supporting_check( $badge->textContent === ( ! $health ? 'Check failed' : ( $external ? 'Persistent' : 'Standard' ) ), 'object-cache badge distinguishes failure, persistent and standard modes' );
	supporting_check( str_contains( $badge->getAttribute( 'class' ), ! $health ? 'is-error' : ( $external ? 'is-success' : 'is-neutral' ) ), 'object-cache severity follows actual probe outcome' );
	supporting_check( 1 === supporting_count( $xpath, '//details[not(@open)]//dt[text()="Provider"]' ) && 1 === supporting_count( $xpath, '//details[not(@open)]//p[text()="Current request probe fixture."]' ), 'provider and technical probe live in optional evidence' );
	supporting_check( 1 === supporting_count( $xpath, '//details//p[contains(text(),"does not measure speed or verify persistence between requests")]' ), 'probe scope does not overclaim cross-request health or measured speed' );
	supporting_check( ( ! $external || ! $health ? 1 : 0 ) === supporting_count( $xpath, '//a[contains(@href,"native-object-cache")]' ), 'setup or troubleshooting appears only when relevant' );
	if ( ! $external || ! $health ) {
		$action = ! $health ? 'Troubleshoot object cache' : 'Set up persistent object cache';
		supporting_check( 1 === supporting_count( $xpath, '//a[contains(@href,"native-object-cache") and text()="' . $action . '"]' ), 'next action names setup or recovery according to actual state' );
	}
	if ( $health && ! $external ) {
		supporting_check( 1 === supporting_count( $xpath, '//div[contains(@class,"noobrocket-card-body")]/p[contains(text(),"between requests too")]' ), 'standard mode explains persistent-cache opportunity before technical details' );
	}
}
$cache['connected'] = false;
[ $html, $xpath ] = supporting_render( 'object-cache-diagnostics', [ 'object_cache' => $cache ] );
supporting_check( 1 === supporting_count( $xpath, '//details//dd[text()="Disconnected"]' ), 'known provider disconnection stays available as evidence' );
$allowed = false;
[ $html, $xpath ] = supporting_render( 'object-cache-diagnostics', [ 'object_cache' => $cache ] );
supporting_check( '' === $html, 'object-cache evidence remains capability gated' );
$allowed = true;

$base = [ 'configured' => false, 'public_url' => false, 'url' => 'http://127.0.0.1:8765/', 'notice' => null, 'results' => [] ];
foreach ( [ [ false, false ], [ false, true ], [ true, false ], [ true, true ] ] as [ $public, $configured ] ) {
	$data = $base;
	$data['public_url'] = $public;
	$data['configured'] = $configured;
	[ $html, $xpath ] = supporting_render( 'pagespeed', $data );
	$state = ! $public ? 'Unavailable here' : ( $configured ? 'API key saved' : 'API key required' );
	supporting_check( 1 === supporting_count( $xpath, '//div[contains(@class,"noobrocket-surface-head")]/span[text()="' . $state . '"]' ), 'PageSpeed status describes actual local and credential prerequisites' );
	supporting_check( ( $public && $configured ? 0 : 2 ) === supporting_count( $xpath, '//button[@data-noobrocket-pagespeed-run][@disabled]' ), 'manual tests stay disabled until public URL and saved key exist' );
	supporting_check( ( $public && ! $configured ? 1 : 0 ) === supporting_count( $xpath, '//details[contains(@class,"noobrocket-pagespeed-setup")][@open]' ), 'public missing-key setup opens while local unnecessary setup stays folded' );
	supporting_check( 1 === supporting_count( $xpath, '//input[@name="api_key"][@value=""][@type="password"][@form="noobrocket-pagespeed-key"]' ), 'key input stays blank, masked and independently owned' );
	supporting_check( 1 === supporting_count( $xpath, '//p[contains(text(),"only when you request them") and contains(text(),"URL to Google")]' ), 'manual execution and Google disclosure remain visible' );
	if ( ! $public ) {
		supporting_check( strpos( $html, 'cannot check this local or private address' ) < strpos( $html, 'noobrocket-pagespeed-setup' ), 'local blocker comes before API setup' );
	}
}
$data = $base;
$data['notice'] = [ 'type' => 'error', 'code' => 'invalid_key', 'message' => 'Invalid fixture key.' ];
[ $html, $xpath ] = supporting_render( 'pagespeed', $data );
supporting_check( 1 === supporting_count( $xpath, '//details[contains(@class,"noobrocket-pagespeed-setup")][@open]' ) && 1 === supporting_count( $xpath, '//input[@aria-invalid="true"]' ), 'key validation reveals its disclosure and marks the field' );
supporting_check( 1 === supporting_count( $xpath, '//div[@id="noobrocket-pagespeed-key-error"][@role="alert"][@data-noobrocket-inline-feedback]' ), 'invalid-key error retains inline ownership' );

$data = $base;
$data['configured'] = true;
$data['public_url'] = true;
$result = [ 'checked_at' => 1720000000, 'url' => 'https://example.test/', 'scores' => [ 'performance' => 0, 'accessibility' => 96, 'best-practices' => 80, 'seo' => null ], 'metrics' => [ 'fcp' => 2100, 'lcp' => 3000, 'tbt' => 0, 'cls' => 0.012 ] ];
$data['results'] = [ 'mobile' => $result, 'desktop' => $result ];
[ $html, $xpath ] = supporting_render( 'pagespeed', $data );
foreach ( [ 'mobile', 'desktop' ] as $strategy ) {
	$panel = '//section[@data-noobrocket-pagespeed-result="' . $strategy . '"]';
	supporting_check( 1 === supporting_count( $xpath, $panel . '//*[@data-noobrocket-pagespeed-score="performance" and text()="0" and not(ancestor::details[contains(@class,"noobrocket-pagespeed-detail")])]' ), 'zero performance score remains visible and preserved' );
	supporting_check( 1 === supporting_count( $xpath, $panel . '//*[@data-noobrocket-pagespeed-grade="performance" and contains(@class,"is-error") and text()="Poor"]' ), 'zero is poor rather than unavailable' );
	supporting_check( 1 === supporting_count( $xpath, $panel . '//*[@data-noobrocket-pagespeed-time][@datetime]' ), 'each strategy has one dated result retained for async updates' );
	foreach ( [ 'performance', 'accessibility', 'best-practices', 'seo' ] as $category ) {
		supporting_check( 1 === supporting_count( $xpath, $panel . '//*[@data-noobrocket-pagespeed-score="' . $category . '"]' ) && 1 === supporting_count( $xpath, $panel . '//*[@data-noobrocket-pagespeed-grade="' . $category . '"]' ), 'score and grade selectors stay unique for async updates' );
	}
	foreach ( [ 'fcp', 'lcp', 'tbt', 'cls' ] as $metric ) {
		supporting_check( 1 === supporting_count( $xpath, $panel . '//*[@data-noobrocket-pagespeed-metric="' . $metric . '"]' ), 'metric selectors stay unique for async updates' );
	}
	supporting_check( 2 === supporting_count( $xpath, $panel . '//details[not(@open)]//*[@data-noobrocket-pagespeed-metric]' ) && 3 === supporting_count( $xpath, $panel . '//details[not(@open)]//*[@data-noobrocket-pagespeed-score]' ), 'secondary metrics and grades are folded without removing saved evidence' );
	supporting_check( 1 === supporting_count( $xpath, $panel . '//details//*[@data-noobrocket-pagespeed-url]' ), 'tested URL remains retained in result evidence' );
}
supporting_check( 2 === supporting_count( $xpath, '//button[@data-noobrocket-pagespeed-run][@type="button"]' ), 'checks never submit the global settings form' );
supporting_check( 1 === supporting_count( $xpath, '//p[contains(text(),"homepage URL to Google")]' ) && str_contains( $html, 'Lighthouse lab scores, not real visitor data.' ), 'privacy and lab-data limits stay explicit with results' );

echo 'PASS: supporting Overview cards (', $checks, " assertions; standalone)\n";
