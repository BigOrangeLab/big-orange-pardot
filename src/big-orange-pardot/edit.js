import apiFetch from '@wordpress/api-fetch';
import {
	InspectorControls,
	useBlockProps,
	useInnerBlocksProps,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import { createBlock } from '@wordpress/blocks';
import {
	Button,
	Notice,
	PanelBody,
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
	[ 'bigorangelab/pardot-submit', { label: 'Submit' } ],
];

export default function Edit( { attributes, setAttributes, clientId } ) {
	const { pardotFormUrl, pardotFormHandlerId } = attributes;
	const blockProps = useBlockProps();
	const innerBlocksProps = useInnerBlocksProps( blockProps, {
		template: DEFAULT_TEMPLATE,
		templateLock: false,
		allowedBlocks: [
			'bigorangelab/pardot-field',
			'bigorangelab/pardot-submit',
		],
	} );

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
				const submitBlock = createBlock( 'bigorangelab/pardot-submit', {
					label: 'Submit',
				} );
				replaceInnerBlocks( clientId, [ ...fieldBlocks, submitBlock ] );
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

			<div { ...innerBlocksProps } />
		</>
	);
}
