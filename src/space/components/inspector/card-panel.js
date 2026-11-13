import { __ } from '@wordpress/i18n';
import {
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
	__experimentalToolsPanel as ToolsPanel,
	__experimentalToolsPanelItem as ToolsPanelItem,
} from '@wordpress/components';

import CardImageItem from './card-image-item';
import ToggleItem from './toggle-item';
import { getDefaults } from './defaults';
import useToolsPanelDropdownMenuProps from '../../utils/use-tools-panel-dropdown-menu-props';

const CARD_KEYS = [
	'showImage',
	'imagePosition',
	'showDescription',
	'showHostname',
];

/**
 * The Card panel: what the card shows and which image it uses.
 *
 * @param {Object}                 props               Component props.
 * @param {Object}                 props.attributes    Block attributes.
 * @param {(next: Object) => void} props.setAttributes Updates block attributes.
 * @param {string}                 props.clientId      Block client ID, used as the tools panel ID.
 * @param {() => void}             props.onResetImage  Puts the Space's own image back.
 * @param {boolean}                props.isRefreshing  Whether the Space's details are being fetched.
 */
export default function CardPanel( {
	attributes,
	setAttributes,
	clientId,
	onResetImage,
	isRefreshing,
} ) {
	const dropdownMenuProps = useToolsPanelDropdownMenuProps();
	const itemProps = { attributes, setAttributes, clientId };

	return (
		<ToolsPanel
			panelId={ clientId }
			label={ __( 'Card', 'spacefast-block' ) }
			resetAll={ () => {
				setAttributes( getDefaults( CARD_KEYS ) );
				if ( attributes.imageId ) {
					onResetImage();
				}
			} }
			dropdownMenuProps={ dropdownMenuProps }
		>
			<ToggleItem
				attribute="showImage"
				label={ __( 'Show image', 'spacefast-block' ) }
				{ ...itemProps }
			/>
			{ attributes.showImage && (
				<ToolsPanelItem
					panelId={ clientId }
					label={ __( 'Image position', 'spacefast-block' ) }
					hasValue={ () => attributes.imagePosition !== 'top' }
					onDeselect={ () =>
						setAttributes( { imagePosition: 'top' } )
					}
					isShownByDefault
				>
					<ToggleGroupControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						isBlock
						label={ __( 'Image position', 'spacefast-block' ) }
						value={ attributes.imagePosition }
						onChange={ ( imagePosition ) =>
							setAttributes( { imagePosition } )
						}
					>
						<ToggleGroupControlOption
							value="top"
							label={ __( 'Top', 'spacefast-block' ) }
						/>
						<ToggleGroupControlOption
							value="side"
							label={ __( 'Side', 'spacefast-block' ) }
						/>
					</ToggleGroupControl>
				</ToolsPanelItem>
			) }
			{ attributes.showImage && (
				<CardImageItem
					{ ...itemProps }
					onResetImage={ onResetImage }
					isRefreshing={ isRefreshing }
				/>
			) }
			<ToggleItem
				attribute="showDescription"
				label={ __( 'Show description', 'spacefast-block' ) }
				{ ...itemProps }
			/>
			<ToggleItem
				attribute="showHostname"
				label={ __( 'Show address', 'spacefast-block' ) }
				{ ...itemProps }
			/>
		</ToolsPanel>
	);
}
