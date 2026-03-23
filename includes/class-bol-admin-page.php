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
 *
 * The page has two tabs: Settings (credentials, connection, inspector) and Help
 * (user-facing documentation). Keep render_help_tab() up to date whenever the
 * plugin's behaviour or setup process changes.
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
	 * Nonce action for log management actions.
	 *
	 * @var string
	 */
	const NONCE_LOGS = 'bol_logs';

	/**
	 * Registers all admin hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_requests' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_filter( 'plugin_action_links_big-orange-pardot/big-orange-pardot.php', array( $this, 'add_settings_link' ) );
	}

	/**
	 * Enqueues admin assets for the Logs tab.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		if ( 'settings_page_' . self::SLUG !== $hook_suffix ) {
			return;
		}

		if ( 'logs' !== $this->current_tab() ) {
			return;
		}

		$plugin_file = dirname( __DIR__ ) . '/big-orange-pardot.php';
		$asset_file  = dirname( __DIR__ ) . '/build/log-viewer.asset.php';
		$asset_data  = file_exists( $asset_file ) ? include $asset_file : array(
			'dependencies' => array( 'wp-dom-ready', 'wp-element', 'wp-i18n', 'wp-components' ),
			'version'      => '1.0.1',
		);

		$deps = isset( $asset_data['dependencies'] ) && is_array( $asset_data['dependencies'] ) ? $asset_data['dependencies'] : array();
		$deps = array_values(
			array_filter(
				$deps,
				static function ( $dep ) {
					return wp_script_is( $dep, 'registered' );
				}
			)
		);

		$version = isset( $asset_data['version'] ) ? (string) $asset_data['version'] : '1.0.1';

		wp_enqueue_script(
			'big-orange-pardot-log-viewer',
			plugins_url( 'build/log-viewer.js', $plugin_file ),
			$deps,
			$version,
			true
		);

		wp_enqueue_style(
			'big-orange-pardot-log-viewer',
			plugins_url( 'build/log-viewer.css', $plugin_file ),
			array( 'wp-components' ),
			$version
		);

		wp_add_inline_script(
			'big-orange-pardot-log-viewer',
			'window.bolPardotLogsPageConfig = ' . wp_json_encode(
				array(
					'items' => BOL_Pardot_API::get_api_log_entries( 1000 ),
				)
			) . ';',
			'before'
		);
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
		if ( isset( $_GET['error'], $_GET['state'] ) && ! isset( $_GET['notice'] ) ) {
			$error_description = isset( $_GET['error_description'] ) ? sanitize_text_field( wp_unslash( $_GET['error_description'] ) ) : '';
			$error_message     = sanitize_text_field( wp_unslash( $_GET['error'] ) );

			if ( '' !== $error_description ) {
				$error_message .= ': ' . $error_description;
			}

			wp_safe_redirect( $this->settings_url( 'oauth_error', $error_message ) );
			exit;
		}

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

		// Clear API log and disable logging.
		if ( isset( $_POST['bol_clear_api_log'] ) ) {
			check_admin_referer( self::NONCE_LOGS );
			update_option( 'big_orange_pardot_enable_api_logging', '0', false );

			BOL_Pardot_API::clear_api_log();
			wp_safe_redirect( $this->tab_url( 'logs' ) . '&notice=log_cleared' );
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

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce checked via check_admin_referer() in handle_requests() before this method is called.
		$enable_salesforce_api_scope = isset( $_POST['bol_enable_salesforce_api_scope'] ) ? '1' : '0';
		update_option( 'big_orange_pardot_enable_salesforce_api_scope', $enable_salesforce_api_scope, false );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce checked via check_admin_referer() in handle_requests() before this method is called.
		$enable_api_logging = isset( $_POST['bol_enable_api_logging'] ) ? '1' : '0';
		update_option( 'big_orange_pardot_enable_api_logging', $enable_api_logging, false );

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

		$notice             = 'connected';
		$current_business_id = BOL_Pardot_API::get_business_unit_id();
		$business_units     = BOL_Pardot_API::get_business_units( true );

		if ( is_wp_error( $business_units ) ) {
			if ( 'business_units_not_supported' === $business_units->get_error_code() ) {
				update_option( 'big_orange_pardot_enable_salesforce_api_scope', '0', false );
				wp_safe_redirect( $this->settings_url( 'connected_business_unit_not_supported' ) );
				exit;
			}

			wp_safe_redirect( $this->settings_url( 'connected_business_unit_lookup_failed', $business_units->get_error_message() ) );
			exit;
		}

		if ( '' === $current_business_id && 1 === count( $business_units ) ) {
			update_option( 'big_orange_pardot_business_unit_id', (string) $business_units[0]['id'], false );
			$notice = 'connected_business_unit_set';
		} elseif ( '' === $current_business_id && count( $business_units ) > 1 ) {
			$notice = 'connected_select_business_unit';
		}

		wp_safe_redirect( $this->settings_url( $notice ) );
		exit;
	}

	// -------------------------------------------------------------------------
	// Page rendering
	// -------------------------------------------------------------------------

	/**
	 * Returns the active tab slug, defaulting to 'settings'.
	 *
	 * @return string
	 */
	private function current_tab() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'settings';
		return in_array( $tab, array( 'settings', 'help', 'logs' ), true ) ? $tab : 'settings';
	}

	/**
	 * Returns the URL to a specific tab on the settings page.
	 *
	 * @param string $tab Tab slug.
	 * @return string
	 */
	private function tab_url( $tab ) {
		return add_query_arg(
			array(
				'page' => self::SLUG,
				'tab'  => $tab,
			),
			admin_url( 'options-general.php' )
		);
	}

	/**
	 * Renders the full settings page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$current_tab = $this->current_tab();
		?>
		<div class="wrap bol-pardot-admin">
			<h1><?php esc_html_e( 'Big Orange Pardot', 'big-orange-pardot' ); ?></h1>

			<nav class="nav-tab-wrapper">
				<a href="<?php echo esc_url( $this->tab_url( 'settings' ) ); ?>"
					class="nav-tab<?php echo 'settings' === $current_tab ? ' nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Settings', 'big-orange-pardot' ); ?>
				</a>
				<a href="<?php echo esc_url( $this->tab_url( 'help' ) ); ?>"
					class="nav-tab<?php echo 'help' === $current_tab ? ' nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Help', 'big-orange-pardot' ); ?>
				</a>
				<a href="<?php echo esc_url( $this->tab_url( 'logs' ) ); ?>"
					class="nav-tab<?php echo 'logs' === $current_tab ? ' nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Logs', 'big-orange-pardot' ); ?>
				</a>
			</nav>

			<?php if ( 'help' === $current_tab ) : ?>
				<?php $this->render_help_tab(); ?>
			<?php elseif ( 'logs' === $current_tab ) : ?>
				<?php $this->render_logs_tab(); ?>
			<?php else : ?>
				<?php $this->render_settings_tab(); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renders the Settings tab content.
	 *
	 * @return void
	 */
	private function render_settings_tab() {
		$business_units       = array();
		$business_units_error = '';
		$enable_sf_api_scope  = (bool) get_option( 'big_orange_pardot_enable_salesforce_api_scope', false );
		$enable_api_logging   = BOL_Pardot_API::is_api_logging_enabled();

		if ( BOL_Pardot_API::is_connected() ) {
			$business_units = BOL_Pardot_API::get_business_units();
			if ( is_wp_error( $business_units ) ) {
				if ( 'business_units_not_supported' === $business_units->get_error_code() ) {
					update_option( 'big_orange_pardot_enable_salesforce_api_scope', '0', false );
					$enable_sf_api_scope  = false;
					$business_units_error = __( 'This Salesforce org does not expose Business Units via API. Enter your Business Unit ID manually.', 'big-orange-pardot' );
				} else {
					$business_units_error = $business_units->get_error_message();
				}
				$business_units       = array();
			}
		}

		?>
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
						<?php if ( ! empty( $business_units ) ) : ?>
							<select id="bol_business_unit_id" name="bol_business_unit_id" class="regular-text">
								<option value=""><?php esc_html_e( '— Select a Business Unit —', 'big-orange-pardot' ); ?></option>
								<?php foreach ( $business_units as $business_unit ) : ?>
									<option value="<?php echo esc_attr( $business_unit['id'] ); ?>" <?php selected( BOL_Pardot_API::get_business_unit_id(), $business_unit['id'] ); ?>>
										<?php echo esc_html( $business_unit['name'] ); ?> (<?php echo esc_html( $business_unit['id'] ); ?>)
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Business units were loaded automatically from Salesforce after connecting.', 'big-orange-pardot' ); ?></p>
						<?php else : ?>
							<input type="text" id="bol_business_unit_id" name="bol_business_unit_id" class="regular-text"
								value="<?php echo esc_attr( BOL_Pardot_API::get_business_unit_id() ); ?>" autocomplete="off"
								placeholder="0Uv..." />
							<p class="description"><?php esc_html_e( '18-character ID starting with 0Uv. Connect first to auto-load your business units, or paste an ID manually from Salesforce Setup under Account Engagement → Business Unit Setup.', 'big-orange-pardot' ); ?></p>
						<?php endif; ?>
						<?php if ( '' !== $business_units_error ) : ?>
							<p class="description" style="color:#b32d2e;"><?php echo esc_html( $business_units_error ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="bol_enable_salesforce_api_scope"><?php esc_html_e( 'Auto-discover Business Units', 'big-orange-pardot' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" id="bol_enable_salesforce_api_scope" name="bol_enable_salesforce_api_scope" value="1" <?php checked( $enable_sf_api_scope ); ?> />
							<?php esc_html_e( 'Request Salesforce API scope (api) during connect so business units can be loaded automatically.', 'big-orange-pardot' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'If connecting fails with "invalid_scope", uncheck this and reconnect. You can still paste a Business Unit ID manually.', 'big-orange-pardot' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="bol_enable_api_logging"><?php esc_html_e( 'API Logging', 'big-orange-pardot' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" id="bol_enable_api_logging" name="bol_enable_api_logging" value="1" <?php checked( $enable_api_logging ); ?> />
							<?php esc_html_e( 'Log Salesforce/Pardot API requests to the uploads directory.', 'big-orange-pardot' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Logs may contain metadata about requests and responses. Sensitive values like tokens and secrets are redacted.', 'big-orange-pardot' ); ?></p>
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
		<?php
	}

	/**
	 * Renders the Logs tab content.
	 *
	 * @return void
	 */
	private function render_logs_tab() {
		$log_path        = BOL_Pardot_API::get_api_log_path();
		$log_exists      = '' !== $log_path && file_exists( $log_path );
		$log_is_readable = $log_exists && is_readable( $log_path );
		$log_size        = $log_exists ? (int) filesize( $log_path ) : 0;
		$log_entries     = $log_is_readable ? BOL_Pardot_API::get_api_log_entries( 1000 ) : array();
		?>
		<?php $this->render_notices(); ?>

		<h2><?php esc_html_e( 'API Logs', 'big-orange-pardot' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Use this tab to inspect Salesforce/Pardot API activity when troubleshooting connection and data issues.', 'big-orange-pardot' ); ?>
		</p>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Logging status', 'big-orange-pardot' ); ?></th>
				<td><?php echo BOL_Pardot_API::is_api_logging_enabled() ? esc_html__( 'Enabled', 'big-orange-pardot' ) : esc_html__( 'Disabled', 'big-orange-pardot' ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Log file path', 'big-orange-pardot' ); ?></th>
				<td><code><?php echo esc_html( '' !== $log_path ? $log_path : __( '(unavailable)', 'big-orange-pardot' ) ); ?></code></td>
			</tr>
			<?php if ( $log_exists ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Log size', 'big-orange-pardot' ); ?></th>
					<td><?php echo esc_html( size_format( $log_size ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Parsed entries', 'big-orange-pardot' ); ?></th>
					<td><?php echo esc_html( number_format_i18n( count( $log_entries ) ) ); ?></td>
				</tr>
			<?php endif; ?>
		</table>

		<form method="post" action="">
			<?php wp_nonce_field( self::NONCE_LOGS ); ?>
			<p class="submit">
				<button type="submit" name="bol_clear_api_log" class="button button-secondary">
					<?php esc_html_e( 'Delete Log and Disable Logging', 'big-orange-pardot' ); ?>
				</button>
			</p>
		</form>

		<?php if ( ! $log_exists ) : ?>
			<p><?php esc_html_e( 'No log file exists yet.', 'big-orange-pardot' ); ?></p>
		<?php elseif ( ! $log_is_readable ) : ?>
			<div class="notice notice-error inline"><p>
				<?php esc_html_e( 'The log file exists but is not readable by WordPress.', 'big-orange-pardot' ); ?>
			</p></div>
		<?php elseif ( empty( $log_entries ) ) : ?>
			<p><?php esc_html_e( 'The log file is empty.', 'big-orange-pardot' ); ?></p>
		<?php else : ?>
			<div id="bol-pardot-log-viewer-app">
				<p><?php esc_html_e( 'Loading log viewer…', 'big-orange-pardot' ); ?></p>
			</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * Renders the Help tab content.
	 *
	 * @return void
	 */
	private function render_help_tab() {
		$redirect_uri = $this->settings_url();
		?>
		<style>
			.bol-help-toc { background: #f6f7f7; border: 1px solid #dcdcde; border-radius: 4px; display: inline-block; margin: 1.5em 0; padding: 1em 1.5em; }
			.bol-help-toc p { font-weight: 600; margin: 0 0 .5em; }
			.bol-help-toc ol { list-style: decimal; margin: 0; padding-left: 1.5em; }
			.bol-help-toc ol ol { list-style: lower-alpha; margin-top: .25em; }
			.bol-help-toc li { margin-bottom: .25em; }
		</style>
		<div class="bol-help">

			<nav class="bol-help-toc" aria-label="<?php esc_attr_e( 'Table of contents', 'big-orange-pardot' ); ?>">
				<p><?php esc_html_e( 'Contents', 'big-orange-pardot' ); ?></p>
				<ol>
					<li><a href="#bol-help-overview"><?php esc_html_e( 'Overview', 'big-orange-pardot' ); ?></a></li>
					<li>
						<a href="#bol-help-setup"><?php esc_html_e( 'Setup: Connecting to Salesforce', 'big-orange-pardot' ); ?></a>
						<ol>
							<li><a href="#bol-help-setup-step1"><?php esc_html_e( 'Step 1 — Create a Salesforce Connected App', 'big-orange-pardot' ); ?></a></li>
							<li><a href="#bol-help-setup-step2"><?php esc_html_e( 'Step 2 — Find your Business Unit ID', 'big-orange-pardot' ); ?></a></li>
							<li><a href="#bol-help-setup-step3"><?php esc_html_e( 'Step 3 — Enter credentials and authorize', 'big-orange-pardot' ); ?></a></li>
						</ol>
					</li>
					<li>
						<a href="#bol-help-block"><?php esc_html_e( 'Using the Block', 'big-orange-pardot' ); ?></a>
						<ol>
							<li><a href="#bol-help-block-connected"><?php esc_html_e( 'If connected to Pardot', 'big-orange-pardot' ); ?></a></li>
							<li><a href="#bol-help-block-unconnected"><?php esc_html_e( 'If not connected to Pardot', 'big-orange-pardot' ); ?></a></li>
							<li><a href="#bol-help-block-fields"><?php esc_html_e( 'Customising fields', 'big-orange-pardot' ); ?></a></li>
							<li><a href="#bol-help-block-field-styling"><?php esc_html_e( 'Field styling', 'big-orange-pardot' ); ?></a></li>
							<li><a href="#bol-help-block-form-styling"><?php esc_html_e( 'Form-level styling', 'big-orange-pardot' ); ?></a></li>
							<li><a href="#bol-help-block-button"><?php esc_html_e( 'Submit button styling', 'big-orange-pardot' ); ?></a></li>
						</ol>
					</li>
					<li><a href="#bol-help-attribution"><?php esc_html_e( 'Attribution Tracking', 'big-orange-pardot' ); ?></a></li>
					<li><a href="#bol-help-debugging"><?php esc_html_e( 'Testing &amp; Debugging', 'big-orange-pardot' ); ?></a></li>
				</ol>
			</nav>

			<h2 id="bol-help-overview"><?php esc_html_e( 'Overview', 'big-orange-pardot' ); ?></h2>
			<p><?php esc_html_e( 'Big Orange Pardot adds a Gutenberg block that embeds a Pardot (Account Engagement) form directly on any page or post. Rather than using an iframe, it renders a native HTML form that submits directly to your Pardot form handler, giving you full control over styling and layout.', 'big-orange-pardot' ); ?></p>
			<p><?php esc_html_e( 'The plugin also automatically captures marketing attribution data (UTM parameters, Google Click ID, landing page URL, and referrer) into cookies on every page load, then injects those values as hidden fields on any Pardot form found on the page — even forms loaded dynamically after the page renders.', 'big-orange-pardot' ); ?></p>

			<hr />

			<h2 id="bol-help-setup"><?php esc_html_e( 'Setup: Connecting to Salesforce', 'big-orange-pardot' ); ?></h2>
			<p>
				<?php
				printf(
					/* translators: %s: Salesforce OAuth documentation link */
					esc_html__( 'The plugin connects to Pardot using the %s. This requires creating a Connected App in Salesforce once, then authorizing it here. There is no simpler authentication path — Salesforce requires a Connected App for all API access.', 'big-orange-pardot' ),
					'<a href="https://help.salesforce.com/s/articleView?id=xcloud.remoteaccess_oauth_web_server_flow.htm&type=5" target="_blank" rel="noopener noreferrer">' . esc_html__( 'OAuth 2.0 Web Server Flow', 'big-orange-pardot' ) . '</a>'
				);
				?>
			</p>

			<h3 id="bol-help-setup-step1"><?php esc_html_e( 'Step 1 — Create a Salesforce Connected App', 'big-orange-pardot' ); ?></h3>
			<ol>
				<li><?php esc_html_e( 'In Salesforce, go to Setup → Apps → App Manager and click New Connected App.', 'big-orange-pardot' ); ?></li>
				<li><?php esc_html_e( 'Give it a name (e.g. "WordPress Pardot Integration") and fill in a contact email.', 'big-orange-pardot' ); ?></li>
				<li><?php esc_html_e( 'Under API (Enable OAuth Settings), check Enable OAuth Settings.', 'big-orange-pardot' ); ?></li>
				<li>
					<?php esc_html_e( 'Set the Callback URL (Redirect URI) to:', 'big-orange-pardot' ); ?>
					<br /><code><?php echo esc_html( $redirect_uri ); ?></code>
				</li>
				<li>
					<?php esc_html_e( 'Under Selected OAuth Scopes, add:', 'big-orange-pardot' ); ?>
					<ul style="list-style: disc; margin-left: 2em;">
						<li><strong>pardot_api</strong> — <?php esc_html_e( 'Access and manage your Pardot data', 'big-orange-pardot' ); ?></li>
						<li><strong>refresh_token, offline_access</strong> — <?php esc_html_e( 'Perform requests at any time (allows token refresh without re-authorization)', 'big-orange-pardot' ); ?></li>
						<li><strong>api</strong> — <?php esc_html_e( 'Optional: needed only if you want the plugin to auto-load Account Engagement Business Units.', 'big-orange-pardot' ); ?></li>
					</ul>
				</li>
				<li><?php esc_html_e( 'Save the Connected App. Salesforce may take a few minutes to activate it.', 'big-orange-pardot' ); ?></li>
				<li><?php esc_html_e( 'From the Connected App detail page, click Manage Consumer Details to view your Consumer Key and Consumer Secret.', 'big-orange-pardot' ); ?></li>
			</ol>

			<h3 id="bol-help-setup-step2"><?php esc_html_e( 'Step 2 — Find your Business Unit ID', 'big-orange-pardot' ); ?></h3>
			<ol>
				<li><?php esc_html_e( 'In Salesforce Setup, search for "Account Engagement" and go to Account Engagement → Business Unit Setup.', 'big-orange-pardot' ); ?></li>
				<li><?php esc_html_e( 'Copy the Business Unit ID — it is an 18-character value beginning with 0Uv. If you prefer, you can skip this and let the plugin load business units automatically after you connect.', 'big-orange-pardot' ); ?></li>
			</ol>

			<h3 id="bol-help-setup-step3"><?php esc_html_e( 'Step 3 — Enter credentials and authorize', 'big-orange-pardot' ); ?></h3>
			<ol>
				<li>
					<?php
					printf(
						/* translators: %s: link to the Settings tab */
						esc_html__( 'Go to the %s tab and enter your Consumer Key and Consumer Secret. You can enter a Business Unit ID now, or let the plugin load available business units after connecting. Click Save Credentials.', 'big-orange-pardot' ),
						'<a href="' . esc_url( $this->tab_url( 'settings' ) ) . '">' . esc_html__( 'Settings', 'big-orange-pardot' ) . '</a>'
					);
					?>
				</li>
				<li><?php esc_html_e( 'Click Connect to Pardot. You will be redirected to Salesforce to log in and approve access.', 'big-orange-pardot' ); ?></li>
				<li><?php esc_html_e( 'After approving, Salesforce redirects back here automatically. The connection status will show Connected.', 'big-orange-pardot' ); ?></li>
			</ol>
			<p class="description"><?php esc_html_e( 'Access tokens are refreshed automatically in the background — you should only need to authorize once unless you explicitly disconnect or revoke access in Salesforce.', 'big-orange-pardot' ); ?></p>

			<hr />

			<h2 id="bol-help-block"><?php esc_html_e( 'Using the Block', 'big-orange-pardot' ); ?></h2>
			<ol>
				<li><?php esc_html_e( 'In the block editor, insert the Big Orange Pardot block from the Widgets category (or search for "Pardot").', 'big-orange-pardot' ); ?></li>
				<li><?php esc_html_e( 'The block inserts with a default set of seven fields (First Name, Last Name, Email, Phone, Company, Job Title, Comments) plus a Submit button. Each field is its own block — you can reorder, delete, or add fields freely using the block editor.', 'big-orange-pardot' ); ?></li>
			</ol>

			<h3 id="bol-help-block-connected"><?php esc_html_e( 'If connected to Pardot', 'big-orange-pardot' ); ?></h3>
			<ol>
				<li><?php esc_html_e( 'Open the block settings panel. A dropdown lists all form handlers from your Pardot account. Select the handler this form should submit to — the submission URL is set automatically.', 'big-orange-pardot' ); ?></li>
				<li><?php esc_html_e( 'Any fields the handler expects that are not already in your form are added automatically when you select a handler. Review the added fields and adjust labels, types, or widths as needed.', 'big-orange-pardot' ); ?></li>
				<li><?php esc_html_e( 'The settings panel shows how many Pardot fields are present in your form. Use the "Add missing field(s)" button to insert any remaining expected fields.', 'big-orange-pardot' ); ?></li>
				<li><?php esc_html_e( 'To start fresh from Pardot\'s field list, click "Replace all with Pardot fields". This removes all current fields and rebuilds them from the handler\'s configuration.', 'big-orange-pardot' ); ?></li>
			</ol>

			<p class="description">
				<?php esc_html_e( 'Until a form handler is configured, the form is hidden from site visitors. Editors viewing the page while logged in will see a notice above a non-submittable preview of the form, so they can review the layout in context before the form goes live.', 'big-orange-pardot' ); ?>
			</p>

			<h3 id="bol-help-block-unconnected"><?php esc_html_e( 'If not connected to Pardot', 'big-orange-pardot' ); ?></h3>
			<ol>
				<li><?php esc_html_e( 'Open the block settings panel. Enter the Form Handler URL — found in your Pardot form handler\'s embed code — into the Form Handler URL field.', 'big-orange-pardot' ); ?></li>
				<li><?php esc_html_e( 'Open the "Common Pardot Fields" panel to quickly add standard Pardot field names to your form. Fields already present show a check mark; click "Add" for any you want to include.', 'big-orange-pardot' ); ?></li>
				<li><?php esc_html_e( 'You can also add fields manually via the block editor and type any Pardot field name directly into the Field Name setting on each field block.', 'big-orange-pardot' ); ?></li>
			</ol>
			<h3 id="bol-help-block-fields"><?php esc_html_e( 'Customising fields', 'big-orange-pardot' ); ?></h3>
			<p><?php esc_html_e( 'Select any Pardot Field block inside the form to edit it via the block settings panel. Available settings:', 'big-orange-pardot' ); ?></p>
			<ul style="list-style: disc; margin-left: 2em;">
				<li><strong><?php esc_html_e( 'Field Name (Pardot)', 'big-orange-pardot' ); ?></strong> — <?php esc_html_e( 'The HTML name attribute submitted to Pardot. Must match the field name in your form handler.', 'big-orange-pardot' ); ?></li>
				<li><strong><?php esc_html_e( 'Field Type', 'big-orange-pardot' ); ?></strong> — <?php esc_html_e( 'Text, Email, Phone, or Textarea.', 'big-orange-pardot' ); ?></li>
				<li><strong><?php esc_html_e( 'Required', 'big-orange-pardot' ); ?></strong> — <?php esc_html_e( 'Adds browser-side validation and a * indicator to the label.', 'big-orange-pardot' ); ?></li>
				<li><strong><?php esc_html_e( 'Placeholder', 'big-orange-pardot' ); ?></strong> — <?php esc_html_e( 'Placeholder text shown inside the input.', 'big-orange-pardot' ); ?></li>
				<li><strong><?php esc_html_e( 'Width', 'big-orange-pardot' ); ?></strong> — <?php esc_html_e( 'Full width (spans both grid columns) or Half width (spans one column). Two adjacent half-width fields sit side by side on the frontend.', 'big-orange-pardot' ); ?></li>
			</ul>

			<h3 id="bol-help-block-field-styling"><?php esc_html_e( 'Field styling', 'big-orange-pardot' ); ?></h3>
			<p><?php esc_html_e( 'When any Pardot Field block is selected, the block settings panel shows a Field Styling section. Changes made here apply to every field in the form — the settings are stored once on the parent form block and cascade to all fields automatically.', 'big-orange-pardot' ); ?></p>
			<ul style="list-style: disc; margin-left: 2em;">
				<li><strong><?php esc_html_e( 'Label Color', 'big-orange-pardot' ); ?></strong> — <?php esc_html_e( 'Text colour for all field labels.', 'big-orange-pardot' ); ?></li>
				<li><strong><?php esc_html_e( 'Input Background', 'big-orange-pardot' ); ?></strong> — <?php esc_html_e( 'Background colour for text inputs and textareas.', 'big-orange-pardot' ); ?></li>
				<li><strong><?php esc_html_e( 'Input Border Color', 'big-orange-pardot' ); ?></strong> — <?php esc_html_e( 'Border colour for text inputs and textareas.', 'big-orange-pardot' ); ?></li>
				<li><strong><?php esc_html_e( 'Focus / Accent Color', 'big-orange-pardot' ); ?></strong> — <?php esc_html_e( 'Outline and border colour when a field is focused; also used for the hover border on inputs.', 'big-orange-pardot' ); ?></li>
				<li><strong><?php esc_html_e( 'Input Border Radius (px)', 'big-orange-pardot' ); ?></strong> — <?php esc_html_e( 'Corner rounding applied to all inputs and textareas.', 'big-orange-pardot' ); ?></li>
			</ul>
			<p class="description"><?php esc_html_e( 'When no custom colours are set, the form falls back to your active theme\'s color palette (Kadence global palette variables if Kadence Blocks is active, then WordPress preset colors, then sensible hard-coded defaults).', 'big-orange-pardot' ); ?></p>

			<h3 id="bol-help-block-form-styling"><?php esc_html_e( 'Form-level styling', 'big-orange-pardot' ); ?></h3>
			<p><?php esc_html_e( 'Select the outer Big Orange Pardot block itself (not a field inside it) to access additional styling controls in the block settings panel and the block toolbar:', 'big-orange-pardot' ); ?></p>
			<ul style="list-style: disc; margin-left: 2em;">
				<li><strong><?php esc_html_e( 'Color → Background', 'big-orange-pardot' ); ?></strong> — <?php esc_html_e( 'Background colour of the entire form wrapper.', 'big-orange-pardot' ); ?></li>
				<li><strong><?php esc_html_e( 'Dimensions → Padding / Margin', 'big-orange-pardot' ); ?></strong> — <?php esc_html_e( 'Spacing inside and outside the form wrapper.', 'big-orange-pardot' ); ?></li>
				<li><strong><?php esc_html_e( 'Border', 'big-orange-pardot' ); ?></strong> — <?php esc_html_e( 'Border color, width, style, and radius for the form wrapper.', 'big-orange-pardot' ); ?></li>
			</ul>

			<h3 id="bol-help-block-button"><?php esc_html_e( 'Submit button styling', 'big-orange-pardot' ); ?></h3>
			<p><?php esc_html_e( 'Select the Pardot Form block to access its submit button settings. The button uses standard WordPress button classes and will pick up Kadence or theme button styles automatically if available; the options below let you override individual properties:', 'big-orange-pardot' ); ?></p>
			<ul style="list-style: disc; margin-left: 2em;">
				<li><strong><?php esc_html_e( 'Alignment', 'big-orange-pardot' ); ?></strong> — <?php esc_html_e( 'Left, center, or right — available in the block toolbar.', 'big-orange-pardot' ); ?></li>
				<li><strong><?php esc_html_e( 'Button Label', 'big-orange-pardot' ); ?></strong> — <?php esc_html_e( 'The button text (defaults to "Submit").', 'big-orange-pardot' ); ?></li>
				<li><strong><?php esc_html_e( 'Button Colors → Text Color', 'big-orange-pardot' ); ?></strong> — <?php esc_html_e( 'Button label colour.', 'big-orange-pardot' ); ?></li>
				<li><strong><?php esc_html_e( 'Button Colors → Background', 'big-orange-pardot' ); ?></strong> — <?php esc_html_e( 'Solid background colour or gradient for the button.', 'big-orange-pardot' ); ?></li>
				<li><strong><?php esc_html_e( 'Button Colors → Hover Background', 'big-orange-pardot' ); ?></strong> — <?php esc_html_e( 'Background colour shown when the button is hovered.', 'big-orange-pardot' ); ?></li>
				<li><strong><?php esc_html_e( 'Button Appearance → Padding', 'big-orange-pardot' ); ?></strong> — <?php esc_html_e( 'Inner spacing on each side of the button label.', 'big-orange-pardot' ); ?></li>
				<li><strong><?php esc_html_e( 'Button Appearance → Border Radius (px)', 'big-orange-pardot' ); ?></strong> — <?php esc_html_e( 'Corner rounding for the button.', 'big-orange-pardot' ); ?></li>
				<li><strong><?php esc_html_e( 'Button Appearance → Border', 'big-orange-pardot' ); ?></strong> — <?php esc_html_e( 'Border color, width, and style.', 'big-orange-pardot' ); ?></li>
				<li><strong><?php esc_html_e( 'Button Appearance → Box Shadow', 'big-orange-pardot' ); ?></strong> — <?php esc_html_e( 'Any valid CSS box-shadow value (e.g. "0 2px 8px rgba(0,0,0,0.15)").', 'big-orange-pardot' ); ?></li>
				<li><strong><?php esc_html_e( 'Dimensions → Margin', 'big-orange-pardot' ); ?></strong> — <?php esc_html_e( 'Outer spacing around the button wrapper.', 'big-orange-pardot' ); ?></li>
			</ul>

			<hr />

			<h2 id="bol-help-attribution"><?php esc_html_e( 'Attribution Tracking', 'big-orange-pardot' ); ?></h2>
			<p><?php esc_html_e( 'A small script runs on every page of your site (not just pages with the form). It captures marketing attribution data into cookies and injects that data as hidden fields on any Pardot form it finds on the page — including forms loaded dynamically after page load.', 'big-orange-pardot' ); ?></p>
			<p><?php esc_html_e( 'The following hidden fields are submitted with every form and must be mapped in your Pardot form handler to be recorded:', 'big-orange-pardot' ); ?></p>
			<table class="widefat striped" style="max-width: 600px;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Field name', 'big-orange-pardot' ); ?></th>
						<th><?php esc_html_e( 'What it captures', 'big-orange-pardot' ); ?></th>
						<th><?php esc_html_e( 'Cookie lifetime', 'big-orange-pardot' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr><td><code>utm_source</code></td><td><?php esc_html_e( 'UTM source parameter', 'big-orange-pardot' ); ?></td><td><?php esc_html_e( '30 days, overwritten on each visit', 'big-orange-pardot' ); ?></td></tr>
					<tr><td><code>utm_medium</code></td><td><?php esc_html_e( 'UTM medium parameter', 'big-orange-pardot' ); ?></td><td><?php esc_html_e( '30 days, overwritten on each visit', 'big-orange-pardot' ); ?></td></tr>
					<tr><td><code>utm_campaign</code></td><td><?php esc_html_e( 'UTM campaign parameter', 'big-orange-pardot' ); ?></td><td><?php esc_html_e( '30 days, overwritten on each visit', 'big-orange-pardot' ); ?></td></tr>
					<tr><td><code>utm_term</code></td><td><?php esc_html_e( 'UTM term parameter', 'big-orange-pardot' ); ?></td><td><?php esc_html_e( '30 days, overwritten on each visit', 'big-orange-pardot' ); ?></td></tr>
					<tr><td><code>utm_content</code></td><td><?php esc_html_e( 'UTM content parameter', 'big-orange-pardot' ); ?></td><td><?php esc_html_e( '30 days, overwritten on each visit', 'big-orange-pardot' ); ?></td></tr>
					<tr><td><code>gclid</code></td><td><?php esc_html_e( 'Google Click ID (from Google Ads)', 'big-orange-pardot' ); ?></td><td><?php esc_html_e( '90 days, overwritten on each visit', 'big-orange-pardot' ); ?></td></tr>
					<tr><td><code>landing_page_url</code></td><td><?php esc_html_e( 'Full URL of the first page visited', 'big-orange-pardot' ); ?></td><td><?php esc_html_e( '30 days, set once and never overwritten', 'big-orange-pardot' ); ?></td></tr>
					<tr><td><code>referrer_url</code></td><td><?php esc_html_e( 'Referring URL from outside this domain', 'big-orange-pardot' ); ?></td><td><?php esc_html_e( '30 days, set once and never overwritten', 'big-orange-pardot' ); ?></td></tr>
				</tbody>
			</table>
			<p class="description">
				<?php
				printf(
					/* translators: %s: link to Form Handler Inspector */
					esc_html__( 'Unmapped fields are silently ignored by Pardot. Use the %s on the Settings tab to verify which of these fields are configured in each of your form handlers.', 'big-orange-pardot' ),
					'<a href="' . esc_url( $this->tab_url( 'settings' ) ) . '">' . esc_html__( 'Form Handler Inspector', 'big-orange-pardot' ) . '</a>'
				);
				?>
			</p>

			<hr />

			<h2 id="bol-help-debugging"><?php esc_html_e( 'Testing &amp; Debugging', 'big-orange-pardot' ); ?></h2>
			<p>
				<?php esc_html_e( 'When you are logged in as an administrator, a small "Attribution (N)" menu appears in the WordPress admin bar on every page of the site. It shows the current value of each attribution cookie — or "(not set)" if that cookie has not been captured yet.', 'big-orange-pardot' ); ?>
			</p>
			<p>
				<?php esc_html_e( 'The menu also includes a "Clear all cookies" link. Clicking it deletes all eight attribution cookies and reloads the page, so you can simulate a fresh visitor without opening a private browser window.', 'big-orange-pardot' ); ?>
			</p>
			<p>
				<?php esc_html_e( 'For API troubleshooting, enable API Logging on the Settings tab. Request/response activity for Salesforce and Pardot is written to your uploads directory and shown on the Logs tab in a filterable table with per-entry inspection. From that tab, "Delete Log and Disable Logging" removes the file and turns logging off.', 'big-orange-pardot' ); ?>
			</p>
			<p class="description">
				<?php esc_html_e( 'The admin bar inspector is only visible to users with the manage_options capability and has no effect on site visitors.', 'big-orange-pardot' ); ?>
			</p>

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
			'log_cleared'          => array( 'success', __( 'API log deleted and logging disabled.', 'big-orange-pardot' ) ),
			'connected_business_unit_not_supported' => array( 'warning', __( 'Connected to Pardot. Business Unit auto-discovery is unavailable for this Salesforce org, so please enter your Business Unit ID manually.', 'big-orange-pardot' ) ),
			'connected_business_unit_set' => array( 'success', __( 'Successfully connected to Pardot and automatically selected your Business Unit.', 'big-orange-pardot' ) ),
			'connected_select_business_unit' => array( 'warning', __( 'Successfully connected to Pardot. Multiple Business Units were found, so please select one and save credentials.', 'big-orange-pardot' ) ),
			'connected_business_unit_lookup_failed' => array( 'warning', sprintf( /* translators: %s: error message */ __( 'Connected to Pardot, but Business Unit lookup failed: %s', 'big-orange-pardot' ), esc_html( $error_msg ) ) ),
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
