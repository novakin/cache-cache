<?php
declare(strict_types=1);

namespace NoobRocket\ThirdParty\Plugins;

use NoobRocket\Dependencies\League\Container\Argument\Literal\StringArgument;
use NoobRocket\Subscriber\Third_Party\Plugins\Images\Webp\Imagify_Subscriber;
use NoobRocket\Subscriber\Third_Party\Plugins\Images\Webp\Optimus_Subscriber;
use NoobRocket\Subscriber\Third_Party\Plugins\Mobile_Subscriber;
use NoobRocket\ThirdParty\Plugins\Ads\Adthrive;
use NoobRocket\ThirdParty\Plugins\CDN\Cloudflare;
use NoobRocket\ThirdParty\Plugins\ContactForm7;
use NoobRocket\ThirdParty\Plugins\ConvertPlug;
use NoobRocket\ThirdParty\Plugins\Cookie\Termly;
use NoobRocket\ThirdParty\Plugins\Ecommerce\BigCommerce;
use NoobRocket\ThirdParty\Plugins\Ecommerce\WooCommerceSubscriber;
use NoobRocket\ThirdParty\Plugins\EWWW;
use NoobRocket\ThirdParty\Plugins\I18n\TranslatePress;
use NoobRocket\ThirdParty\Plugins\I18n\Weglot;
use NoobRocket\ThirdParty\Plugins\I18n\WPML;
use NoobRocket\ThirdParty\Plugins\InlineRelatedPosts;
use NoobRocket\ThirdParty\Plugins\Jetpack;
use NoobRocket\ThirdParty\Plugins\NGG;
use NoobRocket\ThirdParty\Plugins\Optimization\AMP;
use NoobRocket\ThirdParty\Plugins\Optimization\Autoptimize;
use NoobRocket\ThirdParty\Plugins\Optimization\Perfmatters;
use NoobRocket\ThirdParty\Plugins\Optimization\RapidLoad;
use NoobRocket\ThirdParty\Plugins\Optimization\RocketLazyLoad;
use NoobRocket\ThirdParty\Plugins\Optimization\WPMeteor;
use NoobRocket\ThirdParty\Plugins\Optimole;
use NoobRocket\ThirdParty\Plugins\PageBuilder\BeaverBuilder;
use NoobRocket\ThirdParty\Plugins\PageBuilder\Elementor;
use NoobRocket\ThirdParty\Plugins\PDFEmbedder;
use NoobRocket\ThirdParty\Plugins\PWA;
use NoobRocket\ThirdParty\Plugins\RevolutionSlider;
use NoobRocket\ThirdParty\Plugins\Security\WordFenceCompatibility;
use NoobRocket\ThirdParty\Plugins\SEO\AllInOneSEOPack;
use NoobRocket\ThirdParty\Plugins\SEO\RankMathSEO;
use NoobRocket\ThirdParty\Plugins\SEO\SEOPress;
use NoobRocket\ThirdParty\Plugins\SEO\TheSEOFramework;
use NoobRocket\ThirdParty\Plugins\SEO\Yoast;
use NoobRocket\ThirdParty\Plugins\ShortPixel;
use NoobRocket\ThirdParty\Plugins\SimpleCustomCss;
use NoobRocket\ThirdParty\Plugins\Smush;
use NoobRocket\ThirdParty\Plugins\SyntaxHighlighter;
use NoobRocket\ThirdParty\Plugins\TheEventsCalendar;
use NoobRocket\ThirdParty\Plugins\ThirstyAffiliates;
use NoobRocket\ThirdParty\Plugins\UnlimitedElements;
use NoobRocket\ThirdParty\Plugins\WPGeotargeting;

/**
 * Authoritative id => class registry and constructor argument builder for the
 * resolver-gate-able plugin compatibility subscribers.
 *
 * Single source of truth for the factory-owned plugin ids. `get_registry()` is
 * a pure literal map, safe to enumerate without side effects; `get_arguments()`
 * builds the runtime args for a single id only, at register time.
 */
class SubscriberFactory {
	/**
	 * Authoritative id => FQCN map for resolver-gate-able plugin compat subscribers.
	 *
	 * @return array<string,string>
	 */
	public function get_registry(): array {
		return [
			'mobile_subscriber'            => Mobile_Subscriber::class,
			// syntaxhighlighter_subscriber is ordered before elementor_subscriber: both hook
			// `noobrocket_exclude_js` at the default priority, and this order is relied upon.
			'syntaxhighlighter_subscriber' => SyntaxHighlighter::class,
			'elementor_subscriber'         => Elementor::class,
			'woocommerce_subscriber'       => WooCommerceSubscriber::class,
			'ngg_subscriber'               => NGG::class,
			'smush_subscriber'             => Smush::class,
			'imagify_webp_subscriber'      => Imagify_Subscriber::class,
			'shortpixel_webp_subscriber'   => ShortPixel::class,
			'ewww_webp_subscriber'         => EWWW::class,
			'optimus_webp_subscriber'      => Optimus_Subscriber::class,
			'bigcommerce_subscriber'       => BigCommerce::class,
			'beaverbuilder_subscriber'     => BeaverBuilder::class,
			'amp_subscriber'               => AMP::class,
			'simple_custom_css'            => SimpleCustomCss::class,
			'pdfembedder'                  => PDFEmbedder::class,
			'adthrive'                     => Adthrive::class,
			'autoptimize'                  => Autoptimize::class,
			'wp-meteor'                    => WPMeteor::class,
			'revolution_slider_subscriber' => RevolutionSlider::class,
			'wordfence_subscriber'         => WordFenceCompatibility::class,
			'thirstyaffiliates'            => ThirstyAffiliates::class,
			'pwa'                          => PWA::class,
			'yoast_seo'                    => Yoast::class,
			'convertplug'                  => ConvertPlug::class,
			'unlimited_elements'           => UnlimitedElements::class,
			'inline_related_posts'         => InlineRelatedPosts::class,
			'wpml'                         => WPML::class,
			'cloudflare_plugin_subscriber' => Cloudflare::class,
			'jetpack'                      => Jetpack::class,
			'rank_math_seo'                => RankMathSEO::class,
			'all_in_one_seo_pack'          => AllInOneSEOPack::class,
			'seopress'                     => SEOPress::class,
			'the_seo_framework'            => TheSEOFramework::class,
			'noobrocket_lazy_load'             => RocketLazyLoad::class,
			'the_events_calendar'          => TheEventsCalendar::class,
			'perfmatters'                  => Perfmatters::class,
			'rapidload'                    => RapidLoad::class,
			'weglot'                       => Weglot::class,
			'translatepress'               => TranslatePress::class,
			'wpgeotargeting'               => WPGeotargeting::class,
			'contactform7'                 => ContactForm7::class,
			'termly_subscriber'            => Termly::class,
			'optimole_subscriber'          => Optimole::class,
		];
	}

	/**
	 * Constructor arguments for a given id, verbatim from the current register().
	 *
	 * @param string $id Plugin subscriber id.
	 *
	 * @return array
	 */
	public function get_arguments( string $id ): array {
		// Container-id arguments only: plain lazy references, no runtime construction.
		$lazy_arguments = [
			'woocommerce_subscriber'       => [ 'delay_js_html' ],
			'smush_subscriber'             => [ 'options_api', 'options' ],
			'imagify_webp_subscriber'      => [ 'options' ],
			'shortpixel_webp_subscriber'   => [ 'options' ],
			'ewww_webp_subscriber'         => [ 'options' ],
			'autoptimize'                  => [ 'options' ],
			'jetpack'                      => [ 'options' ],
			'all_in_one_seo_pack'          => [ 'options' ],
			'seopress'                     => [ 'options' ],
			'the_seo_framework'            => [ 'options' ],
			'amp_subscriber'               => [ 'cdn_subscriber' ],
			'cloudflare_plugin_subscriber' => [ 'options', 'options_api', 'beacon', 'cloudflare_plugin_facade' ],
		];

		if ( isset( $lazy_arguments[ $id ] ) ) {
			return $lazy_arguments[ $id ];
		}

		// Ids whose arguments must be constructed at register time, built only when requested.
		if ( 'elementor_subscriber' === $id ) {
			return [ 'options', noobrocket_direct_filesystem(), 'delay_js_html' ];
		}

		if ( 'simple_custom_css' === $id ) {
			return [
				new StringArgument( noobrocket_get_constant( 'NOOBROCKET_CACHE_BUSTING_PATH', '' ) ),
				new StringArgument( noobrocket_get_constant( 'NOOBROCKET_CACHE_BUSTING_URL', '' ) ),
			];
		}

		return [];
	}
}
