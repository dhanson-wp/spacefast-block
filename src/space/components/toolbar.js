import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { ToolbarButton } from '@wordpress/components';
import {
	BlockControls,
	__experimentalBlockFullHeightAligmentControl as FullHeightAlignmentControl,
} from '@wordpress/block-editor';
import { external } from '@wordpress/icons';

import {
	getEffectiveDisplay,
	getFullHeightToggleAttributes,
	isFullHeight,
} from '../utils/display';
import { frameUrl } from '../utils/url';

/**
 * Block toolbar: the full-height toggle for the inline mode, and Replace,
 * Refresh details, and Open Space.
 *
 * @param {Object}                 props                Component props.
 * @param {Object}                 props.attributes     Block attributes.
 * @param {(next: Object) => void} props.setAttributes  Updates block attributes.
 * @param {() => void}             props.onReplace      Shows the placeholder to choose another Space.
 * @param {() => void}             props.onRefresh      Fetches the Space's details again.
 * @param {boolean}                props.isRefreshing   Whether a refresh is running.
 * @param {boolean}                props.isHostVerified Whether the Space's host may be framed.
 */
export default function SpaceToolbar( {
	attributes,
	setAttributes,
	onReplace,
	onRefresh,
	isRefreshing,
	isHostVerified,
} ) {
	const { minHeight, minHeightUnit } = attributes;
	const spaceUrl = frameUrl( attributes.url, attributes.startPath );

	// The height to go back to when full height is turned off, as Cover keeps it.
	const [ previousHeight, setPreviousHeight ] = useState( {
		minHeight,
		minHeightUnit,
	} );

	const toggleFullHeight = () => {
		if ( ! isFullHeight( attributes ) ) {
			setPreviousHeight( { minHeight, minHeightUnit } );
		}
		setAttributes(
			getFullHeightToggleAttributes( attributes, previousHeight )
		);
	};

	return (
		<>
			{ getEffectiveDisplay( attributes, isHostVerified ) ===
				'inline' && (
				<BlockControls group="block">
					<FullHeightAlignmentControl
						isActive={ isFullHeight( attributes ) }
						onToggle={ toggleFullHeight }
					/>
				</BlockControls>
			) }
			<BlockControls group="other">
				<ToolbarButton onClick={ onReplace }>
					{ __( 'Replace', 'spacefast-block' ) }
				</ToolbarButton>
				<ToolbarButton
					onClick={ onRefresh }
					isBusy={ isRefreshing }
					disabled={ isRefreshing }
					accessibleWhenDisabled
				>
					{ __( 'Refresh details', 'spacefast-block' ) }
				</ToolbarButton>
				{ spaceUrl && (
					<ToolbarButton
						icon={ external }
						label={ __( 'Open Space', 'spacefast-block' ) }
						href={ spaceUrl }
						target="_blank"
						rel="noopener noreferrer"
						describedBy={ __(
							'(opens in a new tab)',
							'spacefast-block'
						) }
					/>
				) }
			</BlockControls>
		</>
	);
}
