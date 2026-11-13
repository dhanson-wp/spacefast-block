import { __ } from '@wordpress/i18n';

import icon from './icon';

/** @typedef {import('@wordpress/blocks').WPBlockVariation} WPBlockVariation */

/**
 * One variation per display mode, so each mode can be inserted or switched to
 * directly. The inline variation stands in for the block itself.
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
			'Show a Spacefast Space at full screen below your header, with your footer after it.',
			'spacefast-block'
		),
		icon,
		attributes: { display: 'fullpage' },
	},
	{
		name: 'card',
		title: __( 'Spacefast card', 'spacefast-block' ),
		description: __(
			'Show a Spacefast Space as a card with its image, title, and description.',
			'spacefast-block'
		),
		icon,
		attributes: { display: 'card' },
	},
	{
		name: 'link',
		title: __( 'Spacefast link', 'spacefast-block' ),
		description: __(
			'Show a Spacefast Space as a button that opens it.',
			'spacefast-block'
		),
		icon,
		attributes: { display: 'link' },
	},
].map( ( variation ) => ( {
	...variation,
	scope: [ 'inserter', 'transform' ],
	isActive: [ 'display' ],
} ) );

export default variations;
