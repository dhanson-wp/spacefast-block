import {
	TextControl,
	__experimentalToolsPanelItem as ToolsPanelItem,
} from '@wordpress/components';

/**
 * A text setting in a tools panel, reset to empty.
 *
 * @param {Object}                 props               Component props.
 * @param {string}                 props.attribute     String attribute it controls.
 * @param {string}                 props.label         Control label.
 * @param {string}                 props.help          Optional help text.
 * @param {string}                 props.placeholder   Text shown while it is empty.
 * @param {Object}                 props.attributes    Block attributes.
 * @param {(next: Object) => void} props.setAttributes Updates block attributes.
 */
export default function TextItem( {
	attribute,
	label,
	help,
	placeholder,
	attributes,
	setAttributes,
} ) {
	return (
		<ToolsPanelItem
			label={ label }
			hasValue={ () => !! attributes[ attribute ] }
			onDeselect={ () => setAttributes( { [ attribute ]: '' } ) }
			isShownByDefault
		>
			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ label }
				help={ help }
				placeholder={ placeholder }
				value={ attributes[ attribute ] }
				onChange={ ( value ) =>
					setAttributes( { [ attribute ]: value } )
				}
			/>
		</ToolsPanelItem>
	);
}
