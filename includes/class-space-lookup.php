<?php
/**
 * Pure rules for the search, resolve, and sideload routes.
 *
 * Pure PHP; this file must not call any WordPress function.
 *
 * @package Spacefast_Block
 */

namespace Spacefast_Block;

/**
 * Maps Spacefast list payloads and validates IDs and URLs the routes accept.
 */
class Space_Lookup {

	const MAX_RESULTS = 20;

	const ID_PATTERN = '/^spc_[a-f0-9]{32}\z/';

	/**
	 * Whether a value is a Spacefast Space ID.
	 *
	 * @param mixed $id Candidate.
	 * @return bool
	 */
	public static function is_valid_space_id( $id ): bool {
		return is_string( $id ) && 1 === preg_match( self::ID_PATTERN, $id );
	}

	/**
	 * Maps the `data` rows of `GET /spaces` to the shape the picker uses.
	 *
	 * @param array $rows Rows from Spacefast.
	 * @return array<int, array{id: string, title: string, team: string, hostname: string, url: string}>
	 */
	public static function map_spaces( array $rows ): array {
		$spaces = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || ! self::is_valid_space_id( $row['id'] ?? null ) ) {
				continue;
			}
			$hostname = self::text( $row, 'defaultHostname' );
			$url      = self::text( $row, 'liveUrl' );
			if ( '' === $url && '' !== $hostname ) {
				$url = 'https://' . $hostname . '/';
			}
			$title    = self::text( $row, 'title' );
			$spaces[] = array(
				'id'       => $row['id'],
				'title'    => '' !== $title ? $title : $hostname,
				'team'     => self::text( $row, 'teamSlug' ),
				'hostname' => $hostname,
				'url'      => $url,
			);
			if ( count( $spaces ) >= self::MAX_RESULTS ) {
				break;
			}
		}
		return $spaces;
	}

	/**
	 * Whether a URL may be downloaded as a preview image: https, with a host that
	 * passes the same rules as a Space URL, and no credentials or port.
	 *
	 * @param mixed $url Candidate.
	 * @return bool
	 */
	public static function is_valid_sideload_url( $url ): bool {
		if ( ! is_string( $url ) || '' === $url || preg_match( '/[\x00-\x20\x7f\\\\]/', $url ) ) {
			return false;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Pure PHP, tested without WordPress.
		$parts = parse_url( $url );
		if ( ! is_array( $parts ) || ! isset( $parts['scheme'], $parts['host'] ) || 'https' !== strtolower( $parts['scheme'] ) ) {
			return false;
		}
		if ( isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['port'] ) ) {
			return false;
		}
		return Space_Url::is_dns_name( strtolower( rtrim( $parts['host'], '.' ) ) );
	}

	/**
	 * Resolves a redirect `Location` against the URL that returned it, and checks
	 * the result with the same rules as a sideload URL.
	 *
	 * @param string $base     URL that answered with the redirect.
	 * @param string $location Raw `Location` header value.
	 * @return string|null Absolute https URL that may be followed, or null.
	 */
	public static function next_redirect_url( string $base, string $location ): ?string {
		$location = trim( $location );
		if ( '' === $location || preg_match( '/[\x00-\x20\x7f\\\\]/', $location ) ) {
			return null;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Pure PHP, tested without WordPress.
		$base_parts = parse_url( $base );
		if ( ! is_array( $base_parts ) || ! isset( $base_parts['scheme'], $base_parts['host'] ) ) {
			return null;
		}
		$origin = $base_parts['scheme'] . '://' . $base_parts['host'];

		if ( 0 === strpos( $location, '//' ) ) {
			$candidate = 'https:' . $location;
		} elseif ( '/' === $location[0] ) {
			$candidate = $origin . $location;
		} elseif ( preg_match( '#^[A-Za-z][A-Za-z0-9+.\-]*:#', $location ) ) {
			$candidate = $location;
		} else {
			$path      = $base_parts['path'] ?? '/';
			$candidate = $origin . substr( $path, 0, (int) strrpos( $path, '/' ) + 1 ) . $location;
		}
		return self::is_valid_sideload_url( $candidate ) ? $candidate : null;
	}

	/**
	 * Whether the Spacefast key may be sent to a URL: https on the API host only.
	 *
	 * @param string $url Request URL.
	 * @return bool
	 */
	public static function should_send_key( string $url ): bool {
		if ( ! self::is_valid_sideload_url( $url ) ) {
			return false;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Pure PHP, tested without WordPress.
		return 'api.spacefast.com' === strtolower( rtrim( (string) parse_url( $url, PHP_URL_HOST ), '.' ) );
	}

	/**
	 * The error code for a thumbnail the API would not hand over.
	 *
	 * A 403 is Spacefast refusing this connection's key for the image (it says
	 * "You do not have access to this space" even when the same key can read
	 * the Space), so it gets its own code and an accurate message. Any other
	 * failure keeps its code; a 401 must stay `unauthorized`-shaped so the dead
	 * key is cleared.
	 *
	 * @param int    $status HTTP status of the failure.
	 * @param string $code   Error code from the API client.
	 * @return string
	 */
	public static function thumbnail_error_code( int $status, string $code ): string {
		return 403 === $status ? 'spacefast_preview_forbidden' : $code;
	}

	/**
	 * Reads a string field from a row.
	 *
	 * @param array  $row   Row.
	 * @param string $field Field name.
	 * @return string
	 */
	private static function text( array $row, string $field ): string {
		return isset( $row[ $field ] ) && is_string( $row[ $field ] ) ? $row[ $field ] : '';
	}
}
