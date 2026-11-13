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
	 * The key is sent only to the API host. If the API answers with a redirect
	 * (for example to an image host), the redirect target is checked against the
	 * same host rules as every other URL and fetched without the key.
	 *
	 * @param string $path Path under the API base, starting with a slash.
	 * @param string $key  API key sent as a bearer token.
	 * @return array{body: string, content_type: string}|WP_Error
	 */
	public static function request_raw( string $path, string $key ) {
		$unreachable = new WP_Error(
			'spacefast_unreachable',
			__( 'Could not reach Spacefast. Try again in a moment.', 'spacefast-block' ),
			array( 'status' => 502 )
		);
		$options     = array(
			'timeout'             => 15,
			'redirection'         => 0,
			'limit_response_size' => self::MAX_RAW_BYTES,
			'headers'             => array(
				'Authorization' => 'Bearer ' . $key,
				'User-Agent'    => 'spacefast-block/' . SPACEFAST_BLOCK_VERSION,
			),
		);

		$response = wp_remote_get( self::BASE_URL . $path, $options );
		if ( is_wp_error( $response ) ) {
			return $unreachable;
		}
		$status = (int) wp_remote_retrieve_response_code( $response );

		if ( $status >= 300 && $status < 400 ) {
			$location = (string) wp_remote_retrieve_header( $response, 'location' );
			if ( ! Space_Lookup::is_valid_sideload_url( $location ) ) {
				return $unreachable;
			}
			unset( $options['headers']['Authorization'] );
			$options['reject_unsafe_urls'] = true;
			$response                      = wp_safe_remote_get( $location, $options );
			if ( is_wp_error( $response ) ) {
				return $unreachable;
			}
			$status = (int) wp_remote_retrieve_response_code( $response );
		}

		if ( $status < 200 || $status >= 300 ) {
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
