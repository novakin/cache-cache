<?php
/** Standalone proof of native CDN settings validation. No WordPress bootstrap, database, or network. */
namespace NoobRocket\Addon\Sucuri {
	class Subscriber {
		public static function is_api_key_valid( $value ) { return '' === $value; }
	}
}
namespace {
	class WP_Error {
		private $code;
		private $message;
		public function __construct( $code, $message ) { $this->code = $code; $this->message = $message; }
		public function get_error_code() { return $this->code; }
		public function get_error_message() { return $this->message; }
	}
	define( 'ABSPATH', __DIR__ );
	define( 'NOOBROCKET_FILE', dirname( __DIR__ ) . '/noobrocket/noobrocket.php' );
	$cdn_checks = 0;
	$wp_settings_errors = [];
	function apply_filters( $name, $value ) { return $value; }
	function __( $value, $domain = null ) { return $value; }
	function esc_html__( $value, $domain = null ) { return esc_html( $value ); }
	function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $value ) { return esc_html( $value ); }
	function number_format_i18n( $value ) { return number_format( $value ); }
	function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
	function add_filter( ...$args ) {}
	function register_activation_hook( ...$args ) {}
	function wpm_apply_filters_typed( $type, $name, $value ) {
		return 'noobrocket_mcp_options_allowlist' === $name ? array_merge( $value, $GLOBALS['mcp_extensions'] ?? [] ) : $value;
	}
	function get_transient( $name ) { return false; }
	function wp_parse_url( $value ) { return parse_url( $value ); }
	function set_url_scheme( $value ) { return preg_replace( '#^(?:https?:)?//#i', 'https://', $value ); }
	function untrailingslashit( $value ) { return rtrim( $value, '/\\' ); }
	function add_settings_error( $setting, $code, $message, $type = 'error' ) {
		$GLOBALS['wp_settings_errors'][] = compact( 'setting', 'code', 'message', 'type' );
	}
	function cdn_check( $condition, $message ) {
		if ( ! $condition ) { throw new \RuntimeException( $message ); }
		$GLOBALS['cdn_checks']++;
	}
	require dirname( __DIR__ ) . '/noobrocket/inc/classes/admin/class-options-data.php';
	require dirname( __DIR__ ) . '/noobrocket/inc/functions/formatting.php';
	require dirname( __DIR__ ) . '/noobrocket/inc/free.php';
	require dirname( __DIR__ ) . '/noobrocket/inc/Engine/Admin/Settings/Settings.php';
	set_error_handler( function ( $severity, $message, $file, $line ) {
		throw new \ErrorException( $message, 0, $severity, $file, $line );
	}, E_WARNING | E_NOTICE );
	$saved = [
		'cdn' => 1,
		'cdn_cnames' => [ 'https://saved.example.test' ],
		'cdn_zone' => [ 'images' ],
		'cdn_reject_files' => [ '/keep.css' ],
		'cdn_reject_pages' => [ '/keep-page/' ],
	];
	$settings = new \NoobRocket\Engine\Admin\Settings\Settings( new \NoobRocket\Admin\Options_Data( $saved ) );
	$input = [
		'cdn' => 0,
		'cdn_cnames' => [ 'https://new.example.test', 'not a hostname' ],
		'cdn_zone' => [ 'css', 'all' ],
		'cdn_reject_files' => '',
		'cdn_reject_pages' => '',
		'lazyload' => 1,
		'ignore' => 1,
	];
	$result = $settings->sanitize_callback( $input );
	foreach ( $saved as $name => $value ) {
		cdn_check( $result[ $name ] === $value, 'Invalid hostname preserves saved ' . $name );
	}
	cdn_check( 1 === $result['lazyload'], 'Invalid CDN preserves unrelated valid settings changes' );
	cdn_check( 1 === count( $wp_settings_errors ) && 'error' === $wp_settings_errors[0]['type'], 'Rejected CDN row emits native persistent settings error' );
	cdn_check( false !== strpos( $wp_settings_errors[0]['message'], '2' ) && false !== strpos( $wp_settings_errors[0]['message'], 'not a hostname' ), 'Native error identifies row and rejected input' );
	cdn_check( false !== strpos( $wp_settings_errors[0]['message'], '#page_cdn' ), 'Native error links to CDN correction destination' );
	$valid = $settings->sanitize_callback( [
		'cdn' => 1,
		'cdn_cnames' => [ '  cdn.example.test/  ', '', 'https://second.example.test/path/', 'cdn.example.test/' ],
		'cdn_zone' => [ 'css', 'images', 'js', 'all' ],
		'ignore' => 1,
	] );
	cdn_check( [ 'https://cdn.example.test', 'https://second.example.test/path' ] === $valid['cdn_cnames'], 'Valid hostnames retain normalization, paths, blank omission and duplicate removal' );
	cdn_check( [ 'css', 'js' ] === $valid['cdn_zone'], 'Valid rows retain matched file scopes after blank and duplicate omission' );
	$blank = $settings->sanitize_callback( [ 'cdn' => 0, 'cdn_cnames' => [ '', '  ' ], 'cdn_zone' => [ 'all', 'images' ], 'ignore' => 1 ] );
	cdn_check( [] === $blank['cdn_cnames'] && [] === $blank['cdn_zone'] && 0 === $blank['cdn'], 'Blank rows permit intended removal and disabling' );
	$off = new \NoobRocket\Engine\Admin\Settings\Settings( new \NoobRocket\Admin\Options_Data( [ 'cdn' => 0 ] ) );
	$off_result = $off->sanitize_callback( [ 'cdn' => 1, 'cdn_cnames' => [ 'bad hostname' ], 'ignore' => 1 ] );
	cdn_check( 0 === $off_result['cdn'] && [] === $off_result['cdn_cnames'] && [] === $off_result['cdn_zone'], 'Invalid first setup cannot enable rewriting' );
	$wp_settings_errors = [];
	$settings->sanitize_callback( [ 'cdn_cnames' => [ '<script>alert(1)</script>', 'bad hostname' ], 'ignore' => 1 ] );
	cdn_check( 2 === count( $wp_settings_errors ), 'Each rejected row receives error feedback' );
	cdn_check( false === strpos( $wp_settings_errors[0]['message'], '<script>' ) && false !== strpos( $wp_settings_errors[0]['message'], '&lt;script&gt;' ), 'Rejected input is escaped in native error' );
	foreach ( [ [ [ 'nested' ] ], 'not-an-array', [ false ], [ 'http://' ], [ 'https://-bad.example.test' ], [ 'ftp://cdn.example.test' ], [ 'javascript:alert@cdn.example.test' ], [ 'http:foo@cdn.example.test' ], [ 'https://cdn.example.test/bad path' ], [ 'bad-key' => 'cdn.example.test' ] ] as $malformed ) {
		$result = $settings->sanitize_callback( [ 'cdn' => 0, 'cdn_cnames' => $malformed, 'ignore' => 1 ] );
		cdn_check( $saved['cdn_cnames'] === $result['cdn_cnames'] && $saved['cdn_zone'] === $result['cdn_zone'] && 1 === $result['cdn'], 'Malformed input cannot throw or publish partial CDN configuration' );
	}
	$http = $settings->sanitize_callback( [ 'cdn' => 1, 'cdn_cnames' => [ 'HTTP://CDN.EXAMPLE.TEST/base/', '//cdn.example.test', 'HTTPS://secure.example.test/' ], 'cdn_zone' => [ 'css', 'js', 'images' ], 'ignore' => 1 ] );
	cdn_check( [ 'http://cdn.example.test/base', 'https://cdn.example.test', 'https://secure.example.test' ] === $http['cdn_cnames'], 'HTTP(S) case, hostname case and protocol-relative URL support remain valid' );
	$sparse = $settings->sanitize_callback( [ 'cdn_cnames' => [ 1000000000 => 'cdn.example.test', 2 => 'other.example.test' ], 'cdn_zone' => [ 1000000000 => 'js', 2 => 'css' ], 'ignore' => 1 ] );
	cdn_check( [ 'js', 'css' ] === $sparse['cdn_zone'], 'Sparse numeric row indices retain their scopes without scanning missing indices' );
	$bad_scope = $settings->sanitize_callback( [ 'cdn_cnames' => [ 'cdn.example.test' ], 'cdn_zone' => [ [ 'nested' ] ], 'ignore' => 1 ] );
	cdn_check( $saved['cdn_zone'] === $bad_scope['cdn_zone'] && $saved['cdn_cnames'] === $bad_scope['cdn_cnames'], 'Malformed scope preserves the saved CDN group instead of broadening rewriting' );
	$missing_scope = $settings->sanitize_callback( [ 'cdn_cnames' => [ 'cdn.example.test' ], 'ignore' => 1 ] );
	cdn_check( [ 'all' ] === $missing_scope['cdn_zone'], 'Missing scope safely uses existing default' );
	restore_error_handler();
	echo 'PASS: ', $cdn_checks, ' native CDN validation assertions.', PHP_EOL;
}
