import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { useBlockProps } from '@wordpress/block-editor';
import { store as noticesStore } from '@wordpress/notices';

import SpacePlaceholder from './components/placeholder';
import SpacePreview from './components/preview';
import SpaceInspector from './components/inspector';
import SpaceToolbar from './components/toolbar';
import { getErrorMessage, resolveSpace } from './utils/api';
import {
	getRefreshedAttributes,
	getSpaceAttributes,
} from './utils/apply-space';
import { getBlockClassNames, getMinHeight } from './utils/display';

export default function Edit( {
	attributes,
	setAttributes,
	isSelected,
	clientId,
	toggleSelection,
} ) {
	const [ isRefreshing, setIsRefreshing ] = useState( false );
	const { createSuccessNotice, createErrorNotice } =
		useDispatch( noticesStore );

	const hasSpace = !! attributes.url;
	const minHeight = hasSpace ? getMinHeight( attributes ) : undefined;
	const blockProps = useBlockProps( {
		className: hasSpace ? getBlockClassNames( attributes ) : undefined,
		style: minHeight ? { minHeight } : undefined,
	} );

	const applySpace = ( details ) =>
		setAttributes( getSpaceAttributes( attributes, details ) );

	/**
	 * Fetches the Space's details again, skipping the server's cache.
	 *
	 * @param {Object}  options            Options.
	 * @param {boolean} options.resetImage Also drop a media library image.
	 */
	const refreshDetails = async ( { resetImage = false } = {} ) => {
		setIsRefreshing( true );
		try {
			const details = await resolveSpace(
				attributes.url,
				attributes.spaceId,
				true
			);
			setAttributes(
				getRefreshedAttributes( attributes, details, { resetImage } )
			);
			createSuccessNotice(
				__( 'Space details updated.', 'spacefast-block' ),
				{ type: 'snackbar' }
			);
		} catch ( error ) {
			createErrorNotice( getErrorMessage( error ), { type: 'snackbar' } );
		}
		setIsRefreshing( false );
	};

	if ( ! hasSpace ) {
		return (
			<div { ...blockProps }>
				<SpacePlaceholder onApply={ applySpace } />
			</div>
		);
	}

	return (
		<>
			<SpaceToolbar
				attributes={ attributes }
				setAttributes={ setAttributes }
				onRefresh={ () => refreshDetails() }
				isRefreshing={ isRefreshing }
			/>
			<SpaceInspector
				attributes={ attributes }
				setAttributes={ setAttributes }
				clientId={ clientId }
				onResetImage={ () => refreshDetails( { resetImage: true } ) }
				isRefreshing={ isRefreshing }
			/>
			<div { ...blockProps }>
				<SpacePreview
					attributes={ attributes }
					setAttributes={ setAttributes }
					isSelected={ isSelected }
					toggleSelection={ toggleSelection }
				/>
			</div>
		</>
	);
}
