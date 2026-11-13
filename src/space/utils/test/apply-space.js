import { getSpaceAttributes } from '../apply-space';

// What the block ends up with once the returned patch is applied.
const applied = ( attributes, details ) => ( {
	...attributes,
	...getSpaceAttributes( attributes, details ),
} );

const details = {
	url: 'https://a.space.fast/',
	spaceId: 'spc_123',
	title: 'A Space',
	description: 'About it',
	hostname: 'a.space.fast',
	imageUrl: 'https://a.space.fast/og.png',
	isFrameable: true,
	isPrivate: false,
};

describe( 'getSpaceAttributes', () => {
	it( 'copies the Space details', () => {
		expect( applied( { display: 'inline' }, details ) ).toEqual(
			expect.objectContaining( details )
		);
	} );

	it( 'leaves display settings alone for a frameable Space', () => {
		const result = applied(
			{ display: 'fullpage', clickAction: 'modal' },
			details
		);
		expect( result.display ).toBe( 'fullpage' );
		expect( result.clickAction ).toBe( 'modal' );
	} );

	it( 'keeps card and link display for a frameable Space', () => {
		const result = applied(
			{ display: 'link', clickAction: 'link' },
			details
		);
		expect( result.display ).toBe( 'link' );
		expect( result.clickAction ).toBe( 'link' );
	} );

	it.each( [ 'inline', 'fullpage' ] )(
		'moves %s to card for a Space that cannot be framed',
		( display ) => {
			const result = applied(
				{ display, clickAction: 'modal' },
				{ ...details, isFrameable: false, isPrivate: true }
			);
			expect( result.display ).toBe( 'card' );
			expect( result.clickAction ).toBe( 'link' );
		}
	);

	it( 'keeps link display for a Space that cannot be framed', () => {
		const result = applied(
			{ display: 'link', clickAction: 'modal' },
			{ ...details, isFrameable: false }
		);
		expect( result.display ).toBe( 'link' );
		expect( result.clickAction ).toBe( 'link' );
	} );

	it( 'resets the chosen image', () => {
		expect(
			applied( { imageId: 42, display: 'inline' }, details ).imageId
		).toBe( 0 );
	} );

	it( 'defaults a missing space id and image to empty values', () => {
		const result = applied(
			{ display: 'inline' },
			{ ...details, spaceId: undefined, imageUrl: undefined }
		);
		expect( result.spaceId ).toBe( '' );
		expect( result.imageUrl ).toBe( '' );
	} );
} );
