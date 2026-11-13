<?php
/**
 * Per-user Spacefast connection: key storage and device sign-in.
 *
 * @package Spacefast_Block
 */

namespace Spacefast_Block;

use WP_Error;

/**
 * Stores each user's Spacefast key encrypted, and runs the device flow.
 * Encryption and poll interpretation are pure PHP and unit tested.
 */
class Connection {

	const META_KEY         = '_spacefast_block_key';
	const TRANSIENT_PREFIX = 'spacefast_block_device_';

	/**
	 * Encrypts a value with a secretbox key derived from a secret.
	 *
	 * @param string $plain  Value to encrypt.
	 * @param string $secret Secret (a WordPress salt in production).
	 * @return string Base64 of nonce followed by ciphertext.
	 */
	public static function encrypt( string $plain, string $secret ): string {
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$box   = sodium_crypto_secretbox( $plain, $nonce, sodium_crypto_generichash( $secret, '', 32 ) );
		return base64_encode( $nonce . $box ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decrypts a value from encrypt().
	 *
	 * @param string $encoded Base64 value.
	 * @param string $secret  Secret used to encrypt.
	 * @return string|null Plain text, or null when it cannot be decrypted.
	 */
	public static function decrypt( string $encoded, string $secret ): ?string {
		$raw = base64_decode( $encoded, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES ) {
			return null;
		}
		$nonce = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$box   = substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$plain = sodium_crypto_secretbox_open( $box, $nonce, sodium_crypto_generichash( $secret, '', 32 ) );
		return false === $plain ? null : $plain;
	}

	/**
	 * Maps a poll response payload to a status and, when approved, the key.
	 *
	 * Anything that is not clearly pending or approved-with-a-key ends the
	 * flow as `expired`, so the editor never polls forever. A `consumed`
	 * code means the key was already handed out on an earlier poll.
	 *
	 * @param array $data The poll response `data`.
	 * @return array{status: string, secret: string|null}
	 */
	public static function interpret_poll( array $data ): array {
		$status = isset( $data['status'] ) && is_string( $data['status'] ) ? $data['status'] : '';

		if ( 'pending' === $status ) {
			return array(
				'status' => 'pending',
				'secret' => null,
			);
		}
		if ( 'denied' === $status ) {
			return array(
				'status' => 'denied',
				'secret' => null,
			);
		}
		if ( 'approved' === $status ) {
			$secret = isset( $data['apiKey']['secret'] ) && is_string( $data['apiKey']['secret'] ) ? $data['apiKey']['secret'] : '';
			if ( '' !== $secret ) {
				return array(
					'status' => 'connected',
					'secret' => $secret,
				);
			}
		}
		return array(
			'status' => 'expired',
			'secret' => null,
		);
	}

	/**
	 * Returns a user's decrypted key, or null. Clears the meta if it cannot be read.
	 *
	 * @param int $user_id User ID.
	 * @return string|null
	 */
	public static function get_key( int $user_id ): ?string {
		$stored = get_user_meta( $user_id, self::META_KEY, true );
		if ( ! is_string( $stored ) || '' === $stored ) {
			return null;
		}
		$key = self::decrypt( $stored, wp_salt( 'auth' ) );
		if ( null === $key ) {
			delete_user_meta( $user_id, self::META_KEY );
		}
		return $key;
	}

	/**
	 * Stores a user's key, encrypted.
	 *
	 * @param int    $user_id User ID.
	 * @param string $key     Spacefast API key.
	 */
	public static function set_key( int $user_id, string $key ): void {
		update_user_meta( $user_id, self::META_KEY, self::encrypt( $key, wp_salt( 'auth' ) ) );
	}

	/**
	 * Forgets a user's key and any sign-in in progress.
	 *
	 * @param int $user_id User ID.
	 */
	public static function clear( int $user_id ): void {
		delete_user_meta( $user_id, self::META_KEY );
		delete_transient( self::TRANSIENT_PREFIX . $user_id );
	}

	/**
	 * Whether a user has a readable key.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function is_connected( int $user_id ): bool {
		return null !== self::get_key( $user_id );
	}

	/**
	 * Sends an API request as a user. A 401 means the key is dead, so it is forgotten.
	 *
	 * @param int    $user_id User ID.
	 * @param string $method  HTTP method.
	 * @param string $path    API path.
	 * @param array  $args    Request args.
	 * @return array|WP_Error
	 */
	public static function request( int $user_id, string $method, string $path, array $args = array() ) {
		$key = self::get_key( $user_id );
		if ( null === $key ) {
			return new WP_Error(
				'spacefast_not_connected',
				__( 'Connect your Spacefast account first.', 'spacefast-block' ),
				array( 'status' => 401 )
			);
		}
		$result = Api_Client::request( $method, $path, $args, $key );
		if ( is_wp_error( $result ) && 401 === ( $result->get_error_data()['status'] ?? 0 ) ) {
			self::clear( $user_id );
		}
		return $result;
	}

	/**
	 * Starts a device sign-in. The device code stays on the server.
	 *
	 * @param int $user_id User ID.
	 * @return array{userCode: string, verificationUrl: string, interval: int, expiresAt: string}|WP_Error
	 */
	public static function start( int $user_id ) {
		$data = Api_Client::request( 'POST', '/auth/device', array( 'access' => 'read' ) );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		if ( empty( $data['deviceCode'] ) || empty( $data['userCode'] ) || empty( $data['verificationUrl'] ) || empty( $data['expiresAt'] ) ) {
			return new WP_Error(
				'spacefast_bad_response',
				__( 'Spacefast returned an unexpected response.', 'spacefast-block' ),
				array( 'status' => 502 )
			);
		}

		if ( ! self::is_trusted_verification_url( (string) $data['verificationUrl'] ) ) {
			return new WP_Error(
				'spacefast_bad_response',
				__( 'Spacefast returned an unexpected response.', 'spacefast-block' ),
				array( 'status' => 502 )
			);
		}

		$ttl = max( 60, (int) strtotime( (string) $data['expiresAt'] ) - time() );
		set_transient( self::TRANSIENT_PREFIX . $user_id, self::encrypt( (string) $data['deviceCode'], wp_salt( 'auth' ) ), $ttl );

		return array(
			'userCode'        => (string) $data['userCode'],
			'verificationUrl' => (string) $data['verificationUrl'],
			'interval'        => max( 1, (int) ( $data['interval'] ?? 5 ) ),
			'expiresAt'       => (string) $data['expiresAt'],
		);
	}

	/**
	 * Checks a pending device sign-in once.
	 *
	 * @param int $user_id User ID.
	 * @return string One of `pending`, `connected`, `denied`, `expired`.
	 */
	public static function poll( int $user_id ): string {
		$stored      = get_transient( self::TRANSIENT_PREFIX . $user_id );
		$device_code = is_string( $stored ) && '' !== $stored ? self::decrypt( $stored, wp_salt( 'auth' ) ) : null;
		if ( null === $device_code ) {
			return self::reconcile_status( 'expired', self::is_connected( $user_id ) );
		}

		$data = Api_Client::request( 'POST', '/auth/device/poll', array( 'deviceCode' => $device_code ) );
		if ( is_wp_error( $data ) ) {
			$status = (int) ( $data->get_error_data()['status'] ?? 0 );
			// Network trouble, rate limits, and server errors are worth retrying.
			if ( $status >= 500 || 429 === $status ) {
				return 'pending';
			}
			delete_transient( self::TRANSIENT_PREFIX . $user_id );
			return self::reconcile_status( 'expired', self::is_connected( $user_id ) );
		}

		$result = self::interpret_poll( $data );
		if ( 'pending' !== $result['status'] ) {
			delete_transient( self::TRANSIENT_PREFIX . $user_id );
		}
		if ( null !== $result['secret'] ) {
			self::set_key( $user_id, $result['secret'] );
		}
		return self::reconcile_status( $result['status'], null !== $result['secret'] || self::is_connected( $user_id ) );
	}

	/**
	 * Turns `expired` into `connected` when the user already has a key.
	 *
	 * Two overlapping polls can race: the first stores the key, the second sees
	 * a consumed code. The user is connected, so say so.
	 *
	 * @param string $status       Status from the poll.
	 * @param bool   $is_connected Whether the user already has a readable key.
	 * @return string
	 */
	public static function reconcile_status( string $status, bool $is_connected ): string {
		return 'expired' === $status && $is_connected ? 'connected' : $status;
	}

	/**
	 * Whether a verification URL is https on spacefast.com or a subdomain of it.
	 *
	 * @param string $url URL from the API.
	 * @return bool
	 */
	public static function is_trusted_verification_url( string $url ): bool {
		if ( preg_match( '/[\x00-\x20\x7f\\\\]/', $url ) ) {
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
		$host = strtolower( rtrim( $parts['host'], '.' ) );
		return 'spacefast.com' === $host || '.spacefast.com' === substr( $host, -14 );
	}
}
