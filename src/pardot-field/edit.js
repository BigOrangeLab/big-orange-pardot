import {
	InspectorControls,
	PanelColorSettings,
	RichText,
	store as blockEditorStore,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

export default function Edit( { attributes, setAttributes, clientId } ) {
	const { fieldName, label, fieldType, isRequired, placeholder, width } =
		attributes;

	const blockProps = useBlockProps( {
		className: `bol-pardot-field bol-pardot-field--${ width }`,
	} );

	// Read shared field style attributes from the parent block so all fields
	// show the same values — changing one field's styling updates them all.
	const {
		parentClientId,
		fieldLabelColor,
		fieldInputBg,
		fieldBorderColor,
		fieldFocusColor,
		fieldBorderRadius,
	} = useSelect(
		( select ) => {
			const { getBlockRootClientId, getBlockAttributes } =
				select( blockEditorStore );
			const parentId = getBlockRootClientId( clientId );
			const parentAttrs = getBlockAttributes( parentId ) || {};
			return {
				parentClientId: parentId,
				fieldLabelColor: parentAttrs.fieldLabelColor || '',
				fieldInputBg: parentAttrs.fieldInputBg || '',
				fieldBorderColor: parentAttrs.fieldBorderColor || '',
				fieldFocusColor: parentAttrs.fieldFocusColor || '',
				fieldBorderRadius: parentAttrs.fieldBorderRadius || '',
			};
		},
		[ clientId ]
	);

	// Write field style changes back to the parent so all fields share them.
	const { updateBlockAttributes } = useDispatch( blockEditorStore );
	function setFieldStyle( attr ) {
		return ( value ) =>
			updateBlockAttributes( parentClientId, { [ attr ]: value || '' } );
	}

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

			{ /* Field styling is stored on the parent and shared across all fields. */ }
			<InspectorControls>
				<PanelColorSettings
					title={ __( 'Field Styling', 'big-orange-pardot' ) }
					initialOpen={ false }
					colorSettings={ [
						{
							value: fieldLabelColor,
							onChange: setFieldStyle( 'fieldLabelColor' ),
							label: __( 'Label Color', 'big-orange-pardot' ),
						},
						{
							value: fieldInputBg,
							onChange: setFieldStyle( 'fieldInputBg' ),
							label: __(
								'Input Background',
								'big-orange-pardot'
							),
						},
						{
							value: fieldBorderColor,
							onChange: setFieldStyle( 'fieldBorderColor' ),
							label: __(
								'Input Border Color',
								'big-orange-pardot'
							),
						},
						{
							value: fieldFocusColor,
							onChange: setFieldStyle( 'fieldFocusColor' ),
							label: __(
								'Focus / Accent Color',
								'big-orange-pardot'
							),
						},
					] }
				>
					<RangeControl
						label={ __(
							'Input Border Radius (px)',
							'big-orange-pardot'
						) }
						value={
							fieldBorderRadius
								? parseInt( fieldBorderRadius, 10 )
								: undefined
						}
						onChange={ ( value ) =>
							updateBlockAttributes( parentClientId, {
								fieldBorderRadius:
									value !== undefined
										? String( value ) + 'px'
										: '',
							} )
						}
						min={ 0 }
						max={ 24 }
						allowReset
					/>
					<p className="components-base-control__help">
						{ __(
							'These settings apply to all fields in this form.',
							'big-orange-pardot'
						) }
					</p>
				</PanelColorSettings>
			</InspectorControls>

			<div { ...blockProps }>
				<div className="bol-drag-handle" aria-hidden="true">
					<svg
						xmlns="http://www.w3.org/2000/svg"
						viewBox="0 0 18 18"
						width="18"
						height="18"
						aria-hidden="true"
						focusable="false"
					>
						<circle cx="5" cy="5" r="1.5" fill="currentColor" />
						<circle cx="13" cy="5" r="1.5" fill="currentColor" />
						<circle cx="5" cy="9" r="1.5" fill="currentColor" />
						<circle cx="13" cy="9" r="1.5" fill="currentColor" />
						<circle cx="5" cy="13" r="1.5" fill="currentColor" />
						<circle cx="13" cy="13" r="1.5" fill="currentColor" />
					</svg>
				</div>
				{ /* eslint-disable-next-line jsx-a11y/label-has-associated-control */ }
				<label>
					<RichText
						tagName="span"
						allowedFormats={ [] }
						value={ label }
						onChange={ ( value ) =>
							setAttributes( { label: value } )
						}
						placeholder={
							fieldName ||
							__( 'Field label…', 'big-orange-pardot' )
						}
					/>{ ' ' }
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
