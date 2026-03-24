<?php
/**
 * Plugin Name:       Big Orange Pardot
 * Description:       A WordPress Form block for WordPress to integrate with Pardot.
 * Version:           1.0.1
 * Requires at least: 6.8
 * Requires PHP:      7.4
 * Author:            George Stephanis / Big Orange Lab
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       big-orange-pardot
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
	$asset = require __DIR__ . '/build/attribution.asset.php';
	wp_enqueue_script(
		'big-orange-pardot-attribution',
		plugins_url( 'build/attribution.js', __FILE__ ),
		$asset['dependencies'],
		$asset['version'],
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
 * Adds an Attribution Cookies menu to the admin bar for administrators.
 * Shows current cookie values captured by attribution.js and a "Clear all" action.
 *
 * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
 */
function bol_register_admin_bar_node( WP_Admin_Bar $wp_admin_bar ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$set_count = 0;
	foreach ( BOL_Pardot_API::ATTRIBUTION_FIELDS as $field ) {
		if ( ! empty( $_COOKIE[ $field ] ) ) {
			++$set_count;
		}
	}

	$wp_admin_bar->add_node(
		array(
			'id'    => 'bol-attribution',
			'title' => sprintf(
				/* translators: %d: number of attribution cookies currently set */
				__( 'Attribution (%d)', 'big-orange-pardot' ),
				$set_count
			),
		)
	);

	foreach ( BOL_Pardot_API::ATTRIBUTION_FIELDS as $field ) {
		$raw     = isset( $_COOKIE[ $field ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ $field ] ) ) : '';
		$display = $raw ? $raw : __( '(not set)', 'big-orange-pardot' );
		$wp_admin_bar->add_node(
			array(
				'id'     => 'bol-attribution-field-' . $field,
				'parent' => 'bol-attribution',
				'title'  => '<span class="bol-ab-field-name">' . esc_html( $field ) . '</span>'
							. '<span class="bol-ab-field-value' . ( $raw ? '' : ' bol-ab-field-empty' ) . '">'
							. esc_html( $display ) . '</span>',
				'meta'   => array( 'tabindex' => '0' ),
			)
		);
	}

	$wp_admin_bar->add_node(
		array(
			'id'     => 'bol-attribution-clear',
			'parent' => 'bol-attribution',
			'title'  => __( 'Clear all cookies', 'big-orange-pardot' ),
			'href'   => '#',
			'meta'   => array( 'class' => 'bol-ab-clear' ),
		)
	);
}
add_action( 'admin_bar_menu', 'bol_register_admin_bar_node', 100 );

/**
 * Enqueues the admin-bar attribution inspector script and styles.
 * Runs on both frontend and wp-admin when the admin bar is visible to admins.
 */
function bol_enqueue_admin_bar_assets() {
	if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$asset = require __DIR__ . '/build/admin-bar-attribution.asset.php';
	wp_enqueue_script(
		'big-orange-pardot-admin-bar',
		plugins_url( 'build/admin-bar-attribution.js', __FILE__ ),
		$asset['dependencies'],
		$asset['version'],
		array( 'strategy' => 'defer' )
	);
	wp_enqueue_style(
		'big-orange-pardot-admin-bar',
		plugins_url( 'build/admin-bar-attribution.css', __FILE__ ),
		array( 'admin-bar' ),
		$asset['version']
	);
	wp_style_add_data( 'big-orange-pardot-admin-bar', 'rtl', 'replace' );
}
add_action( 'wp_enqueue_scripts', 'bol_enqueue_admin_bar_assets' );
add_action( 'admin_enqueue_scripts', 'bol_enqueue_admin_bar_assets' );

/**
 * Passes the settings page URL to the block editor script so the "not connected"
 * notice can link directly to the credentials page.
 */
function big_orange_pardot_editor_script_data() {
	wp_localize_script(
		'bigorangelab-pardot-form-editor-script',
		'bolPardot',
		array(
			'settingsUrl' => admin_url( 'options-general.php?page=big-orange-pardot' ),
			'isConnected' => BOL_Pardot_API::is_connected(),
		)
	);
}
add_action( 'enqueue_block_editor_assets', 'big_orange_pardot_editor_script_data' );
