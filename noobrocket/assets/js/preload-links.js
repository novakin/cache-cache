class NoobRocketPreloadLinks {

	constructor( browser, config ) {
		this.browser = browser;
		this.config  = config;
		this.options = this.browser.options;
		this.prefetched = new Set;
		this.numOnHover = 0;
		this.prerenderUrl = null;
		this.rulesScript = null;
		this.nonce = document.currentScript ? document.currentScript.nonce : '';
	}

	/**
	 * Initializes the handler.
	 */
	init() {
		if (
			this.browser.isDataSaverModeOn()
			||
			this.browser.isSlowConnection()
		) {
			return;
		}
		this.usePrerender = this.config.prerender === true
			&& typeof HTMLScriptElement.supports === 'function'
			&& HTMLScriptElement.supports( 'speculationrules' );
		const usePrefetch = this.config.prefetch !== false || this.config.prerender === true;
		if ( ! this.usePrerender && ( ! usePrefetch || ! this.browser.supportsLinkPrefetch() ) ) {
			return;
		}
		try {
			this.site = new URL( this.config.siteUrl );
			this.nativeExclusions = [];
			this.nativeSelector = '';
			if ( this.config.prerender ) {
				this.nativeExclusions = ( this.config.nativeExcludePaths || [] ).map( pattern => new URLPattern( pattern, document.baseURI ) );
				this.nativeSelector = ( this.config.nativeExcludeSelectors || [] ).join( ', ' );
				if ( this.nativeSelector ) {
					document.documentElement.matches( this.nativeSelector );
				}
			}
			this.regex = {
				excludeUris: this.config.excludeUris ? new RegExp( this.config.excludeUris, 'i' ) : null,
				prerenderExcludeUris: this.config.prerender && this.config.prerenderExcludeUris ? new RegExp( this.config.prerenderExcludeUris, 'i' ) : null,
				files: new RegExp( '\\.(?:' + this.config.imageExt + '|xml|json|csv|zip|gz|mp3|mp4|webm|svg|ico|css|js)(?:/)?$', 'i' )
			};
		} catch ( error ) {
			// Invalid custom exclusion patterns must not prepare unchecked destinations.
			return;
		}

		this._initListeners( this );
	}

	/**
	 * Initializes the event listeners.
	 *
	 * @private
	 *
	 * @param self instance of this object, used for binding "this" to the listeners.
	 */
	_initListeners( self ) {
		// Setting onHoverDelay to -1 disables the "on-hover" feature.
		if ( this.config.onHoverDelay > -1 ) {
			document.addEventListener( 'mouseover', self.listener.bind( self ), self.options );
		}
		document.addEventListener( 'focusin', self.listener.bind( self ), self.options );
		document.addEventListener( 'mousedown', self.listener.bind( self ), self.options );
		document.addEventListener( 'touchstart', self.listener.bind( self ), self.options );
	}

	/**
	 * Event listener. Processes when near or on a valid <a> hyperlink.
	 *
	 * @param Event event Event instance.
	 */
	listener( event ) {
		const linkElem = event.target instanceof Element ? event.target.closest( 'a' ) : null;
		const url      = this._prepareUrl( linkElem );
		if ( null === url ) {
			return;
		}

		switch ( event.type ) {
			case 'mousedown':
			case 'touchstart':
				this._preparePage( linkElem );
				break;
			case 'mouseover':
				this._earlyPrefetch( linkElem, 'mouseout' );
				break;
			case 'focusin':
				this._earlyPrefetch( linkElem, 'focusout' );
		}
	}

	/**
	 *
	 * @private
	 *
	 * @param Element|null linkElem
	 * @param string resetEvent
	 */
	_earlyPrefetch( linkElem, resetEvent ) {
		const doPrefetch = () => {
			falseTrigger = null;

			// Start the rate throttle: 1 sec timeout.
			if ( 0 === this.numOnHover ) {
				setTimeout( () => this.numOnHover = 0, 1000 );
			}
			// Bail out when exceeding the rate throttle.
			else if ( this.numOnHover >= this.config.rateThrottle ) {
				return;
			}

			this.numOnHover++;
			// Revalidate: scripts can change a link while its intent timer is pending.
			this._preparePage( linkElem );
		};

		// Delay to avoid false triggers for hover/touch/tap.
		let falseTrigger = setTimeout( doPrefetch, this.config.onHoverDelay );

		// On reset event, reset the false trigger timer.
		const reset = () => {
			linkElem.removeEventListener( resetEvent, reset, { passive: true } );
			if ( null === falseTrigger ) {
				return;
			}

			clearTimeout( falseTrigger );
			falseTrigger = null;
		};
		linkElem.addEventListener( resetEvent, reset, { passive: true } );
	}

	_preparePage( linkElem ) {
		const url = this._prepareUrl( linkElem );
		if ( ! url ) {
			return;
		}
		if ( ! this.usePrerender ) {
			this._addPrefetchLink( url );
			return;
		}
		if ( this.rulesScript ) {
			this.rulesScript.remove();
		}
		const script = document.createElement( 'script' );
		script.id = 'noobrocket-speculation-rules';
		script.type = 'speculationrules';
		if ( this.nonce ) {
			script.nonce = this.nonce;
		}
		// Intent is already established. Keep one candidate instead of starting a second hover delay.
		script.textContent = JSON.stringify( { prerender: [ { source: 'list', urls: [ url.href ], eagerness: 'immediate' } ] } );
		document.head.appendChild( script );
		this.rulesScript = script;
		this.prerenderUrl = url.href;
	}

	/**
	 * Adds a <link rel="prefetch" href="<url>"> for the given URL.
	 *
	 * @param string url The Given URL to prefetch.
	 */
	_addPrefetchLink( url ) {
		this.prefetched.add( url.href );

		return new Promise( ( resolve, reject ) => {
			const elem   = document.createElement( 'link' );
			elem.rel     = 'prefetch';
			elem.href    = url.href;
			elem.onload  = resolve;
			elem.onerror = reject;

			document.head.appendChild( elem );
		} ).catch(() => {
			// ignore and continue.
		});
	}

	/**
	 * Prepares the target link's URL.
	 *
	 * @private
	 *
	 * @param Element|null linkElem Instance of the link element.
	 * @returns {null|*}
	 */
	_prepareUrl( linkElem ) {
		if (
			null === linkElem
			||
			typeof linkElem !== 'object'
			||
			! ( 'href' in linkElem )
			||
			// Link prefetching only works on http/https protocol.
			[ 'http:', 'https:' ].indexOf( linkElem.protocol ) === -1
		) {
			return null;
		}
		const target = linkElem.getAttribute( 'target' ) || document.querySelector( 'base[target]' )?.getAttribute( 'target' );
		if ( ( target && target.toLowerCase() !== '_self' ) || linkElem.hasAttribute( 'download' )
			|| linkElem.rel.toLowerCase().split( /\s+/ ).includes( 'nofollow' )
			|| linkElem.closest( '.no-prefetch, [data-noobrocket-no-speculation]' )
			|| this.config.prerender && linkElem.closest( '.no-prerender' )
			|| this.nativeSelector && linkElem.matches( this.nativeSelector ) ) {
			return null;
		}
		let url;
		try {
			url = new URL( linkElem.href );
		} catch ( error ) {
			return null;
		}
		return this._isLinkOk( url ) ? url : null;
	}

	/**
	 * Checks if the given link element is okay to process.
	 *
	 * @private
	 *
	 * @param object url URL parts object.
	 *
	 * @returns {boolean}
	 */
	_isLinkOk( url ) {
		if ( null === url || typeof url !== 'object' ) {
			return false;
		}
		let decodedUrl;
		let decodedPath;
		try {
			decodedUrl = decodeURIComponent( url.href );
			decodedPath = decodeURIComponent( url.pathname );
		} catch ( error ) {
			return false;
		}

		const sitePath = this.site.pathname.replace( /\/$/, '' );
		if ( url.origin !== this.site.origin
			|| ( sitePath && url.pathname !== sitePath && ! url.pathname.startsWith( sitePath + '/' ) )
			|| url.username || url.password || url.href.includes( '?' ) || url.href.includes( '#' )
			|| url.href === window.location.origin + window.location.pathname
			|| this.prefetched.has( url.href ) || url.href === this.prerenderUrl ) {
			return false;
		}
		if ( this.regex.files.test( url.pathname ) || this.regex.files.test( decodedPath ) ) {
			return false;
		}
		if ( this.nativeExclusions.some( pattern => pattern.test( url.href ) ) ) {
			return false;
		}
		if ( this.regex.excludeUris && ( this.regex.excludeUris.test( url.href ) || this.regex.excludeUris.test( decodedUrl ) ) ) {
			return false;
		}
		if ( this.config.prerender && this.regex.prerenderExcludeUris
			&& ( this.regex.prerenderExcludeUris.test( url.href ) || this.regex.prerenderExcludeUris.test( decodedUrl ) ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Named static constructor to encapsulate how to create the object.
	 */
	static run() {
		// Bail out if the configuration not passed from the server.
		if ( typeof NoobRocketPreloadLinksConfig === 'undefined' ) {
			return;
		}

		const browser  = new NoobRocketBrowserCompatibilityChecker( {
			capture: true,
			passive: true
		} );
		const instance = new NoobRocketPreloadLinks( browser, NoobRocketPreloadLinksConfig );
		instance.init();
	}
}

NoobRocketPreloadLinks.run();
