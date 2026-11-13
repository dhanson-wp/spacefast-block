import { __ } from '@wordpress/i18n';
import {
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
	__experimentalToolsPanelItem as ToolsPanelItem,
} from '@wordpress/components';

import Panel from './panel';
import ToggleItem from './toggle-item';

/**
 * The Click behavior panel for cards and links: open the Space in a modal or
 * go to it.
 *
 * @param {Object}                 props               Component props.
 * @param {Object}                 props.attributes    Block attributes.
 * @param {(next: Object) => void} props.setAttributes Updates block attributes.
 * @param {string}                 props.clientId      Block client ID, used as the tools panel ID.
 */
export default function ClickPanel( { attributes, setAttributes, clientId } ) {
	const { clickAction, isFrameable } = attributes;
	// A Space that can't be framed can't open in a modal either.
	const defaultAction = isFrameable ? 'modal' : 'link';
	const itemProps = { attributes, setAttributes, clientId };

	return (
		<Panel
			label={ __( 'Click behavior', 'spacefast-block' ) }
			clientId={ clientId }
			resetKeys={ [ 'openInNewTab', 'modalExternalLink' ] }
			setAttributes={ ( defaults ) =>
				setAttributes( { ...defaults, clickAction: defaultAction } )
			}
		>
			<ToolsPanelItem
				panelId={ clientId }
				label={ __( 'On click', 'spacefast-block' ) }
				hasValue={ () => clickAction !== defaultAction }
				onDeselect={ () =>
					setAttributes( { clickAction: defaultAction } )
				}
				isShownByDefault
			>
				<ToggleGroupControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					isBlock
					label={ __( 'On click', 'spacefast-block' ) }
					value={ isFrameable ? clickAction : 'link' }
					onChange={ ( value ) =>
						setAttributes( { clickAction: value } )
					}
				>
					<ToggleGroupControlOption
						value="modal"
						label={ __( 'Open in a modal', 'spacefast-block' ) }
						disabled={ ! isFrameable }
					/>
					<ToggleGroupControlOption
						value="link"
						label={ __( 'Go to the Space', 'spacefast-block' ) }
					/>
				</ToggleGroupControl>
			</ToolsPanelItem>
			{ clickAction === 'link' || ! isFrameable ? (
				<ToggleItem
					attribute="openInNewTab"
					label={ __( 'Open in new tab', 'spacefast-block' ) }
					{ ...itemProps }
				/>
			) : (
				<ToggleItem
					attribute="modalExternalLink"
					label={ __( 'Show link to the Space', 'spacefast-block' ) }
					help={ __(
						'Adds a link in the modal that opens the Space in a new tab.',
						'spacefast-block'
					) }
					{ ...itemProps }
				/>
			) }
		</Panel>
	);
}
