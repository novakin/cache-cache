<?php

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'get_noobrocket_footprint' ) ) :
	/**
	 * Get NoobRocket footprint
	 *
	 * @deprecated 3.3
	 * @since 3.0.5 White label footprint if NOOBROCKET_WHITE_LABEL_FOOTPRINT is defined.
	 * @since 2.0
	 *
	 * @param bool $debug (default: true) If true, adds the date of generation cache file.
	 * @return string The footprint that will be printed
	 */
	function get_noobrocket_footprint( $debug = true ) {
		_deprecated_function( __FUNCTION__ . '()', '3.3', '\NoobRocket\Buffer\Cache->get_noobrocket_footprint()' );
		$footprint = defined( 'NOOBROCKET_WHITE_LABEL_FOOTPRINT' ) ?
						"\n" . '<!-- Cached for great performance' :
						"\n" . '<!-- Performance optimized by ' . NOOBROCKET_PLUGIN_NAME;
		if ( $debug ) {
			$footprint .= ' - Debug: cached@' . time();
		}
		$footprint .= ' -->';
		return $footprint;
	}
endif;

if ( ! function_exists( 'noobrocket_lazyload_script' ) ) :
/**
 * Add lazyload options to the footer
 *
 * @deprecated 3.3
 * @since 2.11 load options in the footer and add filter for the threshold
 * @since 1.3.5 It's possible to exclude LazyLoad process by used do_noobrocket_lazyload filter
 * @since 1.1.0 This code is insert in head with inline script for more performance
 * @since 1.0
 */
function noobrocket_lazyload_script() {
	_deprecated_function( __FUNCTION__ . '()', '3.3', '\NoobRocketLazyload\Assets::insertLazyloadScript()' );
	if ( ( defined( 'DONOTNOOBROCKETOPTIMIZE' ) && DONOTNOOBROCKETOPTIMIZE ) ) {
		return;
	}

	if ( ( ! get_noobrocket_option( 'lazyload' ) && ! get_noobrocket_option( 'lazyload_iframes' ) ) || ( ! apply_filters( 'do_noobrocket_lazyload', true ) && ! apply_filters( 'do_noobrocket_lazyload_iframes', true ) ) ) {
		return;
	}

	$suffix   = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
	$elements = [];

	if ( get_noobrocket_option( 'lazyload' ) ) {
		$elements[] = 'img';
	}

	if ( get_noobrocket_option( 'lazyload_iframes' ) ) {
		$elements[] = 'iframe';
	}

	/**
	 * Filters the threshold at which lazyload is triggered
	 *
	 * @since 2.11
	 * @author Remy Perona
	 *
	 * @param int $threshold Threshold value.
	 */
	$threshold = apply_filters( 'noobrocket_lazyload_threshold', 300 );

	echo '<script>(function(w, d){
	var b = d.getElementsByTagName("body")[0];
	var s = d.createElement("script"); s.async = true;
	s.src = !("IntersectionObserver" in w) ? "' . get_noobrocket_cdn_url( NOOBROCKET_FRONT_JS_URL, array( 'all', 'css_and_js', 'js' ) ) . 'lazyload-8.15.2' . $suffix . '.js" : "' . get_noobrocket_cdn_url( NOOBROCKET_FRONT_JS_URL, array( 'all', 'css_and_js', 'js' ) ) . 'lazyload-10.17' . $suffix . '.js";
	w.lazyLoadOptions = {
		elements_selector: "' . esc_attr( implode( ',', $elements ) ) . '",
		data_src: "lazy-src",
		data_srcset: "lazy-srcset",
		data_sizes: "lazy-sizes",
		skip_invisible: false,
		class_loading: "lazyloading",
		class_loaded: "lazyloaded",
		threshold: ' . esc_attr( $threshold ) . ',
		callback_load: function(element) {
			if ( element.tagName === "IFRAME" && element.dataset.noobrocketLazyload == "fitvidscompatible" ) {
				if (element.classList.contains("lazyloaded") ) {
					if (typeof window.jQuery != "undefined") {
						if (jQuery.fn.fitVids) {
							jQuery(element).parent().fitVids();
						}
					}
				}
			}
		}
	}; // Your options here. See "recipes" for more information about async.
	b.appendChild(s);
}(window, document));

// Listen to the Initialized event
window.addEventListener(\'LazyLoad::Initialized\', function (e) {
    // Get the instance and puts it in the lazyLoadInstance variable
	var lazyLoadInstance = e.detail.instance;

	var observer = new MutationObserver(function(mutations) {
		mutations.forEach(function(mutation) {
			lazyLoadInstance.update();
		} );
	} );

	var b      = document.getElementsByTagName("body")[0];
	var config = { childList: true, subtree: true };

	observer.observe(b, config);
}, false);
</script>';

	if ( get_noobrocket_option( 'lazyload_youtube' ) ) {
		/**
		 * Filters the resolution of the YouTube thumbnail
		 *
		 * @since 2.11.5
		 * @author Arun Basil Lal
		 *
		 * @param string $thumbnail_resolution The resolution of the thumbnail. Accepted values: default, mqdefault, sddefault, hqdefault, maxresdefault
		 */
		$thumbnail_resolution = apply_filters( 'noobrocket_youtube_thumbnail_resolution', 'hqdefault' );

		echo <<<HTML
		<script>function lazyLoadThumb(e){var t='<img src="https://i.ytimg.com/vi/ID/$thumbnail_resolution.jpg">',a='<div class="play"></div>';return t.replace("ID",e)+a}function lazyLoadYoutubeIframe(){var e=document.createElement("iframe"),t="https://www.youtube.com/embed/ID?autoplay=1";t+=0===this.dataset.query.length?'':'&'+this.dataset.query;e.setAttribute("src",t.replace("ID",this.dataset.id)),e.setAttribute("frameborder","0"),e.setAttribute("allowfullscreen","1"),this.parentNode.replaceChild(e,this)}document.addEventListener("DOMContentLoaded",function(){var e,t,a=document.getElementsByClassName("rll-youtube-player");for(t=0;t<a.length;t++)e=document.createElement("div"),e.setAttribute("data-id",a[t].dataset.id),e.setAttribute("data-query", a[t].dataset.query),e.innerHTML=lazyLoadThumb(a[t].dataset.id),e.onclick=lazyLoadYoutubeIframe,a[t].appendChild(e)});</script>
HTML;
	}
}
endif;

if ( ! function_exists( 'noobrocket_lazyload_enqueue' ) ) :
/**
 * Enqueue the CSS code for Youtube lazyload styling
 *
 * @deprecated 3.3
 * @since 2.11
 * @author Remy Perona
 */
function noobrocket_lazyload_enqueue() {
	_deprecated_function( __FUNCTION__ . '()', '3.3', '\NoobRocketLazyload\Assets::insertYoutubeThumbnailCSS()' );
	if ( ( defined( 'DONOTNOOBROCKETOPTIMIZE' ) && DONOTNOOBROCKETOPTIMIZE ) ) {
		return;
	}

	if ( ( ! get_noobrocket_option( 'lazyload_iframes' ) ) || ( ! apply_filters( 'do_noobrocket_lazyload', true ) && ! apply_filters( 'do_noobrocket_lazyload_iframes', true ) ) ) {
		return;
	}

	if ( get_noobrocket_option( 'lazyload_youtube' ) ) {
		$css = '.rll-youtube-player{position:relative;padding-bottom:56.23%;height:0;overflow:hidden;max-width:100%;background:#000;margin:5px}.rll-youtube-player iframe{position:absolute;top:0;left:0;width:100%;height:100%;z-index:100;background:0 0}.rll-youtube-player img{bottom:0;display:block;left:0;margin:auto;max-width:100%;width:100%;position:absolute;right:0;top:0;border:none;height:auto;cursor:pointer;-webkit-transition:.4s all;-moz-transition:.4s all;transition:.4s all}.rll-youtube-player img:hover{-webkit-filter:brightness(75%)}.rll-youtube-player .play{height:72px;width:72px;left:50%;top:50%;margin-left:-36px;margin-top:-36px;position:absolute;background:url(' . NOOBROCKET_FRONT_URL . 'img/youtube.png) no-repeat;cursor:pointer}';

		wp_register_style( 'noobrocket-lazyload', false );
		wp_enqueue_style( 'noobrocket-lazyload' );
		wp_add_inline_style( 'noobrocket-lazyload', $css );
	}
}
endif;

if ( ! function_exists( 'noobrocket_lazyload_images' ) ) :
/**
 * Replace Gravatar, thumbnails, images in post content and in widget text by LazyLoad
 *
 * @deprecated 3.3
 * @since 2.6 Add the get_image_tag filter
 * @since 2.2 Better regex pattern in a replace_callback
 * @since 1.3.5 It's possible to exclude LazyLoad process by used do_noobrocket_lazyload filter
 * @since 1.2.0 It's possible to not lazyload an image with data-no-lazy attribute
 * @since 1.1.0 Don't lazyload if the thumbnail has already been run through previously
 * @since 1.0.1 Add priority of hooks at maximum later with PHP_INT_MAX
 * @since 1.0
 *
 * @param string $html HTML content.
 * @return string Modified HTML content
 */
function noobrocket_lazyload_images( $html ) {
	_deprecated_function( __FUNCTION__ . '()', '3.3', '\NoobRocketLazyload\Images::lazyloadImages()' );
	// Don't LazyLoad if process is stopped for these reasons.
	if ( ! get_noobrocket_option( 'lazyload' ) || ! apply_filters( 'do_noobrocket_lazyload', true ) || is_feed() || is_preview() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'DONOTNOOBROCKETOPTIMIZE' ) && DONOTNOOBROCKETOPTIMIZE ) || empty( $html ) || ( defined( 'DONOTLAZYLOAD' ) && DONOTLAZYLOAD ) || wp_script_is( 'twentytwenty-twentytwenty', 'enqueued' ) ) {
		return $html;
	}

	$html = preg_replace_callback( '#<img([^>]*) src=("(?:[^"]+)"|\'(?:[^\']+)\'|(?:[^ >]+))([^>]*)>#', 'noobrocket_lazyload_replace_callback', $html );

	return $html;
}
endif;

if ( ! function_exists( 'noobrocket_lazyload_replace_callback' ) ) :
/**
 * Used to check if we have to LazyLoad this or not
 *
 * @deprecated 3.3
 * @since 2.5.5  Don't apply LazyLoad on images from WP Retina x2
 * @since 2.5    Don't apply LazyLoad on all images from LayerSlider
 * @since 2.4.2  Don't apply LazyLoad on all images from Media Grid
 * @since 2.3.11 Don't apply LazyLoad on all images from Timthumb
 * @since 2.3.10 Don't apply LazyLoad on all images from Revolution Slider & Justified Image Grid
 * @since 2.3.8  Don't apply LazyLoad on captcha from Really Simple CAPTCHA
 * @since 2.2
 *
 * @param array $matches Images matching the regex.
 * @return string Modified HTML content
 */
function noobrocket_lazyload_replace_callback( $matches ) {
	_deprecated_function( __FUNCTION__ . '()', '3.3', '\NoobRocketLazyload\Images::lazyloadImages()' );
	// Don't apply LazyLoad on images from WP Retina x2.
	if ( function_exists( 'wr2x_picture_rewrite' ) ) {
		if ( wr2x_get_retina( trailingslashit( ABSPATH ) . wr2x_get_pathinfo_from_image_src( trim( $matches[2], '"' ) ) ) ) {
			return $matches[0];
		}
	}

	/**
	 * Filters the attributes used to prevent lazylad from being applied
	 *
	 * @since 2.11
	 * @author Remy Perona
	 *
	 * @param array $excluded_attributes An array of excluded attributes.
	 */
	$excluded_attributes = apply_filters(
		'noobrocket_lazyload_excluded_attributes', array(
			'data-src=',
			'data-no-lazy=',
			'data-lazy-original=',
			'data-lazy-src=',
			'data-lazysrc=',
			'data-lazyload=',
			'data-bgposition=',
			'data-envira-src=',
			'fullurl=',
			'lazy-slider-img=',
			'data-srcset=',
			'class="ls-l',
			'class="ls-bg',
		)
	);

	/**
	 * Filters the src used to prevent lazylad from being applied
	 *
	 * @since 2.11
	 * @author Remy Perona
	 *
	 * @param array $excluded_src An array of excluded src.
	 */
	$excluded_src = apply_filters(
		'noobrocket_lazyload_excluded_src', array(
			'/wpcf7_captcha/',
			'timthumb.php?src',
		)
	);

	if ( noobrocket_is_excluded_lazyload( $matches[1] . $matches[3], $excluded_attributes ) || noobrocket_is_excluded_lazyload( $matches[2], $excluded_src ) ) {
		return $matches[0];
	}

	/**
	 * Filter the LazyLoad placeholder on src attribute
	 *
	 * @since 1.1
	 *
	 * @param string $placeholder Placeholder that will be printed.
	 */
	$placeholder = apply_filters( 'noobrocket_lazyload_placeholder', 'data:image/gif;base64,R0lGODdhAQABAPAAAP///wAAACwAAAAAAQABAEACAkQBADs=' );

	$html = sprintf( '<img%1$s src="%4$s" data-lazy-src=%2$s%3$s>', $matches[1], $matches[2], $matches[3], $placeholder );

	$html_noscript = sprintf( '<noscript><img%1$s src=%2$s%3$s></noscript>', $matches[1], $matches[2], $matches[3] );

	/**
	 * Filter the LazyLoad HTML output
	 *
	 * @since 1.0.2
	 *
	 * @param array $html Output that will be printed
	 */
	$html = apply_filters( 'noobrocket_lazyload_html', $html, true );

	return $html . $html_noscript;
}
endif;

if ( ! function_exists( 'noobrocket_is_excluded_lazyload' ) ) :
/**
 * Determine if the current image should be excluded from lazyload
 *
 * @deprecated 3.3
 * @since 1.1
 * @author Remy Perona
 *
 * @param string $string String to search.
 * @param array  $excluded_values Array of excluded values to search in the string.
 * @return bool True if one of the excluded values was found, false otherwise
 */
function noobrocket_is_excluded_lazyload( $string, $excluded_values ) {
	_deprecated_function( __FUNCTION__ . '()', '3.3', '\NoobRocketLazyload\Images::isExcluded()' );
	foreach ( $excluded_values as $excluded_value ) {
		if ( strpos( $string, $excluded_value ) !== false ) {
			return true;
		}
	}

	return false;
}
endif;

if ( ! function_exists( 'noobrocket_lazyload_smilies' ) ) :
/**
 * Replace WordPress smilies by Lazy Load
 *
 * @since 3.3
 * @since 2.0   New system for replace smilies by Lazy Load
 * @since 1.3.5 It's possible to exclude LazyLoad process by used do_noobrocket_lazyload filter
 * @since 1.1.0 Don't lazy-load if the thumbnail has already been run through previously
 * @since 1.0.1 Add priority of hooks at maximum later with PHP_INT_MAX
 * @since 1.0
 */
function noobrocket_lazyload_smilies() {
	_deprecated_function( __FUNCTION__ . '()', '3.3', '\NoobRocket\Subscriber\Optimization\Lazyload_Subscriber::lazyload_smilies()' );
	if ( ! get_noobrocket_option( 'lazyload' ) || ! apply_filters( 'do_noobrocket_lazyload', true, 'smilies' ) || ( defined( 'DONOTNOOBROCKETOPTIMIZE' ) && DONOTNOOBROCKETOPTIMIZE ) || ( defined( 'DONOTLAZYLOAD' ) && DONOTLAZYLOAD ) ) {
		return;
	}

	remove_filter( 'the_content', 'convert_smilies' );
	remove_filter( 'the_excerpt', 'convert_smilies' );
	remove_filter( 'comment_text', 'convert_smilies', 20 );

	add_filter( 'the_content', 'noobrocket_convert_smilies' );
	add_filter( 'the_excerpt', 'noobrocket_convert_smilies' );
	add_filter( 'comment_text', 'noobrocket_convert_smilies', 20 );
}
endif;

if ( ! function_exists( 'noobrocket_convert_smilies' ) ) :
/**
 * Convert text equivalent of smilies to images.
 *
 * @source convert_smilies() in /wp-includes/formattings.php
 * @since 2.0
 * @deprecated 3.3
 * @param string $text Text to process.
 * @return string Modified text
 */
function noobrocket_convert_smilies( $text ) {
	_deprecated_function( __FUNCTION__ . '()', '3.3', '\NoobRocketLazyload\Image::convertSmilies()' );
	global $wp_smiliessearch;

	if ( ! get_option( 'use_smilies' ) || empty( $wp_smiliessearch ) ) {
		return $text;
	}

	$output = '';
	// HTML loop taken from texturize function, could possible be consolidated.
	$textarr = preg_split( '/(<.*>)/U', $text, -1, PREG_SPLIT_DELIM_CAPTURE ); // capture the tags as well as in between.
	$stop    = count( $textarr );// loop stuff.

	// Ignore processing of specific tags.
	$tags_to_ignore       = 'code|pre|style|script|textarea';
	$ignore_block_element = '';

	for ( $i = 0; $i < $stop; $i++ ) {
		$content = $textarr[ $i ];

		// If we're in an ignore block, wait until we find its closing tag.
		if ( '' === $ignore_block_element && preg_match( '/^<(' . $tags_to_ignore . ')>/', $content, $matches ) ) {
			$ignore_block_element = $matches[1];
		}

		// If it's not a tag and not in ignore block.
		if ( '' === $ignore_block_element && strlen( $content ) > 0 && '<' !== $content[0] ) {
			$content = preg_replace_callback( $wp_smiliessearch, 'noobrocket_translate_smiley', $content );
		}

		// did we exit ignore block.
		if ( '' !== $ignore_block_element && '</' . $ignore_block_element . '>' === $content ) {
			$ignore_block_element = '';
		}

		$output .= $content;
	}

	return $output;
}
endif;

if ( ! function_exists( 'noobrocket_translate_smiley' ) ) :
/**
 * Convert one smiley code to the icon graphic file equivalent.
 *
 * @source translate_smiley() in /wp-includes/formattings.php
 * @since 2.0
 * @deprecated 3.3
 *
 * @param array $matches An array of matching content.
 * @return string HTML code for smiley
 */
function noobrocket_translate_smiley( $matches ) {
	_deprecated_function( __FUNCTION__ . '()', '3.3', '\NoobRocketLazyload\Image::translateSmiley()' );
	global $wpsmiliestrans;

	if ( count( $matches ) === 0 ) {
		return '';
	}

	$smiley = trim( reset( $matches ) );
	$img    = $wpsmiliestrans[ $smiley ];

	$matches    = array();
	$ext        = preg_match( '/\.([^.]+)$/', $img, $matches ) ? strtolower( $matches[1] ) : false;
	$image_exts = array( 'jpg', 'jpeg', 'jpe', 'gif', 'png' );

	// Don't convert smilies that aren't images - they're probably emoji.
	if ( ! in_array( $ext, $image_exts, true ) ) {
		return $img;
	}

	/**
	 * Filter the Smiley image URL before it's used in the image element.
	 *
	 * @since 2.9.0
	 *
	 * @param string $smiley_url URL for the smiley image.
	 * @param string $img        Filename for the smiley image.
	 * @param string $site_url   Site URL, as returned by site_url().
	 */
	$src_url = apply_filters( 'smilies_src', includes_url( "images/smilies/$img" ), $img, site_url() );

	// Don't LazyLoad if process is stopped for these reasons.
	if ( is_feed() || is_preview() ) {
		return sprintf( ' <img src="%s" alt="%s" class="wp-smiley" /> ', esc_url( $src_url ), esc_attr( $smiley ) );
	}

	/** This filter is documented in inc/front/lazyload.php */
	$placeholder = apply_filters( 'noobrocket_lazyload_placeholder', 'data:image/gif;base64,R0lGODdhAQABAPAAAP///wAAACwAAAAAAQABAEACAkQBADs=' );

	return sprintf( ' <img src="%s" data-lazy-src="%s" alt="%s" class="wp-smiley" /> ', $placeholder, esc_url( $src_url ), esc_attr( $smiley ) );
}
endif;

if ( ! function_exists( 'noobrocket_lazyload_iframes' ) ) :
/**
 * Replace iframes by LazyLoad
 *
 * @deprecated 3.3
 * @since 2.6
 *
 * @param string $html HTML content.
 * @return string Modified HTML content
 */
function noobrocket_lazyload_iframes( $html ) {
	_deprecated_function( __FUNCTION__ . '()', '3.3', '\NoobRocketLazyload\Iframe::lazyloadIframes()' );
	// Don't LazyLoad if process is stopped for these reasons.
	if ( ! get_noobrocket_option( 'lazyload_iframes' ) || ! apply_filters( 'do_noobrocket_lazyload_iframes', true ) || is_feed() || is_preview() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || empty( $html ) || ( defined( 'DONOTNOOBROCKETOPTIMIZE' ) && DONOTNOOBROCKETOPTIMIZE ) || ( defined( 'DONOTLAZYLOAD' ) && DONOTLAZYLOAD ) ) {
		return $html;
	}

	preg_match_all( '@<iframe(?<atts>\s.+)>.*</iframe>@iUs', $html, $matches, PREG_SET_ORDER );

	if ( empty( $matches ) ) {
		return $html;
	}

	foreach ( $matches as $iframe ) {
		// Don't mess with the Gravity Forms ajax iframe.
		if ( strpos( $iframe[0], 'gform_ajax_frame' ) ) {
			continue;
		}

		// Don't lazyload if iframe has data-no-lazy attribute.
		if ( strpos( $iframe[0], 'data-no-lazy=' ) ) {
			continue;
		}

		// Don't lazyload if iframe is google recaptcha fallback.
		if ( strpos( $iframe[0], 'recaptcha/api/fallback' ) ) {
			continue;
		}

		// Given the previous regex pattern, $iframe['atts'] starts with a whitespace character.
		if ( ! preg_match( '@\ssrc\s*=\s*(\'|")(?<src>.*)\1@iUs', $iframe['atts'], $atts ) ) {
			continue;
		}

		$iframe['src'] = trim( $atts['src'] );

		if ( '' === $iframe['src'] ) {
			continue;
		}

		if ( get_noobrocket_option( 'lazyload_youtube' ) ) {
			$youtube_id = noobrocket_lazyload_get_youtube_id_from_url( $iframe['src'] );

			if ( $youtube_id ) {
				$query = wp_parse_url( htmlspecialchars_decode( $iframe['src'] ), PHP_URL_QUERY );

				/**
				 * Filter the LazyLoad HTML output on Youtube iframes
				 *
				 * @since 2.11
				 *
				 * @param array $html Output that will be printed.
				 */
				$youtube_lazyload  = apply_filters( 'noobrocket_lazyload_youtube_html', '<div class="rll-youtube-player" data-id="' . esc_attr( $youtube_id ) . '" data-query="' . esc_attr( $query ) . '"></div>' );
				$youtube_lazyload .= '<noscript>' . $iframe[0] . '</noscript>';

				$html = str_replace( $iframe[0], $youtube_lazyload, $html );
				continue;
			}
		}

		/**
		 * Filter the LazyLoad placeholder on src attribute
		 *
		 * @since 2.11
		 *
		 * @param string $placeholder placeholder that will be printed.
		 */
		$placeholder = apply_filters( 'noobrocket_lazyload_placeholder', 'about:blank' );

		$placeholder_atts = str_replace( $iframe['src'], $placeholder, $iframe['atts'] );
		$iframe_lazyload  = str_replace( $iframe['atts'], $placeholder_atts . ' data-noobrocket-lazyload="fitvidscompatible" data-lazy-src="' . esc_url( $iframe['src'] ) . '"', $iframe[0] );

		/**
		 * Filter the LazyLoad HTML output on iframes
		 *
		 * @since 2.11
		 *
		 * @param array $html Output that will be printed.
		 */
		$iframe_lazyload  = apply_filters( 'noobrocket_lazyload_iframe_html', $iframe_lazyload );
		$iframe_lazyload .= '<noscript>' . $iframe[0] . '</noscript>';

		$html = str_replace( $iframe[0], $iframe_lazyload, $html );
	}

	return $html;
}
endif;

if ( ! function_exists( 'noobrocket_deactivate_lazyload_on_specific_posts' ) ) :
/**
 * Check if we need to exclude LazyLoad on specific posts
 *
 * @since 3.3
 * @since 2.5
 */
function noobrocket_deactivate_lazyload_on_specific_posts() {
	_deprecated_function( __FUNCTION__ . '()', '3.3', '\NoobRocket\Subscriber\Optimization\Lazyload_Subscriber::deactivate_lazyload_on_specific_posts()' );
	if ( is_noobrocket_post_excluded_option( 'lazyload' ) ) {
		add_filter( 'do_noobrocket_lazyload', '__return_false' );
	}

	if ( is_noobrocket_post_excluded_option( 'lazyload_iframes' ) ) {
		add_filter( 'do_noobrocket_lazyload_iframes', '__return_false' );
	}
}
endif;

if ( ! function_exists( 'noobrocket_lazyload_on_srcset' ) ) :
/**
 * Compatibility with images with srcset attribute
 *
 * @author Remy Perona
 *
 * @since 3.3
 * @since 2.8 Also add sizes to the data-lazy-* attributes to prevent error in W3C validator
 * @since 2.7
 *
 * @param string $html HTML content.
 * @return string Modified HTML content
 */
function noobrocket_lazyload_on_srcset( $html ) {
	_deprecated_function( __FUNCTION__ . '()', '3.3', '\NoobRocketLazyload\Image:lazyloadResponsiveAttributes()' );
	if ( preg_match( '/srcset=("(?:[^"]+)"|\'(?:[^\']+)\'|(?:[^ >]+))/i', $html ) ) {
		$html = str_replace( 'srcset=', 'data-lazy-srcset=', $html );
	}

	if ( preg_match( '/sizes=("(?:[^"]+)"|\'(?:[^\']+)\'|(?:[^ >]+))/i', $html ) ) {
		$html = str_replace( 'sizes=', 'data-lazy-sizes=', $html );
	}

	return $html;
}
endif;

if ( ! function_exists( 'noobrocket_lazyload_get_youtube_id_from_url' ) ) :
/**
 * Gets youtube video ID from URL
 *
 * @author Remy Perona
 * @deprecated 3.3
 * @since 2.11
 *
 * @param string $url URL to parse.
 * @return string     Youtube video id or false if none found.
 */
function noobrocket_lazyload_get_youtube_id_from_url( $url ) {
	_deprecated_function( __FUNCTION__ . '()', '3.3', '\NoobRocketLazyload\Iframe:getYoutubeIDFromURL()' );
	$pattern = '#^(?:https?:)?(?://)?(?:www\.)?(?:youtu\.be|youtube\.com|youtube-nocookie\.com)/(?:embed/|v/|watch/?\?v=)([\w-]{11})#iU';
	$result  = preg_match( $pattern, $url, $matches );

	if ( ! $result ) {
		return false;
	}

	if ( 'videoseries' === $matches[1] ) {
		return false;
	}

	return $matches[1];
}
endif;

if ( ! function_exists( 'noobrocket_user_agent' ) ) :
	/**
	 * Add Rocket information into USER_AGENT
	 *
	 * @since 1.1.0
	 * @deprecated 3.3.6
	 *
	 * @param string $user_agent User Agent value.
	 * @return string NoobRocket user agent
	 */
	function noobrocket_user_agent( $user_agent ) {
		_deprecated_function( __FUNCTION__ . '()', '3.3.6', '\NoobRocket\Subscriber\Plugin\Updater_Api_Common_Subscriber->get_noobrocket_user_agent()' );

		$consumer_key = '';
		if ( isset( $_POST[ NOOBROCKET_SLUG ]['consumer_key'] ) ) {
			$consumer_key = $_POST[ NOOBROCKET_SLUG ]['consumer_key'];
		} elseif ( '' !== (string) get_noobrocket_option( 'consumer_key' ) ) {
			$consumer_key = (string) get_noobrocket_option( 'consumer_key' );
		}

		$consumer_email = '';
		if ( isset( $_POST[ NOOBROCKET_SLUG ]['consumer_email'] ) ) {
			$consumer_email = $_POST[ NOOBROCKET_SLUG ]['consumer_email'];
		} elseif ( '' !== (string) get_noobrocket_option( 'consumer_email' ) ) {
			$consumer_email = (string) get_noobrocket_option( 'consumer_email' );
		}

		$bonus       = ! get_noobrocket_option( 'do_beta' ) ? '' : '+';
		$php_version = preg_replace( '@^(\d\.\d+).*@', '\1', phpversion() );
		$new_ua      = sprintf( '%s;NoobRocket|%s%s|%s|%s|%s|%s;', $user_agent, NOOBROCKET_VERSION, $bonus, $consumer_key, $consumer_email, esc_url( home_url() ), $php_version );

		return $new_ua;
	}
endif;

if ( ! function_exists( 'noobrocket_add_own_ua' ) ) :
	/**
	 * Force our user agent header when we hit our urls
	 *
	 * @since 2.4
	 * @deprecated 3.3.6
	 *
	 * @param array  $request An array of request arguments.
	 * @param string $url     Requested URL.
	 * @return array An array of requested arguments
	 */
	function noobrocket_add_own_ua( $request, $url ) {
		_deprecated_function( __FUNCTION__ . '()', '3.3.6', '\NoobRocket\Subscriber\Plugin\Updater_Api_Common_Subscriber->maybe_set_noobrocket_user_agent()' );

		if ( ! is_string( $url ) ) {
			return $request;
		}

		if ( strpos( $url, 'noobrocket.me' ) !== false ) {
			$request['user-agent'] = noobrocket_user_agent( $request['user-agent'] );
		}
		return $request;
	}
endif;

if ( ! function_exists( 'noobrocket_updates_exclude' ) ) :
	/**
	 * Excludes NoobRocket from WP updates
	 *
	 * @since 1.0
	 * @deprecated 3.3.6
	 *
	 * @param array  $request An array of HTTP request arguments.
	 * @param string $url The request URL.
	 * @return array Updated array of HTTP request arguments.
	 */
	function noobrocket_updates_exclude( $request, $url ) {
		_deprecated_function( __FUNCTION__ . '()', '3.3.6', '\NoobRocket\Subscriber\Plugin\Updater_Subscriber->exclude_noobrocket_from_wp_updates()' );

		if ( ! is_string( $url ) ) {
			return $request;
		}

		if ( 0 !== strpos( $url, 'http://api.wordpress.org/plugins/update-check' ) || ! isset( $request['body']['plugins'] ) ) {
			return $request; // Not a plugin update request. Stop immediately.
		}

		$plugins = maybe_unserialize( $request['body']['plugins'] );

		if ( isset( $plugins->plugins[ plugin_basename( NOOBROCKET_FILE ) ], $plugins->active[ array_search( plugin_basename( NOOBROCKET_FILE ), $plugins->active, true ) ] ) ) {
			unset( $plugins->plugins[ plugin_basename( NOOBROCKET_FILE ) ] );
			unset( $plugins->active[ array_search( plugin_basename( NOOBROCKET_FILE ), $plugins->active, true ) ] );
		}

		$request['body']['plugins'] = maybe_serialize( $plugins );
		return $request;
	}
endif;

if ( ! function_exists( 'noobrocket_force_info' ) ) :
	/**
	 * Hack the returned object
	 *
	 * @since 1.0
	 * @deprecated 3.3.6
	 *
	 * @param false|object|array $bool The result object or array. Default false.
	 * @param string             $action The type of information being requested from the Plugin Install API.
	 * @param object             $args Plugin API arguments.
	 * @return false|object|array Empty object if slug is NoobRocket, default value otherwise
	 */
	function noobrocket_force_info( $bool, $action, $args ) {
		_deprecated_function( __FUNCTION__ . '()', '3.3.6', '\NoobRocket\Subscriber\Plugin\Information_Subscriber->exclude_noobrocket_from_wp_info()' );

		if ( 'plugin_information' === $action && 'noobrocket' === $args->slug ) {
			return new stdClass();
		}
		return $bool;
	}
endif;

if ( ! function_exists( 'noobrocket_force_info_result' ) ) :
	/**
	 * Hack the returned result with our content
	 *
	 * @since 1.0
	 * @deprecated 3.3.6
	 *
	 * @param object|WP_Error $res Response object or WP_Error.
	 * @param string          $action The type of information being requested from the Plugin Install API.
	 * @param object          $args Plugin API arguments.
	 * @return object|WP_Error Updated response object or WP_Error
	 */
	function noobrocket_force_info_result( $res, $action, $args ) {
		_deprecated_function( __FUNCTION__ . '()', '3.3.6', '\NoobRocket\Subscriber\Plugin\Information_Subscriber->add_noobrocket_info()' );

		if ( 'plugin_information' === $action && isset( $args->slug ) && 'noobrocket' === $args->slug && isset( $res->external ) && $res->external ) {

			$request = wp_remote_post(
				NOOBROCKET_WEB_INFO, array(
					'timeout' => 30,
					'action'  => 'plugin_information',
					'request' => serialize( $args ),
				)
			);

			if ( is_wp_error( $request ) ) {
				// translators: %s is an URL.
				$res = new WP_Error( 'plugins_api_failed', sprintf( __( 'An unexpected error occurred. Check the server configuration and <a href="%s">read the troubleshooting guide</a>.', 'noobrocket' ), noobrocket_get_external_url( 'support', array(
					'utm_source' => 'wp_plugin',
					'utm_medium' => 'noobrocket',
				) ) ), $request->get_error_message() );
			} else {
				$res = maybe_unserialize( wp_remote_retrieve_body( $request ) );

				if ( ! is_object( $res ) && ! is_array( $res ) ) {
					// translators: %s is an URL.
					$res = new WP_Error( 'plugins_api_failed', sprintf( __( 'An unexpected error occurred. Check the server configuration and <a href="%s">read the troubleshooting guide</a>.', 'noobrocket' ), noobrocket_get_external_url( 'support', array(
						'utm_source' => 'wp_plugin',
						'utm_medium' => 'noobrocket',
					) ) ), wp_remote_retrieve_body( $request ) );
				}
			}
		}

		return $res;
	}
endif;

if ( ! function_exists( 'noobrocket_check_update' ) ) :
	/**
	 * When WP sets the update_plugins site transient, we set our own transient, then see noobrocket_add_response_to_updates
	 *
	 * @since 2.6.5
	 * @deprecated 3.3.6
	 *
	 * @param Object $value Site transient object.
	 */
	function noobrocket_check_update( $value ) {
		_deprecated_function( __FUNCTION__ . '()', '3.3.6', '\NoobRocket\Subscriber\Plugin\Updater_Subscriber->maybe_add_noobrocket_update_data()' );

		$timer_update_noobrocket = (int) get_site_transient( 'update_noobrocket' );
		$temp_object           = get_site_transient( 'update_noobrocket_response' );
		if ( ( ! isset( $_GET['noobrocket_force_update'] ) || defined( 'WP_INSTALLING' ) ) &&
			( 12 * HOUR_IN_SECONDS ) > ( time() - $timer_update_noobrocket ) // retry in 12 hours.
		) {
			if ( is_object( $value ) && false !== $temp_object ) {
				if ( version_compare( $temp_object->new_version, NOOBROCKET_VERSION ) > 0 ) {
					$value->response[ $temp_object->plugin ] = $temp_object;
				} else {
					delete_site_transient( 'update_noobrocket_response' );
				}
			}
			return $value;
		}

		if ( isset( $_GET['noobrocket_force_update'] ) ) {
			$_SERVER['REQUEST_URI'] = remove_query_arg( 'noobrocket_force_update' );
		}

		$plugin_folder = plugin_basename( dirname( NOOBROCKET_FILE ) );
		$plugin_file   = basename( NOOBROCKET_FILE );
		$version       = true;
		if ( ! $value ) {
			$value               = new stdClass();
			$value->last_checked = time();
		}

		$response = wp_remote_get(
			NOOBROCKET_WEB_CHECK, array(
				'timeout' => 30,
			)
		);
		if ( ! is_a( $response, 'WP_Error' ) && 200 === $response['response']['code'] && strlen( $response['body'] ) > 32 ) {

			set_site_transient( 'update_noobrocket', time() );

			list( $version, $url ) = explode( '|', $response['body'] );
			if ( version_compare( $version, NOOBROCKET_VERSION ) <= 0 ) {
				return $value;
			}

			$temp_array = array(
				'slug'        => $plugin_folder,
				'plugin'      => $plugin_folder . '/' . $plugin_file,
				'new_version' => $version,
				'url'         => 'https://www.noobunbox.net/',
				'package'     => $url,
			);

			$temp_object = (object) $temp_array;
			$value->response[ $plugin_folder . '/' . $plugin_file ] = $temp_object;

			set_site_transient( 'update_noobrocket_response', $temp_object );
		} else {
			set_site_transient( 'update_noobrocket', ( time() + ( 11 * HOUR_IN_SECONDS ) ) ); // retry in 1 hour in case of error..
		}
		return $value;
	}
endif;

if ( ! function_exists( 'noobrocket_reset_check_update_timer' ) ) :
	/**
	 * When WP deletes the update_plugins site transient or updates the plugins, we delete our own transients to avoid another 12 hours waiting
	 *
	 * @since 2.6.8
	 * @deprecated 3.3.6
	 *
	 * @param string $transient Transient name.
	 * @param object $value Transient object.
	 */
	function noobrocket_reset_check_update_timer( $transient = 'update_plugins', $value = null ) {
		_deprecated_function( __FUNCTION__ . '()', '3.3.6' );

		// $value used by set.
		if ( 'update_plugins' === $transient ) {
			if ( is_null( $value ) || is_object( $value ) && ! isset( $value->response ) ) {
				delete_site_transient( 'update_noobrocket' );
			}
		}
	}
endif;
