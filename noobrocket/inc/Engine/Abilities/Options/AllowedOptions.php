<?php
declare(strict_types=1);

namespace NoobRocket\Engine\Abilities\Options;

class AllowedOptions {
	/**
	 * Returns the list of NoobRocket option keys accessible via MCP.
	 *
	 * Advanced users may extend or restrict this list with the noobrocket_mcp_options_allowlist filter.
	 *
	 * @return string[]
	 */
	public function get(): array {
		$allowlist = [
			// Cache.
			'cache_logged_user',
			'cache_webp',
			'cache_stale_refresh',
			'cache_reject_uri',
			'cache_reject_cookies',
			'cache_reject_ua',
			'cache_query_strings',
			'cache_purge_pages',
			'purge_cron_interval',
			'purge_cron_unit',
			// CSS.
			'minify_css',
			'minify_google_fonts',
			'exclude_css',
			'local_css_mode',
			'local_css_safelist',
			// JS.
			'minify_js',
			'exclude_js',
			'exclude_inline_js',
			'defer_all_js',
			'exclude_defer_js',
			'delay_js',
			'delay_js_exclusions',
			// Media.
			'image_delivery',
			'lazyload',
			'lazyload_iframes',
			'lazyload_youtube',
			'lazyload_css_bg_img',
			'exclude_lazyload',
			'image_dimensions',
			// Fonts.
			'host_fonts_locally',
			'auto_preload_fonts',
			// Preload.
			'manual_preload',
			'preload_links',
			'prerender_links',
			'prerender_excluded_uri',
			'preload_fonts',
			'preload_excluded_uri',
			'dns_prefetch',
			// Database.
			'database_revisions',
			'database_auto_drafts',
			'database_trashed_posts',
			'database_spam_comments',
			'database_trashed_comments',
			'database_all_transients',
			'database_optimize_tables',
			'schedule_automatic_cleanup',
			'automatic_cleanup_frequency',
			// CDN.
			'cdn',
			'cdn_cnames',
			'cdn_zone',
			'cdn_reject_files',
			'cdn_reject_pages',
			'cdn_state',
			// Cloudflare.
			'do_cloudflare',
			'cloudflare_devmode',
			'cloudflare_protocol_rewrite',
			'cloudflare_auto_settings',
			// Heartbeat.
			'control_heartbeat',
			'heartbeat_site_behavior',
			'heartbeat_admin_behavior',
			'heartbeat_editor_behavior',
			// Add-ons / integrations.
			'varnish_auto_purge',
			'sucury_waf_cache_sync',
			// Misc.
			'emoji',
			'remove_dashicons',
			'remove_embed_assets',
			'remove_block_library',
		];

		/**
		 * Filters the list of NoobRocket option keys that MCP abilities may read or write.
		 *
		 * Advanced users can add custom or third-party option keys to expose them
		 * through the get-options and set-options MCP abilities, or remove keys to
		 * restrict AI access to specific settings.
		 *
		 * @param string[] $allowlist Flat array of option key strings.
		 * @return string[]
		 */
		return wpm_apply_filters_typed( 'array', 'noobrocket_mcp_options_allowlist', $allowlist );
	}
}
