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
 * The heights the full-page layout needs. The Space fills the screen under the
 * header; the footer sits below the Space at its natural height, reached by
 * scrolling the page, so it is never capped.
 *
 * @param {Object}  measures                  Measured heights and the block's options.
 * @param {number}  [measures.adminBarHeight] The admin bar, when shown.
 * @param {number}  [measures.headerHeight]   The site header; 0 or missing for none.
 * @param {number}  [measures.footerHeight]   The site footer; 0 or missing for none.
 * @param {boolean} measures.hideHeader       Whether the block hides the header.
 * @param {boolean} measures.hideFooter       Whether the block hides the footer.
 * @return {{adminBar: number, header: number, footer: number}} Heights in pixels.
 */
export function computeLayout( {
	adminBarHeight = 0,
	headerHeight = 0,
	footerHeight = 0,
	hideHeader,
	hideFooter,
} ) {
	return {
		adminBar: adminBarHeight,
		header: hideHeader ? 0 : headerHeight,
		footer: hideFooter ? 0 : footerHeight,
	};
}
