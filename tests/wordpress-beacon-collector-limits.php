<?php
/** Actual beacon collectors and contexts; modeled WordPress/database boundaries, no site bootstrap. */
declare(strict_types=1);

namespace {
	class BeaconLimitQuery {
		public $row = false;
		public $write_result = true;
		public $lookups = [];
		public $writes = [];
		public function get_row( $url, $is_mobile ) { $this->lookups[] = [ $url, $is_mobile ]; return $this->row; }
		public function add_item( $item ) { $this->writes[] = $item; return $this->write_result; }
	}
}
namespace NoobRocket\Engine\Media\PreconnectExternalDomains\Database\Queries {
	class PreconnectExternalDomains extends \BeaconLimitQuery {}
}
namespace NoobRocket\Engine\Media\PreloadFonts\Database\Queries {
	class PreloadFonts extends \BeaconLimitQuery {}
}
namespace NoobRocket\Engine\Cache {
	class Purge {
		public function purge_url( $url, $pagination = false, $anonymous_only = false ) {
			\beacon_limit_check( false === $pagination && true === $anonymous_only, 'guest font publication selects only anonymous page files' );
			$GLOBALS['purge_calls'][] = $url;
		}
	}
}
namespace {
	$checks = 0;
	$filters = [];
	$filter_calls = [];
	$sanitized_urls = [];
	$purge_calls = [];
	$home = 'https://site.test';
	$posts = [];
	$rejected = '';
	class WP_Http {
		public static function make_absolute_url( $url, $base ) { return false !== strpos( $url, '://' ) ? $url : $base . ltrim( $url, '/' ); }
	}
	function home_url() { return $GLOBALS['home']; }
	function admin_url() { return $GLOBALS['home'] . '/wp-admin/'; }
	function rest_url() { return $GLOBALS['home'] . '/wp-json/'; }
	function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
	function trailingslashit( $url ) { return rtrim( $url, '/' ) . '/'; }
	function get_noobrocket_cache_reject_uri() { return $GLOBALS['rejected']; }
	function get_post_stati() { return [ 'publish', 'private', 'draft' ]; }
	function noobrocket_url_to_postid( $url, $statuses ) { return isset( $GLOBALS['posts'][ $url ] ) ? $url : 0; }
	function get_post( $id ) { return $GLOBALS['posts'][ $id ] ?? false; }
	function is_post_publicly_viewable( $post ) { return 'publish' === $post->post_status; }
	function beacon_limit_check( $condition, $message ) {
		if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); }
		++$GLOBALS['checks'];
	}
	function apply_filters( $hook, $value ) { return $value; }
	function wpm_apply_filters_typed( $type, $hook, $value, ...$args ) {
		$GLOBALS['filter_calls'][ $hook ][] = [ $type, $value, $args ];
		return $GLOBALS['filters'][ $hook ] ?? $value;
	}
	function check_ajax_referer( $action, $field ) {
		beacon_limit_check( 'noobrocket_beacon' === $action && 'noobrocket_beacon_nonce' === $field, 'collectors retain beacon nonce boundary' );
	}
	function wp_unslash( $value ) { return stripslashes( $value ); }
	function esc_url_raw( $url ) { return trim( $url ); }
	function untrailingslashit( $url ) { return rtrim( $url, '/' ); }
	function sanitize_url( $url ) {
		$GLOBALS['sanitized_urls'][] = $url;
		return trim( $url );
	}
	function sanitize_text_field( $value ) { return trim( strip_tags( $value ) ); }
	function esc_html__( $text, $domain ) { return $text; }
	function wp_json_encode( $value ) { return json_encode( $value ); }
	function current_time( $format, $gmt ) { return '2026-10-10 12:00:00'; }

	$root = dirname( __DIR__ ) . '/noobrocket/inc/';
	require $root . 'classes/admin/class-options-data.php';
	require $root . 'Engine/Common/Context/ContextInterface.php';
	require $root . 'Engine/Common/Context/AbstractContext.php';
	require $root . 'Engine/Media/PreconnectExternalDomains/Context/Context.php';
	require $root . 'Engine/Media/PreloadFonts/Context/Context.php';
	require $root . 'Engine/Common/PerformanceHints/AJAX/AJAXControllerTrait.php';
	require $root . 'Engine/Common/PerformanceHints/AJAX/ControllerInterface.php';
	require $root . 'Engine/Preload/Controller/CheckExcludedTrait.php';
	require $root . 'Engine/Media/PreconnectExternalDomains/AJAX/Controller.php';
	require $root . 'Engine/Media/PreloadFonts/AJAX/Controller.php';

	function beacon_limit_controller( $feature, $allowed = true ) {
		$options = new NoobRocket\Admin\Options_Data( [ 'auto_preload_fonts' => (int) $allowed ] );
		if ( 'fonts' === $feature ) {
			$query = new NoobRocket\Engine\Media\PreloadFonts\Database\Queries\PreloadFonts();
			$context = new NoobRocket\Engine\Media\PreloadFonts\Context\Context( $options );
			$controller = new NoobRocket\Engine\Media\PreloadFonts\AJAX\Controller( $query, $context, new NoobRocket\Engine\Cache\Purge() );
		} else {
			$GLOBALS['filters']['noobrocket_preconnect_external_domains_optimization'] = $allowed;
			$query = new NoobRocket\Engine\Media\PreconnectExternalDomains\Database\Queries\PreconnectExternalDomains();
			$context = new NoobRocket\Engine\Media\PreconnectExternalDomains\Context\Context( $options );
			$controller = new NoobRocket\Engine\Media\PreconnectExternalDomains\AJAX\Controller( $query, $context );
		}
		return [ $controller, $query ];
	}
	function beacon_limit_request( $controller, $input_key, $items, $status = '<b>success</b>', $url = 'https://site.test/page/' ) {
		$_POST = [ 'url' => $url, 'is_mobile' => 'true', 'status' => $status ];
		if ( null !== $items ) { $_POST['results'] = addslashes( json_encode( [ $input_key => $items ] ) ); }
		$GLOBALS['sanitized_urls'] = [];
		$GLOBALS['purge_calls'] = [];
		return $controller->add_data();
	}

	$features = [
		'domains' => [ 'preconnect_external_domain', 'preconnect_external_domains', 'noobrocket_preconnect_external_domains_number' ],
		'fonts' => [ 'preload_fonts', 'preload_fonts', 'noobrocket_preload_fonts_number' ],
	];
	foreach ( $features as $field => [ $input_key, $payload_key, $limit_hook ] ) {
		$resources = [];
		for ( $index = 1; $index <= 24; ++$index ) { $resources[] = 'https://cdn.test/resource-' . $index . '.woff2'; }
		$filters = [];
		list( $controller, $query ) = beacon_limit_controller( $field );
		$payload = beacon_limit_request( $controller, $input_key, $resources );
		$saved = $query->writes[0];
		beacon_limit_check( array_slice( $resources, 0, 20 ) === json_decode( $saved[ $field ], true ), $field . ': default limit bounds persisted hints in input order' );
		beacon_limit_check( $saved === $payload[ $payload_key ] && [ [ 'https://site.test/page', true ] ] === $query->lookups, $field . ': existing response, URL and device variant are preserved' );
		beacon_limit_check( 'completed' === $saved['status'] && '' === $saved['error_message'] && $saved['created_at'] === $saved['last_accessed'], $field . ': native status mapping and row metadata are preserved' );
		beacon_limit_check( array_slice( $resources, 0, 20 ) === $sanitized_urls, $field . ': only retained resources reach URL sanitizer' );
		beacon_limit_check( [ 'integer', 20, [ 'https://site.test/page', $resources ] ] === $filter_calls[ $limit_hook ][0], $field . ': limit filter receives original discovery and page URL' );

		foreach ( [ 2 => 2, 1 => 1, 0 => 1, -4 => 1 ] as $limit => $expected_count ) {
			$filters = [ $limit_hook => $limit ];
			list( $controller, $query ) = beacon_limit_controller( $field );
			beacon_limit_request( $controller, $input_key, array_slice( $resources, 0, 4 ) );
			beacon_limit_check( array_slice( $resources, 0, $expected_count ) === json_decode( $query->writes[0][ $field ], true ), $field . ': configured limit ' . $limit . ' retains normalized count' );
		}
		foreach ( [ [], [ $resources[0] ], array_slice( $resources, 0, 2 ) ] as $items ) {
			$filters = [ $limit_hook => 2 ];
			list( $controller, $query ) = beacon_limit_controller( $field );
			beacon_limit_request( $controller, $input_key, $items );
			beacon_limit_check( $items === json_decode( $query->writes[0][ $field ], true ), $field . ': empty, below-limit and exact-limit discoveries are unchanged' );
		}

		$filters = [ $limit_hook => 1 ];
		list( $controller, $query ) = beacon_limit_controller( $field );
		beacon_limit_request( $controller, $input_key, [ ' https://cdn.test/trimmed.woff2 ', $resources[1] ] );
		beacon_limit_check( [ 'https://cdn.test/trimmed.woff2' ] === json_decode( $query->writes[0][ $field ], true ), $field . ': retained resource stores sanitizer result' );

		list( $controller, $query ) = beacon_limit_controller( $field );
		$query->row = (object) [ 'id' => 7, $field => 'existing-hints' ];
		$payload = beacon_limit_request( $controller, $input_key, $resources );
		beacon_limit_check( [ $payload_key => 'item already in the database' ] === $payload && [] === $query->writes && 'existing-hints' === $query->row->$field, $field . ': existing row is never overwritten' );
		beacon_limit_check( [ $input_key => true ] === $controller->check_data(), $field . ': existing-row read response is preserved' );
		$query->row = false;
		beacon_limit_check( [ $input_key => false ] === $controller->check_data(), $field . ': absent-row read response is preserved' );

		list( $controller, $query ) = beacon_limit_controller( $field );
		$query->write_result = false;
		$payload = beacon_limit_request( $controller, $input_key, $resources );
		beacon_limit_check( [ $payload_key => 'error when adding the entry to the database' ] === $payload && 1 === count( $query->writes ), $field . ': storage failure retains existing feedback' );
		list( $controller, $query ) = beacon_limit_controller( $field );
		beacon_limit_request( $controller, $input_key, null, 'timeout' );
		beacon_limit_check( [] === json_decode( $query->writes[0][ $field ], true ) && 'failed' === $query->writes[0]['status'] && 'Script timeout' === $query->writes[0]['error_message'], $field . ': missing discoveries and timeout row handling are preserved' );
		list( $controller, $query ) = beacon_limit_controller( $field, false );
		$payload = beacon_limit_request( $controller, $input_key, $resources );
		beacon_limit_check( [ $payload_key => 'not allowed' ] === $payload && [] === $query->lookups && [] === $query->writes && [] === $sanitized_urls, $field . ': disallowed context performs no collection or storage' );
	}

	$fonts = [
		'https://cdn.test/wrong.ttf',
		'https://cdn.test/exact.woff2',
		'https://cdn.test/BLOCKED-family.woff2',
		'https://cdn.test/first.otf',
		'https://cdn.test/second.woff2',
		'https://cdn.test/third.woff2',
	];
	$filters = [
		'noobrocket_preload_fonts_number' => 2,
		'noobrocket_preload_fonts_excluded_fonts' => [ $fonts[1], 'blocked' ],
		'noobrocket_preload_fonts_processed_extensions' => [ 'woff2', 'otf' ],
	];
	list( $controller, $query ) = beacon_limit_controller( 'fonts' );
	beacon_limit_request( $controller, 'preload_fonts', $fonts );
	beacon_limit_check( [ $fonts[3], $fonts[4] ] === json_decode( $query->writes[0]['fonts'], true ), 'fonts: extension and exact/case-insensitive exclusions run before limit, without consuming slots' );
	beacon_limit_check( [ $fonts[3], $fonts[4] ] === $sanitized_urls, 'fonts: excluded and over-limit resources never reach URL sanitizer' );
	$filters['noobrocket_preload_fonts_number'] = 0;
	list( $controller, $query ) = beacon_limit_controller( 'fonts' );
	beacon_limit_request( $controller, 'preload_fonts', $fonts );
	beacon_limit_check( [ $fonts[3] ] === json_decode( $query->writes[0]['fonts'], true ), 'fonts: normalized minimum retains first eligible resource after exclusions' );
	$filters['noobrocket_preload_fonts_excluded_fonts'] = [ 'cdn.test' ];
	list( $controller, $query ) = beacon_limit_controller( 'fonts' );
	beacon_limit_request( $controller, 'preload_fonts', $fonts );
	beacon_limit_check( [] === json_decode( $query->writes[0]['fonts'], true ), 'fonts: fully excluded discovery remains empty despite normalized minimum' );
	$filters = [ 'noobrocket_preload_fonts_number' => 1 ];
	list( $controller, $query ) = beacon_limit_controller( 'fonts' );
	beacon_limit_request( $controller, 'preload_fonts', [ 'https://cdn.test/legacy.svg', $fonts[3] ] );
	beacon_limit_check( [ 'https://cdn.test/legacy.svg' ] === json_decode( $query->writes[0]['fonts'], true ), 'fonts: empty exclusion list retains existing extension-filter bypass' );

	$filters = [];
	foreach ( [ 'https://site.test/', 'https://site.test/page/' ] as $url ) {
		list( $controller, $query ) = beacon_limit_controller( 'fonts' );
		beacon_limit_request( $controller, 'preload_fonts', [ $fonts[4] ], 'success', $url );
		beacon_limit_check( [ untrailingslashit( $url ) ] === $purge_calls, 'successful nonempty collection invalidates its exact page once' );
		$query->row = (object) $query->writes[0];
		beacon_limit_request( $controller, 'preload_fonts', [ $fonts[4] ], 'success', $url );
		beacon_limit_check( [] === $purge_calls, 'duplicate collection does not repeat invalidation' );
	}
	foreach ( [ [ [], 'success' ], [ [ '' ], 'success' ], [ [ $fonts[4] ], 'timeout' ], [ [ $fonts[4] ], 'script_error' ] ] as [ $items, $status ] ) {
		list( $controller, $query ) = beacon_limit_controller( 'fonts' );
		beacon_limit_request( $controller, 'preload_fonts', $items, $status );
		beacon_limit_check( [] === $purge_calls, 'empty or failed font collection does not invalidate' );
	}
	list( $controller, $query ) = beacon_limit_controller( 'fonts' );
	$query->write_result = false;
	beacon_limit_request( $controller, 'preload_fonts', [ $fonts[4] ], 'success' );
	beacon_limit_check( [] === $purge_calls, 'failed persistence does not invalidate' );
	list( $controller, $query ) = beacon_limit_controller( 'fonts', false );
	beacon_limit_request( $controller, 'preload_fonts', [ $fonts[4] ], 'success' );
	beacon_limit_check( [] === $purge_calls, 'disabled feature does not invalidate' );
	foreach ( [ 'https://foreign.test/page/', '/page/', 'http://site.test/page/', 'https://site.test:444/page/', 'https://user@site.test/page/', 'https://site.test/page/?action=save', 'https://site.test/page/?', 'https://site.test/page/#anchor', 'https://site.test/page/#', 'https://site.test/page/*', 'https://site.test/page/%2a', 'https://site.test/page/%2e%2e/other/', 'https://site.test/wp-admin/', 'https://site.test/wp-login.php', 'https://site.test/wp-json/wp/v2/posts' ] as $url ) {
		list( $controller, $query ) = beacon_limit_controller( 'fonts' );
		beacon_limit_request( $controller, 'preload_fonts', [ $fonts[4] ], 'success', $url );
		beacon_limit_check( [] === $purge_calls, 'shared nonce does not authorize invalid page purge: ' . $url );
	}
	$home = 'https://site.test/blog';
	foreach ( [ 'https://site.test/other/', 'https://site.test/blogger/' ] as $url ) {
		list( $controller, $query ) = beacon_limit_controller( 'fonts' );
		beacon_limit_request( $controller, 'preload_fonts', [ $fonts[4] ], 'success', $url );
		beacon_limit_check( [] === $purge_calls, 'sibling site outside selected home path is preserved' );
	}
	list( $controller, $query ) = beacon_limit_controller( 'fonts' );
	beacon_limit_request( $controller, 'preload_fonts', [ $fonts[4] ], 'success', $home . '/page/' );
	beacon_limit_check( [ $home . '/page' ] === $purge_calls, 'selected subdirectory page can expose completed hints' );
	$home = 'https://site.test';
	foreach ( [ [ 'private', '' ], [ 'draft', '' ], [ 'publish', 'password' ] ] as [ $status, $password ] ) {
		$posts['https://site.test/page'] = (object) [ 'post_status' => $status, 'post_password' => $password ];
		list( $controller, $query ) = beacon_limit_controller( 'fonts' );
		beacon_limit_request( $controller, 'preload_fonts', [ $fonts[4] ], 'success' );
		beacon_limit_check( [] === $purge_calls, 'private, draft and password-protected pages are not invalidated by guest hints' );
	}
	$posts = [];
	$rejected = '/page/?';
	list( $controller, $query ) = beacon_limit_controller( 'fonts' );
	beacon_limit_request( $controller, 'preload_fonts', [ $fonts[4] ], 'success' );
	beacon_limit_check( [] === $purge_calls, 'saved cache URI exclusions also prevent guest invalidation' );
	foreach ( [ '/page/', '/(?:.+/)?embed/' ] as $pattern ) {
		$rejected = $pattern;
		$url = false !== strpos( $pattern, 'embed' ) ? 'https://site.test/article/embed/' : 'https://site.test/page/';
		list( $controller, $query ) = beacon_limit_controller( 'fonts' );
		beacon_limit_request( $controller, 'preload_fonts', [ $fonts[4] ], 'success', $url );
		beacon_limit_check( [] === $purge_calls, 'required trailing-slash exclusions survive stored URL normalization' );
	}

	echo "PASS: $checks beacon collector limit checks; modeled WordPress/database boundaries, no site writes.\n";
}
