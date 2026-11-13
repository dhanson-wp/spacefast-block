import { __experimentalToolsPanel as ToolsPanel } from '@wordpress/components';

import { getDefaults } from './defaults';
import useToolsPanelDropdownMenuProps from '../../utils/use-tools-panel-dropdown-menu-props';

/**
 * A sidebar tools panel whose "Reset all" puts the named attributes back to
 * their block.json defaults.
 *
 * @param {Object}                 props               Component props.
 * @param {string}                 props.label         Panel title.
 * @param {string[]}               props.resetKeys     Attributes the panel's items set.
 * @param {(next: Object) => void} props.setAttributes Updates block attributes.
 * @param {Object}                 props.children      Panel items.
 */
export default function Panel( { label, resetKeys, setAttributes, children } ) {
	const dropdownMenuProps = useToolsPanelDropdownMenuProps();
	return (
		<ToolsPanel
			label={ label }
			resetAll={ () => setAttributes( getDefaults( resetKeys ) ) }
			dropdownMenuProps={ dropdownMenuProps }
		>
			{ children }
		</ToolsPanel>
	);
}
