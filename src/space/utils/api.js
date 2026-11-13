import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

const NAMESPACE = '/spacefast-block/v1';

/**
 * The message to show for a failed request. The server's messages are already
 * translated and written for editors, so they are shown as they come.
 *
 * @param {Object|undefined} error Value the request rejected with.
 * @return {string} Message for the editor.
 */
export function getErrorMessage( error ) {
	if ( error && typeof error.message === 'string' && error.message ) {
		return error.message;
	}
	return __( 'Something went wrong. Please try again.', 'spacefast-block' );
}

/**
 * Fetches a link once and reports what it is.
 *
 * @param {string} url     Link to check.
 * @param {string} spaceId Space ID, when it came from the account search.
 * @return {Promise<Object>} Space details.
 */
export function resolveSpace( url, spaceId = '' ) {
	const query = new URLSearchParams( { url } );
	if ( spaceId ) {
		query.set( 'spaceId', spaceId );
	}
	return apiFetch( { path: `${ NAMESPACE }/resolve?${ query }` } );
}

/**
 * Lists the connected account's Spaces.
 *
 * @param {string} search Optional search text.
 * @return {Promise<{connected: boolean, spaces: Object[]}>} Search results.
 */
export function searchSpaces( search = '' ) {
	const query = new URLSearchParams( { search } );
	return apiFetch( { path: `${ NAMESPACE }/spaces?${ query }` } );
}

/**
 * Starts the device sign-in.
 *
 * @return {Promise<Object>} The user code, verification URL, interval, and expiry.
 */
export function startConnection() {
	return apiFetch( { path: `${ NAMESPACE }/connection`, method: 'POST' } );
}

/**
 * Asks the server how the device sign-in is going.
 *
 * @return {Promise<{status: string}>} One of pending, connected, denied, expired.
 */
export function pollConnection() {
	return apiFetch( {
		path: `${ NAMESPACE }/connection/poll`,
		method: 'POST',
	} );
}

/**
 * Forgets the stored Spacefast key for the current user.
 *
 * @return {Promise<Object>} The new connection state.
 */
export function disconnect() {
	return apiFetch( { path: `${ NAMESPACE }/connection`, method: 'DELETE' } );
}
