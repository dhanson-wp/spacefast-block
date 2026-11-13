<?php
/**
 * The public relay routes that serve a Space's page, stylesheets, scripts, and
 * fonts to the full-page frame from the site itself, so the page can report
 * its height to the block.
 *
 * Anyone can reach these routes, so they only ever fetch a host that may be
 * framed (Verified_Hosts::may_frame()) and a path Space_Relay::upstream_url()
 * allows. Every response is raw bytes with a fixed set of headers, always
 * including the CSP sandbox that keeps relayed documents in an opaque origin.
 * No upstream header is passed through.
 *
 * @package Spacefast_Block
 */

namespace Spacefast_Block;

use WP_REST_Request;
use WP_REST_Response;

/**
 * Registers and serves `spacefast-block/v1/page/…` and `…/asset/…`.
 */
class Relay_Controller {

	/**
	 * Seconds a rewritten page is kept, in a transient and in browsers.
	 */
	const PAGE_TTL = 300;

	/**
	 * Seconds browsers keep a relayed asset.
	 */
	const ASSET_TTL = 86400;

	/**
	 * The response the current request serves, set by a route callback and
	 * printed by serve().
	 *
	 * @var array|null
	 */
	private static $payload = null;

	/**
	 * Hooks the routes and the raw response.
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		// After rest_send_cors_headers(), whose headers serve() removes.
		add_filter( 'rest_pre_serve_request', array( __CLASS__, 'serve' ), 100, 3 );
	}

	/**
	 * The REST namespace URL relay URLs are built on.
	 *
	 * @return string
	 */
	public static function relay_base(): string {
		return rest_url( Rest_Controller::NAMESPACE . '/' );
	}

	/**
	 * Registers the page and asset routes. The host and path are path segments
	 * so relative URLs in relayed files keep resolving through the relay. The
	 * patterns accept anything; the callbacks decide.
	 */
	public static function register_routes(): void {
		foreach ( array( 'page', 'asset' ) as $kind ) {
			register_rest_route(
				Rest_Controller::NAMESPACE,
				'/' . $kind . '/(?P<host>[^/]*)(?:/(?P<path>.*))?',
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, $kind ),
					'permission_callback' => '__return_true',
				)
			);
		}
	}

	/**
	 * GET /page/<host>/<path>: the Space's HTML page, rewritten.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function page( WP_REST_Request $request ): WP_REST_Response {
		$upstream = self::upstream( $request );
		if ( is_int( $upstream ) ) {
			return self::page_error( $upstream );
		}

		$relay_base = self::relay_base();
		$cache_key  = Space_Relay::cache_key( $relay_base, $upstream );
		$cached     = get_transient( $cache_key );
		if ( is_array( $cached ) && isset( $cached['type'], $cached['body'] ) ) {
			return self::page_response( $cached['type'], $cached['body'] );
		}

		$fetched = self::fetch( $upstream, Space_Relay::PAGE_MAX_BYTES );
		if ( is_array( $fetched ) && '' !== $fetched['redirect'] ) {
			// Only one redirect, to the same path with a trailing slash.
			$next = Space_Relay::page_redirect_target( $upstream, $fetched['redirect'] );
			if ( null === $next ) {
				return self::page_error( 502 );
			}
			$upstream = $next;
			$fetched  = self::fetch( $upstream, Space_Relay::PAGE_MAX_BYTES );
		}
		if ( is_int( $fetched ) ) {
			return self::page_error( $fetched );
		}
		if ( '' !== $fetched['redirect'] ) {
			return self::page_error( 502 );
		}

		$type = Space_Relay::page_content_type( $fetched['type'] );
		if ( null === $type ) {
			return self::page_error( 415 );
		}

		$reporter = (string) file_get_contents( SPACEFAST_BLOCK_DIR . 'includes/relay-reporter.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A file shipped with the plugin.
		$page     = Relay_Page::rewrite( $fetched['body'], $upstream, $relay_base, $reporter );
		if ( false === strpos( $type, 'charset=' ) && '' !== $page['charset'] ) {
			$type .= '; charset=' . $page['charset'];
		}

		set_transient(
			$cache_key,
			array(
				'type' => $type,
				'body' => $page['html'],
			),
			self::PAGE_TTL
		);
		return self::page_response( $type, $page['html'] );
	}

	/**
	 * GET /asset/<host>/<path>: a stylesheet, script, or font from the Space.
	 * Stylesheets are rewritten so their fonts and imports come through the
	 * relay too.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function asset( WP_REST_Request $request ): WP_REST_Response {
		$upstream = self::upstream( $request );
		if ( is_int( $upstream ) ) {
			return self::asset_error( $upstream );
		}

		$fetched = self::fetch( $upstream, Space_Relay::ASSET_MAX_BYTES );
		if ( is_int( $fetched ) ) {
			return self::asset_error( $fetched );
		}
		if ( '' !== $fetched['redirect'] ) {
			return self::asset_error( 502 );
		}

		$type = Space_Relay::asset_content_type( $fetched['type'] );
		if ( null === $type ) {
			return self::asset_error( 415 );
		}

		$body = $fetched['body'];
		if ( Space_Relay::is_css( $type ) ) {
			$body = Space_Relay::rewrite_css( $body, $upstream, self::relay_base() );
		}
		return self::respond( 200, $type, $body, 'public, max-age=' . self::ASSET_TTL, true );
	}

	/**
	 * Prints the relay response as raw bytes with only the relay's headers.
	 * Hooked to `rest_pre_serve_request`; leaves every other route alone.
	 *
	 * @param bool                 $served  Whether the request was already served.
	 * @param mixed                $result  The response, unused: the payload holds the real one.
	 * @param WP_REST_Request|null $request The request being served.
	 * @return bool
	 */
	public static function serve( $served, $result = null, $request = null ) {
		if ( $served || null === self::$payload ) {
			return $served;
		}
		$route = $request instanceof WP_REST_Request ? $request->get_route() : '';
		if ( ! preg_match( '#^/' . preg_quote( Rest_Controller::NAMESPACE, '#' ) . '/(?:page|asset)/#', $route ) ) {
			return $served;
		}
		$payload       = self::$payload;
		self::$payload = null;

		if ( ! headers_sent() ) {
			// Drop the JSON type, CORS, Link, and no-cache headers the REST
			// server already sent, and any cookie.
			header_remove();
			status_header( $payload['status'] );
			foreach ( $payload['headers'] as $name => $value ) {
				header( $name . ': ' . $value );
			}
		}
		echo $payload['body']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Raw relayed bytes, served under a CSP sandbox with nosniff.
		return true;
	}

	/**
	 * The upstream URL for a request, or the status to refuse it with: 404 for
	 * a host that may not be framed, 400 for a path or query the URL rules
	 * refuse. Nothing is fetched for either.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return string|int
	 */
	private static function upstream( WP_REST_Request $request ) {
		// URL parameters only: a query parameter named `host` or `path` must not override them.
		$params = $request->get_url_params();
		$host   = strtolower( (string) ( $params['host'] ?? '' ) );
		if ( null === Space_Url::normalize( $host ) || ! Verified_Hosts::may_frame( 'https://' . $host . '/' ) ) {
			return 404;
		}

		$query_string = isset( $_SERVER['QUERY_STRING'] ) ? (string) wp_unslash( $_SERVER['QUERY_STRING'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated by Space_Relay::upstream_url().
		$upstream     = Space_Relay::upstream_url( $host, (string) ( $params['path'] ?? '' ), Space_Relay::forwarded_query( $query_string ) );
		return null === $upstream ? 400 : $upstream;
	}

	/**
	 * Fetches an upstream URL with no cookies, credentials, or visitor headers,
	 * following no redirects.
	 *
	 * @param string $url URL from Space_Relay.
	 * @param int    $max Largest body allowed, in bytes.
	 * @return array{type: string, body: string, redirect: string}|int The
	 *         response, or 502 when it failed, wasn't 200 or a redirect, or was too large.
	 */
	private static function fetch( string $url, int $max ) {
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 10,
				'redirection'         => 0,
				'reject_unsafe_urls'  => true,
				'limit_response_size' => $max + 1,
				'user-agent'          => 'spacefast-block/' . SPACEFAST_BLOCK_VERSION,
				'headers'             => array(),
				'cookies'             => array(),
			)
		);
		if ( is_wp_error( $response ) ) {
			return 502;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( $status >= 300 && $status < 400 ) {
			return array(
				'type'     => '',
				'body'     => '',
				'redirect' => (string) wp_remote_retrieve_header( $response, 'location' ),
			);
		}
		$body   = (string) wp_remote_retrieve_body( $response );
		$length = wp_remote_retrieve_header( $response, 'content-length' );
		if ( 200 !== $status || strlen( $body ) > $max || ( is_numeric( $length ) && (int) $length > $max ) ) {
			return 502;
		}
		return array(
			'type'     => (string) wp_remote_retrieve_header( $response, 'content-type' ),
			'body'     => $body,
			'redirect' => '',
		);
	}

	/**
	 * A relayed page.
	 *
	 * @param string $type Content-Type.
	 * @param string $body Page.
	 * @return WP_REST_Response
	 */
	private static function page_response( string $type, string $body ): WP_REST_Response {
		return self::respond( 200, $type, $body, 'public, max-age=' . self::PAGE_TTL, false );
	}

	/**
	 * A page the relay couldn't serve: a tiny document that tells the block at
	 * once, so it frames the Space directly without waiting.
	 *
	 * @param int $status HTTP status.
	 * @return WP_REST_Response
	 */
	private static function page_error( int $status ): WP_REST_Response {
		$body = '<!doctype html><meta charset="utf-8"><title>' . esc_html__( 'Space unavailable', 'spacefast-block' ) . '</title>'
			. '<script>parent.postMessage({spacefastBlock:"error",kind:"resource"},"*");</script>';
		return self::respond( $status, 'text/html; charset=utf-8', $body, 'no-store', false );
	}

	/**
	 * An asset the relay couldn't serve.
	 *
	 * @param int $status HTTP status.
	 * @return WP_REST_Response
	 */
	private static function asset_error( int $status ): WP_REST_Response {
		return self::respond( $status, 'text/plain; charset=utf-8', 'Unavailable', 'no-store', true );
	}

	/**
	 * Sets the response serve() prints, with exactly the relay's headers.
	 *
	 * @param int    $status        HTTP status.
	 * @param string $type          Content-Type.
	 * @param string $body          Body.
	 * @param string $cache_control Cache-Control.
	 * @param bool   $is_asset      Whether it is an asset, which any origin may read.
	 * @return WP_REST_Response A placeholder; serve() prints the real response.
	 */
	private static function respond( int $status, string $type, string $body, string $cache_control, bool $is_asset ): WP_REST_Response {
		$headers = array(
			'Content-Type'            => $type,
			'Content-Security-Policy' => Space_Relay::CSP,
			'X-Content-Type-Options'  => 'nosniff',
			'X-Robots-Tag'            => 'noindex',
			'Referrer-Policy'         => 'no-referrer',
			'Cache-Control'           => $cache_control,
		);
		if ( $is_asset ) {
			$headers['Access-Control-Allow-Origin'] = '*';
		}
		self::$payload = array(
			'status'  => $status,
			'headers' => $headers,
			'body'    => $body,
		);
		return new WP_REST_Response( null, $status );
	}
}
