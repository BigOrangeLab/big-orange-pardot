import apiFetch from '@wordpress/api-fetch';
import {
	AlignmentControl,
	BlockControls,
	InspectorControls,
	PanelColorSettings,
	RichText,
	useBlockProps,
	useInnerBlocksProps,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import { createBlock } from '@wordpress/blocks';
import {
	BoxControl,
	Button,
	Notice,
	PanelBody,
	RangeControl,
	SelectControl,
	Spinner,
	TextControl,
} from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import './editor.scss';

/* global bolPardot */

/** Map Pardot dataFormat values to our fieldType attribute values. */
const PARDOT_TYPE_MAP = { Email: 'email', Phone: 'tel', TextArea: 'textarea' };

/** Default 7-field + submit template applied when the block is first inserted. */
const DEFAULT_TEMPLATE = [
	[
		'bigorangelab/pardot-field',
		{
			fieldName: 'first_name',
			label: 'First Name',
			fieldType: 'text',
			isRequired: true,
			width: 'half',
		},
	],
	[
		'bigorangelab/pardot-field',
		{
			fieldName: 'last_name',
			label: 'Last Name',
			fieldType: 'text',
			isRequired: true,
			width: 'half',
		},
	],
	[
		'bigorangelab/pardot-field',
		{
			fieldName: 'email',
			label: 'Email',
			fieldType: 'email',
			isRequired: true,
			width: 'full',
		},
	],
	[
		'bigorangelab/pardot-field',
		{
			fieldName: 'phone',
			label: 'Phone',
			fieldType: 'tel',
			isRequired: false,
			width: 'full',
		},
	],
	[
		'bigorangelab/pardot-field',
		{
			fieldName: 'company',
			label: 'Company',
			fieldType: 'text',
			isRequired: false,
			width: 'full',
		},
	],
	[
		'bigorangelab/pardot-field',
		{
			fieldName: 'job_title',
			label: 'Job Title',
			fieldType: 'text',
			isRequired: false,
			width: 'full',
		},
	],
	[
		'bigorangelab/pardot-field',
		{
			fieldName: 'comments',
			label: 'Comments',
			fieldType: 'textarea',
			isRequired: false,
			width: 'full',
		},
	],
];

export default function Edit( { attributes, setAttributes, clientId } ) {
	const {
		pardotFormUrl,
		pardotFormHandlerId,
		fieldLabelColor,
		fieldInputBg,
		fieldBorderColor,
		fieldFocusColor,
		fieldBorderRadius,
		submitLabel,
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

	// Emit field style attributes as CSS custom properties so child field
	// blocks inherit them via the cascade — no context passing required.
	const fieldCustomProps = {};
	if ( fieldLabelColor ) {
		fieldCustomProps[ '--bol-label-color' ] = fieldLabelColor;
	}
	if ( fieldInputBg ) {
		fieldCustomProps[ '--bol-input-bg' ] = fieldInputBg;
	}
	if ( fieldBorderColor ) {
		fieldCustomProps[ '--bol-border-color' ] = fieldBorderColor;
	}
	if ( fieldFocusColor ) {
		fieldCustomProps[ '--bol-focus-color' ] = fieldFocusColor;
	}
	if ( fieldBorderRadius ) {
		fieldCustomProps[ '--bol-field-radius' ] = fieldBorderRadius;
	}

	// Build inline style for the submit button preview.
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
	if ( buttonHoverBgColor ) {
		buttonStyle[ '--bol-btn-hover-bg' ] = buttonHoverBgColor;
	}

	const blockProps = useBlockProps( { style: fieldCustomProps } );
	const innerBlocksProps = useInnerBlocksProps(
		{},
		{
			template: DEFAULT_TEMPLATE,
			templateLock: false,
			allowedBlocks: [ 'bigorangelab/pardot-field' ],
		}
	);

	const { replaceInnerBlocks } = useDispatch( blockEditorStore );

	const [ formHandlers, setFormHandlers ] = useState( null ); // null = not yet loaded
	const [ isLoading, setIsLoading ] = useState( false );
	const [ apiError, setApiError ] = useState( null );
	const [ isImporting, setIsImporting ] = useState( false );

	// Fetch form handlers from the REST endpoint on mount.
	useEffect( () => {
		setIsLoading( true );
		apiFetch( { path: '/big-orange-pardot/v1/form-handlers' } )
			.then( ( data ) => {
				setFormHandlers( data );
				setIsLoading( false );
			} )
			.catch( () => {
				setFormHandlers( [] );
				setApiError( 'fetch_failed' );
				setIsLoading( false );
			} );
	}, [] );

	/**
	 * Called when the user picks a form handler from the dropdown.
	 * Stores both the handler ID and the action URL derived from the API.
	 *
	 * @param {string} value Selected handler ID as a string.
	 */
	function onSelectHandler( value ) {
		const id = parseInt( value, 10 );
		const handler = ( formHandlers || [] ).find( ( h ) => h.id === id );
		setAttributes( {
			pardotFormHandlerId: id,
			pardotFormUrl: handler ? handler.url : '',
		} );
	}

	/**
	 * Fetches fields for the selected handler from Pardot and replaces
	 * all inner blocks with the returned field layout.
	 */
	function importFieldsFromPardot() {
		setIsImporting( true );
		apiFetch( {
			path:
				'/big-orange-pardot/v1/form-handler-fields?handler_id=' +
				pardotFormHandlerId,
		} )
			.then( ( fields ) => {
				const fieldBlocks = fields.map( ( field ) => {
					const fieldType =
						PARDOT_TYPE_MAP[ field.dataFormat ] || 'text';
					const label = field.name
						.replace( /_/g, ' ' )
						.replace( /\b\w/g, ( c ) => c.toUpperCase() );
					return createBlock( 'bigorangelab/pardot-field', {
						fieldName: field.name,
						label,
						fieldType,
						isRequired: !! field.isRequired,
						width: 'full',
					} );
				} );
				replaceInnerBlocks( clientId, fieldBlocks );
				setIsImporting( false );
			} )
			.catch( () => {
				setIsImporting( false );
			} );
	}

	// Build SelectControl options.
	const handlerOptions = [
		{
			label: __( '— Select a form handler —', 'big-orange-pardot' ),
			value: '0',
		},
		...( formHandlers || [] ).map( ( h ) => ( {
			label: h.name,
			value: String( h.id ),
		} ) ),
	];

	// Settings page URL injected by wp_localize_script.
	const settingsUrl =
		typeof bolPardot !== 'undefined' ? bolPardot.settingsUrl : '';
	const isConnected =
		typeof bolPardot !== 'undefined' ? bolPardot.isConnected : false;

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Pardot Settings', 'big-orange-pardot' ) }
				>
					{ /* Not connected notice */ }
					{ ! isConnected && (
						<Notice
							status="warning"
							isDismissible={ false }
							className="bol-inspector-notice"
						>
							{ __(
								'Pardot is not connected.',
								'big-orange-pardot'
							) }
							{ settingsUrl && (
								<a href={ settingsUrl }>
									{ __(
										'Configure credentials →',
										'big-orange-pardot'
									) }
								</a>
							) }
						</Notice>
					) }

					{ /* Loading state */ }
					{ isConnected && isLoading && (
						<div className="bol-inspector-loading">
							<Spinner />
							<span>
								{ __(
									'Loading form handlers…',
									'big-orange-pardot'
								) }
							</span>
						</div>
					) }

					{ /* API fetch error */ }
					{ isConnected && ! isLoading && apiError && (
						<Notice status="error" isDismissible={ false }>
							{ __(
								'Could not load form handlers from Pardot. Check your connection on the',
								'big-orange-pardot'
							) }{ ' ' }
							{ settingsUrl && (
								<a href={ settingsUrl }>
									{ __(
										'Settings page',
										'big-orange-pardot'
									) }
								</a>
							) }
							{ '.' }
						</Notice>
					) }

					{ /* Form handler dropdown */ }
					{ isConnected && ! isLoading && ! apiError && (
						<SelectControl
							label={ __( 'Form Handler', 'big-orange-pardot' ) }
							value={ String( pardotFormHandlerId ) }
							options={ handlerOptions }
							onChange={ onSelectHandler }
							help={ __(
								"The form will POST to this handler's URL.",
								'big-orange-pardot'
							) }
						/>
					) }

					{ /* Import fields button */ }
					{ isConnected &&
						! isLoading &&
						! apiError &&
						pardotFormHandlerId > 0 && (
							<Button
								variant="secondary"
								onClick={ importFieldsFromPardot }
								isBusy={ isImporting }
								disabled={ isImporting }
							>
								{ __(
									'Import fields from Pardot',
									'big-orange-pardot'
								) }
							</Button>
						) }

					{ /* Manual URL override */ }
					<TextControl
						label={ __(
							'Form Handler URL (manual override)',
							'big-orange-pardot'
						) }
						help={ __(
							'Auto-populated when you select a handler above. Edit only if needed.',
							'big-orange-pardot'
						) }
						value={ pardotFormUrl }
						onChange={ ( value ) =>
							setAttributes( { pardotFormUrl: value } )
						}
						type="url"
						placeholder="https://go.pardot.com/l/..."
					/>
				</PanelBody>
			</InspectorControls>

			<BlockControls>
				<AlignmentControl
					value={ buttonAlignment }
					onChange={ ( value ) =>
						setAttributes( { buttonAlignment: value || 'left' } )
					}
				/>
			</BlockControls>

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
									label: __( '\u2014', 'big-orange-pardot' ),
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
				<div { ...innerBlocksProps } />
				<div
					className="bol-pardot-submit"
					style={
						buttonAlignment && 'left' !== buttonAlignment
							? { textAlign: buttonAlignment }
							: undefined
					}
				>
					<RichText
						tagName="div"
						className="kb-button wp-block-button__link"
						allowedFormats={ [] }
						value={ submitLabel }
						onChange={ ( value ) =>
							setAttributes( { submitLabel: value } )
						}
						placeholder={ __( 'Submit', 'big-orange-pardot' ) }
						style={ buttonStyle }
					/>
				</div>
			</div>
		</>
	);
}
