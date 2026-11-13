/**
 * Decisions for the full-page takeover, kept apart from the Interactivity store
 * so they can be tested without a browser.
 */

/**
 * Where to look for the site header, most specific first. The block theme's
 * template part wins; the rest cover classic themes. style.scss uses the same
 * lists, so keep them in step.
 */
export const HEADER_SELECTORS = [
	'header.wp-block-template-part',
	'.wp-site-blocks > header',
	'#masthead',
	'.site-header',
	'body > header',
];

/**
 * Where to look for the site footer, most specific first.
 */
export const FOOTER_SELECTORS = [
	'footer.wp-block-template-part',
	'.wp-site-blocks > footer',
	'#colophon',
	'.site-footer',
	'body > footer',
];

/**
 * The largest share of the viewport the footer may take. A taller footer
 * scrolls inside that space, so the Space keeps most of the screen.
 */
export const FOOTER_MAX_SHARE = 0.4;

/**
 * The first visible element, taking the candidate lists in order.
 *
 * @param {Array<Array<Element>>}     candidateLists Matches for each selector, in priority order.
 * @param {function(Element):boolean} isVisible      Whether an element is shown.
 * @return {?Element} The element, or null.
 */
export function pickFirstVisible( candidateLists, isVisible ) {
	for ( const candidates of candidateLists ) {
		const match = candidates.find( isVisible );
		if ( match ) {
			return match;
		}
	}
	return null;
}

/**
 * The height the footer takes at the bottom of the viewport.
 *
 * @param {number} naturalHeight  The footer's height.
 * @param {number} viewportHeight The viewport's height.
 * @return {number} Height in pixels.
 */
export function footerRegionHeight( naturalHeight, viewportHeight ) {
	return Math.min( naturalHeight, viewportHeight * FOOTER_MAX_SHARE );
}

/**
 * The distances from the top and bottom of the viewport to the Space.
 *
 * @param {Object}  measures                  Measured heights and the block's options.
 * @param {number}  [measures.adminBarHeight] The admin bar, when shown.
 * @param {number}  [measures.headerHeight]   The site header; 0 or missing for none.
 * @param {number}  [measures.footerHeight]   The site footer; 0 or missing for none.
 * @param {number}  measures.viewportHeight   The viewport.
 * @param {boolean} measures.hideHeader       Whether the block hides the header.
 * @param {boolean} measures.hideFooter       Whether the block hides the footer.
 * @return {{top: number, bottom: number}} Offsets in pixels.
 */
export function computeOffsets( {
	adminBarHeight = 0,
	headerHeight = 0,
	footerHeight = 0,
	viewportHeight,
	hideHeader,
	hideFooter,
} ) {
	return {
		top: adminBarHeight + ( hideHeader ? 0 : headerHeight ),
		bottom: hideFooter
			? 0
			: footerRegionHeight( footerHeight, viewportHeight ),
	};
}
