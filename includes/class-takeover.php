<?php
/**
 * The full-page takeover: one Spacefast block fills the page between the
 * site's header and footer.
 *
 * Which block takes over is decided once per request from the queried post's
 * blocks, so the body classes that switch the takeover styles on can be added
 * in PHP, before anything paints.
 *
 * @package Spacefast_Block
 */

namespace Spacefast_Block;

/**
 * Finds and claims the block that takes over a singular page.
 */
class Takeover {

	/**
	 * The active block's attributes, null for none, or false before it is worked out.
	 *
	 * @var array|null|false
	 */
	private static $active = false;

	/**
	 * Whether a block has already rendered as the takeover this request.
	 *
	 * @var bool
	 */
	private static $claimed = false;

	/**
	 * Hooks the body classes.
	 */
	public static function init(): void {
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
	}

	/**
	 * The first Spacefast block in a parsed block tree that is shown full page,
	 * searching inner blocks depth first, in document order. A Space that can't
	 * be framed is shown as a card, so it never takes over.
	 *
	 * Synced patterns (`core/block`) are not expanded; a full-page block inside
	 * one doesn't take over.
	 *
	 * @param array $blocks Output of parse_blocks().
	 * @return array|null The block's attributes, or null.
	 */
	public static function find_fullpage_block( array $blocks ): ?array {
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
			if (
				'spacefast/space' === ( $block['blockName'] ?? null )
				&& 'fullpage' === Space_Display::effective_display( $attrs )
			) {
				return $attrs;
			}
			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$found = self::find_fullpage_block( $block['innerBlocks'] );
				if ( null !== $found ) {
					return $found;
				}
			}
		}
		return null;
	}

	/**
	 * The attributes of the block that takes over this request's page, or null.
	 *
	 * Only a singular front-end view of a post whose content can be read takes
	 * over. Worked out once per request, after the main query has run.
	 *
	 * @return array|null
	 */
	public static function active_block(): ?array {
		if ( false !== self::$active ) {
			return self::$active;
		}
		if ( ! did_action( 'wp' ) ) {
			// The main query hasn't run yet; don't cache a guess.
			return null;
		}

		self::$active = null;
		if (
			is_admin()
			|| wp_is_serving_rest_request()
			|| wp_doing_ajax()
			|| is_feed()
			|| is_embed()
			|| ! is_singular()
		) {
			return null;
		}

		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post || post_password_required( $post ) || ! has_block( 'spacefast/space', $post ) ) {
			return null;
		}

		self::$active = self::find_fullpage_block( parse_blocks( $post->post_content ) );
		return self::$active;
	}

	/**
	 * Claims the takeover for the block being rendered. True at most once per
	 * request: for the first full-page block rendered in the main query's loop
	 * for the queried post. Every other full-page block renders as inline.
	 *
	 * @return bool True when the block being rendered should take over the page.
	 */
	public static function claim(): bool {
		if ( self::$claimed || null === self::active_block() ) {
			return false;
		}
		if ( ! in_the_loop() || ! is_main_query() || get_the_ID() !== get_queried_object_id() ) {
			return false;
		}
		self::$claimed = true;
		return true;
	}

	/**
	 * Adds the takeover body classes.
	 *
	 * @param string[] $classes Body classes.
	 * @return string[]
	 */
	public static function body_class( $classes ): array {
		$classes = (array) $classes;
		$active  = self::active_block();
		if ( null === $active ) {
			return $classes;
		}

		$classes[] = 'has-spacefast-fullpage';
		if ( ! empty( $active['hideHeader'] ) ) {
			$classes[] = 'spacefast-hide-header';
		}
		if ( ! empty( $active['hideFooter'] ) ) {
			$classes[] = 'spacefast-hide-footer';
		}
		return $classes;
	}
}
