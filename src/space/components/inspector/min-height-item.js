import { __ } from '@wordpress/i18n';
import { useInstanceId } from '@wordpress/compose';
import { useSettings } from '@wordpress/block-editor';
import {
	__experimentalToolsPanelItem as ToolsPanelItem,
	__experimentalUnitControl as UnitControl,
	__experimentalUseCustomUnits as useCustomUnits,
} from '@wordpress/components';

import { withoutAspectRatio } from '../../utils/display';

const HEIGHT_UNITS = [ 'px', 'em', 'rem', 'vh', 'vw' ];
// The smallest pixel height, matching the Cover block.
const MIN_PX_HEIGHT = 50;

/**
 * The Minimum height control in the Dimensions panel, built as the Cover
 * block builds it. Setting a height clears any aspect ratio.
 *
 * @param {Object}                 props               Component props.
 * @param {Object}                 props.attributes    Block attributes.
 * @param {(next: Object) => void} props.setAttributes Updates block attributes.
 * @param {string}                 props.clientId      Block client ID.
 */
export default function MinHeightItem( {
	attributes,
	setAttributes,
	clientId,
} ) {
	const { minHeight, minHeightUnit = 'px', style } = attributes;
	const inputId = `spacefast-space-min-height-${ useInstanceId(
		UnitControl
	) }`;
	const hasAspectRatio = !! style?.dimensions?.aspectRatio;

	const [ themeUnits ] = useSettings( 'spacing.units' );
	const allowedUnits = ( themeUnits || HEIGHT_UNITS ).filter( ( unit ) =>
		HEIGHT_UNITS.includes( unit )
	);
	const units = useCustomUnits( {
		availableUnits: allowedUnits.length ? allowedUnits : HEIGHT_UNITS,
		defaultValues: { px: 600, em: 20, rem: 20, vw: 20, vh: 50 },
	} );

	// Keep the unit even while the number is empty, as Cover does, so a unit
	// picked before typing isn't lost.
	const quantity = hasAspectRatio || minHeight === undefined ? '' : minHeight;
	const value = `${ quantity }${ minHeightUnit }`;

	const resetAttributes = () => ( {
		minHeight: undefined,
		minHeightUnit: undefined,
	} );

	return (
		<ToolsPanelItem
			className="single-column"
			label={ __( 'Minimum height', 'spacefast-block' ) }
			hasValue={ () => !! minHeight }
			onDeselect={ () => setAttributes( resetAttributes() ) }
			resetAllFilter={ resetAttributes }
			isShownByDefault
			panelId={ clientId }
		>
			<UnitControl
				__next40pxDefaultSize
				label={ __( 'Minimum height', 'spacefast-block' ) }
				id={ inputId }
				isResetValueOnUnitChange
				min={ minHeightUnit === 'px' ? MIN_PX_HEIGHT : 0 }
				units={ units }
				value={ value }
				onChange={ ( rawValue ) => {
					const next =
						rawValue !== '' ? parseFloat( rawValue ) : undefined;
					if ( Number.isNaN( next ) ) {
						return;
					}
					setAttributes( {
						minHeight: next,
						style: withoutAspectRatio( style ),
					} );
				} }
				onUnitChange={ ( nextUnit ) =>
					setAttributes( { minHeightUnit: nextUnit } )
				}
			/>
		</ToolsPanelItem>
	);
}
