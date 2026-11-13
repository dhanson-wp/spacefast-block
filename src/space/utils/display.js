import { __ } from '@wordpress/i18n';

/**
 * Display modes that put the Space in a frame on the page.
 */
const FRAMED_DISPLAYS = [ 'inline', 'fullpage' ];

/**
 * Whether a display mode can be used. A Space that cannot be framed (private
 * Spaces, or ones that refuse framing) can only be shown as a card or a link.
 *
 * @param {string}  display     Display mode.
 * @param {boolean} isFrameable Whether the Space can be framed.
 * @return {boolean} True if the mode is available.
 */
export function isDisplayAvailable( display, isFrameable ) {
	return isFrameable || ! FRAMED_DISPLAYS.includes( display );
}

/**
 * Whether the Space may be put in a frame: it can be framed, and the server
 * has confirmed its host. A Space on a host that hasn't been confirmed is
 * treated exactly like one that cannot be framed. Mirrors
 * `Space_Display::can_frame()`.
 *
 * @param {Object}  attributes             Block attributes.
 * @param {boolean} attributes.isFrameable Whether the Space can be framed.
 * @param {boolean} isHostVerified         Whether the Space's host may be framed.
 * @return {boolean} True if the Space may be framed.
 */
export function canFrame( { isFrameable }, isHostVerified ) {
	return !! isHostVerified && !! isFrameable;
}

/**
 * The mode the block is shown in. A Space that can no longer be framed is
 * shown as a card, as the front end does.
 *
 * @param {Object}  attributes         Block attributes.
 * @param {string}  attributes.display Display mode.
 * @param {boolean} isHostVerified     Whether the Space's host may be framed.
 * @return {string} Display mode.
 */
export function getEffectiveDisplay( attributes, isHostVerified = true ) {
	const { display } = attributes;
	return isDisplayAvailable( display, canFrame( attributes, isHostVerified ) )
		? display
		: 'card';
}

/**
 * The note shown when a Space can only be a card or a link, or '' when every
 * mode is available.
 *
 * @param {Object}  attributes     Block attributes.
 * @param {boolean} isHostVerified Whether the Space's host may be framed.
 * @return {string} Notice text.
 */
export function getDisplayNotice( attributes, isHostVerified = true ) {
	if ( ! isHostVerified ) {
		return __(
			"This link hasn't been confirmed as a Spacefast Space. Refresh details to check it.",
			'spacefast-block'
		);
	}
	if ( attributes.isFrameable ) {
		return '';
	}
	return attributes.isPrivate
		? __(
				'This Space is private, so it can only be shown as a card or link.',
				'spacefast-block'
			)
		: __(
				"This Space can't be shown inside a page, so it can only be shown as a card or link.",
				'spacefast-block'
			);
}

/**
 * Whether the block has an explicit aspect ratio, which wins over any height.
 * An `auto` ratio is no ratio, as the block editor's dimensions support has it.
 *
 * @param {Object} attributes Block attributes.
 * @return {boolean} True if an aspect ratio other than auto is set.
 */
export function hasAspectRatio( attributes ) {
	const aspectRatio = attributes.style?.dimensions?.aspectRatio;
	return !! aspectRatio && `${ aspectRatio }`.trim().toLowerCase() !== 'auto';
}

/**
 * The inline `min-height` for the block wrapper. Only the inline mode has a
 * minimum height, and an explicit aspect ratio takes precedence over it.
 *
 * @param {Object}  attributes     Block attributes.
 * @param {boolean} isHostVerified Whether the Space's host may be framed.
 * @return {string|undefined} CSS length, or undefined for none.
 */
export function getMinHeight( attributes, isHostVerified = true ) {
	const { minHeight, minHeightUnit } = attributes;
	if (
		getEffectiveDisplay( attributes, isHostVerified ) !== 'inline' ||
		! minHeight ||
		hasAspectRatio( attributes )
	) {
		return undefined;
	}
	return `${ minHeight }${ minHeightUnit || 'px' }`;
}

/**
 * Whether the block fills the viewport height, as the Cover block decides it.
 *
 * @param {Object} attributes Block attributes.
 * @return {boolean} True at 100vh with no aspect ratio.
 */
export function isFullHeight( attributes ) {
	return (
		attributes.minHeightUnit === 'vh' &&
		attributes.minHeight === 100 &&
		! hasAspectRatio( attributes )
	);
}

/**
 * Removes the aspect ratio from a block style, dropping objects left empty.
 *
 * @param {Object|undefined} style Block style attribute.
 * @return {Object|undefined} Style without an aspect ratio.
 */
export function withoutAspectRatio( style ) {
	if ( ! style ) {
		return undefined;
	}
	const { dimensions = {}, ...rest } = style;

	const { aspectRatio, ...otherDimensions } = dimensions;
	const next = { ...rest };
	if ( Object.keys( otherDimensions ).length ) {
		next.dimensions = otherDimensions;
	}
	return Object.keys( next ).length ? next : undefined;
}

/**
 * Attributes for the full-height toolbar toggle, following the Cover block:
 * turning it on sets 100vh and clears any aspect ratio; turning it off puts
 * back the height the block had before, or clears it if that was also 100vh.
 *
 * @param {Object} attributes Current block attributes.
 * @param {Object} previous   `minHeight` and `minHeightUnit` from before full height was turned on.
 * @return {Object} Attributes to set.
 */
export function getFullHeightToggleAttributes( attributes, previous ) {
	if ( isFullHeight( attributes ) ) {
		if ( previous.minHeightUnit === 'vh' && previous.minHeight === 100 ) {
			return { minHeight: undefined, minHeightUnit: undefined };
		}
		return {
			minHeight: previous.minHeight,
			minHeightUnit: previous.minHeightUnit,
		};
	}
	return {
		minHeight: 100,
		minHeightUnit: 'vh',
		style: withoutAspectRatio( attributes.style ),
	};
}

/**
 * The badge text for a private Space.
 *
 * @param {Object} attributes              Block attributes.
 * @param {string} attributes.privateLabel Badge text.
 * @return {string} Label.
 */
export function getPrivateLabel( { privateLabel } ) {
	return privateLabel || __( 'Private', 'spacefast-block' );
}

/**
 * The call to action for a private Space.
 *
 * @param {Object} attributes            Block attributes.
 * @param {string} attributes.privateCta Call to action.
 * @return {string} Call to action.
 */
export function getPrivateCta( { privateCta } ) {
	return privateCta || __( 'Request access', 'spacefast-block' );
}

/**
 * The link text used when the editor hasn't written one.
 *
 * @param {Object} attributes Block attributes.
 * @return {string} Link text.
 */
export function getLinkFallback( attributes ) {
	return attributes.isPrivate
		? getPrivateCta( attributes )
		: __( 'Open Space', 'spacefast-block' );
}

/**
 * The card heading: the Space title, or its hostname when it has none.
 *
 * @param {Object} attributes          Block attributes.
 * @param {string} attributes.title    Space title.
 * @param {string} attributes.hostname Space hostname.
 * @return {string} Heading.
 */
export function getCardTitle( { title, hostname } ) {
	return title || hostname;
}

/**
 * Whether the card shows an image. A media library image counts even before
 * its URL is known. A private Space's own image can't be loaded by visitors,
 * so it only shows one copied into the media library.
 *
 * @param {Object}  attributes           Block attributes.
 * @param {boolean} attributes.showImage Whether the card shows an image.
 * @param {string}  attributes.imageUrl  Image URL.
 * @param {number}  attributes.imageId   Media library image ID.
 * @param {boolean} attributes.isPrivate Whether the Space is private.
 * @return {boolean} True if the card shows an image.
 */
export function hasCardImage( { showImage, imageUrl, imageId, isPrivate } ) {
	if ( ! showImage ) {
		return false;
	}
	return imageId > 0 || ( ! isPrivate && !! imageUrl );
}

/**
 * The image the card shows: the media library image when one is chosen, else
 * the Space's own image. `imageUrl` keeps the Space's own image, so it is
 * also what shows until the library image's URL is known.
 *
 * @param {Object} attributes          Block attributes.
 * @param {string} attributes.imageUrl The Space's own image URL.
 * @param {number} attributes.imageId  Media library image ID.
 * @param {string} mediaUrl            URL of the media library image, looked up from its ID.
 * @return {string} Image URL, or '' for none.
 */
export function getCardImageUrl( attributes, mediaUrl = '' ) {
	if ( ! hasCardImage( attributes ) ) {
		return '';
	}
	return ( attributes.imageId ? mediaUrl : '' ) || attributes.imageUrl || '';
}

/**
 * Class names for the block wrapper, shared with the front end.
 *
 * @param {Object}  attributes     Block attributes.
 * @param {boolean} isHostVerified Whether the Space's host may be framed.
 * @return {string} Space-separated class names.
 */
export function getBlockClassNames( attributes, isHostVerified = true ) {
	const display = getEffectiveDisplay( attributes, isHostVerified );
	const classNames = [ `is-display-${ display }` ];
	if ( display === 'card' && hasCardImage( attributes ) ) {
		classNames.push( `has-image-${ attributes.imagePosition || 'top' }` );
	}
	if ( attributes.isPrivate ) {
		classNames.push( 'is-private' );
	}
	return classNames.join( ' ' );
}

/**
 * The body for `POST /sideload`, or null when there is nothing to copy into
 * the media library. Private Spaces send their ID so the server can fetch the
 * preview with the editor's account. A public image link goes with the
 * Space's title, which becomes the image's alt text.
 *
 * @param {Object}  attributes           Block attributes.
 * @param {string}  attributes.imageUrl  Image URL.
 * @param {number}  attributes.imageId   Media library image ID.
 * @param {boolean} attributes.isPrivate Whether the Space is private.
 * @param {string}  attributes.spaceId   Space ID.
 * @param {string}  attributes.title     Space title.
 * @return {Object|null} Request body.
 */
export function getSideloadRequest( {
	imageUrl,
	imageId,
	isPrivate,
	spaceId,
	title,
} ) {
	if ( imageId ) {
		return null;
	}
	if ( isPrivate ) {
		return spaceId ? { spaceId } : null;
	}
	return imageUrl ? { url: imageUrl, alt: title || '' } : null;
}

/**
 * Attributes that forget the chosen Space so the placeholder shows again.
 * Display settings are left alone.
 *
 * @return {Object} Attributes to set.
 */
export function getClearedSpaceAttributes() {
	return {
		url: '',
		spaceId: '',
		title: '',
		description: '',
		hostname: '',
		imageUrl: '',
		imageId: 0,
		isFrameable: true,
		isPrivate: false,
	};
}
