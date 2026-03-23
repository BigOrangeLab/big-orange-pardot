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
	 * Log file name for optional API request logging.
	 *
	 * @var string
	 */
	const LOG_FILE_NAME = 'big-orange-pardot-api.log';

	/**
	 * Salesforce REST API version used for Business Unit discovery.
	 *
	 * @var string
	 */
	const SALESFORCE_API_VERSION = 'v62.0';

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
		delete_option( 'big_orange_pardot_instance_url' );
		delete_transient( 'big_orange_pardot_form_handlers' );
		delete_transient( 'big_orange_pardot_business_units' );
	}

	/**
	 * Returns true when API logging is enabled in settings.
	 *
	 * @return bool
	 */
	public static function is_api_logging_enabled() {
		return (bool) get_option( 'big_orange_pardot_enable_api_logging', false );
	}

	/**
	 * Returns the full path to the API log file in uploads, or empty string on failure.
	 *
	 * @return string
	 */
	public static function get_api_log_path() {
		$uploads = wp_upload_dir();

		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return '';
		}

		return trailingslashit( $uploads['basedir'] ) . self::LOG_FILE_NAME;
	}

	/**
	 * Returns API log contents, optionally truncated to the latest bytes.
	 *
	 * @param int $max_bytes Maximum bytes to return from the end of the file.
	 * @return string
	 */
	public static function get_api_log_contents( $max_bytes = 500000 ) {
		$path = self::get_api_log_path();

		if ( '' === $path || ! file_exists( $path ) || ! is_readable( $path ) ) {
			return '';
		}

		$size = filesize( $path );
		if ( false === $size ) {
			return '';
		}

		if ( $size <= $max_bytes ) {
			$contents = file_get_contents( $path );
			return false === $contents ? '' : (string) $contents;
		}

		$handle = fopen( $path, 'rb' );
		if ( false === $handle ) {
			return '';
		}

		fseek( $handle, -1 * (int) $max_bytes, SEEK_END );
		$contents = fread( $handle, (int) $max_bytes );
		fclose( $handle );

		if ( false === $contents ) {
			return '';
		}

		$contents = (string) $contents;

		// Avoid showing a cut-off first line in the UI.
		$first_newline = strpos( $contents, "\n" );
		if ( false !== $first_newline ) {
			$contents = substr( $contents, $first_newline + 1 );
		}

		return $contents;
	}

	/**
	 * Deletes the API log file if present.
	 *
	 * @return bool True when deleted or file was absent.
	 */
	public static function clear_api_log() {
		$path = self::get_api_log_path();

		if ( '' === $path || ! file_exists( $path ) ) {
			return true;
		}

		return (bool) wp_delete_file( $path );
	}

	/**
	 * Sanitizes potentially sensitive values before writing log entries.
	 *
	 * @param mixed $value Value to sanitize.
	 * @return mixed
	 */
	private static function sanitize_log_value( $value ) {
		$sensitive_keys = array(
			'authorization',
			'access_token',
			'refresh_token',
			'client_secret',
			'password',
			'code_verifier',
		);

		if ( is_array( $value ) ) {
			$sanitized = array();
			foreach ( $value as $key => $item ) {
				$key_lc = strtolower( (string) $key );
				if ( in_array( $key_lc, $sensitive_keys, true ) ) {
					$sanitized[ $key ] = '[redacted]';
					continue;
				}

				$sanitized[ $key ] = self::sanitize_log_value( $item );
			}

			return $sanitized;
		}

		if ( is_string( $value ) ) {
			if ( strlen( $value ) > 5000 ) {
				return substr( $value, 0, 5000 ) . '...[truncated]';
			}

			return $value;
		}

		return $value;
	}

	/**
	 * Writes a single structured JSON log line for an API request.
	 *
	 * @param string          $service Service label (salesforce|pardot).
	 * @param string          $method  HTTP method.
	 * @param string          $url     Request URL.
	 * @param array           $args    Sanitized request args.
	 * @param array|\WP_Error $result  HTTP response or WP_Error.
	 * @return void
	 */
	private static function log_http_transaction( $service, $method, $url, $args, $result ) {
		if ( ! self::is_api_logging_enabled() ) {
			return;
		}

		$path = self::get_api_log_path();
		if ( '' === $path ) {
			return;
		}

		$entry = array(
			'timestamp' => gmdate( 'c' ),
			'service'   => $service,
			'method'    => strtoupper( $method ),
			'url'       => $url,
			'request'   => self::sanitize_log_value( $args ),
		);

		if ( is_wp_error( $result ) ) {
			$entry['error'] = $result->get_error_message();
		} else {
			$body              = wp_remote_retrieve_body( $result );
			$entry['status']   = (int) wp_remote_retrieve_response_code( $result );
			$entry['response'] = self::sanitize_log_value( json_decode( $body, true ) ?: $body );
		}

		$line = wp_json_encode( $entry, JSON_UNESCAPED_SLASHES );
		if ( false === $line ) {
			return;
		}

		$line .= PHP_EOL;

		$file = @fopen( $path, 'ab' );
		if ( false === $file ) {
			return;
		}

		flock( $file, LOCK_EX );
		fwrite( $file, $line );
		flock( $file, LOCK_UN );
		fclose( $file );
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
		$scopes         = 'pardot_api refresh_token';

		// Opt-in scope needed for Salesforce REST queries (e.g. Business Unit auto-discovery).
		if ( (bool) get_option( 'big_orange_pardot_enable_salesforce_api_scope', false ) ) {
			$scopes = 'api pardot_api refresh_token';
		}

		update_option( 'big_orange_pardot_pkce_verifier', $code_verifier, false );

		return add_query_arg(
			array(
				'response_type'         => 'code',
				'client_id'             => self::get_client_id(),
				'redirect_uri'          => rawurlencode( $redirect_uri ),
				'scope'                 => $scopes,
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

		self::log_http_transaction( 'salesforce', 'POST', self::TOKEN_URL, array( 'body' => $body ), $response );

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

		self::log_http_transaction(
			'salesforce',
			'POST',
			self::TOKEN_URL,
			array(
				'body' => array(
					'grant_type'    => 'refresh_token',
					'client_id'     => self::get_client_id(),
					'client_secret' => self::get_client_secret(),
					'refresh_token' => $refresh_token,
				),
			),
			$response
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

		if ( ! empty( $body['instance_url'] ) ) {
			update_option( 'big_orange_pardot_instance_url', esc_url_raw( (string) $body['instance_url'] ), false );
		}

		if ( ! empty( $body['refresh_token'] ) ) {
			update_option( 'big_orange_pardot_refresh_token', $body['refresh_token'], false );
		}

		delete_transient( 'big_orange_pardot_business_units' );

		return true;
	}

	/**
	 * Returns the stored Salesforce instance URL.
	 *
	 * @return string
	 */
	public static function get_salesforce_instance_url() {
		return self::get_opt( 'instance_url' );
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

		self::log_http_transaction(
			'pardot',
			'GET',
			$url,
			array(
				'headers' => array(
					'Authorization'           => 'Bearer ' . $token,
					'Pardot-Business-Unit-Id' => self::get_business_unit_id(),
				),
				'timeout' => 15,
			),
			$response
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

	// -------------------------------------------------------------------------
	// Salesforce Business Units
	// -------------------------------------------------------------------------

	/**
	 * Returns Pardot Business Units from Salesforce, optionally forcing a refresh.
	 *
	 * @param bool $force_refresh Whether to bypass the transient cache.
	 * @return array|\WP_Error Array of arrays with keys: id, name.
	 */
	public static function get_business_units( $force_refresh = false ) {
		if ( ! $force_refresh ) {
			$cached = get_transient( 'big_orange_pardot_business_units' );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		$token = self::get_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$instance_url = self::get_salesforce_instance_url();
		if ( '' === $instance_url ) {
			return new \WP_Error( 'missing_instance_url', __( 'Salesforce instance URL is not available yet. Please reconnect to Salesforce.', 'big-orange-pardot' ) );
		}

		$query = 'SELECT Id, Name FROM PardotBusinessUnit ORDER BY Name';
		$url   = trailingslashit( untrailingslashit( $instance_url ) ) . 'services/data/' . self::SALESFORCE_API_VERSION . '/query';
		$url   = add_query_arg( 'q', $query, $url );

		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
				),
				'timeout' => 15,
			)
		);

		self::log_http_transaction(
			'salesforce',
			'GET',
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
				),
				'timeout' => 15,
			),
			$response
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( (int) $code < 200 || (int) $code >= 300 ) {
			if ( ! empty( $body[0]['message'] ) ) {
				$message = (string) $body[0]['message'];
			} elseif ( ! empty( $body['message'] ) ) {
				$message = (string) $body['message'];
			} else {
				$message = sprintf(
					/* translators: %d: HTTP status code */
					__( 'Salesforce API returned HTTP %d while loading Business Units.', 'big-orange-pardot' ),
					$code
				);
			}

			if ( false !== stripos( $message, 'sObject type' ) && false !== stripos( $message, 'PardotBusinessUnit' ) && false !== stripos( $message, 'not supported' ) ) {
				return new \WP_Error(
					'business_units_not_supported',
					__( 'Connected to Salesforce, but this org cannot query Account Engagement Business Units via API. Please paste your Business Unit ID manually.', 'big-orange-pardot' )
				);
			}

			return new \WP_Error( 'business_units_error', $message );
		}

		$business_units = array();
		foreach ( (array) ( $body['records'] ?? array() ) as $record ) {
			if ( empty( $record['Id'] ) ) {
				continue;
			}

			$business_units[] = array(
				'id'   => (string) $record['Id'],
				'name' => (string) ( $record['Name'] ?? $record['Id'] ),
			);
		}

		set_transient( 'big_orange_pardot_business_units', $business_units, 15 * MINUTE_IN_SECONDS );

		return $business_units;
	}
}
