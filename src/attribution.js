/**
 * Big Orange Pardot — Attribution & Cookie Script
 *
 * Runs on every page load. Captures UTM parameters, gclid, landing page URL,
 * and referrer into cookies, then populates hidden fields in any Pardot form
 * found on the page (including forms added dynamically via MutationObserver).
 *
 * Also handles Pardot error redirects: when Pardot returns the visitor to the
 * form page with `errors=true` in the query string, this script displays the
 * error messages and re-populates visible fields with the previously submitted
 * values.
 *
 * Additionally, submit-time validation runs before the form is sent to Pardot,
 * catching required-field and format errors client-side using the HTML5
 * Constraint Validation API.
 */
/* global MutationObserver */

const COOKIE_EXPIRY_DAYS = 30;
const GCLID_EXPIRY_DAYS = 90;
const COOKIE_PATH = '/';

// Cookie-backed hidden fields — populated from their matching cookie values.
// Note: visitor_id is handled separately because its cookie name is dynamic
// (visitor_id{piAId}, e.g. visitor_id787913) — see getPardotVisitorId().
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

// URL params Pardot appends to its error redirect — not visible form fields.
const PARDOT_SYSTEM_PARAMS = [ 'errors', 'errorMessage', 'allFields' ];

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

/**
 * Finds the Pardot visitor ID from its dynamically-named cookie.
 *
 * Pardot's tracking script stores the visitor ID in a cookie named
 * visitor_id{piAId} (e.g. visitor_id787913). We scan all cookies for the
 * pattern visitor_id + digits, excluding the companion -hash variant.
 *
 * @return {string|null} Visitor ID value, or null if not found.
 */
function getPardotVisitorId() {
	const cookies = document.cookie.split( ';' );
	for ( let i = 0; i < cookies.length; i++ ) {
		const eqPos = cookies[ i ].indexOf( '=' );
		if ( eqPos === -1 ) {
			continue;
		}
		const name = cookies[ i ].substring( 0, eqPos ).trim();
		if ( /^visitor_id\d+$/.test( name ) ) {
			return decodeURIComponent(
				cookies[ i ].substring( eqPos + 1 ).trim()
			);
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
		if ( input.name === 'last_form_submission_url' ) {
			// Always set from the current page URL, not a cookie.
			input.value = window.location.href;
		} else if ( input.name === 'visitor_id' ) {
			// Pardot's visitor ID cookie is named visitor_id{piAId} — scan for it.
			const visitorId = getPardotVisitorId();
			if ( visitorId !== null ) {
				input.value = visitorId;
			}
		} else if ( HIDDEN_FIELD_NAMES.indexOf( input.name ) !== -1 ) {
			const cookieValue = getCookie( input.name );
			if ( cookieValue !== null ) {
				input.value = cookieValue;
			}
		}
	} );
}

// -------------------------------------------------------------------------
// Client-side submit validation
// -------------------------------------------------------------------------

/**
 * Validates the form using the HTML5 Constraint Validation API.
 * Shows errors in the error container and returns false if invalid.
 *
 * @param {HTMLFormElement} form           The form element.
 * @param {HTMLElement}     errorContainer The .bol-pardot-errors element.
 * @return {boolean} True if the form is valid; false otherwise.
 */
function validateForm( form, errorContainer ) {
	// Always reset any existing client-side errors before re-validating.
	errorContainer.innerHTML = '';
	errorContainer.style.display = 'none';

	if ( form.checkValidity() ) {
		return true;
	}

	const errors = [];
	Array.prototype.forEach.call( form.elements, function ( field ) {
		if ( field.name && 'hidden' !== field.type && ! field.validity.valid ) {
			errors.push( field.validationMessage || field.name );
		}
	} );

	if ( errors.length ) {
		const ul = document.createElement( 'ul' );
		errors.forEach( function ( msg ) {
			const li = document.createElement( 'li' );
			li.textContent = msg;
			ul.appendChild( li );
		} );
		errorContainer.appendChild( ul );
		errorContainer.removeAttribute( 'style' );
		errorContainer.scrollIntoView( {
			behavior: 'smooth',
			block: 'nearest',
		} );
	}

	return false;
}

// -------------------------------------------------------------------------
// Pardot error-redirect handling
// -------------------------------------------------------------------------

/**
 * Detects a Pardot error redirect (errors=true in the query string), displays
 * the error message, and re-populates visible form fields with the previously
 * submitted values that Pardot echoes back in the URL.
 */
function handlePardotErrors() {
	const params = new URLSearchParams( window.location.search );
	if ( 'true' !== params.get( 'errors' ) ) {
		return;
	}

	const rawMessage = params.get( 'errorMessage' ) || '';
	const messageParts = rawMessage
		.split( '~~~' )
		.map( function ( s ) {
			return s.trim();
		} )
		.filter( Boolean );

	document
		.querySelectorAll( '.wp-block-bigorangelab-pardot-form' )
		.forEach( function ( wrapper ) {
			const errorContainer =
				wrapper.querySelector( '.bol-pardot-errors' );
			if ( ! errorContainer ) {
				return;
			}

			errorContainer.innerHTML = '';

			if ( messageParts.length ) {
				const ul = document.createElement( 'ul' );
				messageParts.forEach( function ( part ) {
					const li = document.createElement( 'li' );
					li.textContent = part;
					ul.appendChild( li );
				} );
				errorContainer.appendChild( ul );
			}
			errorContainer.removeAttribute( 'style' );

			// Re-populate visible fields with the submitted values Pardot echoes back.
			const form = wrapper.querySelector( 'form' );
			if ( ! form ) {
				return;
			}
			form.querySelectorAll(
				'input:not([type="hidden"]), textarea'
			).forEach( function ( field ) {
				if (
					field.name &&
					PARDOT_SYSTEM_PARAMS.indexOf( field.name ) === -1
				) {
					const value = params.get( field.name );
					if ( null !== value ) {
						field.value = value;
					}
				}
			} );
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
// Submit validation — event delegation handles static & dynamic forms
// -------------------------------------------------------------------------

document.addEventListener( 'submit', function ( e ) {
	const form = e.target;
	const wrapper = form.closest( '.wp-block-bigorangelab-pardot-form' );
	if ( ! wrapper ) {
		return;
	}
	const errorContainer = wrapper.querySelector( '.bol-pardot-errors' );
	if ( ! errorContainer ) {
		return;
	}
	if ( ! validateForm( form, errorContainer ) ) {
		e.preventDefault();
	}
} );

// -------------------------------------------------------------------------
// Init
// -------------------------------------------------------------------------

captureAttribution();

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', function () {
		populateForms();
		handlePardotErrors();
		observeForForms();
	} );
} else {
	populateForms();
	handlePardotErrors();
	observeForForms();
}
