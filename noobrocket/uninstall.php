<?php

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ! defined( 'NOOBROCKET_CACHE_ROOT_PATH' ) ) {
	define( 'NOOBROCKET_CACHE_ROOT_PATH', WP_CONTENT_DIR . '/cache/noobrocket/' );
}

if ( ! defined( 'NOOBROCKET_CONFIG_PATH' ) ) {
	define( 'NOOBROCKET_CONFIG_PATH', WP_CONTENT_DIR . '/noobrocket-config/' );
}

require_once __DIR__ . '/inc/Engine/NoobRocketUninstall.php';

// RUCSS Database Engine.
require_once __DIR__ . '/inc/Dependencies/BerlinDB/Database/Base.php';
require_once __DIR__ . '/inc/Dependencies/BerlinDB/Database/Column.php';
require_once __DIR__ . '/inc/Dependencies/BerlinDB/Database/Schema.php';
require_once __DIR__ . '/inc/Dependencies/BerlinDB/Database/Query.php';
require_once __DIR__ . '/inc/Dependencies/BerlinDB/Database/Row.php';
require_once __DIR__ . '/inc/Dependencies/BerlinDB/Database/Table.php';
require_once __DIR__ . '/inc/Dependencies/BerlinDB/Database/Queries/Meta.php';
require_once __DIR__ . '/inc/Dependencies/BerlinDB/Database/Queries/Date.php';
require_once __DIR__ . '/inc/Dependencies/BerlinDB/Database/Queries/Compare.php';
require_once __DIR__ . '/inc/Engine/Common/Database/TableInterface.php';
require_once __DIR__ . '/inc/Engine/Common/Database/Tables/AbstractTable.php';
require_once __DIR__ . '/inc/Engine/Optimization/RUCSS/Database/Tables/UsedCSS.php';
require_once __DIR__ . '/inc/Engine/Preload/Database/Tables/Cache.php';
require_once __DIR__ . '/inc/Engine/Media/AboveTheFold/Database/Tables/AboveTheFold.php';
require_once __DIR__ . '/inc/Engine/Optimization/LazyRenderContent/Database/Table/LazyRenderContent.php';
require_once __DIR__ . '/inc/Engine/Media/PreloadFonts/Database/Table/PreloadFonts.php';
require_once __DIR__ . '/inc/Engine/Media/PreconnectExternalDomains/Database/Table/PreconnectExternalDomains.php';
require_once __DIR__ . '/inc/Engine/Admin/PerformanceMonitoring/Database/Tables/PerformanceMonitoring.php';
require_once __DIR__ . '/inc/Engine/CDN/HostedCDN/Database/Tables/HostedCDN.php';

$noobrocket_rucss_usedcss_table   = new NoobRocket\Engine\Optimization\RUCSS\Database\Tables\UsedCSS();
$noobrocket_cache_table           = new NoobRocket\Engine\Preload\Database\Tables\Cache();
$noobrocket_atf_table             = new NoobRocket\Engine\Media\AboveTheFold\Database\Tables\AboveTheFold();
$noobrocket_lrc_table             = new NoobRocket\Engine\Optimization\LazyRenderContent\Database\Table\LazyRenderContent();
$noobrocket_preload_fonts_table   = new NoobRocket\Engine\Media\PreloadFonts\Database\Table\PreloadFonts();
$noobrocket_preload_domains_table = new NoobRocket\Engine\Media\PreconnectExternalDomains\Database\Table\PreconnectExternalDomains();
$noobrocket_noobrocket_insights_table = new NoobRocket\Engine\Admin\PerformanceMonitoring\Database\Tables\PerformanceMonitoring();
$noobrocket_noobrocket_cdn_table      = new NoobRocket\Engine\CDN\HostedCDN\Database\Tables\HostedCDN();

$noobrocket_uninstall = new NoobRocketUninstall(
	NOOBROCKET_CACHE_ROOT_PATH,
	NOOBROCKET_CONFIG_PATH,
	[
		$noobrocket_rucss_usedcss_table,
		$noobrocket_cache_table,
		$noobrocket_atf_table,
		$noobrocket_lrc_table,
		$noobrocket_preload_fonts_table,
		$noobrocket_preload_domains_table,
		$noobrocket_noobrocket_insights_table,
		$noobrocket_noobrocket_cdn_table,
	]
);

$noobrocket_uninstall->uninstall();
