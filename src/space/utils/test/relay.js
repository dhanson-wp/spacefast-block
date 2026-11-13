import cases from '../../../../tests/fixtures/relay-urls.json';
import {
	HEIGHT_TIMEOUT,
	MAX_HEIGHT,
	acceptScroll,
	clampHeight,
	navigationTarget,
	pageRelayUrl,
	readMessage,
	relayUrl,
	remainingWait,
	shouldFallBack,
} from '../relay';

const BASE = 'http://example.test/wp-json/spacefast-block/v1/';
const SIG = '0123456789abcdef';

describe( 'pageRelayUrl (shared with the PHP twin)', () => {
	it.each( cases.pageRelayUrl.map( ( c ) => [ c.name, c ] ) )(
		'%s',
		( name, c ) => {
			expect(
				pageRelayUrl( c.relayBase, c.url, c.startPath, c.signature )
			).toBe( c.expected );
		}
	);
} );

describe( 'relayUrl (shared with the PHP twin)', () => {
	it.each( cases.relayUrl.map( ( c ) => [ c.name, c ] ) )(
		'%s',
		( name, c ) => {
			expect( relayUrl( c.relayBase, c.kind, c.url, c.signature ) ).toBe(
				c.expected
			);
		}
	);
} );

describe( 'clampHeight', () => {
	it( 'keeps a height in range, rounded up', () => {
		expect( clampHeight( 8832, 700 ) ).toBe( 8832 );
		expect( clampHeight( 8831.2, 700 ) ).toBe( 8832 );
	} );

	it( 'is at least one screen', () => {
		expect( clampHeight( 300, 700 ) ).toBe( 700 );
	} );

	it( 'is at most the maximum', () => {
		expect( clampHeight( 250000, 700 ) ).toBe( MAX_HEIGHT );
		expect( MAX_HEIGHT ).toBe( 100000 );
	} );

	it( 'treats a missing or negative minimum as zero', () => {
		expect( clampHeight( 10, -5 ) ).toBe( 10 );
		expect( clampHeight( 10, undefined ) ).toBe( 10 );
	} );
} );

describe( 'readMessage', () => {
	const frame = {};
	const other = {};
	const event = ( data, source = frame ) => ( {
		data,
		source,
		origin: 'null',
	} );

	it( 'accepts known messages from the frame', () => {
		expect(
			readMessage(
				event( { spacefastBlock: 'height', height: 900 } ),
				frame
			)
		).toEqual( { type: 'height', height: 900 } );
		expect(
			readMessage( event( { spacefastBlock: 'ready' } ), frame )
		).toEqual( { type: 'ready' } );
		expect(
			readMessage(
				event( { spacefastBlock: 'error', kind: 'script' } ),
				frame
			)
		).toEqual( { type: 'error', kind: 'script' } );
		expect(
			readMessage(
				event( { spacefastBlock: 'navigate', path: '/a/?b=1#c' } ),
				frame
			)
		).toEqual( { type: 'navigate', path: '/a/?b=1#c' } );
		expect(
			readMessage(
				event( { spacefastBlock: 'scroll', top: 120 } ),
				frame
			)
		).toEqual( { type: 'scroll', top: 120 } );
	} );

	it( 'ignores messages from any other window', () => {
		expect(
			readMessage(
				event( { spacefastBlock: 'height', height: 900 }, other ),
				frame
			)
		).toBeNull();
		expect(
			readMessage(
				event( { spacefastBlock: 'height', height: 900 }, null ),
				null
			)
		).toBeNull();
	} );

	it( 'ignores unknown or malformed messages', () => {
		[
			null,
			'height',
			{},
			{ spacefastBlock: 'resize', height: 900 },
			{ spacefastBlock: 'height' },
			{ spacefastBlock: 'height', height: '900' },
			{ spacefastBlock: 'height', height: NaN },
			{ spacefastBlock: 'height', height: Infinity },
			{ spacefastBlock: 'height', height: -1 },
			{ spacefastBlock: 'navigate', path: 42 },
			{ spacefastBlock: 'scroll', top: 'x' },
			{ spacefastBlock: 'error', kind: 'other' },
		].forEach( ( data ) => {
			expect( readMessage( event( data ), frame ) ).toBeNull();
		} );
	} );

	it( 'does not read event.origin', () => {
		expect(
			readMessage(
				{
					data: { spacefastBlock: 'ready' },
					source: frame,
					origin: 'https://evil.example',
				},
				frame
			)
		).toEqual( { type: 'ready' } );
	} );
} );

describe( 'shouldFallBack', () => {
	it( 'falls back on an error before the page is ready', () => {
		expect( shouldFallBack( { type: 'error' }, { ready: false } ) ).toBe(
			true
		);
	} );

	it( 'ignores errors once the page is ready', () => {
		expect( shouldFallBack( { type: 'error' }, { ready: true } ) ).toBe(
			false
		);
	} );

	it( 'falls back when the page keeps growing to the maximum', () => {
		expect(
			shouldFallBack(
				{ type: 'height', height: MAX_HEIGHT },
				{ ready: true }
			)
		).toBe( true );
		expect(
			shouldFallBack( { type: 'height', height: 9000 }, { ready: true } )
		).toBe( false );
	} );

	it( 'falls back when no height came in time', () => {
		expect(
			shouldFallBack( { type: 'timeout' }, { hasHeight: false } )
		).toBe( true );
		expect(
			shouldFallBack( { type: 'timeout' }, { hasHeight: true } )
		).toBe( false );
	} );

	it( 'never falls back twice', () => {
		expect(
			shouldFallBack(
				{ type: 'error' },
				{ ready: false, fellBack: true }
			)
		).toBe( false );
	} );

	it( 'does not fall back on other messages', () => {
		expect( shouldFallBack( { type: 'ready' }, {} ) ).toBe( false );
		expect(
			shouldFallBack( { type: 'navigate', path: '/' }, { ready: false } )
		).toBe( false );
	} );
} );

describe( 'navigationTarget', () => {
	const space = 'https://a.space.fast/';

	it( 'builds the direct and relay URLs for a safe path', () => {
		expect(
			navigationTarget( space, '/docs/a.html#y', BASE, SIG )
		).toEqual( {
			direct: 'https://a.space.fast/docs/a.html#y',
			relay: `${ BASE }page/a.space.fast~${ SIG }/docs/a.html`,
		} );
		expect( navigationTarget( space, '/', BASE, SIG ) ).toEqual( {
			direct: 'https://a.space.fast/',
			relay: `${ BASE }page/a.space.fast~${ SIG }/`,
		} );
	} );

	it( 'loads a page with a query directly', () => {
		expect( navigationTarget( space, '/a/?x=1', BASE, SIG ) ).toEqual( {
			direct: 'https://a.space.fast/a/?x=1',
			relay: '',
		} );
	} );

	it( 'refuses an unsafe path', () => {
		[
			'/../x',
			'/a/./b',
			'//evil.example/',
			'/a@b',
			'/a\\b',
			'',
			'x',
		].forEach( ( path ) => {
			expect( navigationTarget( space, path, BASE, SIG ) ).toBeNull();
		} );
	} );
} );

describe( 'acceptScroll', () => {
	it( 'accepts a scroll during a user gesture, clamped to the page', () => {
		expect( acceptScroll( 640, { isActive: true, maxTop: 9000 } ) ).toBe(
			640
		);
		expect( acceptScroll( 20000, { isActive: true, maxTop: 9000 } ) ).toBe(
			9000
		);
	} );

	it( 'refuses a scroll without a user gesture', () => {
		expect( acceptScroll( 640, { isActive: false, maxTop: 9000 } ) ).toBe(
			null
		);
	} );

	it( 'accepts a scroll where the browser has no activation API', () => {
		expect(
			acceptScroll( 640, { isActive: undefined, maxTop: 9000 } )
		).toBe( 640 );
	} );

	it( 'refuses a scroll before any height is known', () => {
		expect( acceptScroll( 640, { isActive: true, maxTop: 0 } ) ).toBe(
			null
		);
	} );
} );

describe( 'remainingWait', () => {
	it( 'counts from when the frame started loading', () => {
		expect( remainingWait( 2500, 500 ) ).toBe( HEIGHT_TIMEOUT - 2000 );
	} );

	it( 'never goes below zero', () => {
		expect( remainingWait( 9000, 500 ) ).toBe( 0 );
	} );

	it( 'waits the full time when the start is unknown', () => {
		expect( remainingWait( 2500, undefined ) ).toBe( HEIGHT_TIMEOUT );
		expect( remainingWait( 2500, 4000 ) ).toBe( HEIGHT_TIMEOUT );
	} );
} );
