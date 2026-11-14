<?php
/**
 * URL, rewrite, and limit rules for the relay that serves a Space's page to
 * the full-page frame from the site itself.
 *
 * Pure PHP; this file must not call any WordPress function. Every upstream URL
 * the relay fetches is built here from a signed host and a path that passes
 * Space_Url's rules, and every URL it writes into a relayed document is
 * decided here. src/space/utils/relay.js mirrors relay_url() and
 * page_relay_url(); both are pinned by tests/fixtures/relay-urls.json.
 *
 * @package Spacefast_Block
 */

namespace Spacefast_Block;

/**
 * Builds relay and upstream URLs, rewrites stylesheets, and keeps the relay's
 * cache and upstream fetches bounded.
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
	 * Prefix of the relay's transients. uninstall.php deletes every
	 * `spacefast_block_` transient.
	 */
	const CACHE_PREFIX = 'spacefast_block_relay_';

	/**
	 * Font files a stylesheet's `url()` may point at.
	 */
	const FONT_EXTENSIONS = '/\.(?:woff2?|ttf|otf|eot)$/i';

	/**
	 * A quoted CSS string, double or single, with backslash escapes.
	 */
	const CSS_STRING = '"(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'';

	/**
	 * The signature that ties a relay URL to one host, so the relay only serves
	 * Spaces this site itself put in a page: the first 16 hex characters of an
	 * HMAC-MD5 of `spacefast-relay|<host>`. With the site's auth salt as the
	 * secret this is `substr( wp_hash( 'spacefast-relay|' . $host ), 0, 16 )`.
	 *
	 * @param string $host   Lowercased host.
	 * @param string $secret Signing secret.
	 * @return string
	 */
	public static function host_signature( string $host, string $secret ): string {
		return substr( hash_hmac( 'md5', 'spacefast-relay|' . $host, $secret ), 0, 16 );
	}

	/**
	 * The route segment for a host: `<host>~<signature>`.
	 *
	 * @param string $host   Host.
	 * @param string $secret Signing secret.
	 * @return string
	 */
	public static function signed_segment( string $host, string $secret ): string {
		$host = strtolower( $host );
		return $host . '~' . self::host_signature( $host, $secret );
	}

	/**
	 * The host in a signed route segment, or null when the signature is
	 * missing or wrong. Checked before anything else about the request.
	 *
	 * @param string $segment Route segment, `<host>~<signature>`.
	 * @param string $secret  Signing secret.
	 * @return string|null
	 */
	public static function verified_host( string $segment, string $secret ): ?string {
		$parts = explode( '~', $segment );
		if ( 2 !== count( $parts ) || '' === $parts[0] || 16 !== strlen( $parts[1] ) ) {
			return null;
		}
		return hash_equals( self::host_signature( $parts[0], $secret ), $parts[1] ) ? $parts[0] : null;
	}

	/**
	 * The relay URL for a Space URL: the relay base, the kind (`page` or
	 * `asset`), the signed host, and the path as path segments, so relative URLs
	 * in the relayed file keep resolving through the relay. The relay never
	 * carries a query: a page with one isn't relayed (''), and an asset's is
	 * dropped. The fragment is dropped. `+` is encoded so one decode by
	 * WordPress gives it back, and with plain permalinks (a base holding `?`)
	 * so is `&`.
	 *
	 * @param string $relay_base The REST namespace URL, ending in a slash.
	 * @param string $kind       `page` or `asset`.
	 * @param string $url        Absolute Space URL.
	 * @param string $signature  The host's signature, from host_signature().
	 * @return string Relay URL, or '' when it can't be relayed.
	 */
	public static function relay_url( string $relay_base, string $kind, string $url, string $signature ): string {
		if ( ! in_array( $kind, array( 'page', 'asset' ), true ) || ! preg_match( '/^[0-9a-f]{16}$/', $signature ) ) {
			return '';
		}
		$parts = self::split( $url );
		if ( null === $parts || ( 'page' === $kind && $parts['has_query'] ) ) {
			return '';
		}
		$base = 'https://' . $parts['host'] . '/';
		if ( Space_Url::frame_url( $base, $parts['path'] ) !== $base . $parts['path'] ) {
			return '';
		}

		$path = str_replace( '+', '%2B', $parts['path'] );
		if ( false !== strpos( $relay_base, '?' ) ) {
			$path = str_replace( '&', '%26', $path );
		}
		return $relay_base . $kind . '/' . $parts['host'] . '~' . $signature . '/' . $path;
	}

	/**
	 * The page relay URL for a block's Space URL and start path.
	 *
	 * @param string $relay_base The REST namespace URL, ending in a slash.
	 * @param string $url        Space URL.
	 * @param string $start_path Start path.
	 * @param string $signature  The host's signature.
	 * @return string Relay URL, or '' when the Space URL isn't valid or has a query.
	 */
	public static function page_relay_url( string $relay_base, string $url, string $start_path, string $signature ): string {
		$frame_url = Space_Url::frame_url( $url, $start_path );
		return '' === $frame_url ? '' : self::relay_url( $relay_base, 'page', $frame_url, $signature );
	}

	/**
	 * The URL the relay fetches for a request: the host, and the path
	 * WordPress decoded once, encoded again and held to Space_Url::frame_url()'s
	 * rules. A path with `@`, a backslash, or a control character is refused
	 * outright. The request's query is never used.
	 *
	 * @param string $host Verified host.
	 * @param string $path Path from the route, without a leading slash, decoded.
	 * @return string|null Upstream URL, or null when it is refused.
	 */
	public static function upstream_url( string $host, string $path ): ?string {
		$host = strtolower( $host );
		$base = 'https://' . $host . '/';
		if ( Space_Url::normalize( $host ) !== $base || preg_match( '/[\x00-\x1f\x7f\\\\@]/', $path ) ) {
			return null;
		}

		$path = preg_replace_callback(
			'/[^A-Za-z0-9\-._~!$&\'()*+,;=\/]/',
			static function ( array $found ): string {
				return rawurlencode( $found[0] );
			},
			$path
		);
		if ( '' === $path ) {
			return $base;
		}
		return Space_Url::frame_url( $base, $path ) === $base . $path ? $base . $path : null;
	}

	/**
	 * Where a page redirect may go: only the same host and path with a trailing
	 * slash added, and no query.
	 *
	 * @param string $url      URL that answered with the redirect.
	 * @param string $location Raw `Location` header value.
	 * @return string|null The URL to follow, or null.
	 */
	public static function page_redirect_target( string $url, string $location ): ?string {
		$from = self::split( $url );
		$next = self::resolve_url( $url, $location );
		$to   = null === $next ? null : self::split( $next );
		if ( null === $from || null === $to || $from['host'] !== $to['host'] || $from['has_query'] || $to['has_query'] ) {
			return null;
		}
		if ( $to['path'] !== $from['path'] . '/' || '/' === substr( $from['path'], -1 ) ) {
			return null;
		}
		return self::upstream_url( $to['host'], rawurldecode( $to['path'] ) );
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
	 * to leave the reference alone. The reference resolves against the
	 * document's base, but only a URL on the page's own host is relayed, so a
	 * `<base>` pointing elsewhere can't put another host through the relay.
	 *
	 * @param string $document_base The URL the document resolves against.
	 * @param string $page_url      The page's real URL.
	 * @param string $ref           The attribute value.
	 * @param string $relay_base    The REST namespace URL.
	 * @param string $signature     The page host's signature.
	 * @return string|null
	 */
	public static function html_asset_url( string $document_base, string $page_url, string $ref, string $relay_base, string $signature ): ?string {
		$resolved = self::resolve_url( $document_base, $ref );
		if ( null === $resolved || ! self::same_host( $resolved, $page_url ) ) {
			return null;
		}
		$relayed = self::relay_url( $relay_base, 'asset', $resolved, $signature );
		return '' === $relayed ? null : $relayed;
	}

	/**
	 * Rewrites a stylesheet's `url()`, `@import`, and `image-set()` targets.
	 * Each is resolved against the stylesheet's real URL. Same-host fonts and
	 * imported stylesheets go through the relay; any other same-host URL
	 * becomes an absolute URL on the Space. Comments, `data:` URLs, fragments,
	 * and other hosts are left as written.
	 *
	 * @param string $css        Stylesheet.
	 * @param string $css_url    The stylesheet's real URL (the page's for a `<style>`).
	 * @param string $relay_base The REST namespace URL.
	 * @param string $signature  The relayed host's signature.
	 * @param string $page_url   The URL whose host may be relayed, when it isn't
	 *                           the stylesheet's own (a `<style>` in a page whose
	 *                           `<base>` points elsewhere). Default: `$css_url`.
	 * @return string
	 */
	public static function rewrite_css( string $css, string $css_url, string $relay_base, string $signature, string $page_url = '' ): string {
		$page_url = '' === $page_url ? $css_url : $page_url;
		$string   = self::CSS_STRING;
		$pattern  = '~/\*.*?\*/'
			. '|((?:-webkit-)?image-set\(\s*)((?:[^()"\']|' . $string . '|\([^()]*\))*)\)'
			. '|(@import\s+)?url\(\s*(' . $string . '|[^)\s"\']*)\s*\)'
			. '|@import\s+(' . $string . ')~is';

		$rewritten = preg_replace_callback(
			$pattern,
			static function ( array $m ) use ( $css_url, $relay_base, $signature, $page_url ): string {
				if ( 0 === strpos( $m[0], '/*' ) ) {
					return $m[0];
				}
				if ( '' !== ( $m[1] ?? '' ) ) {
					// image-set(): strings are images, so they become absolute;
					// url() inside goes through the usual rules.
					$inner = preg_replace_callback(
						'~' . self::CSS_STRING . '~s',
						static function ( array $s ) use ( $css_url, $page_url ): string {
							$target = self::css_target( $css_url, $page_url, self::css_unquote( $s[0] ), '', '', false );
							return null === $target ? $s[0] : '"' . str_replace( '"', '%22', $target ) . '"';
						},
						$m[2]
					);
					$inner = self::rewrite_css( (string) $inner, $css_url, $relay_base, $signature, $page_url );
					return $m[1] . $inner . ')';
				}
				$is_url    = '' !== ( $m[4] ?? '' ) || (bool) preg_match( '/^(?:@import\s+)?url\(/i', $m[0] );
				$is_import = ! $is_url || '' !== ( $m[3] ?? '' );
				$ref       = self::css_unquote( $is_url ? ( $m[4] ?? '' ) : ( $m[5] ?? '' ) );

				$target = self::css_target( $css_url, $page_url, $ref, $relay_base, $signature, $is_import );
				if ( null === $target ) {
					return $m[0];
				}
				$target = str_replace( '"', '%22', $target );
				return $is_url ? ( $m[3] ?? '' ) . 'url("' . $target . '")' : '@import "' . $target . '"';
			},
			$css
		);
		return null === $rewritten ? $css : $rewritten;
	}

	/**
	 * The transient name for a relay response.
	 *
	 * @param string $relay_base The REST namespace URL it was rewritten for.
	 * @param string $kind       `page` or `asset`.
	 * @param string $host       Host.
	 * @param string $path       Path.
	 * @return string
	 */
	public static function cache_key( string $relay_base, string $kind, string $host, string $path ): string {
		return self::CACHE_PREFIX . md5( $relay_base . "\n" . $kind . "\n" . $host . "\n" . $path );
	}

	/**
	 * The cache index with a key added or renewed, or null when the cache is
	 * full and the key isn't in it. Expired and malformed entries are dropped.
	 *
	 * @param mixed  $index   Stored index, key => expiry time.
	 * @param string $key     Cache key to store.
	 * @param int    $expires When the new entry expires.
	 * @param int    $now     Current time.
	 * @param int    $max     Most entries.
	 * @return array<string, int>|null
	 */
	public static function cache_admit( $index, string $key, int $expires, int $now, int $max ): ?array {
		$kept = array();
		foreach ( is_array( $index ) ? $index : array() as $name => $expiry ) {
			if ( is_string( $name ) && is_int( $expiry ) && $expiry > $now ) {
				$kept[ $name ] = $expiry;
			}
		}
		if ( ! isset( $kept[ $key ] ) && count( $kept ) >= $max ) {
			return null;
		}
		$kept[ $key ] = $expires;
		return $kept;
	}

	/**
	 * The fetch budget with one fetch taken, or null when the window's budget
	 * is spent.
	 *
	 * @param mixed $state  Stored state: `start` of the window and `count` so far.
	 * @param int   $now    Current time.
	 * @param int   $limit  Fetches allowed per window.
	 * @param int   $window Window length in seconds.
	 * @return array{start: int, count: int}|null
	 */
	public static function budget_take( $state, int $now, int $limit, int $window ): ?array {
		$valid = is_array( $state ) && isset( $state['start'], $state['count'] ) && is_int( $state['start'] ) && is_int( $state['count'] )
			&& $state['start'] <= $now && $now - $state['start'] < $window;
		$state = $valid ? $state : array(
			'start' => $now,
			'count' => 0,
		);
		if ( $state['count'] >= $limit ) {
			return null;
		}
		++$state['count'];
		return $state;
	}

	/**
	 * Upstream fetches allowed per minute, by kind. Pages and assets have
	 * separate budgets, because every new visitor's stylesheets, fonts, and
	 * module scripts are fetched (they aren't cached on the server).
	 */
	const BUDGETS = array(
		'page'  => 120,
		'asset' => 1200,
	);

	/**
	 * The fetch limit for a kind: a filtered value when it is a positive
	 * number, else the default. 0 for an unknown kind.
	 *
	 * @param string $kind     `page` or `asset`.
	 * @param mixed  $filtered The `spacefast_block_relay_budget` filter's result.
	 * @return int
	 */
	public static function budget_limit( string $kind, $filtered ): int {
		if ( ! isset( self::BUDGETS[ $kind ] ) ) {
			return 0;
		}
		return is_numeric( $filtered ) && (int) $filtered > 0 ? (int) $filtered : self::BUDGETS[ $kind ];
	}

	/**
	 * The budgets with one fetch of a kind taken, or null when that kind's
	 * budget is spent. Each kind has its own counter and window.
	 *
	 * @param mixed  $states Stored budgets, kind => state for budget_take().
	 * @param string $kind   `page` or `asset`.
	 * @param int    $now    Current time.
	 * @param int    $limit  Fetches of this kind allowed per window.
	 * @param int    $window Window length in seconds.
	 * @return array<string, array{start: int, count: int}>|null
	 */
	public static function budget_take_kind( $states, string $kind, int $now, int $limit, int $window ): ?array {
		if ( ! isset( self::BUDGETS[ $kind ] ) ) {
			return null;
		}
		$states = is_array( $states ) ? array_intersect_key( $states, self::BUDGETS ) : array();
		$taken  = self::budget_take( $states[ $kind ] ?? null, $now, $limit, $window );
		if ( null === $taken ) {
			return null;
		}
		$states[ $kind ] = $taken;
		return $states;
	}

	/**
	 * Sends a relay response: the status, each header, then the body. If
	 * anything was already sent, the headers that keep the body safe (the CSP
	 * sandbox, nosniff, the type) can't be, so nothing is sent at all.
	 *
	 * @param array    $payload      `status`, `headers` (name => value), and `body`.
	 * @param bool     $headers_sent Whether output has already started.
	 * @param callable $send_status  Sends the status code.
	 * @param callable $send_header  Sends one `Name: value` header line.
	 * @param callable $send_body    Sends the body.
	 * @return bool Whether the response was sent.
	 */
	public static function emit( array $payload, bool $headers_sent, callable $send_status, callable $send_header, callable $send_body ): bool {
		if ( $headers_sent ) {
			return false;
		}
		$send_status( (int) $payload['status'] );
		foreach ( $payload['headers'] as $name => $value ) {
			$send_header( $name . ': ' . $value );
		}
		$send_body( (string) $payload['body'] );
		return true;
	}

	/**
	 * A script with its comment-only lines, blank lines, and indentation
	 * removed, for printing inline. Only comments that start a line are
	 * removed, so `//` or `/*` inside strings and regular expressions survive.
	 *
	 * @param string $js Script source.
	 * @return string
	 */
	public static function compact_script( string $js ): string {
		$js    = (string) preg_replace( '~^[ \t]*/\*.*?\*/[ \t]*\r?\n~ms', '', $js );
		$lines = array();
		foreach ( preg_split( '/\r?\n/', $js ) as $line ) {
			$line = trim( $line );
			if ( '' !== $line && 0 !== strpos( $line, '//' ) ) {
				$lines[] = $line;
			}
		}
		return implode( "\n", $lines );
	}

	/**
	 * Where one stylesheet reference should point, or null to leave it alone.
	 *
	 * @param string $css_url    The stylesheet's URL.
	 * @param string $page_url   The URL whose host may be relayed or made absolute.
	 * @param string $ref        The reference, unquoted.
	 * @param string $relay_base The REST namespace URL, or '' never to relay.
	 * @param string $signature  The stylesheet host's signature.
	 * @param bool   $is_import  Whether it is an `@import`.
	 * @return string|null
	 */
	private static function css_target( string $css_url, string $page_url, string $ref, string $relay_base, string $signature, bool $is_import ): ?string {
		$resolved = self::resolve_url( $css_url, $ref );
		if ( null === $resolved || ! self::same_host( $resolved, $page_url ) ) {
			return null;
		}
		$parts = self::split( $resolved );
		if ( '' !== $relay_base && null !== $parts && ( $is_import || preg_match( self::FONT_EXTENSIONS, $parts['path'] ) ) ) {
			$relayed = self::relay_url( $relay_base, 'asset', $resolved, $signature );
			if ( '' !== $relayed ) {
				return $relayed;
			}
		}
		return $resolved;
	}

	/**
	 * A CSS string or bare URL token without its quotes and simple escapes.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private static function css_unquote( string $value ): string {
		if ( '' !== $value && ( '"' === $value[0] || "'" === $value[0] ) ) {
			$value = substr( $value, 1, -1 );
		}
		return (string) preg_replace( '/\\\\([^0-9A-Fa-f\r\n])/', '$1', $value );
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
	 * whether it has a query, or null when it has a port, credentials, or
	 * another scheme.
	 *
	 * @param string $url URL.
	 * @return array{host: string, path: string, has_query: bool}|null
	 */
	private static function split( string $url ): ?array {
		if ( ! preg_match( '#^https://([^/?\#]+)(?:/([^?\#]*))?(\?[^\#]*)?#i', $url, $m ) ) {
			return null;
		}
		$host = strtolower( $m[1] );
		if ( ! Space_Url::is_dns_name( $host ) ) {
			return null;
		}
		return array(
			'host'      => $host,
			'path'      => $m[2] ?? '',
			'has_query' => '' !== ( $m[3] ?? '' ),
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
