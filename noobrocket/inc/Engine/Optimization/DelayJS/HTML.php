<?php
declare( strict_types=1 );

namespace NoobRocket\Engine\Optimization\DelayJS;

use NoobRocket\Admin\Options_Data;
use NoobRocket\Engine\Optimization\DelayJS\Admin\Settings;
use NoobRocket\Engine\Optimization\DynamicLists\DefaultLists\DataManager;
use NoobRocket\Engine\Optimization\RegexTrait;
use NoobRocket\Engine\Support\CommentTrait;
use NoobRocket\Logger\Logger;

class HTML {
	use RegexTrait;
	use CommentTrait;

	/**
	 * Plugin options instance.
	 *
	 * @var Options_Data
	 */
	protected $options;

	/**
	 * DataManager instance
	 *
	 * @var DataManager
	 */
	private $data_manager;

	/**
	 * Array of excluded patterns from delay JS
	 *
	 * @since 3.9
	 *
	 * @var array
	 */
	protected $excluded = [];

	/**
	 * Allowed type attributes.
	 *
	 * @var array Array of allowed type attributes.
	 */
	private $allowed_types = [
		'text/javascript',
		'module',
		'application/javascript',
		'application/ecmascript',
		'application/x-ecmascript',
		'application/x-javascript',
		'text/ecmascript',
		'text/javascript1.0',
		'text/javascript1.1',
		'text/javascript1.2',
		'text/javascript1.3',
		'text/javascript1.4',
		'text/javascript1.5',
		'text/jscript',
		'text/livescript',
		'text/x-ecmascript',
		'text/x-javascript',
	];

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	protected $logger;

	/**
	 * Creates an instance of HTML.
	 *
	 * @param Options_Data $options Plugin options instance.
	 * @param DataManager  $data_manager DataManager instance.
	 * @param Logger       $logger Logger instance.
	 */
	public function __construct( Options_Data $options, DataManager $data_manager, Logger $logger ) {
		$this->options      = $options;
		$this->data_manager = $data_manager;
		$this->logger       = $logger;
	}

	/**
	 * Adjust HTML to have delay js structure.
	 *
	 * @since 3.9 Updated to use exclusions list instead of inclusions list.
	 * @since 3.7
	 *
	 * @param string $html Buffer html for the page.
	 *
	 * @return string
	 */
	public function delay_js( $html ): string {
		if ( ! $this->is_allowed( $html ) ) {
			return $html;
		}

		$this->set_exclusions();

		$this->excluded = array_merge( $this->excluded, $this->options->get( 'delay_js_exclusions', [] ) );
		$this->excluded = array_merge( $this->excluded, $this->options->get( 'delay_js_exclusions_selected_exclusions', [] ) );

		if ( $this->options->get( 'delay_js_execution_safe_mode', 0 ) ) {
			$this->excluded = array_merge(
				$this->excluded,
				Settings::get_safe_mode_exclusions()
			);
		}

		/**
		 * Filters the delay JS exclusions array
		 *
		 * @since 3.9
		 *
		 * @param array $excluded Array of excluded patterns.
		 */
		$this->excluded = wpm_apply_filters_typed( 'string[]', 'noobrocket_delay_js_exclusions', $this->excluded );
		$this->excluded = array_map(
			function ( $value ) {
				if ( ! is_string( $value ) ) {
					$value = (string) $value;
				}

				return str_replace(
					[ '+', '?ver', '#' ],
					[ '\+', '\?ver', '\#' ],
					$value
				);
			},
			$this->excluded
		);

		$html = $this->parse( $html );

		return $this->add_meta_comment( 'delay_js', $html );
	}

	/**
	 * Checks if is allowed to Delay JS.
	 *
	 * @since 3.7
	 *
	 * @return bool
	 */
	public function is_allowed( string $html = '' ): bool {
		// The delay runtime needs inline execution. Keep functional originals under
		// enforcing CSP instead of weakening the site's policy or guessing its nonce.
		foreach ( headers_list() as $header ) {
			if ( 0 === stripos( $header, 'Content-Security-Policy:' ) ) {
				return false;
			}
		}
		if ( preg_match( '#<meta\b[^>]*http-equiv\s*=\s*["\']?content-security-policy(?=["\'\s>])#i', $html ) ) {
			return false;
		}

		if ( noobrocket_bypass() ) {
			return false;
		}

		if ( noobrocket_get_constant( 'DONOTNOOBROCKETOPTIMIZE' ) ) {
			return false;
		}

		if ( is_noobrocket_post_excluded_option( 'delay_js' ) ) {
			return false;
		}

		return (bool) $this->options->get( 'delay_js', 0 );
	}

	/**
	 * Gets Javascript to redirect IE visitors to the uncached page
	 *
	 * @since 3.9
	 *
	 * @return string
	 */
	public function get_ie_fallback(): string {
		return 'if(navigator.userAgent.match(/MSIE|Internet Explorer/i)||navigator.userAgent.match(/Trident\/7\..*?rv:11/i)){var href=document.location.href;if(!href.match(/[?&]nonoobrocket/)){if(href.indexOf("?")==-1){if(href.indexOf("#")==-1){document.location.href=href+"?nonoobrocket=1"}else{document.location.href=href.replace("#","?nonoobrocket=1#")}}else{if(href.indexOf("#")==-1){document.location.href=href+"&nonoobrocket=1"}else{document.location.href=href.replace("#","&nonoobrocket=1#")}}}}';
	}

	/**
	 * Parse the html and add/remove attributes from specific scripts.
	 *
	 * @since 3.7
	 *
	 * @param string $html Buffer html for the page.
	 *
	 * @return string
	 */
	private function parse( $html ): string {

		if ( empty( $html ) ) {
			return $html;
		}

		$result = $this->replace_xmp_tags( $html );
		$result = $this->replace_svg_tags( $result );

		$replaced_html = preg_replace_callback(
			'/<\s*script\b(?<attr>(?:"[^"]*"|\'[^\']*\'|[^\'">])*)>(?<content>.*?)<\s*\/\s*script\s*>/ims',
			[
				$this,
				'replace_scripts',
			],
			$result
		);
		if ( empty( $replaced_html ) ) {
			return $html;
		}

		$replaced_html = $this->restore_xmp_tags( $replaced_html );
		return $this->restore_svg_tags( $replaced_html );
	}

	/**
	 * Callback method for preg_replace_callback that is used to adjust attributes for scripts.
	 *
	 * @since 3.9 Use exclusions list & fake type attribute.
	 * @since 3.7
	 *
	 * @param array $matches Matches array for scripts regex.
	 *
	 * @return string
	 */
	public function replace_scripts( $matches ): string {
		foreach ( $this->excluded as $pattern ) {
			if ( preg_match( "#{$pattern}#i", $matches[0] ) ) {
				$this->logger->debug( "DelayJS: Script {$matches[0]} excluded by $pattern" );
				return $matches[0];
			}
		}

		// This filter is documented in inc/Engine/Optimization/DelayJS/Subscriber.php.
		$version = wpm_apply_filters_typesafe( 'noobrocket_delay_js_version_js_script', '' );

		if ( empty( $matches['attr'] ) ) {
			if ( ! empty( $version ) ) {
				return '<script type="noobrocketlazyloadscript">' . $matches['content'] . '</script>';
			}

			return '<script type="text/noobrocketlazyloadscript">' . $matches['content'] . '</script>';
		}

		// Skip other quoted values so their text cannot masquerade as src/type.
		// Keep the original assignment bytes, including unquoted values and entities.
		$attributes = [];
		foreach ( $this->get_html_attributes( $matches['attr'] ) as $attribute ) {
			if ( ! in_array( strtolower( $attribute['name'][0] ), [ 'type', 'src' ], true ) ) {
				continue;
			}
			$attributes[] = $attribute;
			if ( 'type' !== strtolower( $attribute['name'][0] ) ) {
				continue;
			}
			$type = $attribute['double'][0] ?? $attribute['single'][0] ?? $attribute['unquoted'][0] ?? '';
			$type = trim( html_entity_decode( $type, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
			if ( '' !== $type && ! in_array( $type, $this->allowed_types, true ) ) {
				return $matches[0];
			}
		}
		foreach ( array_reverse( $attributes ) as $attribute ) {
			$name = strtolower( $attribute['name'][0] );
			$value = $attribute['double'][0] ?? $attribute['single'][0] ?? $attribute['unquoted'][0];
			if ( null === $value ) {
				if ( 'src' === $name ) {
					return $matches[0];
				}
				$matches['attr'] = substr_replace( $matches['attr'], '', $attribute[0][1], strlen( $attribute[0][0] ) );
				continue;
			}
			$matches['attr'] = substr_replace( $matches['attr'], 'data-noobrocket-' . $name, $attribute['name'][1], strlen( $attribute['name'][0] ) );
		}

		if ( ! empty( $version ) ) {
			return '<script type="noobrocketlazyloadscript" ' . trim( $matches['attr'] ) . '>' . $matches['content'] . '</script>';
		}

		return '<script type="text/noobrocketlazyloadscript" ' . trim( $matches['attr'] ) . '>' . $matches['content'] . '</script>';
	}


	/**
	 * Move meta charset to head if not found to the top of page content.
	 *
	 * @since 3.9.4
	 *
	 * @param string $html Html content.
	 *
	 * @return string
	 */
	public function move_meta_charset_to_head( $html ): string {
		$meta_pattern = "#<meta[^h]*(http-equiv[^=]*=[^\'\"]*[\'\" ]Content-Type[\'\"][ ]*[^>]*|)(charset[^=]*=[ ]*[\'\" ]*[^\'\"> ][^\'\">]+[^\'\"> ][\'\" ]*|charset[^=]*=*[^\'\"> ][^\'\">]+[^\'\"> ])([^>]*|)>(?=.*</head>)#Usmi";

		if ( ! preg_match( $meta_pattern, $html, $matches ) ) {
			return $html;
		}

		$replaced_html = preg_replace( "$meta_pattern", '', $html );

		if ( empty( $replaced_html ) ) {
			return $html;
		}

		if ( preg_match( '/<head\b/i', $replaced_html ) ) {
			$replaced_html = preg_replace( '/(<head\b[^>]*?>)/i', "\${1}{$matches[0]}", $replaced_html, 1 );

			if ( empty( $replaced_html ) ) {
				return $html;
			}

			return $replaced_html;
		}

		if ( preg_match( '/<html\b/i', $replaced_html ) ) {
			$replaced_html = preg_replace( '/(<html\b[^>]*?>)/i', "\${1}{$matches[0]}", $replaced_html, 1 );

			if ( empty( $replaced_html ) ) {
				return $html;
			}

			return $replaced_html;
		}

		$replaced_html = preg_replace( '/(<\w+)/', "{$matches[0]}\${1}", $replaced_html, 1 );

		if ( empty( $replaced_html ) ) {
			return $html;
		}

		return $replaced_html;
	}

	/**
	 * Get exclusions
	 *
	 * @return void
	 */
	private function set_exclusions() {
		$lists = $this->data_manager->get_lists();

		$this->excluded = isset( $lists->delay_js_exclusions ) ? $lists->delay_js_exclusions : [];
	}
}
