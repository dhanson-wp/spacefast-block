<?php
/**
 * URL rules for Spaces.
 *
 * Pure PHP; this file must not call any WordPress function. It is the boundary
 * for every URL the plugin fetches or frames.
 *
 * @package Spacefast_Block
 */

namespace Spacefast_Block;

/**
 * Normalizes Space URLs and builds safe frame URLs.
 */
class Space_Url {

	/**
	 * Reduces user input to `https://host/`, or null when it isn't a plain Space host.
	 *
	 * Only the scheme and host survive; a path belongs in the block's start path.
	 * Rejects non-HTTPS schemes, credentials, ports, IP literals, `localhost`,
	 * and anything that isn't a dotted DNS name.
	 *
	 * @param string $input Raw URL or bare hostname.
	 * @return string|null Normalized URL, or null.
	 */
	public static function normalize( string $input ): ?string {
		$input = trim( $input );

		if ( '' === $input || preg_match( '/[\x00-\x20\x7f\\\\]/', $input ) ) {
			return null;
		}

		if ( false === strpos( $input, '://' ) ) {
			// A bare host has no colon before its first path, query, or fragment
			// delimiter. Anything else (javascript:, data:, host:port) is refused.
			$authority = preg_split( '/[\/?#]/', $input, 2 )[0];
			if ( false !== strpos( $authority, ':' ) ) {
				return null;
			}
			$input = 'https://' . $input;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- No WordPress available here; PHP_URL_* components are used.
		$parts = parse_url( $input );
		if ( ! is_array( $parts ) || ! isset( $parts['scheme'], $parts['host'] ) ) {
			return null;
		}
		if ( 'https' !== strtolower( $parts['scheme'] ) ) {
			return null;
		}
		if ( isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['port'] ) ) {
			return null;
		}

		$host = strtolower( rtrim( $parts['host'], '.' ) );
		if ( ! self::is_dns_name( $host ) ) {
			return null;
		}

		return 'https://' . $host . '/';
	}

	/**
	 * Joins a start path onto a Space URL, or returns the base URL if the path is unsafe.
	 *
	 * The path must be relative: path segments, then an optional query and fragment.
	 * One leading slash is tolerated. Dot segments, empty segments, backslashes,
	 * control characters, `@`, colons in the path, and anything that could change the
	 * host are all refused.
	 *
	 * @param string $url        Space URL; normally the output of normalize().
	 * @param string $start_path Relative start path.
	 * @return string Frame URL, or an empty string when the Space URL itself isn't valid.
	 */
	public static function frame_url( string $url, string $start_path ): string {
		$base = self::normalize( $url );
		if ( null === $base ) {
			return '';
		}

		if ( '' === $start_path || preg_match( '/[\x00-\x20\x7f\\\\@]/', $start_path ) ) {
			return $base;
		}

		if ( '/' === $start_path[0] ) {
			$start_path = substr( $start_path, 1 );
		}

		$fragment = null;
		$query    = null;
		$hash_at  = strpos( $start_path, '#' );
		if ( false !== $hash_at ) {
			$fragment   = substr( $start_path, $hash_at + 1 );
			$start_path = substr( $start_path, 0, $hash_at );
		}
		$query_at = strpos( $start_path, '?' );
		if ( false !== $query_at ) {
			$query      = substr( $start_path, $query_at + 1 );
			$start_path = substr( $start_path, 0, $query_at );
		}

		if ( ! self::is_safe_path( $start_path ) ) {
			return $base;
		}
		foreach ( array( $query, $fragment ) as $tail ) {
			if ( null !== $tail && ! preg_match( '/^(?:[A-Za-z0-9\-._~!$&\'()*+,;=:\/?]|%[0-9A-Fa-f]{2})*$/', $tail ) ) {
				return $base;
			}
		}

		$result = $base . $start_path;
		if ( null !== $query && '' !== $query ) {
			$result .= '?' . $query;
		}
		if ( null !== $fragment && '' !== $fragment ) {
			$result .= '#' . $fragment;
		}
		return $result;
	}

	/**
	 * The start path a pasted link points to: its path, query, and fragment, as
	 * frame_url() would use them, or '' for the home page or a path it refuses.
	 *
	 * @param string $input Raw URL or bare hostname with a path.
	 * @return string Start path with a leading slash, or ''.
	 */
	public static function start_path( string $input ): string {
		$base = self::normalize( $input );
		if ( null === $base ) {
			return '';
		}

		$input = trim( $input );
		$after = strpos( $input, '://' );
		$rest  = false === $after ? $input : substr( $input, $after + 3 );
		$tail  = strpbrk( $rest, '/?#' );
		if ( false === $tail ) {
			return '';
		}

		$framed = self::frame_url( $base, $tail );
		return $framed === $base ? '' : '/' . substr( $framed, strlen( $base ) );
	}

	/**
	 * Returns the host of a URL, or an empty string when there isn't one.
	 *
	 * @param string $url URL.
	 * @return string Hostname.
	 */
	public static function hostname( string $url ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- No WordPress available here.
		$host = parse_url( $url, PHP_URL_HOST );
		return is_string( $host ) ? $host : '';
	}

	/**
	 * Whether a host may be shown in a frame: it is `space.fast` or one of its
	 * subdomains, or a host the resolve route confirmed as a Space.
	 *
	 * @param string $host           Hostname.
	 * @param array  $verified_hosts Confirmed hosts, host => time confirmed.
	 * @return bool
	 */
	public static function may_frame_host( string $host, array $verified_hosts ): bool {
		$host = strtolower( $host );
		if ( '' === $host ) {
			return false;
		}
		if ( 'space.fast' === $host || '.space.fast' === substr( $host, -11 ) ) {
			return true;
		}
		return array_key_exists( $host, $verified_hosts );
	}

	/**
	 * The confirmed host list with one host added or its time renewed. Entries
	 * that aren't host => time are dropped, and past the cap the hosts confirmed
	 * longest ago are pruned.
	 *
	 * @param array  $verified_hosts Confirmed hosts, host => time confirmed.
	 * @param string $host           Host to add.
	 * @param int    $now            Current time.
	 * @param int    $max            Most hosts to keep.
	 * @return array<string, int>
	 */
	public static function with_verified_host( array $verified_hosts, string $host, int $now, int $max ): array {
		$hosts = array();
		foreach ( $verified_hosts as $name => $time ) {
			if ( is_string( $name ) && '' !== $name && is_int( $time ) ) {
				$hosts[ $name ] = $time;
			}
		}

		$host = strtolower( $host );
		if ( '' === $host ) {
			return $hosts;
		}
		unset( $hosts[ $host ] );
		$hosts[ $host ] = $now;

		$excess = count( $hosts ) - $max;
		for ( $i = 0; $i < $excess; $i++ ) {
			unset( $hosts[ array_keys( $hosts, min( $hosts ), true )[0] ] );
		}
		return $hosts;
	}

	/**
	 * Whether a host is a dotted DNS name: not an IP literal, not `localhost`.
	 *
	 * @param string $host Lowercased host.
	 * @return bool
	 */
	public static function is_dns_name( string $host ): bool {
		if ( strlen( $host ) > 253 || false === strpos( $host, '.' ) ) {
			return false;
		}
		if ( ! preg_match( '/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $host ) ) {
			return false;
		}
		// A real TLD contains a letter and isn't a hex number (0x7f000001), which
		// resolvers read as an IPv4 address. Loopback and local-network TLDs are out too.
		$labels = explode( '.', $host );
		$tld    = end( $labels );
		if ( ! preg_match( '/[a-z]/', $tld ) || preg_match( '/^0x[0-9a-f]*$/', $tld ) ) {
			return false;
		}
		if ( in_array( $tld, array( 'localhost', 'local', 'internal' ), true ) ) {
			return false;
		}
		// Hex or numeric labels anywhere (127.0.0.0x1) are IPv4 spellings.
		foreach ( $labels as $label ) {
			if ( preg_match( '/^0x[0-9a-f]*$/', $label ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Whether a path (no query or fragment) is made only of plain segments.
	 *
	 * @param string $path Path without a leading slash.
	 * @return bool
	 */
	private static function is_safe_path( string $path ): bool {
		if ( '' === $path ) {
			return true;
		}
		$segments = explode( '/', $path );
		$last     = count( $segments ) - 1;
		foreach ( $segments as $index => $segment ) {
			if ( '' === $segment ) {
				// Only a trailing slash may leave an empty segment.
				if ( $index === $last && $index > 0 ) {
					continue;
				}
				return false;
			}
			if ( ! preg_match( '/^(?:[A-Za-z0-9\-._~!$&\'()*+,;=]|%[0-9A-Fa-f]{2})+$/', $segment ) ) {
				return false;
			}
			$decoded = rawurldecode( $segment );
			if ( '.' === $decoded || '..' === $decoded ) {
				return false;
			}
		}
		return true;
	}
}
