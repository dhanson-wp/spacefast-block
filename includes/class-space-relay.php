<?php
/**
 * URL and rewrite rules for the relay that serves a Space's page to the
 * full-page frame from the site itself.
 *
 * Pure PHP; this file must not call any WordPress function. Every upstream URL
 * the relay fetches is built here from a host and path that pass Space_Url's
 * rules, and every URL it writes into a relayed document is decided here.
 * src/space/utils/relay.js mirrors relay_url() and page_relay_url(); both are
 * pinned by tests/fixtures/relay-urls.json.
 *
 * @package Spacefast_Block
 */

namespace Spacefast_Block;

/**
 * Builds relay and upstream URLs and rewrites stylesheets.
 */
class Space_Relay {

	/**
	 * The frame's sandbox. No `allow-same-origin`: the relayed document is
	 * served from the site, so it must never run with the site's origin.
	 */
	const SANDBOX = 'allow-scripts allow-forms allow-popups allow-popups-to-escape-sandbox allow-downloads';

	/**
	 * Sent on every relay response, so the document gets an opaque origin even
	 * when its URL is opened directly.
	 */
	const CSP = 'sandbox ' . self::SANDBOX;

	/**
	 * Largest page the relay serves, in bytes.
	 */
	const PAGE_MAX_BYTES = 1048576;

	/**
	 * Largest stylesheet, script, or font the relay serves, in bytes.
	 */
	const ASSET_MAX_BYTES = 2097152;

	/**
	 * Prefix of the transient holding a rewritten page. uninstall.php deletes
	 * every `spacefast_block_` transient.
	 */
	const CACHE_PREFIX = 'spacefast_block_relay_';

	/**
	 * Font files a stylesheet's `url()` may point at.
	 */
	const FONT_EXTENSIONS = '/\.(?:woff2?|ttf|otf|eot)$/i';

	/**
	 * The relay URL for a Space URL: the relay base, the kind (`page` or
	 * `asset`), the host, and the path as path segments, so relative URLs in the
	 * relayed file keep resolving through the relay. The fragment is dropped.
	 * `+` is encoded so one decode by WordPress gives it back, and with plain
	 * permalinks (a base holding `?`) so is `&`, and the query follows with `&`.
	 *
	 * @param string $relay_base The REST namespace URL, ending in a slash.
	 * @param string $kind       `page` or `asset`.
	 * @param string $url        Absolute Space URL.
	 * @return string Relay URL, or '' when the URL isn't a safe Space URL.
	 */
	public static function relay_url( string $relay_base, string $kind, string $url ): string {
		if ( ! in_array( $kind, array( 'page', 'asset' ), true ) ) {
			return '';
		}
		$parts = self::split( $url );
		if ( null === $parts ) {
			return '';
		}
		$target = $parts['path'] . ( '' !== $parts['query'] ? '?' . $parts['query'] : '' );
		$base   = 'https://' . $parts['host'] . '/';
		if ( Space_Url::frame_url( $base, $target ) !== $base . $target ) {
			return '';
		}

		$plain = false !== strpos( $relay_base, '?' );
		$path  = str_replace( '+', '%2B', $parts['path'] );
		if ( $plain ) {
			$path = str_replace( '&', '%26', $path );
		}
		$result = $relay_base . $kind . '/' . $parts['host'] . '/' . $path;
		if ( '' !== $parts['query'] ) {
			$result .= ( $plain ? '&' : '?' ) . $parts['query'];
		}
		return $result;
	}

	/**
	 * The page relay URL for a block's Space URL and start path.
	 *
	 * @param string $relay_base The REST namespace URL, ending in a slash.
	 * @param string $url        Space URL.
	 * @param string $start_path Start path.
	 * @return string Relay URL, or '' when the Space URL isn't valid.
	 */
	public static function page_relay_url( string $relay_base, string $url, string $start_path ): string {
		$frame_url = Space_Url::frame_url( $url, $start_path );
		return '' === $frame_url ? '' : self::relay_url( $relay_base, 'page', $frame_url );
	}

	/**
	 * The URL the relay fetches for a request: the host, and the path
	 * WordPress decoded once, encoded again and held to Space_Url::frame_url()'s
	 * rules. A path with `@`, a backslash, or a control character is refused
	 * outright.
	 *
	 * @param string $host  Host from the route.
	 * @param string $path  Path from the route, without a leading slash, decoded.
	 * @param string $query Query to forward, as sent.
	 * @return string|null Upstream URL, or null when it is refused.
	 */
	public static function upstream_url( string $host, string $path, string $query ): ?string {
		$host = strtolower( $host );
		$base = 'https://' . $host . '/';
		if ( Space_Url::normalize( $host ) !== $base || preg_match( '/[\x00-\x1f\x7f\\\\@]/', $path ) ) {
			return null;
		}

		$path   = preg_replace_callback(
			'/[^A-Za-z0-9\-._~!$&\'()*+,;=\/]/',
			static function ( array $found ): string {
				return rawurlencode( $found[0] );
			},
			$path
		);
		$target = '' !== $query ? $path . '?' . $query : $path;
		if ( '' === $target ) {
			return $base;
		}
		$url = Space_Url::frame_url( $base, $target );
		return $url === $base . $target ? $url : null;
	}

	/**
	 * The query string to forward upstream: the request's, without the
	 * `rest_route` parameter plain permalinks carry the route in.
	 *
	 * @param string $query_string Raw query string.
	 * @return string
	 */
	public static function forwarded_query( string $query_string ): string {
		$kept = array();
		foreach ( explode( '&', $query_string ) as $pair ) {
			if ( '' === $pair || 'rest_route' === explode( '=', $pair, 2 )[0] ) {
				continue;
			}
			$kept[] = $pair;
		}
		return implode( '&', $kept );
	}

	/**
	 * Where a page redirect may go: only to the same URL with a trailing slash
	 * added to its path.
	 *
	 * @param string $url      URL that answered with the redirect.
	 * @param string $location Raw `Location` header value.
	 * @return string|null The URL to follow, or null.
	 */
	public static function page_redirect_target( string $url, string $location ): ?string {
		$from = self::split( $url );
		$next = self::resolve_url( $url, $location );
		$to   = null === $next ? null : self::split( $next );
		if ( null === $from || null === $to || $from['host'] !== $to['host'] ) {
			return null;
		}
		if ( $to['path'] !== $from['path'] . '/' || '/' === substr( $from['path'], -1 ) ) {
			return null;
		}
		return 'https://' . $to['host'] . '/' . $to['path'] . ( '' !== $to['query'] ? '?' . $to['query'] : '' );
	}

	/**
	 * The Content-Type to send for a relayed asset: CSS, JavaScript, or a font,
	 * with a plain charset parameter kept.
	 *
	 * @param string $content_type Upstream Content-Type.
	 * @return string|null Header value, or null when the type isn't relayed.
	 */
	public static function asset_content_type( string $content_type ): ?string {
		$mime = self::mime( $content_type );
		if (
			in_array( $mime, array( 'text/css', 'text/javascript', 'application/javascript', 'application/vnd.ms-fontobject' ), true )
			|| preg_match( '#^(?:font/|application/font-)[a-z0-9.+\-]+$#', $mime )
		) {
			return $mime . self::charset( $content_type );
		}
		return null;
	}

	/**
	 * The Content-Type to send for a relayed page: HTML only.
	 *
	 * @param string $content_type Upstream Content-Type.
	 * @return string|null Header value, or null when it isn't HTML.
	 */
	public static function page_content_type( string $content_type ): ?string {
		return 'text/html' === self::mime( $content_type ) ? 'text/html' . self::charset( $content_type ) : null;
	}

	/**
	 * Whether a Content-Type is CSS.
	 *
	 * @param string $content_type Content-Type.
	 * @return bool
	 */
	public static function is_css( string $content_type ): bool {
		return 'text/css' === self::mime( $content_type );
	}

	/**
	 * Resolves a reference against an absolute URL (RFC 3986), keeping only
	 * http and https results.
	 *
	 * @param string $base Absolute URL.
	 * @param string $ref  Reference, as written in the document.
	 * @return string|null Absolute URL, or null for an empty or fragment-only
	 *                     reference, another scheme, or a base that isn't a URL.
	 */
	public static function resolve_url( string $base, string $ref ): ?string {
		$ref = trim( $ref, " \t\n\r\f" );
		if ( '' === $ref || '#' === $ref[0] ) {
			return null;
		}
		if ( ! preg_match( '#^(https?)://([^/?\#]*)([^?\#]*)(\?[^\#]*)?#i', $base, $b ) ) {
			return null;
		}
		$origin = strtolower( $b[1] ) . '://' . $b[2];
		$path   = '' === $b[3] ? '/' : $b[3];

		if ( preg_match( '#^([A-Za-z][A-Za-z0-9+.\-]*):#', $ref, $scheme ) ) {
			if ( ! in_array( strtolower( $scheme[1] ), array( 'http', 'https' ), true ) ) {
				return null;
			}
			$absolute = $ref;
		} elseif ( 0 === strpos( $ref, '//' ) ) {
			$absolute = strtolower( $b[1] ) . ':' . $ref;
		} elseif ( '/' === $ref[0] ) {
			$absolute = $origin . $ref;
		} elseif ( '?' === $ref[0] ) {
			$absolute = $origin . $path . $ref;
		} else {
			$absolute = $origin . substr( $path, 0, (int) strrpos( $path, '/' ) + 1 ) . $ref;
		}

		if ( ! preg_match( '#^([A-Za-z]+://[^/?\#]*)([^?\#]*)(.*)$#s', $absolute, $a ) ) {
			return null;
		}
		return $a[1] . self::remove_dot_segments( '' === $a[2] ? '/' : $a[2] ) . $a[3];
	}

	/**
	 * The `<base href>` for a relayed page: an existing base resolved against
	 * the page, or else the page's directory.
	 *
	 * @param string      $page_url      The page's real URL.
	 * @param string|null $existing_base The document's own `<base href>`, if any.
	 * @return string
	 */
	public static function base_href( string $page_url, ?string $existing_base ): string {
		if ( null !== $existing_base ) {
			$resolved = self::resolve_url( $page_url, $existing_base );
			if ( null !== $resolved ) {
				return $resolved;
			}
		}
		$url = preg_replace( '/[?#].*$/s', '', $page_url );
		return substr( $url, 0, (int) strrpos( $url, '/' ) + 1 );
	}

	/**
	 * Whether a `<link>` is relayed: a stylesheet, a preload of a font, style,
	 * or script, or a module preload.
	 *
	 * @param string $rel     The `rel` attribute.
	 * @param string $as_type The `as` attribute.
	 * @return bool
	 */
	public static function is_relayed_link( string $rel, string $as_type ): bool {
		$tokens = preg_split( '/\s+/', strtolower( trim( $rel ) ), -1, PREG_SPLIT_NO_EMPTY );
		if ( in_array( 'stylesheet', $tokens, true ) || in_array( 'modulepreload', $tokens, true ) ) {
			return true;
		}
		return in_array( 'preload', $tokens, true ) && in_array( strtolower( trim( $as_type ) ), array( 'font', 'style', 'script' ), true );
	}

	/**
	 * Whether a `<script src>` is relayed: module scripts only. Classic scripts
	 * load straight from the Space.
	 *
	 * @param string $type The `type` attribute.
	 * @return bool
	 */
	public static function is_relayed_script( string $type ): bool {
		return 'module' === strtolower( trim( $type ) );
	}

	/**
	 * The relay URL for a stylesheet, script, or font a page points at, or null
	 * to leave the reference alone: another host, another scheme, or a path the
	 * relay would refuse.
	 *
	 * @param string $document_base The URL the document resolves against.
	 * @param string $ref           The attribute value.
	 * @param string $relay_base    The REST namespace URL.
	 * @return string|null
	 */
	public static function html_asset_url( string $document_base, string $ref, string $relay_base ): ?string {
		$resolved = self::resolve_url( $document_base, $ref );
		if ( null === $resolved || ! self::same_host( $resolved, $document_base ) ) {
			return null;
		}
		$relayed = self::relay_url( $relay_base, 'asset', $resolved );
		return '' === $relayed ? null : $relayed;
	}

	/**
	 * Rewrites a stylesheet's `url()` and `@import` targets. Each is resolved
	 * against the stylesheet's real URL. Same-host fonts and imported
	 * stylesheets go through the relay; any other same-host URL becomes an
	 * absolute URL on the Space. `data:` URLs, fragments, and other hosts are
	 * left as written.
	 *
	 * @param string $css        Stylesheet.
	 * @param string $css_url    The stylesheet's real URL (the page's for a `<style>`).
	 * @param string $relay_base The REST namespace URL.
	 * @return string
	 */
	public static function rewrite_css( string $css, string $css_url, string $relay_base ): string {
		$pattern = '/(@import\s+)?url\(\s*(?:"([^"]*)"|\'([^\']*)\'|([^)\s"\']*))\s*\)|@import\s+(?:"([^"]*)"|\'([^\']*)\')/i';

		$rewritten = preg_replace_callback(
			$pattern,
			static function ( array $m ) use ( $css_url, $relay_base ): string {
				$is_url    = (bool) preg_match( '/^(?:@import\s+)?url\(/i', $m[0] );
				$is_import = ! $is_url || '' !== $m[1];
				$ref       = $is_url ? $m[2] . ( $m[3] ?? '' ) . ( $m[4] ?? '' ) : ( $m[5] ?? '' ) . ( $m[6] ?? '' );

				$target = self::css_target( $css_url, $ref, $relay_base, $is_import );
				if ( null === $target ) {
					return $m[0];
				}
				$target = str_replace( '"', '%22', $target );
				return $is_url ? ( $m[1] ?? '' ) . 'url("' . $target . '")' : '@import "' . $target . '"';
			},
			$css
		);
		return null === $rewritten ? $css : $rewritten;
	}

	/**
	 * The transient name for a rewritten page.
	 *
	 * @param string $relay_base   The REST namespace URL the page was rewritten for.
	 * @param string $upstream_url The page's upstream URL.
	 * @return string
	 */
	public static function cache_key( string $relay_base, string $upstream_url ): string {
		return self::CACHE_PREFIX . md5( $relay_base . "\n" . $upstream_url );
	}

	/**
	 * Where one stylesheet reference should point, or null to leave it alone.
	 *
	 * @param string $css_url    The stylesheet's URL.
	 * @param string $ref        The reference.
	 * @param string $relay_base The REST namespace URL.
	 * @param bool   $is_import  Whether it is an `@import`.
	 * @return string|null
	 */
	private static function css_target( string $css_url, string $ref, string $relay_base, bool $is_import ): ?string {
		$resolved = self::resolve_url( $css_url, $ref );
		if ( null === $resolved || ! self::same_host( $resolved, $css_url ) ) {
			return null;
		}
		$parts = self::split( $resolved );
		if ( null !== $parts && ( $is_import || preg_match( self::FONT_EXTENSIONS, $parts['path'] ) ) ) {
			$relayed = self::relay_url( $relay_base, 'asset', $resolved );
			if ( '' !== $relayed ) {
				return $relayed;
			}
		}
		return $resolved;
	}

	/**
	 * Whether two URLs are on the same https host, with no port or credentials.
	 *
	 * @param string $url   URL.
	 * @param string $other URL.
	 * @return bool
	 */
	private static function same_host( string $url, string $other ): bool {
		$a = self::split( $url );
		$b = self::split( $other );
		return null !== $a && null !== $b && $a['host'] === $b['host'];
	}

	/**
	 * Splits an https URL on a DNS host into host, path (no leading slash), and
	 * query, or null when it has a port, credentials, or another scheme.
	 *
	 * @param string $url URL.
	 * @return array{host: string, path: string, query: string}|null
	 */
	private static function split( string $url ): ?array {
		if ( ! preg_match( '#^https://([^/?\#]+)(?:/([^?\#]*))?(?:\?([^\#]*))?#i', $url, $m ) ) {
			return null;
		}
		$host = strtolower( $m[1] );
		if ( ! Space_Url::is_dns_name( $host ) ) {
			return null;
		}
		return array(
			'host'  => $host,
			'path'  => $m[2] ?? '',
			'query' => $m[3] ?? '',
		);
	}

	/**
	 * Removes `.` and `..` segments from an absolute path (RFC 3986 5.2.4).
	 *
	 * @param string $path Path starting with a slash.
	 * @return string
	 */
	private static function remove_dot_segments( string $path ): string {
		$output = array();
		foreach ( explode( '/', substr( $path, 1 ) ) as $segment ) {
			if ( '..' === $segment ) {
				array_pop( $output );
			} elseif ( '.' !== $segment ) {
				$output[] = $segment;
			}
		}
		$last = substr( $path, strrpos( $path, '/' ) + 1 );
		if ( '.' === $last || '..' === $last ) {
			$output[] = '';
		}
		return '/' . implode( '/', $output );
	}

	/**
	 * The lowercased MIME type of a Content-Type.
	 *
	 * @param string $content_type Content-Type.
	 * @return string
	 */
	private static function mime( string $content_type ): string {
		return strtolower( trim( explode( ';', $content_type, 2 )[0] ) );
	}

	/**
	 * A plain `; charset=` parameter from a Content-Type, or ''.
	 *
	 * @param string $content_type Content-Type.
	 * @return string
	 */
	private static function charset( string $content_type ): string {
		return preg_match( '/;\s*charset=([A-Za-z0-9_\-]+)\s*(?:;|$)/i', $content_type, $m ) ? '; charset=' . $m[1] : '';
	}
}
