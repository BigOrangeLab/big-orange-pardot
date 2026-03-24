/**
 * Big Orange Pardot — Attribution & Cookie Script
 *
 * Runs on every page load. Captures UTM parameters, gclid, landing page URL,
 * and referrer into cookies, then populates hidden fields in any Pardot form
 * found on the page (including forms added dynamically via MutationObserver).
 */
/* global MutationObserver */

const COOKIE_EXPIRY_DAYS = 30;
const GCLID_EXPIRY_DAYS = 90;
const COOKIE_PATH = '/';

const HIDDEN_FIELD_NAMES = [
	'utm_source',
	'utm_medium',
	'utm_campaign',
	'utm_term',
	'utm_content',
	'referrer_url',
	'landing_page_url',
	'gclid',
];

// -------------------------------------------------------------------------
// Cookie utilities
// -------------------------------------------------------------------------

function setCookie( name, value, days ) {
	let expires = '';
	if ( days ) {
		const date = new Date();
		date.setTime( date.getTime() + days * 24 * 60 * 60 * 1000 );
		expires = '; expires=' + date.toUTCString();
	}
	document.cookie =
		name +
		'=' +
		encodeURIComponent( value ) +
		expires +
		'; path=' +
		COOKIE_PATH;
}

function getCookie( name ) {
	const nameEQ = name + '=';
	const cookies = document.cookie.split( ';' );
	for ( let i = 0; i < cookies.length; i++ ) {
		const c = cookies[ i ].trim();
		if ( c.indexOf( nameEQ ) === 0 ) {
			return decodeURIComponent( c.substring( nameEQ.length ) );
		}
	}
	return null;
}

// -------------------------------------------------------------------------
// URL param helpers
// -------------------------------------------------------------------------

function getUrlParam( name ) {
	const params = new URLSearchParams( window.location.search );
	return params.get( name );
}

// -------------------------------------------------------------------------
// Capture & store attribution data
// -------------------------------------------------------------------------

function captureAttribution() {
	const utmParams = [
		'utm_source',
		'utm_medium',
		'utm_campaign',
		'utm_term',
		'utm_content',
	];

	// UTM params — overwrite cookies whenever present in URL.
	utmParams.forEach( function ( param ) {
		const value = getUrlParam( param );
		if ( value ) {
			setCookie( param, value, COOKIE_EXPIRY_DAYS );
		}
	} );

	// gclid — overwrite whenever present in URL (90-day window).
	const gclid = getUrlParam( 'gclid' );
	if ( gclid ) {
		setCookie( 'gclid', gclid, GCLID_EXPIRY_DAYS );
	}

	// landing_page_url — set once only on first visit.
	if ( ! getCookie( 'landing_page_url' ) ) {
		setCookie(
			'landing_page_url',
			window.location.href,
			COOKIE_EXPIRY_DAYS
		);
	}

	// referrer_url — set once only, external referrers only.
	if ( ! getCookie( 'referrer_url' ) && document.referrer ) {
		try {
			const referrerHost = new URL( document.referrer ).hostname;
			if ( referrerHost !== window.location.hostname ) {
				setCookie(
					'referrer_url',
					document.referrer,
					COOKIE_EXPIRY_DAYS
				);
			}
		} catch ( e ) {
			// Malformed referrer — skip.
		}
	}

	// Notify any listeners (e.g. admin bar inspector) that cookies may have changed.
	document.dispatchEvent( new CustomEvent( 'bolAttributionUpdated' ) );
}

// -------------------------------------------------------------------------
// Populate hidden fields in any forms on the page
// -------------------------------------------------------------------------

function populateForms() {
	const hiddenInputs = document.querySelectorAll( 'input[type="hidden"]' );
	hiddenInputs.forEach( function ( input ) {
		if ( HIDDEN_FIELD_NAMES.indexOf( input.name ) !== -1 ) {
			const cookieValue = getCookie( input.name );
			if ( cookieValue !== null ) {
				input.value = cookieValue;
			}
		}
	} );
}

// -------------------------------------------------------------------------
// MutationObserver — handle dynamically injected forms (modals, off-canvas)
// -------------------------------------------------------------------------

let debounceTimer = null;

function debouncedPopulate() {
	clearTimeout( debounceTimer );
	debounceTimer = setTimeout( populateForms, 50 );
}

function observeForForms() {
	const observer = new MutationObserver( function ( mutations ) {
		for ( let i = 0; i < mutations.length; i++ ) {
			if ( mutations[ i ].addedNodes.length > 0 ) {
				debouncedPopulate();
				return;
			}
		}
	} );

	observer.observe( document.body, {
		childList: true,
		subtree: true,
	} );
}

// -------------------------------------------------------------------------
// Init
// -------------------------------------------------------------------------

captureAttribution();

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', function () {
		populateForms();
		observeForForms();
	} );
} else {
	populateForms();
	observeForForms();
}
