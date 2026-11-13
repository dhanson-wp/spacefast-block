import { __ } from '@wordpress/i18n';
import { useEffect, useRef, useState } from '@wordpress/element';
import { Button, ExternalLink, Flex, Notice } from '@wordpress/components';

import { getErrorMessage, pollConnection, startConnection } from '../utils/api';

export { disconnect } from '../utils/api';

// Polls that may fail in a row, from a dropped connection say, before the
// sign-in stops with the error.
const MAX_POLL_FAILURES = 3;

/**
 * Connects the editor's Spacefast account with the device sign-in flow.
 *
 * @param {Object}     props             Component props.
 * @param {() => void} props.onConnected Called once the account is connected.
 */
export default function Connect( { onConnected } ) {
	// The sign-in request returned by the server while we wait for approval.
	const [ request, setRequest ] = useState( null );
	const [ isStarting, setIsStarting ] = useState( false );
	const [ error, setError ] = useState( '' );

	// Kept in a ref so a new callback from the parent doesn't restart polling.
	const onConnectedRef = useRef( onConnected );
	useEffect( () => {
		onConnectedRef.current = onConnected;
	}, [ onConnected ] );

	useEffect( () => {
		if ( ! request ) {
			return undefined;
		}

		let isActive = true;
		let timer;
		let failures = 0;
		const delay = Math.max( 1, Number( request.interval ) || 1 ) * 1000;
		const expiresAt = Date.parse( request.expiresAt );

		const stop = ( message ) => {
			setError( message );
			setRequest( null );
		};

		const poll = async () => {
			if ( ! Number.isNaN( expiresAt ) && Date.now() >= expiresAt ) {
				stop(
					__(
						'That sign-in request expired. Try again.',
						'spacefast-block'
					)
				);
				return;
			}

			let status;
			try {
				( { status } = await pollConnection() );
			} catch ( pollError ) {
				if ( ! isActive ) {
					return;
				}
				failures += 1;
				if ( failures >= MAX_POLL_FAILURES ) {
					stop( getErrorMessage( pollError ) );
				} else {
					timer = setTimeout( poll, delay );
				}
				return;
			}
			if ( ! isActive ) {
				return;
			}
			failures = 0;

			if ( status === 'connected' ) {
				setRequest( null );
				onConnectedRef.current();
			} else if ( status === 'denied' ) {
				stop( __( 'The request was declined.', 'spacefast-block' ) );
			} else if ( status === 'pending' ) {
				timer = setTimeout( poll, delay );
			} else {
				stop(
					__(
						'That sign-in request expired. Try again.',
						'spacefast-block'
					)
				);
			}
		};

		timer = setTimeout( poll, delay );

		return () => {
			isActive = false;
			clearTimeout( timer );
		};
	}, [ request ] );

	const start = async () => {
		setError( '' );
		setIsStarting( true );
		let started;
		try {
			started = await startConnection();
		} catch ( startError ) {
			setError( getErrorMessage( startError ) );
			setIsStarting( false );
			return;
		}
		setIsStarting( false );
		setRequest( started );
		// If the browser blocks the new tab, the visible link still works.
		window.open( started.verificationUrl, '_blank', 'noopener' );
	};

	if ( request ) {
		return (
			<Flex direction="column" gap={ 4 }>
				<p>
					{ __(
						'Approve the request in the tab that just opened, and check that it shows this code.',
						'spacefast-block'
					) }
				</p>
				<p>
					<strong>{ request.userCode }</strong>
				</p>
				<p>
					<ExternalLink href={ request.verificationUrl }>
						{ __( 'Open Spacefast', 'spacefast-block' ) }
					</ExternalLink>
				</p>
				<p role="status">
					{ __( 'Waiting for approval…', 'spacefast-block' ) }
				</p>
				<div>
					<Button
						variant="secondary"
						__next40pxDefaultSize
						onClick={ () => setRequest( null ) }
					>
						{ __( 'Cancel', 'spacefast-block' ) }
					</Button>
				</div>
			</Flex>
		);
	}

	return (
		<Flex direction="column" gap={ 4 }>
			<p>
				{ __(
					'Connect your Spacefast account to choose from your Spaces.',
					'spacefast-block'
				) }
			</p>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			<div>
				<Button
					variant="primary"
					__next40pxDefaultSize
					isBusy={ isStarting }
					disabled={ isStarting }
					accessibleWhenDisabled
					onClick={ start }
				>
					{ __( 'Connect to Spacefast', 'spacefast-block' ) }
				</Button>
			</div>
		</Flex>
	);
}
