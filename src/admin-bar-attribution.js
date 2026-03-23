import './admin-bar-attribution.scss';

/**
 * Big Orange Pardot — admin bar attribution cookie clear handler.
 *
 * Handles the "Clear all cookies" link in the Attribution admin bar menu.
 * Expires each attribution cookie then reloads the page so the admin bar
 * counts update immediately.
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
}

if ( 'loading' === document.readyState ) {
	document.addEventListener( 'DOMContentLoaded', init );
} else {
	init();
}
