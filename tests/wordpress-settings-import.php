<?php
/** Standalone import transaction proof. No WordPress bootstrap, database, or network. */
namespace NoobRocket\Admin {
	class Options {
		public function __construct( $prefix ) {}
		public function get( $name, $default = null ) { return $GLOBALS['import_options']; }
		public function set( $name, $value ) {
			if ( ! empty( $GLOBALS['import_refuse_write'] ) ) { return; }
			$GLOBALS['import_writes']++;
			$GLOBALS['import_options'] = $value;
		}
	}
}
namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'NOOBROCKET_FILE', dirname( __DIR__ ) . '/noobrocket/noobrocket.php' );
	define( 'NOOBROCKET_PLUGIN_SLUG', 'noobrocket' );
	define( 'NOOBROCKET_SLUG', 'noobrocket_settings' );
	define( 'MINUTE_IN_SECONDS', 60 );
	class ImportRedirect extends \RuntimeException {
		public $status;
		public function __construct( $message, $status ) { parent::__construct( $message ); $this->status = $status; }
	}
	function add_action( ...$args ) {}
	function add_filter( ...$args ) {}
	function remove_filter( ...$args ) {}
	function register_activation_hook( ...$args ) {}
	function plugin_basename( $path ) { return basename( $path ); }
	function apply_filters( $name, $value ) { return $value; }
	function __( $value, $domain = null ) { return $value; }
	function esc_html__( $value, $domain = null ) { return esc_html( $value ); }
	function esc_attr__( $value, $domain = null ) { return esc_html( $value ); }
	function esc_html_e( $value, $domain = null ) { echo esc_html( $value ); }
	function esc_attr_e( $value, $domain = null ) { echo esc_html( $value ); }
	function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $value ) { return esc_html( $value ); }
	function esc_url( $value ) { return esc_html( $value ); }
	function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
	function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) ); }
	function sanitize_file_name( $value ) { return basename( $value ); }
	function wp_unslash( $value ) { return $value; }
	function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
	function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
	function number_format_i18n( $value ) { return number_format( $value ); }
	function sanitize_option( $name, $value ) {
		if ( NOOBROCKET_SLUG !== $name || 1 !== ( $value['ignore'] ?? null ) ) throw new \RuntimeException( 'Import must use native settings validation without a saved notice.' );
		$GLOBALS['import_validations']++;
		unset( $value['ignore'] );
		if ( ! empty( $GLOBALS['import_validation_changed'] ) ) $value['heartbeat_admin_behavior'] = '';
		return $value;
	}
	function admin_url( $value ) { return 'http://fixture.invalid/wp-admin/' . $value; }
	function current_user_can( $capability ) { return $GLOBALS['import_permission']; }
	function get_current_user_id() { return 7; }
	function check_ajax_referer( $action, $name ) {
		$GLOBALS['import_nonce_checks']++;
		if ( ( $_POST[ $name ] ?? '' ) !== 'valid-fixture-nonce' ) throw new ImportRedirect( 'Invalid nonce', 'nonce' );
	}
	function get_allowed_mime_types() { return [ 'json' => 'application/json' ]; }
	function noobrocket_allow_json_mime_type( $mimes ) { return $mimes; }
	function wp_check_filetype_and_ext( ...$args ) { return [ 'type' => 'application/json' ]; }
	function wp_handle_sideload( $file, $overrides ) { return [ 'file' => $file['tmp_name'] ]; }
	function noobrocket_put_content( $path, $value ) { file_put_contents( $path, $value ); }
	function noobrocket_direct_filesystem() {
		return new class {
			public function get_contents( $path ) { return file_get_contents( $path ); }
			public function delete( $path ) { return unlink( $path ); }
		};
	}
	function set_transient( $key, $value, $ttl ) { $GLOBALS['import_reviews'][ $key ] = $value; $GLOBALS['import_ttl'] = $ttl; }
	function get_transient( $key ) { return $GLOBALS['import_reviews'][ $key ] ?? false; }
	function delete_transient( $key ) { unset( $GLOBALS['import_reviews'][ $key ] ); }
	function wp_generate_uuid4() { return 'fixture-review-' . ++$GLOBALS['import_token']; }
	function wp_safe_redirect( $url ) { throw new ImportRedirect( $url, 'redirect' ); }
	function noobrocket_settings_import_redirect( $message, $status ) { throw new ImportRedirect( $message, $status ); }
	function do_action( $name, ...$args ) { $GLOBALS['import_actions'][] = [ $name, $args ]; }
	function wp_nonce_field( $action, $name ) { echo '<input type="hidden" name="', esc_attr( $name ), '" value="valid-fixture-nonce">'; }

	require dirname( __DIR__ ) . '/noobrocket/inc/free.php';
	require dirname( __DIR__ ) . '/noobrocket/inc/admin/admin.php';
	$import_permission = true;
	$import_writes = 0;
	$import_nonce_checks = 0;
	$import_reviews = [];
	$import_token = 0;
	$import_actions = [];
	$import_validations = 0;
	$import_options = noobrocket_free_settings( [
		'heartbeat_admin_behavior' => 'reduce_periodicity', 'cloudflare_api_key' => 'existing-private-fixture', 'do_cloudflare' => 0,
		'secret_cache_key' => 'cache-private-fixture', 'minify_css_key' => 'css-private-fixture',
		'minify_js_key' => 'js-private-fixture', 'version' => 'fixture-version', 'engine_version' => 'fixture-engine',
		'cache_mobile' => 0, 'do_caching_mobile_files' => 0,
	] );
	$baseline = $import_options;
	$proposed = array_merge( $baseline, [ 'heartbeat_admin_behavior' => 'disable', 'cloudflare_api_key' => 'replacement-private-fixture', 'do_cloudflare' => 1, 'secret_cache_key' => 'wrong-private-fixture', 'async_css' => 1, 'submit_optimize' => 1 ] );
	$review_key = 'noobrocket_import_review_7';
	$checks = 0;
	$check = static function ( $condition, $label ) use ( &$checks ) {
		if ( ! $condition ) throw new \RuntimeException( 'FAIL: ' . $label );
		$checks++;
	};
	$invoke = static function () {
		try { noobrocket_handle_settings_import(); } catch ( ImportRedirect $result ) { return $result; }
		throw new \RuntimeException( 'Import handler did not redirect.' );
	};
	$upload = static function ( $content ) use ( $invoke ) {
		$path = tempnam( sys_get_temp_dir(), 'noobrocket-import-' );
		file_put_contents( $path, $content );
		$_POST = [ 'action' => 'noobrocket_import_settings', 'noobrocket_import_settings_nonce' => 'valid-fixture-nonce' ];
		$_FILES = [ 'import' => [ 'name' => 'noobrocket-settings-2026-10-09-abcdef0123456.json', 'size' => strlen( $content ), 'tmp_name' => $path ] ];
		try { return $invoke(); } finally { if ( is_file( $path ) ) unlink( $path ); }
	};
	$confirm = static function ( $token, $cancel = false ) use ( $invoke ) {
		$_FILES = [];
		$_POST = [ 'action' => 'noobrocket_import_settings', 'noobrocket_import_settings_nonce' => 'valid-fixture-nonce', 'noobrocket_import_review' => $token ];
		if ( $cancel ) $_POST['noobrocket_import_cancel'] = '1';
		return $invoke();
	};
	$result = $upload( json_encode( $proposed ) );
	$review = get_transient( $review_key );
	$check( 'redirect' === $result->status && false !== strpos( $result->getMessage(), '#tools' ) && 0 === $import_writes && $baseline === $import_options, 'upload only stages a review' );
	$check( 600 === $import_ttl && $review['current'] === $baseline && $review['settings']['secret_cache_key'] === $baseline['secret_cache_key'] && 0 === $review['settings']['async_css'], 'review preserves cache identities and uses edition normalization' );
	$check( 1 === $import_validations && ! isset( $review['settings']['submit_optimize'] ) && ! isset( $review['settings']['ignore'] ), 'review reuses native validation and cannot import a cleanup operation' );
	$data = [ 'review_only' => true, 'action' => 'noobrocket_import_settings', 'upload_dir' => [], 'size' => '1 MB', 'bytes' => 1048576, 'labels' => [ 'heartbeat_admin_behavior' => 'Administration Heartbeat' ], 'choices' => [ 'heartbeat_admin_behavior' => [ 'reduce_periodicity' => 'Reduce activity', 'disable' => 'Disable' ] ], 'types' => [ 'do_cloudflare' => 'noobrocket_addon' ] ];
	ob_start();
	include dirname( __DIR__ ) . '/noobrocket/views/settings/fields/import-form.php';
	$html = ob_get_clean();
	$check( false !== strpos( $html, 'Administration Heartbeat' ) && false !== strpos( $html, 'Reduce activity' ) && false !== strpos( $html, 'Disable' ) && false !== strpos( $html, 'Private value (hidden)' ) && false === strpos( $html, 'existing-private-fixture' ) && false === strpos( $html, 'replacement-private-fixture' ), 'review shows native field and choice labels without credentials' );
	$check( 1 === preg_match( '/data-noobrocket-import-setting="do_cloudflare"[^<]*<\/th><td\b[^>]*>Off<\/td><td\b[^>]*>On<\/td>/', $html ), 'provider behavior changes use readable native toggle states while credentials are hidden' );
	$result = $confirm( $review['token'] );
	$check( 'updated' === $result->status && 1 === $import_writes && $import_options === $review['settings'] && false === get_transient( $review_key ) && 'noobrocket_after_save_import' === $import_actions[0][0], 'confirmation writes exactly the reviewed proposal once' );
	$import_options = $baseline;
	$upload( json_encode( $proposed ) );
	$review = get_transient( $review_key );
	$import_options['heartbeat_admin_behavior'] = 'default';
	$result = $confirm( $review['token'] );
	$check( 'error' === $result->status && 1 === $import_writes && false === get_transient( $review_key ), 'concurrent saved-setting changes invalidate confirmation' );
	$import_options = $baseline;
	$upload( json_encode( $proposed ) );
	$older = get_transient( $review_key );
	$upload( json_encode( $proposed ) );
	$newer = get_transient( $review_key );
	$result = $confirm( $older['token'] );
	$check( 'error' === $result->status && $newer === get_transient( $review_key ) && 1 === $import_writes, 'an older tab cannot confirm the newer review' );
	$result = $confirm( $newer['token'], true );
	$check( 'updated' === $result->status && $baseline === $import_options && 1 === $import_writes && false === get_transient( $review_key ), 'cancel deletes staged review without saving settings' );
	$result = $confirm( $newer['token'] );
	$check( 'error' === $result->status && 1 === $import_writes, 'expired or consumed review cannot be committed' );
	$upload( json_encode( $proposed ) );
	$review = get_transient( $review_key );
	$import_validation_changed = true;
	$result = $confirm( $review['token'] );
	$check( 'error' === $result->status && 1 === $import_writes && false === get_transient( $review_key ), 'changed native validation invalidates the reviewed proposal' );
	$import_validation_changed = false;
	$result = $upload( '[1,2,3]' );
	$check( 'error' === $result->status && 1 === $import_writes && false === get_transient( $review_key ), 'non-settings JSON is rejected before review' );
	$import_permission = false;
	$result = $confirm( $newer['token'] );
	$check( 'error' === $result->status && 1 === $import_writes, 'import capability remains required on confirmation' );
	$import_permission = true;
	$_POST['noobrocket_import_settings_nonce'] = 'invalid';
	$result = $invoke();
	$check( 'nonce' === $result->status && 1 === $import_writes && $import_nonce_checks >= 10, 'native nonce remains required on every import path' );
	$import_options = $baseline;
	$upload( json_encode( $proposed ) );
	$review = get_transient( $review_key );
	$import_refuse_write = true;
	$before_actions = count( $import_actions );
	$result = $confirm( $review['token'] );
	$check( 'error' === $result->status && $baseline === $import_options && $before_actions === count( $import_actions ), 'refused changed writes report failure without post-save actions' );
	$upload( json_encode( $baseline ) );
	$review = get_transient( $review_key );
	$result = $confirm( $review['token'] );
	$check( 'updated' === $result->status && $baseline === $import_options, 'an unchanged valid import remains successful even without a changed write' );
	$import_refuse_write = false;
	echo 'PASS: ', $checks, ' settings import transaction checks; no WordPress or live writes.', PHP_EOL;
}
