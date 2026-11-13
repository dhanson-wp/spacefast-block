import { getPickerView, shouldOfferConnect } from '../picker-view';

describe( 'getPickerView', () => {
	it( 'shows loading until the connection state is known', () => {
		expect(
			getPickerView( { isConnected: null, isLoading: true, error: '' } )
		).toBe( 'loading' );
	} );

	it( 'shows the error when the first load failed', () => {
		expect(
			getPickerView( {
				isConnected: null,
				isLoading: false,
				error: 'Nope.',
			} )
		).toBe( 'error' );
	} );

	it( 'shows loading again while a failed first load is retried', () => {
		expect(
			getPickerView( {
				isConnected: null,
				isLoading: true,
				error: 'Nope.',
			} )
		).toBe( 'loading' );
	} );

	it( 'offers to connect when there is no account', () => {
		expect(
			getPickerView( { isConnected: false, isLoading: false, error: '' } )
		).toBe( 'connect' );
	} );

	it( 'shows search once connected', () => {
		expect(
			getPickerView( { isConnected: true, isLoading: false, error: '' } )
		).toBe( 'search' );
	} );

	it( 'keeps showing search when a later search fails', () => {
		expect(
			getPickerView( {
				isConnected: true,
				isLoading: false,
				error: 'Nope.',
			} )
		).toBe( 'search' );
	} );
} );

describe( 'shouldOfferConnect', () => {
	it( 'offers to connect only once the account is known to be missing', () => {
		expect( shouldOfferConnect( false ) ).toBe( true );
	} );

	it( 'keeps the picker wording while the state is unknown or connected', () => {
		expect( shouldOfferConnect( null ) ).toBe( false );
		expect( shouldOfferConnect( undefined ) ).toBe( false );
		expect( shouldOfferConnect( true ) ).toBe( false );
	} );
} );
