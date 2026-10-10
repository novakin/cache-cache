<?php
// Bootstrap only the fresh site made by setup-wordpress.sh.
$root = isset( $argv[1] ) ? realpath( $argv[1] ) : false;
if ( PHP_SAPI !== 'cli' || ! $root || ! is_file( $root . '/.noobrocket-acceptance-site' ) ) {
    fwrite( STDERR, "A fresh marked CLI site is required.\n" );
    exit( 2 );
}
define( 'WP_INSTALLING', true );
$_SERVER['HTTP_HOST'] = '127.0.0.1:8765';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'POST';
require $root . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
if ( is_blog_installed() ) {
    throw new RuntimeException( 'Refusing to install over an existing WordPress database.' );
}
add_filter( 'pre_wp_mail', '__return_true' );
// Use the chosen fixture structure instead of probing HTTP during installation.
$install_permalink_structure = static function () { return '/%postname%/'; };
add_filter( 'pre_option_permalink_structure', $install_permalink_structure );
try {
    wp_install( 'Cache-Cache disposable CI', 'cachecache', 'proof@example.test', false, '', 'disposable-ci-login' );
} finally {
    remove_filter( 'pre_option_permalink_structure', $install_permalink_structure );
}
update_option( 'permalink_structure', '/%postname%/' );
echo "Installed disposable WordPress database.\n";
