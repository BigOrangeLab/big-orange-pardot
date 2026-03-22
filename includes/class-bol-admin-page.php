<?php
/**
 * Admin settings page — credentials, OAuth connection, and form handler inspector.
 *
 * @package BigOrangePardot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the Settings → Big Orange Pardot admin page and handles the OAuth flow.
 */
class BOL_Admin_Page {

	/**
	 * Settings page slug.
	 *
	 * @var string
	 */
	const SLUG = 'big-orange-pardot';

	/**
	 * Nonce action for the credentials form.
	 *
	 * @var string
	 */
	const NONCE_CREDENTIALS = 'bol_save_credentials';

	/**
	 * Nonce action for the disconnect action.
	 *
	 * @var string
	 */
	const NONCE_DISCONNECT = 'bol_disconnect';

	/**
	 * Registers all admin hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_requests' ) );
		add_filter( 'plugin_action_links_big-orange-pardot/big-orange-pardot.php', array( $this, 'add_settings_link' ) );
	}

	// -------------------------------------------------------------------------
	// Menu
	// -------------------------------------------------------------------------

	/**
	 * Registers the Settings sub-page.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_submenu_page(
			'options-general.php',
			__( 'Big Orange Pardot', 'big-orange-pardot' ),
			__( 'Big Orange Pardot', 'big-orange-pardot' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Adds a Settings link to the plugin row on the Plugins screen.
	 *
	 * @param string[] $links Existing action links.
	 * @return string[]
	 */
	public function add_settings_link( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $this->settings_url() ),
			esc_html__( 'Settings', 'big-orange-pardot' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}

	// -------------------------------------------------------------------------
	// Request handling (runs on admin_init, before output)
	// -------------------------------------------------------------------------

	/**
	 * Dispatches POST and OAuth callback requests.
	 *
	 * @return void
	 */
	public function handle_requests() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( self::SLUG !== $page ) {
			return;
		}

		// OAuth callback — Salesforce redirected back with ?code=&state=.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['code'], $_GET['state'] ) ) {
			$this->handle_oauth_callback(
				sanitize_text_field( wp_unslash( $_GET['code'] ) ),
				sanitize_text_field( wp_unslash( $_GET['state'] ) )
			);
			return;
		}
		// phpcs:enable

		$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		if ( 'POST' !== $request_method ) {
			return;
		}

		// Save credentials.
		if ( isset( $_POST['bol_save_credentials'] ) ) {
			check_admin_referer( self::NONCE_CREDENTIALS );
			$this->save_credentials();
			return;
		}

		// Initiate OAuth.
		if ( isset( $_POST['bol_connect'] ) ) {
			check_admin_referer( self::NONCE_CREDENTIALS );
			$this->initiate_oauth();
			return;
		}

		// Disconnect.
		if ( isset( $_POST['bol_disconnect'] ) ) {
			check_admin_referer( self::NONCE_DISCONNECT );
			BOL_Pardot_API::disconnect();
			wp_safe_redirect( $this->settings_url( 'disconnected' ) );
			exit;
		}
	}

	/**
	 * Saves client_id, client_secret, and business_unit_id from POST data.
	 *
	 * @return void
	 */
	private function save_credentials() {
		$fields = array( 'client_id', 'client_secret', 'business_unit_id' );
		foreach ( $fields as $field ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce checked via check_admin_referer() in handle_requests() before this method is called.
			$value = isset( $_POST[ 'bol_' . $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'bol_' . $field ] ) ) : '';
			update_option( 'big_orange_pardot_' . $field, $value, false );
		}
		wp_safe_redirect( $this->settings_url( 'saved' ) );
		exit;
	}

	/**
	 * Generates a state nonce and redirects to the Salesforce authorization URL.
	 *
	 * @return void
	 */
	private function initiate_oauth() {
		$state = wp_generate_password( 32, false );
		update_option( 'big_orange_pardot_oauth_state', $state, false );

		$redirect_uri  = $this->settings_url();
		$authorize_url = BOL_Pardot_API::get_authorize_url( $redirect_uri, $state );

		wp_redirect( $authorize_url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- external OAuth URL.
		exit;
	}

	/**
	 * Validates the OAuth callback, exchanges the code for tokens, and redirects.
	 *
	 * @param string $code  Authorization code from Salesforce.
	 * @param string $state State parameter for CSRF validation.
	 * @return void
	 */
	private function handle_oauth_callback( $code, $state ) {
		$expected_state = (string) get_option( 'big_orange_pardot_oauth_state', '' );

		if ( '' === $expected_state || ! hash_equals( $expected_state, $state ) ) {
			wp_safe_redirect( $this->settings_url( 'oauth_state_mismatch' ) );
			exit;
		}

		delete_option( 'big_orange_pardot_oauth_state' );

		$redirect_uri = $this->settings_url();
		$result       = BOL_Pardot_API::exchange_code( $code, $redirect_uri );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( $this->settings_url( 'oauth_error', $result->get_error_message() ) );
			exit;
		}

		wp_safe_redirect( $this->settings_url( 'connected' ) );
		exit;
	}

	// -------------------------------------------------------------------------
	// Page rendering
	// -------------------------------------------------------------------------

	/**
	 * Renders the full settings page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap bol-pardot-admin">
			<h1><?php esc_html_e( 'Big Orange Pardot', 'big-orange-pardot' ); ?></h1>

			<?php $this->render_notices(); ?>

			<form method="post" action="">
				<?php wp_nonce_field( self::NONCE_CREDENTIALS ); ?>

				<h2><?php esc_html_e( 'API Credentials', 'big-orange-pardot' ); ?></h2>
				<p class="description">
					<?php
					printf(
						/* translators: %s: link to Salesforce Connected App documentation */
						esc_html__( 'Enter the credentials from your Salesforce %s. The redirect URI to register is shown below.', 'big-orange-pardot' ),
						'<a href="https://help.salesforce.com/s/articleView?id=sf.connected_app_create.htm&type=5" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Connected App', 'big-orange-pardot' ) . '</a>'
					);
					?>
				</p>
				<p>
					<strong><?php esc_html_e( 'Redirect URI to register:', 'big-orange-pardot' ); ?></strong>
					<code><?php echo esc_html( $this->settings_url() ); ?></code>
				</p>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="bol_client_id"><?php esc_html_e( 'Consumer Key (Client ID)', 'big-orange-pardot' ); ?></label>
						</th>
						<td>
							<input type="text" id="bol_client_id" name="bol_client_id" class="regular-text"
								value="<?php echo esc_attr( BOL_Pardot_API::get_client_id() ); ?>" autocomplete="off" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="bol_client_secret"><?php esc_html_e( 'Consumer Secret (Client Secret)', 'big-orange-pardot' ); ?></label>
						</th>
						<td>
							<input type="password" id="bol_client_secret" name="bol_client_secret" class="regular-text"
								value="<?php echo esc_attr( BOL_Pardot_API::get_client_secret() ); ?>" autocomplete="off" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="bol_business_unit_id"><?php esc_html_e( 'Business Unit ID', 'big-orange-pardot' ); ?></label>
						</th>
						<td>
							<input type="text" id="bol_business_unit_id" name="bol_business_unit_id" class="regular-text"
								value="<?php echo esc_attr( BOL_Pardot_API::get_business_unit_id() ); ?>" autocomplete="off"
								placeholder="0Uv..." />
							<p class="description"><?php esc_html_e( '18-character ID starting with 0Uv. Found in Salesforce Setup under Account Engagement → Business Unit Setup.', 'big-orange-pardot' ); ?></p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" name="bol_save_credentials" class="button button-secondary">
						<?php esc_html_e( 'Save Credentials', 'big-orange-pardot' ); ?>
					</button>
					<?php if ( BOL_Pardot_API::get_client_id() && ! BOL_Pardot_API::is_connected() ) : ?>
						<button type="submit" name="bol_connect" class="button button-primary">
							<?php esc_html_e( 'Connect to Pardot', 'big-orange-pardot' ); ?>
						</button>
					<?php endif; ?>
				</p>
			</form>

			<?php $this->render_connection_section(); ?>
			<?php $this->render_inspector_section(); ?>
		</div>
		<?php
	}

	/**
	 * Renders the connection status section.
	 *
	 * @return void
	 */
	private function render_connection_section() {
		?>
		<hr />
		<h2><?php esc_html_e( 'Pardot Connection', 'big-orange-pardot' ); ?></h2>

		<?php if ( BOL_Pardot_API::is_connected() ) : ?>
			<p>
				<span class="bol-status bol-status--connected">&#x2714; <?php esc_html_e( 'Connected', 'big-orange-pardot' ); ?></span>
			</p>
			<form method="post" action="">
				<?php wp_nonce_field( self::NONCE_DISCONNECT ); ?>
				<button type="submit" name="bol_disconnect" class="button button-secondary">
					<?php esc_html_e( 'Disconnect', 'big-orange-pardot' ); ?>
				</button>
			</form>
		<?php else : ?>
			<p>
				<span class="bol-status bol-status--disconnected">&#x2717; <?php esc_html_e( 'Not connected', 'big-orange-pardot' ); ?></span>
			</p>
			<?php if ( ! BOL_Pardot_API::get_client_id() ) : ?>
				<p class="description"><?php esc_html_e( 'Save your credentials above, then click Connect to Pardot.', 'big-orange-pardot' ); ?></p>
			<?php endif; ?>
		<?php endif; ?>
		<?php
	}

	/**
	 * Renders the Form Handler Inspector section (only when connected).
	 *
	 * @return void
	 */
	private function render_inspector_section() {
		if ( ! BOL_Pardot_API::is_connected() ) {
			return;
		}
		?>
		<hr />
		<h2><?php esc_html_e( 'Form Handler Inspector', 'big-orange-pardot' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Select a form handler to see which attribution fields your plugin submits are actually mapped in Pardot.', 'big-orange-pardot' ); ?>
		</p>

		<?php
		$handlers = BOL_Pardot_API::get_form_handlers();

		if ( is_wp_error( $handlers ) ) {
			printf(
				'<div class="notice notice-error inline"><p>%s</p></div>',
				esc_html( $handlers->get_error_message() )
			);
			return;
		}

		if ( empty( $handlers ) ) {
			echo '<p>' . esc_html__( 'No form handlers found in your Pardot account.', 'big-orange-pardot' ) . '</p>';
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$selected_id = isset( $_GET['handler_id'] ) ? (int) $_GET['handler_id'] : 0;
		?>

		<form method="get" action="">
			<input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG ); ?>" />
			<select name="handler_id" id="bol_handler_id">
				<option value="0"><?php esc_html_e( '— Select a form handler —', 'big-orange-pardot' ); ?></option>
				<?php foreach ( $handlers as $handler ) : ?>
					<option value="<?php echo esc_attr( $handler['id'] ); ?>"
						<?php selected( $selected_id, $handler['id'] ); ?>>
						<?php echo esc_html( $handler['name'] ); ?>
						<?php if ( ! empty( $handler['url'] ) ) : ?>
							(<?php echo esc_html( $handler['url'] ); ?>)
						<?php endif; ?>
					</option>
				<?php endforeach; ?>
			</select>
			<button type="submit" class="button button-secondary">
				<?php esc_html_e( 'Inspect', 'big-orange-pardot' ); ?>
			</button>
		</form>

		<?php if ( $selected_id > 0 ) : ?>
			<?php $this->render_field_table( $selected_id ); ?>
		<?php endif; ?>
		<?php
	}

	/**
	 * Renders the field mapping table for a specific form handler.
	 *
	 * @param int $handler_id Pardot form handler ID.
	 * @return void
	 */
	private function render_field_table( $handler_id ) {
		$fields = BOL_Pardot_API::get_form_handler_fields( $handler_id );

		if ( is_wp_error( $fields ) ) {
			printf(
				'<div class="notice notice-error inline"><p>%s</p></div>',
				esc_html( $fields->get_error_message() )
			);
			return;
		}

		// Build a lookup of Pardot external field names (the 'name' on each field object).
		$mapped_names = array_map(
			static function ( $field ) {
				return strtolower( (string) ( $field['name'] ?? '' ) );
			},
			$fields
		);

		$attribution_fields = BOL_Pardot_API::ATTRIBUTION_FIELDS;

		// Determine which attribution fields are mapped.
		$attribution_status = array();
		foreach ( $attribution_fields as $attr_field ) {
			$attribution_status[ $attr_field ] = in_array( strtolower( $attr_field ), $mapped_names, true );
		}

		$all_mapped = ! in_array( false, $attribution_status, true );
		?>
		<h3><?php esc_html_e( 'Attribution Field Mapping', 'big-orange-pardot' ); ?></h3>

		<?php if ( $all_mapped ) : ?>
			<div class="notice notice-success inline"><p>
				<?php esc_html_e( 'All attribution fields are mapped in this form handler.', 'big-orange-pardot' ); ?>
			</p></div>
		<?php else : ?>
			<div class="notice notice-warning inline"><p>
				<?php esc_html_e( 'Some attribution fields are not mapped. Unmapped fields will be silently ignored by Pardot.', 'big-orange-pardot' ); ?>
			</p></div>
		<?php endif; ?>

		<table class="widefat striped bol-pardot-field-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Field Name (submitted by plugin)', 'big-orange-pardot' ); ?></th>
					<th><?php esc_html_e( 'Mapped in Pardot?', 'big-orange-pardot' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $attribution_status as $field_name => $is_mapped ) : ?>
					<tr class="<?php echo $is_mapped ? 'bol-mapped' : 'bol-unmapped'; ?>">
						<td><code><?php echo esc_html( $field_name ); ?></code></td>
						<td>
							<?php if ( $is_mapped ) : ?>
								<span class="bol-status bol-status--connected">&#x2714; <?php esc_html_e( 'Mapped', 'big-orange-pardot' ); ?></span>
							<?php else : ?>
								<span class="bol-status bol-status--disconnected">&#x2717; <?php esc_html_e( 'Not mapped', 'big-orange-pardot' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<h3><?php esc_html_e( 'All Form Handler Fields', 'big-orange-pardot' ); ?></h3>
		<?php if ( empty( $fields ) ) : ?>
			<p><?php esc_html_e( 'No fields configured for this form handler.', 'big-orange-pardot' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'big-orange-pardot' ); ?></th>
						<th><?php esc_html_e( 'Prospect Field', 'big-orange-pardot' ); ?></th>
						<th><?php esc_html_e( 'Required?', 'big-orange-pardot' ); ?></th>
						<th><?php esc_html_e( 'Format', 'big-orange-pardot' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $fields as $field ) : ?>
						<tr>
							<td><code><?php echo esc_html( $field['name'] ?? '' ); ?></code></td>
							<td><?php echo esc_html( $field['prospectApiFieldId'] ?? '' ); ?></td>
							<td><?php echo ! empty( $field['isRequired'] ) ? esc_html__( 'Yes', 'big-orange-pardot' ) : esc_html__( 'No', 'big-orange-pardot' ); ?></td>
							<td><?php echo esc_html( $field['dataFormat'] ?? '' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
	}

	// -------------------------------------------------------------------------
	// Notices
	// -------------------------------------------------------------------------

	/**
	 * Renders admin notices based on the current ?notice= query parameter.
	 *
	 * @return void
	 */
	private function render_notices() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$notice = isset( $_GET['notice'] ) ? sanitize_key( $_GET['notice'] ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$error_msg = isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( $_GET['error'] ) ) : '';

		$messages = array(
			'saved'                => array( 'success', __( 'Credentials saved.', 'big-orange-pardot' ) ),
			'connected'            => array( 'success', __( 'Successfully connected to Pardot!', 'big-orange-pardot' ) ),
			'disconnected'         => array( 'success', __( 'Disconnected from Pardot.', 'big-orange-pardot' ) ),
			'oauth_state_mismatch' => array( 'error', __( 'OAuth state mismatch — possible CSRF attempt. Please try connecting again.', 'big-orange-pardot' ) ),
			'oauth_error'          => array( 'error', sprintf( /* translators: %s: error message */ __( 'OAuth error: %s', 'big-orange-pardot' ), esc_html( $error_msg ) ) ),
		);

		if ( isset( $messages[ $notice ] ) ) {
			list( $type, $text ) = $messages[ $notice ];
			printf(
				'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
				esc_attr( $type ),
				wp_kses_post( $text )
			);
		}
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Returns the URL to the settings page, optionally with a notice parameter.
	 *
	 * @param string $notice    Optional notice key.
	 * @param string $error_msg Optional error message (for oauth_error notice).
	 * @return string
	 */
	private function settings_url( $notice = '', $error_msg = '' ) {
		$args = array( 'page' => self::SLUG );
		if ( '' !== $notice ) {
			$args['notice'] = $notice;
		}
		if ( '' !== $error_msg ) {
			$args['error'] = rawurlencode( $error_msg );
		}
		return add_query_arg( $args, admin_url( 'options-general.php' ) );
	}
}
