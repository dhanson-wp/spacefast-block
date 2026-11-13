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
			header: 90,
			footer: 200,
		} );
	} );

	it( 'reports the admin bar separately from the header', () => {
		expect( computeLayout( { ...base, adminBarHeight: 32 } ) ).toEqual( {
			adminBar: 32,
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
		).toEqual( { adminBar: 46, header: 0, footer: 200 } );
	} );

	it( 'zeroes the footer when it is hidden', () => {
		expect( computeLayout( { ...base, hideFooter: true } ) ).toEqual( {
			adminBar: 0,
			header: 90,
			footer: 0,
		} );
	} );

	it( 'treats a missing header or footer as 0', () => {
		expect(
			computeLayout( { hideHeader: false, hideFooter: false } )
		).toEqual( { adminBar: 0, header: 0, footer: 0 } );
	} );
} );
