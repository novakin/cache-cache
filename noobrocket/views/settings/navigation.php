<?php
/** Direct destinations keep the settings menu compact and predictable. */
defined( 'ABSPATH' ) || exit;
$noobrocket_sections = [];
foreach ( $data as $section ) {
	$noobrocket_sections[ $section['id'] ] = $section;
}
$noobrocket_routes = array_unique(
	array_merge(
		[ 'dashboard', 'preload', 'advanced_cache', 'file_optimization', 'media', 'assets', 'page_cdn', 'addons', 'cloudflare', 'sucuri', 'heartbeat', 'database', 'history', 'tools' ],
		array_keys( $noobrocket_sections )
	)
);
$noobrocket_titles = [
	'dashboard'      => __( 'Overview', 'noobrocket' ),
	'preload'        => __( 'Preload', 'noobrocket' ),
	'advanced_cache' => __( 'Cache rules', 'noobrocket' ),
	'page_cdn'       => __( 'CDN', 'noobrocket' ),
	'addons'         => __( 'Integrations', 'noobrocket' ),
	'history'        => __( 'Settings history', 'noobrocket' ),
];
$noobrocket_icons = [
	'dashboard' => 'grid', 'preload' => 'cache', 'advanced_cache' => 'sliders',
	'file_optimization' => 'code', 'media' => 'image', 'assets' => 'sliders',
	'page_cdn' => 'globe', 'cloudflare' => 'cloud', 'sucuri' => 'shield',
	'heartbeat' => 'clock', 'addons' => 'server', 'database' => 'server',
	'history' => 'clock', 'tools' => 'sliders',
];
foreach ( $noobrocket_routes as $id ) :
	if ( ! isset( $noobrocket_sections[ $id ] ) ) {
		continue;
	}
	$section = $noobrocket_sections[ $id ];
?>
			<a href="#<?php echo esc_attr( $id ); ?>" id="noobrocket-nav-<?php echo esc_attr( $id ); ?>" class="noobrocket-menuItem <?php echo esc_attr( $section['class'] ); ?>">
				<svg class="noobrocket-icon" aria-hidden="true"><use href="#nbr-icon-<?php echo esc_attr( $noobrocket_icons[ $id ] ?? 'sliders' ); ?>"/></svg>
				<span class="noobrocket-menuItem-title"><?php echo esc_html( $noobrocket_titles[ $id ] ?? $section['title'] ); ?></span>
			</a>
<?php endforeach; ?>
