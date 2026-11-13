import { __ } from '@wordpress/i18n';
import { Icon } from '@wordpress/icons';

import icon from '../icon';

/**
 * The Spacefast mark: the block icon at 16px and the word "Spacefast".
 */
export default function Branding() {
	return (
		<span className="wp-block-spacefast-space__branding">
			<Icon icon={ icon } size={ 16 } aria-hidden="true" />
			{ __( 'Spacefast', 'spacefast-block' ) }
		</span>
	);
}
