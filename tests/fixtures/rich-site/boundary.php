<?php
/** Copied only to the owned disposable site's MU directory by wordpress-rich-site.php. */
if ( ! is_file( ABSPATH . '.noobrocket-acceptance-site' ) ) {
	throw new RuntimeException( 'Rich fixture boundary requires the disposable marker.' );
}
add_filter( 'pre_option_show_avatars', '__return_zero' );
add_filter( 'show_admin_bar', '__return_false' );
foreach ( [ 'locale', 'determine_locale' ] as $rich_locale_filter ) {
	add_filter( $rich_locale_filter, static function ( $locale ) {
		$requested = $_COOKIE['rich_fixture_locale'] ?? '';
		return in_array( $requested, [ 'en_US', 'fr_FR' ], true ) ? $requested : $locale;
	} );
}
