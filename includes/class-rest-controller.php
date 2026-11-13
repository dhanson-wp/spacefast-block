<?php
/**
 * REST routes for the editor.
 *
 * @package Spacefast_Block
 */

namespace Spacefast_Block;

use WP_REST_Response;
use WP_REST_Server;

/**
 * Registers the `spacefast-block/v1` routes. No response ever carries the key.
 */
class Rest_Controller {

	const NAMESPACE = 'spacefast-block/v1';

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
}
