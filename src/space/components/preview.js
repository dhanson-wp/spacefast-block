import InlinePreview from './preview/inline';
import FullPagePreview from './preview/full-page';
import CardPreview from './preview/card';
import LinkPreview from './preview/link';
import { getPreviewDisplay } from '../utils/display';

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
 * @param {boolean}                      props.isOnlyBlock     Whether this is the only block on the page; a full page block that isn't previews as inline.
 * @param {Object}                       props.supportProps    Color and font size classes and styles, for the link mode's button.
 */
export default function SpacePreview( {
	isHostVerified,
	isOnlyBlock,
	...props
} ) {
	const Preview =
		PREVIEWS[
			getPreviewDisplay( props.attributes, isHostVerified, isOnlyBlock )
		] || InlinePreview;
	return <Preview { ...props } isHostVerified={ isHostVerified } />;
}
