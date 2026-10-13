<?php
/** Actual purge owners and native filesystem classes with owned files and modeled WordPress/failure boundaries; no site bootstrap. */
declare(strict_types=1);

$checks = 0;
$actions = [];
$no_dots = false;
$root = sys_get_temp_dir() . '/noobrocket-purge-' . bin2hex( random_bytes( 8 ) );
// Only core classes are loaded. No wp-load.php, site, database or HTTP request.
$wordpress = isset( $argv[1] ) ? realpath( $argv[1] ) : false;
if ( ! $wordpress || ! is_file( $wordpress . '/wp-admin/includes/class-wp-filesystem-direct.php' ) ) { throw new RuntimeException( 'Provide WordPress core for native filesystem classes.' ); }
mkdir( $root );
mkdir( $root . '/pages' );
define( 'ABSPATH', $wordpress . '/' );
define( 'NOOBROCKET_CACHE_PATH', $root . '/pages/' );

function purge_check( $condition, string $message ): void {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
	++$GLOBALS['checks'];
}
function apply_filters( $hook, $value, ...$args ) { return 'noobrocket_url_no_dots' === $hook ? $GLOBALS['no_dots'] : $value; }
function do_action( $hook, ...$args ) { $GLOBALS['actions'][] = [ $hook, $args ]; }
function did_action( $hook ) { return count( array_filter( $GLOBALS['actions'], static function ( $action ) use ( $hook ) { return $hook === $action[0]; } ) ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function wp_normalize_path( $path ) { return str_replace( '\\', '/', $path ); }
function noobrocket_get_constant( $name, $default = null ) { return defined( $name ) ? constant( $name ) : $default; }
function get_noobrocket_option( $name, $default = false ) { return 'secret_cache_key' === $name ? 'owned-secret' : $default; }
function get_current_blog_id() { return 1; }
function untrailingslashit( $value ) { return rtrim( $value, '/\\' ); }
function trailingslashit( $value ) { return untrailingslashit( $value ) . '/'; }
function home_url() { return 'https://example.test'; }
function admin_url() { return 'https://example.test/wp-admin/'; }
function __( $message, $domain ) { return $message; }
function current_user_can( $capability ) { return 'noobrocket_manage_options' === $capability; }

require ABSPATH . 'wp-includes/class-wp-error.php';
require ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
require ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
class PurgeFixtureFilesystem extends WP_Filesystem_Direct {
	public $refused = [];
	public function delete( $path, $recursive = false, $type = false ) {
		if ( in_array( wp_normalize_path( $path ), array_map( 'wp_normalize_path', $this->refused ), true ) ) { return false; }
		return parent::delete( $path, $recursive, $type );
	}
}
require dirname( __DIR__ ) . '/noobrocket/vendor/autoload.php';
require dirname( __DIR__ ) . '/noobrocket/inc/functions/formatting.php';
require dirname( __DIR__ ) . '/noobrocket/inc/functions/files.php';
$filesystem = new PurgeFixtureFilesystem( null );

function purge_file( string $relative ): string {
	$path = $GLOBALS['root'] . '/' . $relative;
	if ( ! is_dir( dirname( $path ) ) ) { mkdir( dirname( $path ), 0777, true ); }
	file_put_contents( $path, 'owned fixture' );
	return $path;
}
function purge_url( string $path, $filesystem = null, bool $actions = false ): bool {
	$url = 'https://example.test/' . $path;
	$filesystem = $filesystem ?? $GLOBALS['filesystem'];
	$result = noobrocket_clean_files( $url, $filesystem, $actions );
	return $result[ $url ];
}

try {
	$sentinel = purge_file( 'outside-owned/sentinel.txt' );
	$canonical = purge_file( 'pages/example.test/query-refusal/index.html' );
	$query = purge_file( 'pages/example.test/query-refusal#test=1/index.html' );
	$filesystem->refused = [ $query ];
	purge_check( ! purge_url( 'query-refusal' ) && ! is_file( $canonical ) && is_file( $query ), 'a query-variant deletion refusal prevents successful public purge feedback' );
	$filesystem->refused = [];
	purge_check( purge_url( 'query-refusal' ) && ! is_file( $query ), 'retry removes the query-only cache after canonical deletion' );
	$actions = [];
	purge_check( false === noobrocket_clean_domain( '', $filesystem ) && is_file( $sentinel ) && [] === $actions, 'early domain purge declines without helpers, hooks or deletion' );
	function noobrocket_is_importing() { return ! empty( $GLOBALS['domain_importing'] ); }
	purge_check( false === noobrocket_clean_domain( '', $filesystem ), 'partial API initialization still declines until i18n loads' );
	purge_file( 'pages/example.test/keep/index.html' );
	$ability = new NoobRocket\Engine\Cache\Abilities\ClearUrlCache();
	$url = 'https://example.test/%2e%2e/%2e%2e/outside-owned/';
	purge_check( $ability->check_permissions() && false !== filter_var( $url, FILTER_VALIDATE_URL ), 'authorized URL passes existing entry validation' );
	$reply = $ability->execute( [ 'url' => $url ] );
	purge_check( is_file( $sentinel ), 'encoded parent path preserves sibling content' );
	purge_check( ! $reply['success'] && isset( $reply['error'][ $url ] ), 'ability reports rejected path' );
	purge_check( ! purge_url( '../../outside-owned/' ) && is_file( $sentinel ), 'literal parent path preserves sibling content' );
	purge_check( ! purge_url( '%00invalid' ), 'invalid decoded filesystem path returns false without throwing' );

	$refused = purge_file( 'pages/example.test/refused/index.html' );
	$filesystem->refused = [ $refused ];
	purge_check( ! purge_url( 'refused/' ) && is_file( $refused ), 'refused recursive deletion reports false' );
	$file = purge_file( 'pages/example.test/refused.html' );
	$filesystem->refused = [ $file ];
	purge_check( ! purge_url( 'refused.html' ) && is_file( $file ), 'refused file deletion reports false' );
	$filesystem->refused = [];
	purge_check( purge_url( 'refused/' ) && ! is_file( $refused ), 'ordinary recursive deletion succeeds' );
	purge_check( purge_url( 'refused.html' ) && ! is_file( $file ), 'ordinary file deletion succeeds' );
	purge_check( ! purge_url( 'missing/' ), 'absent cache reports false' );

	$public = purge_file( 'pages/example.test/variants/index.html' );
	$user = purge_file( 'pages/example.test-user-owned-secret/variants/index-mobile-https.html_gzip' );
	$foreign = purge_file( 'pages/example.test-user-foreign-secret/variants/index.html' );
	_noobrocket_get_cache_dirs( '', '', true );
	$filesystem->refused = [ $user ];
	purge_check( ! purge_url( 'variants/' ) && ! is_file( $public ) && is_file( $user ), 'one refused variant prevents whole URL success' );
	$filesystem->refused = [];
	purge_check( purge_url( 'variants/' ) && ! is_file( $user ) && is_file( $foreign ), 'valid user variant clears while foreign bucket remains' );

	$encoded = purge_file( 'pages/example.test/%c3%a9/index.html' );
	purge_check( purge_url( '%C3%A9/' ) && ! is_file( $encoded ), 'encoded Unicode path retains cache naming' );
	$unicode = purge_file( 'pages/example.test/%c3%a9/index.html' );
	purge_check( purge_url( 'é/' ) && ! is_file( $unicode ), 'literal Unicode path retains cache naming' );
	$double_encoded = purge_file( 'pages/example.test-user-owned-secret/%252e%252e/double/index.html' );
	purge_check( purge_url( '%252e%252e/double/' ) && ! is_file( $double_encoded ), 'double-encoded path retains native writer naming across public/user variants' );

	$wildcard = purge_file( 'pages/example.test/assets/file1.css' );
	$wildcard_refused = purge_file( 'pages/example.test/assets/file2.css' );
	$unmatched = purge_file( 'pages/example.test/assets/keep.js' );
	$filesystem->refused = [ $wildcard_refused ];
	purge_check( ! purge_url( 'assets/file.*css' ) && ! is_file( $wildcard ) && is_file( $wildcard_refused ), 'wildcard deletion failure remains false' );
	$filesystem->refused = [];
	purge_check( purge_url( 'assets/file.*css' ) && ! is_file( $wildcard_refused ) && is_file( $unmatched ), 'wildcard retains unmatched files' );
	purge_check( ! purge_url( '../../outside-owned/.*txt' ) && is_file( $sentinel ), 'wildcard search stays within selected bucket' );
	$parent_match = purge_file( 'pages/example.test/file1.css-parent/assets/file2.css' );
	$parent_unmatched = purge_file( 'pages/example.test/file1.css-parent/assets/keep.js' );
	purge_check( purge_url( 'file1.css-parent/assets/file.*css' ) && ! is_file( $parent_match ) && is_file( $parent_unmatched ), 'wildcard matches only filename even when parent path matches' );
	if ( 'Windows' !== PHP_OS_FAMILY ) {
		$repeated = purge_file( 'pages/example.test/repeat.*css/repeat1.css' );
		purge_check( purge_url( 'repeat.*css/repeat.*css' ) && ! is_file( $repeated ), 'wildcard search uses actual parent path when names repeat' );
	} else {
		echo "SKIP literal-star directory control: Windows forbids this filename; Linux CI required.\n";
	}

	$no_dots = true;
	$dot_file = purge_file( 'pages/example_test/file_html' );
	_noobrocket_get_cache_dirs( '', '', true );
	purge_check( purge_url( 'file.html' ) && ! is_file( $dot_file ), 'no-dots naming filter remains supported' );
	$no_dots = false;
	_noobrocket_get_cache_dirs( '', '', true );

	$actions = [];
	purge_file( 'pages/example.test/hooks/index.html' );
	purge_check( purge_url( 'hooks/', null, true ), 'action-enabled purge succeeds' );
	$hooks = array_column( $actions, 0 );
	purge_check( in_array( 'before_noobrocket_clean_files', $hooks, true ) && in_array( 'after_noobrocket_clean_files', $hooks, true ) && in_array( 'after_noobrocket_clean_file', $hooks, true ), 'public purge actions remain' );

	// Directory links require OS permission; Linux CI executes this control.
	$link = $root . '/pages/example.test/link';
	if ( @symlink( $root . '/outside-owned', $link ) ) {
		purge_check( ! purge_url( 'link/' ) && is_file( $sentinel ), 'resolved external link target is rejected' );
		noobrocket_rrmdir( $link, [], $filesystem );
		purge_check( ! is_link( $link ) && is_file( $sentinel ), 'recursive helper unlinks top-level link without following target' );
		$broken = $root . '/pages/example.test/broken/link';
		mkdir( dirname( $broken ) );
		purge_check( symlink( $root . '/missing-owned-target', $broken ), 'owned broken link created' );
		purge_check( purge_url( 'broken/' ) && ! is_link( $broken ), 'recursive helper removes broken directory links' );
		$nested = purge_file( 'pages/example.test/nested/index.html' );
		purge_check( symlink( $root . '/outside-owned', dirname( $nested ) . '/link' ), 'owned nested link created' );
		purge_check( purge_url( 'nested/' ) && is_file( $sentinel ), 'recursive helper unlinks nested link without following target' );
	} else {
		if ( 'Windows' !== PHP_OS_FAMILY ) { throw new RuntimeException( 'Directory-link controls require symlink support on this platform.' ); }
		echo "SKIP directory-link control: OS denied symlink creation; Linux CI required.\n";
	}
	$preserved = purge_file( 'pages/example.test/preserve/keep/index.html' );
	$removed = purge_file( 'pages/example.test/preserve/remove/index.html' );
	$preserve_parent = dirname( dirname( $preserved ) );
	foreach ( new FilesystemIterator( $preserve_parent ) as $item ) {
		if ( 'keep' === $item->getFilename() ) { $preserve_pattern = preg_quote( $item->getPathname(), '#' ); }
	}
	noobrocket_rrmdir( $preserve_parent, [ $preserve_pattern ], $filesystem );
	purge_check( is_file( $preserved ) && ! is_file( $removed ), 'recursive helper retains preserved directories' );
	function get_noobrocket_i18n_uri() { return [ 'https://example.test/domain-ready/' ]; }
	function get_noobrocket_i18n_to_preserve( $lang, $path ) { return []; }
	$domain_file = purge_file( 'pages/example.test/domain-ready/index.html' );
	_noobrocket_get_cache_dirs( '', '', true );
	$GLOBALS['domain_importing'] = true;
	noobrocket_clean_domain( '', $filesystem );
	purge_check( is_file( $domain_file ) && ! did_action( 'noobrocket_after_clean_domain' ), 'importing preserves cache and completion state' );
	$GLOBALS['domain_importing'] = false;
	purge_check( ! did_action( 'noobrocket_loaded' ) && true === noobrocket_clean_domain( '', $filesystem ) && ! is_file( $domain_file ), 'initialized dependencies permit purge before final loaded hook' );
	$domain_file = purge_file( 'pages/example.test/domain-ready/index.html' );
	noobrocket_clean_domain( '', $filesystem );
	purge_check( is_file( $domain_file ) && 1 === did_action( 'noobrocket_after_clean_domain' ), 'existing repeated-purge guard remains intact' );
	echo 'PASS cache purge boundaries: ', $checks, " checks\n";
} finally {
	$resolved = realpath( $root );
	if ( ! $resolved || dirname( $resolved ) !== realpath( sys_get_temp_dir() ) || 0 !== strpos( basename( $resolved ), 'noobrocket-purge-' ) ) { throw new RuntimeException( 'Owned cleanup containment failed' ); }
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $iterator as $entry ) {
		$entry->isDir() && ! $entry->isLink() ? rmdir( $entry->getPathname() ) : unlink( $entry->getPathname() );
	}
	rmdir( $root );
}
