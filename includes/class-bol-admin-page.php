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
	 * Returns the active tab slug, defaulting to 'settings'.
	 *
	 * @return string
	 */
	private function current_tab() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'settings';
		return in_array( $tab, array( 'settings', 'help' ), true ) ? $tab : 'settings';
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
			</nav>

			<?php if ( 'help' === $current_tab ) : ?>
				<?php $this->render_help_tab(); ?>
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
		<div class="bol-help">

			<h2><?php esc_html_e( 'Overview', 'big-orange-pardot' ); ?></h2>
			<p><?php esc_html_e( 'Big Orange Pardot adds a Gutenberg block that embeds a Pardot (Account Engagement) form directly on any page or post. Rather than using an iframe, it renders a native HTML form that submits directly to your Pardot form handler, giving you full control over styling and layout.', 'big-orange-pardot' ); ?></p>
			<p><?php esc_html_e( 'The plugin also automatically captures marketing attribution data (UTM parameters, Google Click ID, landing page URL, and referrer) into cookies on every page load, then injects those values as hidden fields on any Pardot form found on the page — even forms loaded dynamically after the page renders.', 'big-orange-pardot' ); ?></p>

			<hr />

			<h2><?php esc_html_e( 'Setup: Connecting to Salesforce', 'big-orange-pardot' ); ?></h2>
			<p>
				<?php
				printf(
					/* translators: %s: Salesforce OAuth documentation link */
					esc_html__( 'The plugin connects to Pardot using the %s. This requires creating a Connected App in Salesforce once, then authorizing it here. There is no simpler authentication path — Salesforce requires a Connected App for all API access.', 'big-orange-pardot' ),
					'<a href="https://help.salesforce.com/s/articleView?id=xcloud.remoteaccess_oauth_web_server_flow.htm&type=5" target="_blank" rel="noopener noreferrer">' . esc_html__( 'OAuth 2.0 Web Server Flow', 'big-orange-pardot' ) . '</a>'
				);
				?>
			</p>

			<h3><?php esc_html_e( 'Step 1 — Create a Salesforce Connected App', 'big-orange-pardot' ); ?></h3>
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
					</ul>
				</li>
				<li><?php esc_html_e( 'Save the Connected App. Salesforce may take a few minutes to activate it.', 'big-orange-pardot' ); ?></li>
				<li><?php esc_html_e( 'From the Connected App detail page, click Manage Consumer Details to view your Consumer Key and Consumer Secret.', 'big-orange-pardot' ); ?></li>
			</ol>

			<h3><?php esc_html_e( 'Step 2 — Find your Business Unit ID', 'big-orange-pardot' ); ?></h3>
			<ol>
				<li><?php esc_html_e( 'In Salesforce Setup, search for "Account Engagement" and go to Account Engagement → Business Unit Setup.', 'big-orange-pardot' ); ?></li>
				<li><?php esc_html_e( 'Copy the Business Unit ID — it is an 18-character value beginning with 0Uv.', 'big-orange-pardot' ); ?></li>
			</ol>

			<h3><?php esc_html_e( 'Step 3 — Enter credentials and authorize', 'big-orange-pardot' ); ?></h3>
			<ol>
				<li>
					<?php
					printf(
						/* translators: %s: link to the Settings tab */
						esc_html__( 'Go to the %s tab and enter your Consumer Key, Consumer Secret, and Business Unit ID, then click Save Credentials.', 'big-orange-pardot' ),
						'<a href="' . esc_url( $this->tab_url( 'settings' ) ) . '">' . esc_html__( 'Settings', 'big-orange-pardot' ) . '</a>'
					);
					?>
				</li>
				<li><?php esc_html_e( 'Click Connect to Pardot. You will be redirected to Salesforce to log in and approve access.', 'big-orange-pardot' ); ?></li>
				<li><?php esc_html_e( 'After approving, Salesforce redirects back here automatically. The connection status will show Connected.', 'big-orange-pardot' ); ?></li>
			</ol>
			<p class="description"><?php esc_html_e( 'Access tokens are refreshed automatically in the background — you should only need to authorize once unless you explicitly disconnect or revoke access in Salesforce.', 'big-orange-pardot' ); ?></p>

			<hr />

			<h2><?php esc_html_e( 'Using the Block', 'big-orange-pardot' ); ?></h2>
			<ol>
				<li><?php esc_html_e( 'In the block editor, insert the Big Orange Pardot block from the Widgets category (or search for "Pardot").', 'big-orange-pardot' ); ?></li>
				<li><?php esc_html_e( 'The block inserts with a default set of seven fields (First Name, Last Name, Email, Phone, Company, Job Title, Comments) plus a Submit button. Each field is its own block — you can reorder, delete, or add fields freely using the block editor.', 'big-orange-pardot' ); ?></li>
				<li><?php esc_html_e( 'Open the block settings panel (sidebar). If the plugin is connected, a dropdown lists all form handlers from your Pardot account. Select the form handler this block should submit to — the Form Handler URL is populated automatically from the handler\'s embed code.', 'big-orange-pardot' ); ?></li>
				<li><?php esc_html_e( 'Once a form handler is selected, click Import fields from Pardot to replace the inner blocks with the actual fields configured on that handler. Field types and required status are imported automatically.', 'big-orange-pardot' ); ?></li>
				<li><?php esc_html_e( 'You can also paste a URL directly into the Form Handler URL field if you need to override or bypass the dropdown.', 'big-orange-pardot' ); ?></li>
			</ol>
			<h3><?php esc_html_e( 'Customising fields', 'big-orange-pardot' ); ?></h3>
			<p><?php esc_html_e( 'Select any Pardot Field block inside the form to edit it via the block settings panel. Available settings:', 'big-orange-pardot' ); ?></p>
			<ul style="list-style: disc; margin-left: 2em;">
				<li><strong><?php esc_html_e( 'Field Name (Pardot)', 'big-orange-pardot' ); ?></strong> — <?php esc_html_e( 'The HTML name attribute submitted to Pardot. Must match the field name in your form handler.', 'big-orange-pardot' ); ?></li>
				<li><strong><?php esc_html_e( 'Field Type', 'big-orange-pardot' ); ?></strong> — <?php esc_html_e( 'Text, Email, Phone, or Textarea.', 'big-orange-pardot' ); ?></li>
				<li><strong><?php esc_html_e( 'Required', 'big-orange-pardot' ); ?></strong> — <?php esc_html_e( 'Adds browser-side validation and a * indicator to the label.', 'big-orange-pardot' ); ?></li>
				<li><strong><?php esc_html_e( 'Placeholder', 'big-orange-pardot' ); ?></strong> — <?php esc_html_e( 'Placeholder text shown inside the input.', 'big-orange-pardot' ); ?></li>
				<li><strong><?php esc_html_e( 'Width', 'big-orange-pardot' ); ?></strong> — <?php esc_html_e( 'Full width (spans both grid columns) or Half width (spans one column). Two adjacent half-width fields sit side by side on the frontend.', 'big-orange-pardot' ); ?></li>
			</ul>

			<h3><?php esc_html_e( 'Field styling', 'big-orange-pardot' ); ?></h3>
			<p><?php esc_html_e( 'When any Pardot Field block is selected, the block settings panel shows a Field Styling section. Changes made here apply to every field in the form — the settings are stored once on the parent form block and cascade to all fields automatically.', 'big-orange-pardot' ); ?></p>
			<ul style="list-style: disc; margin-left: 2em;">
				<li><strong><?php esc_html_e( 'Label Color', 'big-orange-pardot' ); ?></strong> — <?php esc_html_e( 'Text colour for all field labels.', 'big-orange-pardot' ); ?></li>
				<li><strong><?php esc_html_e( 'Input Background', 'big-orange-pardot' ); ?></strong> — <?php esc_html_e( 'Background colour for text inputs and textareas.', 'big-orange-pardot' ); ?></li>
				<li><strong><?php esc_html_e( 'Input Border Color', 'big-orange-pardot' ); ?></strong> — <?php esc_html_e( 'Border colour for text inputs and textareas.', 'big-orange-pardot' ); ?></li>
				<li><strong><?php esc_html_e( 'Focus / Accent Color', 'big-orange-pardot' ); ?></strong> — <?php esc_html_e( 'Outline and border colour when a field is focused; also used for the hover border on inputs.', 'big-orange-pardot' ); ?></li>
				<li><strong><?php esc_html_e( 'Input Border Radius (px)', 'big-orange-pardot' ); ?></strong> — <?php esc_html_e( 'Corner rounding applied to all inputs and textareas.', 'big-orange-pardot' ); ?></li>
			</ul>
			<p class="description"><?php esc_html_e( 'When no custom colours are set, the form falls back to your active theme\'s color palette (Kadence global palette variables if Kadence Blocks is active, then WordPress preset colors, then sensible hard-coded defaults).', 'big-orange-pardot' ); ?></p>

			<h3><?php esc_html_e( 'Form-level styling', 'big-orange-pardot' ); ?></h3>
			<p><?php esc_html_e( 'Select the outer Big Orange Pardot block itself (not a field inside it) to access additional styling controls in the block settings panel and the block toolbar:', 'big-orange-pardot' ); ?></p>
			<ul style="list-style: disc; margin-left: 2em;">
				<li><strong><?php esc_html_e( 'Color → Background', 'big-orange-pardot' ); ?></strong> — <?php esc_html_e( 'Background colour of the entire form wrapper.', 'big-orange-pardot' ); ?></li>
				<li><strong><?php esc_html_e( 'Dimensions → Padding / Margin', 'big-orange-pardot' ); ?></strong> — <?php esc_html_e( 'Spacing inside and outside the form wrapper.', 'big-orange-pardot' ); ?></li>
				<li><strong><?php esc_html_e( 'Border', 'big-orange-pardot' ); ?></strong> — <?php esc_html_e( 'Border color, width, style, and radius for the form wrapper.', 'big-orange-pardot' ); ?></li>
			</ul>

			<h3><?php esc_html_e( 'Submit button styling', 'big-orange-pardot' ); ?></h3>
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

			<h2><?php esc_html_e( 'Attribution Tracking', 'big-orange-pardot' ); ?></h2>
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
