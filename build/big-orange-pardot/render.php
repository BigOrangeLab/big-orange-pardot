<?php
/**
 * Big Orange Pardot block — frontend render template.
 *
 * Available variables:
 *   $attributes  array  Block attributes.
 *   $content     string Inner block content (unused).
 *   $block       object WP_Block instance.
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

		<!-- Name row — two columns on desktop -->
		<div class="bol-pardot-row bol-pardot-two-col">
			<div class="bol-pardot-field">
				<label for="bol-first-name">
					<?php esc_html_e( 'First Name', 'big-orange-pardot' ); ?>
					<span class="bol-required" aria-hidden="true">*</span>
				</label>
				<input
					type="text"
					id="bol-first-name"
					name="first_name"
					autocomplete="given-name"
					required
				/>
			</div>
			<div class="bol-pardot-field">
				<label for="bol-last-name">
					<?php esc_html_e( 'Last Name', 'big-orange-pardot' ); ?>
					<span class="bol-required" aria-hidden="true">*</span>
				</label>
				<input
					type="text"
					id="bol-last-name"
					name="last_name"
					autocomplete="family-name"
					required
				/>
			</div>
		</div>

		<div class="bol-pardot-field">
			<label for="bol-email">
				<?php esc_html_e( 'Email', 'big-orange-pardot' ); ?>
				<span class="bol-required" aria-hidden="true">*</span>
			</label>
			<input
				type="email"
				id="bol-email"
				name="email"
				autocomplete="email"
				required
			/>
		</div>

		<div class="bol-pardot-field">
			<label for="bol-phone"><?php esc_html_e( 'Phone', 'big-orange-pardot' ); ?></label>
			<input
				type="tel"
				id="bol-phone"
				name="phone"
				autocomplete="tel"
			/>
		</div>

		<div class="bol-pardot-field">
			<label for="bol-company"><?php esc_html_e( 'Company', 'big-orange-pardot' ); ?></label>
			<input
				type="text"
				id="bol-company"
				name="company"
				autocomplete="organization"
			/>
		</div>

		<div class="bol-pardot-field">
			<label for="bol-job-title"><?php esc_html_e( 'Job Title', 'big-orange-pardot' ); ?></label>
			<input
				type="text"
				id="bol-job-title"
				name="job_title"
				autocomplete="organization-title"
			/>
		</div>

		<div class="bol-pardot-field">
			<label for="bol-comments"><?php esc_html_e( 'Comments', 'big-orange-pardot' ); ?></label>
			<textarea
				id="bol-comments"
				name="comments"
				rows="4"
			></textarea>
		</div>

		<!-- Hidden attribution fields — populated by attribution.js -->
		<input type="hidden" name="utm_source"      value="" />
		<input type="hidden" name="utm_medium"      value="" />
		<input type="hidden" name="utm_campaign"    value="" />
		<input type="hidden" name="utm_term"        value="" />
		<input type="hidden" name="utm_content"     value="" />
		<input type="hidden" name="referrer_url"    value="" />
		<input type="hidden" name="landing_page_url" value="" />
		<input type="hidden" name="gclid"           value="" />

		<div class="bol-pardot-submit">
			<button type="submit" class="kb-button wp-block-button__link">
				<?php esc_html_e( 'Submit', 'big-orange-pardot' ); ?>
			</button>
		</div>

	</form>
</div>
