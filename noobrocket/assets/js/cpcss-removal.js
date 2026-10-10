function noobrocketRemoveCPCSS() {
	let preload_stylesheets = document.querySelectorAll( 'link[data-noobrocket-async="style"][rel="preload"]' );
	if ( preload_stylesheets && preload_stylesheets.length > 0 ) {
		for ( let stylesheet_index = 0;stylesheet_index < preload_stylesheets.length;stylesheet_index++ ){
			let media = preload_stylesheets[stylesheet_index].getAttribute('media') || 'all';
			if( window.matchMedia(media).matches ){
				setTimeout( noobrocketRemoveCPCSS, 200 );
				return;
			}
		}
	}

	const elem = document.getElementById( 'noobrocket-critical-css' );
	if ( elem && 'remove' in elem ) {
		elem.remove();
	}
}

if ( window.addEventListener ) {
	window.addEventListener( 'load', noobrocketRemoveCPCSS );
} else if ( window.attachEvent ) {
	window.attachEvent( 'onload', noobrocketRemoveCPCSS );
}
