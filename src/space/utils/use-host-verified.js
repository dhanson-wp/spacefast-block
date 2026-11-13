import { useCallback, useEffect, useState } from '@wordpress/element';

import { checkVerifiedHost } from './api';
import { frameUrl, isSpacefastHost } from './url';

// What the server said about each Space origin during this editor session.
const answers = new Map();

/**
 * Whether the server lets the block's Space be framed: its host is on
 * space.fast, or the resolve route has confirmed it as a Space. The editor
 * frames nothing until the answer is known.
 *
 * @param {string} url Space URL.
 * @return {{isHostVerified: (boolean|undefined), setHostVerified: (spaceUrl: string, isVerified: boolean) => void}}
 *         `isHostVerified` is undefined while the server is being asked.
 *         `setHostVerified` records an answer that came with Space details.
 */
export default function useHostVerified( url ) {
	const origin = frameUrl( url, '' );
	const [ , setAnswerCount ] = useState( 0 );

	let isHostVerified = false;
	if ( origin ) {
		isHostVerified = isSpacefastHost( origin ) || answers.get( origin );
	}

	const setHostVerified = useCallback( ( spaceUrl, isVerified ) => {
		const spaceOrigin = frameUrl( spaceUrl, '' );
		if ( spaceOrigin ) {
			answers.set( spaceOrigin, !! isVerified );
			setAnswerCount( ( count ) => count + 1 );
		}
	}, [] );

	useEffect( () => {
		if ( ! origin || isHostVerified !== undefined ) {
			return undefined;
		}
		let isActive = true;
		checkVerifiedHost( origin )
			.then( ( { verified } ) => answers.set( origin, !! verified ) )
			.catch( () => answers.set( origin, false ) )
			.finally( () => {
				if ( isActive ) {
					setAnswerCount( ( count ) => count + 1 );
				}
			} );
		return () => {
			isActive = false;
		};
	}, [ origin, isHostVerified ] );

	return { isHostVerified, setHostVerified };
}
