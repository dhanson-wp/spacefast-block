<?php
/**
 * Rewrites a Space's HTML page for the relay.
 *
 * Every URL decision is made by Space_Relay; this class only walks the
 * document with WP_HTML_Tag_Processor and applies them.
 *
 * @package Spacefast_Block
 */

namespace Spacefast_Block;

use WP_HTML_Tag_Processor;

/**
 * Puts the base and reporter first in a page and points its stylesheets,
 * fonts, and module scripts at the relay.
 */
class Relay_Page {

	/**
	 * The page rewritten for the relay, and the charset its own markup declares.
	 *
	 * - A `<base>` pointing at the page's directory on the Space, then the
	 *   reporter script, become the first children of `<head>`. An existing
	 *   `<base href>` is resolved, used, and its `href` dropped.
	 * - Same-host stylesheets, font/style/script preloads, module preloads, and
	 *   module scripts point at the relay. A rewritten stylesheet loses its
	 *   `integrity`, since its bytes change.
	 * - `<style>` elements get the same rewrite as relayed stylesheets, so their
	 *   fonts load through the relay too.
	 *
	 * A `<meta http-equiv="Content-Security-Policy">` is left alone: it only
	 * applies to what follows it, and the reporter comes first.
	 *
	 * @param string $html       The page as fetched.
	 * @param string $page_url   The page's real URL.
	 * @param string $relay_base The REST namespace URL.
	 * @param string $reporter   The reporter script.
	 * @return array{html: string, charset: string} Rewritten page, and the
	 *         `<meta charset>` value ('' for none).
	 */
	public static function rewrite( string $html, string $page_url, string $relay_base, string $reporter ): array {
		$scan     = self::scan( $html );
		$base     = Space_Relay::base_href( $page_url, $scan['base'] );
		$marker   = 'data-spacefast-relay-' . substr( md5( $html ), 0, 12 );
		$marked   = false;
		$mark_tag = $scan['has_head'] ? 'HEAD' : 'HTML';

		$processor = new WP_HTML_Tag_Processor( $html );
		while ( $processor->next_tag() ) {
			if ( $processor->is_tag_closer() ) {
				continue;
			}
			$tag = $processor->get_tag();

			if ( ! $marked && $mark_tag === $tag ) {
				$processor->set_attribute( $marker, true );
				$marked = true;
			} elseif ( 'BASE' === $tag ) {
				$processor->remove_attribute( 'href' );
			} elseif ( 'LINK' === $tag ) {
				self::rewrite_link( $processor, $base, $relay_base );
			} elseif ( 'SCRIPT' === $tag ) {
				$src = $processor->get_attribute( 'src' );
				if ( is_string( $src ) && Space_Relay::is_relayed_script( (string) $processor->get_attribute( 'type' ) ) ) {
					$relayed = Space_Relay::html_asset_url( $base, $src, $relay_base );
					if ( null !== $relayed ) {
						$processor->set_attribute( 'src', $relayed );
					}
				}
			} elseif ( 'STYLE' === $tag ) {
				$css       = $processor->get_modifiable_text();
				$rewritten = Space_Relay::rewrite_css( $css, $base, $relay_base );
				if ( $rewritten !== $css ) {
					$processor->set_modifiable_text( $rewritten );
				}
			}
		}

		$insert = '<base href="' . esc_url( $base ) . '"><script>' . $reporter . '</script>';
		return array(
			'html'    => self::insert_first( $processor->get_updated_html(), $marker, $insert ),
			'charset' => $scan['charset'],
		);
	}

	/**
	 * What the rewrite needs to know before it starts: the first `<base href>`,
	 * whether there are `<head>` and `<html>` tags, and the declared charset.
	 *
	 * @param string $html Page.
	 * @return array{base: ?string, has_head: bool, charset: string}
	 */
	private static function scan( string $html ): array {
		$found     = array(
			'base'     => null,
			'has_head' => false,
			'charset'  => '',
		);
		$processor = new WP_HTML_Tag_Processor( $html );
		while ( $processor->next_tag() ) {
			if ( $processor->is_tag_closer() ) {
				continue;
			}
			$tag = $processor->get_tag();
			if ( 'HEAD' === $tag ) {
				$found['has_head'] = true;
			} elseif ( 'BASE' === $tag && null === $found['base'] && is_string( $processor->get_attribute( 'href' ) ) ) {
				$found['base'] = $processor->get_attribute( 'href' );
			} elseif ( 'META' === $tag && '' === $found['charset'] ) {
				$charset = $processor->get_attribute( 'charset' );
				if ( is_string( $charset ) && preg_match( '/^[A-Za-z0-9_\-]+$/', trim( $charset ) ) ) {
					$found['charset'] = trim( $charset );
				}
			}
		}
		return $found;
	}

	/**
	 * Points a relayed `<link>` at the relay.
	 *
	 * @param WP_HTML_Tag_Processor $processor  Processor on the link.
	 * @param string                $base       The URL the document resolves against.
	 * @param string                $relay_base The REST namespace URL.
	 */
	private static function rewrite_link( WP_HTML_Tag_Processor $processor, string $base, string $relay_base ): void {
		$href = $processor->get_attribute( 'href' );
		$rel  = (string) $processor->get_attribute( 'rel' );
		$as   = (string) $processor->get_attribute( 'as' );
		if ( ! is_string( $href ) || ! Space_Relay::is_relayed_link( $rel, $as ) ) {
			return;
		}
		$relayed = Space_Relay::html_asset_url( $base, $href, $relay_base );
		if ( null === $relayed ) {
			return;
		}
		$processor->set_attribute( 'href', $relayed );
		if ( preg_match( '/(^|\s)stylesheet(\s|$)/i', $rel ) || 'style' === strtolower( trim( $as ) ) ) {
			$processor->remove_attribute( 'integrity' );
		}
	}

	/**
	 * Inserts markup right after the marked `<head>` or `<html>` tag, removing
	 * the marker, or after the doctype when neither tag is written out.
	 *
	 * @param string $html   Page with one tag marked.
	 * @param string $marker Marker attribute name.
	 * @param string $insert Markup to insert.
	 * @return string
	 */
	private static function insert_first( string $html, string $marker, string $insert ): string {
		$tag_pattern = '/<[A-Za-z]+\s+' . preg_quote( $marker, '/' ) . '(?=[\s\/>])(?:[^>"\']|"[^"]*"|\'[^\']*\')*>/';
		if ( preg_match( $tag_pattern, $html, $match, PREG_OFFSET_CAPTURE ) ) {
			$tag   = $match[0][0];
			$at    = $match[0][1];
			$clean = preg_replace( '/\s+' . preg_quote( $marker, '/' ) . '(?=[\s\/>])/', '', $tag, 1 );
			return substr( $html, 0, $at ) . $clean . $insert . substr( $html, $at + strlen( $tag ) );
		}
		if ( preg_match( '/^(?:\xEF\xBB\xBF)?\s*<!doctype[^>]*>/i', $html, $match ) ) {
			return $match[0] . $insert . substr( $html, strlen( $match[0] ) );
		}
		return $insert . $html;
	}
}
