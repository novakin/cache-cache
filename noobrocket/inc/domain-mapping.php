<?php

defined( 'ABSPATH' ) || exit;

/**
 * Used to get compatibility between multidomain and get_noobrocket_parse_url()
 *
 * @since 2.2
 *
 * @param string $url URL to modify.
 * @return string Modified URL
 */
function noobrocket_parse_url_domain_mapping( $url ) {
	$original_siteurl_host       = noobrocket_extract_url_component( get_original_url( 'siteurl' ), PHP_URL_HOST );
	$domain_mapping_siteurl_host = noobrocket_extract_url_component( domain_mapping_siteurl( false ), PHP_URL_HOST );

	if ( false === strpos( $domain_mapping_siteurl_host, $original_siteurl_host ) ) {
		$url['host'] = str_replace( $original_siteurl_host, $domain_mapping_siteurl_host, $url['host'] );
	}

	return $url;
}
add_filter( 'noobrocket_parse_url', 'noobrocket_parse_url_domain_mapping' );

/**
 * Used to get compatibility between multidomain and noobrocket_clean_files() & noobrocket_clean_domain()
 *
 * @since 2.6.5 Add compatibility with noobrocket_clean_domain()
 * @since 2.2
 */
if ( function_exists( 'domain_mapping_post_content' ) ) :
	add_filter( 'noobrocket_clean_files', 'domain_mapping_post_content' );
	add_filter( 'noobrocket_clean_domain_urls', 'domain_mapping_post_content' );
	add_filter( 'noobrocket_post_purge_urls', 'domain_mapping_post_content' );
endif;

/**
 * Used to get compatibility between multidomain and noobrocket_clean_home()
 *
 * @since 2.6.5
 *
 * @param string $root Path to the cache for the host.
 * @param string $host Host value.
 * @param string $path Unused.
 * @return $root Path to the cache
 */
function noobrocket_clean_home_root_for_domain_mapping_siteurl( $root, $host, $path ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	$original_siteurl_host       = noobrocket_extract_url_component( get_original_url( 'siteurl' ), PHP_URL_HOST );
	$domain_mapping_siteurl_host = noobrocket_extract_url_component( domain_mapping_siteurl( false ), PHP_URL_HOST );

	if ( $original_siteurl_host !== $domain_mapping_siteurl_host ) {
		$root = NOOBROCKET_CACHE_PATH . $host . '*';
	}

	return $root;
}
add_filter( 'noobrocket_clean_home_root', 'noobrocket_clean_home_root_for_domain_mapping_siteurl', 10, 3 );
