<?php
/**
 * The hosts the resolve route has confirmed as Spaces.
 *
 * The front end never asks Spacefast whether a host is a Space; it reads this
 * list. The rule that decides whether a host may be framed is the pure
 * Space_Url::may_frame_host().
 *
 * @package Spacefast_Block
 */

namespace Spacefast_Block;

/**
 * Reads and records confirmed Space hosts in a site option.
 */
class Verified_Hosts {

	/**
	 * Site option holding host => time confirmed. Not autoloaded.
	 */
	const OPTION = 'spacefast_block_space_hosts';

	/**
	 * Most hosts kept; the ones confirmed longest ago are pruned.
	 */
	const MAX_HOSTS = 500;

	/**
	 * Every confirmed host.
	 *
	 * @return array<string, int> Host => time confirmed.
	 */
	public static function all(): array {
		$hosts = get_option( self::OPTION, array() );
		return is_array( $hosts ) ? $hosts : array();
	}

	/**
	 * Records the host of a URL the resolve route confirmed as a Space.
	 *
	 * @param string $url Space URL, as Space_Url::normalize() returns it.
	 */
	public static function record( string $url ): void {
		$host = Space_Url::hostname( $url );
		if ( '' === $host ) {
			return;
		}
		update_option( self::OPTION, Space_Url::with_verified_host( self::all(), $host, time(), self::MAX_HOSTS ), false );
	}

	/**
	 * Whether the Space at a URL may be shown in a frame.
	 *
	 * @param string $url Space URL.
	 * @return bool
	 */
	public static function may_frame( string $url ): bool {
		return Space_Url::may_frame_host( Space_Url::hostname( $url ), self::all() );
	}
}
