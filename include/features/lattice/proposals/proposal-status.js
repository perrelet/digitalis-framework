( function ( wp ) {

	var data = window.latticeProposalStatus;

	if ( ! data || ! wp || ! wp.plugins || ! wp.element ) return;

	// WordPress 6.6 moved it from edit-post to editor.
	var PluginPostStatusInfo = ( wp.editor && wp.editor.PluginPostStatusInfo ) || ( wp.editPost && wp.editPost.PluginPostStatusInfo );

	if ( ! PluginPostStatusInfo ) return;

	var el     = wp.element.createElement;
	var Button = wp.components && wp.components.Button;

	// The editor can collapse its meta-box pane to a bar, leaving the box nothing to scroll to.
	function openPane () {
		var prefs = wp.data && wp.data.select( 'core/preferences' );
		if ( prefs && ! prefs.get( 'core/edit-post', 'metaBoxesMainIsOpen' ) ) wp.data.dispatch( 'core/preferences' ).set( 'core/edit-post', 'metaBoxesMainIsOpen', true );
	}

	function scrollToBox () {
		openPane();
		requestAnimationFrame( function () {
			var box = document.getElementById( data.box );
			if ( box ) box.scrollIntoView( { behavior: 'smooth', block: 'start' } );
		} );
	}

	function Panel () {

		var children = [];

		if ( data.heading ) children.push( el( 'p', { key: 'h', style: { margin: '0 0 8px', fontWeight: 600 } }, data.heading ) );
		if ( data.title )   children.push( el( 'p', { key: 't', style: { margin: '0 0 8px', color: '#50575e' } }, data.title ) );
		if ( data.note )    children.push( el( 'p', { key: 'n', style: { margin: '0 0 8px', color: '#8a6d3b' } }, el( 'strong', null, data.label + ' ' ), data.note ) );
		if ( data.text )    children.push( el( 'p', { key: 'x', style: { margin: 0 } }, data.text ) );
		if ( data.review )  children.push( Button ? el( Button, { key: 'r', variant: 'primary', onClick: scrollToBox }, data.reviewLabel ) : el( 'button', { key: 'r', type: 'button', className: 'button button-primary', onClick: scrollToBox }, data.reviewLabel ) );

		return el( PluginPostStatusInfo, { className: 'lattice-proposal-status' }, el( 'div', { style: { width: '100%' } }, children ) );

	}

	wp.plugins.registerPlugin( 'lattice-proposal-status', { render: Panel } );

	// Meta boxes mount late, after the anchor's jump.
	if ( window.location.hash === '#' + data.box ) {

		var tries = 0;
		var timer = setInterval( function () {

			var box = document.getElementById( data.box );

			if ( box ) {
				clearInterval( timer );
				openPane();
				[ 0, 400, 900 ].forEach( function ( t ) { setTimeout( function () { box.scrollIntoView( { block: 'start' } ); }, t ); } );
			} else if ( ++tries > 40 ) {
				clearInterval( timer );
			}

		}, 150 );

	}

} )( window.wp );
