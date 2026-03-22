<?php
/**
 * Plugin Name:       Big Orange Pardot
 * Description:       A WordPress Form block for WordPress to integrate with Pardot.
 * Version:           0.1.0
 * Requires at least: 6.8
 * Requires PHP:      7.4
 * Author:            George Stephanis / Big Orange Lab
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       big-orange-pardot
 * Requires Plugins:  kadence-blocks
 *
 * @package BigOrangePardot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once __DIR__ . '/includes/class-bol-pardot-api.php';
require_once __DIR__ . '/includes/class-bol-admin-page.php';

new BOL_Admin_Page();
/**
 * Registers the block(s) metadata from the `blocks-manifest.php` and registers the block type(s)
 * based on the registered block metadata. Behind the scenes, it registers also all assets so they can be enqueued
 * through the block editor in the corresponding context.
 *
 * @see https://make.wordpress.org/core/2025/03/13/more-efficient-block-type-registration-in-6-8/
 * @see https://make.wordpress.org/core/2024/10/17/new-block-type-registration-apis-to-improve-performance-in-wordpress-6-7/
 */
function create_block_big_orange_pardot_block_init() {
	wp_register_block_types_from_metadata_collection( __DIR__ . '/build', __DIR__ . '/build/blocks-manifest.php' );
}
add_action( 'init', 'create_block_big_orange_pardot_block_init' );

/**
 * Enqueues the attribution script on every frontend page load so UTM params,
 * gclid, landing page URL, and referrer are captured into cookies and injected
 * into any Pardot form hidden fields — even on pages without the form block.
 */
function big_orange_pardot_enqueue_attribution() {
	wp_enqueue_script(
		'big-orange-pardot-attribution',
		plugins_url( 'assets/attribution.js', __FILE__ ),
		array(),
		'0.1.0',
		array( 'strategy' => 'defer' )
	);
}
add_action( 'wp_enqueue_scripts', 'big_orange_pardot_enqueue_attribution' );

/**
 * Registers the REST API endpoints that the block editor uses for Pardot data.
 */
function big_orange_pardot_rest_init() {
	register_rest_route(
		'big-orange-pardot/v1',
		'/form-handlers',
		array(
			'methods'             => 'GET',
			'callback'            => static function () {
				$handlers = BOL_Pardot_API::get_form_handlers();
				if ( is_wp_error( $handlers ) ) {
					return $handlers;
				}
				return rest_ensure_response( $handlers );
			},
			'permission_callback' => static function () {
				return current_user_can( 'manage_options' );
			},
		)
	);

	register_rest_route(
		'big-orange-pardot/v1',
		'/form-handler-fields',
		array(
			'methods'             => 'GET',
			'callback'            => static function ( \WP_REST_Request $request ) {
				$handler_id = (int) $request->get_param( 'handler_id' );
				if ( $handler_id <= 0 ) {
					return new \WP_Error( 'missing_handler_id', __( 'handler_id is required.', 'big-orange-pardot' ), array( 'status' => 400 ) );
				}
				$fields = BOL_Pardot_API::get_form_handler_fields( $handler_id );
				if ( is_wp_error( $fields ) ) {
					return $fields;
				}
				return rest_ensure_response( $fields );
			},
			'permission_callback' => static function () {
				return current_user_can( 'manage_options' );
			},
			'args'                => array(
				'handler_id' => array(
					'type'              => 'integer',
					'required'          => true,
					'sanitize_callback' => 'absint',
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'big_orange_pardot_rest_init' );

/**
 * Passes the settings page URL to the block editor script so the "not connected"
 * notice can link directly to the credentials page.
 */
function big_orange_pardot_editor_script_data() {
	wp_localize_script(
		'bigorangelab-big-orange-pardot-editor-script',
		'bolPardot',
		array(
			'settingsUrl' => admin_url( 'options-general.php?page=big-orange-pardot' ),
			'isConnected' => BOL_Pardot_API::is_connected(),
		)
	);
}
add_action( 'enqueue_block_editor_assets', 'big_orange_pardot_editor_script_data' );
