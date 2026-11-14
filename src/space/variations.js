import { __ } from '@wordpress/i18n';

import icon from './icon';
import modeIcons from './mode-icons';

/** @typedef {import('@wordpress/blocks').WPBlockVariation} WPBlockVariation */

/**
 * One variation per display mode, so each mode can be inserted directly. The
 * inline one is the block itself and keeps the Spacefast mark; the others get
 * their mode's icon so they can be told apart in the inserter. The inline variation stands in for the block itself.
 *
 * @type {WPBlockVariation[]}
 */
const variations = [
	{
		name: 'inline',
		title: __( 'Spacefast', 'spacefast-block' ),
		description: __(
			'Show a Spacefast Space inline, inside the post.',
			'spacefast-block'
		),
		icon,
		attributes: { display: 'inline' },
		isDefault: true,
	},
	{
		name: 'fullpage',
		title: __( 'Spacefast full page', 'spacefast-block' ),
		description: __(
			'Show a Spacefast Space as the page itself, between your header and footer.',
			'spacefast-block'
		),
		icon: modeIcons.fullpage,
		attributes: { display: 'fullpage' },
	},
	{
		name: 'card',
		title: __( 'Spacefast card', 'spacefast-block' ),
		description: __(
			'Show a Spacefast Space as a card with its image, title, and description.',
			'spacefast-block'
		),
		icon: modeIcons.card,
		attributes: { display: 'card' },
	},
	{
		name: 'link',
		title: __( 'Spacefast link', 'spacefast-block' ),
		description: __(
			'Show a Spacefast Space as a button that opens it.',
			'spacefast-block'
		),
		icon: modeIcons.link,
		attributes: { display: 'link' },
	},
].map( ( variation ) => ( {
	...variation,
	// Inserter only: the Display setting switches modes, so a second chooser
	// in the inspector would repeat it.
	scope: [ 'inserter' ],
	isActive: [ 'display' ],
} ) );

export default variations;
