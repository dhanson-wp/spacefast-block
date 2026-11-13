import { __, _n, sprintf } from '@wordpress/i18n';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { useDebounce } from '@wordpress/compose';
import { speak } from '@wordpress/a11y';
import {
	Button,
	Flex,
	Modal,
	Notice,
	SearchControl,
	Spinner,
} from '@wordpress/components';

import Connect from './connect';
import { getErrorMessage, resolveSpace, searchSpaces } from '../utils/api';

/**
 * Modal for choosing one of the connected account's Spaces.
 *
 * @param {Object}                    props                Component props.
 * @param {(details: Object) => void} props.onSelect       Called with the details of the chosen Space.
 * @param {() => void}                props.onRequestClose Called to close the modal.
 */
export default function SpacePickerModal( { onSelect, onRequestClose } ) {
	const [ isConnected, setIsConnected ] = useState( null );
	const [ search, setSearch ] = useState( '' );
	const [ query, setQuery ] = useState( '' );
	const [ spaces, setSpaces ] = useState( [] );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ pickingId, setPickingId ] = useState( '' );
	const [ error, setError ] = useState( '' );

	const updateQuery = useDebounce( setQuery, 300 );
	const onConnected = useCallback( () => setIsConnected( true ), [] );

	useEffect( () => {
		if ( isConnected === false ) {
			return undefined;
		}

		let isActive = true;
		setIsLoading( true );
		setError( '' );
		speak( __( 'Loading Spaces…', 'spacefast-block' ) );

		searchSpaces( query )
			.then( ( response ) => {
				if ( ! isActive ) {
					return;
				}
				setIsConnected( response.connected );
				setSpaces( response.spaces );
				setIsLoading( false );
				if ( ! response.connected ) {
					return;
				}
				speak(
					response.spaces.length
						? sprintf(
								/* translators: %d: number of Spaces found. */
								_n(
									'%d Space found.',
									'%d Spaces found.',
									response.spaces.length,
									'spacefast-block'
								),
								response.spaces.length
							)
						: __( 'No Spaces found.', 'spacefast-block' )
				);
			} )
			.catch( ( searchError ) => {
				if ( ! isActive ) {
					return;
				}
				setSpaces( [] );
				setError( getErrorMessage( searchError ) );
				setIsLoading( false );
			} );

		return () => {
			isActive = false;
		};
	}, [ query, isConnected ] );

	const pick = async ( space ) => {
		setError( '' );
		setPickingId( space.id );
		try {
			const details = await resolveSpace( space.url, space.id );
			onSelect( {
				...details,
				// A private Space's page can't be read, but the account knows its title.
				title: details.title || space.title,
				hostname: details.hostname || space.hostname,
			} );
			onRequestClose();
		} catch ( pickError ) {
			setError( getErrorMessage( pickError ) );
			setPickingId( '' );
		}
	};

	let content;
	if ( isConnected === false ) {
		content = <Connect onConnected={ onConnected } />;
	} else {
		content = (
			<Flex direction="column" gap={ 4 }>
				<SearchControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __( 'Search your Spaces', 'spacefast-block' ) }
					value={ search }
					onChange={ ( value ) => {
						setSearch( value );
						updateQuery( value );
					} }
				/>
				{ error && (
					<Notice status="error" isDismissible={ false }>
						{ error }
					</Notice>
				) }
				{ isLoading && <Spinner /> }
				{ ! isLoading && ! error && spaces.length === 0 && (
					<p>{ __( 'No Spaces found.', 'spacefast-block' ) }</p>
				) }
				{ ! isLoading && spaces.length > 0 && (
					<ul className="spacefast-space-results">
						{ spaces.map( ( space ) => (
							<li key={ space.id }>
								<Button
									__next40pxDefaultSize
									variant="tertiary"
									isBusy={ pickingId === space.id }
									disabled={ pickingId !== '' }
									accessibleWhenDisabled
									onClick={ () => pick( space ) }
								>
									<span>
										<strong>{ space.title }</strong>
										<br />
										{ [ space.team, space.hostname ]
											.filter( Boolean )
											.join( ' · ' ) }
									</span>
								</Button>
							</li>
						) ) }
					</ul>
				) }
			</Flex>
		);
	}

	return (
		<Modal
			title={ __( 'Choose a Space', 'spacefast-block' ) }
			onRequestClose={ onRequestClose }
		>
			{ content }
		</Modal>
	);
}
