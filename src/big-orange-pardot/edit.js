import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import './editor.scss';

export default function Edit( { attributes, setAttributes } ) {
	const { pardotFormUrl } = attributes;
	const blockProps = useBlockProps();

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Pardot Settings', 'big-orange-pardot' ) }>
					<TextControl
						label={ __( 'Form Handler URL', 'big-orange-pardot' ) }
						help={ __(
							'Paste the Pardot Form Handler URL. The form will POST to this address.',
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
							'Add a Pardot Form Handler URL in the block settings panel →',
							'big-orange-pardot'
						) }
					</div>
				) }

				<form className="bol-pardot-preview" onSubmit={ ( e ) => e.preventDefault() }>
					<div className="bol-pardot-row bol-pardot-two-col">
						<div className="bol-pardot-field">
							<label>{ __( 'First Name', 'big-orange-pardot' ) } <span className="bol-required">*</span></label>
							<input type="text" disabled placeholder={ __( 'First Name', 'big-orange-pardot' ) } />
						</div>
						<div className="bol-pardot-field">
							<label>{ __( 'Last Name', 'big-orange-pardot' ) } <span className="bol-required">*</span></label>
							<input type="text" disabled placeholder={ __( 'Last Name', 'big-orange-pardot' ) } />
						</div>
					</div>

					<div className="bol-pardot-field">
						<label>{ __( 'Email', 'big-orange-pardot' ) } <span className="bol-required">*</span></label>
						<input type="email" disabled placeholder={ __( 'Email', 'big-orange-pardot' ) } />
					</div>

					<div className="bol-pardot-field">
						<label>{ __( 'Phone', 'big-orange-pardot' ) }</label>
						<input type="tel" disabled placeholder={ __( 'Phone', 'big-orange-pardot' ) } />
					</div>

					<div className="bol-pardot-field">
						<label>{ __( 'Company', 'big-orange-pardot' ) }</label>
						<input type="text" disabled placeholder={ __( 'Company', 'big-orange-pardot' ) } />
					</div>

					<div className="bol-pardot-field">
						<label>{ __( 'Job Title', 'big-orange-pardot' ) }</label>
						<input type="text" disabled placeholder={ __( 'Job Title', 'big-orange-pardot' ) } />
					</div>

					<div className="bol-pardot-field">
						<label>{ __( 'Comments', 'big-orange-pardot' ) }</label>
						<textarea disabled rows={ 4 } placeholder={ __( 'Comments', 'big-orange-pardot' ) } />
					</div>

					<div className="bol-pardot-submit">
						<button type="button" className="kb-button wp-block-button__link" disabled>
							{ __( 'Submit', 'big-orange-pardot' ) }
						</button>
					</div>
				</form>
			</div>
		</>
	);
}
