<?php
/** Actual beacon injection; modeled WordPress helpers and owned copied assets, no site or HTTP. */
declare(strict_types=1);

namespace NoobRocket\Engine\Common {
	class Utils {
		public static function is_saas_visit() { return false; }
	}
}

namespace {
	function beacon_asset_check( $condition, $message ) {
		if ( ! $condition ) { throw new \RuntimeException( 'FAIL: ' . $message ); }
		++$GLOBALS['checks'];
	}
	class WP_Filesystem_Direct {
		public $exists_calls = [];
		public $mtime_calls = [];
		public $mtime_available = true;
		public function exists( $path ) {
			$this->exists_calls[] = $path;
			return is_file( $path );
		}
		public function mtime( $path ) {
			$this->mtime_calls[] = $path;
			clearstatcache( true, $path );
			return $this->mtime_available ? filemtime( $path ) : false;
		}
	}
	function noobrocket_get_constant( $name ) { return $GLOBALS['beacon_constants'][ $name ] ?? false; }
	function noobrocket_apply_filter_and_deprecated( $hook, $args, $version, $old_hook ) { return $args[0]; }
	function admin_url( $path ) { return 'https://site.test/wp-admin/' . $path; }
	function wp_create_nonce( $action ) {
		beacon_asset_check( 'noobrocket_beacon' === $action, 'beacon nonce action stays unchanged' );
		++$GLOBALS['nonce_calls'];
		return 'modeled-current-visitor-nonce';
	}
	function wp_json_encode( $value ) { return json_encode( $value ); }
	function add_query_arg( $key, $value, $url ) {
		$GLOBALS['query_calls'][] = [ $key, $value, $url ];
		return $url . '?' . rawurlencode( $key ) . '=' . rawurlencode( (string) $value );
	}
	function esc_url( $url ) {
		$GLOBALS['escaped_urls'][] = $url;
		return htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' );
	}
	function beacon_asset_output( $html ) {
		beacon_asset_check( 1 === preg_match( '~<script data-name="noobrocket-noobrocket-beacon" src=\x27([^\x27]+)\x27 async></script>~', $html, $script ), 'one external beacon script is emitted' );
		beacon_asset_check( 1 === preg_match( '~<script>var noobrocket_beacon_data = (.*?)</script>~s', $html, $inline ), 'inline beacon configuration is emitted' );
		return [ html_entity_decode( $script[1], ENT_QUOTES, 'UTF-8' ), json_decode( $inline[1], true ) ];
	}

	$mode = $argv[1] ?? 'min';
	if ( ! in_array( $mode, [ 'source', 'min' ], true ) ) {
		throw new \InvalidArgumentException( 'Usage: php tests/beacon-asset-version.php [source|min]' );
	}
	define( 'SCRIPT_DEBUG', 'source' === $mode );
	$root = dirname( __DIR__ );
	$owner = getenv( 'NOOBROCKET_BEACON_PROCESSOR' ) ?: $root . '/noobrocket/inc/Engine/Common/PerformanceHints/Frontend/Processor.php';
	require $owner;
	$checks = 0;
	$nonce_calls = 0;
	$query_calls = [];
	$escaped_urls = [];
	$assets = [ 'noobrocket-beacon.js', 'noobrocket-beacon.min.js' ];
	$owned_directory = sys_get_temp_dir() . '/noobrocket-beacon-asset-version-' . bin2hex( random_bytes( 8 ) );
	if ( ! mkdir( $owned_directory, 0700 ) ) { throw new \RuntimeException( 'Cannot create owned asset directory.' ); }
	$owned_directory = realpath( $owned_directory );

	try {
		foreach ( $assets as $asset ) {
			beacon_asset_check( copy( $root . '/noobrocket/assets/js/' . $asset, $owned_directory . '/' . $asset ), 'copy maintained asset to owned temporary directory' );
			beacon_asset_check( touch( $owned_directory . '/' . $asset, 1735689500 ), 'set alternate asset timestamp' );
		}
		$filename = 'noobrocket-beacon' . ( SCRIPT_DEBUG ? '' : '.min' ) . '.js';
		$asset_path = $owned_directory . '/' . $filename;
		$beacon_constants = [
			'NOOBROCKET_ASSETS_JS_PATH' => $owned_directory . '/',
			'NOOBROCKET_ASSETS_JS_URL'  => 'https://assets.site.test/noobrocket/assets/js/',
			'NOOBROCKET_VERSION'        => 'fixture-product-version',
			'NOOBROCKET_DEBUG'          => true,
		];
		$filesystem = new WP_Filesystem_Direct();
		$factory = new class {
			public function get_frontend_controller() { return $this; }
			public function add_custom_data( $data ) {
				$data['status']['preload_fonts'] = false;
				$data['collector_marker'] = 'preserved';
				return $data;
			}
		};
		$reflection = new \ReflectionClass( \NoobRocket\Engine\Common\PerformanceHints\Frontend\Processor::class );
		$processor = $reflection->newInstanceWithoutConstructor();
		foreach ( [ 'factories' => [ $factory ], 'filesystem' => $filesystem ] as $name => $value ) {
			$property = $reflection->getProperty( $name );
			$property->setAccessible( true );
			$property->setValue( $processor, $value );
		}
		$inject = $reflection->getMethod( 'inject_beacon' );
		$inject->setAccessible( true );
		$html = '<html><body><iframe srcdoc="<body>nested</body>"></iframe><p>Preserve page.</p></body></html>';
		$page_url = 'https://site.test/branded-page';
		$expected_data = [
			'ajax_url' => 'https://site.test/wp-admin/admin-ajax.php',
			'nonce' => 'modeled-current-visitor-nonce', 'url' => $page_url, 'is_mobile' => true,
			'width_threshold' => 393, 'height_threshold' => 830, 'delay' => 500, 'debug' => true,
			'status' => [ 'preload_fonts' => false ], 'collector_marker' => 'preserved',
		];
		beacon_asset_check( touch( $asset_path, 1735689600 ), 'set first selected asset timestamp' );
		$first = $inject->invoke( $processor, $html, $page_url, true );
		list( $first_url, $first_data ) = beacon_asset_output( $first );
		$base_url = $beacon_constants['NOOBROCKET_ASSETS_JS_URL'] . $filename;
		beacon_asset_check( $base_url . '?ver=1735689600' === $first_url, $mode . ': asset URL uses selected copied file timestamp' );
		beacon_asset_check( $expected_data === $first_data, 'nonce, collector data, URL and mobile context are preserved' );
		beacon_asset_check( [ $asset_path ] === $filesystem->exists_calls && [ $asset_path ] === $filesystem->mtime_calls, 'existence guard and timestamp read select the same asset' );
		beacon_asset_check( [ [ 'ver', 1735689600, $base_url ] ] === $query_calls && [ $first_url ] === $escaped_urls, 'versioned URL passes through WordPress query and escape helpers' );
		beacon_asset_check( $html === preg_replace( '~<script>var noobrocket_beacon_data = .*?</script><script data-name="noobrocket-noobrocket-beacon".*?</script>~s', '', $first ), 'injection preserves original HTML including nested body tags' );
		beacon_asset_check( '</script></body></html>' === substr( $first, -23 ), 'beacon stays before final closing body tag' );

		beacon_asset_check( touch( $asset_path, 1735689720 ), 'change owned copied asset timestamp' );
		$second = $inject->invoke( $processor, $html, $page_url, true );
		list( $second_url, $second_data ) = beacon_asset_output( $second );
		beacon_asset_check( $base_url . '?ver=1735689720' === $second_url && $first_url !== $second_url, $mode . ': browser cache URL changes after asset update' );
		beacon_asset_check( str_replace( $first_url, $second_url, $first ) === $second && $first_data === $second_data, 'asset version change preserves all other emitted bytes and configuration' );

		$filesystem->mtime_available = false;
		list( $fallback_url, $fallback_data ) = beacon_asset_output( $inject->invoke( $processor, $html, $page_url, true ) );
		beacon_asset_check( $base_url . '?ver=fixture-product-version' === $fallback_url && $first_data === $fallback_data, 'unavailable timestamp falls back to product version without changing configuration' );
		list( $desktop_url, $desktop_data ) = beacon_asset_output( $inject->invoke( $processor, $html, $page_url, false ) );
		$expected_data['is_mobile'] = false;
		$expected_data['width_threshold'] = 1600;
		$expected_data['height_threshold'] = 700;
		beacon_asset_check( $fallback_url === $desktop_url && $expected_data === $desktop_data, 'desktop URL, device and threshold context remain intact' );

		beacon_asset_check( unlink( $asset_path ), 'remove only owned selected asset for missing-file control' );
		$before = [ count( $filesystem->mtime_calls ), $nonce_calls, count( $query_calls ), count( $escaped_urls ) ];
		beacon_asset_check( $html === $inject->invoke( $processor, $html, $page_url, true ), 'missing selected asset prevents injection' );
		beacon_asset_check( $before === [ count( $filesystem->mtime_calls ), $nonce_calls, count( $query_calls ), count( $escaped_urls ) ], 'missing asset performs no timestamp, nonce or URL work' );
		$beacon_constants['DONOTNOOBROCKETOPTIMIZE'] = true;
		$exists_before = count( $filesystem->exists_calls );
		beacon_asset_check( $html === $inject->invoke( $processor, $html, $page_url, true ) && $exists_before === count( $filesystem->exists_calls ), 'disabled optimization context returns before filesystem work' );
		echo "PASS: $checks beacon asset-version checks ($mode); modeled WordPress, owned copied files, no site or HTTP.\n";
	} finally {
		foreach ( $assets as $asset ) {
			$path = $owned_directory . '/' . $asset;
			if ( is_file( $path ) ) {
				if ( dirname( realpath( $path ) ) !== $owned_directory ) { throw new \RuntimeException( 'Refuse cleanup outside owned asset directory.' ); }
				unlink( $path );
			}
		}
		rmdir( $owned_directory );
	}
}
