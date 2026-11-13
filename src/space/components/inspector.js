import { __ } from '@wordpress/i18n';
import { InspectorControls } from '@wordpress/block-editor';

import AccountControl from './inspector/account-control';
import CardPanel from './inspector/card-panel';
import ClickPanel from './inspector/click-panel';
import DisplayPanel from './inspector/display-panel';
import MinHeightItem from './inspector/min-height-item';
import Panel from './inspector/panel';
import TextItem from './inspector/text-item';
import ToggleItem from './inspector/toggle-item';
import { getEffectiveDisplay, getPrivateCta } from '../utils/display';
import { frameUrl } from '../utils/url';

/**
 * Whether a start path was typed but can't be used, so the Space opens at its
 * home page instead.
 *
 * @param {Object} attributes           Block attributes.
 * @param {string} attributes.url       Space URL.
 * @param {string} attributes.startPath Start path.
 * @return {boolean} True if the path will be ignored.
 */
function isStartPathIgnored( { url, startPath } ) {
	return (
		startPath.replace( /^\//, '' ) !== '' &&
		frameUrl( url, startPath ) === frameUrl( url, '' )
	);
}

/**
 * The block sidebar. Panels for other display modes stay hidden.
 *
 * @param {Object}                 props               Component props.
 * @param {Object}                 props.attributes    Block attributes.
 * @param {(next: Object) => void} props.setAttributes Updates block attributes.
 * @param {string}                 props.clientId      Block client ID.
 * @param {() => void}             props.onResetImage  Puts the Space's own card image back.
 * @param {boolean}                props.isRefreshing  Whether the Space's details are being fetched.
 */
export default function SpaceInspector( {
	attributes,
	setAttributes,
	clientId,
	onResetImage,
	isRefreshing,
} ) {
	const display = getEffectiveDisplay( attributes );
	const itemProps = { attributes, setAttributes };

	return (
		<>
			<InspectorControls>
				<DisplayPanel { ...itemProps } />
				{ display === 'inline' && (
					<Panel
						label={ __( 'Inline', 'spacefast-block' ) }
						resetKeys={ [ 'showTitleBar' ] }
						setAttributes={ setAttributes }
					>
						<ToggleItem
							attribute="showTitleBar"
							label={ __( 'Show title bar', 'spacefast-block' ) }
							{ ...itemProps }
						/>
					</Panel>
				) }
				{ display === 'fullpage' && (
					<Panel
						label={ __( 'Full page', 'spacefast-block' ) }
						resetKeys={ [ 'hideHeader', 'hideFooter' ] }
						setAttributes={ setAttributes }
					>
						<ToggleItem
							attribute="hideHeader"
							label={ __( 'Hide header', 'spacefast-block' ) }
							{ ...itemProps }
						/>
						<ToggleItem
							attribute="hideFooter"
							label={ __( 'Hide footer', 'spacefast-block' ) }
							{ ...itemProps }
						/>
					</Panel>
				) }
				{ display === 'card' && (
					<CardPanel
						{ ...itemProps }
						onResetImage={ onResetImage }
						isRefreshing={ isRefreshing }
					/>
				) }
				{ ( display === 'card' || display === 'link' ) && (
					<ClickPanel { ...itemProps } />
				) }
				{ attributes.isPrivate && (
					<Panel
						label={ __( 'Private Space', 'spacefast-block' ) }
						resetKeys={ [
							'showPrivateBadge',
							'privateLabel',
							'privateCta',
						] }
						setAttributes={ setAttributes }
					>
						<ToggleItem
							attribute="showPrivateBadge"
							label={ __( 'Show badge', 'spacefast-block' ) }
							{ ...itemProps }
						/>
						{ attributes.showPrivateBadge && (
							<TextItem
								attribute="privateLabel"
								label={ __( 'Badge text', 'spacefast-block' ) }
								placeholder={ __(
									'Private',
									'spacefast-block'
								) }
								{ ...itemProps }
							/>
						) }
						<TextItem
							attribute="privateCta"
							label={ __( 'Call to action', 'spacefast-block' ) }
							placeholder={ getPrivateCta( {} ) }
							{ ...itemProps }
						/>
					</Panel>
				) }
				<Panel
					label={ __( 'Settings', 'spacefast-block' ) }
					resetKeys={ [
						'startPath',
						'showBranding',
						'lazyLoad',
						'allowFullscreen',
					] }
					setAttributes={ setAttributes }
				>
					<TextItem
						attribute="startPath"
						label={ __( 'Start path', 'spacefast-block' ) }
						placeholder="/"
						help={
							isStartPathIgnored( attributes )
								? __(
										"This path can't be used, so the Space opens at its home page.",
										'spacefast-block'
									)
								: __(
										'The page inside the Space to open first, like /docs.',
										'spacefast-block'
									)
						}
						{ ...itemProps }
					/>
					<ToggleItem
						attribute="showBranding"
						label={ __(
							'Show Spacefast branding',
							'spacefast-block'
						) }
						{ ...itemProps }
					/>
					<ToggleItem
						attribute="lazyLoad"
						label={ __( 'Load when visible', 'spacefast-block' ) }
						help={ __(
							'Waits to load the Space until a visitor scrolls near it.',
							'spacefast-block'
						) }
						{ ...itemProps }
					/>
					<ToggleItem
						attribute="allowFullscreen"
						label={ __( 'Allow full screen', 'spacefast-block' ) }
						{ ...itemProps }
					/>
				</Panel>
			</InspectorControls>
			{ display === 'inline' && (
				<InspectorControls group="dimensions">
					<MinHeightItem { ...itemProps } clientId={ clientId } />
				</InspectorControls>
			) }
			<InspectorControls group="advanced">
				<AccountControl />
			</InspectorControls>
		</>
	);
}
