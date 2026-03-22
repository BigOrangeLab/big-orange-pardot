<?php
/**
 * Pardot Submit block — frontend render template.
 *
 * Available variables:
 *   $attributes  array    Block attributes.
 *   $content     string   Inner block content (unused).
 *   $block       WP_Block Block instance.
 *
 * @package BigOrangePardot
 */

$label = isset( $attributes['label'] ) && '' !== $attributes['label']
	? $attributes['label']
	: __( 'Submit', 'big-orange-pardot' );

// -------------------------------------------------------------------------
// Wrapper — alignment + native spacing.margin support.
// -------------------------------------------------------------------------
$wrapper_style = '';
if ( ! empty( $attributes['buttonAlignment'] ) && 'left' !== $attributes['buttonAlignment'] ) {
	$wrapper_style = 'text-align: ' . esc_attr( $attributes['buttonAlignment'] ) . ';';
}
$wrapper_extra = $wrapper_style ? array(
	'style' => $wrapper_style,
	'class' => 'bol-pardot-submit',
) : array( 'class' => 'bol-pardot-submit' );

// -------------------------------------------------------------------------
// Button — inline styles built from block attributes.
// -------------------------------------------------------------------------
$btn_parts = array();

// Colors.
if ( ! empty( $attributes['buttonTextColor'] ) ) {
	$btn_parts[] = 'color: ' . esc_attr( $attributes['buttonTextColor'] );
}
if ( ! empty( $attributes['buttonBgGradient'] ) ) {
	$btn_parts[] = 'background: ' . esc_attr( $attributes['buttonBgGradient'] );
} elseif ( ! empty( $attributes['buttonBgColor'] ) ) {
	$btn_parts[] = 'background-color: ' . esc_attr( $attributes['buttonBgColor'] );
}

// Hover background via CSS custom property — referenced in style.scss.
if ( ! empty( $attributes['buttonHoverBgColor'] ) ) {
	$btn_parts[] = '--bol-btn-hover-bg: ' . esc_attr( $attributes['buttonHoverBgColor'] );
}

// Border.
if ( ! empty( $attributes['buttonBorderColor'] ) ) {
	$btn_parts[] = 'border-color: ' . esc_attr( $attributes['buttonBorderColor'] );
}
if ( ! empty( $attributes['buttonBorderWidth'] ) ) {
	$btn_parts[] = 'border-width: ' . esc_attr( $attributes['buttonBorderWidth'] );
}
if ( ! empty( $attributes['buttonBorderStyle'] ) ) {
	$btn_parts[] = 'border-style: ' . esc_attr( $attributes['buttonBorderStyle'] );
}
if ( ! empty( $attributes['buttonBorderRadius'] ) ) {
	$btn_parts[] = 'border-radius: ' . esc_attr( $attributes['buttonBorderRadius'] );
}

// Padding (from BoxControl — individual sides).
$padding = isset( $attributes['buttonPadding'] ) && is_array( $attributes['buttonPadding'] )
	? $attributes['buttonPadding']
	: array();
foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
	if ( ! empty( $padding[ $side ] ) ) {
		$btn_parts[] = 'padding-' . $side . ': ' . esc_attr( $padding[ $side ] );
	}
}

// Shadow.
if ( ! empty( $attributes['buttonShadow'] ) ) {
	$btn_parts[] = 'box-shadow: ' . esc_attr( $attributes['buttonShadow'] );
}

$button_style = ! empty( $btn_parts ) ? implode( '; ', $btn_parts ) : '';
?>
<div <?php echo get_block_wrapper_attributes( $wrapper_extra ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core function, output is already escaped. ?>>
	<button
		type="submit"
		class="kb-button wp-block-button__link"
		<?php if ( $button_style ) : ?>
			style="<?php echo esc_attr( $button_style ); ?>"
		<?php endif; ?>
	>
		<?php echo esc_html( $label ); ?>
	</button>
</div>
