import { getPickerView } from '../picker-view';

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
			getPickerView( { isConnected: null, isLoading: true, error: '' } )
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
