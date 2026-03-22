<?php
/**
 * Big Orange Pardot block — frontend render template.
 *
 * Available variables:
 *   $attributes  array    Block attributes.
 *   $content     string   Rendered inner blocks HTML (fields + submit button).
 *   $block       WP_Block Block instance.
 *
 * @package BigOrangePardot
 */

$pardot_form_url = isset( $attributes['pardotFormUrl'] ) ? trim( $attributes['pardotFormUrl'] ) : '';
?>
<div <?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core function, output is already escaped. ?>>
	<form
		method="post"
		action="<?php echo esc_url( $pardot_form_url ); ?>"
		novalidate
	>

		<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inner blocks HTML is already escaped by WordPress. ?>

		<!-- Hidden attribution fields — populated by attribution.js -->
		<input type="hidden" name="utm_source"       value="" />
		<input type="hidden" name="utm_medium"       value="" />
		<input type="hidden" name="utm_campaign"     value="" />
		<input type="hidden" name="utm_term"         value="" />
		<input type="hidden" name="utm_content"      value="" />
		<input type="hidden" name="referrer_url"     value="" />
		<input type="hidden" name="landing_page_url" value="" />
		<input type="hidden" name="gclid"            value="" />

	</form>
</div>
