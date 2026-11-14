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
	getEffectiveDisplay,
	getFullPageWarning,
	getMinHeight,
} from './utils/display';
import useColorAndFontSizeProps from './utils/use-color-and-font-size-props';
import useHostVerified from './utils/use-host-verified';
import useIsOnlyBlock from './utils/use-is-only-block';
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
	// Replacing shows the placeholder again without touching the current Space,
	// so Cancel can bring it straight back.
	const [ isReplacing, setIsReplacing ] = useState( false );
	const showSpace = hasSpace && ! isReplacing;
	// Nothing is framed until the server confirms the host, as on the front end.
	const { isHostVerified, setHostVerified } = useHostVerified(
		attributes.url
	);
	const isCheckingHost = hasSpace && isHostVerified === undefined;
	const canFrameHost = isHostVerified === true;
	// Full page is only usable when this is the only block on the page.
	const isOnlyBlock = useIsOnlyBlock( clientId );
	const fullPageWarning = getFullPageWarning(
		attributes,
		canFrameHost,
		isOnlyBlock
	);
	const minHeight =
		showSpace && ! isCheckingHost
			? getMinHeight( attributes, canFrameHost )
			: undefined;
	// The link mode puts color and font size on its button; every other mode
	// on the block wrapper.
	const supportProps = useColorAndFontSizeProps( attributes );
	const isLink =
		showSpace &&
		! isCheckingHost &&
		getEffectiveDisplay( attributes, canFrameHost ) === 'link';
	const wrapperClassNames = [
		showSpace && ! isCheckingHost
			? getBlockClassNames( attributes, canFrameHost )
			: '',
		isLink ? '' : supportProps.className,
	].filter( Boolean );
	const blockProps = useBlockProps( {
		className: wrapperClassNames.length
			? wrapperClassNames.join( ' ' )
			: undefined,
		style: {
			...( isLink ? {} : supportProps.style ),
			...( minHeight ? { minHeight } : {} ),
		},
	} );

	const applySpace = ( details ) => {
		setHostVerified( details.url, details.verified );
		setAttributes( getSpaceAttributes( attributes, details ) );
		setIsReplacing( false );
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

	if ( ! showSpace ) {
		return (
			<div { ...blockProps }>
				<SpacePlaceholder
					onApply={ applySpace }
					onCancel={
						hasSpace ? () => setIsReplacing( false ) : undefined
					}
				/>
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
				onReplace={ () => setIsReplacing( true ) }
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
				isOnlyBlock={ isOnlyBlock }
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
				{ fullPageWarning && (
					<Notice
						className="wp-block-spacefast-space__host-notice"
						status="warning"
						isDismissible={ false }
					>
						{ fullPageWarning }
					</Notice>
				) }
				<SpacePreview
					attributes={ attributes }
					setAttributes={ setAttributes }
					isSelected={ isSelected }
					toggleSelection={ toggleSelection }
					isHostVerified={ canFrameHost }
					isOnlyBlock={ isOnlyBlock }
					supportProps={ supportProps }
				/>
			</div>
		</>
	);
}
