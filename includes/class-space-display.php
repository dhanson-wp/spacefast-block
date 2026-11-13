<?php
/**
 * Display rules for the Spacefast block.
 *
 * Pure PHP; this file must not call any WordPress function. It is the PHP side
 * of src/space/utils/display.js, and tests/php/SpaceDisplayTest.php mirrors that
 * file's tests so the editor and the front end behave the same. Text that needs
 * translating is passed in by the caller.
 *
 * @package Spacefast_Block
 */

namespace Spacefast_Block;

/**
 * Decides how a block's attributes are shown.
 */
class Space_Display {

	/**
	 * Display modes that put the Space in a frame on the page.
	 */
	const FRAMED_DISPLAYS = array( 'inline', 'fullpage' );

	/**
	 * Every display mode.
	 */
	const DISPLAYS = array( 'inline', 'fullpage', 'card', 'link' );

	/**
	 * CSS units the minimum height may use.
	 */
	const HEIGHT_UNITS = array( 'px', 'em', 'rem', 'vh', 'svh', 'dvh', 'lvh', 'vw', '%' );

	/**
	 * Whether a display mode can be used. A Space that cannot be framed can only
	 * be shown as a card or a link.
	 *
	 * @param string $display      Display mode.
	 * @param bool   $is_frameable Whether the Space can be framed.
	 * @return bool
	 */
	public static function is_display_available( string $display, bool $is_frameable ): bool {
		return $is_frameable || ! in_array( $display, self::FRAMED_DISPLAYS, true );
	}

	/**
	 * Whether the Space may be put in a frame: it can be framed, and its host
	 * may be framed (see Space_Url::may_frame_host()). A Space on a host that
	 * may not be framed is treated exactly like one that cannot be framed.
	 *
	 * @param array $attributes         Block attributes.
	 * @param bool  $host_may_be_framed Whether the Space's host may be framed.
	 * @return bool
	 */
	public static function can_frame( array $attributes, bool $host_may_be_framed ): bool {
		return $host_may_be_framed && (bool) ( $attributes['isFrameable'] ?? true );
	}

	/**
	 * The mode the block is shown in. A Space that cannot be framed is shown as a
	 * card, and an unknown mode as inline.
	 *
	 * @param array $attributes         Block attributes.
	 * @param bool  $host_may_be_framed Whether the Space's host may be framed.
	 * @return string
	 */
	public static function effective_display( array $attributes, bool $host_may_be_framed = true ): string {
		$display = $attributes['display'] ?? 'inline';
		if ( ! in_array( $display, self::DISPLAYS, true ) ) {
			$display = 'inline';
		}
		return self::is_display_available( $display, self::can_frame( $attributes, $host_may_be_framed ) ) ? $display : 'card';
	}

	/**
	 * Whether the block has an explicit aspect ratio, which wins over any height.
	 * An `auto` ratio is no ratio.
	 *
	 * @param array $attributes Block attributes.
	 * @return bool
	 */
	public static function has_aspect_ratio( array $attributes ): bool {
		$ratio = $attributes['style']['dimensions']['aspectRatio'] ?? '';
		return is_string( $ratio ) && '' !== trim( $ratio ) && 'auto' !== strtolower( trim( $ratio ) );
	}

	/**
	 * The `min-height` for the block wrapper. Only the inline mode has a minimum
	 * height, and an explicit aspect ratio takes precedence over it.
	 *
	 * @param array $attributes         Block attributes.
	 * @param bool  $host_may_be_framed Whether the Space's host may be framed.
	 * @return string|null CSS length, or null for none.
	 */
	public static function min_height( array $attributes, bool $host_may_be_framed = true ): ?string {
		$height = $attributes['minHeight'] ?? 0;
		if (
			'inline' !== self::effective_display( $attributes, $host_may_be_framed )
			|| ! is_numeric( $height )
			|| $height <= 0
			|| self::has_aspect_ratio( $attributes )
		) {
			return null;
		}

		$unit = $attributes['minHeightUnit'] ?? 'px';
		if ( ! in_array( $unit, self::HEIGHT_UNITS, true ) ) {
			$unit = 'px';
		}
		return ( 0 + $height ) . $unit;
	}

	/**
	 * The badge text for a private Space.
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $fallback   Text to use when none was written.
	 * @return string
	 */
	public static function private_label( array $attributes, string $fallback ): string {
		$label = $attributes['privateLabel'] ?? '';
		return '' !== $label ? $label : $fallback;
	}

	/**
	 * The call to action for a private Space.
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $fallback   Text to use when none was written.
	 * @return string
	 */
	public static function private_cta( array $attributes, string $fallback ): string {
		$cta = $attributes['privateCta'] ?? '';
		return '' !== $cta ? $cta : $fallback;
	}

	/**
	 * The link text: the written label, else "Open Space", or the private call
	 * to action for a private Space.
	 *
	 * @param array  $attributes     Block attributes.
	 * @param string $open_space     Fallback for a public Space.
	 * @param string $request_access Fallback for the private call to action.
	 * @return string
	 */
	public static function link_label( array $attributes, string $open_space, string $request_access ): string {
		$label = $attributes['linkLabel'] ?? '';
		if ( '' !== $label ) {
			return $label;
		}
		return ! empty( $attributes['isPrivate'] )
			? self::private_cta( $attributes, $request_access )
			: $open_space;
	}

	/**
	 * The card heading: the Space title, or its hostname when it has none.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function card_title( array $attributes ): string {
		$title = $attributes['title'] ?? '';
		return '' !== $title ? $title : (string) ( $attributes['hostname'] ?? '' );
	}

	/**
	 * Where the card's image comes from: 'attachment' (the media library, which
	 * wins), 'url' (the Space's own preview image), or 'none'. A private Space's
	 * own image can't be loaded by visitors, so it only shows a library image.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function card_image_source( array $attributes ): string {
		if ( ! ( $attributes['showImage'] ?? true ) ) {
			return 'none';
		}
		if ( ( $attributes['imageId'] ?? 0 ) > 0 ) {
			return 'attachment';
		}
		if ( ! empty( $attributes['isPrivate'] ) || '' === ( $attributes['imageUrl'] ?? '' ) ) {
			return 'none';
		}
		return 'url';
	}

	/**
	 * Class names for the block wrapper, matching the editor.
	 *
	 * @param array $attributes         Block attributes.
	 * @param bool  $host_may_be_framed Whether the Space's host may be framed.
	 * @return string Space-separated class names.
	 */
	public static function block_class_names( array $attributes, bool $host_may_be_framed = true ): string {
		$display = self::effective_display( $attributes, $host_may_be_framed );
		$classes = array( 'is-display-' . $display );
		if ( 'card' === $display && 'none' !== self::card_image_source( $attributes ) ) {
			$classes[] = 'has-image-' . ( 'side' === ( $attributes['imagePosition'] ?? 'top' ) ? 'side' : 'top' );
		}
		if ( ! empty( $attributes['isPrivate'] ) ) {
			$classes[] = 'is-private';
		}
		return implode( ' ', $classes );
	}
}
