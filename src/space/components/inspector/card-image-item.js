import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import {
	BaseControl,
	Button,
	Flex,
	Notice,
	__experimentalToolsPanelItem as ToolsPanelItem,
} from '@wordpress/components';
import { MediaUpload, MediaUploadCheck } from '@wordpress/block-editor';
import { store as noticesStore } from '@wordpress/notices';

import { getErrorMessage, sideloadImage } from '../../utils/api';
import { getSideloadRequest } from '../../utils/display';

/**
 * The card image controls: replace it from the media library, copy the
 * Space's own image into the media library, or go back to the Space's image.
 *
 * @param {Object}                 props               Component props.
 * @param {Object}                 props.attributes    Block attributes.
 * @param {(next: Object) => void} props.setAttributes Updates block attributes.
 * @param {string}                 props.clientId      Block client ID, used as the tools panel ID.
 * @param {() => void}             props.onResetImage  Puts the Space's own image back.
 * @param {boolean}                props.isRefreshing  Whether the Space's details are being fetched.
 */
export default function CardImageItem( {
	attributes,
	setAttributes,
	clientId,
	onResetImage,
	isRefreshing,
} ) {
	const { imageId, isPrivate } = attributes;
	const [ isUploading, setIsUploading ] = useState( false );
	// Shown under the buttons until the next try, so a refusal is not missed.
	const [ uploadError, setUploadError ] = useState( '' );
	const { createSuccessNotice } = useDispatch( noticesStore );
	const postId = useSelect(
		( select ) => select( 'core/editor' )?.getCurrentPostId?.() || 0,
		[]
	);
	const sideloadRequest = getSideloadRequest( attributes );

	const upload = async () => {
		setIsUploading( true );
		setUploadError( '' );
		try {
			const media = await sideloadImage( sideloadRequest, postId );
			// `imageUrl` keeps the Space's own image, the fallback if this one is deleted.
			setAttributes( { imageId: media.id } );
			createSuccessNotice( __( 'Image uploaded.', 'spacefast-block' ), {
				type: 'snackbar',
			} );
		} catch ( error ) {
			setUploadError( getErrorMessage( error ) );
		}
		setIsUploading( false );
	};

	return (
		<ToolsPanelItem
			panelId={ clientId }
			label={ __( 'Image', 'spacefast-block' ) }
			hasValue={ () => !! imageId }
			onDeselect={ onResetImage }
			isShownByDefault
		>
			<BaseControl.VisualLabel>
				{ __( 'Image', 'spacefast-block' ) }
			</BaseControl.VisualLabel>
			<Flex justify="flex-start" wrap>
				<MediaUploadCheck>
					<MediaUpload
						allowedTypes={ [ 'image' ] }
						value={ imageId || undefined }
						onSelect={ ( media ) =>
							setAttributes( { imageId: media.id } )
						}
						render={ ( { open } ) => (
							<Button
								variant="secondary"
								__next40pxDefaultSize
								onClick={ open }
							>
								{ __( 'Replace', 'spacefast-block' ) }
							</Button>
						) }
					/>
					{ sideloadRequest && (
						<Button
							variant="secondary"
							__next40pxDefaultSize
							isBusy={ isUploading }
							disabled={ isUploading }
							accessibleWhenDisabled
							onClick={ upload }
						>
							{ isPrivate
								? __(
										'Upload preview to media library',
										'spacefast-block'
									)
								: __(
										'Upload to media library',
										'spacefast-block'
									) }
						</Button>
					) }
				</MediaUploadCheck>
				{ !! imageId && (
					<Button
						variant="tertiary"
						__next40pxDefaultSize
						isBusy={ isRefreshing }
						disabled={ isRefreshing }
						accessibleWhenDisabled
						onClick={ onResetImage }
					>
						{ __( 'Reset', 'spacefast-block' ) }
					</Button>
				) }
			</Flex>
			{ uploadError && sideloadRequest && (
				<Notice
					status="error"
					isDismissible={ false }
					className="wp-block-spacefast-space__upload-error"
				>
					{ uploadError }
				</Notice>
			) }
		</ToolsPanelItem>
	);
}
