import { frameUrl, isSpacefastHost, looksLikeUrl } from '../url';

describe( 'looksLikeUrl', () => {
	it( 'accepts a bare Space host', () => {
		expect( looksLikeUrl( 'a.space.fast' ) ).toBe( true );
	} );

	it( 'accepts an https link with a path', () => {
		expect( looksLikeUrl( 'https://a.space.fast/x' ) ).toBe( true );
	} );

	it( 'rejects text with spaces', () => {
		expect( looksLikeUrl( 'hello world' ) ).toBe( false );
	} );

	it( 'rejects an empty string', () => {
		expect( looksLikeUrl( '' ) ).toBe( false );
	} );

	it( 'rejects http links', () => {
		expect( looksLikeUrl( 'http://a.space.fast' ) ).toBe( false );
	} );
} );

describe( 'frameUrl', () => {
	const base = 'https://a.space.fast/';

	it( 'appends a relative path', () => {
		expect( frameUrl( base, 'docs/intro' ) ).toBe(
			'https://a.space.fast/docs/intro'
		);
	} );

	it( 'tolerates a leading slash', () => {
		expect( frameUrl( base, '/docs' ) ).toBe( 'https://a.space.fast/docs' );
	} );

	it( 'keeps the query and fragment', () => {
		expect( frameUrl( base, 'docs/intro?tab=2#top' ) ).toBe(
			'https://a.space.fast/docs/intro?tab=2#top'
		);
	} );

	it( 'returns the base for an empty path', () => {
		expect( frameUrl( base, '' ) ).toBe( base );
	} );

	it( 'normalizes the base to scheme and host', () => {
		expect( frameUrl( 'HTTPS://A.Space.Fast/x?y#z', 'docs' ) ).toBe(
			'https://a.space.fast/docs'
		);
	} );

	it.each( [
		[ 'protocol relative', '//evil.com' ],
		[ 'absolute url', 'https://evil.com/x' ],
		[ 'parent traversal', '../..' ],
		[ 'embedded parent', 'docs/../../x' ],
		[ 'dot segment', './docs' ],
		[ 'encoded dots', '%2e%2e/x' ],
		[ 'backslash', 'docs\\intro' ],
		[ 'leading backslash', '\\\\evil.com' ],
		[ 'control character', 'docs/\x01intro' ],
		[ 'newline', 'docs\nintro' ],
		[ 'at sign', 'x@evil.com' ],
		[ 'javascript', 'javascript:alert(1)' ],
		[ 'space', 'docs/in tro' ],
		[ 'bad percent', 'docs/%zz' ],
		[ 'empty segment', 'docs//intro' ],
	] )( 'returns the base for an unsafe path (%s)', ( _name, path ) => {
		expect( frameUrl( base, path ) ).toBe( base );
	} );

	it.each( [
		[ 'http loopback', 'http://127.0.0.1/' ],
		[ 'javascript', 'javascript:alert(1)' ],
		[ 'empty', '' ],
	] )( 'returns an empty string for an invalid base (%s)', ( _name, url ) => {
		expect( frameUrl( url, 'docs' ) ).toBe( '' );
		expect( frameUrl( url, '' ) ).toBe( '' );
	} );

	it.each( [
		[ 'userinfo', 'https://user:pw@a.space.fast' ],
		[ 'port', 'https://a.space.fast:8443' ],
		[ 'ipv4', 'https://127.0.0.1' ],
		[ 'ipv6', 'https://[::1]' ],
		[ 'localhost', 'https://localhost' ],
		[ 'hex octet', 'https://127.0.0.0x1' ],
		[ 'local tld', 'https://printer.local' ],
		[ 'numeric tld', 'https://127.1' ],
	] )(
		'returns an empty string for a base the server refuses (%s)',
		( _name, url ) => {
			expect( frameUrl( url, '' ) ).toBe( '' );
		}
	);
} );

describe( 'isSpacefastHost', () => {
	// Mirrors the space.fast cases of Space_Url::may_frame_host() in tests/php/SpaceUrlTest.php.
	it.each( [
		[ 'https://space.fast/', true ],
		[ 'https://a.space.fast/', true ],
		[ 'https://a.b.space.fast/docs', true ],
		[ 'https://A.Space.Fast/', true ],
		[ 'https://evilspace.fast/', false ],
		[ 'https://a.space.fast.evil.com/', false ],
		[ 'https://example.com/', false ],
		[ 'javascript:alert(1)', false ],
		[ '', false ],
	] )( '%s is %p', ( url, expected ) => {
		expect( isSpacefastHost( url ) ).toBe( expected );
	} );
} );
