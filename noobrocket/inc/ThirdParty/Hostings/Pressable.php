<?php
namespace NoobRocket\ThirdParty\Hostings;

/**
 * Subscriber for compatibility with Pressable hosting
 *
 * @since 3.3
 */
class Pressable extends AbstractNoCacheHost {
	/**
	 * Return an array of events that this subscriber wants to listen to.
	 *
	 * @since  3.3
	 *
	 * @return array
	 */
	public static function get_subscribed_events() {
		return [
			'do_noobrocket_generate_caching_files'    => [ 'return_false', PHP_INT_MAX ],
			'noobrocket_display_varnish_options_tab'  => 'return_false',
			'noobrocket_cache_mandatory_cookies'      => [ 'return_empty_array', PHP_INT_MAX ],
			'noobrocket_after_clean_domain'           => 'purge_pressable_cache',
			'noobrocket_url_to_path'                  => 'fix_wp_includes_path',
			'noobrocket_set_wp_cache_constant'        => 'return_false',
			'noobrocket_generate_advanced_cache_file' => 'return_false',
			'noobrocket_cdn_cnames'                   => [ 'add_pressable_cdn_cname', 1 ],
		];
	}

	/**
	 * Purge Pressable cache
	 *
	 * @since 3.3
	 *
	 * @return void
	 */
	public function purge_pressable_cache() {
		wp_cache_flush();
	}

	/**
	 * Modify wp-includes absolute path to be able to optimize assets in this directory on Pressable
	 *
	 * @since 3.3
	 *
	 * @param string $file Absolute path to the file.
	 * @return string
	 */
	public function fix_wp_includes_path( $file ) {
		return preg_replace( '#^(.+)(wp-includes(?:.+))$#is', ABSPATH . '$2', $file );
	}

	/**
	 * Add Pressable CDN cname to NoobRocket list to recognize assets as internal ones.
	 *
	 * @since 3.3
	 *
	 * @param array $hosts Array of CDN URLs.
	 * @return array
	 */
	public function add_pressable_cdn_cname( $hosts ) {
		if ( ! noobrocket_get_constant( 'WP_STACK_CDN_DOMAIN' ) ) {
			return $hosts;
		}

		$hosts[] = WP_STACK_CDN_DOMAIN;

		return $hosts;
	}
}
