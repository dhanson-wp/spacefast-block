import {
	alignNone,
	button,
	fullscreen,
	postFeaturedImage,
} from '@wordpress/icons';

/**
 * One icon per display mode, as core's Group block has one per layout. The
 * Display setting and the inserter's variations share them.
 */
const modeIcons = {
	inline: alignNone,
	fullpage: fullscreen,
	card: postFeaturedImage,
	link: button,
};

export default modeIcons;
