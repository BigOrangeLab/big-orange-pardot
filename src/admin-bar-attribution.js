import './admin-bar-attribution.scss';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Big Orange Pardot — admin bar attribution cookie clear handler.
 *
 * Handles the "Clear all cookies" link in the Attribution admin bar menu.
 * Expires each attribution cookie then reloads the page so the admin bar
 * counts update immediately.
 *
 * Also listens for the `bolAttributionUpdated` event dispatched by
 * attribution.js so the admin bar reflects new cookies without a page reload.
 */

const COOKIE_NAMES = [
	'utm_source',
	'utm_medium',
	'utm_campaign',
	'utm_term',
	'utm_content',
	'referrer_url',
	'landing_page_url',
	'gclid',
	'visitor_id',
];

/**
 * Expires a cookie by setting its expiry date to the past.
 *
 * @param {string} name Cookie name.
 */
function deleteCookie( name ) {
	document.cookie =
		name + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
}

/**
 * Reads a cookie value by name.
 *
 * @param {string} name Cookie name.
 * @return {string|null} Cookie value, or null if not set.
 */
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
 * Updates the admin bar Attribution menu to reflect the current cookie state.
 * Called after attribution.js dispatches `bolAttributionUpdated`.
 */
function updateAdminBar() {
	let setCount = 0;

	COOKIE_NAMES.forEach( function ( name ) {
		const item = document.getElementById(
			'wp-admin-bar-bol-attribution-field-' + name
		);
		if ( ! item ) {
			return;
		}
		const valueSpan = item.querySelector( '.bol-ab-field-value' );
		if ( ! valueSpan ) {
			return;
		}
		const value = getCookie( name );
		if ( value ) {
			setCount++;
			valueSpan.textContent = value;
			valueSpan.classList.remove( 'bol-ab-field-empty' );
		} else {
			valueSpan.textContent = __( '(not set)', 'big-orange-pardot' );
			valueSpan.classList.add( 'bol-ab-field-empty' );
		}
	} );

	const parentItem = document.getElementById(
		'wp-admin-bar-bol-attribution'
	);
	if ( parentItem ) {
		const titleLink = parentItem.querySelector( ':scope > .ab-item' );
		if ( titleLink ) {
			titleLink.textContent = sprintf(
				/* translators: %d: number of attribution cookies currently set */
				__( 'Attribution (%d)', 'big-orange-pardot' ),
				setCount
			);
		}
	}
}

function init() {
	const clearLink = document.getElementById(
		'wp-admin-bar-bol-attribution-clear'
	);
	if ( ! clearLink ) {
		return;
	}
	clearLink.addEventListener( 'click', function ( e ) {
		e.preventDefault();
		COOKIE_NAMES.forEach( deleteCookie );
		window.location.reload();
	} );

	document.addEventListener( 'bolAttributionUpdated', updateAdminBar );
}

if ( 'loading' === document.readyState ) {
	document.addEventListener( 'DOMContentLoaded', init );
} else {
	init();
}
