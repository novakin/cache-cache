<?php
/** Standalone Overview evidence, locale and native probe contracts. No WordPress site is loaded. */
declare(strict_types=1);

define( 'ABSPATH', __DIR__ );
define( 'NOOBROCKET_PATH', dirname( __DIR__ ) . '/noobrocket/' );
define( 'NOOBROCKET_FILE', NOOBROCKET_PATH . 'noobrocket.php' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'MINUTE_IN_SECONDS', 60 );
$checks = 0;
$locale = 'en';
$responses = [];
$requests = [];
$transients = [];
class WP_Error {}
class OverviewRedirect extends RuntimeException {}
function __( $text, $domain = '' ) {
	$translations = [
		'The server could not reach your homepage. Check server connectivity and HTTPS, then run the check again.' => 'Échec de la vérification anonyme de la page d’accueil.',
		'Preloading resumed. The existing queue and sitemap discovery continue when Preload Cache is enabled.' => 'Préchargement repris. La file existante est conservée.',
		'%d failed CSS jobs queued for retry.' => '%d tâches CSS remises dans la file.',
	];
	return 'fr' === $GLOBALS['locale'] ? ( $translations[ $text ] ?? $text ) : $text;
}
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $text ) { return esc_html( $text ); }
function esc_url( $text ) { return esc_attr( $text ); }
function esc_html__( $text, $domain = '' ) { return esc_html( __( $text, $domain ) ); }
function esc_html_e( $text, $domain = '' ) { echo esc_html__( $text, $domain ); }
function _n( $singular, $plural, $number, $domain = '' ) { return 1 === (int) $number ? $singular : $plural; }
function disabled( $value, $expected = true, $echo = true ) {
	$output = $value == $expected ? 'disabled="disabled"' : '';
	if ( $echo ) { echo $output; }
	return $output;
}
function get_option( $key, $default = false ) { return [ 'date_format' => 'Y-m-d', 'time_format' => 'H:i' ][ $key ] ?? $default; }
function wp_date( $format, $time ) { return gmdate( $format, $time ); }
function human_time_diff( $time ) { return (string) max( 0, (int) floor( ( time() - $time ) / 60 ) ) . ' minutes'; }
function number_format_i18n( $number ) { return number_format( $number ); }
function plugins_url( $path ) { return 'https://example.test/plugins/noobrocket/' . $path; }
function wp_strip_all_tags( $text ) { return strip_tags( $text ); }
function wp_parse_url( $url ) { return parse_url( $url ); }
function home_url( $path = '' ) { return 'https://example.test' . $path; }
function wp_safe_remote_get( $url, $args ) {
	$GLOBALS['requests'][] = [ 'url' => $url, 'args' => $args ];
	if ( ! $GLOBALS['responses'] ) { throw new RuntimeException( 'Fixture would send an unmocked request.' ); }
	return array_shift( $GLOBALS['responses'] );
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_remote_retrieve_response_code( $response ) { return $response['status']; }
function wp_remote_retrieve_header( $response, $header ) { return $response['headers'][ $header ] ?? ''; }
function current_user_can() { return true; }
function check_admin_referer() {}
function wp_unslash( $text ) { return $text; }
function sanitize_key( $text ) { return preg_replace( '/[^a-z_]/', '', $text ); }
function set_transient( $key, $value, $expiration ) { $GLOBALS['transients'][ $key ] = [ $value, $expiration ]; }
function admin_url( $path ) { return 'https://example.test/wp-admin/' . $path; }
function wp_safe_redirect( $url ) { throw new OverviewRedirect( $url ); }

require NOOBROCKET_PATH . 'inc/classes/event-management/subscriber-interface.php';
require NOOBROCKET_PATH . 'inc/Engine/Optimization/LocalCSS/Diagnostics.php';
require NOOBROCKET_PATH . 'inc/Engine/Media/Picture/Generator.php';
require NOOBROCKET_PATH . 'inc/Engine/Cache/ObjectCache/Diagnostics.php';
require NOOBROCKET_PATH . 'inc/Engine/Admin/PageSpeed.php';
use NoobRocket\Engine\Optimization\LocalCSS\Diagnostics;
use NoobRocket\Engine\Media\Picture\Generator;
use NoobRocket\Engine\Cache\ObjectCache\Diagnostics as ObjectCacheDiagnostics;
use NoobRocket\Engine\Admin\PageSpeed;

function overview_check( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
	++$GLOBALS['checks'];
}
function overview_render( array $changes = [] ): string {
	$data = array_replace_recursive( [
		'available' => true, 'mode' => 'critical', 'paused' => false, 'worker' => [], 'probe' => null, 'notice' => [],
		'counts' => [ 'pending' => 0, 'running' => 0, 'failed' => 0, 'ready' => 0 ], 'failed' => [],
		'preload_enabled' => false, 'preload_paused' => false,
		'preload_counts' => [ 'pending' => 0, 'in-progress' => 0, 'completed' => 0, 'failed' => 0 ],
	], $changes );
	ob_start();
	require NOOBROCKET_PATH . 'views/settings/partials/local-css-diagnostics.php';
	return ob_get_clean();
}
function image_render( array $changes = [] ): string {
	$data = array_replace_recursive( [ 'enabled' => false, 'capabilities' => [ 'avif' => true, 'webp' => true ], 'pending' => 0, 'failed' => 0, 'active' => false, 'cursor' => 0, 'last_run' => 0, 'next_run' => null, 'generated' => 0, 'last_error' => '' ], $changes );
	ob_start();
	require NOOBROCKET_PATH . 'views/settings/partials/image-generation.php';
	return ob_get_clean();
}
function overview_document( string $html ): DOMXPath {
	$document = new DOMDocument();
	libxml_use_internal_errors( true );
	$document->loadHTML( $html );
	libxml_clear_errors();
	return new DOMXPath( $document );
}

$diagnostics = ( new ReflectionClass( Diagnostics::class ) )->newInstanceWithoutConstructor();
$responses = [ [ 'status' => 200 ], [ 'status' => 200, 'headers' => [ 'cache-status' => 'NoobRocket; hit' ] ] ];
$probe = $diagnostics->probe_cache();
overview_check( $probe['hit'] && 'cache_hit' === $probe['code'] && 200 === $probe['status'], 'second anonymous request confirms the native hit header' );
overview_check( ! isset( $probe['message'] ), 'probe evidence contains a code rather than cached translated prose' );
overview_check( 2 === count( $requests ) && 'https://example.test/' === $requests[0]['url'], 'only the canonical homepage is requested twice' );
overview_check( [] === $requests[0]['args']['cookies'] && 0 === $requests[0]['args']['redirection'] && 8 === $requests[0]['args']['timeout'], 'probe retains anonymous/no-redirect/eight-second request boundaries' );
$responses = [ [ 'status' => 200 ], new WP_Error() ];
$probe = $diagnostics->probe_cache();
overview_check( ! $probe['hit'] && 0 === $probe['status'] && 'transport_error' === $probe['code'], 'second-request transport failure does not retain first-request HTTP success' );
$responses = [ [ 'status' => 301 ] ];
$request_count = count( $requests );
$probe = $diagnostics->probe_cache();
overview_check( 'http_error' === $probe['code'] && 301 === $probe['status'] && count( $requests ) === $request_count + 1, 'redirect is reported without following it or warming again' );
$responses = [ [ 'status' => 200 ], [ 'status' => 200 ] ];
$probe = $diagnostics->probe_cache();
overview_check( 'header_missing' === $probe['code'] && ! $probe['hit'], 'successful response without the PHP header remains unverified' );
overview_check( str_contains( Diagnostics::probe_message( $probe ), 'Nginx or a proxy may serve cached pages without this header' ), 'missing header explains the static/proxy verification boundary' );

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [ 'operation' => 'probe' ];
$responses = [ new WP_Error() ];
try { $diagnostics->handle(); }
catch ( OverviewRedirect $redirect ) { overview_check( str_ends_with( $redirect->getMessage(), '#dashboard' ), 'native probe action redirects to Overview' ); }
overview_check( 'transport_error' === $transients['noobrocket_cache_delivery_probe'][0]['code'] && DAY_IN_SECONDS === $transients['noobrocket_cache_delivery_probe'][1], 'saved probe uses stable evidence and original one-day expiry' );
overview_check( is_array( $transients['noobrocket_diagnostics_notice'][0] ) && ! isset( $transients['noobrocket_diagnostics_notice'][0]['message'] ) && MINUTE_IN_SECONDS === $transients['noobrocket_diagnostics_notice'][1], 'probe notice stores evidence with original one-minute expiry' );

$legacy = [ 'hit' => false, 'status' => 0, 'checked_at' => time() - 86400, 'message' => 'Cached English failure' ];
$locale = 'fr';
overview_check( str_contains( Diagnostics::probe_message( $legacy ), 'Échec' ), 'legacy transport evidence translates in current viewing locale' );
overview_check( str_contains( Diagnostics::probe_message( $transients['noobrocket_diagnostics_notice'][0] ), 'Échec' ), 'new stored probe notice translates in current viewing locale' );
overview_check( [ 'type' => 'error', 'message' => 'Échec de la vérification anonyme de la page d’accueil.' ] === Diagnostics::notice_data( $transients['noobrocket_diagnostics_notice'][0] ), 'transport action outcome is an error in the current viewing locale' );
overview_check( [ 'type' => 'success', 'message' => 'Préchargement repris. La file existante est conservée.' ] === Diagnostics::notice_data( [ 'operation' => 'resume_preload' ] ), 'preload resume outcome translates at presentation and stays a success' );
overview_check( [ 'type' => 'success', 'message' => '3 tâches CSS remises dans la file.' ] === Diagnostics::notice_data( [ 'operation' => 'retry', 'count' => 3 ] ), 'retry count uses localized current-locale operation prose' );
$html = overview_render( [ 'probe' => $legacy ] );
overview_check( str_contains( $html, 'Échec' ) && ! str_contains( $html, 'Cached English failure' ), 'render never repeats stale legacy prose' );
$locale = 'en';
overview_check( str_contains( Diagnostics::probe_message( [ 'hit' => true, 'status' => 200, 'message' => 'Old locale' ] ), 'served from the Cache-Cache cache' ), 'legacy hit evidence maps to current cache-hit message' );
overview_check( str_contains( Diagnostics::probe_message( [ 'hit' => false, 'status' => 301, 'message' => 'Old locale' ] ), 'HTTP 301' ), 'legacy non-success status retains HTTP result' );
overview_check( str_contains( Diagnostics::probe_message( [ 'hit' => false, 'status' => 200, 'message' => 'Old locale' ] ), 'Nginx or a proxy may serve cached pages without this header' ), 'legacy header-missing evidence retains static/proxy guidance' );
overview_check( [ 'type' => 'success', 'message' => 'Legacy completed action.' ] === Diagnostics::notice_data( 'Legacy completed action.' ), 'legacy plain action completion remains a success during transition' );
foreach ( [ 'pause_css', 'resume_css', 'pause_preload', 'resume_preload', 'regenerate' ] as $operation ) {
	$outcome = Diagnostics::notice_data( [ 'operation' => $operation ] );
	overview_check( 'success' === $outcome['type'] && '' !== $outcome['message'], $operation . ' produces explicit completed-action success rather than generic blue information' );
}
$failed_outcome = Diagnostics::notice_data( [ 'hit' => false, 'status' => 0, 'code' => 'transport_error' ] );
$requests_before_render = count( $requests );
$outcome_html = overview_render( [ 'notice' => $failed_outcome ] );
$outcome_xpath = overview_document( $outcome_html );
overview_check( 1 === $outcome_xpath->query( '//*[@data-noobrocket-toast and contains(@class,"notice-error")]' )->length, 'failed probe outcome is marked for the shared tray with error severity' );
overview_check( 0 === $outcome_xpath->query( '//*[@data-noobrocket-toast and contains(@class,"notice-info")]' )->length, 'failed probe outcome never falls back to a blue information notice' );
overview_check( count( $requests ) === $requests_before_render, 'rendering saved action feedback issues no probe request' );
$resumed_html = overview_render( [ 'notice' => Diagnostics::notice_data( [ 'operation' => 'resume_preload' ] ) ] );
overview_check( 1 === overview_document( $resumed_html )->query( '//*[@data-noobrocket-toast and contains(@class,"notice-success")]' )->length, 'preloading resumed uses the same tray marker with success severity' );
$html = overview_render( [ 'probe' => $legacy ] );
overview_check( 1 === overview_document( $html )->query( '//section[contains(@class,"noobrocket-cache-status")]//div[dt="Delivery result" and dd="No response"]' )->length, 'transport failures have a meaningful delivery result' );
overview_check( str_contains( $html, gmdate( 'Y-m-d H:i', $legacy['checked_at'] ) ) && str_contains( $html, 'ago)' ), 'cache evidence retains full date and elapsed age' );
overview_check( str_contains( $html, 'Other pages and site speed are not tested.' ) && str_contains( $html, 'does not prove visitor caching is broken' ), 'probe scope remains available and failure limit stays visible' );
foreach ( [
	'transport error' => [ [ 'hit' => false, 'status' => 0, 'code' => 'transport_error' ], 'is-error', 'is-error', 'Check failed' ],
	'HTTP error'      => [ [ 'hit' => false, 'status' => 503, 'code' => 'http_error' ], 'is-error', 'is-error', 'Check failed' ],
	'legacy failure'  => [ $legacy, 'is-error', 'is-error', 'Check failed' ],
	'missing header'  => [ [ 'hit' => false, 'status' => 200, 'code' => 'header_missing' ], 'is-attention', 'is-warning', 'No cache header' ],
	'confirmed hit'   => [ [ 'hit' => true, 'status' => 200, 'code' => 'cache_hit' ], '', 'is-success', 'Cache hit confirmed' ],
	'not checked'     => [ null, '', 'is-neutral', 'Not checked' ],
] as $state => [ $record, $panel_class, $badge_class, $badge_label ] ) {
	$state_xpath = overview_document( overview_render( [ 'probe' => $record ] ) );
	$panel       = $state_xpath->query( '//section[contains(@class,"noobrocket-status-panel")][.//h3[contains(.,"Homepage cache check")]]' )->item( 0 );
	$badge       = $state_xpath->query( './/span[contains(@class,"noobrocket-badge")]', $panel )->item( 0 );
	overview_check( trim( $panel->getAttribute( 'class' ) ) === trim( 'noobrocket-status-panel noobrocket-cache-status ' . $panel_class ), $state . ' has the intended panel severity' );
	overview_check( 'noobrocket-badge ' . $badge_class === $badge->getAttribute( 'class' ) && $badge_label === $badge->textContent, $state . ' has the matching semantic badge and explicit label' );
	overview_check( 1 === $state_xpath->query( './/div[contains(@class,"noobrocket-cache-header")][.//h3[contains(.,"Homepage cache check")]][.//span[contains(@class,"noobrocket-badge")]][.//button[@value="probe"]]', $panel )->length, $state . ' groups the scoped heading, outcome and next action together' );
	overview_check( 0 === $state_xpath->query( './/strong', $panel )->length, $state . ' does not repeat its status as a competing outcome title' );
	if ( is_array( $record ) ) {
		$severity = 'Check failed' === $badge_label ? 'error' : ( 'Cache hit confirmed' === $badge_label ? 'success' : 'warning' );
		overview_check( $severity === Diagnostics::probe_severity( $record ), $state . ' action feedback severity agrees with persistent evidence' );
		overview_check( [ 'type' => $severity, 'message' => Diagnostics::probe_message( $record ) ] === Diagnostics::notice_data( $record ), $state . ' raw evidence retains old-record feedback compatibility' );
	}
}
$xpath = overview_document( $html );
overview_check( 1 === $xpath->query( '//section[contains(@class,"noobrocket-status-panel")][.//h3[contains(.,"Homepage cache check")]]//button[@value="probe"]' )->length, 'homepage next action stays with its status' );
overview_check( 1 === $xpath->query( '//section[contains(@class,"noobrocket-cache-status")]//p[contains(.,"Last check")][not(ancestor::details)]' )->length, 'homepage timestamp stays visible outside cache evidence disclosure' );
overview_check( 1 === $xpath->query( '//section[contains(@class,"noobrocket-cache-status")]//details[summary="Cache check details"][not(@open)]//p[contains(.,"Other pages and site speed are not tested.")]' )->length, 'probe scope is retained in closed cache evidence disclosure' );
overview_check( 0 === $xpath->query( '//details[contains(@class,"noobrocket-status-evidence")][@open]' )->length, 'routine queue evidence uses closed native disclosures' );
	overview_check( str_contains( $html, 'No pages queued' ) && str_contains( $html, 'Open a public page while logged out' ) && str_contains( $html, 'Generation needs the same-server worker.' )
	&& 1 === $xpath->query( '//section[contains(@class,"noobrocket-css-status")]//a[contains(@href,"docs/index.html#css")][.="Worker setup and scheduling"]' )->length, 'empty CSS queue explains enqueue and generation prerequisites with adjacent worker setup destination' );

foreach ( [
	'worker required' => [ [ 'counts' => [ 'pending' => 2 ] ], true ],
	'fresh setup check' => [ [ 'worker' => [ 'state' => 'ready', 'last_seen' => time() ] ], false ],
	'worker error' => [ [ 'worker' => [ 'state' => 'error', 'last_seen' => time() ] ], true ],
	'paused CSS' => [ [ 'paused' => true ], false ],
	'CSS off' => [ [ 'mode' => 'off' ], false ],
	'fresh worker activity' => [ [ 'worker' => [ 'state' => 'running', 'last_seen' => time() ] ], false ],
	'paused CSS with completed worker' => [ [ 'paused' => true, 'worker' => [ 'state' => 'stopped', 'last_seen' => time() ] ], false ],
] as $state => [ $changes, $open ] ) {
	$state_xpath = overview_document( overview_render( $changes ) );
	$worker_details = $state_xpath->query( '//details[contains(@class,"noobrocket-worker-details")]' )->item( 0 );
	if ( 'CSS off' === $state ) {
		overview_check( null === $worker_details, 'CSS off without a previous report omits irrelevant worker setup' );
		continue;
	}
	overview_check( null !== $worker_details && $worker_details->hasAttribute( 'open' ) === $open, $state . ' has the intended worker disclosure state' );
	overview_check( 1 === $state_xpath->query( '//section[contains(@class,"noobrocket-site-status")]/div[contains(@class,"noobrocket-card-body")]/details[contains(@class,"noobrocket-worker-details")]' )->length, $state . ' keeps deep worker evidence in the full-width supporting region of Site status' );
	overview_check( 1 === $state_xpath->query( '//a[contains(@href,"docs/index.html#css")][.="Worker setup and scheduling"]' )->length, $state . ' keeps one CSS worker setup destination in its owning task' );
}

$html = overview_render( [ 'worker' => [ 'state' => 'ready', 'last_seen' => time() - 301, 'node' => 'v22.0.0' ] ] );
overview_check( str_contains( $html, 'Environment check recorded' ) && str_contains( $html, 'Older than 5 min' ) && str_contains( $html, 'does not show a running or scheduled worker' ), 'historical ready report cannot imply current worker activity' );
overview_check( 1 === overview_document( $html )->query( '//details[contains(@class,"noobrocket-worker-details")]//dl/div[dt="Node.js" and dd="v22.0.0"]' )->length, 'runtime versions remain labeled in the worker disclosure' );
$html = overview_render( [ 'worker' => [ 'state' => 'ready', 'last_seen' => time() ] ] );
overview_check( str_contains( $html, 'Setup check only' ) && str_contains( $html, 'does not show a running or scheduled worker' ) && ! str_contains( $html, 'Recent worker activity is recorded.' ), 'fresh ready report still describes setup rather than liveness' );
$html = overview_render( [ 'counts' => [ 'pending' => 2 ], 'worker' => [ 'state' => 'running', 'last_seen' => time() ] ] );
overview_check( str_contains( $html, 'Recent worker activity is recorded. This report does not verify future scheduled runs.' ) && ! str_contains( $html, 'Waiting for server worker' ), 'recent running report supports observed activity only' );
$html = overview_render( [ 'counts' => [ 'pending' => 2 ], 'worker' => [ 'state' => 'idle', 'last_seen' => time() - 301 ] ] );
overview_check( str_contains( $html, 'Waiting for server worker' ) && str_contains( $html, 'Check the worker process and schedule.' ), 'stale active-state report guides queued work to scheduling' );
$html = overview_render( [ 'counts' => [ 'failed' => 2 ], 'failed' => [ [ 'url' => 'https://example.test/failure', 'reason' => 'Missing fixture font' ] ] ] );
$xpath = overview_document( $html );
overview_check( 1 === $xpath->query( '//section[.//h3[contains(.,"Local CSS generation")]]//button[@value="retry"]' )->length && str_contains( $html, 'Missing fixture font' ), 'failed jobs keep a local retry action and complete failure evidence' );
overview_check( ! str_contains( overview_render( [ 'mode' => 'off' ] ), 'value="retry"' ), 'disabled CSS mode does not offer an active failed-job retry' );
$html = overview_render( [ 'paused' => true, 'counts' => [ 'pending' => 2 ] ] );
overview_check( str_contains( $html, 'Pending work is preserved.' ) && str_contains( $html, 'value="resume_css"' ) && ! str_contains( $html, 'value="pause_css"' ), 'paused CSS directs to resume without implying work was cleared' );
overview_check( ! str_contains( $html, 'Previous activity' ) && str_contains( $html, 'No worker report' ), 'paused CSS without a worker report cannot imply previous activity' );
$html = overview_render( [ 'available' => false ] );
$xpath = overview_document( $html );
overview_check( str_contains( $html, 'CSS queue storage is unavailable.' )
	&& 1 === $xpath->query( '//section[contains(@class,"noobrocket-css-status") and contains(@class,"is-error")]//span[contains(@class,"noobrocket-badge") and contains(@class,"is-error")][.="Unavailable"]' )->length, 'missing queue storage identifies the failing layer with matching error panel and badge severity' );

$action_html = overview_render( [ 'preload_enabled' => true, 'counts' => [ 'failed' => 1 ] ] )
	. overview_render( [ 'paused' => true, 'preload_enabled' => true, 'preload_paused' => true ] );
$action_xpath = overview_document( $action_html );
foreach ( [ 'probe', 'pause_preload', 'resume_preload', 'retry', 'pause_css', 'resume_css', 'regenerate' ] as $operation ) {
	overview_check( $action_xpath->query( '//button[@type="submit"][@name="operation"][@value="' . $operation . '"][@form="noobrocket-diagnostics-actions"]' )->length > 0, $operation . ' retains its separate diagnostics form ownership' );
}
overview_check( 0 === $action_xpath->query( '//button[@type="submit"][not(@form="noobrocket-diagnostics-actions")]' )->length && 0 === $action_xpath->query( '//form' )->length, 'overview diagnostics never submits settings or nests an action form in its partial' );

$html = image_render();
overview_check( str_contains( $html, 'Configure image delivery' ) && str_contains( $html, 'Enable image delivery in Media and save.' ) && str_contains( $html, 'Then scan the library here' ), 'disabled image generation gives configure/save/scan sequence' );
overview_check( 1 === overview_document( $html )->query( '//button[@form="noobrocket-image-actions"][@disabled]' )->length, 'image queue remains disabled before saved enablement' );
$html = image_render( [ 'enabled' => true ] );
overview_check( 1 === overview_document( $html )->query( '//button[@form="noobrocket-image-actions"][not(@disabled)]' )->length && str_contains( $html, 'Scan media library' ) && str_contains( $html, 'Uses saved Media settings.' ), 'enabled fresh queue describes initial scan and saved setting boundary' );
$resumed_html = image_render( [ 'enabled' => true, 'active' => true, 'cursor' => 42, 'next_run' => time() + 30 ] );
overview_check( str_contains( $resumed_html, 'Scan queued' ) && ! str_contains( $resumed_html, 'Scan in progress' ) && str_contains( $resumed_html, 'Resume library scan' ), 'saved discovery progress explains its resume action without implying live execution' );
$html = image_render( [ 'enabled' => true, 'capabilities' => [ 'avif' => false, 'webp' => false ] ] );
overview_check( 1 === overview_document( $html )->query( '//button[@disabled]' )->length && str_contains( $html, 'Ask your host to enable AVIF or WebP encoding' ), 'missing encoder preserves disabled queue and concrete setup explanation' );
overview_check( Diagnostics::get_subscribed_events()['noobrocket_before_dashboard_content'][1] < Generator::get_subscribed_events()['noobrocket_before_dashboard_content'][1], 'native hook priorities put Site status before Modern images in DOM' );
overview_check( [ 'render', 15 ] === ObjectCacheDiagnostics::get_subscribed_events()['noobrocket_before_dashboard_content'], 'Object cache renders through the native dashboard hook at priority 15' );
overview_check( Generator::get_subscribed_events()['noobrocket_before_dashboard_content'][1] < ObjectCacheDiagnostics::get_subscribed_events()['noobrocket_before_dashboard_content'][1]
	&& ObjectCacheDiagnostics::get_subscribed_events()['noobrocket_before_dashboard_content'][1] < PageSpeed::get_subscribed_events()['noobrocket_before_dashboard_content'][1], 'native DOM order pairs Modern images with Object cache before optional PageSpeed results' );
echo 'PASS: standalone Overview evidence/locale/probe (', $checks, " assertions; no site bootstrap or external requests)\n";
