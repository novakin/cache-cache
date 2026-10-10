<?php

use NoobRocket\Buffer\Cache;
use NoobRocket\Buffer\Config;
use NoobRocket\Buffer\Tests;

defined( 'ABSPATH' ) || exit;

define( 'NOOBROCKET_ADVANCED_CACHE', true );

$noobrocket_path        = '{{NOOBROCKET_PATH}}';
$noobrocket_config_path = '{{NOOBROCKET_CONFIG_PATH}}';
$noobrocket_cache_path  = '{{NOOBROCKET_CACHE_PATH}}';

if (
	version_compare( phpversion(), '{{NOOBROCKET_PHP_VERSION}}', '<' )
	|| ! file_exists( $noobrocket_path )
	|| ! file_exists( $noobrocket_config_path )
	|| ! file_exists( $noobrocket_cache_path )
) {
	define( 'NOOBROCKET_ADVANCED_CACHE_PROBLEM', true );
	return;
}

'{{MOBILE_CACHE}}';
if ( file_exists( '{{NOOBROCKET_PATH}}inc/Dependencies/Detection/MobileDetect.php' ) && ! class_exists( 'NoobRocket\Dependencies\Detection\MobileDetect' ) ) {
	include_once '{{NOOBROCKET_PATH}}inc/Dependencies/Detection/MobileDetect.php';
}
'{{/MOBILE_CACHE}}';

spl_autoload_register(
	function ( $class ) use ( $noobrocket_path ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.classFound
		$noobrocket_classes = [
			'NoobRocket\\Buffer\\Abstract_Buffer' => [ $noobrocket_path . 'inc/classes/Buffer/class-abstract-buffer.php' ],
			'NoobRocket\\Buffer\\Cache'           => [ $noobrocket_path . 'inc/classes/Buffer/class-cache.php' ],
			'NoobRocket\\Buffer\\Tests'           => [ $noobrocket_path . 'inc/classes/Buffer/class-tests.php' ],
			'NoobRocket\\Buffer\\Config'          => [ $noobrocket_path . 'inc/classes/Buffer/class-config.php' ],
			'NoobRocket\\Logger\\HTMLFormatter'   => [
				$noobrocket_path . 'inc/Logger/HTMLFormatter.php',
				$noobrocket_path . 'inc/classes/logger/class-html-formatter.php',
			],
			'NoobRocket\\Logger\\Logger'          => [
				$noobrocket_path . 'inc/Logger/Logger.php',
				$noobrocket_path . 'inc/classes/logger/class-logger.php',
			],
			'NoobRocket\\Logger\\StreamHandler'   => [
				$noobrocket_path . 'inc/Logger/StreamHandler.php',
				$noobrocket_path . 'inc/classes/logger/class-stream-handler.php',
			],
			'NoobRocket\\Traits\\Memoize'         => [ $noobrocket_path . 'inc/classes/traits/trait-memoize.php' ],
		];

		if ( isset( $noobrocket_classes[ $class ] ) ) {
			$file_options = $noobrocket_classes[ $class ];
			$file         = '';

			foreach ( $file_options as $file_option ) {
				if ( file_exists( $file_option ) ) {
					$file = $file_option;
					break;
				}
			}
		} elseif ( strpos( $class, 'NoobRocket\\Dependencies\\Monolog\\' ) === 0 ) {
			$class = str_replace( 'NoobRocket\\Dependencies\\Monolog\\', '', $class );

			$file = $noobrocket_path . 'inc/Dependencies/Monolog/' . str_replace( '\\', '/', $class ) . '.php';
			if ( ! file_exists( $file ) ) {
				$file = $noobrocket_path . 'vendor/monolog/monolog/src/' . str_replace( '\\', '/', $class ) . '.php';
			}
		} elseif ( strpos( $class, 'NoobRocket\\Dependencies\\Psr\\Log\\' ) === 0 ) {
			$class = str_replace( 'NoobRocket\\Dependencies\\Psr\\Log\\', '', $class );

			$file = $noobrocket_path . 'inc/Dependencies/Psr/Log/' . str_replace( '\\', '/', $class ) . '.php';
			if ( ! file_exists( $file ) ) {
				$file = $noobrocket_path . 'vendor/psr/log/' . str_replace( '\\', '/', $class ) . '.php';
			}
		} else {
			return;
		}

		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

if ( ! class_exists( '\NoobRocket\Buffer\Cache' ) ) {
	if ( ! defined( 'DONOTNOOBROCKETOPTIMIZE' ) ) {
		define( 'DONOTNOOBROCKETOPTIMIZE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
	}
	return;
}

$noobrocket_config_class = new Config(
	[
		'config_dir_path' => $noobrocket_config_path,
	]
);

( new Cache(
	new Tests(
		$noobrocket_config_class
	),
	$noobrocket_config_class,
	[
		'cache_dir_path' => $noobrocket_cache_path,
	]
) )->maybe_init_process();
