<?php
/** Native duplicate recovery through registered preload hooks on a copied disposable site. */
$wordpress = isset( $argv[1] ) ? realpath( $argv[1] ) : false;
if ( ! $wordpress || ! is_file( $wordpress . '/wp-load.php' ) || ! is_file( $wordpress . '/.noobrocket-acceptance-site' ) ) {
	fwrite( STDERR, "Provide a marked disposable WordPress installation with a copied plugin.\n" );
	exit( 2 );
}
$temporary = tempnam( sys_get_temp_dir(), 'cache-cache-preload-completion-' );
unlink( $temporary );
mkdir( $temporary );
define( 'NOOBROCKET_CACHE_ROOT_PATH', $temporary . '/' );
define( 'NOOBROCKET_CONFIG_PATH', $temporary . '/config/' );
define( 'WP_ADMIN', true );
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
$_SERVER['HTTP_HOST'] = '127.0.0.1';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'POST';
require $wordpress . '/wp-load.php';

$container = apply_filters( 'noobrocket_container', null );
$query = $container->get( 'preload_caches_query' );
$options = $container->get( 'options' );
$saved = $options->get_options();
$options->set_values( array_merge( $saved, [ 'cache_ssl' => true, 'do_caching_mobile_files' => true, 'cache_webp' => false ] ) );
$unpaused = static function () { return false; };
add_filter( 'pre_option_noobrocket_preload_paused', $unpaused );
$requests = 0;
$block = static function () use ( &$requests ) {
	++$requests;
	return new WP_Error( 'completion_fixture_http', 'Ready cache variants must not need HTTP.' );
};
add_filter( 'pre_http_request', $block, PHP_INT_MAX - 1 );
$checks = 0;
$check = static function ( $condition, $message ) use ( &$checks ) {
	if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
	++$checks;
};
$ids = [];
$locks = [];
$files = [];
$url = home_url( '/cache-cache-completion-' . wp_generate_uuid4() );
$other = $url . '-other';
try {
	$check( has_action( 'noobrocket_preload_job_preload_url' ) !== false, 'native preload callback is registered' );
	for ( $i = 0; $i < 125; ++$i ) {
		$id = $query->add_item( [ 'url' => $url, 'status' => $i % 4 === 0 ? 'completed' : 'pending', 'modified' => '2020-01-01 00:00:00', 'last_accessed' => '2020-01-02 00:00:00', 'is_locked' => $i % 2 ] );
		$check( $id > 0, 'native duplicate insertion succeeds' );
		$ids[] = $id;
		$locks[ $id ] = $i % 2;
	}
	$neighbor = $query->add_item( [ 'url' => $other, 'status' => 'pending', 'modified' => '2020-01-01 00:00:00', 'last_accessed' => '2020-01-02 00:00:00', 'is_locked' => 1 ] );
	$ids[] = $neighbor;
	$check( count( $query->get_rows_by_url( $url . '/?ignored=1' ) ) === 125, 'native normalized lookup includes more than 100 rows' );
	$parsed = get_noobrocket_parse_url( $url );
	$suffix = $parsed['scheme'] === 'https' ? '-https' : '';
	$directory = NOOBROCKET_CACHE_PATH . $parsed['host'] . trailingslashit( $parsed['path'] );
	wp_mkdir_p( $directory );
	foreach ( [ 'index' . $suffix . '.html', 'index-mobile' . $suffix . '.html' ] as $name ) {
		$files[] = $directory . $name;
		file_put_contents( $directory . $name, '<!doctype html><html><body>Ready fixture cache</body></html>' );
	}
	do_action( 'noobrocket_preload_job_preload_url', $url . '/' );
	$rows = $query->get_rows_by_url( $url );
	$check( count( $rows ) === 125, 'completion preserves duplicate row identities' );
	foreach ( $rows as $i => $row ) {
		$check( $row->status === 'completed', 'registered preload completion leaves no pending duplicate' );
		$check( $row->last_accessed === strtotime( '2020-01-02 00:00:00 UTC' ), 'completion preserves last access metadata' );
		$check( (int) $row->is_locked === $locks[ $row->id ], 'completion preserves lock metadata' );
	}
	$check( $query->update_item( $rows[0]->id, [ 'status' => 'completed', 'modified' => gmdate( 'Y-m-d H:i:s', $rows[0]->modified ) ] ) === false, 'native unchanged BerlinDB update reports false' );
	$check( $query->make_status_complete( $url ), 'native repeated completion succeeds despite BerlinDB no-op updates' );
	$reset_condition = static function () use ( $wpdb, $url ) { return $wpdb->prepare( ' WHERE url = %s', $url ); };
	add_filter( 'noobrocket_preload_all_to_pending_condition', $reset_condition );
	try {
		$query->set_all_to_pending();
	} finally {
		remove_filter( 'noobrocket_preload_all_to_pending_condition', $reset_condition );
	}
	$pending = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}nbr_noobrocket_cache WHERE url = %s AND status = 'pending'", $url ) );
	$check( $pending === 125, 'native bulk reset changes backing rows after item caches were primed' );
	$check( $query->make_status_complete( $url ), 'completion recovers native bulk reset despite primed completed item caches' );
	$pending = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}nbr_noobrocket_cache WHERE url = %s AND status <> 'completed'", $url ) );
	$check( $pending === 0, 'native backing rows all complete after primed-cache reset' );
	$check( $query->get_rows_by_url( $other )[0]->status === 'pending', 'completion isolates unrelated URL' );
	$check( $requests === 0, 'ready desktop/mobile variants produce no HTTP requests' );
	$check( $query->delete_by_url( $url . '/?ignored=1' ), 'native normalized deletion succeeds' );
	$remaining = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}nbr_noobrocket_cache WHERE url = %s", $url ) );
	$check( $remaining === 0, 'native storage contains no matching rows after deletion' );
	$check( $query->get_rows_by_url( $url ) === false, 'native deletion removes all 125 matching rows' );
	$check( $query->get_rows_by_url( $other )[0]->status === 'pending', 'deletion preserves unrelated URL' );
	echo "PASS: $checks native preload completion assertions; 125 seeded duplicates, registered hook, zero HTTP requests.\n";
} finally {
	foreach ( $ids as $id ) { $query->delete_item( $id ); }
	foreach ( $files as $file ) { if ( is_file( $file ) ) { unlink( $file ); } }
	$options->set_values( $saved );
	remove_filter( 'pre_http_request', $block, PHP_INT_MAX - 1 );
	remove_filter( 'pre_option_noobrocket_preload_paused', $unpaused );
	noobrocket_direct_filesystem()->delete( $temporary, true );
}
