import domReady from '@wordpress/dom-ready';
import { createRoot, render } from '@wordpress/element';

import { LogViewerApp } from './log-viewer-app';
import './log-viewer.scss';

domReady( () => {
	const target = document.getElementById( 'bol-pardot-log-viewer-app' );
	if ( ! target ) {
		return;
	}

	const config = window.bolPardotLogsPageConfig || {};

	if ( createRoot ) {
		createRoot( target ).render( <LogViewerApp config={ config } /> );
		return;
	}

	render( <LogViewerApp config={ config } />, target );
} );
