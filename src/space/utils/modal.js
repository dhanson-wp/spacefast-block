/**
 * Decisions for the modal, kept apart from the Interactivity store so they can
 * be tested without a browser.
 */

/**
 * Whether the browser can show a native modal dialog.
 *
 * @param {Object} win A window, or something shaped like one.
 * @return {boolean} True if `<dialog>` and `showModal()` exist.
 */
export function supportsModal( win ) {
	return (
		typeof win?.HTMLDialogElement !== 'undefined' &&
		typeof win.HTMLDialogElement.prototype?.showModal === 'function'
	);
}

/**
 * Whether a click on the trigger should open the modal. Modified and
 * non-primary clicks (open in a new tab, download, and so on) belong to the
 * browser, and so does every click when dialogs are not supported.
 *
 * @param {Object}  event                  The click event.
 * @param {number}  event.button           Mouse button; 0 is primary.
 * @param {boolean} event.metaKey          Whether Meta was held.
 * @param {boolean} event.ctrlKey          Whether Ctrl was held.
 * @param {boolean} event.shiftKey         Whether Shift was held.
 * @param {boolean} event.altKey           Whether Alt was held.
 * @param {boolean} event.defaultPrevented Whether something already handled it.
 * @param {boolean} canShowModal           Result of supportsModal().
 * @param {boolean} hasFallbackLink        Whether the trigger is a link the browser can follow. A button has none, so every click opens the modal.
 * @return {boolean} True to open the modal and cancel navigation.
 */
export function shouldOpenModal(
	{ button, metaKey, ctrlKey, shiftKey, altKey, defaultPrevented },
	canShowModal,
	hasFallbackLink = true
) {
	if ( ! canShowModal || defaultPrevented ) {
		return false;
	}
	if ( ! hasFallbackLink ) {
		return true;
	}
	return button === 0 && ! ( metaKey || ctrlKey || shiftKey || altKey );
}

/**
 * Whether a click landed on the dialog's backdrop. The dialog has no padding, so
 * a click that targets the dialog element itself is outside its contents.
 *
 * @param {?EventTarget} target Click target.
 * @param {?Element}     dialog The dialog element.
 * @return {boolean} True for a backdrop click.
 */
export function isBackdropClick( target, dialog ) {
	return !! dialog && target === dialog;
}

/**
 * Whether a click should close the dialog as a backdrop click. The press must
 * have started on the backdrop too, so selecting text or dragging from inside
 * the dialog and letting go over the backdrop leaves it open.
 *
 * @param {?EventTarget} pressTarget Target of the pointerdown that began the click.
 * @param {?EventTarget} clickTarget Click target.
 * @param {?Element}     dialog      The dialog element.
 * @return {boolean} True to close the dialog.
 */
export function isBackdropDismiss( pressTarget, clickTarget, dialog ) {
	return (
		isBackdropClick( pressTarget, dialog ) &&
		isBackdropClick( clickTarget, dialog )
	);
}
