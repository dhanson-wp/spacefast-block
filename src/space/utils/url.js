/**
 * Whether a string looks like a link worth sending to the server: an optional
 * https:// prefix, a dotted host, and an optional path. The server makes the
 * real decision; this only keeps obvious non-links from triggering a request.
 *
 * @param {string} value Text typed or pasted by the editor.
 * @return {boolean} True if the text could be a link.
 */
export function looksLikeUrl( value ) {
	return /^(https:\/\/)?[^\s/:?#]+\.[^\s/:?#]+([/?#]\S*)?$/i.test(
		String( value ).trim()
	);
}
