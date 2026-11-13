import { looksLikeUrl } from '../url';

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
