import * as interactivity from '@wordpress/interactivity';
import {
	isBackdropDismiss,
	shouldOpenModal,
	supportsModal,
} from './utils/modal';
import {
	FOOTER_SELECTORS,
	HEADER_SELECTORS,
	computeLayout,
	pickFirstVisible,
} from './utils/takeover';
import {
	acceptScroll,
	clampHeight,
	navigationTarget,
	readMessage,
	remainingWait,
	shouldFallBack,
} from './utils/relay';

const { store, getContext, getElement } = interactivity;

// `withSyncEvent` arrived in WordPress 6.8, where `preventDefault()` needs it.
// Earlier versions run handlers synchronously, so the handler works as it is.
// It is looked up by a variable name so the bundle doesn't import it by name,
// which would fail to load on WordPress 6.7.
const SYNC_EVENT_EXPORT = 'withSyncEvent';
const withSyncEvent =
	interactivity[ SYNC_EVENT_EXPORT ] ?? ( ( handler ) => handler );

const SCROLL_LOCK_CLASS = 'spacefast-modal-open';
const TAKEOVER_SELECTOR = '.wp-block-spacefast-space.is-fullpage-takeover';
const TAKEOVER_BODY_CLASSES = [
	'has-spacefast-fullpage',
	'spacefast-hide-header',
	'spacefast-hide-footer',
];
const REGION_ATTRIBUTE = 'data-spacefast-region';

// Set on <html> once the header and footer are measured.
const READY_CLASS = 'spacefast-takeover-ready';
const WRAPPER_SELECTOR = '.wp-block-spacefast-space';
const DIALOG_SELECTOR = 'dialog.wp-block-spacefast-space__modal';

// The element that opened each dialog, so focus can go back to it on close.
const triggers = new WeakMap();

// Where the last press inside each dialog began, to tell a backdrop click from
// a drag that ends on the backdrop.
const presses = new WeakMap();

/**
 * The block wrapper's dialog for an element inside the block.
 *
 * @param {Element} element An element inside the block.
 * @return {?HTMLDialogElement} The dialog.
 */
function getDialog( element ) {
	return element
		?.closest( WRAPPER_SELECTOR )
		?.querySelector( DIALOG_SELECTOR );
}

// The server adds the takeover body classes when the post has a full-page
// block. If that block didn't render (filtered out, or a theme that shows the
// post some other way), the styles already leave the page alone; drop the
// classes too so nothing else keys off them.
if (
	document.body?.classList.contains( TAKEOVER_BODY_CLASSES[ 0 ] ) &&
	! document.querySelector( TAKEOVER_SELECTOR )
) {
	document.body.classList.remove( ...TAKEOVER_BODY_CLASSES );
}

store( 'spacefast/space', {
	actions: {
		/**
		 * Open the modal from the card or link, or the inline "Enlarge" button.
		 * Modified clicks and browsers without <dialog> fall through to a
		 * link's normal navigation; a button has none, so it opens on any click.
		 */
		open: withSyncEvent( ( event ) => {
			const { ref } = getElement();
			const isLink = ref.tagName === 'A';
			if ( ! shouldOpenModal( event, supportsModal( window ), isLink ) ) {
				return;
			}
			const dialog = getDialog( ref );
			if ( ! dialog ) {
				return;
			}
			event.preventDefault();

			const context = getContext();
			const frame = dialog.querySelector( 'iframe' );
			if ( frame && ! frame.getAttribute( 'src' ) ) {
				frame.setAttribute( 'src', context.frameUrl );
			}

			triggers.set( dialog, ref );
			if ( ! dialog.open ) {
				dialog.showModal();
			}
			document.documentElement.classList.add( SCROLL_LOCK_CLASS );
		} ),

		close() {
			getDialog( getElement().ref )?.close();
		},

		/**
		 * Remember where a press in the dialog began.
		 *
		 * @param {PointerEvent} event The pointerdown event.
		 */
		notePress( event ) {
			presses.set( getElement().ref, event.target );
		},

		/**
		 * A click on the dialog element itself is a click on the backdrop. It
		 * only closes the dialog when the press began on the backdrop too.
		 *
		 * @param {Event} event The click event.
		 */
		closeOnBackdrop: withSyncEvent( ( event ) => {
			const { ref } = getElement();
			const pressTarget = presses.get( ref ) ?? null;
			presses.delete( ref );
			if ( isBackdropDismiss( pressTarget, event.target, ref ) ) {
				ref.close();
			}
		} ),
	},

	callbacks: {
		/**
		 * The dialog closed, by Escape, the close button, or the backdrop. The
		 * frame stays loaded so reopening is instant.
		 */
		onDialogClose() {
			const { ref } = getElement();

			// Only unlock the page when no other block's dialog is still open.
			if ( ! document.querySelector( `${ DIALOG_SELECTOR }[open]` ) ) {
				document.documentElement.classList.remove( SCROLL_LOCK_CLASS );
			}

			triggers.get( ref )?.focus();
		},

		/**
		 * The inline "Enlarge" button is printed hidden. Show it once the
		 * browser can open a dialog; without JavaScript, or without <dialog>,
		 * it stays hidden rather than sit there doing nothing.
		 */
		initEnlarge() {
			if ( supportsModal( window ) ) {
				getElement().ref.hidden = false;
			}
		},

		/**
		 * The full-page block: keep it between the site header and footer. The
		 * body classes added in PHP already lay the page out; this measures the
		 * header, footer, and admin bar and writes the exact offsets. When the
		 * frame loads the relay, it also sizes the Space to the relayed page.
		 *
		 * @return {() => void} Cleanup.
		 */
		initTakeover() {
			const stopTakeover = startTakeover( document, window );
			const { fit } = getContext();
			const stopRelay = fit
				? startRelay(
						getElement().ref,
						{
							spaceUrl: fit.spaceUrl,
							directUrl: fit.directUrl,
							relayBase: fit.relayBase,
							signature: fit.signature,
							sandbox: fit.sandbox,
							allowFullscreen: fit.allowFullscreen,
						},
						document,
						window
					)
				: null;
			return () => {
				stopRelay?.();
				stopTakeover();
			};
		},
	},
} );

/**
 * Whether an element is laid out.
 *
 * @param {Element} element The element.
 * @return {boolean} True when it takes up space.
 */
function isLaidOut( element ) {
	return element.getClientRects().length > 0;
}

/**
 * Whether an element is the containing block of absolutely positioned
 * descendants: it is positioned, or a transform, filter, perspective, or
 * containment makes it one.
 *
 * @param {CSSStyleDeclaration} style The element's computed style.
 * @return {boolean} True when it contains them.
 */
function isContainingBlock( style ) {
	return (
		style.position !== 'static' ||
		( !! style.transform && style.transform !== 'none' ) ||
		( !! style.filter && style.filter !== 'none' ) ||
		( !! style.perspective && style.perspective !== 'none' ) ||
		/\b(layout|paint|strict|content)\b/.test( style.contain || '' )
	);
}

/**
 * The first visible element for a list of selectors, in priority order.
 *
 * @param {Document} doc       The document.
 * @param {string[]} selectors Selectors, most specific first.
 * @return {?Element} The element.
 */
function findRegion( doc, selectors ) {
	return pickFirstVisible(
		selectors.map( ( selector ) =>
			Array.from( doc.querySelectorAll( selector ) )
		),
		isLaidOut
	);
}

/**
 * Measure the header, footer, admin bar, and where <html>'s content starts,
 * and write `--spacefast-admin-bar`, `--spacefast-offset`,
 * `--spacefast-header`, and `--spacefast-footer` on <html>, now and whenever
 * any of them or the window changes size.
 *
 * @param {Document} doc The document.
 * @param {Window}   win The window.
 * @return {() => void} Stops watching.
 */
function startTakeover( doc, win ) {
	const root = doc.documentElement;
	const body = doc.body;
	const hideHeader = body.classList.contains( 'spacefast-hide-header' );
	const hideFooter = body.classList.contains( 'spacefast-hide-footer' );
	const header = hideHeader ? null : findRegion( doc, HEADER_SELECTORS );
	const footer = hideFooter ? null : findRegion( doc, FOOTER_SELECTORS );
	const adminBar = doc.getElementById( 'wpadminbar' );

	// Pin exactly the header and footer that are measured.
	header?.setAttribute( REGION_ATTRIBUTE, 'header' );
	footer?.setAttribute( REGION_ATTRIBUTE, 'footer' );

	const height = ( element ) =>
		element && isLaidOut( element )
			? element.getBoundingClientRect().height
			: 0;

	let frame = 0;
	const update = () => {
		frame = 0;
		const rootStyle = win.getComputedStyle( root );
		const layout = computeLayout( {
			adminBarHeight: height( adminBar ),
			headerHeight: height( header ),
			footerHeight: height( footer ),
			rootTop: root.getBoundingClientRect().top + win.scrollY,
			rootBorderTop: parseFloat( rootStyle.borderTopWidth ) || 0,
			rootPaddingTop: parseFloat( rootStyle.paddingTop ) || 0,
			rootIsPositioned: isContainingBlock( rootStyle ),
			hideHeader,
			hideFooter,
		} );
		root.style.setProperty(
			'--spacefast-admin-bar',
			`${ layout.adminBar }px`
		);
		root.style.setProperty( '--spacefast-offset', `${ layout.offset }px` );
		root.style.setProperty( '--spacefast-header', `${ layout.header }px` );
		root.style.setProperty( '--spacefast-footer', `${ layout.footer }px` );
		// Switches the styles from the pinned fallback to the measured layout.
		root.classList.add( READY_CLASS );
	};
	const schedule = () => {
		if ( ! frame ) {
			frame = win.requestAnimationFrame( update );
		}
	};

	update();
	win.addEventListener( 'resize', schedule );
	doc.fonts?.ready.then( schedule );

	let observer = null;
	if ( typeof win.ResizeObserver === 'function' ) {
		observer = new win.ResizeObserver( schedule );
		[ header, footer, adminBar ].forEach(
			( element ) => element && observer.observe( element )
		);
	}

	return () => {
		win.removeEventListener( 'resize', schedule );
		observer?.disconnect();
		header?.removeAttribute( REGION_ATTRIBUTE );
		footer?.removeAttribute( REGION_ATTRIBUTE );
		root.classList.remove( READY_CLASS );
		if ( frame ) {
			win.cancelAnimationFrame( frame );
		}
	};
}

/**
 * Show the Space at its full length: the frame loads the relay's copy of the
 * page, which reports its height and links by postMessage. The height goes to
 * `--spacefast-space-height` on <html>, which the measured layout uses for the
 * Space and the footer below it. If the relayed page fails before it is ready,
 * reports no height in time, or keeps growing, the frame loads the Space
 * directly instead, once, and the layout goes back to one screen.
 *
 * @param {Element}  wrapper             The block wrapper.
 * @param {Object}   fit                 From the block context.
 * @param {string}   fit.spaceUrl        The Space's URL.
 * @param {string}   fit.directUrl       The Space page the frame shows.
 * @param {string}   fit.relayBase       The REST namespace URL.
 * @param {string}   fit.signature       The Space host's relay signature, from the server.
 * @param {string}   fit.sandbox         The sandbox for framing the Space directly.
 * @param {boolean}  fit.allowFullscreen Whether the direct frame allows fullscreen.
 * @param {Document} doc                 The document.
 * @param {Window}   win                 The window.
 * @return {?(() => void)} Stops listening, or null when there is no frame.
 */
function startRelay( wrapper, fit, doc, win ) {
	const frame = wrapper.querySelector( '.wp-block-spacefast-space__frame' );
	if ( ! frame ) {
		return null;
	}
	const root = doc.documentElement;
	const adminBar = doc.getElementById( 'wpadminbar' );
	const state = { ready: false, hasHeight: false, fellBack: false };
	let directUrl = fit.directUrl;
	let reported = 0;
	let timer = 0;

	const adminBarHeight = () =>
		adminBar && isLaidOut( adminBar )
			? adminBar.getBoundingClientRect().height
			: 0;

	// The header's height, as startTakeover() measured it.
	const headerHeight = () =>
		parseFloat( root.style.getPropertyValue( '--spacefast-header' ) ) || 0;

	// At least the screen under the header. The frame's offset covers a title
	// bar above it, if the block shows one. Once the frame is as tall as its
	// page, the page is told to stop scrolling inside; until then, and
	// without this script, it scrolls inside as usual.
	const applyHeight = () => {
		if ( ! reported || state.fellBack ) {
			return;
		}
		const height =
			clampHeight(
				reported,
				win.innerHeight - adminBarHeight() - headerHeight()
			) + frame.offsetTop;
		root.style.setProperty( '--spacefast-space-height', `${ height }px` );
		// Every time: after a link, the first height can still come from the
		// page being left, and the new page needs telling too.
		frame.contentWindow?.postMessage( { spacefastBlock: 'sized' }, '*' );
	};

	const resetHeight = () => {
		reported = 0;
		root.style.removeProperty( '--spacefast-space-height' );
	};

	// Scroll the page to a point in the Space, measured from its top. With
	// `onlyIfAbove`, only when that point has scrolled up out of view.
	const scrollToSpace = ( offset, onlyIfAbove = false ) => {
		const top =
			wrapper.getBoundingClientRect().top + offset - adminBarHeight();
		if ( ! onlyIfAbove || top < 0 ) {
			win.scrollTo( 0, win.scrollY + top );
		}
	};

	// The wait for a first height counts from when the frame started loading,
	// which for the first page is before this script ran.
	const startTimer = ( startedAt ) => {
		win.clearTimeout( timer );
		timer = win.setTimeout(
			() => handle( { type: 'timeout' } ),
			remainingWait( win.performance.now(), startedAt )
		);
	};
	const frameStart = () =>
		win.performance
			.getEntriesByType?.( 'resource' )
			.find(
				( entry ) =>
					entry.initiatorType === 'iframe' && entry.name === frame.src
			)?.startTime;

	const onMessage = ( event ) => {
		const message = readMessage( event, frame.contentWindow );
		if ( message ) {
			handle( message );
		}
	};

	const stop = () => {
		win.clearTimeout( timer );
		win.removeEventListener( 'message', onMessage );
		win.removeEventListener( 'resize', applyHeight );
	};

	const fallBack = () => {
		state.fellBack = true;
		stop();
		resetHeight();
		wrapper.classList.remove( 'is-fit-content' );
		wrapper.classList.add( 'is-fit-fallback' );
		frame.removeAttribute( 'scrolling' );
		frame.setAttribute( 'sandbox', fit.sandbox );
		if ( fit.allowFullscreen ) {
			frame.setAttribute( 'allowfullscreen', '' );
		}
		frame.setAttribute( 'src', directUrl );
	};

	function handle( message ) {
		if ( shouldFallBack( message, state ) ) {
			fallBack();
			return;
		}
		if ( message.type === 'height' ) {
			state.hasHeight = true;
			reported = message.height;
			applyHeight();
		} else if ( message.type === 'ready' ) {
			state.ready = true;
		} else if ( message.type === 'scroll' ) {
			// Only for a click in the Space, and never past its end.
			const top = acceptScroll( message.top, {
				isActive: win.navigator.userActivation?.isActive,
				maxTop: reported,
			} );
			if ( top !== null ) {
				scrollToSpace( frame.offsetTop + top );
			}
		} else if ( message.type === 'navigate' ) {
			const target = navigationTarget(
				fit.spaceUrl,
				message.path,
				fit.relayBase,
				fit.signature
			);
			if ( ! target ) {
				return;
			}
			directUrl = target.direct;
			scrollToSpace( 0, true );
			if ( ! target.relay ) {
				// The relay never carries a query: show that page directly.
				fallBack();
				return;
			}
			state.ready = false;
			state.hasHeight = false;
			resetHeight();
			frame.setAttribute( 'src', target.relay );
			startTimer();
		}
	}

	win.addEventListener( 'message', onMessage );
	win.addEventListener( 'resize', applyHeight );
	startTimer( frameStart() );
	// The page may have reported before this script ran; ask again.
	frame.contentWindow?.postMessage( { spacefastBlock: 'measure' }, '*' );

	return () => {
		stop();
		if ( ! state.fellBack ) {
			resetHeight();
		}
	};
}
