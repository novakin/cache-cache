<?php
/** Standalone link-preparation proof. Reads core files; no WordPress bootstrap, database or network. */
declare(strict_types=1);

$wordpress = realpath( $argv[1] ?? dirname( __DIR__ ) . '/.artifacts/wordpress' );
if ( ! $wordpress || ! is_file( $wordpress . '/wp-includes/plugin.php' ) || ! is_file( $wordpress . '/.noobrocket-acceptance-site' ) ) {
	fwrite( STDERR, "Provide a disposable WordPress installation with the .noobrocket-acceptance-site marker.\n" );
	exit( 2 );
}
define( 'ABSPATH', $wordpress . '/' );
define( 'NOOBROCKET_PATH', dirname( __DIR__ ) . '/noobrocket/' );
define( 'NOOBROCKET_FILE', NOOBROCKET_PATH . 'noobrocket.php' );
define( 'NOOBROCKET_VERSION', 'fixture-version' );
define( 'NOOBROCKET_SLUG', 'noobrocket_settings' );
define( 'WP_PLUGIN_DIR', dirname( NOOBROCKET_PATH ) );
define( 'WPMU_PLUGIN_DIR', dirname( NOOBROCKET_PATH ) . '/mu-plugins' );
define( 'SCRIPT_DEBUG', true );
require ABSPATH . 'wp-includes/class-wp-error.php';

$state = array_fill_keys( [ 'logged_in', 'admin', 'feed', 'embed', 'preview', 'search', '404', 'password', 'bypass' ], false );
$state['singular'] = true;
$scripts = [];
$stored_options = [];
$abilities = [];
$checks = 0;
$wp_settings_errors = [];
$wp_plugin_paths = [];
$wp_rewrite = (object) [ 'feed_base' => 'feed', 'search_base' => 'recherche' ];

function is_user_logged_in() { return $GLOBALS['state']['logged_in']; }
function is_admin() { return $GLOBALS['state']['admin']; }
function is_feed() { return $GLOBALS['state']['feed']; }
function is_embed() { return $GLOBALS['state']['embed']; }
function is_preview() { return $GLOBALS['state']['preview']; }
function is_search() { return $GLOBALS['state']['search']; }
function is_404() { return $GLOBALS['state']['404']; }
function is_singular() { return $GLOBALS['state']['singular']; }
function post_password_required() { return $GLOBALS['state']['password']; }
function noobrocket_bypass() { return $GLOBALS['state']['bypass']; }
function is_multisite() { return false; }
function home_url( $path = '' ) { return 'https://example.test' . ( $GLOBALS['state']['home_path'] ?? '' ) . $path; }
function get_home_url() { return home_url(); }
function site_url( $path = '' ) { return 'https://example.test' . ( $GLOBALS['state']['site_path'] ?? '' ) . $path; }
function wp_upload_dir( $time = null, $create = true ) { return [ 'baseurl' => 'https://example.test/wordpress/wp-content/uploads' ]; }
function content_url() { return 'https://example.test/wordpress/wp-content'; }
function plugins_url() { return content_url() . '/plugins'; }
function get_stylesheet_directory_uri() { return content_url() . '/themes/child'; }
function get_template_directory_uri() { return content_url() . '/themes/parent'; }
function noobrocket_get_home_url( $path = '' ) { return home_url( $path ); }
function wp_login_url() { return home_url( '/wp-login.php' ); }
function get_permalink() { return home_url( '/article/' ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function wp_normalize_path( $path ) { return str_replace( '\\', '/', $path ); }
function is_utf8_charset() { return true; }
function __( $text, $domain = null ) { return $text; }
function _x( $text, $context, $domain = null ) { return $text; }
function add_settings_error( $setting, $code, $message, $type = 'error' ) {
	$GLOBALS['wp_settings_errors'][] = compact( 'setting', 'code', 'message', 'type' );
}
function current_user_can( $capability ) { return false; }
function get_option( $name, $default = false ) {
	return apply_filters( 'option_' . $name, $GLOBALS['stored_options'][ $name ] ?? $default );
}
function update_option() { throw new RuntimeException( 'Fixture must not save settings.' ); }
function get_transient() { return false; }
function wp_register_ability( $name, $args ) { $GLOBALS['abilities'][ $name ] = $args; }
function wp_script_is( $handle ) { return isset( $GLOBALS['scripts'][ $handle ] ); }
function wp_register_script( $handle, $src, $dependencies, $version, $footer ) {
	$GLOBALS['scripts'][ $handle ] = [ 'src' => $src, 'dependencies' => $dependencies, 'version' => $version, 'footer' => $footer, 'enqueued' => 0, 'inline' => [] ];
}
function wp_enqueue_script( $handle ) { ++$GLOBALS['scripts'][ $handle ]['enqueued']; }
function wp_add_inline_script( $handle, $source, $position = 'after' ) {
	$GLOBALS['scripts'][ $handle ]['inline'][] = [ 'source' => $source, 'position' => $position ];
}
function wp_json_encode( $value, $flags = 0, $depth = 512 ) { return json_encode( $value, $flags, $depth ); }
function wp_localize_script() { throw new RuntimeException( 'Link configuration must preserve boolean JSON types instead of localizing them as text.' ); }

require ABSPATH . 'wp-includes/plugin.php';
require ABSPATH . 'wp-includes/formatting.php';
if ( is_file( ABSPATH . 'wp-includes/compat-utf8.php' ) ) {
	require ABSPATH . 'wp-includes/compat-utf8.php';
	require ABSPATH . 'wp-includes/utf8.php';
}
require NOOBROCKET_PATH . 'vendor/autoload.php';
require NOOBROCKET_PATH . 'inc/constants.php';
require NOOBROCKET_PATH . 'inc/functions/formatting.php';
require NOOBROCKET_PATH . 'inc/functions/options.php';
require NOOBROCKET_PATH . 'inc/free.php';

set_error_handler( static function ( $severity, $message, $file, $line ) {
	if ( error_reporting() & $severity ) {
		throw new ErrorException( $message, 0, $severity, $file, $line );
	}
	return true;
} );

function link_prerender_check( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( 'FAIL: ' . $message );
	}
	++$GLOBALS['checks'];
}
function link_subscriber( array $options = [] ): NoobRocket\Engine\Preload\Links\Subscriber {
	$filesystem = new class {
		public function get_contents( $path ) { return file_get_contents( $path ); }
	};
	return new NoobRocket\Engine\Preload\Links\Subscriber( new NoobRocket\Admin\Options_Data( $options ), $filesystem );
}
function link_script_configuration(): array {
	$before = array_values( array_filter( $GLOBALS['scripts']['noobrocket-preload-links']['inline'], static function ( $script ) {
		return 'before' === $script['position'];
	} ) );
	if ( 1 !== count( $before ) || ! preg_match( '/^var NoobRocketPreloadLinksConfig = (.*);$/s', $before[0]['source'], $match ) ) {
		throw new RuntimeException( 'Expected one configuration assignment before the link handler.' );
	}
	return json_decode( $match[1], true, 512, JSON_THROW_ON_ERROR );
}

// Constants cannot be unset; each sensitive request context gets its own process.
if ( isset( $argv[2] ) ) {
	$guard = $argv[2];
	if ( ! in_array( $guard, [ 'DONOTCACHEPAGE', 'REST_REQUEST', 'DOING_AJAX' ], true ) ) {
		throw new RuntimeException( 'Invalid constant fixture.' );
	}
	define( $guard, true );
	$subscriber = link_subscriber( [ 'preload_links' => 1, 'prerender_links' => 1 ] );
	$subscriber->add_preload_script();
	link_prerender_check( [] === $scripts, $guard . ' emits no preparation script' );
	$core = [ 'mode' => 'prerender', 'eagerness' => 'conservative' ];
	link_prerender_check( $core === $subscriber->coordinate_core_speculation( $core ), $guard . ' preserves core ownership' );
	echo 'PASS: ', $guard, " (2 assertions; isolated process)\n";
	exit;
}

$defaults = noobrocket_free_settings( [] );
link_prerender_check( 0 === $defaults['prerender_links'] && [] === $defaults['prerender_excluded_uri'], 'old or imported settings default to prerender off' );
foreach ( [ true, 1, '1' ] as $enabled ) {
	link_prerender_check( 1 === noobrocket_sanitize_prerender_options( [ 'prerender_links' => $enabled ] )['prerender_links'], 'explicit boolean enables prerender' );
}
foreach ( [ false, 0, '0', '', 'on', 'true', 2, 1.0, [], [ 1 ], null ] as $disabled ) {
	link_prerender_check( 0 === noobrocket_sanitize_prerender_options( [ 'prerender_links' => $disabled ] )['prerender_links'], 'malformed or false opt-in stays off' );
}
$patterns = " /members/(.*)\r\n/private/\r/members/(.*)\n <b>/action/</b> \n";
$expected = [ '/members/(.*)', '/private/', '/action/' ];
link_prerender_check( $expected === noobrocket_sanitize_prerender_options( [ 'prerender_excluded_uri' => $patterns ] )['prerender_excluded_uri'], 'textarea accepts newline formats, strips markup and deduplicates patterns' );
link_prerender_check( [ '/members/(.*)', '/private/', '0' ] === noobrocket_sanitize_prerender_options( [ 'prerender_excluded_uri' => [ "/members/(.*)\n/private/", [ '/nested/' ], true, 0, null, '0', '/private/' ] ] )['prerender_excluded_uri'], 'mixed imported array ignores invalid entries and preserves string zero' );
foreach ( [ true, 7, null ] as $invalid ) {
	link_prerender_check( [] === noobrocket_sanitize_prerender_options( [ 'prerender_excluded_uri' => $invalid ] )['prerender_excluded_uri'], 'invalid exclusion list becomes empty' );
}
foreach ( [ 'option_noobrocket_settings', 'pre_update_option_noobrocket_settings', 'pre_add_option_noobrocket_settings', 'noobrocket_first_install_options' ] as $hook ) {
	$normalized = apply_filters( $hook, [ 'prerender_links' => 'on', 'prerender_excluded_uri' => $patterns, 'unrelated' => 7 ] );
	link_prerender_check( 0 === $normalized['prerender_links'] && $expected === $normalized['prerender_excluded_uri'] && 7 === $normalized['unrelated'], $hook . ' validates opt-in and preserves unrelated settings' );
}
$admin = new NoobRocket\Engine\Preload\Links\AdminSubscriber( new NoobRocket\Admin\Options_Data( [] ) );
$installed = $admin->add_option( [ 'unrelated' => 7 ] );
link_prerender_check( 1 === $installed['preload_links'] && 0 === $installed['prerender_links'] && [] === $installed['prerender_excluded_uri'] && 7 === $installed['unrelated'], 'first installation keeps legacy prefetch and new prerender off' );

$settings = new NoobRocket\Engine\Admin\Settings\Settings( new NoobRocket\Admin\Options_Data( [] ) );
$submitted = $settings->sanitize_callback( [ 'prerender_links' => '1', 'prerender_excluded_uri' => $patterns, 'ignore' => 1 ] );
link_prerender_check( 1 === $submitted['prerender_links'] && $expected === $submitted['prerender_excluded_uri'], 'native settings sanitizer uses shared normalization' );
$submitted = $settings->sanitize_callback( [ 'prerender_links' => [ 1 ], 'prerender_excluded_uri' => 7, 'ignore' => 1 ] );
link_prerender_check( 0 === $submitted['prerender_links'] && [] === $submitted['prerender_excluded_uri'], 'native form rejects malformed values' );

$allowed = new NoobRocket\Engine\Abilities\Options\AllowedOptions();
link_prerender_check( in_array( 'prerender_links', $allowed->get(), true ) && in_array( 'prerender_excluded_uri', $allowed->get(), true ), 'MCP and history allowlist includes both prerender options' );
$ability = new NoobRocket\Engine\Abilities\Options\SetOption( $allowed );
$sanitize = new ReflectionMethod( $ability, 'sanitize_value' );
foreach ( [ true, 1, '1', 'true', false, 0, '0', 'false' ] as $value ) {
	$expected_boolean = in_array( $value, [ true, 1, '1', 'true' ], true ) ? 1 : 0;
	link_prerender_check( $expected_boolean === $sanitize->invoke( $ability, 'prerender_links', $value ), 'MCP boolean uses shared explicit representations' );
}
foreach ( [ 'on', [ 1 ], 1.0 ] as $value ) {
	link_prerender_check( $sanitize->invoke( $ability, 'prerender_links', $value ) instanceof WP_Error, 'MCP invalid boolean has an explicit validation error' );
}
link_prerender_check( [ '/old/', '/private/', '/members/(.*)', '/action/' ] === $sanitize->invoke( $ability, 'prerender_excluded_uri', $patterns, 'update', [ '/old/', '/private/' ] ), 'MCP update merges exclusion patterns without duplicates' );
link_prerender_check( $expected === $sanitize->invoke( $ability, 'prerender_excluded_uri', $patterns, 'replace', [ '/old/' ] ), 'MCP replace uses only normalized new patterns' );
link_prerender_check( [] === $sanitize->invoke( $ability, 'prerender_excluded_uri', '', 'replace', [ '/old/' ] ), 'MCP replace can clear exclusions' );
link_prerender_check( ! $ability->check_permissions(), 'MCP mutation retains native capability check' );
( new NoobRocket\Engine\Abilities\Options\GetOptions( new NoobRocket\Admin\Options_Data( [] ), $allowed ) )->register();
$schema = $abilities['noobrocket/get-options']['output_schema']['properties'];
link_prerender_check( [ 0, 1 ] === $schema['prerender_links']['enum'] && 'array' === $schema['prerender_excluded_uri']['type'] && 'string' === $schema['prerender_excluded_uri']['items']['type'], 'readable ability schema describes boolean and pattern list' );
$history = new NoobRocket\Engine\Optimization\Assets\Snapshots( new NoobRocket\Engine\Optimization\Assets\Rules() );
$restorable = $history->restorable( [ 'prerender_links' => 1, 'prerender_excluded_uri' => $patterns, 'cloudflare_api_key' => 'fixture-private-key' ] );
link_prerender_check( 1 === $restorable['prerender_links'] && $expected === $restorable['prerender_excluded_uri'] && ! isset( $restorable['cloudflare_api_key'] ), 'history restores normalized prerender settings and omits credentials' );

$stored_options['noobrocket_settings'] = [ 'cache_reject_uri' => [ '/cache-private/(.*)' ] ];
$subscriber = link_subscriber( [ 'preload_links' => 0, 'prerender_links' => 1, 'prerender_excluded_uri' => [ '/private/(.*)' ], 'preload_excluded_uri' => [ '/preload-private/(.*)' ] ] );
$subscriber->add_preload_script();
link_prerender_check( isset( $scripts['noobrocket-preload-links'], $scripts['noobrocket-browser-checker'] ), 'prerender works independently while legacy prefetch is off' );
$config = link_script_configuration();
link_prerender_check( false === $config['prefetch'] && true === $config['prerender'] && home_url() === $config['siteUrl'], 'frontend configuration has distinct modes and correct origin' );
link_prerender_check( true === $config['usesTrailingSlash'] && 100 === $config['onHoverDelay'], 'existing permalink and intent delay settings are retained' );
link_prerender_check( is_bool( $config['prefetch'] ) && is_bool( $config['prerender'] ) && is_bool( $config['usesTrailingSlash'] ) && is_int( $config['onHoverDelay'] ) && is_int( $config['rateThrottle'] ), 'inline JSON preserves boolean and numeric types used by the frontend' );
$inline = $scripts['noobrocket-preload-links']['inline'];
link_prerender_check( 2 === count( $inline ) && 'after' === $inline[0]['position'] && 'before' === $inline[1]['position'] && false !== strpos( $inline[0]['source'], 'NoobRocketPreloadLinks.run()' ), 'WordPress before slot delivers configuration ahead of the existing after-slot handler' );
$excluded = '~' . $config['excludeUris'] . '|' . $config['prerenderExcludeUris'] . '~i';
foreach ( [ '/cache-private/child/', '/article/feed/', '/article/embed/', '/recherche/term/', '/private/child/', '/preload-private/child/', '/checkout/', '/my-account/', '/wp-admin/', '/wp-json/posts/', '/wp-login.php', '/refer/deal/' ] as $path ) {
	link_prerender_check( 1 === preg_match( $excluded, home_url( $path ) ), 'frontend excludes ' . $path );
}
link_prerender_check( 0 === preg_match( $excluded, home_url( '/public/article/' ) ), 'ordinary public article remains eligible' );
$subscriber->add_preload_script();
link_prerender_check( 1 === $scripts['noobrocket-preload-links']['enqueued'] && 2 === count( $scripts['noobrocket-preload-links']['inline'] ), 'handler and configuration are each enqueued only once per request' );
link_prerender_check( [ 'noobrocket-browser-checker' ] === $scripts['noobrocket-preload-links']['dependencies'] && $scripts['noobrocket-preload-links']['footer'], 'existing dependency and footer delivery are preserved' );

$core = [ 'mode' => 'prerender', 'eagerness' => 'conservative' ];
link_prerender_check( null === $subscriber->coordinate_core_speculation( $core ), 'eligible opt-in removes duplicate broader core prerender rules' );
link_prerender_check( $core === link_subscriber()->coordinate_core_speculation( $core ), 'option off preserves existing core rules' );
link_prerender_check( null === link_subscriber()->coordinate_core_speculation( null ), 'disabled core rules remain disabled' );
link_prerender_check( ! function_exists( 'wp_get_speculation_rules' ) && ! function_exists( 'wp_get_speculation_rules_configuration' ), 'feature works without modern core speculation functions' );
foreach ( [ 'logged_in', 'admin', 'feed', 'embed', 'preview', 'search', '404', 'password', 'bypass' ] as $guard ) {
	$state[ $guard ] = true;
	$scripts = [];
	$guarded = link_subscriber( [ 'preload_links' => 1, 'prerender_links' => 1 ] );
	$guarded->add_preload_script();
	link_prerender_check( [] === $scripts, $guard . ' blocks script output' );
	link_prerender_check( $core === $guarded->coordinate_core_speculation( $core ), $guard . ' preserves core ownership' );
	$state[ $guard ] = false;
}
$scripts = [];
link_subscriber()->add_preload_script();
link_prerender_check( [] === $scripts, 'both options off emit no scripts' );
link_subscriber( [ 'preload_links' => 1, 'prerender_links' => 'on' ] )->add_preload_script();
$config = link_script_configuration();
link_prerender_check( true === $config['prefetch'] && false === $config['prerender'], 'legacy prefetch remains available and malformed opt-in cannot prerender' );

foreach ( [ 'DONOTCACHEPAGE', 'REST_REQUEST', 'DOING_AJAX' ] as $constant ) {
	$output = [];
	$code = 0;
	$command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' ' . escapeshellarg( $wordpress ) . ' ' . escapeshellarg( $constant );
	exec( $command, $output, $code );
	link_prerender_check( 0 === $code, $constant . ' isolated request checks pass: ' . implode( ' ', $output ) );
}

// Load only the native rules classes/functions now, after proving the pre-6.8 path.
if ( is_file( ABSPATH . 'wp-includes/speculative-loading.php' ) ) {
	require ABSPATH . 'wp-includes/class-wp-speculation-rules.php';
	require ABSPATH . 'wp-includes/class-wp-url-pattern-prefixer.php';
	require ABSPATH . 'wp-includes/speculative-loading.php';
	$stored_options['permalink_structure'] = '/%postname%/';
	$state['home_path'] = '/blog';
	$state['site_path'] = '/wordpress';
	$core_subscriber = link_subscriber( [ 'prerender_links' => 1, 'preload_links' => 0 ] );
	$core_callback = [ $core_subscriber, 'coordinate_core_speculation' ];
	add_filter( 'wp_speculation_rules_configuration', $core_callback );
	$native_mode = null;
	$native_paths = static function ( $paths, $mode ) use ( &$native_mode ) {
		$native_mode = $mode;
		return array_merge( $paths, [ '/private/*', '/blog/member/*', '/private/*', 7 ] );
	};
	add_filter( 'wp_speculation_rules_href_exclude_paths', $native_paths, 10, 2 );
	$scripts = [];
	$core_subscriber->add_preload_script();
	$config = link_script_configuration();
	link_prerender_check( true === $config['prerender'] && false === $config['prefetch'], 'native exclusions retain independent plugin prerender' );
	link_prerender_check( 'prerender' === $native_mode, 'core exclusions are read in prerender mode rather than core prefetch default' );
	foreach ( [ '/blog/private/*', '/blog/member/*', '/wordpress/wp-*.php', '/wordpress/wp-admin/*', '/wordpress/wp-content/uploads/*', '/wordpress/wp-content/*', '/wordpress/wp-content/plugins/*', '/wordpress/wp-content/themes/child/*', '/wordpress/wp-content/themes/parent/*', '/blog/*\\?(.+)' ] as $path ) {
		link_prerender_check( in_array( $path, $config['nativeExcludePaths'], true ), 'native path prefix preserved: ' . $path );
	}
	link_prerender_check( count( $config['nativeExcludePaths'] ) === count( array_unique( $config['nativeExcludePaths'] ) ) && ! in_array( 7, $config['nativeExcludePaths'], true ), 'native core sanitation retains unique string paths' );
	foreach ( [ 'a[rel~="nofollow"]', '.no-prerender, .no-prerender a', '.no-prefetch, .no-prefetch a' ] as $selector ) {
		link_prerender_check( in_array( $selector, $config['nativeExcludeSelectors'], true ), 'native opt-out selector preserved: ' . $selector );
	}
	link_prerender_check( null === wp_get_speculation_rules_configuration(), 'native duplicate rules stay disabled after exclusions have been read' );
	$reading = new ReflectionProperty( $core_subscriber, 'reading_core_rules' );
	link_prerender_check( false === $reading->getValue( $core_subscriber ), 'native-reading flag resets after successful extraction' );
	remove_filter( 'wp_speculation_rules_configuration', $core_callback );
	remove_filter( 'wp_speculation_rules_href_exclude_paths', $native_paths );

	$native_disabled = static function ( $configuration ) { return null; };
	add_filter( 'wp_speculation_rules_configuration', $native_disabled, 20 );
	foreach ( [ 0, 1 ] as $prefetch ) {
		$scripts = [];
		$disabled = link_subscriber( [ 'prerender_links' => 1, 'preload_links' => $prefetch ] );
		$disabled_callback = [ $disabled, 'coordinate_core_speculation' ];
		add_filter( 'wp_speculation_rules_configuration', $disabled_callback );
		$disabled->add_preload_script();
		$config = link_script_configuration();
		link_prerender_check( false === $config['prerender'] && (bool) $prefetch === $config['prefetch'], 'native null configuration disables plugin prerender without changing legacy prefetch choice' );
		link_prerender_check( [] === $config['nativeExcludePaths'] && [] === $config['nativeExcludeSelectors'], 'disabled native configuration emits no native rules' );
		remove_filter( 'wp_speculation_rules_configuration', $disabled_callback );
	}
	remove_filter( 'wp_speculation_rules_configuration', $native_disabled, 20 );

	// A third-party core hook failure must not leave coordination stuck in read mode.
	$failure = static function ( $rules ) { throw new RuntimeException( 'fixture-native-rule-failure' ); };
	add_action( 'wp_load_speculation_rules', $failure );
	add_filter( 'wp_speculation_rules_configuration', $core_callback );
	$native_method = new ReflectionMethod( $core_subscriber, 'get_native_exclusions' );
	$failure_message = null;
	try {
		$native_method->invoke( $core_subscriber );
	} catch ( RuntimeException $error ) {
		$failure_message = $error->getMessage();
	}
	link_prerender_check( 'fixture-native-rule-failure' === $failure_message, 'native callback failure propagates its original exception' );
	link_prerender_check( false === $reading->getValue( $core_subscriber ) && null === $core_subscriber->coordinate_core_speculation( $core ), 'finally restores native-reading flag and duplicate suppression after failure' );
	remove_action( 'wp_load_speculation_rules', $failure );
	remove_filter( 'wp_speculation_rules_configuration', $core_callback );
	link_prerender_check( null !== wp_get_speculation_rules_configuration(), 'native ownership resumes after plugin callback removal' );
}
echo 'PASS: link prerender PHP contracts (', $checks, " assertions plus 6 isolated context assertions; no site writes)\n";
