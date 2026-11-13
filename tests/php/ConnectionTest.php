<?php
/**
 * Tests for the pure parts of Connection.
 *
 * @package Spacefast_Block
 */

use PHPUnit\Framework\TestCase;
use Spacefast_Block\Connection;

/**
 * @covers \Spacefast_Block\Connection
 */
final class ConnectionTest extends TestCase {

	public function test_encrypt_round_trips() {
		$cipher = Connection::encrypt( 'sfa_test_not_a_real_key', 'salt' );
		$this->assertSame( 'sfa_test_not_a_real_key', Connection::decrypt( $cipher, 'salt' ) );
	}

	public function test_encrypt_hides_plaintext_and_is_randomized() {
		$one = Connection::encrypt( 'sfa_test_not_a_real_key', 'salt' );
		$two = Connection::encrypt( 'sfa_test_not_a_real_key', 'salt' );
		$this->assertStringNotContainsString( 'sfa_', $one );
		$this->assertNotSame( $one, $two );
	}

	public function test_decrypt_fails_with_wrong_secret() {
		$cipher = Connection::encrypt( 'sfa_test_not_a_real_key', 'salt' );
		$this->assertNull( Connection::decrypt( $cipher, 'other' ) );
	}

	/**
	 * @dataProvider bad_ciphertexts
	 */
	public function test_decrypt_rejects_garbage( $cipher ) {
		$this->assertNull( Connection::decrypt( $cipher, 'salt' ) );
	}

	public function bad_ciphertexts() {
		return array(
			'empty'        => array( '' ),
			'not base64'   => array( '%%%' ),
			'too short'    => array( base64_encode( 'short' ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
			'tampered'     => array( base64_encode( str_repeat( 'x', 80 ) ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		);
	}

	public function test_pending_has_no_secret() {
		$this->assertSame(
			array( 'status' => 'pending', 'secret' => null ),
			Connection::interpret_poll( array( 'status' => 'pending' ) )
		);
	}

	public function test_approved_with_secret_is_connected() {
		$result = Connection::interpret_poll(
			array( 'status' => 'approved', 'apiKey' => array( 'secret' => 'sfa_x' ) )
		);
		$this->assertSame( 'connected', $result['status'] );
		$this->assertSame( 'sfa_x', $result['secret'] );
	}

	public function test_approved_without_secret_is_expired() {
		$this->assertSame( 'expired', Connection::interpret_poll( array( 'status' => 'approved' ) )['status'] );
	}

	/**
	 * @dataProvider terminal_states
	 */
	public function test_terminal_states( $api_status, $expected ) {
		$this->assertSame( $expected, Connection::interpret_poll( array( 'status' => $api_status ) )['status'] );
	}

	public function terminal_states() {
		return array(
			'denied'   => array( 'denied', 'denied' ),
			'expired'  => array( 'expired', 'expired' ),
			'consumed' => array( 'consumed', 'expired' ),
			'unknown'  => array( 'wat', 'expired' ),
			'missing'  => array( null, 'expired' ),
		);
	}

	/**
	 * @dataProvider verification_urls
	 */
	public function test_verification_url_must_be_spacefast_https( $url, $trusted ) {
		$this->assertSame( $trusted, Connection::is_trusted_verification_url( $url ) );
	}

	public function verification_urls() {
		return array(
			'apex'         => array( 'https://spacefast.com/device', true ),
			'subdomain'    => array( 'https://app.spacefast.com/device?code=AB', true ),
			'http'         => array( 'http://spacefast.com/device', false ),
			'lookalike'    => array( 'https://evilspacefast.com/device', false ),
			'suffix trick' => array( 'https://spacefast.com.evil.com/device', false ),
			'credentials'  => array( 'https://spacefast.com@evil.com/device', false ),
			'port'         => array( 'https://spacefast.com:8443/device', false ),
			'javascript'   => array( 'javascript:alert(1)', false ),
			'empty'        => array( '', false ),
		);
	}

	public function test_expired_becomes_connected_when_already_connected() {
		$this->assertSame( 'connected', Connection::reconcile_status( 'expired', true ) );
		$this->assertSame( 'expired', Connection::reconcile_status( 'expired', false ) );
		$this->assertSame( 'denied', Connection::reconcile_status( 'denied', true ) );
		$this->assertSame( 'pending', Connection::reconcile_status( 'pending', true ) );
	}
}
