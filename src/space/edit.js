import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import { Button } from '@wordpress/components';

import icon from './icon';
import SpacePlaceholder from './components/placeholder';
import { getSpaceAttributes } from './utils/apply-space';

export default function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps();

	const applySpace = ( details ) =>
		setAttributes( getSpaceAttributes( attributes, details ) );

	if ( ! attributes.url ) {
		return (
			<div { ...blockProps }>
				<SpacePlaceholder onApply={ applySpace } />
			</div>
		);
	}

	// Temporary summary; the real preview replaces it in the next task.
	return (
		<div { ...blockProps }>
			<p>
				{ icon }{ ' ' }
				<strong>{ attributes.title || attributes.hostname }</strong>
			</p>
			<p>{ attributes.url }</p>
			<Button
				variant="secondary"
				__next40pxDefaultSize
				onClick={ () => setAttributes( { url: '' } ) }
			>
				{ __( 'Replace', 'spacefast-block' ) }
			</Button>
		</div>
	);
}
