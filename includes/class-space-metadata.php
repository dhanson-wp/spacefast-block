<?php
/**
 * Space metadata from an HTTP response.
 *
 * Pure PHP; this file must not call any WordPress function.
 *
 * @package Spacefast_Block
 */

namespace Spacefast_Block;

use DOMDocument;
use DOMXPath;

/**
 * Turns a fetched response into the facts the block stores.
 */
class Space_Metadata {

	/**
	 * Builds block metadata from a response.
	 *
	 * Title, description, and image come only from a successful (2xx) Space
	 * response; a private Space reveals nothing beyond its hostname.
	 *
	 * @param string $url     Space URL that was fetched.
	 * @param int    $status  HTTP status code.
	 * @param array  $headers Response headers, names lowercased; values are strings or arrays of strings.
	 * @param string $body    Response body.
	 * @return array{isSpace:bool,isFrameable:bool,isPrivate:bool,title:string,description:string,imageUrl:string,hostname:string,url:string}
	 */
	public static function from_response( string $url, int $status, array $headers, string $body ): array {
		$is_space = '' !== self::header( $headers, 'x-spacefast-runtime' );
		$is_ok    = $status >= 200 && $status < 300;

		$result = array(
			'isSpace'     => $is_space,
			'isFrameable' => $is_space && $is_ok && ! self::blocks_framing( $headers ),
			'isPrivate'   => $is_space && ( 401 === $status || 403 === $status ),
			'title'       => '',
			'description' => '',
			'imageUrl'    => '',
			'hostname'    => Space_Url::hostname( $url ),
			'url'         => $url,
		);

		if ( $is_space && $is_ok && '' !== trim( $body ) ) {
			$result = array_merge( $result, self::parse_body( $body ) );
		}

		return $result;
	}

	/**
	 * Reads title, description, and image from HTML.
	 *
	 * @param string $body HTML.
	 * @return array{title:string,description:string,imageUrl:string}
	 */
	private static function parse_body( string $body ): array {
		$previous = libxml_use_internal_errors( true );
		$document = new DOMDocument();
		// The XML declaration makes libxml read the markup as UTF-8.
		$loaded = $document->loadHTML( '<?xml encoding="utf-8" ?>' . $body );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( ! $loaded ) {
			return array(
				'title'       => '',
				'description' => '',
				'imageUrl'    => '',
			);
		}

		$xpath = new DOMXPath( $document );

		$title = self::first_text( $xpath, '//title' );
		if ( '' === $title ) {
			$title = self::meta( $xpath, 'property', 'og:title' );
		}
		$description = self::meta( $xpath, 'name', 'description' );
		if ( '' === $description ) {
			$description = self::meta( $xpath, 'property', 'og:description' );
		}

		return array(
			'title'       => self::plain_text( $title ),
			'description' => self::plain_text( $description ),
			'imageUrl'    => self::https_url( self::meta( $xpath, 'property', 'og:image' ) ),
		);
	}

	/**
	 * Text content of the first node matching a query.
	 *
	 * @param DOMXPath $xpath Document.
	 * @param string   $query XPath query.
	 * @return string
	 */
	private static function first_text( DOMXPath $xpath, string $query ): string {
		$nodes = $xpath->query( $query );
		return ( $nodes && $nodes->length ) ? (string) $nodes->item( 0 )->textContent : '';
	}

	/**
	 * Content of the first meta tag with a given attribute value.
	 *
	 * @param DOMXPath $xpath     Document.
	 * @param string   $attribute `name` or `property`.
	 * @param string   $value     Attribute value to match.
	 * @return string
	 */
	private static function meta( DOMXPath $xpath, string $attribute, string $value ): string {
		$nodes = $xpath->query( sprintf( '//meta[@%s="%s"]/@content', $attribute, $value ) );
		return ( $nodes && $nodes->length ) ? (string) $nodes->item( 0 )->nodeValue : '';
	}

	/**
	 * Strips any markup (including markup that was entity-encoded) and collapses whitespace.
	 *
	 * @param string $text Decoded text.
	 * @return string
	 */
	private static function plain_text( string $text ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- No WordPress available here.
		$text = strip_tags( $text );
		$text = str_replace( array( '<', '>' ), '', $text );
		$text = preg_replace( '/[\s\x00-\x1f\x7f]+/u', ' ', $text );
		return trim( (string) $text );
	}

	/**
	 * Returns the URL only when it is an absolute https URL without credentials.
	 *
	 * @param string $value Candidate URL.
	 * @return string
	 */
	private static function https_url( string $value ): string {
		$value = trim( $value );
		if ( '' === $value || preg_match( '/[\x00-\x20\x7f\\\\"\'<>]/', $value ) ) {
			return '';
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- No WordPress available here.
		$parts = parse_url( $value );
		if ( ! is_array( $parts ) || ! isset( $parts['scheme'], $parts['host'] ) ) {
			return '';
		}
		if ( 'https' !== strtolower( $parts['scheme'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return '';
		}
		return $value;
	}

	/**
	 * Whether the headers forbid framing by another origin.
	 *
	 * A `frame-ancestors` list counts as blocking unless it contains `*`, because
	 * the site embedding the Space can't be assumed to be on that list.
	 *
	 * @param array $headers Response headers, names lowercased.
	 * @return bool
	 */
	private static function blocks_framing( array $headers ): bool {
		if ( '' !== self::header( $headers, 'x-frame-options' ) ) {
			return true;
		}
		$csp = self::header( $headers, 'content-security-policy' );
		foreach ( explode( ';', $csp ) as $directive ) {
			$tokens = preg_split( '/\s+/', trim( strtolower( $directive ) ) );
			if ( 'frame-ancestors' === $tokens[0] ) {
				return ! in_array( '*', $tokens, true );
			}
		}
		return false;
	}

	/**
	 * A header value as a single trimmed string.
	 *
	 * @param array  $headers Response headers, names lowercased.
	 * @param string $name    Lowercase header name.
	 * @return string
	 */
	private static function header( array $headers, string $name ): string {
		if ( ! isset( $headers[ $name ] ) ) {
			return '';
		}
		$value = $headers[ $name ];
		if ( is_array( $value ) ) {
			$value = implode( ', ', array_filter( array_map( 'strval', $value ) ) );
		}
		return trim( (string) $value );
	}
}
