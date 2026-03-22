<?php
/**
 * Setup script to create a page with a Pardot form on it.
 *
 * This is intended to be run as part of the GitHub Blueprint workflow, but can also be run manually if needed.
 *
 * Note: This script assumes that the Big Orange Pardot plugin is active and that the necessary blocks are registered.
 *
 * The form created by this script includes the following fields:
 * - First Name (required, half width)
 * - Last Name (required, half width)
 * - Email (required, email field)
 * - Phone (tel field)
 * - Company
 * - Job Title
 * - Comments (textarea)
 *
 * After running this script, the newly created page will be set as the front page of the site.
 *
 * @package BigOrangePardot
 * @since 1.0.0
 */

$content = <<<'BLOCKS'
<!-- wp:bigorangelab/pardot-form -->
	<!-- wp:bigorangelab/pardot-field {\"fieldName\":\"first_name\",\"label\":\"First Name\",\"isRequired\":true,\"width\":\"half\"} /-->
	<!-- wp:bigorangelab/pardot-field {\"fieldName\":\"last_name\",\"label\":\"Last Name\",\"isRequired\":true,\"width\":\"half\"} /-->
	<!-- wp:bigorangelab/pardot-field {\"fieldName\":\"email\",\"label\":\"Email\",\"fieldType\":\"email\",\"isRequired\":true} /-->
	<!-- wp:bigorangelab/pardot-field {\"fieldName\":\"phone\",\"label\":\"Phone\",\"fieldType\":\"tel\"} /-->
	<!-- wp:bigorangelab/pardot-field {\"fieldName\":\"company\",\"label\":\"Company\"} /-->
	<!-- wp:bigorangelab/pardot-field {\"fieldName\":\"job_title\",\"label\":\"Job Title\"} /-->
	<!-- wp:bigorangelab/pardot-field {\"fieldName\":\"comments\",\"label\":\"Comments\",\"fieldType\":\"textarea\"} /-->
<!-- /wp:bigorangelab/pardot-form -->
BLOCKS;

$form_post_id = wp_insert_post(
	array(
		'post_title'   => 'Contact Us',
		'post_status'  => 'publish',
		'post_type'    => 'page',
		'post_content' => $content,
	)
);

/**
 * Set the newly created page as the front page.
 */
update_option( 'show_on_front', 'page' );

if ( ! is_wp_error( $form_post_id ) && $form_post_id > 0 ) {
	update_option( 'page_on_front', $form_post_id );
}

/**
 * Set and flush rewrite rules.
 */
global $wp_rewrite;
$wp_rewrite->set_permalink_structure( '/%postname%/' );
$wp_rewrite->flush_rules();
