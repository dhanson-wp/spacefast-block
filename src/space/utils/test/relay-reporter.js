/**
 * The reporter the relay puts first in a relayed page's <head>. It is a plain
 * ES5 file printed inline by PHP, so it is run here as the page would run it.
 */
import { readFileSync } from 'fs';
import { join } from 'path';

const SOURCE = readFileSync(
	join( __dirname, '../../../../includes/relay-reporter.js' ),
	'utf8'
);

let posted;
let parentWindow;
let added = [];

// Each test runs the reporter again in the same jsdom window, so the
// listeners it adds are recorded and removed after each test.
function run() {
	[ window, document ].forEach( ( target ) => {
		const add = target.addEventListener;
		target.addEventListener = function ( ...args ) {
			added.push( [ target, args ] );
			return add.apply( this, args );
		};
	} );
	try {
		new Function( SOURCE )();
	} finally {
		delete window.addEventListener;
		delete document.addEventListener;
	}
}

afterEach( () => {
	added.forEach( ( [ target, args ] ) =>
		target.removeEventListener( ...args )
	);
	added = [];
} );

function sent( type ) {
	return posted.filter( ( message ) => message.spacefastBlock === type );
}

function setHeight( value ) {
	Object.defineProperty( document.documentElement, 'scrollHeight', {
		configurable: true,
		value,
	} );
}

function click( element, init = {} ) {
	const event = new window.MouseEvent( 'click', {
		bubbles: true,
		cancelable: true,
		button: 0,
		...init,
	} );
	element.dispatchEvent( event );
	return event;
}

beforeEach( () => {
	posted = [];
	parentWindow = {
		postMessage: jest.fn( ( message ) => posted.push( message ) ),
	};
	Object.defineProperty( window, 'parent', {
		configurable: true,
		value: parentWindow,
	} );
	document.head.innerHTML = '<base href="https://a.space.fast/docs/">';
	document.body.innerHTML = '';
	setHeight( 1200 );
} );

describe( 'relay reporter', () => {
	it( 'posts to any origin, since the parent is not known', () => {
		run();
		document.dispatchEvent( new Event( 'DOMContentLoaded' ) );
		expect( parentWindow.postMessage ).toHaveBeenCalledWith(
			{ spacefastBlock: 'height', height: 1200 },
			'*'
		);
	} );

	it( 'is plain ES5 with no arrow functions, let, or const', () => {
		const code = SOURCE.replace( /\/\*[\s\S]*?\*\/|\/\/.*$/gm, '' );
		expect( code ).not.toMatch( /=>|\blet\s|\bconst\s|`/ );
	} );

	it( 'reports the document height once per change', () => {
		run();
		document.dispatchEvent( new Event( 'DOMContentLoaded' ) );
		window.dispatchEvent( new Event( 'load' ) );
		expect( sent( 'height' ) ).toEqual( [
			{ spacefastBlock: 'height', height: 1200 },
		] );
		expect( sent( 'ready' ) ).toHaveLength( 1 );

		setHeight( 1500 );
		window.dispatchEvent( new Event( 'load' ) );
		expect( sent( 'height' ).pop() ).toEqual( {
			spacefastBlock: 'height',
			height: 1500,
		} );
	} );

	it( 'uses the larger of the root and body heights', () => {
		Object.defineProperty( document.body, 'scrollHeight', {
			configurable: true,
			value: 1800,
		} );
		run();
		document.dispatchEvent( new Event( 'DOMContentLoaded' ) );
		expect( sent( 'height' ) ).toEqual( [
			{ spacefastBlock: 'height', height: 1800 },
		] );
		delete document.body.scrollHeight;
	} );

	it( 'reports a shorter page once the frame is taller than its content', () => {
		// The frame was sized for a narrower layout: scrollHeight can't drop
		// below the frame's own height, but the root box shows the real length.
		setHeight( 3000 );
		Object.defineProperty( document.documentElement, 'clientHeight', {
			configurable: true,
			value: 3000,
		} );
		document.documentElement.getBoundingClientRect = () => ( {
			height: 2100.4,
		} );
		run();
		document.dispatchEvent( new Event( 'DOMContentLoaded' ) );
		expect( sent( 'height' ) ).toEqual( [
			{ spacefastBlock: 'height', height: 2101 },
		] );
		delete document.documentElement.clientHeight;
		delete document.documentElement.getBoundingClientRect;
	} );

	it( 'keeps the scroll height when content overflows the root box', () => {
		Object.defineProperty( document.documentElement, 'clientHeight', {
			configurable: true,
			value: 800,
		} );
		document.documentElement.getBoundingClientRect = () => ( {
			height: 900,
		} );
		run();
		document.dispatchEvent( new Event( 'DOMContentLoaded' ) );
		expect( sent( 'height' ) ).toEqual( [
			{ spacefastBlock: 'height', height: 1200 },
		] );
		delete document.documentElement.clientHeight;
		delete document.documentElement.getBoundingClientRect;
	} );

	it( 'reports again when the parent asks', () => {
		run();
		document.dispatchEvent( new Event( 'DOMContentLoaded' ) );
		window.dispatchEvent( new Event( 'load' ) );
		posted = [];
		window.dispatchEvent(
			new MessageEvent( 'message', {
				data: { spacefastBlock: 'measure' },
				source: parentWindow,
			} )
		);
		expect( posted ).toEqual( [
			{ spacefastBlock: 'height', height: 1200 },
			{ spacefastBlock: 'ready' },
		] );
	} );

	it( 'stops scrolling inside once the parent has sized the frame', () => {
		run();
		window.dispatchEvent(
			new MessageEvent( 'message', {
				data: { spacefastBlock: 'sized' },
				source: window,
			} )
		);
		expect( document.documentElement.style.overflow ).toBe( '' );
		window.dispatchEvent(
			new MessageEvent( 'message', {
				data: { spacefastBlock: 'sized' },
				source: parentWindow,
			} )
		);
		expect( document.documentElement.style.overflow ).toBe( 'hidden' );
		document.documentElement.style.overflow = '';
	} );

	it( 'ignores a measure request from anyone but the parent', () => {
		run();
		document.dispatchEvent( new Event( 'DOMContentLoaded' ) );
		posted = [];
		window.dispatchEvent(
			new MessageEvent( 'message', {
				data: { spacefastBlock: 'measure' },
				source: window,
			} )
		);
		expect( posted ).toEqual( [] );
	} );

	it( 'reports a failed script or stylesheet, but not an image', () => {
		run();
		const img = document.createElement( 'img' );
		const script = document.createElement( 'script' );
		const sheet = document.createElement( 'link' );
		sheet.rel = 'stylesheet';
		const icon = document.createElement( 'link' );
		icon.rel = 'icon';
		document.body.append( img, script, sheet, icon );
		[ img, icon ].forEach( ( el ) =>
			el.dispatchEvent( new Event( 'error' ) )
		);
		expect( sent( 'error' ) ).toEqual( [] );
		[ script, sheet ].forEach( ( el ) =>
			el.dispatchEvent( new Event( 'error' ) )
		);
		expect( sent( 'error' ) ).toEqual( [
			{ spacefastBlock: 'error', kind: 'resource' },
			{ spacefastBlock: 'error', kind: 'resource' },
		] );
	} );

	it( 'reports uncaught errors and rejections as script errors', () => {
		run();
		window.dispatchEvent(
			new ErrorEvent( 'error', { message: 'boom', cancelable: true } )
		);
		const rejection = new Event( 'unhandledrejection' );
		window.dispatchEvent( rejection );
		expect( sent( 'error' ) ).toEqual( [
			{ spacefastBlock: 'error', kind: 'script' },
			{ spacefastBlock: 'error', kind: 'script' },
		] );
	} );

	it( 'resends an early error before ready when the parent asks', () => {
		run();
		window.dispatchEvent( new ErrorEvent( 'error', { message: 'x' } ) );
		document.dispatchEvent( new Event( 'DOMContentLoaded' ) );
		window.dispatchEvent( new Event( 'load' ) );
		posted = [];
		window.dispatchEvent(
			new MessageEvent( 'message', {
				data: { spacefastBlock: 'measure' },
				source: parentWindow,
			} )
		);
		expect( posted.map( ( m ) => m.spacefastBlock ) ).toEqual( [
			'error',
			'height',
			'ready',
		] );
	} );

	describe( 'links', () => {
		function link( href, attributes = {} ) {
			const a = document.createElement( 'a' );
			a.setAttribute( 'href', href );
			Object.entries( attributes ).forEach( ( [ name, value ] ) =>
				a.setAttribute( name, value )
			);
			a.innerHTML = '<span>go</span>';
			document.body.append( a );
			return a;
		}

		beforeEach( run );

		it( 'sends a same-Space link to the parent', () => {
			const event = click(
				link( 'guide/page.html?x=1#y' ).querySelector( 'span' )
			);
			expect( event.defaultPrevented ).toBe( true );
			expect( sent( 'navigate' ) ).toEqual( [
				{
					spacefastBlock: 'navigate',
					path: '/docs/guide/page.html?x=1#y',
				},
			] );
		} );

		it( 'leaves modified clicks, downloads, and new-tab links alone', () => {
			[
				[ link( 'a.html' ), { metaKey: true } ],
				[ link( 'a.html' ), { ctrlKey: true } ],
				[ link( 'a.html' ), { shiftKey: true } ],
				[ link( 'a.html' ), { altKey: true } ],
				[ link( 'a.html' ), { button: 1 } ],
				[ link( 'a.html', { download: '' } ), {} ],
				[ link( 'a.html', { target: '_blank' } ), {} ],
			].forEach( ( [ a, init ] ) => {
				expect( click( a, init ).defaultPrevented ).toBe( false );
			} );
			expect( sent( 'navigate' ) ).toEqual( [] );
		} );

		it( 'treats target _self as a normal link', () => {
			click( link( '/x/', { target: '_SELF' } ) );
			expect( sent( 'navigate' ) ).toEqual( [
				{ spacefastBlock: 'navigate', path: '/x/' },
			] );
		} );

		it( 'leaves a link the page already handled alone', () => {
			const a = link( 'a.html' );
			a.addEventListener( 'click', ( e ) => e.preventDefault() );
			click( a );
			expect( sent( 'navigate' ) ).toEqual( [] );
		} );

		it( 'opens other hosts in a new tab', () => {
			const a = link( 'https://example.com/' );
			expect( click( a ).defaultPrevented ).toBe( false );
			expect( a.getAttribute( 'target' ) ).toBe( '_blank' );
			expect( a.getAttribute( 'rel' ) ).toBe( 'noopener' );
			expect( sent( 'navigate' ) ).toEqual( [] );
		} );

		it( 'opens a file on the Space in a new tab', () => {
			const a = link( '/demo.mp4' );
			expect( click( a ).defaultPrevented ).toBe( false );
			expect( a.getAttribute( 'target' ) ).toBe( '_blank' );
			expect( sent( 'navigate' ) ).toEqual( [] );
		} );

		it( 'opens documents, archives, and media on the Space in a new tab', () => {
			[ '/a.pdf', '/b.ZIP', '/c.mp3', '/d.png', '/e.docx' ].forEach(
				( href ) => {
					const a = link( href );
					expect( click( a ).defaultPrevented ).toBe( false );
					expect( a.getAttribute( 'target' ) ).toBe( '_blank' );
				}
			);
			expect( sent( 'navigate' ) ).toEqual( [] );
		} );

		it( 'sends any other same-Space path through the relay', () => {
			click( link( 'a.html' ) );
			click( link( '/b.htm' ) );
			click( link( '/v1.2/' ) );
			click( link( '/v1.2' ) );
			click( link( '/page.php' ) );
			expect( sent( 'navigate' ).map( ( m ) => m.path ) ).toEqual( [
				'/docs/a.html',
				'/b.htm',
				'/v1.2/',
				'/v1.2',
				'/page.php',
			] );
		} );

		it( 'leaves mail links alone', () => {
			const a = link( 'mailto:a@example.com' );
			expect( click( a ).defaultPrevented ).toBe( false );
			expect( a.hasAttribute( 'target' ) ).toBe( false );
		} );

		it( 'scrolls to a hash on the page', () => {
			const target = document.createElement( 'h2' );
			target.id = 'part two';
			target.getBoundingClientRect = () => ( { top: 640 } );
			document.body.append( target );
			const event = click( link( '#part%20two' ) );
			expect( event.defaultPrevented ).toBe( true );
			expect( sent( 'scroll' ) ).toEqual( [
				{ spacefastBlock: 'scroll', top: 640 },
			] );
		} );

		it( 'scrolls to the top for an empty hash', () => {
			click( link( '#' ) );
			expect( sent( 'scroll' ) ).toEqual( [
				{ spacefastBlock: 'scroll', top: 0 },
			] );
		} );
	} );
} );

describe( 'relay reporter in the sandbox', () => {
	it( 'replaces storage and cookies that throw', () => {
		const throwing = () => {
			throw new DOMException( 'denied', 'SecurityError' );
		};
		const originals = {
			local: Object.getOwnPropertyDescriptor( window, 'localStorage' ),
			cookie: Object.getOwnPropertyDescriptor(
				window.Document.prototype,
				'cookie'
			),
		};
		Object.defineProperty( window, 'localStorage', {
			configurable: true,
			get: throwing,
		} );
		Object.defineProperty( document, 'cookie', {
			configurable: true,
			get: throwing,
			set: throwing,
		} );

		run();

		window.localStorage.setItem( 'a', 1 );
		expect( window.localStorage.getItem( 'a' ) ).toBe( '1' );
		expect( window.localStorage.length ).toBe( 1 );
		expect( window.localStorage.key( 0 ) ).toBe( 'a' );
		window.localStorage.removeItem( 'a' );
		expect( window.localStorage.getItem( 'a' ) ).toBeNull();
		document.cookie = 'a=1';
		expect( document.cookie ).toBe( '' );

		delete document.cookie;
		if ( originals.local ) {
			Object.defineProperty( window, 'localStorage', originals.local );
		}
	} );

	it( 'leaves working storage alone', () => {
		const before = window.sessionStorage;
		run();
		expect( window.sessionStorage ).toBe( before );
	} );
} );
