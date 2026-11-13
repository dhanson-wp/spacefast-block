import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { BaseControl, Button } from '@wordpress/components';
import { store as noticesStore } from '@wordpress/notices';

import { disconnect } from '../connect';
import { getConnection, getErrorMessage } from '../../utils/api';

/**
 * Shows whether the editor's Spacefast account is connected, with a button to
 * disconnect it. Lives in the block's Advanced panel.
 */
export default function AccountControl() {
	// null until the server answers.
	const [ isConnected, setIsConnected ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ isDisconnecting, setIsDisconnecting ] = useState( false );
	const { createSuccessNotice, createErrorNotice } =
		useDispatch( noticesStore );

	useEffect( () => {
		let isActive = true;
		getConnection()
			.then( ( response ) => {
				if ( isActive ) {
					setIsConnected( !! response.connected );
				}
			} )
			.catch( ( connectionError ) => {
				if ( isActive ) {
					setError( getErrorMessage( connectionError ) );
				}
			} );
		return () => {
			isActive = false;
		};
	}, [] );

	const onDisconnect = async () => {
		setIsDisconnecting( true );
		try {
			await disconnect();
			setIsConnected( false );
			createSuccessNotice(
				__( 'Disconnected from Spacefast.', 'spacefast-block' ),
				{ type: 'snackbar' }
			);
		} catch ( disconnectError ) {
			createErrorNotice( getErrorMessage( disconnectError ), {
				type: 'snackbar',
			} );
		}
		setIsDisconnecting( false );
	};

	let status = __( 'Checking your Spacefast account…', 'spacefast-block' );
	if ( error ) {
		status = error;
	} else if ( isConnected ) {
		status = __(
			'Your Spacefast account is connected.',
			'spacefast-block'
		);
	} else if ( isConnected === false ) {
		status = __(
			'Your Spacefast account is not connected.',
			'spacefast-block'
		);
	}

	return (
		<BaseControl __nextHasNoMarginBottom>
			<BaseControl.VisualLabel>
				{ __( 'Spacefast account', 'spacefast-block' ) }
			</BaseControl.VisualLabel>
			<p role="status">{ status }</p>
			{ isConnected && (
				<Button
					variant="secondary"
					isDestructive
					__next40pxDefaultSize
					isBusy={ isDisconnecting }
					disabled={ isDisconnecting }
					accessibleWhenDisabled
					onClick={ onDisconnect }
				>
					{ __( 'Disconnect', 'spacefast-block' ) }
				</Button>
			) }
		</BaseControl>
	);
}
