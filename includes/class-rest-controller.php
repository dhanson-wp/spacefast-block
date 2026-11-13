<?php
/**
 * REST routes for the editor.
 *
 * @package Spacefast_Block
 */

namespace Spacefast_Block;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Registers the `spacefast-block/v1` routes. No response ever carries the key.
 */
class Rest_Controller {

	const NAMESPACE = 'spacefast-block/v1';

	const RESOLVE_CACHE_PREFIX = 'spacefast_block_resolve_';
	const RESOLVE_CACHE_TTL    = 600;
	const RESOLVE_MAX_BYTES    = 524288;
	const IMAGE_MAX_BYTES      = 10485760;

	/**
	 * Image types accepted for preview images, by MIME type.
	 */
	const IMAGE_EXTENSIONS = array(
		'image/jpeg' => 'jpg',
		'image/png'  => 'png',
		'image/gif'  => 'gif',
		'image/webp' => 'webp',
		'image/avif' => 'avif',
	);

	/**
	 * Hooks route registration.
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Registers every route.
	 */
	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/connection',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_connection' ),
					'permission_callback' => array( __CLASS__, 'can_edit' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'start_connection' ),
					'permission_callback' => array( __CLASS__, 'can_edit' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'delete_connection' ),
					'permission_callback' => array( __CLASS__, 'can_edit' ),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/connection/poll',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'poll_connection' ),
				'permission_callback' => array( __CLASS__, 'can_edit' ),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/spaces',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'search_spaces' ),
				'permission_callback' => array( __CLASS__, 'can_edit' ),
				'args'                => array(
					'search' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/resolve',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'resolve_url' ),
				'permission_callback' => array( __CLASS__, 'can_edit' ),
				'args'                => array(
					'url'     => array(
						'type'     => 'string',
						'required' => true,
					),
					'spaceId' => array(
						'type'    => 'string',
						'default' => '',
					),
					'refresh' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/sideload',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'sideload_image' ),
				'permission_callback' => array( __CLASS__, 'can_upload' ),
				'args'                => array(
					'url'     => array( 'type' => 'string' ),
					'spaceId' => array( 'type' => 'string' ),
					'postId'  => array(
						'type'    => 'integer',
						'default' => 0,
					),
				),
			)
		);
	}

	/**
	 * Permission check for routes that add to the media library.
	 *
	 * @return bool
	 */
	public static function can_upload(): bool {
		return current_user_can( 'upload_files' );
	}

	/**
	 * Permission check shared by every route.
	 *
	 * @return bool
	 */
	public static function can_edit(): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * GET /connection.
	 *
	 * @return WP_REST_Response
	 */
	public static function get_connection(): WP_REST_Response {
		return new WP_REST_Response( array( 'connected' => Connection::is_connected( get_current_user_id() ) ) );
	}

	/**
	 * POST /connection.
	 *
	 * @return WP_REST_Response|\WP_Error
	 */
	public static function start_connection() {
		$result = Connection::start( get_current_user_id() );
		return is_wp_error( $result ) ? $result : new WP_REST_Response( $result );
	}

	/**
	 * POST /connection/poll.
	 *
	 * @return WP_REST_Response
	 */
	public static function poll_connection(): WP_REST_Response {
		return new WP_REST_Response( array( 'status' => Connection::poll( get_current_user_id() ) ) );
	}

	/**
	 * DELETE /connection.
	 *
	 * @return WP_REST_Response
	 */
	public static function delete_connection(): WP_REST_Response {
		Connection::clear( get_current_user_id() );
		return new WP_REST_Response( array( 'connected' => false ) );
	}

	/**
	 * GET /spaces. Lists the connected user's Spaces, optionally filtered.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function search_spaces( WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		$empty   = new WP_REST_Response(
			array(
				'connected' => false,
				'spaces'    => array(),
			)
		);
		if ( ! Connection::is_connected( $user_id ) ) {
			return $empty;
		}

		$rows = Connection::request(
			$user_id,
			'GET',
			'/spaces',
			array(
				'query' => (string) $request->get_param( 'search' ),
				'limit' => Space_Lookup::MAX_RESULTS,
			)
		);
		if ( is_wp_error( $rows ) ) {
			// A dead key was just cleared, so the editor should offer to connect again.
			return Connection::is_connected( $user_id ) ? $rows : $empty;
		}

		return new WP_REST_Response(
			array(
				'connected' => true,
				'spaces'    => Space_Lookup::map_spaces( $rows ),
			)
		);
	}

	/**
	 * GET /resolve. Fetches a pasted URL once and reports what it is.
	 *
	 * The server only ever requests the output of Space_Url::normalize().
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function resolve_url( WP_REST_Request $request ) {
		$url = Space_Url::normalize( (string) $request->get_param( 'url' ) );
		if ( null === $url ) {
			return new WP_Error(
				'spacefast_invalid_url',
				__( 'Enter a link that starts with https:// and points to a public site.', 'spacefast-block' ),
				array( 'status' => 400 )
			);
		}

		$space_id = (string) $request->get_param( 'spaceId' );
		$space_id = Space_Lookup::is_valid_space_id( $space_id ) ? $space_id : '';

		$cache_key = self::RESOLVE_CACHE_PREFIX . md5( $url );
		if ( ! $request->get_param( 'refresh' ) ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) ) {
				return new WP_REST_Response( array_merge( $cached, array( 'spaceId' => $space_id ) ) );
			}
		}

		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 10,
				'redirection'         => 0,
				'reject_unsafe_urls'  => true,
				'limit_response_size' => self::RESOLVE_MAX_BYTES,
				'headers'             => array( 'Accept' => 'text/html' ),
				'user-agent'          => 'spacefast-block/' . SPACEFAST_BLOCK_VERSION,
			)
		);
		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'spacefast_unreachable',
				__( 'We could not reach that link. Check the address and try again.', 'spacefast-block' ),
				array( 'status' => 502 )
			);
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( $status >= 300 && $status < 400 ) {
			return self::not_a_space();
		}

		$headers     = array();
		$raw_headers = wp_remote_retrieve_headers( $response );
		$raw_headers = is_object( $raw_headers ) && method_exists( $raw_headers, 'getAll' ) ? $raw_headers->getAll() : (array) $raw_headers;
		foreach ( $raw_headers as $name => $value ) {
			$headers[ strtolower( (string) $name ) ] = $value;
		}
		$metadata = Space_Metadata::from_response( $url, $status, $headers, (string) wp_remote_retrieve_body( $response ) );
		if ( ! $metadata['isSpace'] ) {
			return self::not_a_space();
		}

		set_transient( $cache_key, $metadata, self::RESOLVE_CACHE_TTL );
		return new WP_REST_Response( array_merge( $metadata, array( 'spaceId' => $space_id ) ) );
	}

	/**
	 * POST /sideload. Copies a preview image into the media library.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function sideload_image( WP_REST_Request $request ) {
		$url      = $request->get_param( 'url' );
		$space_id = $request->get_param( 'spaceId' );
		$has_url  = is_string( $url ) && '' !== $url;
		$has_id   = is_string( $space_id ) && '' !== $space_id;

		if ( $has_url === $has_id ) {
			return new WP_Error(
				'spacefast_invalid_request',
				__( 'Choose either an image link or a Space.', 'spacefast-block' ),
				array( 'status' => 400 )
			);
		}

		$post_id = absint( $request->get_param( 'postId' ) );
		if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
			$post_id = 0;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		if ( $has_id ) {
			return self::sideload_thumbnail( (string) $space_id, $post_id );
		}
		return self::sideload_remote_image( (string) $url, $post_id );
	}

	/**
	 * Sideloads an https image URL.
	 *
	 * @param string $url     Image URL.
	 * @param int    $post_id Parent post, or 0.
	 * @return WP_REST_Response|WP_Error
	 */
	private static function sideload_remote_image( string $url, int $post_id ) {
		if ( ! Space_Lookup::is_valid_sideload_url( $url ) ) {
			return new WP_Error(
				'spacefast_invalid_image_url',
				__( 'Enter an image link that starts with https:// and points to a public site.', 'spacefast-block' ),
				array( 'status' => 400 )
			);
		}

		$limit = static function ( $args ) {
			$args['limit_response_size'] = self::IMAGE_MAX_BYTES;
			return $args;
		};
		add_filter( 'http_request_args', $limit );
		$tmp = download_url( $url, 15 );
		remove_filter( 'http_request_args', $limit );
		if ( is_wp_error( $tmp ) ) {
			return new WP_Error(
				'spacefast_image_download_failed',
				__( 'We could not download that image.', 'spacefast-block' ),
				array( 'status' => 502 )
			);
		}

		$name = pathinfo( (string) wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_FILENAME );
		return self::attach_image( $tmp, '' !== $name ? $name : 'space-preview', $post_id, '' );
	}

	/**
	 * Sideloads a Space's thumbnail, fetched with the user's key.
	 *
	 * @param string $space_id Space ID.
	 * @param int    $post_id  Parent post, or 0.
	 * @return WP_REST_Response|WP_Error
	 */
	private static function sideload_thumbnail( string $space_id, int $post_id ) {
		if ( ! Space_Lookup::is_valid_space_id( $space_id ) ) {
			return new WP_Error(
				'spacefast_invalid_space',
				__( 'That Space could not be found.', 'spacefast-block' ),
				array( 'status' => 400 )
			);
		}
		$user_id = get_current_user_id();
		$key     = Connection::get_key( $user_id );
		if ( null === $key ) {
			return new WP_Error(
				'spacefast_not_connected',
				__( 'Connect your Spacefast account first.', 'spacefast-block' ),
				array( 'status' => 401 )
			);
		}

		$image = Api_Client::request_raw( '/spaces/' . $space_id . '/thumbnail', $key );
		if ( is_wp_error( $image ) ) {
			if ( 401 === ( $image->get_error_data()['status'] ?? 0 ) ) {
				Connection::clear( $user_id );
			}
			return $image;
		}

		$tmp = wp_tempnam( $space_id );
		if ( ! $tmp || false === file_put_contents( $tmp, $image['body'] ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			return new WP_Error(
				'spacefast_image_save_failed',
				__( 'We could not save that image.', 'spacefast-block' ),
				array( 'status' => 500 )
			);
		}

		// The Space title is the alt text. A failed lookup just leaves it empty.
		$details = Connection::request( $user_id, 'GET', '/spaces/' . $space_id );
		$alt     = is_array( $details ) && isset( $details['title'] ) && is_string( $details['title'] ) ? $details['title'] : '';

		return self::attach_image( $tmp, 'space-preview-' . substr( $space_id, 4, 8 ), $post_id, $alt );
	}

	/**
	 * Checks a downloaded file is an allowed image and adds it to the media library.
	 *
	 * Always removes the temporary file.
	 *
	 * @param string $tmp     Temporary file path.
	 * @param string $name    File name without extension.
	 * @param int    $post_id Parent post, or 0.
	 * @param string $alt     Alt text.
	 * @return WP_REST_Response|WP_Error
	 */
	private static function attach_image( string $tmp, string $name, int $post_id, string $alt ) {
		$mime    = wp_get_image_mime( $tmp );
		$allowed = is_string( $mime ) && isset( self::IMAGE_EXTENSIONS[ $mime ] ) && in_array( $mime, get_allowed_mime_types(), true );
		if ( ! $allowed || filesize( $tmp ) > self::IMAGE_MAX_BYTES ) {
			wp_delete_file( $tmp );
			return new WP_Error(
				'spacefast_not_an_image',
				__( 'That file is not an image type WordPress accepts.', 'spacefast-block' ),
				array( 'status' => 422 )
			);
		}

		$id = media_handle_sideload(
			array(
				'name'     => sanitize_file_name( $name . '.' . self::IMAGE_EXTENSIONS[ $mime ] ),
				'tmp_name' => $tmp,
			),
			$post_id
		);
		if ( is_wp_error( $id ) ) {
			wp_delete_file( $tmp );
			return new WP_Error(
				'spacefast_image_save_failed',
				__( 'We could not save that image.', 'spacefast-block' ),
				array( 'status' => 500 )
			);
		}
		if ( '' !== $alt ) {
			update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( $alt ) );
		}

		return new WP_REST_Response(
			array(
				'id'  => $id,
				'url' => (string) wp_get_attachment_url( $id ),
			)
		);
	}

	/**
	 * The error for a URL that responds but isn't a Space.
	 *
	 * @return WP_Error
	 */
	private static function not_a_space(): WP_Error {
		return new WP_Error(
			'spacefast_not_a_space',
			__( "That link isn't a Spacefast Space.", 'spacefast-block' ),
			array( 'status' => 422 )
		);
	}
}
