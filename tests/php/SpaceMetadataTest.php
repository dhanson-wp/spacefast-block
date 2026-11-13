<?php
/**
 * Tests for Space_Metadata.
 *
 * @package Spacefast_Block
 */

use PHPUnit\Framework\TestCase;
use Spacefast_Block\Space_Metadata;

/**
 * @covers \Spacefast_Block\Space_Metadata
 */
final class SpaceMetadataTest extends TestCase {

	const URL = 'https://a.space.fast/';

	private function public_body() {
		return '<!doctype html><html><head><meta charset="utf-8">'
			. '<title>Tom &amp; Jerry&#8217;s   Space</title>'
			. '<meta name="description" content="Cats &amp; mice. &quot;Fun&quot;">'
			. '<meta property="og:image" content="https://a.space.fast/cover.png">'
			. '</head><body>Hi</body></html>';
	}

	public function test_public_space() {
		$meta = Space_Metadata::from_response( self::URL, 200, array( 'x-spacefast-runtime' => '1' ), $this->public_body() );

		$this->assertTrue( $meta['isSpace'] );
		$this->assertTrue( $meta['isFrameable'] );
		$this->assertFalse( $meta['isPrivate'] );
		$this->assertSame( "Tom & Jerry\u{2019}s Space", $meta['title'] );
		$this->assertSame( 'Cats & mice. "Fun"', $meta['description'] );
		$this->assertSame( 'https://a.space.fast/cover.png', $meta['imageUrl'] );
		$this->assertSame( 'a.space.fast', $meta['hostname'] );
		$this->assertSame( self::URL, $meta['url'] );
	}

	public function test_result_has_exactly_the_documented_keys() {
		$meta = Space_Metadata::from_response( self::URL, 200, array(), '' );
		$keys = array_keys( $meta );
		sort( $keys );
		$this->assertSame(
			array( 'description', 'hostname', 'imageUrl', 'isFrameable', 'isPrivate', 'isSpace', 'title', 'url' ),
			$keys
		);
	}

	public function test_private_space() {
		$headers = array(
			'x-spacefast-runtime'     => '1',
			'content-security-policy' => "frame-ancestors 'none'",
		);
		$meta    = Space_Metadata::from_response( self::URL, 403, $headers, $this->public_body() );

		$this->assertTrue( $meta['isSpace'] );
		$this->assertFalse( $meta['isFrameable'] );
		$this->assertTrue( $meta['isPrivate'] );
		$this->assertSame( '', $meta['title'] );
		$this->assertSame( '', $meta['description'] );
		$this->assertSame( '', $meta['imageUrl'] );
		$this->assertSame( 'a.space.fast', $meta['hostname'] );
	}

	/**
	 * @dataProvider frame_blocking_x_frame_options
	 */
	public function test_x_frame_options_blocks_framing( $value ) {
		$headers = array(
			'x-spacefast-runtime' => '1',
			'x-frame-options'     => $value,
		);
		$meta    = Space_Metadata::from_response( self::URL, 200, $headers, $this->public_body() );

		$this->assertTrue( $meta['isSpace'] );
		$this->assertFalse( $meta['isFrameable'] );
		$this->assertFalse( $meta['isPrivate'] );
	}

	public function frame_blocking_x_frame_options() {
		return array(
			'DENY'       => array( 'DENY' ),
			'SAMEORIGIN' => array( 'SAMEORIGIN' ),
			'lowercase'  => array( 'deny' ),
		);
	}

	public function test_frame_ancestors_self_blocks_framing_on_a_200() {
		$headers = array(
			'x-spacefast-runtime'     => '1',
			'content-security-policy' => "default-src 'self'; frame-ancestors 'self'",
		);
		$meta    = Space_Metadata::from_response( self::URL, 200, $headers, '' );

		$this->assertFalse( $meta['isFrameable'] );
		$this->assertFalse( $meta['isPrivate'] );
	}

	public function test_frame_ancestors_wildcard_allows_framing() {
		$headers = array(
			'x-spacefast-runtime'     => '1',
			'content-security-policy' => 'frame-ancestors *',
		);
		$meta    = Space_Metadata::from_response( self::URL, 200, $headers, '' );

		$this->assertTrue( $meta['isFrameable'] );
	}

	public function test_missing_runtime_header_is_not_a_space() {
		$meta = Space_Metadata::from_response( self::URL, 200, array( 'content-type' => 'text/html' ), $this->public_body() );

		$this->assertFalse( $meta['isSpace'] );
		$this->assertFalse( $meta['isFrameable'] );
		$this->assertFalse( $meta['isPrivate'] );
	}

	public function test_private_status_without_runtime_header_is_not_private_space() {
		$meta = Space_Metadata::from_response( self::URL, 403, array(), '' );

		$this->assertFalse( $meta['isSpace'] );
		$this->assertFalse( $meta['isPrivate'] );
	}

	public function test_empty_body() {
		$meta = Space_Metadata::from_response( self::URL, 200, array( 'x-spacefast-runtime' => '1' ), '' );

		$this->assertTrue( $meta['isSpace'] );
		$this->assertTrue( $meta['isFrameable'] );
		$this->assertSame( '', $meta['title'] );
		$this->assertSame( '', $meta['description'] );
		$this->assertSame( '', $meta['imageUrl'] );
		$this->assertSame( 'a.space.fast', $meta['hostname'] );
	}

	/**
	 * @dataProvider unusable_images
	 */
	public function test_unusable_og_image_is_dropped( $image ) {
		$body = '<html><head><meta property="og:image" content="' . $image . '"></head></html>';
		$meta = Space_Metadata::from_response( self::URL, 200, array( 'x-spacefast-runtime' => '1' ), $body );

		$this->assertSame( '', $meta['imageUrl'] );
	}

	public function unusable_images() {
		return array(
			'http'              => array( 'http://a.space.fast/cover.png' ),
			'relative'          => array( '/cover.png' ),
			'javascript'        => array( 'javascript:alert(1)' ),
			'data'              => array( 'data:image/png;base64,AAAA' ),
			'userinfo'          => array( 'https://user:pw@a.space.fast/c.png' ),
			'empty'             => array( '' ),
			'protocol relative' => array( '//a.space.fast/c.png' ),
		);
	}

	public function test_header_values_may_be_arrays() {
		$headers = array(
			'x-spacefast-runtime' => array( '1' ),
			'x-frame-options'     => array( 'DENY' ),
		);
		$meta    = Space_Metadata::from_response( self::URL, 200, $headers, '' );

		$this->assertTrue( $meta['isSpace'] );
		$this->assertFalse( $meta['isFrameable'] );
	}

	public function test_title_decodes_entities_and_collapses_whitespace() {
		$body = "<html><head><title>\n  Caf&eacute;\t&amp;\n   Bar  &#169;  </title></head></html>";
		$meta = Space_Metadata::from_response( self::URL, 200, array( 'x-spacefast-runtime' => '1' ), $body );

		$this->assertSame( "Caf\u{e9} & Bar \u{a9}", $meta['title'] );
	}

	public function test_title_and_description_never_contain_markup() {
		$body = '<html><head><title>&lt;script&gt;alert(1)&lt;/script&gt;Safe &lt;b&gt;bold&lt;/b&gt;</title>'
			. '<meta name="description" content="&lt;img src=x onerror=alert(1)&gt;Plain text"></head></html>';
		$meta = Space_Metadata::from_response( self::URL, 200, array( 'x-spacefast-runtime' => '1' ), $body );

		$this->assertStringNotContainsString( '<', $meta['title'] );
		$this->assertStringNotContainsString( '>', $meta['title'] );
		$this->assertStringNotContainsString( '<', $meta['description'] );
		$this->assertStringNotContainsString( '>', $meta['description'] );
		$this->assertStringContainsString( 'Safe', $meta['title'] );
		$this->assertStringContainsString( 'Plain text', $meta['description'] );
	}

	public function test_falls_back_to_open_graph_title_and_description() {
		$body = '<html><head><meta property="og:title" content="OG title">'
			. '<meta property="og:description" content="OG description"></head></html>';
		$meta = Space_Metadata::from_response( self::URL, 200, array( 'x-spacefast-runtime' => '1' ), $body );

		$this->assertSame( 'OG title', $meta['title'] );
		$this->assertSame( 'OG description', $meta['description'] );
	}

	public function test_malformed_html_does_not_emit_warnings() {
		$body = '<html><head><title>Broken<meta name="description" content="x"><body><div></span>';
		$meta = Space_Metadata::from_response( self::URL, 200, array( 'x-spacefast-runtime' => '1' ), $body );

		$this->assertTrue( $meta['isSpace'] );
	}

	public function test_hostname_comes_from_the_url() {
		$meta = Space_Metadata::from_response( 'https://other.space.fast/', 200, array( 'x-spacefast-runtime' => '1' ), '' );

		$this->assertSame( 'other.space.fast', $meta['hostname'] );
		$this->assertSame( 'https://other.space.fast/', $meta['url'] );
	}
}
