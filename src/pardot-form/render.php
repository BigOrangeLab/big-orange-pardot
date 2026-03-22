<?php
/**
 * Big Orange Pardot block — frontend render template.
 *
 * Available variables:
 *   $attributes  array    Block attributes.
 *   $content     string   Rendered inner blocks HTML (field blocks).
 *   $block       WP_Block Block instance.
 *
 * @package BigOrangePardot
 */

$pardot_form_url = isset( $attributes['pardotFormUrl'] ) ? trim( $attributes['pardotFormUrl'] ) : '';
$submit_label    = isset( $attributes['submitLabel'] ) && '' !== $attributes['submitLabel']
	? $attributes['submitLabel']
	: __( 'Submit', 'big-orange-pardot' );

// Build inline style string for the submit button.
$btn_styles = array();
if ( ! empty( $attributes['buttonTextColor'] ) ) {
	$btn_styles[] = 'color: ' . esc_attr( $attributes['buttonTextColor'] );
}
if ( ! empty( $attributes['buttonBgGradient'] ) ) {
	$btn_styles[] = 'background: ' . esc_attr( $attributes['buttonBgGradient'] );
} elseif ( ! empty( $attributes['buttonBgColor'] ) ) {
	$btn_styles[] = 'background-color: ' . esc_attr( $attributes['buttonBgColor'] );
}
if ( ! empty( $attributes['buttonBorderColor'] ) ) {
	$btn_styles[] = 'border-color: ' . esc_attr( $attributes['buttonBorderColor'] );
}
if ( ! empty( $attributes['buttonBorderWidth'] ) ) {
	$btn_styles[] = 'border-width: ' . esc_attr( $attributes['buttonBorderWidth'] );
}
if ( ! empty( $attributes['buttonBorderStyle'] ) ) {
	$btn_styles[] = 'border-style: ' . esc_attr( $attributes['buttonBorderStyle'] );
}
if ( ! empty( $attributes['buttonBorderRadius'] ) ) {
	$btn_styles[] = 'border-radius: ' . esc_attr( $attributes['buttonBorderRadius'] );
}
if ( ! empty( $attributes['buttonShadow'] ) ) {
	$btn_styles[] = 'box-shadow: ' . esc_attr( $attributes['buttonShadow'] );
}
if ( ! empty( $attributes['buttonHoverBgColor'] ) ) {
	$btn_styles[] = '--bol-btn-hover-bg: ' . esc_attr( $attributes['buttonHoverBgColor'] );
}
$padding = isset( $attributes['buttonPadding'] ) ? $attributes['buttonPadding'] : array();
if ( ! empty( $padding['top'] ) ) {
	$btn_styles[] = 'padding-top: ' . esc_attr( $padding['top'] );
}
if ( ! empty( $padding['right'] ) ) {
	$btn_styles[] = 'padding-right: ' . esc_attr( $padding['right'] );
}
if ( ! empty( $padding['bottom'] ) ) {
	$btn_styles[] = 'padding-bottom: ' . esc_attr( $padding['bottom'] );
}
if ( ! empty( $padding['left'] ) ) {
	$btn_styles[] = 'padding-left: ' . esc_attr( $padding['left'] );
}
$btn_style_attr = ! empty( $btn_styles ) ? ' style="' . implode( '; ', $btn_styles ) . '"' : '';

// Submit wrapper alignment.
$btn_alignment = isset( $attributes['buttonAlignment'] ) ? $attributes['buttonAlignment'] : 'left';
$wrapper_style = ( $btn_alignment && 'left' !== $btn_alignment )
	? ' style="text-align: ' . esc_attr( $btn_alignment ) . ';"'
	: '';

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

		<div class="bol-pardot-submit"<?php echo $wrapper_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- value is esc_attr() above. ?>>
			<button type="submit" class="kb-button wp-block-button__link"<?php echo $btn_style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- all values are esc_attr() above. ?>>
				<?php echo esc_html( $submit_label ); ?>
			</button>
		</div>

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
