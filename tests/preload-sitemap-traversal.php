<?php
/** Actual sitemap/parser/queue owners with modeled HTTP, storage and scheduler boundaries. */
namespace NoobRocket\Engine\Preload\Database\Queries {
	class Cache {
		public $urls = [];
		public function create_or_nothing( $input ) { $this->urls[] = $input['url']; }
	}
}
namespace {
	define( 'MINUTE_IN_SECONDS', 60 );
	$fixture = [ 'paused' => false, 'responses' => [], 'requests' => [], 'actions' => [], 'next' => 1, 'excluded' => [] ];
	$wp_rewrite = (object) [ 'pagination_base' => 'page' ];
	function get_option( $name, $default = false ) { return 'noobrocket_preload_paused' === $name ? $GLOBALS['fixture']['paused'] : $default; }
	function wp_safe_remote_get( $url ) {
		$GLOBALS['fixture']['requests'][] = $url;
		return $GLOBALS['fixture']['responses'][ $url ] ?? [ 'response' => [ 'code' => 404 ], 'body' => '' ];
	}
	function wp_remote_retrieve_response_code( $response ) { return $response['response']['code']; }
	function wp_remote_retrieve_body( $response ) { return $response['body']; }
	function home_url() { return 'https://site.test'; }
	function wp_parse_url( $url ) { return parse_url( $url ); }
	function trailingslashit( $url ) { return rtrim( $url, '/' ) . '/'; }
	function untrailingslashit( $url ) { return rtrim( $url, '/' ); }
	function user_trailingslashit( $url ) { return $url; }
	function apply_filters( $hook, $value, $url ) { return 'noobrocket_preload_exclude' === $hook ? in_array( $url, $GLOBALS['fixture']['excluded'], true ) : $value; }
	class WP_Http { public static function make_absolute_url( $url, $base ) { return parse_url( $url, PHP_URL_SCHEME ) ? $url : $base . ltrim( $url, '/' ); } }
	function as_enqueue_async_action( $hook, $args, $group, $unique = false ) {
		$id = $GLOBALS['fixture']['next']++;
		$GLOBALS['fixture']['actions'][ $id ] = [ 'hook' => $hook, 'args' => $args, 'group' => $group ];
		return $id;
	}
	$inc = dirname( __DIR__ ) . '/noobrocket/inc/';
	foreach ( [ 'Engine/Common/Queue/QueueInterface.php', 'Engine/Common/Queue/AbstractASQueue.php', 'Engine/Preload/Controller/Queue.php', 'Engine/Preload/Controller/CheckExcludedTrait.php', 'Engine/Preload/Frontend/SitemapParser.php', 'Engine/Preload/Frontend/FetchSitemap.php' ] as $file ) { require $inc . $file; }
	$checks = 0;
	$check = static function ( $condition, $message ) use ( &$checks ) { if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $message ); } ++$checks; };
	$index = static function ( array $urls ) { return '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . implode( '', array_map( static function ( $url ) { return '<sitemap><loc>' . htmlspecialchars( $url, ENT_XML1 ) . '</loc></sitemap>'; }, $urls ) ) . '</sitemapindex>'; };
	$response = static function ( $body, $code = 200 ) { return [ 'response' => [ 'code' => $code ], 'body' => $body ]; };
	$queue = new NoobRocket\Engine\Preload\Controller\Queue();
	$query = new NoobRocket\Engine\Preload\Database\Queries\Cache();
	$fetch = new NoobRocket\Engine\Preload\Frontend\FetchSitemap( new NoobRocket\Engine\Preload\Frontend\SitemapParser(), $queue, $query );
	$root = 'https://site.test/wp-sitemap.xml';
	$fixture['responses'][ $root ] = $response( $index( [ $root, $root ] ) );
	$fetch->parse_sitemap( $root );
	$check( count( $fixture['requests'] ) === 1 && [] === $fixture['actions'], 'direct self references finish after one fetch with no recreated parse action' );
	$children = [ 'https://site.test/Child.xml', 'https://site.test/child.xml', 'https://site.test/child.xml?lang=EN', 'https://site.test/child.xml?lang=en', 'https://other.test/child.xml', 'https://site.test/child.xml#section' ];
	$fixture['responses'][ $root ] = $response( $index( array_merge( [ $root ], $children, $children ) ) );
	$fetch->parse_sitemap( $root );
	$queued = array_map( static function ( $action ) { return $action['args'][0]; }, array_values( $fixture['actions'] ) );
	$check( $queued === $children, 'duplicates are removed without folding case, queries, external children or fragments' );
	$fixture['actions'] = [];
	$fixture['requests'] = [];
	$fixture['excluded'] = [ 'https://site.test/private' ];
	$fixture['responses'][ $children[0] ] = $response( '<urlset><url><loc>https://site.test/page</loc></url><url><loc>https://site.test/private</loc></url><url><loc>https://other.test/page</loc></url></urlset>' );
	$fetch->parse_sitemap( $children[0] );
	$check( $query->urls === [ 'https://site.test/page' ], 'legitimate child pages retain existing site/filter admission' );
	$check( [] === $fixture['actions'], 'ordinary URL set schedules no sitemap child jobs' );
	$fixture['paused'] = true;
	$fetch->parse_sitemap( $root );
	$check( $fixture['requests'] === [ $children[0] ], 'paused preload does not fetch' );
	$fixture['paused'] = false;
	foreach ( [ $response( $index( [ $children[0] ] ), 500 ), $response( '' ), $response( '<broken' ) ] as $failure ) {
		$fixture['responses'][ $root ] = $failure;
		$fetch->parse_sitemap( $root );
		$check( [] === $fixture['actions'] && $query->urls === [ 'https://site.test/page' ], 'HTTP, empty and malformed responses remain inert' );
	}
	echo "PASS: $checks sitemap traversal assertions; actual parser/fetch/queue owners, modeled WordPress boundaries, no HTTP.\n";
}
