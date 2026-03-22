import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function Edit( { attributes, setAttributes } ) {
	const { label } = attributes;

	const blockProps = useBlockProps( {
		className: 'bol-pardot-submit',
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Button Settings', 'big-orange-pardot' ) }
				>
					<TextControl
						label={ __( 'Button Label', 'big-orange-pardot' ) }
						value={ label }
						onChange={ ( value ) =>
							setAttributes( { label: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<button
					type="button"
					className="kb-button wp-block-button__link"
					disabled
				>
					{ label || __( 'Submit', 'big-orange-pardot' ) }
				</button>
			</div>
		</>
	);
}
