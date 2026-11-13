<?php
/**
 * Front-end markup for the Spacefast block.
 *
 * These functions build the same structure and class names as the editor
 * preview in src/space/components/preview, so both look alike. Every value is
 * escaped where it is printed. The inline, full-page, and modal markup
 * share spacefast_block_frame_html().
 *
 * @package Spacefast_Block
 */

use Spacefast_Block\Relay_Controller;
use Spacefast_Block\Space_Display;
use Spacefast_Block\Space_Relay;
use Spacefast_Block\Space_Url;
use Spacefast_Block\Takeover;
use Spacefast_Block\Verified_Hosts;

defined( 'ABSPATH' ) || exit;

/**
 * The sandbox of a frame that loads the Space directly. src/space/view.js puts
 * it back when a relayed full-page frame falls back to the Space.
 */
const SPACEFAST_BLOCK_FRAME_SANDBOX = 'allow-scripts allow-same-origin allow-forms allow-popups allow-downloads';

/**
 * The Spacefast mark, matching src/space/icon.js. The path data lives only here.
 *
 * @return string SVG markup.
 */
function spacefast_block_branding_svg(): string {
	$path = 'M144.237 162.275L78.975 156.525C50.225 153.938 37 144.45 37 115.7L37 92.125C37 63.0875 51.375 49 80.125 49L200.875 49C229.625 49 244 63.375 244 92.125V109.375H157.75V97.875L123.25 97.875V99.025C123.25 110.525 124.687 111.388 136.188 112.25L199.15 117.713C227.9 120.013 244 132.663 244 161.413V188.438C244 217.188 229.625 231.563 200.875 231.563L80.125 231.563C51.0875 231.563 37 217.188 37 188.438V165.438H123.25L123.25 182.688L157.75 182.688L157.75 176.075C157.75 164.575 155.738 163.138 144.237 162.275Z';

	return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1024 1024" width="16" height="16" aria-hidden="true" focusable="false">'
		. '<rect width="1024" height="1024" fill="#1EFE57"/>'
		. '<path fill="#000000" transform="translate(21.6902 22.4527) scale(3.48975)" d="' . esc_attr( $path ) . '"/>'
		. '</svg>';
}

/**
 * The branding mark: the icon and the word "Spacefast".
 *
 * @return string HTML.
 */
function spacefast_block_branding_html(): string {
	return '<span class="wp-block-spacefast-space__branding">'
		. spacefast_block_branding_svg()
		. esc_html__( 'Spacefast', 'spacefast-block' )
		. '</span>';
}

/**
 * The private badge: a lock icon (the `lock` icon from @wordpress/icons) and a label.
 *
 * @param array $attributes Block attributes.
 * @return string HTML.
 */
function spacefast_block_private_badge_html( array $attributes ): string {
	$path  = 'M9.25 10.75H7.75C7.47386 10.75 7.25 10.9739 7.25 11.25V17.25C7.25 17.5261 7.47386 17.75 7.75 17.75H16.25C16.5261 17.75 16.75 17.5261 16.75 17.25V11.25C16.75 10.9739 16.5261 10.75 16.25 10.75H14.75M9.25 10.75V7.5C9.25 5.98122 10.4812 4.75 12 4.75C13.5188 4.75 14.75 5.98122 14.75 7.5V10.75M9.25 10.75H14.75';
	$label = Space_Display::private_label( $attributes, __( 'Private', 'spacefast-block' ) );

	return '<span class="wp-block-spacefast-space__badge">'
		. '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" style="fill:none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" focusable="false">'
		. '<path d="' . esc_attr( $path ) . '"/></svg>'
		. esc_html( $label )
		. '</span>';
}

/**
 * The classes and styles for the block's color and font size supports. The
 * block skips their serialization on its wrapper, so the link mode can put
 * them on its button, as the core Button block does; every other mode puts
 * them back on the wrapper. Core's own support functions build them, from a
 * block type that serializes them. Mirrors
 * src/space/utils/use-color-and-font-size-props.js.
 *
 * @param array $attributes Block attributes.
 * @return array{class: string, style: string}
 */
function spacefast_block_color_and_font_size( array $attributes ): array {
	static $block_type = null;
	if ( null === $block_type ) {
		$block_type = new WP_Block_Type(
			'spacefast/space-text-supports',
			array(
				'supports' => array(
					'color'      => array(
						'text'       => true,
						'background' => true,
					),
					'typography' => array( 'fontSize' => true ),
				),
			)
		);
	}

	$classes = array();
	$styles  = array();
	foreach ( array( wp_apply_colors_support( $block_type, $attributes ), wp_apply_typography_support( $block_type, $attributes ) ) as $support ) {
		if ( ! empty( $support['class'] ) ) {
			$classes[] = $support['class'];
		}
		if ( ! empty( $support['style'] ) ) {
			$styles[] = rtrim( $support['style'], ';' ) . ';';
		}
	}
	return array(
		'class' => implode( ' ', $classes ),
		'style' => implode( '', $styles ),
	);
}

/**
 * Visually hidden "(opens in a new tab)" text for a link that opens a new tab,
 * as core blocks print it.
 *
 * @return string HTML.
 */
function spacefast_block_new_tab_text(): string {
	return '<span class="wp-block-spacefast-space__visually-hidden"> '
		. esc_html__( '(opens in a new tab)', 'spacefast-block' )
		. '</span>';
}

/**
 * Whether the block opens its Space in a modal: the click action asks for one
 * and the Space can be framed. Only the card and link modes have a trigger.
 *
 * @param array $attributes         Block attributes.
 * @param bool  $host_may_be_framed Whether the Space's host may be framed.
 * @return bool
 */
function spacefast_block_has_modal( array $attributes, bool $host_may_be_framed ): bool {
	return 'modal' === ( $attributes['clickAction'] ?? 'modal' )
		&& Space_Display::can_frame( $attributes, $host_may_be_framed )
		&& in_array( Space_Display::effective_display( $attributes, $host_may_be_framed ), array( 'card', 'link' ), true );
}

/**
 * Attributes for a card or link anchor: a plain link to the Space. It opens in a
 * new tab only when asked, and carries the hook the modal script listens for,
 * and says it opens a dialog, only when a modal can show the Space.
 *
 * @param array  $attributes Block attributes.
 * @param string $href       Link target.
 * @param bool   $has_modal  Whether a modal shows the Space.
 * @return string Attribute string with a leading space.
 */
function spacefast_block_trigger_attributes( array $attributes, string $href, bool $has_modal ): string {
	$html = ' href="' . esc_url( $href ) . '"';

	if ( ! empty( $attributes['openInNewTab'] ) ) {
		$html .= ' target="_blank" rel="noopener"';
	}
	if ( $has_modal ) {
		$html .= ' aria-haspopup="dialog" data-wp-on--click="actions.open"';
	}
	return $html;
}

/**
 * The card's image, or '' when it shows none. A media library image is printed
 * with WordPress's own image markup; otherwise the Space's preview image is
 * used, unless the Space is private.
 *
 * @param array $attributes Block attributes.
 * @return string HTML.
 */
function spacefast_block_card_image_html( array $attributes ): string {
	$source = Space_Display::card_image_source( $attributes );

	if ( 'attachment' === $source ) {
		$html = wp_get_attachment_image(
			(int) $attributes['imageId'],
			'large',
			false,
			array(
				'class' => 'wp-block-spacefast-space__image',
				'alt'   => '',
			)
		);
		if ( '' !== $html ) {
			return $html;
		}
		// The library image is gone; fall back to the Space's own image if visitors can load it.
		$source = ! empty( $attributes['isPrivate'] ) || empty( $attributes['imageUrl'] ) ? 'none' : 'url';
	}

	if ( 'url' !== $source ) {
		return '';
	}
	$url = esc_url( (string) $attributes['imageUrl'], array( 'http', 'https' ) );
	if ( '' === $url ) {
		return '';
	}
	return '<img class="wp-block-spacefast-space__image" src="' . $url . '" alt="" loading="lazy">';
}

/**
 * The card: one link holding the image, title, description, hostname, private
 * badge and call to action, and the branding mark.
 *
 * @param array  $attributes Block attributes.
 * @param string $href       Link target.
 * @param string $image_html The card's image, from spacefast_block_card_image_html().
 * @param bool   $has_modal  Whether a modal shows the Space.
 * @return string HTML.
 */
function spacefast_block_card_html( array $attributes, string $href, string $image_html, bool $has_modal ): string {
	$is_private = ! empty( $attributes['isPrivate'] );
	$show       = static function ( string $key ) use ( $attributes ): bool {
		return (bool) ( $attributes[ $key ] ?? true );
	};

	$html  = '<a class="wp-block-spacefast-space__card"' . spacefast_block_trigger_attributes( $attributes, $href, $has_modal ) . '>';
	$html .= $image_html;
	$html .= '<span class="wp-block-spacefast-space__content">';
	if ( $is_private && $show( 'showPrivateBadge' ) ) {
		$html .= spacefast_block_private_badge_html( $attributes );
	}
	$html .= '<span class="wp-block-spacefast-space__title">' . esc_html( Space_Display::card_title( $attributes ) ) . '</span>';
	if ( $show( 'showDescription' ) && ! empty( $attributes['description'] ) ) {
		$html .= '<span class="wp-block-spacefast-space__description">' . esc_html( $attributes['description'] ) . '</span>';
	}
	if ( $show( 'showHostname' ) && ! empty( $attributes['hostname'] ) ) {
		$html .= '<span class="wp-block-spacefast-space__hostname">' . esc_html( $attributes['hostname'] ) . '</span>';
	}
	if ( $is_private ) {
		$html .= '<span class="wp-block-spacefast-space__cta">'
			. esc_html( Space_Display::private_cta( $attributes, __( 'Request access', 'spacefast-block' ) ) )
			. '</span>';
	}
	if ( $show( 'showBranding' ) ) {
		$html .= spacefast_block_branding_html();
	}
	$html .= '</span>';
	if ( ! empty( $attributes['openInNewTab'] ) ) {
		$html .= spacefast_block_new_tab_text();
	}
	$html .= '</a>';

	return $html;
}

/**
 * The link mode: a theme button, with the private badge and branding mark beside it.
 *
 * @param array  $attributes Block attributes.
 * @param string $href       Link target.
 * @param bool   $has_modal  Whether a modal shows the Space.
 * @return string HTML.
 */
function spacefast_block_link_html( array $attributes, string $href, bool $has_modal ): string {
	// The link is a theme button. Core loads its button styles on demand, so ask for
	// them here; theme `elements.button` styles still apply on top.
	if ( wp_style_is( 'wp-block-button', 'registered' ) ) {
		wp_enqueue_style( 'wp-block-button' );
	}

	$html = '';
	if ( ! empty( $attributes['isPrivate'] ) && ( $attributes['showPrivateBadge'] ?? true ) ) {
		$html .= spacefast_block_private_badge_html( $attributes );
	}

	$supports = spacefast_block_color_and_font_size( $attributes );
	$button   = 'wp-block-button__link wp-element-button wp-block-spacefast-space__link';
	if ( '' !== $supports['class'] ) {
		$button .= ' ' . $supports['class'];
	}
	$style = '' !== $supports['style'] ? ' style="' . esc_attr( $supports['style'] ) . '"' : '';

	$label = Space_Display::link_label(
		$attributes,
		__( 'Open Space', 'spacefast-block' ),
		__( 'Request access', 'spacefast-block' )
	);
	$html .= '<div class="wp-block-button">'
		. '<a class="' . esc_attr( $button ) . '"' . $style . spacefast_block_trigger_attributes( $attributes, $href, $has_modal ) . '>'
		. esc_html( $label )
		. ( ! empty( $attributes['openInNewTab'] ) ? spacefast_block_new_tab_text() : '' )
		. '</a></div>';

	if ( $attributes['showBranding'] ?? true ) {
		$html .= spacefast_block_branding_html();
	}
	return $html;
}

/**
 * The Space in a frame.
 *
 * Supported `$args`:
 * - `src`     (bool)        Print the `src` attribute. Default true; the modal passes false and sets it when it opens.
 * - `loading` (string|null) `lazy`, `eager`, or '' for no attribute. Default null: lazy when the block asks for it.
 * - `relay`   (string)      The relay URL to load instead of the Space. The frame then
 *                           doesn't scroll, has no fullscreen, and its sandbox never
 *                           includes `allow-same-origin`, since the relay is on this site.
 *
 * @param array $attributes Block attributes.
 * @param array $args       Options, described above.
 * @return string HTML, or '' when the Space's URL isn't usable.
 */
function spacefast_block_frame_html( array $attributes, array $args = array() ): string {
	$src = Space_Url::frame_url( (string) ( $attributes['url'] ?? '' ), (string) ( $attributes['startPath'] ?? '' ) );
	if ( '' === $src ) {
		return '';
	}

	$loading = $args['loading'] ?? null;
	if ( null === $loading ) {
		$loading = ( $attributes['lazyLoad'] ?? true ) ? 'lazy' : '';
	}
	if ( ! in_array( $loading, array( 'lazy', 'eager' ), true ) ) {
		$loading = '';
	}

	$title = ! empty( $attributes['title'] ) ? $attributes['title'] : __( 'Spacefast Space', 'spacefast-block' );

	$relay = (string) ( $args['relay'] ?? '' );

	$html = '<iframe class="wp-block-spacefast-space__frame"';
	if ( '' !== $relay ) {
		$html .= ' src="' . esc_url( $relay ) . '"';
	} elseif ( $args['src'] ?? true ) {
		$html .= ' src="' . esc_url( $src ) . '"';
	}
	$html .= ' title="' . esc_attr( $title ) . '"';
	if ( '' !== $loading ) {
		$html .= ' loading="' . esc_attr( $loading ) . '"';
	}
	if ( '' !== $relay ) {
		$html .= ' scrolling="no" referrerpolicy="strict-origin-when-cross-origin"'
			. ' sandbox="' . esc_attr( Space_Relay::SANDBOX ) . '"></iframe>';
		return $html;
	}
	if ( $attributes['allowFullscreen'] ?? true ) {
		$html .= ' allowfullscreen';
	}
	$html .= ' referrerpolicy="strict-origin-when-cross-origin"'
		. ' sandbox="' . esc_attr( SPACEFAST_BLOCK_FRAME_SANDBOX ) . '"></iframe>';

	return $html;
}

/**
 * The modal: a native dialog with a header (title, branding mark, optional
 * "Open in new tab" link, close button) and the Space's frame. The frame has no
 * `src`; the view script sets it from the block context on first open.
 *
 * @param array  $attributes Block attributes.
 * @param string $href       Link to the Space.
 * @return string HTML.
 */
function spacefast_block_modal_html( array $attributes, string $href ): string {
	$title_id   = wp_unique_id( 'spacefast-modal-title-' );
	$close_path = 'm13.06 12 6.47-6.47-1.06-1.06L12 10.94 5.53 4.47 4.47 5.53 10.94 12l-6.47 6.47 1.06 1.06L12 13.06l6.47 6.47 1.06-1.06L13.06 12Z';

	$html  = '<dialog class="wp-block-spacefast-space__modal" aria-labelledby="' . esc_attr( $title_id ) . '"'
		. ' data-wp-on--pointerdown="actions.notePress" data-wp-on--click="actions.closeOnBackdrop" data-wp-on--close="callbacks.onDialogClose">';
	$html .= '<div class="wp-block-spacefast-space__modal-header">';
	$html .= '<span class="wp-block-spacefast-space__modal-title" id="' . esc_attr( $title_id ) . '">'
		. esc_html( Space_Display::card_title( $attributes ) ) . '</span>';
	if ( $attributes['showBranding'] ?? true ) {
		$html .= spacefast_block_branding_html();
	}
	if ( $attributes['modalExternalLink'] ?? true ) {
		$html .= '<a class="wp-block-spacefast-space__modal-link" href="' . esc_url( $href ) . '" target="_blank" rel="noopener">'
			. esc_html__( 'Open in new tab', 'spacefast-block' )
			. spacefast_block_new_tab_text()
			. '</a>';
	}
	$html .= '<button type="button" class="wp-block-spacefast-space__modal-close" autofocus aria-label="' . esc_attr__( 'Close', 'spacefast-block' ) . '" data-wp-on--click="actions.close">'
		. '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="currentColor" aria-hidden="true" focusable="false">'
		. '<path d="' . esc_attr( $close_path ) . '"/></svg></button>';
	$html .= '</div>';
	$html .= '<div class="wp-block-spacefast-space__modal-body">'
		. spacefast_block_frame_html(
			$attributes,
			array(
				'src'     => false,
				'loading' => '',
			)
		)
		. '</div></dialog>';

	return $html;
}

/**
 * The inline mode: an optional title bar above the frame.
 *
 * @param array  $attributes Block attributes.
 * @param string $href       Link to the Space.
 * @param array  $frame_args Options for spacefast_block_frame_html().
 * @return string HTML.
 */
function spacefast_block_inline_html( array $attributes, string $href, array $frame_args = array() ): string {
	$html = '';
	if ( ! empty( $attributes['showTitleBar'] ) ) {
		$html .= '<div class="wp-block-spacefast-space__title-bar">'
			. '<span class="wp-block-spacefast-space__title">' . esc_html( Space_Display::card_title( $attributes ) ) . '</span>';
		if ( $attributes['showBranding'] ?? true ) {
			$html .= spacefast_block_branding_html();
		}
		$html .= '<a class="wp-block-spacefast-space__link" href="' . esc_url( $href ) . '" target="_blank" rel="noopener">'
			. esc_html__( 'Open in new tab', 'spacefast-block' )
			. spacefast_block_new_tab_text()
			. '</a></div>';
	}
	return $html . spacefast_block_frame_html( $attributes, $frame_args );
}

/**
 * The full-page takeover: the inline markup with the frame loaded straight
 * away, since it is the page. The theme's post title is hidden while the Space
 * takes over, so a visually hidden heading keeps the page's <h1>. When the
 * header or footer is hidden, a "Back to site" link, shown on focus, replaces
 * the site navigation it took away.
 *
 * When the Space is shown at its full length, the frame loads `$relay`, the
 * relay's copy of the page, instead of the Space.
 *
 * @param array  $attributes Block attributes.
 * @param string $href       Link to the Space.
 * @param string $relay      Relay URL for the frame, or '' to frame the Space.
 * @return string HTML.
 */
function spacefast_block_takeover_html( array $attributes, string $href, string $relay = '' ): string {
	$html = '';
	if ( ! empty( $attributes['hideHeader'] ) || ! empty( $attributes['hideFooter'] ) ) {
		$html .= '<a class="wp-block-spacefast-space__skip-link" href="' . esc_url( home_url( '/' ) ) . '">'
			. esc_html__( 'Back to site', 'spacefast-block' )
			. '</a>';
	}
	$html .= '<h1 class="wp-block-spacefast-space__visually-hidden">' . esc_html( Space_Display::card_title( $attributes ) ) . '</h1>';

	return $html . spacefast_block_inline_html(
		$attributes,
		$href,
		array(
			'loading' => 'eager',
			'relay'   => $relay,
		)
	);
}

/**
 * The whole block: the wrapper and the markup for its display mode.
 *
 * Only a host that may be framed (see Verified_Hosts) is put in a frame. Any
 * other host is treated like a Space that cannot be framed: a card or a plain
 * link, with no modal and no takeover.
 *
 * @param array $attributes Block attributes.
 * @return string HTML, or '' when there is no usable Space URL.
 */
function spacefast_block_render( array $attributes ): string {
	$href = Space_Url::frame_url( (string) ( $attributes['url'] ?? '' ), (string) ( $attributes['startPath'] ?? '' ) );
	if ( '' === $href ) {
		return '';
	}
	$host_may_be_framed = Verified_Hosts::may_frame( $href );

	if ( empty( $attributes['hostname'] ) ) {
		$attributes['hostname'] = Space_Url::hostname( $href );
	}

	$display     = Space_Display::effective_display( $attributes, $host_may_be_framed );
	$is_takeover = 'fullpage' === $display && Takeover::claim();
	$has_modal   = spacefast_block_has_modal( $attributes, $host_may_be_framed );
	// A takeover shows the Space at its full length through the relay unless
	// the block turns that off.
	$relay = $is_takeover && ( $attributes['fitContent'] ?? true )
		? Space_Relay::relay_url( Relay_Controller::relay_base(), 'page', $href )
		: '';
	$inner = '';
	if ( $is_takeover ) {
		$inner = spacefast_block_takeover_html( $attributes, $href, $relay );
	} elseif ( 'card' === $display ) {
		$image_html = spacefast_block_card_image_html( $attributes );
		if ( '' === $image_html ) {
			// Classes follow what the card actually shows.
			$attributes['showImage'] = false;
		}
		$inner = spacefast_block_card_html( $attributes, $href, $image_html, $has_modal );
	} elseif ( 'link' === $display ) {
		$inner = spacefast_block_link_html( $attributes, $href, $has_modal );
	} else {
		// Inline, and every full-page block that doesn't take over the page.
		$inner = spacefast_block_inline_html( $attributes, $href );
	}

	if ( $has_modal ) {
		$inner .= spacefast_block_modal_html( $attributes, $href );
	}

	$wrapper = array(
		'class' => Space_Display::block_class_names( $attributes, $host_may_be_framed )
			. ( $is_takeover ? ' is-fullpage-takeover' : '' )
			. ( '' !== $relay ? ' is-fit-content' : '' ),
		'style' => '',
	);
	// The link mode put color and font size on its button already.
	if ( 'link' !== $display ) {
		$supports = spacefast_block_color_and_font_size( $attributes );
		if ( '' !== $supports['class'] ) {
			$wrapper['class'] .= ' ' . $supports['class'];
		}
		$wrapper['style'] .= $supports['style'];
	}
	$height = Space_Display::min_height( $attributes, $host_may_be_framed );
	if ( null !== $height ) {
		$wrapper['style'] .= 'min-height:' . $height . ';';
	}
	if ( '' === $wrapper['style'] ) {
		unset( $wrapper['style'] );
	}

	// The store is only added when there is a modal or a takeover to drive.
	$interactive = '';
	if ( $is_takeover ) {
		$interactive = ' data-wp-interactive="spacefast/space" data-wp-init="callbacks.initTakeover"';
		if ( '' !== $relay ) {
			// What the view script needs to size the relayed frame, follow its
			// links, and frame the Space directly if the relay doesn't work.
			$interactive .= ' ' . wp_interactivity_data_wp_context(
				array(
					'fit' => array(
						'spaceUrl'        => (string) Space_Url::normalize( $href ),
						'directUrl'       => $href,
						'relayBase'       => Relay_Controller::relay_base(),
						'sandbox'         => SPACEFAST_BLOCK_FRAME_SANDBOX,
						'allowFullscreen' => (bool) ( $attributes['allowFullscreen'] ?? true ),
					),
				)
			);
		}
	} elseif ( $has_modal ) {
		$interactive = ' data-wp-interactive="spacefast/space" '
			. wp_interactivity_data_wp_context( array( 'frameUrl' => $href ) );
	}

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() and wp_interactivity_data_wp_context() return escaped output, and every part of $inner is escaped where it is built.
	return '<div ' . get_block_wrapper_attributes( $wrapper ) . $interactive . '>' . $inner . '</div>';
}
