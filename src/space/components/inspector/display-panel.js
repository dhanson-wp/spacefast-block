import { __ } from '@wordpress/i18n';
import {
	Flex,
	Notice,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOptionIcon as ToggleGroupControlOptionIcon,
	__experimentalToolsPanel as ToolsPanel,
	__experimentalToolsPanelItem as ToolsPanelItem,
} from '@wordpress/components';

import {
	canFrame,
	getDefaultDisplay,
	getDisplayNotice,
	getEffectiveDisplay,
	getFullPageNotice,
	isDisplayChoosable,
} from '../../utils/display';
import useToolsPanelDropdownMenuProps from '../../utils/use-tools-panel-dropdown-menu-props';
import modeIcons from '../../mode-icons';

/**
 * The Display panel: which of the four modes shows the Space, as a row of
 * icons like the Group block's layout choices.
 *
 * @param {Object}                 props                Component props.
 * @param {Object}                 props.attributes     Block attributes.
 * @param {(next: Object) => void} props.setAttributes  Updates block attributes.
 * @param {string}                 props.clientId       Block client ID, used as the tools panel ID.
 * @param {boolean}                props.isHostVerified Whether the Space's host may be framed.
 * @param {boolean}                props.isOnlyBlock    Whether this is the only block on the page.
 */
export default function DisplayPanel( {
	attributes,
	setAttributes,
	clientId,
	isHostVerified,
	isOnlyBlock,
} ) {
	const dropdownMenuProps = useToolsPanelDropdownMenuProps();
	const isFrameable = canFrame( attributes, isHostVerified );
	const display = getEffectiveDisplay( attributes, isHostVerified );
	// A private Space falls back to a card, so that is its default. This must
	// not follow the host check, or a reset made before it answers would stick.
	const defaultDisplay = getDefaultDisplay( attributes );
	const reset = () => setAttributes( { display: defaultDisplay } );
	const notice = getDisplayNotice( attributes, isHostVerified );
	const fullPageNotice = getFullPageNotice( isOnlyBlock );

	const options = [
		{ value: 'inline', label: __( 'Inline', 'spacefast-block' ) },
		{ value: 'fullpage', label: __( 'Full page', 'spacefast-block' ) },
		{ value: 'card', label: __( 'Card', 'spacefast-block' ) },
		{ value: 'link', label: __( 'Link', 'spacefast-block' ) },
	];

	return (
		<ToolsPanel
			panelId={ clientId }
			label={ __( 'Display', 'spacefast-block' ) }
			resetAll={ reset }
			dropdownMenuProps={ dropdownMenuProps }
		>
			<ToolsPanelItem
				panelId={ clientId }
				label={ __( 'Display', 'spacefast-block' ) }
				hasValue={ () => display !== defaultDisplay }
				onDeselect={ reset }
				isShownByDefault
			>
				<Flex direction="column" gap={ 4 }>
					<ToggleGroupControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						isBlock
						label={ __( 'Show as', 'spacefast-block' ) }
						value={ display }
						onChange={ ( value ) =>
							setAttributes( { display: value } )
						}
					>
						{ options.map( ( option ) => (
							<ToggleGroupControlOptionIcon
								key={ option.value }
								value={ option.value }
								icon={ modeIcons[ option.value ] }
								label={ option.label }
								disabled={
									! isDisplayChoosable(
										option.value,
										isFrameable,
										isOnlyBlock
									)
								}
							/>
						) ) }
					</ToggleGroupControl>
					{ notice && (
						<Notice status="info" isDismissible={ false }>
							{ notice }
						</Notice>
					) }
					{ fullPageNotice && (
						<Notice status="info" isDismissible={ false }>
							{ fullPageNotice }
						</Notice>
					) }
				</Flex>
			</ToolsPanelItem>
		</ToolsPanel>
	);
}
