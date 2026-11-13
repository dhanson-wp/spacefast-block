import {
	ToggleControl,
	__experimentalToolsPanelItem as ToolsPanelItem,
} from '@wordpress/components';

import { getDefaults } from './defaults';

/**
 * An on/off setting in a tools panel, reset to its block.json default.
 *
 * @param {Object}                 props               Component props.
 * @param {string}                 props.attribute     Boolean attribute it controls.
 * @param {string}                 props.label         Control label.
 * @param {string}                 props.help          Optional help text.
 * @param {Object}                 props.attributes    Block attributes.
 * @param {(next: Object) => void} props.setAttributes Updates block attributes.
 */
export default function ToggleItem( {
	attribute,
	label,
	help,
	attributes,
	setAttributes,
} ) {
	const defaultValue = getDefaults( [ attribute ] )[ attribute ];
	return (
		<ToolsPanelItem
			label={ label }
			hasValue={ () => attributes[ attribute ] !== defaultValue }
			onDeselect={ () =>
				setAttributes( { [ attribute ]: defaultValue } )
			}
			isShownByDefault
		>
			<ToggleControl
				__nextHasNoMarginBottom
				label={ label }
				help={ help }
				checked={ !! attributes[ attribute ] }
				onChange={ ( value ) =>
					setAttributes( { [ attribute ]: value } )
				}
			/>
		</ToolsPanelItem>
	);
}
