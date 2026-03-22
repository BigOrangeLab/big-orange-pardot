import {
	AlignmentControl,
	BlockControls,
	InspectorControls,
	PanelColorSettings,
	store as blockEditorStore,
	useBlockProps,
} from '@wordpress/block-editor';
import { createBlock } from '@wordpress/blocks';
import {
	BoxControl,
	Button,
	PanelBody,
	RangeControl,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { plus } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';

export default function Edit( { attributes, setAttributes, clientId } ) {
	const {
		label,
		buttonTextColor,
		buttonBgColor,
		buttonBgGradient,
		buttonHoverBgColor,
		buttonBorderColor,
		buttonBorderWidth,
		buttonBorderStyle,
		buttonBorderRadius,
		buttonPadding,
		buttonShadow,
		buttonAlignment,
	} = attributes;

	const { parentClientId, blockIndex } = useSelect(
		( select ) => {
			const { getBlockRootClientId, getBlockIndex } =
				select( blockEditorStore );
			return {
				parentClientId: getBlockRootClientId( clientId ),
				blockIndex: getBlockIndex( clientId ),
			};
		},
		[ clientId ]
	);

	const { insertBlock } = useDispatch( blockEditorStore );

	function addField() {
		insertBlock(
			createBlock( 'bigorangelab/pardot-field' ),
			blockIndex,
			parentClientId,
			true
		);
	}

	const blockProps = useBlockProps( {
		className: 'bol-pardot-submit',
		style:
			buttonAlignment && 'left' !== buttonAlignment
				? { textAlign: buttonAlignment }
				: undefined,
	} );

	// Build inline style for the preview button from attributes.
	const buttonStyle = {};
	if ( buttonTextColor ) {
		buttonStyle.color = buttonTextColor;
	}
	if ( buttonBgGradient ) {
		buttonStyle.background = buttonBgGradient;
	} else if ( buttonBgColor ) {
		buttonStyle.backgroundColor = buttonBgColor;
	}
	if ( buttonBorderColor ) {
		buttonStyle.borderColor = buttonBorderColor;
	}
	if ( buttonBorderWidth ) {
		buttonStyle.borderWidth = buttonBorderWidth;
	}
	if ( buttonBorderStyle ) {
		buttonStyle.borderStyle = buttonBorderStyle;
	}
	if ( buttonBorderRadius ) {
		buttonStyle.borderRadius = buttonBorderRadius;
	}
	if ( buttonShadow ) {
		buttonStyle.boxShadow = buttonShadow;
	}
	if ( buttonPadding ) {
		if ( buttonPadding.top ) {
			buttonStyle.paddingTop = buttonPadding.top;
		}
		if ( buttonPadding.right ) {
			buttonStyle.paddingRight = buttonPadding.right;
		}
		if ( buttonPadding.bottom ) {
			buttonStyle.paddingBottom = buttonPadding.bottom;
		}
		if ( buttonPadding.left ) {
			buttonStyle.paddingLeft = buttonPadding.left;
		}
	}
	// Hover color applied via CSS custom property — referenced in style.scss.
	if ( buttonHoverBgColor ) {
		buttonStyle[ '--bol-btn-hover-bg' ] = buttonHoverBgColor;
	}

	return (
		<>
			<BlockControls>
				<AlignmentControl
					value={ buttonAlignment }
					onChange={ ( value ) =>
						setAttributes( { buttonAlignment: value || 'left' } )
					}
				/>
			</BlockControls>

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

			<InspectorControls>
				<PanelColorSettings
					title={ __( 'Button Colors', 'big-orange-pardot' ) }
					initialOpen={ false }
					colorSettings={ [
						{
							value: buttonTextColor,
							onChange: ( value ) =>
								setAttributes( {
									buttonTextColor: value || '',
								} ),
							label: __( 'Text Color', 'big-orange-pardot' ),
						},
						{
							value: buttonBgGradient || buttonBgColor,
							gradientValue: buttonBgGradient,
							onChange: ( value ) =>
								setAttributes( {
									buttonBgColor: value || '',
									buttonBgGradient: '',
								} ),
							onGradientChange: ( value ) =>
								setAttributes( {
									buttonBgGradient: value || '',
									buttonBgColor: '',
								} ),
							label: __( 'Background', 'big-orange-pardot' ),
						},
						{
							value: buttonHoverBgColor,
							onChange: ( value ) =>
								setAttributes( {
									buttonHoverBgColor: value || '',
								} ),
							label: __(
								'Hover Background',
								'big-orange-pardot'
							),
						},
					] }
				/>
			</InspectorControls>

			<InspectorControls>
				<PanelBody
					title={ __( 'Button Appearance', 'big-orange-pardot' ) }
					initialOpen={ false }
				>
					<BoxControl
						label={ __( 'Padding', 'big-orange-pardot' ) }
						values={ buttonPadding }
						onChange={ ( value ) =>
							setAttributes( { buttonPadding: value } )
						}
					/>
					<RangeControl
						label={ __(
							'Border Radius (px)',
							'big-orange-pardot'
						) }
						value={
							buttonBorderRadius
								? parseInt( buttonBorderRadius, 10 )
								: undefined
						}
						onChange={ ( value ) =>
							setAttributes( {
								buttonBorderRadius:
									value !== undefined
										? String( value ) + 'px'
										: '',
							} )
						}
						min={ 0 }
						max={ 50 }
						allowReset
					/>
					<PanelColorSettings
						title={ __( 'Border', 'big-orange-pardot' ) }
						initialOpen={ false }
						colorSettings={ [
							{
								value: buttonBorderColor,
								onChange: ( value ) =>
									setAttributes( {
										buttonBorderColor: value || '',
									} ),
								label: __(
									'Border Color',
									'big-orange-pardot'
								),
							},
						] }
					>
						<RangeControl
							label={ __(
								'Border Width (px)',
								'big-orange-pardot'
							) }
							value={
								buttonBorderWidth
									? parseInt( buttonBorderWidth, 10 )
									: undefined
							}
							onChange={ ( value ) =>
								setAttributes( {
									buttonBorderWidth:
										value !== undefined
											? String( value ) + 'px'
											: '',
								} )
							}
							min={ 0 }
							max={ 10 }
							allowReset
						/>
						<SelectControl
							label={ __( 'Border Style', 'big-orange-pardot' ) }
							value={ buttonBorderStyle }
							options={ [
								{
									label: __( '—', 'big-orange-pardot' ),
									value: '',
								},
								{
									label: __( 'Solid', 'big-orange-pardot' ),
									value: 'solid',
								},
								{
									label: __( 'Dashed', 'big-orange-pardot' ),
									value: 'dashed',
								},
								{
									label: __( 'Dotted', 'big-orange-pardot' ),
									value: 'dotted',
								},
								{
									label: __( 'Double', 'big-orange-pardot' ),
									value: 'double',
								},
							] }
							onChange={ ( value ) =>
								setAttributes( { buttonBorderStyle: value } )
							}
						/>
					</PanelColorSettings>
					<TextControl
						label={ __( 'Box Shadow', 'big-orange-pardot' ) }
						value={ buttonShadow }
						onChange={ ( value ) =>
							setAttributes( { buttonShadow: value } )
						}
						help={ __(
							'Any valid CSS box-shadow value.',
							'big-orange-pardot'
						) }
						placeholder="0 2px 8px rgba(0,0,0,0.15)"
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<div className="bol-add-field-row">
					<Button
						className="bol-add-field"
						icon={ plus }
						onClick={ addField }
						size="small"
						variant="secondary"
					>
						{ __( 'Add field', 'big-orange-pardot' ) }
					</Button>
				</div>
				<button
					type="button"
					className="kb-button wp-block-button__link"
					style={ buttonStyle }
					disabled
				>
					{ label || __( 'Submit', 'big-orange-pardot' ) }
				</button>
			</div>
		</>
	);
}
