<?php
/** Browser fixture helper: real Tools templates/catalog, native MO reader; no WordPress site bootstrap. */
define( 'ABSPATH', rtrim( $argv[1], '/\\' ) . '/' );
define( 'WPINC', 'wp-includes' );
require $argv[1] . '/wp-includes/compat.php';
require $argv[1] . '/wp-includes/pomo/mo.php';
$catalog = new MO();
if ( 'fr' === $argv[2] && ! $catalog->import_from_file( dirname( __DIR__ ) . '/noobrocket/languages/noobrocket-fr_FR.mo' ) ) {
	throw new RuntimeException( 'French catalog could not be loaded.' );
}
function __( $text, $domain = null ) { return $GLOBALS['catalog']->translate( $text ); }
function esc_html__( $text, $domain = null ) { return htmlspecialchars( __( $text, $domain ), ENT_QUOTES, 'UTF-8' ); }
function esc_html_e( $text, $domain = null ) { echo esc_html__( $text, $domain ); }
function esc_attr_e( $text, $domain = null ) { echo esc_html__( $text, $domain ); }
function esc_attr( $text ) { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $text ) { return esc_attr( $text ); }
$data = [ 'url' => '#tools', 'id' => 'fixture', 'beacon' => [ 'url' => '#tools', 'id' => 'fixture' ] ];
foreach ( [ 'mobile-cache', 'enable-google-fonts' ] as $template ) {
	require dirname( __DIR__ ) . '/noobrocket/views/settings/' . $template . '.php';
}
