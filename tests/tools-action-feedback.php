<?php
/** Actual Tools AJAX owners with modeled nonce, authorization and option storage. No WordPress bootstrap or HTTP. */
define( 'ABSPATH', __DIR__ );

class ToolsAjaxResponse extends RuntimeException {
	public $success;
	public function __construct( $success ) { $this->success = $success; }
}
class ToolsNonceFailure extends RuntimeException {}
function apply_filters( $name, $value ) { return $value; }
function wpm_apply_filters_typed( $type, $name, $value ) { return $value; }
function noobrocket_get_constant( $name, $default = null ) { return $default; }
function current_user_can( $capability ) {
	tools_check( 'noobrocket_manage_options' === $capability, 'Existing action capability is retained' );
	return $GLOBALS['authorized'];
}
function check_ajax_referer( $action, $name = null, $stop = true ) {
	tools_check( 'noobrocket-ajax' === $action && 'nonce' === $name && true === $stop, 'Existing nonce contract is retained' );
	if ( ! $GLOBALS['valid_nonce'] ) { throw new ToolsNonceFailure(); }
	return 1;
}
function get_option( $name, $default = false ) {
	tools_check( 'noobrocket_settings' === $name, 'Readback uses the raw persisted settings option' );
	return $GLOBALS['persisted'];
}
function update_option( $name, $value ) {
	tools_check( 'noobrocket_settings' === $name, 'Writer uses the existing settings option' );
	++$GLOBALS['writes'];
	if ( 'refuse' === $GLOBALS['writer'] ) { return false; }
	if ( 'partial' === $GLOBALS['writer'] ) { $value['do_caching_mobile_files'] = 0; }
	if ( 'adjust' === $GLOBALS['writer'] ) { $value['minify_google_fonts'] = 0; }
	$changed = $GLOBALS['persisted'] !== $value;
	$GLOBALS['persisted'] = $value;
	return $changed;
}
function wp_send_json_success() { throw new ToolsAjaxResponse( true ); }
function wp_send_json_error() { throw new ToolsAjaxResponse( false ); }
function tools_check( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
	++$GLOBALS['checks'];
}
function tools_owner( $class, array $saved ) {
	$reflection = new ReflectionClass( $class );
	$owner = $reflection->newInstanceWithoutConstructor();
	$options = $reflection->getProperty( 'options' );
	$options->setAccessible( true );
	$options->setValue( $owner, new NoobRocket\Admin\Options_Data( $saved ) );
	return $owner;
}
function tools_invoke( $owner, $method ) {
	try { $owner->{$method}(); } catch ( ToolsAjaxResponse $response ) { return $response->success; }
	throw new RuntimeException( 'AJAX owner did not terminate with a native JSON outcome.' );
}

$plugin = dirname( __DIR__ ) . '/noobrocket/';
foreach ( [
	'inc/classes/interfaces/class-render-interface.php',
	'inc/classes/class-abstract-render.php',
	'inc/classes/admin/class-options-data.php',
	'inc/Engine/Admin/Settings/Page.php',
	'inc/Engine/Optimization/GoogleFonts/Admin/Settings.php',
] as $path ) { require $plugin . $path; }
set_error_handler( function ( $severity, $message, $file, $line ) {
	throw new ErrorException( $message, 0, $severity, $file, $line );
}, E_WARNING | E_NOTICE );

$checks = 0;
$authorized = true;
$valid_nonce = true;
$saved = [ 'cache_mobile' => 0, 'do_caching_mobile_files' => 0, 'minify_google_fonts' => 0, 'unrelated' => 'retained' ];
foreach ( [
	[ NoobRocket\Engine\Admin\Settings\Page::class, 'enable_mobile_cache', [ 'cache_mobile', 'do_caching_mobile_files' ] ],
	[ NoobRocket\Engine\Optimization\GoogleFonts\Admin\Settings::class, 'enable_google_fonts', [ 'minify_google_fonts' ] ],
] as $case ) {
	list( $class, $method, $fields ) = $case;
	$persisted = $saved;
	$writes = 0;
	$writer = 'refuse';
	$owner = tools_owner( $class, $saved );
	tools_check( false === tools_invoke( $owner, $method ), $method . ': refused changed write reports error, despite the mutated in-memory snapshot' );
	tools_check( $saved === $persisted && 1 === $writes, $method . ': refusal preserves stored settings' );
	$writer = 'accept';
	tools_check( true === tools_invoke( $owner, $method ), $method . ': the same action can be retried successfully' );
	foreach ( $fields as $field ) { tools_check( 1 === $persisted[ $field ], $method . ': retry persists ' . $field ); }
	tools_check( 'retained' === $persisted['unrelated'], $method . ': unrelated saved setting is retained' );
	$writer = 'refuse';
	tools_check( true === tools_invoke( $owner, $method ), $method . ': already enabled no-op succeeds even when update_option returns false' );
	foreach ( $fields as $field ) { $persisted[ $field ] = '1'; }
	$owner = tools_owner( $class, $persisted );
	tools_check( true === tools_invoke( $owner, $method ), $method . ': native string-valued enabled flags remain successful' );
	$persisted = $saved;
	$owner = tools_owner( $class, $saved );
	$writer = 'enable_mobile_cache' === $method ? 'partial' : 'adjust';
	tools_check( false === tools_invoke( $owner, $method ), $method . ': partial/adjusted persistence reports error' );
	tools_check( 'retained' === $persisted['unrelated'], $method . ': partial/adjusted write retains unrelated data' );
	$authorized = false;
	$before = $persisted;
	$attempts = $writes;
	tools_check( false === tools_invoke( $owner, $method ), $method . ': unauthorized action returns error' );
	tools_check( $before === $persisted && $attempts === $writes, $method . ': authorization denial does not write' );
	$authorized = true;
	$valid_nonce = false;
	try { tools_invoke( $owner, $method ); throw new RuntimeException( 'Invalid nonce was accepted.' ); } catch ( ToolsNonceFailure $error ) {}
	tools_check( $before === $persisted && $attempts === $writes, $method . ': nonce denial does not write' );
	$valid_nonce = true;
}
echo 'PASS: Tools action persistence/feedback, ', $checks, " assertions; modeled storage, no site or HTTP.\n";
