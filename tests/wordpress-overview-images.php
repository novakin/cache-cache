<?php
/** Modern-image overview: truthful saved queue/encoder evidence, without loading a site. */
declare(strict_types=1);

define( 'ABSPATH', __DIR__ );
define( 'NOOBROCKET_PATH', dirname( __DIR__ ) . '/noobrocket/' );
$checks = 0;
$settings = [ 'image_delivery' => 1 ];
$generation = [];
$encoders = [ 'avif' => true, 'webp' => true ];
$next_batch = 1800000030;
$pending_items = 0;
$failed_items = 0;
$reads = [];
function apply_filters( $hook, $value ) { return $value; }
function __( $text, $domain = '' ) { return $text; }
function _n( $single, $plural, $number, $domain = '' ) { return 1 === (int) $number ? $single : $plural; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $text ) { return esc_html( $text ); }
function esc_html_e( $text, $domain = '' ) { echo esc_html( __( $text, $domain ) ); }
function disabled( $value ) { if ( $value ) { echo 'disabled="disabled"'; } }
function number_format_i18n( $number ) { return number_format( $number ); }
function get_option( $name, $default = false ) {
	return [ 'noobrocket_image_generation' => $GLOBALS['generation'], 'date_format' => 'Y-m-d', 'time_format' => 'H:i' ][ $name ] ?? $default;
}
function wp_image_editor_supports( $args ) { return $GLOBALS['encoders'][ substr( $args['mime_type'], 6 ) ] ?? false; }
function wp_next_scheduled( $hook ) {
	if ( 'noobrocket_generate_images' !== $hook ) { throw new RuntimeException( 'Unexpected cron ownership.' ); }
	return $GLOBALS['next_batch'];
}
function wp_date( $format, $timestamp ) { return gmdate( $format, $timestamp ); }
class OverviewImagesDatabase {
	public $postmeta = 'postmeta';
	public function prepare( $sql, ...$args ) { return [ $sql, $args ]; }
	public function get_var( $query ) {
		$GLOBALS['reads'][] = $query;
		return '_noobrocket_image_pending' === $query[1][0] ? $GLOBALS['pending_items'] : $GLOBALS['failed_items'];
	}
}
$wpdb = new OverviewImagesDatabase();
require NOOBROCKET_PATH . 'inc/classes/event-management/subscriber-interface.php';
require NOOBROCKET_PATH . 'inc/classes/admin/class-options-data.php';
require NOOBROCKET_PATH . 'inc/Engine/Media/Picture/Generator.php';
use NoobRocket\Admin\Options_Data;
use NoobRocket\Engine\Media\Picture\Generator;

function images_check( bool $condition, string $message ): void {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
	++$GLOBALS['checks'];
}
function images_render( array $changes = [] ): array {
	$generator = new Generator( new Options_Data( $GLOBALS['settings'] ) );
	$data = array_replace_recursive( $generator->status(), $changes );
	ob_start();
	require NOOBROCKET_PATH . 'views/settings/partials/image-generation.php';
	$html = ob_get_clean();
	$document = new DOMDocument();
	libxml_use_internal_errors( true );
	$document->loadHTML( $html );
	libxml_clear_errors();
	return [ $html, new DOMXPath( $document ), $data ];
}
function image_badge( DOMXPath $xpath ): string {
	return trim( $xpath->query( '//span[contains(@class,"noobrocket-badge")]' )->item( 0 )->textContent );
}
function image_action( DOMXPath $xpath ): string {
	return trim( $xpath->query( '//button[@form="noobrocket-image-actions"]' )->item( 0 )->textContent );
}

list( $html, $xpath, $data ) = images_render();
images_check( $data['next_run'] === $next_batch && 2 === count( $reads ), 'status reads its own scheduled event and existing queue counts only' );
images_check( 'No images queued' === image_badge( $xpath ) && 'Scan media library' === image_action( $xpath ), 'enabled fresh installation reports no work and prompts an explicit initial scan' );
images_check( str_contains( $html, 'New uploads are queued automatically. Scan the library to convert existing images.' ), 'automatic uploads are distinct from an existing-library backfill' );
images_check( str_contains( $html, 'Uses saved Media settings.' ), 'operation ownership is visible before optional evidence' );
images_check( 1 === $xpath->query( '//button[@type="submit" and @form="noobrocket-image-actions"]' )->length, 'backfill continues to use the independent native action form' );
images_check( 0 === $xpath->query( '//button[@disabled]' )->length, 'supported enabled generation remains actionable' );

list( $html, $xpath ) = images_render( [ 'enabled' => false, 'active' => true, 'cursor' => 12, 'pending' => 2 ] );
images_check( 'Off' === image_badge( $xpath ) && 1 === $xpath->query( '//button[@disabled]' )->length, 'saved disabled setting takes precedence over retained work' );
images_check( str_contains( $html, 'Enable image delivery in Media and save.' ) && ! str_contains( $html, 'Next batch scheduled for:' ), 'disabled conversion shows setup rather than a running/scheduled promise' );

foreach ( [ 'avif', 'webp' ] as $format ) {
	list( $html, $xpath ) = images_render( [ 'capabilities' => [ 'avif' => 'avif' === $format, 'webp' => 'webp' === $format ] ] );
	images_check( str_contains( $html, 'Generate ' . ( 'avif' === $format ? 'AVIF' : 'WebP' ) . ' locally.' ) && ! str_contains( $html, 'Generate AVIF and WebP locally.' ), 'feature promise follows the actual ' . $format . ' encoder' );
}
list( $html, $xpath ) = images_render( [ 'capabilities' => [ 'avif' => false, 'webp' => false ] ] );
images_check( 'Unavailable' === image_badge( $xpath ) && 1 === $xpath->query( '//button[@disabled]' )->length, 'unsupported encoders show an unavailable operation' );
images_check( str_contains( $html, 'Ask your host to enable AVIF or WebP encoding' ) && ! str_contains( $html, 'Generate AVIF and WebP locally.' ), 'unsupported host is shown a concrete prerequisite rather than a conversion promise' );
list( $html, $xpath ) = images_render( [ 'enabled' => false, 'capabilities' => [ 'avif' => false, 'webp' => false ] ] );
images_check( 'Off' === image_badge( $xpath ) && ! str_contains( $html, 'Ask your host' ), 'an off feature does not display an active encoder warning' );

list( $html, $xpath ) = images_render( [ 'active' => true ] );
images_check( 'Scan queued' === image_badge( $xpath ) && 'Resume library scan' === image_action( $xpath ), 'active initial discovery with cursor zero is queued rather than complete' );
list( $html, $xpath ) = images_render( [ 'active' => true, 'cursor' => 42, 'last_run' => 1800000000 ] );
images_check( 'Scan queued' === image_badge( $xpath ) && ! str_contains( $html, 'Scan in progress' ), 'saved discovery progress cannot claim a batch is executing' );
images_check( str_contains( $html, 'Recorded progress does not mean a batch is running now.' ), 'unfinished library work is explicitly waiting for cron' );
list( $html, $xpath ) = images_render( [ 'active' => true, 'cursor' => 42, 'next_run' => 0 ] );
images_check( 'Needs scheduling' === image_badge( $xpath ) && str_contains( $html, 'Resume the library scan to queue one.' ), 'retained work without a scheduled event exposes the existing recovery action' );
list( $html, $xpath ) = images_render( [ 'active' => true, 'cursor' => 42, 'next_run' => time() - 600 ] );
images_check( 'Scheduled batch overdue' === image_badge( $xpath ) && 1 === $xpath->query( '//span[contains(@class,"is-warning")]' )->length, 'an overdue event is not presented as a healthy running scan' );
images_check( str_contains( $html, 'Check WordPress cron; pending work is preserved.' ), 'overdue scheduling names the responsible runtime without assuming cron failure' );
list( $html, $xpath ) = images_render( [ 'active' => true, 'next_run' => time() - 120 ] );
images_check( 'Scan queued' === image_badge( $xpath ), 'ordinary cron timing has a grace period before an overdue warning' );

list( $html, $xpath ) = images_render( [ 'pending' => 1 ] );
images_check( 'Images queued' === image_badge( $xpath ) && 'Scan media library' === image_action( $xpath ), 'upload-only queue does not imply that library discovery has started' );
images_check( str_contains( $html, '1 media item awaiting generation.' ), 'queue count describes attachments and uses the singular form' );
list( $html, $xpath ) = images_render( [ 'pending' => 4, 'cursor' => 42 ] );
images_check( 'Images queued' === image_badge( $xpath ) && 'Resume library scan' === image_action( $xpath ), 'an exhausted discovery cursor with pending encodes is still incomplete work' );
images_check( str_contains( $html, '4 media items awaiting generation.' ), 'multiple queued attachments use plural count copy' );

list( $html, $xpath ) = images_render( [ 'cursor' => 42, 'last_run' => 1800000000 ] );
images_check( 'Library scan complete' === image_badge( $xpath ) && 'Scan media library' === image_action( $xpath ), 'completion requires exhausted discovery with no pending items and offers a new scan' );
images_check( str_contains( $html, 'A completed scan does not mean every image has a new format.' ), 'completion preserves the minimum-saving and skipped-image boundary' );
images_check( str_contains( $html, 'Last batch: ' ) && ! str_contains( $html, 'Next batch scheduled for:' ), 'completed work keeps its last-run evidence without implying a further batch is required' );
images_check( 1 === $xpath->query( '//p[contains(.,"Last batch:")][not(ancestor::details)]' )->length, 'recorded batch date remains visible beside the status without opening encoder evidence' );
list( $html, $xpath ) = images_render( [ 'last_run' => 1800000000 ] );
images_check( 'No images queued' === image_badge( $xpath ), 'upload-only or empty-library history cannot prove a completed nonempty library scan' );

list( $html, $xpath ) = images_render( [ 'failed' => 1, 'cursor' => 42, 'last_run' => 1800000000 ] );
images_check( 'Needs attention' === image_badge( $xpath ) && 1 === $xpath->query( '//span[contains(@class,"is-error")]' )->length, 'failed conversion is a danger state rather than success or informational blue' );
images_check( str_contains( $html, '1 media item failed; its original is unchanged.' ) && str_contains( $html, 'Scan the library again to retry failed items.' ), 'completed discovery offers the real full-rescan retry action' );
list( $html, $xpath ) = images_render( [ 'active' => true, 'pending' => 2, 'failed' => 3, 'cursor' => 42 ] );
images_check( str_contains( $html, '3 media items failed; their originals are unchanged.' ) && str_contains( $html, 'Finish the current scan, then scan again to retry failed items.' ), 'resume does not promise to revisit earlier failures behind its cursor' );
images_check( 'Resume library scan' === image_action( $xpath ), 'failed items do not alter the existing current-cursor operation' );
list( $html, $xpath ) = images_render( [ 'enabled' => false, 'pending' => 2, 'failed' => 1 ] );
images_check( ! str_contains( $html, 'media item failed' ) && ! str_contains( $html, 'to retry failed items.' ) && 1 === $xpath->query( '//details//dl/div[dt="Failed media items" and dd="1"]' )->length, 'disabled operation retains failure count in evidence without an active warning or retry' );
list( $html, $xpath ) = images_render( [ 'generated' => 37, 'pending' => 2 ] );
images_check( 1 === $xpath->query( '//details//dl/div[dt="Successful encodes recorded" and dd="37"]' )->length && str_contains( $html, 'It is not a count of media items or files currently on disk.' ), 'existing generated count exposes historical proof without claiming present coverage' );
images_check( str_contains( $html, 'Requires PHP GD or Imagick with AVIF or WebP encoding' ) && str_contains( $html, 'Node.js and Chromium are not required for images.' ), 'image prerequisites distinguish native PHP encoding from the CSS worker' );
images_check( 1 === $xpath->query( '//details//dl/div[dt="AVIF encoder" and dd="Available"]' )->length && 1 === $xpath->query( '//details//dl/div[dt="WebP encoder" and dd="Available"]' )->length, 'each encoder capability has an explicit label and outcome' );

echo 'PASS: ' . $checks . " modern-image overview assertions\n";
