/**
 * Decisions for showing a full-page Space at its full length through the
 * site's relay, kept apart from the Interactivity store so they can be tested
 * without a browser. `relayUrl()` and `pageRelayUrl()` mirror
 * `Space_Relay::relay_url()` and `Space_Relay::page_relay_url()`; both are
 * pinned by tests/fixtures/relay-urls.json.
 */

import { frameUrl, isDnsName } from './url';

/**
 * The tallest height the frame is given, in pixels. A page that reaches it is
 * most likely growing with its own frame and is shown directly instead.
 */
export const MAX_HEIGHT = 100000;

/**
 * How long the relayed page has to report its height, in milliseconds.
 */
export const HEIGHT_TIMEOUT = 6000;

const MESSAGE_TYPES = [ 'height', 'error', 'ready', 'navigate', 'scroll' ];
const ERROR_KINDS = [ 'resource', 'script' ];

/**
 * Splits an https URL on a DNS host into host, path, and whether it has a query.
 *
 * @param {string} url URL.
 * @return {?{host: string, path: string, hasQuery: boolean}} Parts, or null.
 */
function split( url ) {
	const match = /^https:\/\/([^/?#]+)(?:\/([^?#]*))?(\?[^#]*)?/i.exec(
		String( url )
	);
	if ( ! match ) {
		return null;
	}
	const host = match[ 1 ].toLowerCase();
	if ( ! isDnsName( host ) ) {
		return null;
	}
	return { host, path: match[ 2 ] ?? '', hasQuery: !! match[ 3 ] };
}

/**
 * The relay URL for a Space URL. The host is signed by the server, so only
 * Spaces the site put in a page can be relayed. The relay never carries a
 * query: a page with one isn't relayed, and an asset's is dropped. Mirrors
 * `Space_Relay::relay_url()`.
 *
 * @param {string} relayBase The REST namespace URL, ending in a slash.
 * @param {string} kind      `page` or `asset`.
 * @param {string} url       Absolute Space URL.
 * @param {string} signature The host's signature, from the server.
 * @return {string} Relay URL, or '' when it can't be relayed.
 */
export function relayUrl( relayBase, kind, url, signature ) {
	if (
		( kind !== 'page' && kind !== 'asset' ) ||
		! /^[0-9a-f]{16}$/.test( String( signature ) )
	) {
		return '';
	}
	const parts = split( url );
	if ( ! parts || ( kind === 'page' && parts.hasQuery ) ) {
		return '';
	}
	const base = `https://${ parts.host }/`;
	if ( frameUrl( base, parts.path ) !== base + parts.path ) {
		return '';
	}

	let path = parts.path.replace( /\+/g, '%2B' );
	if ( relayBase.includes( '?' ) ) {
		path = path.replace( /&/g, '%26' );
	}
	return `${ relayBase }${ kind }/${ parts.host }~${ signature }/${ path }`;
}

/**
 * The page relay URL for a Space URL and start path. Mirrors
 * `Space_Relay::page_relay_url()`.
 *
 * @param {string} relayBase The REST namespace URL, ending in a slash.
 * @param {string} url       Space URL.
 * @param {string} startPath Start path.
 * @param {string} signature The host's signature, from the server.
 * @return {string} Relay URL, or '' when the Space URL isn't valid or has a query.
 */
export function pageRelayUrl( relayBase, url, startPath, signature ) {
	const direct = frameUrl( url, startPath );
	return direct ? relayUrl( relayBase, 'page', direct, signature ) : '';
}

/**
 * The frame height for a reported page height: at least one screen, at most
 * MAX_HEIGHT, in whole pixels.
 *
 * @param {number} height    Reported height.
 * @param {number} minHeight One screen under the header.
 * @return {number} Height in pixels.
 */
export function clampHeight( height, minHeight ) {
	const min = Math.max( 0, Number( minHeight ) || 0 );
	return Math.min( MAX_HEIGHT, Math.max( min, Math.ceil( height ) ) );
}

/**
 * The relayed page's message, when an event is one: it must come from the
 * block's own frame and be a known type with well-formed fields. The sender's
 * origin is `"null"` for every sandboxed frame, so it is never consulted.
 *
 * @param {MessageEvent} event       The message event.
 * @param {?Window}      frameWindow The frame's `contentWindow`.
 * @return {?Object} `{ type, ...fields }`, or null to ignore the event.
 */
export function readMessage( event, frameWindow ) {
	if ( ! frameWindow || event.source !== frameWindow ) {
		return null;
	}
	const data = event.data;
	if (
		! data ||
		typeof data !== 'object' ||
		! MESSAGE_TYPES.includes( data.spacefastBlock )
	) {
		return null;
	}
	const isLength = ( value ) =>
		typeof value === 'number' && Number.isFinite( value ) && value >= 0;

	switch ( data.spacefastBlock ) {
		case 'height':
			return isLength( data.height )
				? { type: 'height', height: data.height }
				: null;
		case 'scroll':
			return isLength( data.top )
				? { type: 'scroll', top: data.top }
				: null;
		case 'navigate':
			return typeof data.path === 'string'
				? { type: 'navigate', path: data.path }
				: null;
		case 'error':
			return ERROR_KINDS.includes( data.kind )
				? { type: 'error', kind: data.kind }
				: null;
		default:
			return { type: 'ready' };
	}
}

/**
 * Whether to stop relaying and frame the Space directly: an error before the
 * page is ready, a page that grows to MAX_HEIGHT, or no height in time. Only
 * ever once.
 *
 * @param {Object}  message           A message from readMessage(), or `{ type: 'timeout' }`.
 * @param {Object}  state             The frame's state.
 * @param {boolean} [state.ready]     Whether the current page said it is ready.
 * @param {boolean} [state.hasHeight] Whether the current page reported a height.
 * @param {boolean} [state.fellBack]  Whether the block already fell back.
 * @return {boolean} True to fall back.
 */
export function shouldFallBack( message, { ready, hasHeight, fellBack } ) {
	if ( fellBack ) {
		return false;
	}
	switch ( message.type ) {
		case 'error':
			return ! ready;
		case 'height':
			return message.height >= MAX_HEIGHT;
		case 'timeout':
			return ! hasHeight;
		default:
			return false;
	}
}

/**
 * Where a link inside the Space goes: the direct Space URL and its relay URL,
 * or null when the path isn't a safe absolute path on the Space. A page with
 * a query has no relay URL ('') and is loaded directly.
 *
 * @param {string} spaceUrl  The Space's URL.
 * @param {string} path      Path, query, and hash, starting with a slash.
 * @param {string} relayBase The REST namespace URL.
 * @param {string} signature The Space host's signature, from the server.
 * @return {?{direct: string, relay: string}} The URLs, or null.
 */
export function navigationTarget( spaceUrl, path, relayBase, signature ) {
	if ( typeof path !== 'string' || path[ 0 ] !== '/' ) {
		return null;
	}
	const base = frameUrl( spaceUrl, '' );
	const direct = frameUrl( spaceUrl, path );
	// frame_url falls back to the home page for a path it refuses.
	if (
		! direct ||
		( direct === base && path.replace( /#.*$/, '' ) !== '/' )
	) {
		return null;
	}
	return { direct, relay: relayUrl( relayBase, 'page', direct, signature ) };
}

/**
 * Where to scroll the page for a `scroll` message: only while the visitor is
 * interacting (a click in the Space), where the browser can tell, and never
 * past the reported page.
 *
 * @param {number}            top            Offset from the top of the Space.
 * @param {Object}            state          What the page knows.
 * @param {boolean|undefined} state.isActive `navigator.userActivation.isActive`, or undefined without the API.
 * @param {number}            state.maxTop   The last reported height.
 * @return {?number} The offset to scroll to, or null to ignore the message.
 */
export function acceptScroll( top, { isActive, maxTop } ) {
	if ( isActive === false || ! ( maxTop > 0 ) ) {
		return null;
	}
	return Math.min( top, maxTop );
}

/**
 * How much longer to wait for the first height, counting from when the frame
 * started loading.
 *
 * @param {number}  now       Time now, from `performance.now()`.
 * @param {?number} startedAt When the frame started loading, on the same clock.
 * @return {number} Milliseconds.
 */
export function remainingWait( now, startedAt ) {
	if ( typeof startedAt !== 'number' || startedAt > now ) {
		return HEIGHT_TIMEOUT;
	}
	return Math.max( 0, HEIGHT_TIMEOUT - ( now - startedAt ) );
}
