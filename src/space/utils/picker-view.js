/**
 * Which view the Space picker shows.
 *
 * Until the first search answers, the connection state is unknown. A failure
 * in that window has to surface as an error, not an endless spinner. Once
 * connected, later search failures are shown inside the search view.
 *
 * @param {Object}       state             Picker state.
 * @param {boolean|null} state.isConnected Whether the account is connected; null until known.
 * @param {boolean}      state.isLoading   Whether a search is in flight.
 * @param {string}       state.error       Message from the last failed request, or ''.
 * @return {'loading'|'error'|'connect'|'search'} View to render.
 */
export function getPickerView( { isConnected, isLoading, error } ) {
	if ( isConnected === false ) {
		return 'connect';
	}
	if ( isConnected === true ) {
		return 'search';
	}
	return error && ! isLoading ? 'error' : 'loading';
}

/**
 * Whether the placeholder should say "Connect to Spacefast" instead of
 * "Choose a Space". Only once the account is known to be missing, so the
 * button doesn't change wording while the state is still loading.
 *
 * @param {boolean|null|undefined} isConnected Whether the account is connected.
 * @return {boolean} True when connecting is the next step.
 */
export function shouldOfferConnect( isConnected ) {
	return isConnected === false;
}
