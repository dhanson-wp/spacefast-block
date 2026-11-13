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

// Unreserved, sub-delims, and percent-encoded bytes: what a path segment may hold.
const SEGMENT = /^(?:[A-Za-z0-9\-._~!$&'()*+,;=]|%[0-9A-Fa-f]{2})+$/;
// What a query or fragment may hold.
const TAIL = /^(?:[A-Za-z0-9\-._~!$&'()*+,;=:/?]|%[0-9A-Fa-f]{2})*$/;
const DNS_NAME =
	/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/;
const HEX_LABEL = /^0x[0-9a-f]*$/;
const UNSAFE_URL_CHARACTERS = /[\x00-\x20\x7f\\]/;
const UNSAFE_PATH_CHARACTERS = /[\x00-\x20\x7f\\@]/;

/**
 * Whether a host is a dotted DNS name: not an IP literal, not `localhost`.
 * Mirrors `Space_Url::is_dns_name()`.
 *
 * @param {string} host Lowercased host.
 * @return {boolean} True for a public-looking DNS name.
 */
function isDnsName( host ) {
	if (
		host.length > 253 ||
		! host.includes( '.' ) ||
		! DNS_NAME.test( host )
	) {
		return false;
	}
	const labels = host.split( '.' );
	const tld = labels[ labels.length - 1 ];
	if ( ! /[a-z]/.test( tld ) || HEX_LABEL.test( tld ) ) {
		return false;
	}
	if ( [ 'localhost', 'local', 'internal' ].includes( tld ) ) {
		return false;
	}
	return ! labels.some( ( label ) => HEX_LABEL.test( label ) );
}

/**
 * Reduces a Space URL to `https://host/`, or '' when it isn't a plain Space
 * host. Mirrors `Space_Url::normalize()`.
 *
 * @param {string} input Space URL or bare host.
 * @return {string} Normalized URL, or ''.
 */
function normalizeSpaceUrl( input ) {
	let value = String( input ).trim();
	if ( ! value || UNSAFE_URL_CHARACTERS.test( value ) ) {
		return '';
	}
	if ( ! value.includes( '://' ) ) {
		// A bare host has no colon before its first path, query, or fragment.
		if ( value.split( /[/?#]/ )[ 0 ].includes( ':' ) ) {
			return '';
		}
		value = `https://${ value }`;
	}

	const match = /^([a-z][a-z0-9+.-]*):\/\/([^/?#]*)/i.exec( value );
	if ( ! match || match[ 1 ].toLowerCase() !== 'https' ) {
		return '';
	}
	// Credentials, ports, and IPv6 literals all bring an `@` or a `:`.
	const authority = match[ 2 ];
	if ( /[@:]/.test( authority ) ) {
		return '';
	}
	const host = authority.toLowerCase().replace( /\.+$/, '' );
	return isDnsName( host ) ? `https://${ host }/` : '';
}

/**
 * Whether a path (no query or fragment) is made only of plain segments.
 *
 * @param {string} path Path without a leading slash.
 * @return {boolean} True if every segment is safe.
 */
function isSafePath( path ) {
	if ( path === '' ) {
		return true;
	}
	const segments = path.split( '/' );
	const last = segments.length - 1;
	return segments.every( ( segment, index ) => {
		if ( segment === '' ) {
			// Only a trailing slash may leave an empty segment.
			return index === last && index > 0;
		}
		if ( ! SEGMENT.test( segment ) ) {
			return false;
		}
		const decoded = segment.replace( /%2e/gi, '.' );
		return decoded !== '.' && decoded !== '..';
	} );
}

/**
 * The address the editor preview frames: the Space URL plus the start path,
 * but only when the path is a safe relative path. Mirrors
 * `Space_Url::frame_url()` on the server.
 *
 * @param {string} url       Space URL.
 * @param {string} startPath Relative start path.
 * @return {string} Frame URL, or '' when the Space URL itself isn't valid.
 */
export function frameUrl( url, startPath ) {
	const base = normalizeSpaceUrl( url );
	if ( ! base ) {
		return '';
	}

	let path = String( startPath || '' );
	if ( path === '' || UNSAFE_PATH_CHARACTERS.test( path ) ) {
		return base;
	}
	if ( path[ 0 ] === '/' ) {
		path = path.slice( 1 );
	}

	let fragment = null;
	let query = null;
	const hashAt = path.indexOf( '#' );
	if ( hashAt !== -1 ) {
		fragment = path.slice( hashAt + 1 );
		path = path.slice( 0, hashAt );
	}
	const queryAt = path.indexOf( '?' );
	if ( queryAt !== -1 ) {
		query = path.slice( queryAt + 1 );
		path = path.slice( 0, queryAt );
	}

	if ( ! isSafePath( path ) ) {
		return base;
	}
	if (
		[ query, fragment ].some(
			( tail ) => tail !== null && ! TAIL.test( tail )
		)
	) {
		return base;
	}

	let result = base + path;
	if ( query ) {
		result += `?${ query }`;
	}
	if ( fragment ) {
		result += `#${ fragment }`;
	}
	return result;
}

/**
 * Whether a Space URL is on `space.fast` or one of its subdomains, which may
 * always be framed. Any other host must be confirmed by the server first.
 * Mirrors the `space.fast` part of `Space_Url::may_frame_host()`.
 *
 * @param {string} url Space URL.
 * @return {boolean} True for a `space.fast` host.
 */
export function isSpacefastHost( url ) {
	const base = normalizeSpaceUrl( url );
	if ( ! base ) {
		return false;
	}
	const host = base.slice( 'https://'.length, -1 );
	return host === 'space.fast' || host.endsWith( '.space.fast' );
}
