/**
 * Works out the block attributes to save when an editor picks a Space.
 *
 * Display settings the editor already chose are kept, except that a Space that
 * cannot be framed (private Spaces, or ones that refuse framing) can only be
 * shown as a card or a link, and can only open on its own page.
 *
 * @param {Object} attributes Current block attributes.
 * @param {Object} details    Space details from the resolve route.
 * @return {Object} Attributes to merge into the block.
 */
export function getSpaceAttributes( attributes, details ) {
	const isFrameable = details.isFrameable !== false;
	const next = {
		url: details.url,
		spaceId: details.spaceId || '',
		title: details.title || '',
		description: details.description || '',
		hostname: details.hostname || '',
		imageUrl: details.imageUrl || '',
		imageId: 0,
		isFrameable,
		isPrivate: !! details.isPrivate,
	};

	if ( ! isFrameable ) {
		next.clickAction = 'link';
		if (
			attributes.display === 'inline' ||
			attributes.display === 'fullpage'
		) {
			next.display = 'card';
		}
	}

	return next;
}
