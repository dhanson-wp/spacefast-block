import { Icon, external } from '@wordpress/icons';

/**
 * The icon after a link that leaves the site for the Space: core's `external`
 * icon, as big as the text and in its color. The front end prints the same
 * icon in includes/render-helpers.php.
 */
export default function ExternalIcon() {
	return (
		<Icon
			className="wp-block-spacefast-space__external-icon"
			icon={ external }
			size={ 16 }
			aria-hidden="true"
		/>
	);
}
