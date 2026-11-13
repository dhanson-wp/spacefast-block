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
