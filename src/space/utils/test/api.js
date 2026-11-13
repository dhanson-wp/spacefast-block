import { getErrorMessage } from '../api';

describe( 'getErrorMessage', () => {
	it( 'uses the message the server sent', () => {
		expect(
			getErrorMessage( {
				code: 'spacefast_not_a_space',
				message: "That link isn't a Spacefast Space.",
			} )
		).toBe( "That link isn't a Spacefast Space." );
	} );

	it.each( [ undefined, null, {}, { message: '' }, { message: 5 } ] )(
		'falls back to a generic message for %p',
		( error ) => {
			expect( getErrorMessage( error ) ).toBe(
				'Something went wrong. Please try again.'
			);
		}
	);
} );
