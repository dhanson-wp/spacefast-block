<?php
/**
 * Spacefast API client.
 *
 * @package Spacefast_Block
 */

namespace Spacefast_Block;

use WP_Error;

/**
 * Talks to the Spacefast API. Response parsing is pure PHP and unit tested.
 */
class Api_Client {

	const BASE_URL = 'https://api.spacefast.com/v1';

	const MAX_RAW_BYTES = 10485760;

	const MAX_REDIRECTS = 3;

	/**
	 * Sends a request to the Spacefast API.
	 *
	 * @param string      $method HTTP method.
	 * @param string      $path   Path under the API base, starting with a slash.
	 * @param array       $args   JSON body for non-GET requests, or query args for GET.
	 * @param string|null $key    API key sent as a bearer token, if any.
	 * @return array|WP_Error Decoded `data` payload, or an error whose code is the
	 *                        Spacefast problem `code` and whose data has `status`.
	 */
	public static function request( string $method, string $path, array $args = array(), ?string $key = null ) {
		$method  = strtoupper( $method );
		$url     = self::BASE_URL . $path;
		$headers = array(
			'Accept'     => 'application/json',
			'User-Agent' => 'spacefast-block/' . SPACEFAST_BLOCK_VERSION,
		);
		if ( null !== $key && '' !== $key ) {
			$headers['Authorization'] = 'Bearer ' . $key;
		}

		$request = array(
			'method'  => $method,
			'timeout' => 15,
			'headers' => $headers,
		);
		if ( 'GET' === $method ) {
			if ( $args ) {
				$url = add_query_arg( $args, $url );
			}
		} elseif ( $args ) {
			$request['headers']['Content-Type'] = 'application/json';
			$request['body']                    = wp_json_encode( $args );
		}

		$response = wp_remote_request( $url, $request );
		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'spacefast_unreachable',
				__( 'Could not reach Spacefast. Try again in a moment.', 'spacefast-block' ),
				array( 'status' => 502 )
			);
		}

		$parsed = self::parse_response(
			(int) wp_remote_retrieve_response_code( $response ),
			(string) wp_remote_retrieve_body( $response )
		);
		if ( $parsed['ok'] ) {
			return $parsed['data'];
		}
		return new WP_Error( $parsed['code'], self::failure_message( $parsed ), array( 'status' => $parsed['status'] ) );
	}

	/**
	 * Downloads a Spacefast API path as raw bytes, such as an image.
	 *
	 * Redirects are followed by get_following_redirects(). The key goes only to
	 * api.spacefast.com over https, on the first request and on any redirect that
	 * stays on that host; it is never sent anywhere else. A failure from the API
	 * itself keeps the API's error code (so a 401 can clear a dead key); a failure
	 * from any other host gets the distinct `spacefast_image_unavailable` code.
	 *
	 * @param string $path Path under the API base, starting with a slash.
	 * @param string $key  API key sent as a bearer token.
	 * @return array{body: string, content_type: string}|WP_Error
	 */
	public static function request_raw( string $path, string $key ) {
		$fetched = self::get_following_redirects(
			self::BASE_URL . $path,
			array(
				'timeout'             => 15,
				'limit_response_size' => self::MAX_RAW_BYTES,
			),
			$key
		);
		if ( is_wp_error( $fetched ) ) {
			return $fetched;
		}

		$response = $fetched['response'];
		$status   = (int) wp_remote_retrieve_response_code( $response );
		if ( $status < 200 || $status >= 300 ) {
			if ( ! Space_Lookup::should_send_key( $fetched['url'] ) ) {
				return self::image_unavailable();
			}
			$parsed = self::parse_response( $status, (string) wp_remote_retrieve_body( $response ) );
			$parsed = $parsed['ok'] ? array(
				'code'    => 'spacefast_http_error',
				'message' => '',
				'status'  => $status,
			) : $parsed;
			return new WP_Error( $parsed['code'], self::failure_message( $parsed ), array( 'status' => $parsed['status'] ) );
		}

		return array(
			'body'         => (string) wp_remote_retrieve_body( $response ),
			'content_type' => (string) wp_remote_retrieve_header( $response, 'content-type' ),
		);
	}

	/**
	 * GETs a URL, following up to three redirects itself.
	 *
	 * Every hop is resolved and checked by Space_Lookup::next_redirect_url() (https,
	 * public DNS host, no port or credentials) before any request is made. The
	 * bearer key, when given, is attached only to hops that pass
	 * Space_Lookup::should_send_key(). A relative Location on the API host stays on
	 * the API host, so the key is deliberately re-sent there.
	 *
	 * @param string      $url     First URL; the caller must have validated it.
	 * @param array       $options wp_safe_remote_get() arguments.
	 * @param string|null $key     API key, or null to send none.
	 * @return array{response: array, url: string}|WP_Error The last response and the
	 *         URL it came from, or a `spacefast_image_unavailable` error.
	 */
	public static function get_following_redirects( string $url, array $options, ?string $key = null ) {
		$options['redirection']        = 0;
		$options['reject_unsafe_urls'] = true;
		$options['headers']            = array( 'User-Agent' => 'spacefast-block/' . SPACEFAST_BLOCK_VERSION );

		for ( $hop = 0; $hop <= self::MAX_REDIRECTS; $hop++ ) {
			$request = $options;
			if ( null !== $key && Space_Lookup::should_send_key( $url ) ) {
				$request['headers']['Authorization'] = 'Bearer ' . $key;
			}

			$response = wp_safe_remote_get( $url, $request );
			if ( is_wp_error( $response ) ) {
				return self::image_unavailable();
			}

			$status = (int) wp_remote_retrieve_response_code( $response );
			if ( $status < 300 || $status >= 400 ) {
				return array(
					'response' => $response,
					'url'      => $url,
				);
			}

			$next = Space_Lookup::next_redirect_url( $url, (string) wp_remote_retrieve_header( $response, 'location' ) );
			if ( null === $next ) {
				return self::image_unavailable();
			}
			$url = $next;
		}
		return self::image_unavailable();
	}

	/**
	 * Error for an image that could not be fetched from a non-API host or through a redirect.
	 *
	 * @return WP_Error
	 */
	private static function image_unavailable(): WP_Error {
		return new WP_Error(
			'spacefast_image_unavailable',
			__( 'We could not download that image.', 'spacefast-block' ),
			array( 'status' => 502 )
		);
	}

	/**
	 * Picks the message for a failed parse: the server's detail, or translated text.
	 *
	 * @param array $parsed A failed parse_response() result.
	 * @return string
	 */
	private static function failure_message( array $parsed ): string {
		if ( '' !== $parsed['message'] ) {
			return $parsed['message'];
		}
		return 'spacefast_bad_response' === $parsed['code']
			? __( 'Spacefast returned an unreadable response.', 'spacefast-block' )
			: __( 'Spacefast returned an error.', 'spacefast-block' );
	}

	/**
	 * Interprets an HTTP status and body. Pure; calls no WordPress function.
	 *
	 * @param int    $status HTTP status.
	 * @param string $body   Raw response body.
	 * @return array{ok: bool, data?: array, code?: string, message?: string, status?: int}
	 */
	public static function parse_response( int $status, string $body ): array {
		$decoded = json_decode( $body, true );

		if ( $status >= 200 && $status < 300 ) {
			if ( 204 === $status || '' === trim( $body ) ) {
				return array(
					'ok'   => true,
					'data' => array(),
				);
			}
			if ( ! is_array( $decoded ) ) {
				return array(
					'ok'      => false,
					'code'    => 'spacefast_bad_response',
					'message' => '',
					'status'  => 502,
				);
			}
			return array(
				'ok'   => true,
				'data' => isset( $decoded['data'] ) && is_array( $decoded['data'] ) ? $decoded['data'] : $decoded,
			);
		}

		$code    = is_array( $decoded ) && ! empty( $decoded['code'] ) && is_string( $decoded['code'] ) ? $decoded['code'] : 'spacefast_http_error';
		$message = is_array( $decoded ) && ! empty( $decoded['detail'] ) && is_string( $decoded['detail'] ) ? $decoded['detail'] : '';
		return array(
			'ok'      => false,
			'code'    => $code,
			'message' => $message,
			'status'  => $status,
		);
	}
}
