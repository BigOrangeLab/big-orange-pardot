import apiFetch from '@wordpress/api-fetch';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	Notice,
	PanelBody,
	SelectControl,
	Spinner,
	TextControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import './editor.scss';

/* global bolPardot */

export default function Edit( { attributes, setAttributes } ) {
	const { pardotFormUrl, pardotFormHandlerId } = attributes;
	const blockProps = useBlockProps();

	const [ formHandlers, setFormHandlers ] = useState( null ); // null = not yet loaded
	const [ isLoading, setIsLoading ] = useState( false );
	const [ apiError, setApiError ] = useState( null );

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

	const selectedHandlerName =
		pardotFormHandlerId > 0 && formHandlers
			? formHandlers.find( ( h ) => h.id === pardotFormHandlerId )
					?.name ?? ''
			: '';

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

			<div { ...blockProps }>
				{ ! pardotFormUrl && (
					<div className="bol-pardot-notice">
						{ __(
							'Select a Pardot Form Handler in the block settings panel →',
							'big-orange-pardot'
						) }
					</div>
				) }

				{ selectedHandlerName && (
					<p className="bol-pardot-handler-name">
						{
							/* translators: %s: Pardot form handler name */ sprintf(
								__( 'Form handler: %s', 'big-orange-pardot' ),
								selectedHandlerName
							)
						}
					</p>
				) }

				{ /* aria-hidden: preview form is non-interactive, not meaningful to AT */ }
				<form
					className="bol-pardot-preview"
					onSubmit={ ( e ) => e.preventDefault() }
					aria-hidden="true"
				>
					<div className="bol-pardot-row bol-pardot-two-col">
						<div className="bol-pardot-field">
							<label htmlFor="bol-preview-first-name">
								{ __( 'First Name', 'big-orange-pardot' ) }{ ' ' }
								<span className="bol-required">*</span>
							</label>
							<input
								id="bol-preview-first-name"
								type="text"
								disabled
								placeholder={ __(
									'First Name',
									'big-orange-pardot'
								) }
							/>
						</div>
						<div className="bol-pardot-field">
							<label htmlFor="bol-preview-last-name">
								{ __( 'Last Name', 'big-orange-pardot' ) }{ ' ' }
								<span className="bol-required">*</span>
							</label>
							<input
								id="bol-preview-last-name"
								type="text"
								disabled
								placeholder={ __(
									'Last Name',
									'big-orange-pardot'
								) }
							/>
						</div>
					</div>

					<div className="bol-pardot-field">
						<label htmlFor="bol-preview-email">
							{ __( 'Email', 'big-orange-pardot' ) }{ ' ' }
							<span className="bol-required">*</span>
						</label>
						<input
							id="bol-preview-email"
							type="email"
							disabled
							placeholder={ __( 'Email', 'big-orange-pardot' ) }
						/>
					</div>

					<div className="bol-pardot-field">
						<label htmlFor="bol-preview-phone">
							{ __( 'Phone', 'big-orange-pardot' ) }
						</label>
						<input
							id="bol-preview-phone"
							type="tel"
							disabled
							placeholder={ __( 'Phone', 'big-orange-pardot' ) }
						/>
					</div>

					<div className="bol-pardot-field">
						<label htmlFor="bol-preview-company">
							{ __( 'Company', 'big-orange-pardot' ) }
						</label>
						<input
							id="bol-preview-company"
							type="text"
							disabled
							placeholder={ __( 'Company', 'big-orange-pardot' ) }
						/>
					</div>

					<div className="bol-pardot-field">
						<label htmlFor="bol-preview-job-title">
							{ __( 'Job Title', 'big-orange-pardot' ) }
						</label>
						<input
							id="bol-preview-job-title"
							type="text"
							disabled
							placeholder={ __(
								'Job Title',
								'big-orange-pardot'
							) }
						/>
					</div>

					<div className="bol-pardot-field">
						<label htmlFor="bol-preview-comments">
							{ __( 'Comments', 'big-orange-pardot' ) }
						</label>
						<textarea
							id="bol-preview-comments"
							disabled
							rows={ 4 }
							placeholder={ __(
								'Comments',
								'big-orange-pardot'
							) }
						/>
					</div>

					<div className="bol-pardot-submit">
						<button
							type="button"
							className="kb-button wp-block-button__link"
							disabled
						>
							{ __( 'Submit', 'big-orange-pardot' ) }
						</button>
					</div>
				</form>
			</div>
		</>
	);
}
