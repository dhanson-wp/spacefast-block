import {
	getBlockClassNames,
	getDisplayNotice,
	hasAspectRatio,
	getCardImageUrl,
	hasCardImage,
	getCardTitle,
	getClearedSpaceAttributes,
	getEffectiveDisplay,
	getFullHeightToggleAttributes,
	getLinkFallback,
	getMinHeight,
	getPrivateCta,
	getPrivateLabel,
	getSideloadRequest,
	isDisplayAvailable,
	isFullHeight,
	withoutAspectRatio,
} from '../display';

describe( 'isDisplayAvailable', () => {
	it.each( [ 'inline', 'fullpage', 'card', 'link' ] )(
		'allows %s for a Space that can be framed',
		( display ) => {
			expect( isDisplayAvailable( display, true ) ).toBe( true );
		}
	);

	it.each( [
		[ 'inline', false ],
		[ 'fullpage', false ],
		[ 'card', true ],
		[ 'link', true ],
	] )(
		'for a Space that cannot be framed, %s is %p',
		( display, expected ) => {
			expect( isDisplayAvailable( display, false ) ).toBe( expected );
		}
	);
} );

describe( 'getMinHeight', () => {
	it( 'joins the value and unit for inline', () => {
		expect(
			getMinHeight( {
				display: 'inline',
				isFrameable: true,
				minHeight: 420,
				minHeightUnit: 'px',
			} )
		).toBe( '420px' );
	} );

	it( 'defaults the unit to px', () => {
		expect(
			getMinHeight( {
				display: 'inline',
				isFrameable: true,
				minHeight: 30,
			} )
		).toBe( '30px' );
	} );

	it( 'is undefined when no height is set', () => {
		expect(
			getMinHeight( {
				display: 'inline',
				isFrameable: true,
				minHeightUnit: 'vh',
			} )
		).toBeUndefined();
	} );

	it.each( [ 'fullpage', 'card', 'link' ] )(
		'is undefined for %s',
		( display ) => {
			expect(
				getMinHeight( {
					display,
					isFrameable: true,
					minHeight: 50,
					minHeightUnit: 'vh',
				} )
			).toBeUndefined();
		}
	);

	it( 'is undefined for an inline Space that can no longer be framed', () => {
		expect(
			getMinHeight( {
				display: 'inline',
				isFrameable: false,
				minHeight: 50,
				minHeightUnit: 'vh',
			} )
		).toBeUndefined();
	} );

	it( 'gives way to an explicit aspect ratio', () => {
		expect(
			getMinHeight( {
				display: 'inline',
				isFrameable: true,
				minHeight: 50,
				minHeightUnit: 'vh',
				style: { dimensions: { aspectRatio: '16/9' } },
			} )
		).toBeUndefined();
	} );

	it( 'ignores an auto aspect ratio', () => {
		expect(
			getMinHeight( {
				display: 'inline',
				isFrameable: true,
				minHeight: 50,
				minHeightUnit: 'vh',
				style: { dimensions: { aspectRatio: 'auto' } },
			} )
		).toBe( '50vh' );
	} );
} );

describe( 'isFullHeight', () => {
	it( 'is true for 100vh', () => {
		expect( isFullHeight( { minHeight: 100, minHeightUnit: 'vh' } ) ).toBe(
			true
		);
	} );

	it.each( [
		[ { minHeight: 100, minHeightUnit: 'px' } ],
		[ { minHeight: 90, minHeightUnit: 'vh' } ],
		[ {} ],
		[
			{
				minHeight: 100,
				minHeightUnit: 'vh',
				style: { dimensions: { aspectRatio: '4/3' } },
			},
		],
	] )( 'is false for %p', ( attributes ) => {
		expect( isFullHeight( attributes ) ).toBe( false );
	} );
} );

describe( 'withoutAspectRatio', () => {
	it( 'drops the aspect ratio and empty containers', () => {
		expect(
			withoutAspectRatio( { dimensions: { aspectRatio: '16/9' } } )
		).toBeUndefined();
	} );

	it( 'keeps other styles', () => {
		expect(
			withoutAspectRatio( {
				dimensions: { aspectRatio: '16/9', minHeight: '1px' },
				border: { radius: '4px' },
			} )
		).toEqual( {
			dimensions: { minHeight: '1px' },
			border: { radius: '4px' },
		} );
	} );

	it( 'handles a missing style', () => {
		expect( withoutAspectRatio( undefined ) ).toBeUndefined();
	} );
} );

describe( 'getFullHeightToggleAttributes', () => {
	it( 'turns full height on and clears the aspect ratio', () => {
		expect(
			getFullHeightToggleAttributes(
				{
					minHeight: 420,
					minHeightUnit: 'px',
					style: { dimensions: { aspectRatio: '16/9' } },
				},
				{ minHeight: 420, minHeightUnit: 'px' }
			)
		).toEqual( { minHeight: 100, minHeightUnit: 'vh', style: undefined } );
	} );

	it( 'restores the previous values when turned off', () => {
		expect(
			getFullHeightToggleAttributes(
				{ minHeight: 100, minHeightUnit: 'vh' },
				{ minHeight: 420, minHeightUnit: 'px' }
			)
		).toEqual( { minHeight: 420, minHeightUnit: 'px' } );
	} );

	it( 'clears the height when the previous value was also full height', () => {
		expect(
			getFullHeightToggleAttributes(
				{ minHeight: 100, minHeightUnit: 'vh' },
				{ minHeight: 100, minHeightUnit: 'vh' }
			)
		).toEqual( { minHeight: undefined, minHeightUnit: undefined } );
	} );
} );

describe( 'fallback copy', () => {
	it( 'uses the private label when set', () => {
		expect( getPrivateLabel( { privateLabel: 'Team only' } ) ).toBe(
			'Team only'
		);
		expect( getPrivateLabel( { privateLabel: '' } ) ).toBe( 'Private' );
	} );

	it( 'uses the private call to action when set', () => {
		expect( getPrivateCta( { privateCta: 'Ask Derek' } ) ).toBe(
			'Ask Derek'
		);
		expect( getPrivateCta( { privateCta: '' } ) ).toBe( 'Request access' );
	} );

	it( 'falls back to "Open Space" for a public link', () => {
		expect( getLinkFallback( { isPrivate: false } ) ).toBe( 'Open Space' );
	} );

	it( 'falls back to the call to action for a private link', () => {
		expect( getLinkFallback( { isPrivate: true, privateCta: '' } ) ).toBe(
			'Request access'
		);
		expect(
			getLinkFallback( { isPrivate: true, privateCta: 'Ask Derek' } )
		).toBe( 'Ask Derek' );
	} );

	it( 'titles the card with the hostname when there is no title', () => {
		expect(
			getCardTitle( { title: 'Hi', hostname: 'a.space.fast' } )
		).toBe( 'Hi' );
		expect( getCardTitle( { title: '', hostname: 'a.space.fast' } ) ).toBe(
			'a.space.fast'
		);
	} );
} );

describe( 'getCardImageUrl', () => {
	const base = {
		showImage: true,
		imageUrl: 'https://a.space.fast/og.png',
		imageId: 0,
		isPrivate: false,
	};

	it( 'shows the Space image', () => {
		expect( getCardImageUrl( base ) ).toBe( 'https://a.space.fast/og.png' );
	} );

	it( 'shows nothing when the image is turned off', () => {
		expect( getCardImageUrl( { ...base, showImage: false } ) ).toBe( '' );
	} );

	it( 'shows nothing for a private Space without a media library image', () => {
		expect( getCardImageUrl( { ...base, isPrivate: true } ) ).toBe( '' );
	} );

	it( 'shows the media library image for a private Space', () => {
		expect(
			getCardImageUrl( {
				...base,
				isPrivate: true,
				imageId: 12,
				imageUrl: 'http://localhost/wp-content/uploads/a.png',
			} )
		).toBe( 'http://localhost/wp-content/uploads/a.png' );
	} );
} );

describe( 'library image without an image URL', () => {
	const base = {
		showImage: true,
		imageUrl: '',
		imageId: 12,
		isPrivate: false,
	};

	it( 'counts as an image', () => {
		expect( hasCardImage( base ) ).toBe( true );
		expect( hasCardImage( { ...base, isPrivate: true } ) ).toBe( true );
	} );

	it( 'uses the URL looked up from the media library', () => {
		expect( getCardImageUrl( base, 'http://localhost/up/a.png' ) ).toBe(
			'http://localhost/up/a.png'
		);
	} );

	it( 'shows nothing yet while the media URL is loading', () => {
		expect( getCardImageUrl( base ) ).toBe( '' );
	} );

	it( 'prefers the stored image URL', () => {
		expect(
			getCardImageUrl(
				{ ...base, imageUrl: 'http://localhost/up/b.png' },
				'http://localhost/up/a.png'
			)
		).toBe( 'http://localhost/up/b.png' );
	} );

	it( 'is turned off with the image', () => {
		expect( hasCardImage( { ...base, showImage: false } ) ).toBe( false );
	} );

	it( 'is not an image for a private Space without a library image', () => {
		expect(
			hasCardImage( {
				...base,
				imageId: 0,
				imageUrl: 'https://a.space.fast/og.png',
				isPrivate: true,
			} )
		).toBe( false );
	} );

	it( 'names the image position on the card', () => {
		expect(
			getBlockClassNames( {
				...base,
				display: 'card',
				isFrameable: true,
				imagePosition: 'side',
			} )
		).toBe( 'is-display-card has-image-side' );
	} );
} );

describe( 'getSideloadRequest', () => {
	it( 'sends the image link for a public Space', () => {
		expect(
			getSideloadRequest( {
				imageUrl: 'https://a.space.fast/og.png',
				imageId: 0,
				isPrivate: false,
				spaceId: 'spc_1',
			} )
		).toEqual( { url: 'https://a.space.fast/og.png' } );
	} );

	it( 'sends the Space ID for a private Space', () => {
		expect(
			getSideloadRequest( {
				imageUrl: '',
				imageId: 0,
				isPrivate: true,
				spaceId: 'spc_1',
			} )
		).toEqual( { spaceId: 'spc_1' } );
	} );

	it.each( [
		[ 'already in the library', { imageUrl: 'x', imageId: 4 } ],
		[ 'no image', { imageUrl: '', imageId: 0 } ],
		[
			'private without an ID',
			{ isPrivate: true, spaceId: '', imageId: 0 },
		],
	] )( 'is null when %s', ( _name, attributes ) => {
		expect( getSideloadRequest( attributes ) ).toBeNull();
	} );
} );

describe( 'getClearedSpaceAttributes', () => {
	it( 'clears only the Space details', () => {
		expect( getClearedSpaceAttributes() ).toEqual( {
			url: '',
			spaceId: '',
			title: '',
			description: '',
			hostname: '',
			imageUrl: '',
			imageId: 0,
			isFrameable: true,
			isPrivate: false,
		} );
	} );
} );

describe( 'getEffectiveDisplay', () => {
	it( 'keeps the chosen mode', () => {
		expect(
			getEffectiveDisplay( { display: 'fullpage', isFrameable: true } )
		).toBe( 'fullpage' );
	} );

	it.each( [ 'inline', 'fullpage' ] )(
		'shows a card instead of %s when the Space cannot be framed',
		( display ) => {
			expect(
				getEffectiveDisplay( { display, isFrameable: false } )
			).toBe( 'card' );
		}
	);
} );

describe( 'getBlockClassNames', () => {
	const base = {
		display: 'inline',
		isFrameable: true,
		isPrivate: false,
		showImage: true,
		imagePosition: 'top',
		imageUrl: 'https://a.space.fast/og.png',
		imageId: 0,
	};

	it( 'names the display mode', () => {
		expect( getBlockClassNames( base ) ).toBe( 'is-display-inline' );
	} );

	it( 'names the image position for a card with an image', () => {
		expect(
			getBlockClassNames( {
				...base,
				display: 'card',
				imagePosition: 'side',
			} )
		).toBe( 'is-display-card has-image-side' );
	} );

	it( 'leaves out the image position for a card without an image', () => {
		expect(
			getBlockClassNames( { ...base, display: 'card', showImage: false } )
		).toBe( 'is-display-card' );
	} );

	it( 'marks a private Space shown as a card', () => {
		expect(
			getBlockClassNames( {
				...base,
				display: 'inline',
				isFrameable: false,
				isPrivate: true,
			} )
		).toBe( 'is-display-card is-private' );
	} );
} );

describe( 'hasAspectRatio', () => {
	it( 'is true for an explicit ratio', () => {
		expect(
			hasAspectRatio( { style: { dimensions: { aspectRatio: '16/9' } } } )
		).toBe( true );
	} );

	it.each( [
		[ 'auto', { style: { dimensions: { aspectRatio: 'auto' } } } ],
		[
			'Auto with spaces',
			{ style: { dimensions: { aspectRatio: ' Auto ' } } },
		],
		[ 'no style', {} ],
		[ 'empty', { style: { dimensions: { aspectRatio: '' } } } ],
	] )( 'is false for %s', ( _name, attributes ) => {
		expect( hasAspectRatio( attributes ) ).toBe( false );
	} );
} );

describe( 'getDisplayNotice', () => {
	it( 'says nothing for a Space that can be framed', () => {
		expect(
			getDisplayNotice( { isFrameable: true, isPrivate: false } )
		).toBe( '' );
	} );

	it( 'explains a private Space', () => {
		expect(
			getDisplayNotice( { isFrameable: false, isPrivate: true } )
		).toBe(
			'This Space is private, so it can only be shown as a card or link.'
		);
	} );

	it( 'explains a public Space that refuses framing', () => {
		expect(
			getDisplayNotice( { isFrameable: false, isPrivate: false } )
		).toBe(
			"This Space can't be shown inside a page, so it can only be shown as a card or link."
		);
	} );
} );
