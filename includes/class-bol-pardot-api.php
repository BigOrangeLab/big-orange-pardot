<?php
/**
 * Pardot API client — token management and API requests.
 *
 * @package BigOrangePardot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Static utility class for interacting with the Pardot (Account Engagement) v5 API.
 *
 * All credentials and tokens are stored in wp_options with autoload disabled.
 * Form handler data is cached in a 15-minute transient to avoid hammering the API.
 */
class BOL_Pardot_API {

	/**
	 * Pardot API v5 base URL.
	 *
	 * @var string
	 */
	const API_BASE = 'https://pi.pardot.com/api/v5/objects';

	/**
	 * Salesforce OAuth token endpoint.
	 *
	 * @var string
	 */
	const TOKEN_URL = 'https://login.salesforce.com/services/oauth2/token';

	/**
	 * Salesforce OAuth authorization endpoint.
	 *
	 * @var string
	 */
	const AUTH_URL = 'https://login.salesforce.com/services/oauth2/authorize';

	/**
	 * Attribution field names the plugin submits via hidden inputs.
	 * Used to flag mapped vs. unmapped fields in the admin inspector.
	 *
	 * @var string[]
	 */
	const ATTRIBUTION_FIELDS = array(
		'utm_source',
		'utm_medium',
		'utm_campaign',
		'utm_term',
		'utm_content',
		'referrer_url',
		'landing_page_url',
		'gclid',
	);

	// -------------------------------------------------------------------------
	// Credential helpers
	// -------------------------------------------------------------------------

	/**
	 * Returns a stored option value, or an empty string if not set.
	 *
	 * @param string $key Option key (without the big_orange_pardot_ prefix).
	 * @return string
	 */
	private static function get_opt( $key ) {
		return (string) get_option( 'big_orange_pardot_' . $key, '' );
	}

	/**
	 * Returns the configured Client ID.
	 *
	 * @return string
	 */
	public static function get_client_id() {
		return self::get_opt( 'client_id' );
	}

	/**
	 * Returns the configured Client Secret.
	 *
	 * @return string
	 */
	public static function get_client_secret() {
		return self::get_opt( 'client_secret' );
	}

	/**
	 * Returns the configured Pardot Business Unit ID.
	 *
	 * @return string
	 */
	public static function get_business_unit_id() {
		return self::get_opt( 'business_unit_id' );
	}

	// -------------------------------------------------------------------------
	// Connection state
	// -------------------------------------------------------------------------

	/**
	 * Returns true if the plugin has a stored access or refresh token.
	 *
	 * @return bool
	 */
	public static function is_connected() {
		return '' !== self::get_opt( 'access_token' ) || '' !== self::get_opt( 'refresh_token' );
	}

	/**
	 * Removes all stored tokens and clears the form-handler transient.
	 *
	 * @return void
	 */
	public static function disconnect() {
		delete_option( 'big_orange_pardot_access_token' );
		delete_option( 'big_orange_pardot_refresh_token' );
		delete_option( 'big_orange_pardot_token_expires' );
		delete_transient( 'big_orange_pardot_form_handlers' );
	}

	// -------------------------------------------------------------------------
	// OAuth helpers
	// -------------------------------------------------------------------------

	/**
	 * Base64url-encodes a binary string (RFC 4648 §5, no padding).
	 *
	 * @param string $data Raw binary data.
	 * @return string
	 */
	private static function base64url_encode( $data ) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	/**
	 * Builds the Salesforce authorization URL to redirect the admin to.
	 *
	 * Generates a PKCE code_verifier (RFC 7636), stores it in wp_options for
	 * retrieval during the token exchange, and includes the derived code_challenge
	 * in the returned URL.
	 *
	 * @param string $redirect_uri The redirect URI registered with the Connected App.
	 * @param string $state        A random nonce for CSRF protection.
	 * @return string
	 */
	public static function get_authorize_url( $redirect_uri, $state ) {
		$code_verifier  = self::base64url_encode( random_bytes( 32 ) );
		$code_challenge = self::base64url_encode( hash( 'sha256', $code_verifier, true ) );

		update_option( 'big_orange_pardot_pkce_verifier', $code_verifier, false );

		return add_query_arg(
			array(
				'response_type'         => 'code',
				'client_id'             => self::get_client_id(),
				'redirect_uri'          => rawurlencode( $redirect_uri ),
				'scope'                 => 'pardot_api refresh_token',
				'state'                 => $state,
				'code_challenge'        => $code_challenge,
				'code_challenge_method' => 'S256',
			),
			self::AUTH_URL
		);
	}

	/**
	 * Exchanges an authorization code for access + refresh tokens and stores them.
	 *
	 * Retrieves and deletes the stored PKCE code_verifier and includes it in the
	 * token request so Salesforce can verify the challenge sent during authorization.
	 *
	 * @param string $code         The authorization code from the OAuth callback.
	 * @param string $redirect_uri The redirect URI used in the initial authorization request.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public static function exchange_code( $code, $redirect_uri ) {
		$code_verifier = (string) get_option( 'big_orange_pardot_pkce_verifier', '' );
		delete_option( 'big_orange_pardot_pkce_verifier' );

		$body = array(
			'grant_type'    => 'authorization_code',
			'client_id'     => self::get_client_id(),
			'client_secret' => self::get_client_secret(),
			'redirect_uri'  => $redirect_uri,
			'code'          => $code,
		);

		if ( '' !== $code_verifier ) {
			$body['code_verifier'] = $code_verifier;
		}

		$response = wp_remote_post(
			self::TOKEN_URL,
			array( 'body' => $body )
		);

		return self::handle_token_response( $response );
	}

	/**
	 * Uses the stored refresh token to obtain a new access token.
	 *
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public static function refresh_token() {
		$refresh_token = self::get_opt( 'refresh_token' );
		if ( '' === $refresh_token ) {
			return new \WP_Error( 'no_refresh_token', __( 'No refresh token stored.', 'big-orange-pardot' ) );
		}

		$response = wp_remote_post(
			self::TOKEN_URL,
			array(
				'body' => array(
					'grant_type'    => 'refresh_token',
					'client_id'     => self::get_client_id(),
					'client_secret' => self::get_client_secret(),
					'refresh_token' => $refresh_token,
				),
			)
		);

		return self::handle_token_response( $response );
	}

	/**
	 * Parses a token endpoint response and stores access/refresh tokens.
	 *
	 * @param array|\WP_Error $response wp_remote_post() response.
	 * @return true|\WP_Error
	 */
	private static function handle_token_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== (int) $code || empty( $body['access_token'] ) ) {
			$message = ! empty( $body['error_description'] ) ? $body['error_description'] : __( 'Unknown token error.', 'big-orange-pardot' );
			return new \WP_Error( 'token_error', $message );
		}

		update_option( 'big_orange_pardot_access_token', $body['access_token'], false );
		update_option( 'big_orange_pardot_token_expires', time() + (int) ( $body['expires_in'] ?? 3600 ), false );

		if ( ! empty( $body['refresh_token'] ) ) {
			update_option( 'big_orange_pardot_refresh_token', $body['refresh_token'], false );
		}

		return true;
	}

	/**
	 * Returns a valid access token, refreshing if necessary.
	 *
	 * @return string|\WP_Error Access token string or WP_Error.
	 */
	public static function get_access_token() {
		$token   = self::get_opt( 'access_token' );
		$expires = (int) get_option( 'big_orange_pardot_token_expires', 0 );

		// Refresh if expired (with a 60-second buffer).
		if ( '' === $token || time() >= ( $expires - 60 ) ) {
			$result = self::refresh_token();
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$token = self::get_opt( 'access_token' );
		}

		return $token;
	}

	// -------------------------------------------------------------------------
	// API requests
	// -------------------------------------------------------------------------

	/**
	 * Makes an authenticated request to the Pardot v5 API.
	 *
	 * @param string $path   Path relative to API_BASE (e.g. '/form-handlers').
	 * @param array  $params Optional query string parameters.
	 * @return array|\WP_Error Decoded response body or WP_Error.
	 */
	public static function api_request( $path, $params = array() ) {
		$token = self::get_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$url = self::API_BASE . $path;
		if ( ! empty( $params ) ) {
			$url = add_query_arg( $params, $url );
		}

		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Authorization'           => 'Bearer ' . $token,
					'Pardot-Business-Unit-Id' => self::get_business_unit_id(),
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( (int) $code < 200 || (int) $code >= 300 ) {
			$message = ! empty( $body['message'] ) ? $body['message'] : sprintf(
				/* translators: %d: HTTP status code */
				__( 'Pardot API returned HTTP %d.', 'big-orange-pardot' ),
				$code
			);
			return new \WP_Error( 'api_error', $message );
		}

		return $body;
	}

	// -------------------------------------------------------------------------
	// Form handlers
	// -------------------------------------------------------------------------

	/**
	 * Returns all form handlers as a flat array of {id, name, url} objects.
	 * Results are cached in a 15-minute transient.
	 *
	 * @return array|\WP_Error
	 */
	public static function get_form_handlers() {
		$cached = get_transient( 'big_orange_pardot_form_handlers' );
		if ( false !== $cached ) {
			return $cached;
		}

		$response = self::api_request( '/form-handlers', array( 'fields' => 'id,name,embedCode' ) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$handlers = array();
		foreach ( (array) ( $response['values'] ?? array() ) as $handler ) {
			$handlers[] = array(
				'id'   => (int) $handler['id'],
				'name' => (string) $handler['name'],
				'url'  => self::extract_form_action( (string) ( $handler['embedCode'] ?? '' ) ),
			);
		}

		set_transient( 'big_orange_pardot_form_handlers', $handlers, 15 * MINUTE_IN_SECONDS );
		return $handlers;
	}

	/**
	 * Extracts the form action URL from a Pardot embed code string.
	 *
	 * @param string $embed_code HTML embed code containing a <form action="..."> element.
	 * @return string Action URL, or empty string if not found.
	 */
	private static function extract_form_action( $embed_code ) {
		if ( preg_match( '/<form[^>]+action=["\']([^"\']+)["\']/', $embed_code, $matches ) ) {
			return esc_url_raw( $matches[1] );
		}
		return '';
	}

	/**
	 * Returns fields for a specific form handler.
	 *
	 * @param int $handler_id Pardot form handler ID.
	 * @return array|\WP_Error Array of field objects or WP_Error.
	 */
	public static function get_form_handler_fields( $handler_id ) {
		$response = self::api_request(
			'/form-handler-fields',
			array( 'formHandlerId' => (int) $handler_id )
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return (array) ( $response['values'] ?? array() );
	}
}
