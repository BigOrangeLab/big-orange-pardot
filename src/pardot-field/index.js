import { registerBlockType } from '@wordpress/blocks';
import { __, sprintf } from '@wordpress/i18n';
import metadata from './block.json';
import Edit from './edit';
import save from './save';

registerBlockType( metadata.name, {
	edit: Edit,
	save,
	__experimentalLabel( { label, fieldName } ) {
		const name = label || fieldName;
		return name
			? sprintf(
					/* translators: %s: field label or name */
					__( 'Field: %s', 'big-orange-pardot' ),
					name
			  )
			: __( 'Pardot Field', 'big-orange-pardot' );
	},
} );
