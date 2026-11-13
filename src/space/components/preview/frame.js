import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';

import { frameUrl } from '../../utils/url';

/**
 * The live Space in a frame, with a transparent shield over it until the block
 * is selected and clicked, the way core blocks preview embedded content. Its
 * parent must be positioned.
 *
 * @param {Object}  props            Component props.
 * @param {Object}  props.attributes Block attributes.
 * @param {boolean} props.isSelected Whether the block is selected.
 */
export default function Frame( { attributes, isSelected } ) {
	const [ isInteractive, setIsInteractive ] = useState( false );

	if ( ! isSelected && isInteractive ) {
		// Only reset once the block is deselected: resetting when it becomes
		// selected would drop the shield on mouse down, and the mouse up would
		// land in the Space.
		setIsInteractive( false );
	}

	const src = frameUrl( attributes.url, attributes.startPath );

	/* eslint-disable jsx-a11y/no-static-element-interactions -- The shield only catches the first click so the block can be selected; it has no role for the user. */
	return (
		<>
			{ src && (
				<iframe
					className="wp-block-spacefast-space__frame"
					src={ src }
					title={
						attributes.title ||
						__( 'Spacefast Space', 'spacefast-block' )
					}
					loading={ attributes.lazyLoad ? 'lazy' : undefined }
					allowFullScreen={ attributes.allowFullscreen }
					referrerPolicy="strict-origin-when-cross-origin"
					sandbox="allow-scripts allow-same-origin allow-forms allow-popups allow-downloads"
				/>
			) }
			{ ! isInteractive && (
				<div
					className="wp-block-spacefast-space__click-shield"
					onMouseUp={ () => setIsInteractive( true ) }
				/>
			) }
		</>
	);
	/* eslint-enable jsx-a11y/no-static-element-interactions */
}
