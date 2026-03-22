<?php
/**
 * Pardot Field block — frontend render template.
 *
 * Available variables:
 *   $attributes  array    Block attributes.
 *   $content     string   Inner block content (unused).
 *   $block       WP_Block Block instance.
 *
 * @package BigOrangePardot
 */

$field_name  = isset( $attributes['fieldName'] ) ? sanitize_key( $attributes['fieldName'] ) : '';
$label       = isset( $attributes['label'] ) ? $attributes['label'] : '';
$field_type  = isset( $attributes['fieldType'] ) ? $attributes['fieldType'] : 'text';
$is_required = ! empty( $attributes['isRequired'] );
$placeholder = isset( $attributes['placeholder'] ) ? $attributes['placeholder'] : '';
$width       = isset( $attributes['width'] ) ? $attributes['width'] : 'full';

if ( '' === $field_name ) {
	return;
}

$allowed_types = array( 'text', 'email', 'tel', 'textarea' );
if ( ! in_array( $field_type, $allowed_types, true ) ) {
	$field_type = 'text';
}

if ( ! in_array( $width, array( 'full', 'half' ), true ) ) {
	$width = 'full';
}

$field_id = 'bol-field-' . sanitize_html_class( $field_name );
?>
<div class="bol-pardot-field bol-pardot-field--<?php echo esc_attr( $width ); ?>">
	<label for="<?php echo esc_attr( $field_id ); ?>">
		<?php echo esc_html( '' !== $label ? $label : $field_name ); ?>
		<?php if ( $is_required ) : ?>
			<span class="bol-required" aria-hidden="true">*</span>
		<?php endif; ?>
	</label>
	<?php if ( 'textarea' === $field_type ) : ?>
		<textarea
			id="<?php echo esc_attr( $field_id ); ?>"
			name="<?php echo esc_attr( $field_name ); ?>"
			rows="4"
			placeholder="<?php echo esc_attr( $placeholder ); ?>"
			<?php
			if ( $is_required ) :
				?>
				required<?php endif; ?>
		></textarea>
	<?php else : ?>
		<input
			type="<?php echo esc_attr( $field_type ); ?>"
			id="<?php echo esc_attr( $field_id ); ?>"
			name="<?php echo esc_attr( $field_name ); ?>"
			placeholder="<?php echo esc_attr( $placeholder ); ?>"
			<?php
			if ( $is_required ) :
				?>
				required<?php endif; ?>
		/>
	<?php endif; ?>
</div>
