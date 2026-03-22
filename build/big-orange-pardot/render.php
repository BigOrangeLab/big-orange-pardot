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

// Build CSS custom properties for linked field styling and emit them as an
// inline style so all child field blocks inherit via the CSS cascade.
$custom_props = array();
if ( ! empty( $attributes['fieldLabelColor'] ) ) {
	$custom_props[] = '--bol-label-color: ' . esc_attr( $attributes['fieldLabelColor'] );
}
if ( ! empty( $attributes['fieldInputBg'] ) ) {
	$custom_props[] = '--bol-input-bg: ' . esc_attr( $attributes['fieldInputBg'] );
}
if ( ! empty( $attributes['fieldBorderColor'] ) ) {
	$custom_props[] = '--bol-border-color: ' . esc_attr( $attributes['fieldBorderColor'] );
}
if ( ! empty( $attributes['fieldFocusColor'] ) ) {
	$custom_props[] = '--bol-focus-color: ' . esc_attr( $attributes['fieldFocusColor'] );
}
if ( ! empty( $attributes['fieldBorderRadius'] ) ) {
	$custom_props[] = '--bol-field-radius: ' . esc_attr( $attributes['fieldBorderRadius'] );
}

$extra_attrs = ! empty( $custom_props ) ? array( 'style' => implode( '; ', $custom_props ) ) : array();
?>
<div <?php echo get_block_wrapper_attributes( $extra_attrs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core function, output is already escaped. ?>>
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
