/**
 * Printed inline, first in the <head> of every page the relay serves, right
 * after the <base> that points the page at its Space. Plain ES5 with no
 * dependencies, so it runs before and alongside anything the Space loads.
 *
 * The page runs in a sandboxed frame with an opaque origin, so it tells the
 * block's view script (src/space/view.js) what it needs by postMessage:
 *
 * - `height`: the document height, whenever it changes.
 * - `ready`: the page finished loading.
 * - `error`: a script or stylesheet failed, or a script threw.
 * - `navigate`: a link to another page of the Space was followed.
 * - `scroll`: a link to a place on this page was followed.
 *
 * The parent can send `measure` to have it all sent again.
 */
/* eslint-disable no-var, object-shorthand, no-unused-vars -- ES5: no `let`, shorthand, or optional catch bindings. */
( function () {
	var win = window;
	var doc = document;
	var root = doc.documentElement;
	var lastHeight = -1;
	var isReady = false;
	var earlyError = null;
	var base = doc.getElementsByTagName( 'base' )[ 0 ];
	// <base> has no `host` of its own; the relay always writes it as https.
	var spaceHost = base
		? ( /^https:\/\/([^/?#]+)/i.exec( base.href ) || [ '', '' ] )[ 1 ]
		: '';

	function post( message ) {
		try {
			win.parent.postMessage( message, '*' );
		} catch ( e ) {}
	}

	// Storage and cookies throw in an opaque origin; ordinary Space scripts
	// expect them to work, so give them in-memory stand-ins.
	function memoryStorage() {
		var items = {};
		return {
			getItem: function ( key ) {
				return Object.prototype.hasOwnProperty.call( items, key )
					? items[ key ]
					: null;
			},
			setItem: function ( key, value ) {
				items[ key ] = String( value );
			},
			removeItem: function ( key ) {
				delete items[ key ];
			},
			clear: function () {
				items = {};
			},
			key: function ( index ) {
				var keys = Object.keys( items );
				return index < keys.length ? keys[ index ] : null;
			},
			get length() {
				return Object.keys( items ).length;
			},
		};
	}

	[ 'localStorage', 'sessionStorage' ].forEach( function ( name ) {
		try {
			// Reading it is what throws.
			if ( win[ name ] ) {
				return;
			}
		} catch ( e ) {}
		try {
			Object.defineProperty( win, name, {
				configurable: true,
				value: memoryStorage(),
			} );
		} catch ( e ) {}
	} );

	try {
		// Reading it is what throws.
		if ( typeof doc.cookie !== 'string' ) {
			throw new Error();
		}
	} catch ( e ) {
		try {
			Object.defineProperty( doc, 'cookie', {
				configurable: true,
				get: function () {
					return '';
				},
				set: function () {},
			} );
		} catch ( x ) {}
	}

	// The scroll height never drops below the frame's own height, so once the
	// frame is taller than the page (after the layout narrows and widens again)
	// it would never shrink. When nothing overflows, the root element's box is
	// the page's real length.
	function measure() {
		var height = Math.max(
			root ? root.scrollHeight : 0,
			doc.body ? doc.body.scrollHeight : 0
		);
		if ( root && height <= root.clientHeight ) {
			height = Math.min(
				height,
				Math.ceil( root.getBoundingClientRect().height )
			);
		}
		return height;
	}

	function reportHeight() {
		var height = measure();
		if ( height > 0 && height !== lastHeight ) {
			lastHeight = height;
			post( { spacefastBlock: 'height', height: height } );
		}
	}

	function reportError( kind ) {
		var message = { spacefastBlock: 'error', kind: kind };
		if ( ! isReady && ! earlyError ) {
			earlyError = message;
		}
		post( message );
	}

	// Failed scripts and stylesheets. Load errors don't bubble, so listen in
	// the capture phase; images and media are left alone.
	win.addEventListener(
		'error',
		function ( event ) {
			var target = event.target;
			if ( target === win || ! target || ! target.nodeName ) {
				reportError( 'script' );
				return;
			}
			if (
				target.nodeName === 'SCRIPT' ||
				( target.nodeName === 'LINK' &&
					/(^|\s)stylesheet(\s|$)/i.test( target.rel || '' ) )
			) {
				reportError( 'resource' );
			}
		},
		true
	);
	win.addEventListener( 'unhandledrejection', function () {
		reportError( 'script' );
	} );

	doc.addEventListener( 'DOMContentLoaded', function () {
		reportHeight();
		if ( typeof win.ResizeObserver === 'function' ) {
			var observer = new win.ResizeObserver( reportHeight );
			observer.observe( root );
			if ( doc.body ) {
				observer.observe( doc.body );
			}
		}
	} );
	win.addEventListener( 'load', function () {
		reportHeight();
		isReady = true;
		post( { spacefastBlock: 'ready' } );
	} );
	if ( doc.fonts ) {
		if ( doc.fonts.ready ) {
			doc.fonts.ready.then( reportHeight );
		}
		if ( doc.fonts.addEventListener ) {
			doc.fonts.addEventListener( 'loadingdone', reportHeight );
		}
	}

	// The parent's script can start after the first reports were sent.
	win.addEventListener( 'message', function ( event ) {
		if (
			event.source !== win.parent ||
			! event.data ||
			event.data.spacefastBlock !== 'measure'
		) {
			return;
		}
		if ( earlyError ) {
			post( earlyError );
		}
		lastHeight = -1;
		reportHeight();
		if ( isReady ) {
			post( { spacefastBlock: 'ready' } );
		}
	} );

	function closestLink( node ) {
		while ( node && node.nodeName !== 'A' && node.nodeName !== 'AREA' ) {
			node = node.parentNode;
		}
		return node && node.hasAttribute && node.hasAttribute( 'href' )
			? node
			: null;
	}

	function scrollTarget( hash ) {
		var id;
		try {
			id = decodeURIComponent( hash );
		} catch ( e ) {
			id = hash;
		}
		if ( id === '' || id.toLowerCase() === 'top' ) {
			return 0;
		}
		var element =
			doc.getElementById( id ) || doc.getElementsByName( id )[ 0 ];
		return element
			? Math.max(
					0,
					Math.round(
						element.getBoundingClientRect().top +
							( win.pageYOffset || 0 )
					)
				)
			: null;
	}

	// Links resolve against the <base>, so left alone they would load the
	// Space straight into the frame. Pages of the same Space go through the
	// parent; other sites and files open in a new tab.
	doc.addEventListener( 'click', function ( event ) {
		var link = closestLink( event.target );
		if ( ! link || event.defaultPrevented ) {
			return;
		}
		var protocol = link.protocol;
		if ( protocol !== 'https:' && protocol !== 'http:' ) {
			return;
		}
		// Another site, or a file on the Space (a video, a PDF) rather than a
		// page, which the relay doesn't serve.
		if (
			link.host !== spaceHost ||
			protocol !== 'https:' ||
			/\.(?!html?$)[A-Za-z0-9]+$/.test( link.pathname )
		) {
			if ( ! link.hasAttribute( 'download' ) ) {
				link.setAttribute( 'target', '_blank' );
				link.setAttribute( 'rel', 'noopener' );
			}
			return;
		}
		var target = link.getAttribute( 'target' );
		if (
			event.button !== 0 ||
			event.metaKey ||
			event.ctrlKey ||
			event.shiftKey ||
			event.altKey ||
			link.hasAttribute( 'download' ) ||
			( target && target.toLowerCase() !== '_self' )
		) {
			return;
		}
		event.preventDefault();
		var raw = link.getAttribute( 'href' );
		if ( raw.charAt( 0 ) === '#' ) {
			var top = scrollTarget( raw.slice( 1 ) );
			if ( top !== null ) {
				post( { spacefastBlock: 'scroll', top: top } );
			}
			return;
		}
		post( {
			spacefastBlock: 'navigate',
			path: link.pathname + link.search + link.hash,
		} );
	} );
} )();
