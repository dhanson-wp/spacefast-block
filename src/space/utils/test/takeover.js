import {
	FOOTER_SELECTORS,
	HEADER_SELECTORS,
	computeLayout,
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

describe( 'computeLayout', () => {
	const base = {
		adminBarHeight: 0,
		headerHeight: 90,
		footerHeight: 200,
		hideHeader: false,
		hideFooter: false,
	};

	it( 'gives the header and the footer their natural heights', () => {
		expect( computeLayout( base ) ).toEqual( {
			adminBar: 0,
			offset: 0,
			header: 90,
			footer: 200,
		} );
	} );

	it( 'reports the admin bar separately from the header', () => {
		expect( computeLayout( { ...base, adminBarHeight: 32 } ) ).toEqual( {
			adminBar: 32,
			offset: 0,
			header: 90,
			footer: 200,
		} );
	} );

	it( 'never caps a tall footer, because it sits below the Space', () => {
		expect( computeLayout( { ...base, footerHeight: 700 } ).footer ).toBe(
			700
		);
	} );

	it( 'zeroes the header when it is hidden and keeps the admin bar', () => {
		expect(
			computeLayout( { ...base, adminBarHeight: 46, hideHeader: true } )
		).toEqual( { adminBar: 46, offset: 0, header: 0, footer: 200 } );
	} );

	it( 'zeroes the footer when it is hidden', () => {
		expect( computeLayout( { ...base, hideFooter: true } ) ).toEqual( {
			adminBar: 0,
			offset: 0,
			header: 90,
			footer: 0,
		} );
	} );

	it( 'treats a missing header or footer as 0', () => {
		expect(
			computeLayout( { hideHeader: false, hideFooter: false } )
		).toEqual( { adminBar: 0, offset: 0, header: 0, footer: 0 } );
	} );
} );

describe( 'computeLayout offset', () => {
	const base = { hideHeader: false, hideFooter: false };

	it( "starts the page below <html>'s admin bar margin", () => {
		expect(
			computeLayout( { ...base, adminBarHeight: 46, rootTop: 46 } ).offset
		).toBe( 46 );
	} );

	it( 'adds nothing when <html> is positioned, because it is then the containing block', () => {
		expect(
			computeLayout( {
				...base,
				adminBarHeight: 32,
				rootTop: 32,
				rootIsPositioned: true,
			} ).offset
		).toBe( 0 );
	} );

	it( "follows <html>'s margin, not the admin bar, when the bar is hidden", () => {
		expect(
			computeLayout( { ...base, adminBarHeight: 0, rootTop: 46 } ).offset
		).toBe( 46 );
	} );

	it( "is 0 when the admin bar overlaps a page without <html>'s margin", () => {
		expect(
			computeLayout( { ...base, adminBarHeight: 32, rootTop: 0 } ).offset
		).toBe( 0 );
	} );

	it( "counts <html>'s border and padding", () => {
		const measures = {
			...base,
			rootTop: 32,
			rootBorderTop: 2,
			rootPaddingTop: 10,
		};
		expect( computeLayout( measures ).offset ).toBe( 44 );
		expect(
			computeLayout( { ...measures, rootIsPositioned: true } ).offset
		).toBe( 10 );
	} );

	it( 'never goes negative', () => {
		expect( computeLayout( { ...base, rootTop: -20 } ).offset ).toBe( 0 );
	} );
} );
