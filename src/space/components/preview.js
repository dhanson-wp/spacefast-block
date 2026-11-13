import InlinePreview from './preview/inline';
import FullPagePreview from './preview/full-page';
import CardPreview from './preview/card';
import LinkPreview from './preview/link';
import { getEffectiveDisplay } from '../utils/display';

const PREVIEWS = {
	inline: InlinePreview,
	fullpage: FullPagePreview,
	card: CardPreview,
	link: LinkPreview,
};

/**
 * Shows the chosen Space in its display mode, inside the block wrapper.
 *
 * @param {Object}                       props                 Component props.
 * @param {Object}                       props.attributes      Block attributes.
 * @param {(next: Object) => void}       props.setAttributes   Updates block attributes.
 * @param {boolean}                      props.isSelected      Whether the block is selected.
 * @param {(isEnabled: boolean) => void} props.toggleSelection Turns block selection on or off.
 * @param {boolean}                      props.isHostVerified  Whether the Space's host may be framed.
 */
export default function SpacePreview( { isHostVerified, ...props } ) {
	const Preview =
		PREVIEWS[ getEffectiveDisplay( props.attributes, isHostVerified ) ] ||
		InlinePreview;
	return <Preview { ...props } />;
}
