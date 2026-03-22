import { InnerBlocks } from '@wordpress/block-editor';

/**
 * Dynamic block — PHP render.php handles all frontend output.
 * InnerBlocks.Content is required so WordPress serializes the inner block
 * markup into post_content when the post is saved.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#save
 */
export default function save() {
	return <InnerBlocks.Content />;
}
