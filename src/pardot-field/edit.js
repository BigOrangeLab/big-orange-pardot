import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function Edit( { attributes, setAttributes } ) {
	const { fieldName, label, fieldType, isRequired, placeholder, width } =
		attributes;

	const blockProps = useBlockProps( {
		className: `bol-pardot-field bol-pardot-field--${ width }`,
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Field Settings', 'big-orange-pardot' ) }
				>
					<TextControl
						label={ __(
							'Field Name (Pardot)',
							'big-orange-pardot'
						) }
						value={ fieldName }
						onChange={ ( value ) =>
							setAttributes( { fieldName: value } )
						}
						help={ __(
							'The HTML name attribute submitted to Pardot.',
							'big-orange-pardot'
						) }
					/>
					<SelectControl
						label={ __( 'Field Type', 'big-orange-pardot' ) }
						value={ fieldType }
						options={ [
							{
								label: __( 'Text', 'big-orange-pardot' ),
								value: 'text',
							},
							{
								label: __( 'Email', 'big-orange-pardot' ),
								value: 'email',
							},
							{
								label: __( 'Phone', 'big-orange-pardot' ),
								value: 'tel',
							},
							{
								label: __( 'Textarea', 'big-orange-pardot' ),
								value: 'textarea',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { fieldType: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Required', 'big-orange-pardot' ) }
						checked={ isRequired }
						onChange={ ( value ) =>
							setAttributes( { isRequired: value } )
						}
					/>
					<TextControl
						label={ __( 'Placeholder', 'big-orange-pardot' ) }
						value={ placeholder }
						onChange={ ( value ) =>
							setAttributes( { placeholder: value } )
						}
					/>
					<SelectControl
						label={ __( 'Width', 'big-orange-pardot' ) }
						value={ width }
						options={ [
							{
								label: __( 'Full width', 'big-orange-pardot' ),
								value: 'full',
							},
							{
								label: __( 'Half width', 'big-orange-pardot' ),
								value: 'half',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { width: value } )
						}
						help={ __(
							'Two adjacent half-width fields display side by side on the frontend.',
							'big-orange-pardot'
						) }
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				{ /* eslint-disable-next-line jsx-a11y/label-has-associated-control */ }
				<label>
					{ label ||
						fieldName ||
						__( '(no label)', 'big-orange-pardot' ) }{ ' ' }
					{ isRequired && (
						<span className="bol-required" aria-hidden="true">
							*
						</span>
					) }
					{ 'textarea' === fieldType ? (
						<textarea
							disabled
							rows={ 4 }
							placeholder={ placeholder }
						/>
					) : (
						<input
							type={ fieldType }
							disabled
							placeholder={ placeholder }
						/>
					) }
				</label>
			</div>
		</>
	);
}
