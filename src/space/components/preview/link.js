import { __ } from '@wordpress/i18n';
import { RichText } from '@wordpress/block-editor';

import Branding from '../branding';
import ExternalIcon from '../external-icon';
import PrivateBadge from '../private-badge';
import { getLinkFallback, hasModalTrigger } from '../../utils/display';

/**
 * The link mode: a theme-styled button whose text is edited in place, as the
 * core Button block does. The fallback text shows until the editor types one.
 *
 * @param {Object}                 props                Component props.
 * @param {Object}                 props.attributes     Block attributes.
 * @param {(next: Object) => void} props.setAttributes  Updates block attributes.
 * @param {Object}                 props.supportProps   Color and font size classes and styles for the button.
 * @param {boolean}                props.isHostVerified Whether the Space's host may be framed.
 */
export default function LinkPreview( {
	attributes,
	setAttributes,
	supportProps,
	isHostVerified,
} ) {
	return (
		<>
			{ attributes.isPrivate && attributes.showPrivateBadge && (
				<PrivateBadge attributes={ attributes } />
			) }
			<div className="wp-block-button">
				<div
					className={ [
						'wp-block-button__link wp-element-button wp-block-spacefast-space__link',
						supportProps?.className,
					]
						.filter( Boolean )
						.join( ' ' ) }
					style={ supportProps?.style }
				>
					<RichText
						identifier="linkLabel"
						tagName="span"
						aria-label={ __( 'Link text', 'spacefast-block' ) }
						placeholder={ getLinkFallback( attributes ) }
						value={ attributes.linkLabel }
						onChange={ ( linkLabel ) =>
							setAttributes( { linkLabel } )
						}
						allowedFormats={ [] }
						disableLineBreaks
						withoutInteractiveFormatting
					/>
					{ ! hasModalTrigger( attributes, isHostVerified ) && (
						<ExternalIcon />
					) }
				</div>
			</div>
			{ attributes.showBranding && <Branding /> }
		</>
	);
}
