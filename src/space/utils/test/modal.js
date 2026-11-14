import {
	isBackdropClick,
	isBackdropDismiss,
	shouldOpenModal,
	supportsModal,
} from '../modal';

describe( 'shouldOpenModal', () => {
	const plain = {
		button: 0,
		metaKey: false,
		ctrlKey: false,
		shiftKey: false,
		altKey: false,
	};

	it( 'opens on a plain primary click when dialogs are supported', () => {
		expect( shouldOpenModal( plain, true ) ).toBe( true );
	} );

	it.each( [ 'metaKey', 'ctrlKey', 'shiftKey', 'altKey' ] )(
		'lets the browser handle a click with %s held',
		( key ) => {
			expect( shouldOpenModal( { ...plain, [ key ]: true }, true ) ).toBe(
				false
			);
		}
	);

	it.each( [ 1, 2 ] )(
		'lets the browser handle button %d (middle or secondary)',
		( button ) => {
			expect( shouldOpenModal( { ...plain, button }, true ) ).toBe(
				false
			);
		}
	);

	it( 'lets the link work when dialogs are not supported', () => {
		expect( shouldOpenModal( plain, false ) ).toBe( false );
	} );

	it( 'lets the link work when the click was already handled', () => {
		expect(
			shouldOpenModal( { ...plain, defaultPrevented: true }, true )
		).toBe( false );
	} );
} );

describe( 'shouldOpenModal for a button', () => {
	// A button has no link to fall back to, so a modified click still opens it.
	const plain = {
		button: 0,
		metaKey: false,
		ctrlKey: false,
		shiftKey: false,
		altKey: false,
	};

	it( 'opens on a plain click and on a modified click', () => {
		expect( shouldOpenModal( plain, true, false ) ).toBe( true );
		expect(
			shouldOpenModal( { ...plain, ctrlKey: true }, true, false )
		).toBe( true );
		expect(
			shouldOpenModal( { ...plain, shiftKey: true }, true, false )
		).toBe( true );
	} );

	it( 'does nothing when dialogs are not supported or the click was handled', () => {
		expect( shouldOpenModal( plain, false, false ) ).toBe( false );
		expect(
			shouldOpenModal( { ...plain, defaultPrevented: true }, true, false )
		).toBe( false );
	} );
} );

describe( 'supportsModal', () => {
	it( 'is false without HTMLDialogElement', () => {
		expect( supportsModal( {} ) ).toBe( false );
	} );

	it( 'is false when showModal is missing', () => {
		expect( supportsModal( { HTMLDialogElement: class {} } ) ).toBe(
			false
		);
	} );

	it( 'is true when showModal exists', () => {
		function Dialog() {}
		Dialog.prototype.showModal = () => {};
		expect( supportsModal( { HTMLDialogElement: Dialog } ) ).toBe( true );
	} );
} );

describe( 'isBackdropClick', () => {
	it( 'is true when the click landed on the dialog itself', () => {
		const dialog = {};
		expect( isBackdropClick( dialog, dialog ) ).toBe( true );
	} );

	it( 'is false when the click landed inside the dialog', () => {
		expect( isBackdropClick( {}, {} ) ).toBe( false );
	} );

	it( 'is false without a dialog', () => {
		expect( isBackdropClick( null, null ) ).toBe( false );
	} );
} );

describe( 'isBackdropDismiss', () => {
	const dialog = {};
	const inside = {};

	it( 'closes when the press and the click were both on the backdrop', () => {
		expect( isBackdropDismiss( dialog, dialog, dialog ) ).toBe( true );
	} );

	it( 'stays open when a drag starts inside and ends on the backdrop', () => {
		expect( isBackdropDismiss( inside, dialog, dialog ) ).toBe( false );
	} );

	it( 'stays open when a press on the backdrop ends inside', () => {
		expect( isBackdropDismiss( dialog, inside, dialog ) ).toBe( false );
	} );

	it( 'stays open when no press was seen', () => {
		expect( isBackdropDismiss( null, dialog, dialog ) ).toBe( false );
	} );
} );
