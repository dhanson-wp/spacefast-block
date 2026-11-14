import { useSelect } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';

import { isOnlyBlock } from './display';

// Post types whose content isn't a post's own content: the full-page rule is
// about a post's content on the front end, so it can't be broken here.
const NON_CONTENT_POST_TYPES = [
	'wp_template',
	'wp_template_part',
	'wp_block',
];

/**
 * Whether this block is the only meaningful block in the post's content, the
 * condition for full page. A block edited inside a template or a pattern, or
 * anywhere without a post (the widgets screen), counts as satisfying it, so
 * nothing is disabled where the rule doesn't apply.
 *
 * @param {string} clientId This block's client ID.
 * @return {boolean} True when full page is usable.
 */
export default function useIsOnlyBlock( clientId ) {
	return useSelect(
		( select ) => {
			const editor = select( 'core/editor' );
			const postType = editor?.getCurrentPostType?.();
			if ( ! postType || NON_CONTENT_POST_TYPES.includes( postType ) ) {
				return true;
			}
			return isOnlyBlock(
				select( blockEditorStore ).getBlocks(),
				clientId
			);
		},
		[ clientId ]
	);
}
