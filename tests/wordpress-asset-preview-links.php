<?php
/** Signed Asset Manager navigation; no WordPress bootstrap, database or network. */
define( 'MINUTE_IN_SECONDS', 60 );
define( 'NOOBROCKET_PATH', dirname( __DIR__ ) . '/noobrocket/' );

class WP_Error {
	private $code;
	private $message;
	public function __construct( string $code, string $message ) { $this->code = $code; $this->message = $message; }
	public function get_error_code(): string { return $this->code; }
	public function get_error_message(): string { return $this->message; }
}
class AssetPreviewRedirect extends RuntimeException {
	public $url;
	public function __construct( string $url ) { $this->url = $url; parent::__construct( 'Preview redirect.' ); }
}
class AssetPreviewDenied extends RuntimeException {
	public $status;
	public function __construct( string $message, int $status ) { $this->status = $status; parent::__construct( $message ); }
}

$state = [ 'options' => [], 'meta' => [], 'notices' => [], 'authorized' => true, 'writes' => 0 ];
function __( string $message, string $domain = '' ): string { return $message; }
function esc_html__( string $message, string $domain = '' ): string { return $message; }
function wp_unslash( $value ) { return $value; }
function esc_url_raw( string $url ): string { return $url; }
function sanitize_key( string $value ): string { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ); }
function wp_json_encode( $value ): string { return json_encode( $value ); }
function current_user_can( string $capability ): bool { return $GLOBALS['state']['authorized']; }
function get_current_user_id(): int { return 1; }
function get_current_blog_id(): int { return 1; }
function get_option( string $key, $default = false ) { return $GLOBALS['state']['options'][ $key ] ?? $default; }
function get_user_meta( int $user, string $key, bool $single = false ) { return $GLOBALS['state']['meta'][ $key ] ?? []; }
function is_wp_error( $value ): bool { return $value instanceof WP_Error; }
function home_url( string $path = '' ): string { return 'https://example.test' . $path; }
function admin_url( string $path ): string { return home_url( '/wp-admin/' . $path ); }
function wp_parse_url( string $url ) { return parse_url( $url ); }
function wp_create_nonce( string $action ): string { return 'session-fixture-' . $action; }
function check_admin_referer( string $action ): void {
	if ( ! is_string( $_REQUEST['_wpnonce'] ?? null ) || $_REQUEST['_wpnonce'] !== wp_create_nonce( $action ) ) {
		wp_die( 'Invalid or expired request nonce.', '', [ 'response' => 403 ] );
	}
}
function wp_die( string $message, string $title = '', array $args = [] ): void {
	throw new AssetPreviewDenied( $message, $args['response'] ?? 500 );
}
function wp_safe_redirect( string $url ): void { throw new AssetPreviewRedirect( $url ); }
function set_transient( string $key, $value, int $ttl ): bool { $GLOBALS['state']['notices'][ $key ] = $value; return true; }
function update_option( string $key, $value, $autoload = null ): bool { ++$GLOBALS['state']['writes']; throw new RuntimeException( 'Unexpected option write.' ); }
function update_user_meta( int $user, string $key, $value ): void { ++$GLOBALS['state']['writes']; throw new RuntimeException( 'Unexpected private metadata write.' ); }
function delete_user_meta( int $user, string $key ): void { ++$GLOBALS['state']['writes']; throw new RuntimeException( 'Unexpected private metadata deletion.' ); }
function add_query_arg( $key, $value, ?string $url = null ): string {
	$args = is_array( $key ) ? $key : [ $key => $value ];
	$url = is_array( $key ) ? $value : $url;
	$parts = parse_url( $url );
	parse_str( $parts['query'] ?? '', $query );
	foreach ( $args as $name => $item ) {
		if ( null === $item ) { unset( $query[ $name ] ); } else { $query[ $name ] = $item; }
	}
	$base = $parts['scheme'] . '://' . $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' ) . ( $parts['path'] ?? '' );
	return $base . ( $query ? '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 ) : '' ) . ( isset( $parts['fragment'] ) ? '#' . $parts['fragment'] : '' );
}
function remove_query_arg( string $key, string $url ): string { return add_query_arg( $key, null, $url ); }

require NOOBROCKET_PATH . 'inc/classes/event-management/subscriber-interface.php';
require NOOBROCKET_PATH . 'inc/Engine/Optimization/Assets/Rules.php';
require NOOBROCKET_PATH . 'inc/Engine/Optimization/Assets/Frontend.php';
require NOOBROCKET_PATH . 'inc/Engine/Optimization/Assets/Snapshots.php';
require NOOBROCKET_PATH . 'inc/Engine/Optimization/Assets/Admin.php';

use NoobRocket\Engine\Optimization\Assets\Admin;
use NoobRocket\Engine\Optimization\Assets\Frontend;
use NoobRocket\Engine\Optimization\Assets\Rules;
use NoobRocket\Engine\Optimization\Assets\Snapshots;

$checks = 0;
function preview_link_check( bool $condition, string $message ): void {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
	++$GLOBALS['checks'];
}
function preview_link_request( string $method, array $fields = [], array $opposite_fields = [] ) {
	$fields = array_merge( [ 'command' => 'preview', '_wpnonce' => wp_create_nonce( 'noobrocket_assets' ), 'draft_revision' => Rules::draft_revision( $GLOBALS['admin']->draft() ), 'url' => home_url( '/article/' ) ], $fields );
	$_SERVER['REQUEST_METHOD'] = $method;
	$_GET = 'POST' !== $method ? $fields : $opposite_fields;
	$_POST = 'POST' === $method ? $fields : $opposite_fields;
	$_REQUEST = array_merge( $_GET, $_POST );
	$before = [ $GLOBALS['state']['options'], $GLOBALS['state']['meta'], $GLOBALS['state']['writes'] ];
	$GLOBALS['state']['notices'] = [];
	try {
		$GLOBALS['admin']->handle_request();
	} catch ( AssetPreviewRedirect | AssetPreviewDenied $result ) {
		preview_link_check( $before === [ $GLOBALS['state']['options'], $GLOBALS['state']['meta'], $GLOBALS['state']['writes'] ], 'Navigation preserves settings, history, published rules and private work.' );
		return $result;
	}
	throw new RuntimeException( 'Expected a native redirect or denied request.' );
}

$rule = [ 'type' => 'script', 'handle' => 'private-rule', 'scope' => 'everywhere', 'value' => '', 'exceptions' => [] ];
$draft = [ 'base' => [], 'rules' => [ $rule ] ];
$state['options'] = [ Rules::OPTION => [], Snapshots::OPTION => [ 'retained-history' ], 'noobrocket_settings' => [ 'minify_js' => 1 ] ];
$state['meta'] = [ Frontend::user_key( 'draft' ) => $draft, Frontend::user_key( 'entry' ) => [ 'handle' => 'recoverable-entry' ], Frontend::user_key( 'inventory' ) => [ 'url' => home_url( '/previous/' ) ] ];
$rules = new Rules();
$admin = new Admin( $rules, new Snapshots( $rules ) );

foreach ( [ 'GET', 'POST' ] as $method ) {
	$result = preview_link_request( $method, [ 'url' => home_url( '/edited/?view=compact#section' ) ] );
	preview_link_check( $result instanceof AssetPreviewRedirect && '/edited/' === parse_url( $result->url, PHP_URL_PATH ), $method . ' opens the edited frontend URL.' );
	parse_str( parse_url( $result->url, PHP_URL_QUERY ), $query );
	preview_link_check( 'compact' === $query['view'] && 'section' === parse_url( $result->url, PHP_URL_FRAGMENT ), $method . ' preserves the page query and fragment.' );
	preview_link_check( wp_create_nonce( Frontend::PREVIEW ) === $query[ Frontend::PREVIEW ] && Rules::draft_revision( $draft ) === $query[ Frontend::REVISION ], $method . ' signs the current session and reviewed draft.' );
}

$result = preview_link_request( 'GET', [ 'url' => home_url( '/?noobrocket_asset_preview=old&noobrocket_asset_revision=old' ) ] );
parse_str( parse_url( $result->url, PHP_URL_QUERY ), $query );
preview_link_check( wp_create_nonce( Frontend::PREVIEW ) === $query[ Frontend::PREVIEW ] && Rules::draft_revision( $draft ) === $query[ Frontend::REVISION ], 'Existing preview parameters are replaced rather than trusted.' );

foreach ( [ '', 'older-review', [ 'malformed' ] ] as $revision ) {
	$result = preview_link_request( 'GET', [ 'draft_revision' => $revision ] );
	parse_str( parse_url( $result->url, PHP_URL_QUERY ), $query );
	preview_link_check( '0' === $query['asset_updated'] && '#assets' === substr( $result->url, -7 ) && 'https://example.test/wp-admin/options-general.php' === strtok( $result->url, '?' ), 'A stale or malformed review returns safely to Asset Manager.' );
	preview_link_check( false !== strpos( $state['notices']['noobrocket_asset_notice_1']['message'], 'private draft changed' ), 'Review failure explains how to open the current draft.' );
}

foreach ( [ 'https://foreign.test/article/', 'http://example.test/article/', 'https://example.test:8443/article/', 'https://user:pass@example.test/article/', 'https://example.test/wp-admin/', 'https://example.test/wp-login.php', [ 'malformed' ] ] as $url ) {
	$result = preview_link_request( 'GET', [ 'url' => $url ] );
	parse_str( parse_url( $result->url, PHP_URL_QUERY ), $query );
	preview_link_check( $result instanceof AssetPreviewRedirect && '0' === $query['asset_updated'] && '/wp-admin/options-general.php' === parse_url( $result->url, PHP_URL_PATH ), 'Foreign, admin or malformed destinations never become frontend redirects.' );
}

foreach ( [ 'add', 'remove', 'discard', 'publish', 'restore', 'unknown', '', [ 'preview' ] ] as $command ) {
	$result = preview_link_request( 'GET', [ 'command' => $command ], [ 'command' => 'preview' ] );
	preview_link_check( $result instanceof AssetPreviewDenied && 405 === $result->status && [] === $state['notices'], 'GET cannot select a mutation or read a shadow POST command.' );
}

$result = preview_link_request( 'HEAD' );
preview_link_check( $result instanceof AssetPreviewDenied && 405 === $result->status, 'Methods other than the supported GET preview and native POST remain denied.' );
$state['authorized'] = false;
$result = preview_link_request( 'GET' );
preview_link_check( $result instanceof AssetPreviewDenied && 403 === $result->status, 'An unauthorized account cannot open the signing endpoint.' );
$state['authorized'] = true;
foreach ( [ 'GET', 'POST' ] as $method ) {
	$result = preview_link_request( $method, [ '_wpnonce' => 'invalid' ] );
	preview_link_check( $result instanceof AssetPreviewDenied && 403 === $result->status, $method . ' still requires the Asset Manager request nonce.' );
}

echo 'PASS: signed Asset Manager preview links (', $checks, " isolated assertions; no WordPress/database/network)\n";
