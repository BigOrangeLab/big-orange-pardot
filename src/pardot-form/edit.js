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
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import './editor.scss';

/* global bolPardot */

/** Map Pardot dataFormat values to our fieldType attribute values. */
const PARDOT_TYPE_MAP = { Email: 'email', Phone: 'tel', TextArea: 'textarea' };

/**
 * Standard Pardot fields available on most form handlers.
 * Used in unconnected mode to let users quickly add common fields.
 */
const COMMON_PARDOT_FIELDS = [
	{
		name: 'first_name',
		label: 'First Name',
		fieldType: 'text',
		isRequired: false,
		width: 'half',
	},
	{
		name: 'last_name',
		label: 'Last Name',
		fieldType: 'text',
		isRequired: false,
		width: 'half',
	},
	{
		name: 'email',
		label: 'Email',
		fieldType: 'email',
		isRequired: true,
		width: 'full',
	},
	{
		name: 'phone',
		label: 'Phone',
		fieldType: 'tel',
		isRequired: false,
		width: 'full',
	},
	{
		name: 'company',
		label: 'Company',
		fieldType: 'text',
		isRequired: false,
		width: 'full',
	},
	{
		name: 'job_title',
		label: 'Job Title',
		fieldType: 'text',
		isRequired: false,
		width: 'full',
	},
	{
		name: 'website',
		label: 'Website',
		fieldType: 'text',
		isRequired: false,
		width: 'full',
	},
	{
		name: 'comments',
		label: 'Comments',
		fieldType: 'textarea',
		isRequired: false,
		width: 'full',
	},
];

/** Default 7-field template applied when the block is first inserted. */
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

	const { replaceInnerBlocks, insertBlocks } =
		useDispatch( blockEditorStore );

	// Live list of fieldName values from the current inner field blocks.
	const existingFieldNames = useSelect(
		( select ) =>
			select( blockEditorStore )
				.getBlocks( clientId )
				.map( ( b ) => b.attributes.fieldName )
				.filter( Boolean ),
		[ clientId ]
	);

	const [ formHandlers, setFormHandlers ] = useState( null ); // null = not yet loaded
	const [ isLoading, setIsLoading ] = useState( false );
	const [ apiError, setApiError ] = useState( null );
	const [ isImporting, setIsImporting ] = useState( false );

	// handlerFields: expected Pardot fields for the selected handler (display + sync status).
	const [ handlerFields, setHandlerFields ] = useState( null ); // null = not fetched
	const [ isLoadingHandlerFields, setIsLoadingHandlerFields ] =
		useState( false );

	// Settings page URL and connection status injected by wp_localize_script.
	const settingsUrl =
		typeof bolPardot !== 'undefined' ? bolPardot.settingsUrl : '';
	const isConnected =
		typeof bolPardot !== 'undefined' ? bolPardot.isConnected : false;

	// Fetch form handlers from the REST endpoint on mount.
	useEffect( () => {
		if ( ! isConnected ) {
			return;
		}
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
	}, [ isConnected ] );

	// Fetch handler fields whenever the selected handler changes (for sync status display).
	useEffect( () => {
		if ( ! isConnected || pardotFormHandlerId <= 0 ) {
			setHandlerFields( null );
			return;
		}
		setIsLoadingHandlerFields( true );
		apiFetch( {
			path:
				'/big-orange-pardot/v1/form-handler-fields?handler_id=' +
				pardotFormHandlerId,
		} )
			.then( ( fields ) => {
				setHandlerFields( fields );
				setIsLoadingHandlerFields( false );
			} )
			.catch( () => {
				setHandlerFields( [] );
				setIsLoadingHandlerFields( false );
			} );
	}, [ pardotFormHandlerId, isConnected ] );

	/**
	 * Called when the user picks a form handler from the dropdown.
	 * Stores the handler ID and action URL, then auto-inserts any fields
	 * the handler expects that are not already present in the form.
	 *
	 * @param {string} value Selected handler ID as a string.
	 */
	async function onSelectHandler( value ) {
		const id = parseInt( value, 10 );
		const handler = ( formHandlers || [] ).find( ( h ) => h.id === id );
		setAttributes( {
			pardotFormHandlerId: id,
			pardotFormUrl: handler ? handler.url : '',
		} );

		if ( id <= 0 ) {
			setHandlerFields( null );
			return;
		}

		setIsLoadingHandlerFields( true );
		try {
			const fields = await apiFetch( {
				path:
					'/big-orange-pardot/v1/form-handler-fields?handler_id=' +
					id,
			} );
			setHandlerFields( fields );

			// Auto-insert fields the handler expects that aren't in the form yet.
			const missing = fields.filter(
				( f ) => ! existingFieldNames.includes( f.name )
			);
			if ( missing.length > 0 ) {
				insertBlocks(
					missing.map( ( f ) => {
						const newFieldType =
							PARDOT_TYPE_MAP[ f.dataFormat ] || 'text';
						const newLabel = f.name
							.replace( /_/g, ' ' )
							.replace( /\b\w/g, ( c ) => c.toUpperCase() );
						return createBlock( 'bigorangelab/pardot-field', {
							fieldName: f.name,
							label: newLabel,
							fieldType: newFieldType,
							isRequired: !! f.isRequired,
							width: 'full',
						} );
					} ),
					undefined,
					clientId
				);
			}
		} catch ( e ) {
			setHandlerFields( [] );
		} finally {
			setIsLoadingHandlerFields( false );
		}
	}

	/**
	 * Inserts any handler fields not yet present in the form (additive only).
	 */
	function addMissingFields() {
		if ( isImporting || ! handlerFields ) {
			return;
		}
		const missing = handlerFields.filter(
			( f ) => ! existingFieldNames.includes( f.name )
		);
		if ( missing.length === 0 ) {
			return;
		}
		insertBlocks(
			missing.map( ( f ) => {
				const newFieldType = PARDOT_TYPE_MAP[ f.dataFormat ] || 'text';
				const newLabel = f.name
					.replace( /_/g, ' ' )
					.replace( /\b\w/g, ( c ) => c.toUpperCase() );
				return createBlock( 'bigorangelab/pardot-field', {
					fieldName: f.name,
					label: newLabel,
					fieldType: newFieldType,
					isRequired: !! f.isRequired,
					width: 'full',
				} );
			} ),
			undefined,
			clientId
		);
	}

	/**
	 * Fetches all fields for the selected handler and replaces all inner blocks.
	 */
	function replaceAllWithPardotFields() {
		setIsImporting( true );
		apiFetch( {
			path:
				'/big-orange-pardot/v1/form-handler-fields?handler_id=' +
				pardotFormHandlerId,
		} )
			.then( ( fields ) => {
				const fieldBlocks = fields.map( ( field ) => {
					const newFieldType =
						PARDOT_TYPE_MAP[ field.dataFormat ] || 'text';
					const newLabel = field.name
						.replace( /_/g, ' ' )
						.replace( /\b\w/g, ( c ) => c.toUpperCase() );
					return createBlock( 'bigorangelab/pardot-field', {
						fieldName: field.name,
						label: newLabel,
						fieldType: newFieldType,
						isRequired: !! field.isRequired,
						width: 'full',
					} );
				} );
				replaceInnerBlocks( clientId, fieldBlocks );
				setHandlerFields( fields );
				setIsImporting( false );
			} )
			.catch( () => {
				setIsImporting( false );
			} );
	}

	/**
	 * Appends a single common Pardot field to the form.
	 *
	 * @param {{ name: string, label: string, fieldType: string, isRequired: boolean, width: string }} field
	 */
	function addCommonField( field ) {
		insertBlocks(
			createBlock( 'bigorangelab/pardot-field', {
				fieldName: field.name,
				label: field.label,
				fieldType: field.fieldType,
				isRequired: field.isRequired,
				width: field.width,
			} ),
			undefined,
			clientId
		);
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

	// Field sync status computed values (connected mode).
	const matchedCount = handlerFields
		? handlerFields.filter( ( f ) => existingFieldNames.includes( f.name ) )
				.length
		: 0;
	const missingHandlerFields = handlerFields
		? handlerFields.filter(
				( f ) => ! existingFieldNames.includes( f.name )
		  )
		: [];

	return (
		<>
			{ /* ---- Pardot Settings panel ---- */ }
			<InspectorControls>
				<PanelBody
					title={ __( 'Pardot Settings', 'big-orange-pardot' ) }
				>
					{ isConnected ? (
						<>
							{ /* Loading state */ }
							{ isLoading && (
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
							{ ! isLoading && apiError && (
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
							{ ! isLoading && ! apiError && (
								<SelectControl
									label={ __(
										'Form Handler',
										'big-orange-pardot'
									) }
									value={ String( pardotFormHandlerId ) }
									options={ handlerOptions }
									onChange={ onSelectHandler }
									help={ __(
										"The form will POST to this handler's URL.",
										'big-orange-pardot'
									) }
								/>
							) }

							{ /* Field sync status */ }
							{ ! isLoading &&
								! apiError &&
								pardotFormHandlerId > 0 &&
								handlerFields !== null &&
								handlerFields.length > 0 && (
									<>
										{ isLoadingHandlerFields ? (
											<div className="bol-inspector-loading">
												<Spinner />
												<span>
													{ __(
														'Checking fields…',
														'big-orange-pardot'
													) }
												</span>
											</div>
										) : (
											<>
												<p className="description">
													{ sprintf(
														/* translators: 1: matched field count, 2: total handler field count */
														__(
															'%1$d of %2$d Pardot fields present in form.',
															'big-orange-pardot'
														),
														matchedCount,
														handlerFields.length
													) }
												</p>
												{ missingHandlerFields.length >
													0 && (
													<Button
														variant="secondary"
														onClick={
															addMissingFields
														}
														disabled={ isImporting }
													>
														{ sprintf(
															/* translators: %d: number of missing fields */
															__(
																'Add %d missing field(s)',
																'big-orange-pardot'
															),
															missingHandlerFields.length
														) }
													</Button>
												) }
											</>
										) }
									</>
								) }

							{ /* Replace all button */ }
							{ ! isLoading &&
								! apiError &&
								pardotFormHandlerId > 0 && (
									<Button
										variant="secondary"
										onClick={ replaceAllWithPardotFields }
										isBusy={ isImporting }
										disabled={
											isImporting ||
											isLoadingHandlerFields ||
											( handlerFields !== null &&
												handlerFields.length === 0 )
										}
									>
										{ __(
											'Replace all with Pardot fields',
											'big-orange-pardot'
										) }
									</Button>
								) }
						</>
					) : (
						<>
							{ /* Not-connected notice */ }
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

							{ /* URL input — primary control when unconnected */ }
							<TextControl
								label={ __(
									'Form Handler URL',
									'big-orange-pardot'
								) }
								help={ __(
									"The URL your form will POST to. Found in your Pardot form handler's embed code.",
									'big-orange-pardot'
								) }
								value={ pardotFormUrl }
								onChange={ ( value ) =>
									setAttributes( { pardotFormUrl: value } )
								}
								type="url"
								placeholder="https://go.pardot.com/l/..."
							/>
						</>
					) }
				</PanelBody>
			</InspectorControls>

			{ /* ---- Common Pardot Fields panel (unconnected mode only) ---- */ }
			{ ! isConnected && (
				<InspectorControls>
					<PanelBody
						title={ __(
							'Common Pardot Fields',
							'big-orange-pardot'
						) }
						initialOpen={ false }
					>
						<p className="description">
							{ __(
								'Quick-add standard Pardot fields to your form:',
								'big-orange-pardot'
							) }
						</p>
						{ COMMON_PARDOT_FIELDS.map( ( field ) => {
							const isPresent = existingFieldNames.includes(
								field.name
							);
							return (
								<div
									key={ field.name }
									className="bol-common-field-row"
								>
									<span className="bol-common-field-label">
										{ field.label }{ ' ' }
										<code>{ field.name }</code>
									</span>
									{ isPresent ? (
										<span className="bol-common-field-added">
											{ __(
												'\u2713',
												'big-orange-pardot'
											) }
										</span>
									) : (
										<Button
											variant="link"
											onClick={ () =>
												addCommonField( field )
											}
										>
											{ __( 'Add', 'big-orange-pardot' ) }
										</Button>
									) }
								</div>
							);
						} ) }
					</PanelBody>
				</InspectorControls>
			) }

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
				{ ! pardotFormUrl && (
					<div className="bol-pardot-notice">
						{ __(
							'No form handler URL configured — this form will not submit.',
							'big-orange-pardot'
						) }
					</div>
				) }
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
