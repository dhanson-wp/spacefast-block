import { __ } from '@wordpress/i18n';

import Frame from './frame';

/**
 * The full page mode. The editor can't show the takeover, so it shows the
 * Space at a fixed height with a note about where it will appear.
 *
 * @param {Object}  props            Component props.
 * @param {Object}  props.attributes Block attributes.
 * @param {boolean} props.isSelected Whether the block is selected.
 */
export default function FullPagePreview( { attributes, isSelected } ) {
	return (
		<>
			<p className="wp-block-spacefast-space__editor-label">
				{ attributes.fitContent === false
					? __(
							'Fills the screen below your header; your footer follows',
							'spacefast-block'
						)
					: __(
							'Shown at its full length between your header and footer',
							'spacefast-block'
						) }
			</p>
			<div className="wp-block-spacefast-space__stage">
				<Frame attributes={ attributes } isSelected={ isSelected } />
			</div>
		</>
	);
}
