<?php
/**
 * Tests for Space_Url.
 *
 * @package Spacefast_Block
 */

use PHPUnit\Framework\TestCase;
use Spacefast_Block\Space_Url;

/**
 * @covers \Spacefast_Block\Space_Url
 */
final class SpaceUrlTest extends TestCase {

	public function test_normalize_adds_scheme_to_bare_host() {
		$this->assertSame( 'https://a.space.fast/', Space_Url::normalize( 'a.space.fast' ) );
	}

	public function test_normalize_keeps_only_scheme_and_host() {
		$this->assertSame( 'https://a.space.fast/', Space_Url::normalize( ' https://a.space.fast/x?y#z ' ) );
	}

	public function test_normalize_lowercases_host() {
		$this->assertSame( 'https://a.space.fast/', Space_Url::normalize( 'HTTPS://A.Space.Fast' ) );
	}

	/**
	 * @dataProvider rejected_inputs
	 */
	public function test_normalize_rejects( $input ) {
		$this->assertNull( Space_Url::normalize( $input ) );
	}

	public function rejected_inputs() {
		return array(
			'http'              => array( 'http://a.space.fast' ),
			'javascript'        => array( 'javascript:alert(1)' ),
			'userinfo'          => array( 'https://user:pw@a.space.fast' ),
			'user only'         => array( 'https://user@a.space.fast' ),
			'empty'             => array( '' ),
			'whitespace only'   => array( '   ' ),
			'ipv4'              => array( 'https://127.0.0.1' ),
			'ipv4 bare'         => array( '10.0.0.1' ),
			'ipv6'              => array( 'https://[::1]' ),
			'localhost'         => array( 'https://localhost' ),
			'localhost bare'    => array( 'localhost' ),
			'port'              => array( 'https://a.space.fast:8443' ),
			'backslash'         => array( 'https://a.space.fast\\@evil.com' ),
			'control character' => array( "https://a.space.fast\x00.evil.com" ),
			'inner space'       => array( 'https://a.space .fast' ),
			'data scheme'       => array( 'data:text/html,hi' ),
			'ftp'               => array( 'ftp://a.space.fast' ),
			'no dot'            => array( 'intranet' ),
			'empty label'       => array( 'https://a..fast' ),
			'numeric tld'       => array( 'https://127.1' ),
			'hex octet'         => array( 'https://127.0.0.0x1' ),
			'hex first octet'   => array( 'https://0x7f.0.0.0x1' ),
			'hex integer tld'   => array( 'https://a.0x7f000001' ),
			'localhost tld'     => array( 'https://foo.localhost' ),
			'local tld'         => array( 'https://printer.local' ),
			'internal tld'      => array( 'https://db.internal' ),
		);
	}

	public function test_frame_url_appends_relative_path() {
		$this->assertSame( 'https://a.space.fast/docs/intro', Space_Url::frame_url( 'https://a.space.fast/', 'docs/intro' ) );
	}

	public function test_frame_url_tolerates_leading_slash() {
		$this->assertSame( 'https://a.space.fast/docs', Space_Url::frame_url( 'https://a.space.fast/', '/docs' ) );
	}

	public function test_frame_url_keeps_query_and_fragment() {
		$this->assertSame(
			'https://a.space.fast/docs/intro?tab=2#top',
			Space_Url::frame_url( 'https://a.space.fast/', 'docs/intro?tab=2#top' )
		);
	}

	public function test_frame_url_empty_path_returns_base() {
		$this->assertSame( 'https://a.space.fast/', Space_Url::frame_url( 'https://a.space.fast/', '' ) );
	}

	/**
	 * @dataProvider unsafe_start_paths
	 */
	public function test_frame_url_returns_base_for( $path ) {
		$this->assertSame( 'https://a.space.fast/', Space_Url::frame_url( 'https://a.space.fast/', $path ) );
	}

	public function unsafe_start_paths() {
		return array(
			'protocol relative' => array( '//evil.com' ),
			'absolute url'      => array( 'https://evil.com/x' ),
			'parent traversal'  => array( '../..' ),
			'embedded parent'   => array( 'docs/../../x' ),
			'dot segment'       => array( './docs' ),
			'encoded dots'      => array( '%2e%2e/x' ),
			'backslash'         => array( 'docs\\intro' ),
			'leading backslash' => array( '\\\\evil.com' ),
			'control character' => array( "docs/\x01intro" ),
			'newline'           => array( "docs\nintro" ),
			'at sign'           => array( 'x@evil.com' ),
			'javascript'        => array( 'javascript:alert(1)' ),
			'space'             => array( 'docs/in tro' ),
			'bad percent'       => array( 'docs/%zz' ),
			'empty segment'     => array( 'docs//intro' ),
		);
	}

	/**
	 * @dataProvider invalid_bases
	 */
	public function test_frame_url_fails_closed_for_invalid_base( $base ) {
		$this->assertSame( '', Space_Url::frame_url( $base, 'docs' ) );
		$this->assertSame( '', Space_Url::frame_url( $base, '' ) );
	}

	public function invalid_bases() {
		return array(
			'http loopback' => array( 'http://127.0.0.1/' ),
			'javascript'    => array( 'javascript:alert(1)' ),
			'empty'         => array( '' ),
		);
	}

	public function test_hostname() {
		$this->assertSame( 'a.space.fast', Space_Url::hostname( 'https://a.space.fast/' ) );
	}

	public function test_hostname_of_garbage_is_empty() {
		$this->assertSame( '', Space_Url::hostname( 'not a url' ) );
	}
	/**
	 * @dataProvider frameable_hosts
	 */
	public function test_may_frame_host( $host, $verified, $expected ) {
		$this->assertSame( $expected, Space_Url::may_frame_host( $host, $verified ) );
	}

	public function frameable_hosts() {
		$verified = array( 'spaces.example.com' => 1700000000 );
		return array(
			'space.fast apex'         => array( 'space.fast', array(), true ),
			'space.fast subdomain'    => array( 'a.space.fast', array(), true ),
			'deep subdomain'          => array( 'a.b.space.fast', array(), true ),
			'verified host'           => array( 'spaces.example.com', $verified, true ),
			'unverified host'         => array( 'example.com', $verified, false ),
			'verified host uppercase' => array( 'Spaces.Example.com', $verified, true ),
			'lookalike suffix'        => array( 'evilspace.fast', array(), false ),
			'suffix trick'            => array( 'a.space.fast.evil.com', array(), false ),
			'subdomain of verified'   => array( 'x.spaces.example.com', $verified, false ),
			'empty host'              => array( '', array( '' => 1 ), false ),
			'nothing verified'        => array( 'example.com', array(), false ),
		);
	}

	public function test_with_verified_host_adds_the_host_with_its_time() {
		$this->assertSame(
			array(
				'a.example.com' => 100,
				'b.example.com' => 200,
			),
			Space_Url::with_verified_host( array( 'a.example.com' => 100 ), 'b.example.com', 200, 500 )
		);
	}

	public function test_with_verified_host_refreshes_a_known_host() {
		$this->assertSame(
			array( 'a.example.com' => 300 ),
			Space_Url::with_verified_host( array( 'a.example.com' => 100 ), 'A.Example.com', 300, 500 )
		);
	}

	public function test_with_verified_host_prunes_the_oldest_past_the_cap() {
		$hosts = array(
			'new.example.com'    => 300,
			'oldest.example.com' => 100,
			'older.example.com'  => 200,
		);
		$this->assertSame(
			array(
				'new.example.com'   => 300,
				'added.example.com' => 400,
			),
			Space_Url::with_verified_host( $hosts, 'added.example.com', 400, 2 )
		);
	}

	public function test_with_verified_host_drops_malformed_entries() {
		$this->assertSame(
			array( 'b.example.com' => 200 ),
			Space_Url::with_verified_host( array( 0 => 'junk', 'a.example.com' => 'soon' ), 'b.example.com', 200, 500 )
		);
	}

	public function test_with_verified_host_ignores_an_empty_host() {
		$this->assertSame(
			array( 'a.example.com' => 100 ),
			Space_Url::with_verified_host( array( 'a.example.com' => 100 ), '', 200, 500 )
		);
	}
}
