import { __ } from '@wordpress/i18n';
import { useRef, useState } from '@wordpress/element';
import {
	Button,
	Flex,
	Notice,
	Placeholder,
	TextControl,
} from '@wordpress/components';

import icon from '../icon';
import SpacePickerModal from './space-picker-modal';
import { getErrorMessage, resolveSpace } from '../utils/api';
import { looksLikeUrl } from '../utils/url';

/**
 * What an editor sees before a Space is chosen: a link field and a picker.
 *
 * @param {Object}                    props         Component props.
 * @param {(details: Object) => void} props.onApply Called with the details of the chosen Space.
 */
export default function SpacePlaceholder( { onApply } ) {
	const [ link, setLink ] = useState( '' );
	const [ error, setError ] = useState( '' );
	const [ isResolving, setIsResolving ] = useState( false );
	const [ isPickerOpen, setIsPickerOpen ] = useState( false );
	const latestRequest = useRef( 0 );

	const resolve = async ( value ) => {
		const text = value.trim();
		if ( ! looksLikeUrl( text ) ) {
			setError(
				__(
					'Enter a link that starts with https:// and points to a public site.',
					'spacefast-block'
				)
			);
			return;
		}

		const requestId = ++latestRequest.current;
		setError( '' );
		setIsResolving( true );
		try {
			const details = await resolveSpace( text );
			if ( requestId === latestRequest.current ) {
				onApply( details );
			}
		} catch ( resolveError ) {
			if ( requestId === latestRequest.current ) {
				setError( getErrorMessage( resolveError ) );
				setIsResolving( false );
			}
		}
	};

	return (
		<Placeholder
			icon={ icon }
			label={ __( 'Spacefast', 'spacefast-block' ) }
			instructions={ __(
				'Paste a link to a Space, or choose one from your account.',
				'spacefast-block'
			) }
		>
			<form
				onSubmit={ ( event ) => {
					event.preventDefault();
					resolve( link );
				} }
			>
				<Flex direction="column" gap={ 4 }>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Space link', 'spacefast-block' ) }
						type="text"
						inputMode="url"
						value={ link }
						onChange={ ( value ) => {
							setLink( value );
							setError( '' );
						} }
						onPaste={ ( event ) => {
							const pasted =
								event.clipboardData.getData( 'text' );
							if ( looksLikeUrl( pasted ) ) {
								event.preventDefault();
								setLink( pasted.trim() );
								resolve( pasted );
							}
						} }
					/>
					{ error && (
						<Notice status="error" isDismissible={ false }>
							{ error }
						</Notice>
					) }
					<Flex justify="flex-start" gap={ 2 }>
						<Button
							variant="primary"
							type="submit"
							__next40pxDefaultSize
							isBusy={ isResolving }
							disabled={ isResolving }
							accessibleWhenDisabled
						>
							{ __( 'Show Space', 'spacefast-block' ) }
						</Button>
						<Button
							variant="secondary"
							__next40pxDefaultSize
							onClick={ () => setIsPickerOpen( true ) }
						>
							{ __( 'Choose a Space', 'spacefast-block' ) }
						</Button>
					</Flex>
				</Flex>
			</form>
			{ isPickerOpen && (
				<SpacePickerModal
					onSelect={ onApply }
					onRequestClose={ () => setIsPickerOpen( false ) }
				/>
			) }
		</Placeholder>
	);
}
