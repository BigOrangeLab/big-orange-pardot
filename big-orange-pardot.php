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
