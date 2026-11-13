import { useSelect } from '@wordpress/data';

import Branding from '../branding';
import PrivateBadge from '../private-badge';
import {
	getCardImageUrl,
	getCardTitle,
	getPrivateCta,
} from '../../utils/display';
import { frameUrl } from '../../utils/url';

/**
 * The card mode, as it appears on the front end, with clicks turned off.
 *
 * @param {Object} props            Component props.
 * @param {Object} props.attributes Block attributes.
 */
export default function CardPreview( { attributes } ) {
	// A library image may have no URL stored with the block; look it up.
	const mediaUrl = useSelect(
		( select ) =>
			attributes.imageId && ! attributes.imageUrl
				? select( 'core' ).getMedia( attributes.imageId )?.source_url
				: '',
		[ attributes.imageId, attributes.imageUrl ]
	);
	const imageUrl = getCardImageUrl( attributes, mediaUrl );
	const href = frameUrl( attributes.url, attributes.startPath );
	const {
		description,
		hostname,
		isPrivate,
		showDescription,
		showHostname,
		showPrivateBadge,
		showBranding,
	} = attributes;

	return (
		<a
			className="wp-block-spacefast-space__card"
			href={ href || undefined }
			onClick={ ( event ) => event.preventDefault() }
		>
			{ imageUrl && (
				<img
					className="wp-block-spacefast-space__image"
					src={ imageUrl }
					alt=""
				/>
			) }
			<span className="wp-block-spacefast-space__content">
				{ isPrivate && showPrivateBadge && (
					<PrivateBadge attributes={ attributes } />
				) }
				<span className="wp-block-spacefast-space__title">
					{ getCardTitle( attributes ) }
				</span>
				{ showDescription && description && (
					<span className="wp-block-spacefast-space__description">
						{ description }
					</span>
				) }
				{ showHostname && hostname && (
					<span className="wp-block-spacefast-space__hostname">
						{ hostname }
					</span>
				) }
				{ isPrivate && (
					<span className="wp-block-spacefast-space__cta">
						{ getPrivateCta( attributes ) }
					</span>
				) }
				{ showBranding && <Branding /> }
			</span>
		</a>
	);
}
