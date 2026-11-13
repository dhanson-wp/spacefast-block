<?php
/**
 * Tests for Space_Lookup.
 *
 * @package Spacefast_Block
 */

use PHPUnit\Framework\TestCase;
use Spacefast_Block\Space_Lookup;

class SpaceLookupTest extends TestCase {

	public function test_maps_a_spacefast_list_payload() {
		$spaces = Space_Lookup::map_spaces(
			array(
				array(
					'id'              => 'spc_0123456789abcdef0123456789abcdef',
					'title'           => 'Hallmark',
					'teamSlug'        => 'acme',
					'defaultHostname' => 'a.space.fast',
					'liveUrl'         => 'https://a.space.fast/',
					'extra'           => 'ignored',
				),
			)
		);
		$this->assertSame(
			array(
				array(
					'id'       => 'spc_0123456789abcdef0123456789abcdef',
					'title'    => 'Hallmark',
					'team'     => 'acme',
					'hostname' => 'a.space.fast',
					'url'      => 'https://a.space.fast/',
				),
			),
			$spaces
		);
	}

	public function test_skips_malformed_rows_and_caps_at_twenty() {
		$rows = array( 'junk', array( 'title' => 'No id' ), array( 'id' => 'nope' ) );
		for ( $i = 0; $i < 30; $i++ ) {
			$rows[] = array(
				'id'              => 'spc_' . str_pad( dechex( $i ), 32, '0', STR_PAD_LEFT ),
				'title'           => 'S' . $i,
				'defaultHostname' => 's' . $i . '.space.fast',
			);
		}
		$spaces = Space_Lookup::map_spaces( $rows );
		$this->assertCount( 20, $spaces );
		$this->assertSame( '', $spaces[0]['team'] );
		$this->assertSame( 'https://s0.space.fast/', $spaces[0]['url'] );
	}

	public function test_url_falls_back_to_hostname_and_title_to_hostname() {
		$spaces = Space_Lookup::map_spaces(
			array(
				array(
					'id'              => 'spc_0123456789abcdef0123456789abcdef',
					'defaultHostname' => 'x.space.fast',
				),
			)
		);
		$this->assertSame( 'x.space.fast', $spaces[0]['title'] );
		$this->assertSame( 'https://x.space.fast/', $spaces[0]['url'] );
	}

	public function test_non_array_payload_maps_to_empty_list() {
		$this->assertSame( array(), Space_Lookup::map_spaces( array( 'oops' => true ) ) );
	}

	/**
	 * @dataProvider space_ids
	 */
	public function test_space_id_validation( $id, $valid ) {
		$this->assertSame( $valid, Space_Lookup::is_valid_space_id( $id ) );
	}

	public function space_ids() {
		return array(
			'valid'          => array( 'spc_0123456789abcdef0123456789abcdef', true ),
			'uppercase'      => array( 'spc_0123456789ABCDEF0123456789abcdef', false ),
			'too short'      => array( 'spc_0123', false ),
			'too long'       => array( 'spc_0123456789abcdef0123456789abcdef0', false ),
			'path traversal' => array( 'spc_0123456789abcdef0123456789abcd/../', false ),
			'trailing nl'    => array( "spc_0123456789abcdef0123456789abcdef\n", false ),
			'empty'          => array( '', false ),
			'non string'     => array( array( 'x' ), false ),
		);
	}

	/**
	 * @dataProvider sideload_urls
	 */
	public function test_sideload_url_validation( $url, $valid ) {
		$this->assertSame( $valid, Space_Lookup::is_valid_sideload_url( $url ) );
	}

	public function sideload_urls() {
		return array(
			'https image'  => array( 'https://cdn.example.com/a/b.png?x=1', true ),
			'http'         => array( 'http://cdn.example.com/b.png', false ),
			'ip literal'   => array( 'https://127.0.0.1/b.png', false ),
			'ipv6'         => array( 'https://[::1]/b.png', false ),
			'localhost'    => array( 'https://localhost/b.png', false ),
			'local tld'    => array( 'https://nas.local/b.png', false ),
			'port'         => array( 'https://cdn.example.com:8443/b.png', false ),
			'credentials'  => array( 'https://u:p@cdn.example.com/b.png', false ),
			'file'         => array( 'file:///etc/passwd', false ),
			'javascript'   => array( 'javascript:alert(1)', false ),
			'bare host'    => array( 'cdn.example.com/b.png', false ),
			'whitespace'   => array( "https://cdn.example.com/b.png\n", false ),
			'backslash'    => array( 'https://cdn.example.com\\@evil.com/b.png', false ),
			'empty'        => array( '', false ),
		);
	}

	/**
	 * @dataProvider redirects
	 */
	public function test_next_redirect_url( $base, $location, $expected ) {
		$this->assertSame( $expected, Space_Lookup::next_redirect_url( $base, $location ) );
	}

	public function redirects() {
		$api = 'https://api.spacefast.com/v1/spaces/x/thumbnail';
		return array(
			'absolute https'     => array( $api, 'https://cdn.example.com/a.png', 'https://cdn.example.com/a.png' ),
			'http'               => array( $api, 'http://cdn.example.com/a.png', null ),
			'ip literal'         => array( $api, 'https://93.184.216.34/a.png', null ),
			'port'               => array( $api, 'https://cdn.example.com:8443/a.png', null ),
			'credentials'        => array( $api, 'https://u@cdn.example.com/a.png', null ),
			'root relative'      => array( $api, '/v1/files/a.png', 'https://api.spacefast.com/v1/files/a.png' ),
			'path relative'      => array( $api, 'a.png', 'https://api.spacefast.com/v1/spaces/x/a.png' ),
			'protocol relative'  => array( $api, '//cdn.example.com/a.png', 'https://cdn.example.com/a.png' ),
			'protocol rel to ip' => array( $api, '//10.0.0.1/a.png', null ),
			'javascript'         => array( $api, 'javascript:alert(1)', null ),
			'empty'              => array( $api, '', null ),
			'backslash trick'    => array( $api, '/\\evil.com/a.png', null ),
		);
	}

	/**
	 * @dataProvider key_targets
	 */
	public function test_key_is_only_sent_to_the_api_host( $url, $expected ) {
		$this->assertSame( $expected, Space_Lookup::should_send_key( $url ) );
	}

	public function key_targets() {
		return array(
			'api'             => array( 'https://api.spacefast.com/v1/spaces/x/thumbnail', true ),
			'api uppercase'   => array( 'https://API.spacefast.com/v1/x', true ),
			'other spacefast' => array( 'https://cdn.spacefast.com/a.png', false ),
			'lookalike'       => array( 'https://api.spacefast.com.evil.com/a', false ),
			'http api'        => array( 'http://api.spacefast.com/v1/x', false ),
			'api with port'   => array( 'https://api.spacefast.com:444/v1/x', false ),
			'credentials'     => array( 'https://api.spacefast.com@evil.com/x', false ),
			'empty'           => array( '', false ),
		);
	}
}
