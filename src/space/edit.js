import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import { Placeholder } from '@wordpress/components';

import icon from './icon';

export default function Edit() {
	const blockProps = useBlockProps();

	return (
		<div { ...blockProps }>
			<Placeholder
				icon={ icon }
				label={ __( 'Spacefast', 'spacefast-block' ) }
			/>
		</div>
	);
}
