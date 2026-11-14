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
 * The heights and offset the full-page layout needs. The Space fills the
 * screen under the header; the footer sits below the Space at its natural
 * height, reached by scrolling the page, so it is never capped.
 *
 * The header, Space, and footer are absolutely positioned. `offset` is how far
 * below the top of their containing block the header starts: where <html>'s
 * content begins (under the margin WordPress gives it for the admin bar),
 * measured from the document's top, or from <html>'s padding box when a theme
 * makes <html> itself the containing block. It is measured, not taken from
 * the admin bar's height, which can differ from that margin.
 *
 * @param {Object}  measures                    Measured sizes and the block's options.
 * @param {number}  [measures.adminBarHeight]   The admin bar, when shown.
 * @param {number}  [measures.headerHeight]     The site header; 0 or missing for none.
 * @param {number}  [measures.footerHeight]     The site footer; 0 or missing for none.
 * @param {number}  [measures.rootTop]          <html>'s border box top, from the document's top.
 * @param {number}  [measures.rootBorderTop]    <html>'s top border width.
 * @param {number}  [measures.rootPaddingTop]   <html>'s top padding.
 * @param {boolean} [measures.rootIsPositioned] Whether <html> is the containing block.
 * @param {boolean} measures.hideHeader         Whether the block hides the header.
 * @param {boolean} measures.hideFooter         Whether the block hides the footer.
 * @return {{adminBar: number, offset: number, header: number, footer: number}} Sizes in pixels.
 */
export function computeLayout( {
	adminBarHeight = 0,
	headerHeight = 0,
	footerHeight = 0,
	rootTop = 0,
	rootBorderTop = 0,
	rootPaddingTop = 0,
	rootIsPositioned = false,
	hideHeader,
	hideFooter,
} ) {
	const containingBlockTop = rootIsPositioned ? rootTop + rootBorderTop : 0;
	const contentTop = rootTop + rootBorderTop + rootPaddingTop;
	return {
		adminBar: adminBarHeight,
		offset: Math.max( 0, contentTop - containingBlockTop ),
		header: hideHeader ? 0 : headerHeight,
		footer: hideFooter ? 0 : footerHeight,
	};
}
