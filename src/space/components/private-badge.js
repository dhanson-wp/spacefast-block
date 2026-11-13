import { Icon, lock } from '@wordpress/icons';

import { getPrivateLabel } from '../utils/display';

/**
 * The badge that marks a private Space: a lock and a short label.
 *
 * @param {Object} props            Component props.
 * @param {Object} props.attributes Block attributes.
 */
export default function PrivateBadge( { attributes } ) {
	return (
		<span className="wp-block-spacefast-space__badge">
			<Icon icon={ lock } size={ 16 } aria-hidden="true" />
			{ getPrivateLabel( attributes ) }
		</span>
	);
}
