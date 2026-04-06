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
// Consent check
// -------------------------------------------------------------------------

/**
 * Returns true if marketing/analytics cookie consent has been granted.
 *
 * Detection priority:
 *
 * 1. **Operator override** — define `window.bolConsentCheck = function() { return bool; }`
 *    anywhere before this script runs to take full control of the decision.
 *    Returning `true` allows attribution cookies; `false` blocks them.
 *    Example (inline script or wp_add_inline_script):
 *        window.bolConsentCheck = function() {
 *            return myCMP.hasConsent('marketing');
 *        };
 *
 * 2. **Cookiebot** — detected via `window.Cookiebot.consent.marketing`.
 *    Fires `captureAttribution` on the `CookiebotOnAccept` window event.
 *
 * 3. **CookieYes** — detected via `window.getCkyConsent().categories.advertisement`.
 *    Fires on the `ckyConsentUpdate` document event.
 *
 * 4. **Complianz** — detected via the `cmplz_marketing` cookie (`allow` = consented).
 *    Fires on the `cmplzStatusChange` document event.
 *
 * 5. **No CMP detected** — defaults to `true` (allow).
 *    Sites without a CMP are responsible for their own compliance.
 *
 * @return {boolean} Whether marketing cookies may be set.
 */
function hasMarketingConsent() {
	// 1. Operator override.
	if ( typeof window.bolConsentCheck === 'function' ) {
		return !! window.bolConsentCheck();
	}

	// 2. Cookiebot.
	if ( window.Cookiebot && window.Cookiebot.consent !== undefined ) {
		return !! window.Cookiebot.consent.marketing;
	}

	// 3. CookieYes.
	if ( typeof window.getCkyConsent === 'function' ) {
		const cky = window.getCkyConsent();
		return !! ( cky && cky.categories && cky.categories.advertisement );
	}

	// 4. Complianz — cmplz_marketing cookie is set to 'allow' when consented.
	const cmplz = getCookie( 'cmplz_marketing' );
	if ( cmplz !== null ) {
		return 'allow' === cmplz;
	}

	// 5. No CMP detected — allow by default.
	return true;
}

/**
 * Expires all attribution cookies written by captureAttribution() and
 * notifies listeners that cookie state has changed.
 *
 * Called when marketing consent is revoked so stored tracking values are
 * cleared immediately and won't be forwarded on the next form submission.
 * Note: visitor_id is Pardot's own cookie — we do not expire it here.
 */
function expireAttributionCookies() {
	HIDDEN_FIELD_NAMES.forEach( function ( name ) {
		document.cookie =
			name +
			'=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=' +
			COOKIE_PATH +
			';';
	} );
	// Notify listeners (e.g. admin bar inspector) that cookie state changed.
	document.dispatchEvent( new CustomEvent( 'bolAttributionUpdated' ) );
}

/**
 * Runs captureAttribution() + populateForms() once consent is confirmed.
 * Called by each CMP's consent-granted event so that forms already on the
 * page get their hidden fields populated before the visitor submits.
 */
function onConsentGranted() {
	if ( hasMarketingConsent() ) {
		captureAttribution();
		populateForms();
	}
}

/**
 * Expires attribution cookies when consent is revoked.
 * Called by Cookiebot's decline event and the operator `bolConsentRevoked`
 * custom event.
 */
function onConsentRevoked() {
	if ( ! hasMarketingConsent() ) {
		expireAttributionCookies();
	}
}

/**
 * Handles CMPs that fire a single event for both grant and revoke
 * (CookieYes `ckyConsentUpdate`, Complianz `cmplzStatusChange`).
 * Captures attribution when consent is present; expires cookies when not.
 */
function onConsentChange() {
	if ( hasMarketingConsent() ) {
		captureAttribution();
		populateForms();
	} else {
		expireAttributionCookies();
	}
}

/**
 * Registers event listeners for consent-granted and consent-revoked events
 * from known CMPs and operator-provided custom events.
 * Safe to call unconditionally — listeners only fire if the CMP is present.
 */
function setupConsentListeners() {
	// Cookiebot fires separate events for accept and decline.
	window.addEventListener( 'CookiebotOnAccept', onConsentGranted );
	window.addEventListener( 'CookiebotOnDecline', onConsentRevoked );

	// CookieYes fires a single event for both grant and revoke.
	document.addEventListener( 'ckyConsentUpdate', onConsentChange );

	// Complianz fires a single event for both grant and revoke.
	document.addEventListener( 'cmplzStatusChange', onConsentChange );

	// Operator escape hatches — dispatch from your own CMP integration to
	// trigger attribution capture or cookie expiry respectively.
	document.addEventListener( 'bolConsentGranted', onConsentGranted );
	document.addEventListener( 'bolConsentRevoked', onConsentRevoked );
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
			// Set from the current page URL, stripping any known Pardot error-redirect
			// params (errors, errorMessage, allFields) to avoid submitting PII that
			// Pardot echoes back in error redirects. Other query params are preserved
			// as they may be relevant to CMS routing or other aspects of the page.
			const urlParams = new URLSearchParams( window.location.search );
			PARDOT_SYSTEM_PARAMS.forEach( function ( p ) {
				urlParams.delete( p );
			} );
			const cleanSearch = urlParams.toString();
			input.value =
				window.location.origin +
				window.location.pathname +
				( cleanSearch ? '?' + cleanSearch : '' ) +
				window.location.hash;
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
 *
 * After processing, strips the Pardot error params and any echoed field values
 * from the address bar via history.replaceState to avoid leaving PII/error
 * details in browser history, copied links, referrers, and analytics.
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

	// Track which field-name params were echoed back so we can strip them too.
	const echoedFieldNames = [];

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
						echoedFieldNames.push( field.name );
					}
				}
			} );
		} );

	// Remove Pardot error params and echoed field values from the address bar
	// to avoid leaving PII/error details in browser history, copied links,
	// referrer headers sent to third parties, and analytics tools.
	if ( window.history && window.history.replaceState ) {
		const cleanParams = new URLSearchParams( window.location.search );
		PARDOT_SYSTEM_PARAMS.forEach( function ( p ) {
			cleanParams.delete( p );
		} );
		echoedFieldNames.forEach( function ( name ) {
			cleanParams.delete( name );
		} );
		const cleanSearch = cleanParams.toString();
		const cleanUrl =
			window.location.pathname +
			( cleanSearch ? '?' + cleanSearch : '' ) +
			window.location.hash;
		window.history.replaceState( null, '', cleanUrl );
	}
}

// -------------------------------------------------------------------------
// MutationObserver — handle dynamically injected forms (modals, off-canvas)
// -------------------------------------------------------------------------

let debounceTimer = null;

function debouncedPopulate() {
	clearTimeout( debounceTimer );
	debounceTimer = setTimeout( function () {
		populateForms();
		handlePardotErrors();
	}, 50 );
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

// captureAttribution() is gated on consent — it is the only function that
// writes our tracking cookies. Everything else (form population, error
// handling, observers, submit validation) runs unconditionally.
if ( hasMarketingConsent() ) {
	captureAttribution();
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', function () {
		populateForms();
		handlePardotErrors();
		observeForForms();
		setupConsentListeners();
	} );
} else {
	populateForms();
	handlePardotErrors();
	observeForForms();
	setupConsentListeners();
}
