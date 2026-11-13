import * as interactivity from '@wordpress/interactivity';
import { isBackdropClick, shouldOpenModal, supportsModal } from './utils/modal';

const { store, getContext, getElement } = interactivity;

// `withSyncEvent` arrived in WordPress 6.8, where `preventDefault()` needs it.
// Earlier versions run handlers synchronously, so the handler works as it is.
// It is looked up by a variable name so the bundle doesn't import it by name,
// which would fail to load on WordPress 6.6 and 6.7.
const SYNC_EVENT_EXPORT = 'withSyncEvent';
const withSyncEvent =
	interactivity[ SYNC_EVENT_EXPORT ] ?? ( ( handler ) => handler );

const SCROLL_LOCK_CLASS = 'spacefast-modal-open';
const WRAPPER_SELECTOR = '.wp-block-spacefast-space';
const DIALOG_SELECTOR = 'dialog.wp-block-spacefast-space__modal';

// The element that opened each dialog, so focus can go back to it on close.
const triggers = new WeakMap();

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
			context.isOpen = true;
		} ),

		close() {
			getDialog( getElement().ref )?.close();
		},

		/**
		 * A click on the dialog element itself is a click on the backdrop.
		 *
		 * @param {Event} event The click event.
		 */
		closeOnBackdrop( event ) {
			const { ref } = getElement();
			if ( isBackdropClick( event.target, ref ) ) {
				ref.close();
			}
		},
	},

	callbacks: {
		/**
		 * The dialog closed, by Escape, the close button, or the backdrop. The
		 * frame stays loaded so reopening is instant.
		 */
		onDialogClose() {
			const { ref } = getElement();
			getContext().isOpen = false;

			// Only unlock the page when no other block's dialog is still open.
			if ( ! document.querySelector( `${ DIALOG_SELECTOR }[open]` ) ) {
				document.documentElement.classList.remove( SCROLL_LOCK_CLASS );
			}

			triggers.get( ref )?.focus();
		},
	},
} );
