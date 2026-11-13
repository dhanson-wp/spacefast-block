import { __ } from '@wordpress/i18n';
import { useRef } from '@wordpress/element';
import { ResizableBox } from '@wordpress/components';

import Branding from '../branding';
import Frame from './frame';
import { getCardTitle, hasAspectRatio } from '../../utils/display';

// Only the bottom edge resizes, as on the Cover block.
const RESIZE_EDGES = {
	top: false,
	right: false,
	bottom: true,
	left: false,
	topRight: false,
	bottomRight: false,
	bottomLeft: false,
	topLeft: false,
};

// The smallest height the handle allows, matching the Cover block.
const MIN_RESIZE_HEIGHT = 50;

/**
 * The inline mode: an optional title bar above the live Space, with a handle
 * on the bottom edge that sets the block's minimum height in pixels.
 *
 * @param {Object}                       props                 Component props.
 * @param {Object}                       props.attributes      Block attributes.
 * @param {(next: Object) => void}       props.setAttributes   Updates block attributes.
 * @param {boolean}                      props.isSelected      Whether the block is selected.
 * @param {(isEnabled: boolean) => void} props.toggleSelection Turns block selection on or off while dragging.
 */
export default function InlinePreview( {
	attributes,
	setAttributes,
	isSelected,
	toggleSelection,
} ) {
	// The block's height when the drag started. The handle sits inside the
	// block, so the new height is that plus how far the handle moved.
	const startHeight = useRef( 0 );

	const heightAfter = ( delta ) =>
		Math.max(
			MIN_RESIZE_HEIGHT,
			Math.round( startHeight.current + delta.height )
		);

	return (
		<>
			{ attributes.showTitleBar && (
				<div className="wp-block-spacefast-space__title-bar">
					<span className="wp-block-spacefast-space__title">
						{ getCardTitle( attributes ) }
					</span>
					{ attributes.showBranding && <Branding /> }
					<a
						className="wp-block-spacefast-space__link"
						href={ attributes.url }
						target="_blank"
						rel="noopener noreferrer"
						onClick={ ( event ) => event.preventDefault() }
					>
						{ __( 'Open in new tab', 'spacefast-block' ) }
					</a>
				</div>
			) }
			<ResizableBox
				className="wp-block-spacefast-space__resizable"
				size={ { width: 'auto', height: 'auto' } }
				minHeight={ MIN_RESIZE_HEIGHT }
				enable={ RESIZE_EDGES }
				// An aspect ratio wins over a height, so there is nothing to drag.
				showHandle={ isSelected && ! hasAspectRatio( attributes ) }
				onResizeStart={ ( _event, _direction, element ) => {
					startHeight.current =
						element.parentElement.getBoundingClientRect().height;
					setAttributes( { minHeightUnit: 'px' } );
					toggleSelection( false );
				} }
				onResize={ ( _event, _direction, _element, delta ) => {
					setAttributes( { minHeight: heightAfter( delta ) } );
				} }
				onResizeStop={ ( _event, _direction, _element, delta ) => {
					toggleSelection( true );
					setAttributes( { minHeight: heightAfter( delta ) } );
				} }
			>
				<Frame attributes={ attributes } isSelected={ isSelected } />
			</ResizableBox>
		</>
	);
}
