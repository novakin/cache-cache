<?php
/** Native rich-content phases for the existing owned Linux server rehearsal. */
$rich_root = isset( $argv[1] ) ? realpath( $argv[1] ) : false;
$rich_phase = $argv[2] ?? '';
if ( 'profile' === $rich_phase ) {
	$rich_phase = 'profile-' . ( $argv[3] ?? '' );
}
if ( PHP_SAPI !== 'cli' || ! $rich_root || getenv( 'NOOBROCKET_LINUX_PROOF' ) !== $rich_root || ! is_file( $rich_root . '/.noobrocket-acceptance-site' ) || ! is_file( $rich_root . '/wp-load.php' ) ) {
	fwrite( STDERR, "Rich acceptance requires the marked owned Linux site and matching NOOBROCKET_LINUX_PROOF.\n" );
	exit( 2 );
}
if ( ! in_array( $rich_phase, [ 'seed', 'profile-baseline', 'profile-optimized', 'profile-eager', 'profile-delay', 'purge', 'check', 'status', 'cleanup' ], true ) ) {
	fwrite( STDERR, "Usage: php tests/wordpress-rich-site.php SITE seed|profile-baseline|profile-optimized|profile-eager|profile-delay|purge|check|status|cleanup\n" );
	exit( 2 );
}
$rich_source = __DIR__ . '/fixtures/rich-site';
$rich_plugin = $rich_root . '/wp-content/plugins/noobrocket';
if ( is_link( $rich_plugin ) || realpath( $rich_plugin ) !== $rich_plugin || realpath( $rich_plugin ) === realpath( dirname( __DIR__ ) . '/noobrocket' ) ) {
	throw new RuntimeException( 'Rich fixture rejects a linked or source plugin installation.' );
}
foreach ( [ 'noobrocket.php', 'inc/Engine/Admin/Settings/Settings.php', 'inc/functions/files.php' ] as $rich_owner ) {
	$installed = $rich_plugin . '/' . $rich_owner;
	$maintained = dirname( __DIR__ ) . '/noobrocket/' . $rich_owner;
	if ( is_link( $installed ) || ! is_file( $installed ) || hash_file( 'sha256', $installed ) !== hash_file( 'sha256', $maintained ) ) {
		throw new RuntimeException( 'Rich fixture requires copied candidate plugin owner bytes.' );
	}
}
$_SERVER['HTTP_HOST'] = '127.0.0.1:8875';
$_SERVER['SERVER_PORT'] = '8875';
$_SERVER['SERVER_NAME'] = '127.0.0.1';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'POST';
define( 'WP_ADMIN', true );
define( 'DONOTCACHEPAGE', true );
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
require $rich_root . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
wp_set_current_user( 1 );
if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'noobrocket_manage_options' ) || home_url( '/' ) !== 'http://127.0.0.1:8875/' ) {
	throw new RuntimeException( 'Rich fixture native administrator/site selection differs.' );
}
$rich_directory = WP_CONTENT_DIR . '/cache-cache-rich-site';
$rich_contract_path = $rich_directory . '/contract.json';
$rich_private_path = dirname( $rich_root ) . '/rich-site-state.private.json';
$rich_auth_path = dirname( $rich_root ) . '/rich-site-auth.private.json';
$rich_theme_path = WP_CONTENT_DIR . '/themes/cache-cache-rich-site';
$rich_checks = 0;
function rich_check( $condition, $message ) {
	global $rich_checks;
	if ( ! $condition ) { throw new RuntimeException( 'FAIL rich fixture: ' . $message ); }
	++$rich_checks;
}
function rich_json_write( $path, array $data, $private = false ) {
	rich_check( ! is_link( $path ) && realpath( dirname( $path ) ) === dirname( $path ), 'JSON receipt path is owned and plain' );
	rich_check( false !== file_put_contents( $path, wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n", LOCK_EX ), 'JSON receipt published' );
	if ( $private ) { rich_check( chmod( $path, 0600 ), 'private receipt permissions' ); }
}
function rich_copy_tree( $source, $destination ) {
	rich_check( is_dir( $source ) && ! is_link( $source ) && ! file_exists( $destination ) && ! is_link( $destination ), 'fresh fixture theme destination' );
	rich_check( mkdir( $destination, 0755 ), 'fixture theme directory created' );
	foreach ( new DirectoryIterator( $source ) as $entry ) {
		if ( $entry->isDot() || 'boundary.php' === $entry->getFilename() ) { continue; }
		rich_check( ! $entry->isLink(), 'fixture source has no links' );
		$target = $destination . '/' . $entry->getFilename();
		if ( $entry->isDir() ) { rich_copy_tree( $entry->getPathname(), $target ); }
		else { rich_check( copy( $entry->getPathname(), $target ), 'fixture asset copied' ); }
	}
}
function rich_resources( $root, $theme, array $media ) {
	$paths = [];
	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $theme, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
		rich_check( ! $file->isLink(), 'theme resource is plain' );
		if ( $file->isFile() ) { $paths[] = $file->getPathname(); }
	}
	foreach ( $media as $id ) {
		$file = get_attached_file( $id );
		rich_check( is_string( $file ) && is_file( $file ) && ! is_link( $file ) && strpos( realpath( $file ), $root . '/wp-content/uploads/' ) === 0, 'media file belongs to copied site' );
		$paths[] = $file;
		$metadata = wp_get_attachment_metadata( $id );
		foreach ( $metadata['sizes'] ?? [] as $size ) { $paths[] = dirname( $file ) . '/' . $size['file']; }
	}
	sort( $paths );
	return array_map( static function ( $file ) use ( $root ) {
		return [ 'path' => '/' . substr( $file, strlen( $root ) + 1 ), 'sha256' => hash_file( 'sha256', $file ), 'bytes' => filesize( $file ), 'kind' => pathinfo( $file, PATHINFO_EXTENSION ) ];
	}, array_values( array_unique( $paths ) ) );
}
function rich_content_digest( array $ids, array $resources ) {
	$content = array_map( static function ( $id ) { return get_post_field( 'post_content', $id, 'raw' ); }, $ids );
	return hash( 'sha256', wp_json_encode( [ $content, $resources ] ) );
}
function rich_save_settings( array $input, array $expected ) {
	$before = get_option( 'noobrocket_settings' );
	rich_check( is_array( $before ), 'native settings initialized' );
	global $wp_settings_errors;
	$wp_settings_errors = [];
	$domain = new NoobRocket\Engine\Admin\Settings\Settings( new NoobRocket\Admin\Options_Data( $before ) );
	$validated = $domain->sanitize_callback( $input );
	rich_check( ! array_filter( get_settings_errors(), static function ( $notice ) { return 'error' === ( $notice['type'] ?? '' ); } ), 'native domain reports no validation errors' );
	$options = new NoobRocket\Admin\Options( 'noobrocket_' );
	$options->set( 'settings', $validated );
	$saved = get_option( 'noobrocket_settings' );
	foreach ( $expected as $key => $value ) { rich_check( array_key_exists( $key, $saved ) && $saved[ $key ] === $value, 'native requested field persisted: ' . $key ); }
	foreach ( [ 'version', 'engine_version', 'secret_cache_key' ] as $key ) {
		rich_check( ( $before[ $key ] ?? null ) === ( $saved[ $key ] ?? null ), 'native save preserves identity: ' . $key );
	}
	return $saved;
}
function rich_profile( $name, array &$contract ) {
	$common = [
		'cache_mobile' => 0, 'do_caching_mobile_files' => 0, 'cache_logged_user' => 0, 'cache_stale_refresh' => 0,
		'manual_preload' => 0, 'cdn' => 0, 'do_cloudflare' => 0, 'sucury_waf_cache_sync' => 0,
		'local_css_mode' => 'off', 'optimize_css_delivery' => 0, 'remove_unused_css' => 0, 'async_css' => 0,
		'minify_concatenate_js' => 0, 'delay_js' => 0, 'minify_google_fonts' => 0, 'host_fonts_locally' => 0,
		'auto_preload_fonts' => 0, 'image_delivery' => 0, 'preload_links' => 0, 'prerender_links' => 0,
		'schedule_automatic_cleanup' => 0, 'performance_monitoring' => 0, 'emoji' => 0,
		'exclude_css' => [], 'exclude_js' => [], 'exclude_defer_js' => [], 'exclude_lazyload' => [],
		'cache_reject_cookies' => [ 'woocommerce_items_in_cart', 'woocommerce_cart_hash', 'wp_woocommerce_session_' ],
		'prerender_links_mode' => 'document', 'prerender_links_eagerness' => 'eager',
		'prerender_excluded_uri' => [ '/rich-form/', '/rich-password/', '/rich-private/', '/rich-csp/', '/rich-no-store/', '/rich-set-cookie/', '/wp-admin/', '/rich-missing-page/' ],
	];
	$optimized = 'baseline' !== $name;
	$profile = array_merge( $common, [
		'minify_css' => $optimized ? 1 : 0, 'minify_js' => $optimized ? 1 : 0, 'defer_all_js' => $optimized && 'delay' !== $name ? 1 : 0,
		'delay_js' => 'delay' === $name ? 1 : 0,
		'lazyload' => $optimized ? 1 : 0, 'lazyload_iframes' => 0, 'lazyload_youtube' => 0,
		'lazyload_css_bg_img' => $optimized ? 1 : 0, 'image_dimensions' => $optimized ? 1 : 0,
		'prerender_links' => 'eager' === $name ? 1 : 0,
	] );
	$saved = rich_save_settings( array_merge( get_option( 'noobrocket_settings' ), $profile ), $profile );
	$contract['profile'] = [ 'name' => $name, 'settings' => $profile, 'settings_sha256' => hash( 'sha256', serialize( $saved ) ), 'settings_owner' => 'Settings::sanitize_callback + Admin\\Options::set', 'validation_errors' => 0 ];
}

if ( 'seed' === $rich_phase ) {
	rich_check( ! file_exists( $rich_directory ) && ! is_link( $rich_directory ) && ! file_exists( $rich_private_path ) && ! file_exists( $rich_auth_path ), 'seed owns a new fixture scope' );
	$original = [ 'theme' => get_stylesheet(), 'settings' => get_option( 'noobrocket_settings' ), 'show_on_front' => get_option( 'show_on_front' ), 'page_on_front' => get_option( 'page_on_front' ) ];
	rich_check( mkdir( $rich_directory, 0755 ), 'public synthetic contract directory created' );
	rich_copy_tree( $rich_source, $rich_theme_path );
	$mu = WP_CONTENT_DIR . '/mu-plugins/cache-cache-rich-boundary.php';
	rich_check( ! file_exists( $mu ) && ! is_link( $mu ) && copy( $rich_source . '/boundary.php', $mu ), 'disposable boundary installed' );
	rich_check( function_exists( 'imagecreatetruecolor' ) && function_exists( 'imagejpeg' ), 'GD JPEG support required' );
	$upload = wp_upload_dir( '2026/01' );
	rich_check( empty( $upload['error'] ) && ! is_link( $upload['path'] ) && strpos( realpath( $upload['path'] ), $rich_root . '/wp-content/uploads/' ) === 0, 'generated media directory owned' );
	$media = [];
	for ( $i = 0; $i < 12; ++$i ) {
		$image = imagecreatetruecolor( 1400, 900 );
		for ( $y = 0; $y < 900; $y += 20 ) {
			for ( $x = 0; $x < 1400; $x += 20 ) {
				$color = imagecolorallocate( $image, ( $x * 7 + $y * 3 + $i * 31 ) % 220, ( $x * 3 + $y * 5 + $i * 17 ) % 190, ( $x * 5 + $y * 7 + $i * 29 ) % 210 );
				imagefilledrectangle( $image, $x, $y, $x + 19, $y + 19, $color );
			}
		}
		$file = $upload['path'] . '/rich-image-' . sprintf( '%02d', $i + 1 ) . '.jpg';
		rich_check( ! file_exists( $file ) && imagejpeg( $image, $file, 88 ), 'deterministic JPEG created' );
		imagedestroy( $image );
		$id = wp_insert_attachment( [ 'post_title' => 'Rich fixture image ' . ( $i + 1 ), 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit' ], $file, 0, true );
		rich_check( ! is_wp_error( $id ) && $id > 0, 'native media attachment created' );
		update_post_meta( $id, '_rich_fixture_owned', 1 );
		update_post_meta( $id, '_wp_attachment_image_alt', 'Synthetic patterned landscape ' . ( $i + 1 ) );
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file ) );
		$media[] = $id;
	}
	update_option( 'rich_fixture_media', $media, false );
	$font_candidates = [];
	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( WP_CONTENT_DIR . '/themes', FilesystemIterator::SKIP_DOTS ) ) as $font ) {
		if ( ! $font->isLink() && $font->isFile() && 'woff2' === strtolower( $font->getExtension() ) && strpos( $font->getPathname(), $rich_theme_path . '/' ) !== 0 ) { $font_candidates[] = $font->getPathname(); }
	}
	sort( $font_candidates );
	rich_check( ! empty( $font_candidates ), 'verified WordPress theme supplies an actual local font' );
	mkdir( $rich_theme_path . '/assets/fonts', 0755 );
	rich_check( copy( $font_candidates[0], $rich_theme_path . '/assets/fonts/fixture.woff2' ), 'actual local font copied' );
	file_put_contents( $rich_theme_path . '/assets/font.css', '@font-face{font-family:RichFixture;src:url("fonts/fixture.woff2") format("woff2");font-style:normal;font-weight:100 900;font-display:swap}' . "\n" );
	file_put_contents( $rich_theme_path . '/assets/generated-background.css', '.rich-late-background{background-image:url("' . wp_get_attachment_url( $media[5] ) . '")}' . "\n" );
	$terms = [];
	for ( $i = 0; $i < 6; ++$i ) {
		$term = wp_insert_term( 0 === $i ? 'Fixture Guides' : 'Fixture Category ' . $i, 'category', [ 'slug' => 0 === $i ? 'fixture-guides' : 'fixture-category-' . $i ] );
		rich_check( ! is_wp_error( $term ), 'native category created' );
		add_term_meta( $term['term_id'], '_rich_fixture_owned', 1 );
		$terms[] = $term['term_id'];
	}
	$tags = [];
	for ( $i = 0; $i < 12; ++$i ) {
		$term = wp_insert_term( 'Fixture Topic ' . ( $i + 1 ), 'post_tag', [ 'slug' => 'fixture-topic-' . ( $i + 1 ) ] );
		rich_check( ! is_wp_error( $term ), 'native tag created' );
		add_term_meta( $term['term_id'], '_rich_fixture_owned', 1 );
		$tags[] = $term['term_id'];
	}
	$posts = []; $comments = [];
	for ( $i = 1; $i <= 60; ++$i ) {
		$body = '<p class="rich-content-marker">RICH_PUBLIC_ARTICLE_' . sprintf( '%02d', $i ) . '</p>';
		for ( $paragraph = 1; $paragraph <= 14; ++$paragraph ) {
			$body .= '<h3>Field note ' . $paragraph . '</h3><p>Article ' . $i . ' describes a synthetic project with local photographs, careful materials and a repeatable method. This paragraph provides readable long content for real layout and rendering. Every asset belongs to this disposable site. Responsive navigation and accessible controls must keep working after optimization.</p>';
		}
		$id = wp_insert_post( [ 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => sprintf( 'Rich field notes %02d', $i ), 'post_name' => sprintf( 'rich-article-%02d', $i ), 'post_content' => $body, 'post_date' => '2026-01-01 12:00:00', 'post_date_gmt' => '2026-01-01 12:00:00', 'comment_status' => 'open' ], true );
		rich_check( ! is_wp_error( $id ) && $id > 0, 'native article created' );
		update_post_meta( $id, '_rich_fixture_owned', 1 );
		set_post_thumbnail( $id, $media[ ( $i - 1 ) % 12 ] );
		wp_set_post_categories( $id, [ $terms[ ( $i - 1 ) % 6 ], $terms[0] ] );
		wp_set_post_tags( $id, [ 'Fixture Topic ' . ( ( $i - 1 ) % 12 + 1 ) ] );
		$posts[] = $id;
		for ( $j = 1; $j <= 2; ++$j ) {
			$comments[] = wp_insert_comment( [ 'comment_post_ID' => $id, 'comment_author' => 'Synthetic reader ' . $j, 'comment_author_email' => 'reader' . $j . '@example.test', 'comment_content' => 'Local fixture comment on article ' . $i . '.', 'comment_approved' => 1, 'comment_date' => '2026-01-02 12:00:00', 'comment_date_gmt' => '2026-01-02 12:00:00' ] );
		}
	}
	$password = wp_generate_password( 24, false );
	$page_definitions = [
		[ 'Home', 'rich-home', 'public', true ], [ 'Media', 'rich-media', 'public', false ],
		[ 'Interactions', 'rich-interactions', 'public', false ], [ 'Modules', 'rich-modules', 'public', false ],
		[ 'Form', 'rich-form', 'form', false ], [ 'Next', 'rich-next-page', 'public', false ],
		[ 'Password', 'rich-password', 'password', false ], [ 'Private', 'rich-private', 'private', false ],
		[ 'NoStore', 'rich-no-store', 'no-store', false ], [ 'SetCookie', 'rich-set-cookie', 'set-cookie', false ],
		[ 'CSP', 'rich-csp', 'csp', false ],
	];
	$pages = []; $page_ids = [];
	foreach ( $page_definitions as $definition ) {
		list( $name, $slug, $kind, $performance ) = $definition;
		$body = '<p class="rich-content-marker">RICH_' . strtoupper( str_replace( '-', '_', $kind ) ) . '_BODY</p><p>Rich local content for ' . $name . ', using real WordPress posts, media, taxonomies and native form endpoints.</p>';
		$id = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'private' === $kind ? 'private' : 'publish', 'post_title' => 'Rich ' . $name, 'post_name' => $slug, 'post_password' => 'password' === $kind ? $password : '', 'post_content' => $body ], true );
		rich_check( ! is_wp_error( $id ) && $id > 0, 'native fixture page created' );
		update_post_meta( $id, '_rich_fixture_owned', 1 );
		$page_ids[] = $id;
		if ( 'Home' === $name ) { update_option( 'show_on_front', 'page' ); update_option( 'page_on_front', $id ); }
		$pages[] = [ 'name' => $name, 'path' => 'Home' === $name ? '/' : '/' . $slug . '/', 'kind' => $kind, 'performance' => $performance ];
	}
	array_splice( $pages, 1, 0, [ [ 'name' => 'Article', 'path' => '/rich-article-01/', 'kind' => 'public', 'performance' => true ], [ 'name' => 'Catalog', 'path' => '/category/fixture-guides/', 'kind' => 'archive', 'performance' => true ] ] );
	$pages[] = [ 'name' => 'Search', 'path' => '/?s=rich', 'kind' => 'search', 'performance' => false ];
	$pages[] = [ 'name' => 'Missing', 'path' => '/rich-missing-page/', 'kind' => '404', 'performance' => false ];
	switch_theme( 'cache-cache-rich-site' );
	flush_rewrite_rules();
	$username = 'rich-acceptance'; $admin_password = wp_generate_password( 32, false );
	rich_check( ! username_exists( $username ), 'new private fixture administrator' );
	$user_id = wp_create_user( $username, $admin_password, 'rich-admin@example.test' );
	rich_check( ! is_wp_error( $user_id ), 'native fixture administrator created' );
	$user = new WP_User( $user_id ); $user->set_role( 'administrator' );
	$expires = time() + HOUR_IN_SECONDS;
	$token = WP_Session_Tokens::get_instance( $user_id )->create( $expires );
	rich_json_write( $rich_auth_path, [ 'username' => $username, 'password' => $admin_password, 'user_id' => $user_id, 'password_page_password' => $password, 'logged_in_cookie' => LOGGED_IN_COOKIE . '=' . wp_generate_auth_cookie( $user_id, $expires, 'logged_in', $token ) ], true );
	$resources = rich_resources( $rich_root, $rich_theme_path, $media );
	$content_digest = rich_content_digest( array_merge( $posts, $page_ids ), $resources );
	$contract = [
		'schema' => 1, 'protocol' => 'cache-cache-rich-site-v1', 'origin' => 'http://127.0.0.1:8875', 'pages' => $pages,
		'environment' => [ 'php' => PHP_VERSION, 'wordpress' => $GLOBALS['wp_version'], 'plugin' => NOOBROCKET_VERSION, 'theme' => 'Cache-Cache Rich Acceptance/1.0.0', 'content_sha256' => $content_digest ],
		'counts' => [ 'posts' => count( $posts ), 'pages' => count( $page_ids ), 'media' => count( $media ), 'comments' => count( $comments ), 'categories' => count( $terms ), 'tags' => count( $tags ) ],
		'resources' => $resources, 'status_url' => '/wp-admin/admin-ajax.php?action=rich_fixture_status',
		'admin_url' => '/wp-admin/options-general.php?page=noobrocket',
		'selectors' => [ 'hero' => '#rich-hero-image', 'below_images' => '.rich-below-image', 'background' => '#rich-late-background', 'classic' => '#rich-classic-state', 'module' => '#rich-module-state', 'jsonld' => '#rich-jsonld', 'form' => '#rich-form', 'form_name' => '#rich-name', 'form_submit' => '#rich-form-submit', 'form_result' => '#rich-form-result' ],
		'expected' => [ 'classic' => 'RICH_CLASSIC_READY', 'module' => 'RICH_MODULE_READY', 'private' => 'RICH_PRIVATE_BODY', 'password' => 'RICH_PASSWORD_BODY', 'form' => 'RICH_FORM_ACCEPTED', 'session' => 'RICH_SESSION_PRIVATE', 'body_color' => 'rgb(244, 241, 233)' ],
		'font_provenance' => [ 'source' => '/' . substr( $font_candidates[0], strlen( $rich_root ) + 1 ), 'sha256' => hash_file( 'sha256', $font_candidates[0] ), 'distribution' => 'verified official WordPress theme archive; retained upstream license' ],
	];
	rich_profile( 'baseline', $contract );
	rich_json_write( $rich_private_path, [ 'site' => $rich_root, 'posts' => $posts, 'pages' => $page_ids, 'media' => $media, 'comments' => $comments, 'categories' => $terms, 'tags' => $tags, 'user_id' => $user_id, 'content_sha256' => $content_digest, 'original' => $original ], true );
	rich_json_write( $rich_contract_path, $contract );
	echo wp_json_encode( [ 'phase' => 'seed', 'counts' => $contract['counts'], 'performance_pages' => 3, 'content_sha256' => $content_digest, 'private_auth_outside_document_root' => true ] ), "\n";
	exit;
}

rich_check( is_file( $rich_contract_path ) && ! is_link( $rich_contract_path ) && is_file( $rich_private_path ) && ! is_link( $rich_private_path ), 'existing owned fixture receipts' );
$contract = json_decode( file_get_contents( $rich_contract_path ), true );
$state = json_decode( file_get_contents( $rich_private_path ), true );
rich_check( 1 === ( $contract['schema'] ?? null ) && ( $state['site'] ?? '' ) === $rich_root && $contract['environment']['content_sha256'] === $state['content_sha256'], 'fixture identity unchanged' );
rich_check( get_stylesheet() === 'cache-cache-rich-site' && rich_resources( $rich_root, $rich_theme_path, $state['media'] ) === $contract['resources'], 'owned theme and media bytes unchanged' );
rich_check( rich_content_digest( array_merge( $state['posts'], $state['pages'] ), $contract['resources'] ) === $state['content_sha256'], 'actual persisted content and original resource bytes unchanged' );
if ( 'cleanup' === $rich_phase ) {
	rich_check( empty( $state['cleaned'] ) && is_array( $state['original']['settings'] ) && is_dir( WP_CONTENT_DIR . '/themes/' . $state['original']['theme'] ), 'original owned snapshot available for one cleanup' );
	$expected_settings = $state['original']['settings'];
	foreach ( [ 'minify_css_key', 'minify_js_key', 'local_css_key' ] as $key ) { unset( $expected_settings[ $key ] ); }
	rich_save_settings( $state['original']['settings'], $expected_settings );
	switch_theme( $state['original']['theme'] );
	update_option( 'show_on_front', $state['original']['show_on_front'] );
	update_option( 'page_on_front', $state['original']['page_on_front'] );
	rich_check( get_option( 'show_on_front' ) === $state['original']['show_on_front'] && get_option( 'page_on_front' ) === $state['original']['page_on_front'], 'original front-page settings restored' );
	foreach ( array_merge( $state['posts'], $state['pages'] ) as $id ) {
		rich_check( '1' === get_post_meta( $id, '_rich_fixture_owned', true ), 'cleanup post belongs to fixture' );
		rich_check( (bool) wp_delete_post( $id, true ), 'owned post removed natively' );
	}
	foreach ( $state['media'] as $id ) {
		$file = get_attached_file( $id );
		rich_check( '1' === get_post_meta( $id, '_rich_fixture_owned', true ) && is_string( $file ) && ! is_link( $file ) && strpos( realpath( $file ), $rich_root . '/wp-content/uploads/' ) === 0, 'cleanup attachment file belongs to fixture' );
		rich_check( (bool) wp_delete_attachment( $id, true ), 'owned attachment removed natively' );
	}
	foreach ( [ 'category' => $state['categories'], 'post_tag' => $state['tags'] ] as $taxonomy => $ids ) {
		foreach ( $ids as $id ) {
			rich_check( '1' === get_term_meta( $id, '_rich_fixture_owned', true ), 'cleanup term belongs to fixture' );
			rich_check( true === wp_delete_term( $id, $taxonomy ), 'owned taxonomy term removed' );
		}
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	rich_check( get_userdata( $state['user_id'] )->user_login === 'rich-acceptance' && wp_delete_user( $state['user_id'] ), 'owned fixture administrator removed' );
	foreach ( [ 'rich_fixture_media', 'rich_fixture_submissions' ] as $option ) { delete_option( $option ); }
	$mu = WP_CONTENT_DIR . '/mu-plugins/cache-cache-rich-boundary.php';
	rich_check( ! is_link( $mu ) && hash_file( 'sha256', $mu ) === hash_file( 'sha256', $rich_source . '/boundary.php' ), 'cleanup MU boundary is the owned source' );
	rich_check( unlink( $mu ), 'owned MU boundary removed' );
	rich_check( realpath( $rich_theme_path ) === $rich_theme_path && dirname( $rich_theme_path ) === WP_CONTENT_DIR . '/themes', 'cleanup theme stays within owned destination' );
	$entries = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $rich_theme_path, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $entries as $entry ) {
		rich_check( ! $entry->isLink(), 'cleanup theme contains no links' );
		rich_check( $entry->isDir() ? rmdir( $entry->getPathname() ) : unlink( $entry->getPathname() ), 'owned theme entry removed' );
	}
	rich_check( rmdir( $rich_theme_path ) && get_stylesheet() === $state['original']['theme'], 'original theme restored and owned theme removed' );
	flush_rewrite_rules();
	$state['cleaned'] = true;
	rich_json_write( $rich_private_path, $state, true );
	echo wp_json_encode( [ 'phase' => 'cleanup', 'original_theme_restored' => true, 'original_settings_restored_through_native_domain' => true, 'owned_content_removed' => true, 'evidence_retained' => true, 'global_database_rewind' => false ] ), "\n";
	exit;
}
if ( strpos( $rich_phase, 'profile-' ) === 0 ) {
	rich_profile( substr( $rich_phase, 8 ), $contract );
	rich_json_write( $rich_contract_path, $contract );
	echo wp_json_encode( [ 'phase' => $rich_phase, 'profile' => $contract['profile'], 'content_sha256' => $contract['environment']['content_sha256'] ] ), "\n";
	exit;
}
if ( 'purge' === $rich_phase ) {
	$pages_root = WP_CONTENT_DIR . '/cache/noobrocket/pages';
	rich_check( is_dir( $pages_root ) && ! is_link( $pages_root ) && realpath( $pages_root ) === $pages_root, 'native purge owns the copied page-cache path' );
	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $pages_root, FilesystemIterator::SKIP_DOTS ) ) as $entry ) { rich_check( ! $entry->isLink(), 'native purge scope contains no links' ); }
	rich_check( noobrocket_clean_domain(), 'native domain purge completed' );
	echo wp_json_encode( [ 'phase' => 'purge', 'native_owner' => 'noobrocket_clean_domain', 'profile' => $contract['profile']['name'] ] ), "\n";
	exit;
}
foreach ( array_merge( $state['posts'], $state['pages'], $state['media'] ) as $id ) { rich_check( '1' === get_post_meta( $id, '_rich_fixture_owned', true ), 'seeded content still belongs to fixture' ); }
rich_check( count( $state['posts'] ) >= 60 && count( $state['comments'] ) >= 120 && count( $state['media'] ) >= 12, 'representative rich dataset retained' );
echo wp_json_encode( [ 'phase' => $rich_phase, 'profile' => $contract['profile'], 'counts' => $contract['counts'], 'checks' => $rich_checks, 'content_sha256' => $contract['environment']['content_sha256'], 'private_auth_outside_document_root' => true ] ), "\n";
