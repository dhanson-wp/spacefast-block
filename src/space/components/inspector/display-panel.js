import { __ } from '@wordpress/i18n';
import {
	Flex,
	Notice,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
	__experimentalToolsPanel as ToolsPanel,
	__experimentalToolsPanelItem as ToolsPanelItem,
} from '@wordpress/components';

import {
	canFrame,
	getDisplayNotice,
	getEffectiveDisplay,
	isDisplayAvailable,
} from '../../utils/display';
import useToolsPanelDropdownMenuProps from '../../utils/use-tools-panel-dropdown-menu-props';

/**
 * The Display panel: which of the four modes shows the Space.
 *
 * @param {Object}                 props                Component props.
 * @param {Object}                 props.attributes     Block attributes.
 * @param {(next: Object) => void} props.setAttributes  Updates block attributes.
 * @param {string}                 props.clientId       Block client ID, used as the tools panel ID.
 * @param {boolean}                props.isHostVerified Whether the Space's host may be framed.
 */
export default function DisplayPanel( {
	attributes,
	setAttributes,
	clientId,
	isHostVerified,
} ) {
	const dropdownMenuProps = useToolsPanelDropdownMenuProps();
	const isFrameable = canFrame( attributes, isHostVerified );
	const display = getEffectiveDisplay( attributes, isHostVerified );
	// A Space that can't be framed falls back to a card, so that is its default.
	const defaultDisplay = isFrameable ? 'inline' : 'card';
	const reset = () => setAttributes( { display: defaultDisplay } );
	const notice = getDisplayNotice( attributes, isHostVerified );

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
							<ToggleGroupControlOption
								key={ option.value }
								value={ option.value }
								label={ option.label }
								disabled={
									! isDisplayAvailable(
										option.value,
										isFrameable
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
				</Flex>
			</ToolsPanelItem>
		</ToolsPanel>
	);
}
