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
?>
<div class="bol-pardot-submit">
	<button type="submit" class="kb-button wp-block-button__link">
		<?php echo esc_html( $label ); ?>
	</button>
</div>
