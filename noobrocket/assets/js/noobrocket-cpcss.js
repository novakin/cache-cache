let checkCPCSSGenerationCall;
let checkCPCSSMobileGenerationCall;
let cpcsssGenerationPending       = 0;
let cpcsssMobileGenerationPending = 0;
const noobrocketDeleteCPCSSbtn        = document.getElementById( 'noobrocket-delete-post-cpss' );
const noobrocketGenerateCPCSSbtn      = document.getElementById( 'noobrocket-generate-post-cpss' );
const noobrocketCPCSSGenerate         = document.querySelectorAll( '.cpcss_generate' );
const noobrocketCPCSSReGenerate       = document.querySelectorAll( '.cpcss_regenerate' );

if ( null !== noobrocketDeleteCPCSSbtn ) {
	noobrocketDeleteCPCSSbtn.addEventListener( 'click', e => {
		e.preventDefault();
		deleteCPCSS();
	} );
}

if ( null !== noobrocketGenerateCPCSSbtn ) {
	noobrocketGenerateCPCSSbtn.addEventListener( 'click', e => {
		e.preventDefault();
		noobrocketGenerateCPCSSbtn.disabled = true;
		checkCPCSSGeneration( null, false );
		if ( noobrocket_cpcss.noobrocketMobileCpcssEnabled ) {
			checkCPCSSGeneration( null, true );
		}
	} );
}

const checkCPCSSGeneration = ( timeout = null, is_mobile = false ) => {
	const spinner                   = noobrocketGenerateCPCSSbtn.querySelector( '.spinner' );
	spinner.style.display           = 'block';
	spinner.style.visibility        = 'visible';

	const xhttp  = new XMLHttpRequest();
	xhttp.onload = () => {
		if ( 200 !== xhttp.status ) {
			return;
		}

		const cpcss_response = JSON.parse( xhttp.response );
		if ( 200 !== cpcss_response.data.status ) {
			stopCPCSSGeneration( spinner );
			if ( ! is_mobile ) {
				cpcssNotice( cpcss_response.message, 'error' );
			}
			noobrocketGenerateCPCSSbtn.disabled = false;
			return;
		}

		if ( 200 === cpcss_response.data.status &&
			'cpcss_generation_pending' !== cpcss_response.code ) {
			stopCPCSSGeneration( spinner, is_mobile );
			if ( ! is_mobile ) {
				cpcssNotice( cpcss_response.message, 'success' );
			}

			// Revert view to Regenerate.
			noobrocketGenerateCPCSSbtn.querySelector( '.noobrocket-generate-post-cpss-btn-txt' ).innerHTML = noobrocket_cpcss.regenerate_btn;
			noobrocketDeleteCPCSSbtn.style.display                                                     = 'block';
			noobrocketGenerateCPCSSbtn.disabled                                                        = false;
			noobrocketCPCSSGenerate.forEach( item => item.style.display = 'none' );
			noobrocketCPCSSReGenerate.forEach( item => item.style.display = 'block' );
			return;
		}

		if ( is_mobile ) {
			cpcsssMobileGenerationPending++;
			if ( cpcsssMobileGenerationPending > 10 ) {
				stopCPCSSGeneration( spinner, is_mobile );
				cpcsssMobileGenerationPending = 0;
				checkCPCSSGeneration( true, true );
				return;
			}
			checkCPCSSMobileGenerationCall = setTimeout( () => {
				checkCPCSSGeneration( null, true );
			}, 3000 );
		} else {
			cpcsssGenerationPending++;
			if ( cpcsssGenerationPending > 10 ) {
				stopCPCSSGeneration( spinner, is_mobile );
				cpcsssGenerationPending = 0;
				checkCPCSSGeneration( true, false );
				return;
			}

			checkCPCSSGenerationCall = setTimeout( () => {
				checkCPCSSGeneration( null, false );
			}, 3000 );
		}
	};

	xhttp.open( 'POST', noobrocket_cpcss.rest_url, true );
	xhttp.setRequestHeader( 'Content-Type', 'application/json' );
	xhttp.setRequestHeader( 'X-WP-Nonce', noobrocket_cpcss.rest_nonce );
	xhttp.send( JSON.stringify( { timeout: timeout, is_mobile: is_mobile } ) );
}

const stopCPCSSGeneration = ( spinner, is_mobile ) => {
	spinner.style.display = 'none';
	if ( is_mobile ) {
		clearTimeout( checkCPCSSMobileGenerationCall );
	} else {
		clearTimeout( checkCPCSSGenerationCall );
	}
}

const deleteCPCSS = () => {
	noobrocketDeleteCPCSSbtn.disabled = true;

	const xhttp  = new XMLHttpRequest();
	xhttp.onload = () => {
		if ( 200 !== xhttp.status ) {
			return;
		}

		noobrocketDeleteCPCSSbtn.disabled = false;
		const cpcss_response          = JSON.parse( xhttp.response );

		if ( 200 !== cpcss_response.data.status ) {
			cpcssNotice( cpcss_response.message, 'error' );
			return;
		}
		cpcssNotice( cpcss_response.message, 'success' );

		// Revert view to Generate.
		noobrocketGenerateCPCSSbtn.querySelector( '.noobrocket-generate-post-cpss-btn-txt' ).innerHTML = noobrocket_cpcss.generate_btn;
		noobrocketDeleteCPCSSbtn.style.display                                                     = 'none';
		noobrocketCPCSSReGenerate.forEach( item => item.style.display = 'none' );
		noobrocketCPCSSGenerate.forEach( item => item.style.display = 'block' );
	};

	xhttp.open( 'DELETE', noobrocket_cpcss.rest_url, true );
	xhttp.setRequestHeader( 'Content-Type', 'application/json' );
	xhttp.setRequestHeader( 'X-WP-Nonce', noobrocket_cpcss.rest_nonce );
	xhttp.send();
}

const cpcssNotice = ( msg, type ) => {
	/* Add notice class */
	const cpcssNotice     = document.getElementById( 'cpcss_response_notice' );
	cpcssNotice.innerHTML = '';
	cpcssNotice.classList.remove( 'hidden', 'notice', 'is-warning', 'notice-error', 'notice-success', 'is-error', 'is-success' );
	cpcssNotice.classList.add( 'notice', 'notice-' + type, 'is-' + type );

	/* create paragraph element to hold message */
	const p = document.createElement( 'p' );
	p.appendChild( document.createTextNode( msg ) );

	/* Add the whole message to notice div */
	cpcssNotice.appendChild( p );
}
