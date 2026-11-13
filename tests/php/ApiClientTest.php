<?php
/**
 * Tests for the pure parts of Api_Client.
 *
 * @package Spacefast_Block
 */

use PHPUnit\Framework\TestCase;
use Spacefast_Block\Api_Client;

/**
 * @covers \Spacefast_Block\Api_Client
 */
final class ApiClientTest extends TestCase {

	public function test_success_unwraps_data() {
		$parsed = Api_Client::parse_response( 200, '{"data":{"a":1}}' );
		$this->assertTrue( $parsed['ok'] );
		$this->assertSame( array( 'a' => 1 ), $parsed['data'] );
	}

	public function test_success_without_data_wrapper_returns_body() {
		$parsed = Api_Client::parse_response( 200, '{"a":1}' );
		$this->assertSame( array( 'a' => 1 ), $parsed['data'] );
	}

	public function test_problem_uses_code_and_detail() {
		$parsed = Api_Client::parse_response( 401, '{"status":401,"code":"invalid_api_key","detail":"Bad key."}' );
		$this->assertFalse( $parsed['ok'] );
		$this->assertSame( 'invalid_api_key', $parsed['code'] );
		$this->assertSame( 'Bad key.', $parsed['message'] );
		$this->assertSame( 401, $parsed['status'] );
	}

	public function test_non_json_error_falls_back() {
		$parsed = Api_Client::parse_response( 502, '<html>' );
		$this->assertFalse( $parsed['ok'] );
		$this->assertSame( 'spacefast_http_error', $parsed['code'] );
		$this->assertSame( 502, $parsed['status'] );
	}

	public function test_non_json_success_is_an_error() {
		$parsed = Api_Client::parse_response( 200, 'nope' );
		$this->assertFalse( $parsed['ok'] );
		$this->assertSame( 'spacefast_bad_response', $parsed['code'] );
	}

	public function test_empty_204_is_ok() {
		$parsed = Api_Client::parse_response( 204, '' );
		$this->assertTrue( $parsed['ok'] );
		$this->assertSame( array(), $parsed['data'] );
	}
}
