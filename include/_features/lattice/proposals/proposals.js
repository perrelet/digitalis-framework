( function () {

	var data = window.latticeProposals || {};

	document.querySelectorAll( '.lattice-proposal-flag.is-proposal[data-parent]' ).forEach( function ( flag ) {
		var row    = flag.closest( 'tr' );
		var parent = document.getElementById( 'post-' + flag.getAttribute( 'data-parent' ) );
		if ( row && parent && parent.parentNode === row.parentNode ) parent.after( row );
	} );

	document.addEventListener( 'click', function ( event ) {

		var confirmed = event.target.closest( '[data-lattice-proposal-confirm]' );
		if ( confirmed && ! window.confirm( confirmed.getAttribute( 'data-lattice-proposal-confirm' ) ) ) event.preventDefault();

		// Its own form: the editor's would submit instead.
		var reject = event.target.closest( '[data-lattice-proposal-reject]' );
		if ( ! reject ) return;

		var note = window.prompt( data.notePrompt, '' );
		if ( note === null ) return;

		var form  = document.createElement( 'form' );
		var input = document.createElement( 'input' );

		form.method = 'post';
		form.action = reject.getAttribute( 'data-lattice-proposal-reject' );
		input.type  = 'hidden';
		input.name  = data.noteField;
		input.value = note;

		form.appendChild( input );
		document.body.appendChild( form );
		form.submit();

	} );

	// The classic editor scrolls back to the top as it starts, after the anchor's jump.
	if ( data.box && window.location.hash === '#' + data.box && ! document.body.classList.contains( 'block-editor-page' ) ) {
		window.addEventListener( 'load', function () {
			[ 0, 350, 800, 1400 ].forEach( function ( delay ) {
				setTimeout( function () {
					var box = document.getElementById( data.box );
					if ( box ) box.scrollIntoView();
				}, delay );
			} );
		} );
	}

} )();
