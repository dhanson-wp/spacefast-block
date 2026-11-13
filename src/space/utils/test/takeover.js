import {
	FOOTER_SELECTORS,
	HEADER_SELECTORS,
	computeOffsets,
	footerRegionHeight,
	pickFirstVisible,
} from '../takeover';

describe( 'selectors', () => {
	it( 'prefer the block theme template parts', () => {
		expect( HEADER_SELECTORS[ 0 ] ).toBe( 'header.wp-block-template-part' );
		expect( FOOTER_SELECTORS[ 0 ] ).toBe( 'footer.wp-block-template-part' );
	} );

	it( 'fall back to classic theme landmarks', () => {
		expect( HEADER_SELECTORS ).toEqual( [
			'header.wp-block-template-part',
			'.wp-site-blocks > header',
			'#masthead',
			'.site-header',
			'body > header',
		] );
		expect( FOOTER_SELECTORS ).toEqual( [
			'footer.wp-block-template-part',
			'.wp-site-blocks > footer',
			'#colophon',
			'.site-footer',
			'body > footer',
		] );
	} );
} );

describe( 'pickFirstVisible', () => {
	const visible = ( element ) => element.visible;

	it( 'takes the first visible match from the first list that has one', () => {
		const hidden = { id: 'hidden', visible: false };
		const part = { id: 'part', visible: true };
		const masthead = { id: 'masthead', visible: true };

		expect(
			pickFirstVisible( [ [ hidden, part ], [ masthead ] ], visible )
		).toBe( part );
	} );

	it( 'falls through lists with no visible match', () => {
		const masthead = { id: 'masthead', visible: true };

		expect(
			pickFirstVisible(
				[ [], [ { visible: false } ], [ masthead ] ],
				visible
			)
		).toBe( masthead );
	} );

	it( 'is null when nothing is visible', () => {
		expect(
			pickFirstVisible( [ [ { visible: false } ], [] ], visible )
		).toBe( null );
		expect( pickFirstVisible( [], visible ) ).toBe( null );
	} );
} );

describe( 'footerRegionHeight', () => {
	it( 'keeps a footer up to 30% of the viewport', () => {
		expect( footerRegionHeight( 120, 800 ) ).toBe( 120 );
		expect( footerRegionHeight( 240, 800 ) ).toBe( 240 );
	} );

	it( 'caps a taller footer at 30% of the viewport', () => {
		expect( footerRegionHeight( 320, 800 ) ).toBe( 240 );
		expect( footerRegionHeight( 900, 800 ) ).toBe( 240 );
	} );

	it( 'is 0 for no footer', () => {
		expect( footerRegionHeight( 0, 800 ) ).toBe( 0 );
	} );
} );

describe( 'computeOffsets', () => {
	const base = {
		adminBarHeight: 0,
		headerHeight: 90,
		footerHeight: 200,
		viewportHeight: 800,
		hideHeader: false,
		hideFooter: false,
	};

	it( 'puts the Space between the header and the footer', () => {
		expect( computeOffsets( base ) ).toEqual( { top: 90, bottom: 200 } );
	} );

	it( 'adds the admin bar above the header', () => {
		expect( computeOffsets( { ...base, adminBarHeight: 32 } ) ).toEqual( {
			top: 122,
			bottom: 200,
		} );
	} );

	it( 'keeps the admin bar when the header is hidden', () => {
		expect(
			computeOffsets( { ...base, adminBarHeight: 46, hideHeader: true } )
		).toEqual( { top: 46, bottom: 200 } );
	} );

	it( 'zeroes the footer when it is hidden', () => {
		expect( computeOffsets( { ...base, hideFooter: true } ) ).toEqual( {
			top: 90,
			bottom: 0,
		} );
	} );

	it( 'zeroes both when both are hidden', () => {
		expect(
			computeOffsets( { ...base, hideHeader: true, hideFooter: true } )
		).toEqual( { top: 0, bottom: 0 } );
	} );

	it( 'caps a tall footer', () => {
		expect(
			computeOffsets( {
				...base,
				footerHeight: 700,
				viewportHeight: 600,
			} )
		).toEqual( { top: 90, bottom: 180 } );
	} );

	it( 'treats a missing header or footer as 0', () => {
		expect(
			computeOffsets( {
				viewportHeight: 800,
				hideHeader: false,
				hideFooter: false,
			} )
		).toEqual( { top: 0, bottom: 0 } );
	} );
} );
