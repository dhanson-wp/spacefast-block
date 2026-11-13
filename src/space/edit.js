import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { useBlockProps } from '@wordpress/block-editor';
import { Notice, Spinner } from '@wordpress/components';
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
import {
	getBlockClassNames,
	getDisplayNotice,
	getMinHeight,
} from './utils/display';
import useHostVerified from './utils/use-host-verified';
import { frameUrl } from './utils/url';

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

	// A URL that can't be used shows the placeholder, as the front end renders nothing.
	const hasSpace = !! frameUrl( attributes.url, '' );
	// Nothing is framed until the server confirms the host, as on the front end.
	const { isHostVerified, setHostVerified } = useHostVerified(
		attributes.url
	);
	const isCheckingHost = hasSpace && isHostVerified === undefined;
	const canFrameHost = isHostVerified === true;
	const minHeight =
		hasSpace && ! isCheckingHost
			? getMinHeight( attributes, canFrameHost )
			: undefined;
	const blockProps = useBlockProps( {
		className:
			hasSpace && ! isCheckingHost
				? getBlockClassNames( attributes, canFrameHost )
				: undefined,
		style: minHeight ? { minHeight } : undefined,
	} );

	const applySpace = ( details ) => {
		setHostVerified( details.url, details.verified );
		setAttributes( getSpaceAttributes( attributes, details ) );
	};

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
			setHostVerified( details.url, details.verified );
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

	if ( isCheckingHost ) {
		return (
			<div { ...blockProps }>
				<Spinner />
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
				isHostVerified={ canFrameHost }
			/>
			<SpaceInspector
				attributes={ attributes }
				setAttributes={ setAttributes }
				clientId={ clientId }
				onResetImage={ () => refreshDetails( { resetImage: true } ) }
				isRefreshing={ isRefreshing }
				isHostVerified={ canFrameHost }
			/>
			<div { ...blockProps }>
				{ ! canFrameHost && (
					<Notice
						className="wp-block-spacefast-space__host-notice"
						status="warning"
						isDismissible={ false }
						actions={ [
							{
								label: __(
									'Refresh details',
									'spacefast-block'
								),
								onClick: () => refreshDetails(),
								disabled: isRefreshing,
							},
						] }
					>
						{ getDisplayNotice( attributes, false ) }
					</Notice>
				) }
				<SpacePreview
					attributes={ attributes }
					setAttributes={ setAttributes }
					isSelected={ isSelected }
					toggleSelection={ toggleSelection }
					isHostVerified={ canFrameHost }
				/>
			</div>
		</>
	);
}
