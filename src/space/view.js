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
		 * Open the modal from the card or link. Modified clicks and browsers
		 * without <dialog> fall through to the link's normal navigation.
		 */
		open: withSyncEvent( ( event ) => {
			if ( ! shouldOpenModal( event, supportsModal( window ) ) ) {
				return;
			}
			const { ref } = getElement();
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
		 * The full-page block: keep it between the site header and footer. The
		 * body classes added in PHP already lay the page out; this measures the
		 * header, footer, and admin bar and writes the exact offsets.
		 *
		 * @return {() => void} Cleanup.
		 */
		initTakeover() {
			return startTakeover( document, window );
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
 * Measure the header, footer, and admin bar and write `--spacefast-admin-bar`,
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
		const layout = computeLayout( {
			adminBarHeight: height( adminBar ),
			headerHeight: height( header ),
			footerHeight: height( footer ),
			hideHeader,
			hideFooter,
		} );
		root.style.setProperty(
			'--spacefast-admin-bar',
			`${ layout.adminBar }px`
		);
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
