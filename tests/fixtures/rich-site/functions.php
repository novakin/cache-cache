<?php
/** All content, assets and endpoints belong to a marked disposable site. */
add_action( 'after_setup_theme', static function () {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', [ 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption' ] );
} );

add_action( 'wp_enqueue_scripts', static function () {
	$base = get_stylesheet_directory_uri() . '/assets/';
	wp_enqueue_style( 'rich-base', $base . 'base.css', [], '1.0.0' );
	wp_enqueue_style( 'rich-components', $base . 'components.css', [ 'rich-base' ], '1.0.0' );
	wp_enqueue_script( 'rich-core', $base . 'core.js', [], '1.0.0', false );
	wp_enqueue_script( 'rich-interactions', $base . 'interactions.js', [ 'rich-core' ], '1.0.0', true );
	wp_enqueue_script_module( 'rich-module', $base . 'module.js', [], '1.0.0' );
	if ( is_page( 'rich-csp' ) ) {
		foreach ( [ 'global-styles', 'wp-block-library', 'classic-theme-styles' ] as $handle ) {
			wp_dequeue_style( $handle );
		}
	}
}, 100 );

add_action( 'template_redirect', static function () {
	if ( is_page( 'rich-no-store' ) ) {
		header( 'Cache-Control: private, no-store, max-age=0' );
	}
	if ( is_page( 'rich-set-cookie' ) ) {
		setcookie( 'rich_fixture_response', 'synthetic-only', [ 'path' => '/', 'httponly' => true, 'samesite' => 'Lax' ] );
		header( 'Cache-Control: private, no-store, max-age=0' );
	}
	if ( is_page( 'rich-csp' ) ) {
		header( "Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'" );
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
	}
} );

$rich_submit = static function () {
	nocache_headers();
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'rich_fixture_form' ) ) {
		status_header( 403 );
		echo 'RICH_FORM_DENIED';
		exit;
	}
	$name = sanitize_text_field( wp_unslash( $_POST['rich_name'] ?? '' ) );
	if ( '' === $name || strlen( $name ) > 80 ) {
		status_header( 400 );
		echo 'RICH_FORM_INVALID';
		exit;
	}
	update_option( 'rich_fixture_submissions', (int) get_option( 'rich_fixture_submissions', 0 ) + 1, false );
	header( 'Content-Type: text/html; charset=UTF-8' );
	echo '<!doctype html><html lang="en"><title>Fixture form receipt</title><main><p id="rich-form-result">RICH_FORM_ACCEPTED ' . esc_html( $name ) . '</p></main></html>';
	exit;
};
add_action( 'admin_post_nopriv_rich_fixture_form', $rich_submit );
add_action( 'admin_post_rich_fixture_form', $rich_submit );

$rich_status = static function () {
	nocache_headers();
	wp_send_json_success( [ 'submissions' => (int) get_option( 'rich_fixture_submissions', 0 ), 'fixture' => 'cache-cache-rich-site-v1' ] );
};
add_action( 'wp_ajax_nopriv_rich_fixture_status', $rich_status );
add_action( 'wp_ajax_rich_fixture_status', $rich_status );

