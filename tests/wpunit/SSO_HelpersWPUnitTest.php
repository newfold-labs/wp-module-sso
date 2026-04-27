<?php

namespace NewfoldLabs\WP\Module\SSO;

/**
 * Tests for SSO_Helpers.
 *
 * @covers \NewfoldLabs\WP\Module\SSO\SSO_Helpers
 */
class SSO_HelpersWPUnitTest extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * User ID for tests.
	 *
	 * @var int
	 */
	private $user_id;

	/**
	 * Set up test user.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->user_id = $this->factory()->user->create( array( 'user_login' => 'sso_test_user' ) );
	}

	/**
	 * Verifies generateToken returns base64 string.
	 *
	 * @return void
	 */
	public function test_generate_token_returns_base64() {
		$token = SSO_Helpers::generateToken( $this->user_id );
		$this->assertNotEmpty( $token );
		$this->assertSame( $token, base64_encode( base64_decode( $token, true ) ) );
	}

	/**
	 * Verifies getUserIdFromToken extracts user ID from token.
	 *
	 * @return void
	 */
	public function test_get_user_id_from_token() {
		$token = SSO_Helpers::generateToken( $this->user_id );
		SSO_Helpers::saveToken( $token );
		$this->assertSame( $this->user_id, SSO_Helpers::getUserIdFromToken( $token ) );
	}

	/**
	 * Verifies getUserFromToken returns WP_User for valid token.
	 *
	 * @return void
	 */
	public function test_get_user_from_token() {
		$token = SSO_Helpers::generateToken( $this->user_id );
		SSO_Helpers::saveToken( $token );
		$user = SSO_Helpers::getUserFromToken( $token );
		$this->assertInstanceOf( \WP_User::class, $user );
		$this->assertSame( $this->user_id, (int) $user->ID );
	}

	/**
	 * Verifies validateToken returns true for valid saved token.
	 *
	 * @return void
	 */
	public function test_validate_token_valid() {
		$token = SSO_Helpers::generateToken( $this->user_id );
		SSO_Helpers::saveToken( $token );
		$this->assertTrue( SSO_Helpers::validateToken( $token ) );
	}

	/**
	 * Verifies validateToken returns false for invalid token.
	 *
	 * @return void
	 */
	public function test_validate_token_invalid() {
		$this->assertFalse( SSO_Helpers::validateToken( '' ) );
		$this->assertFalse( SSO_Helpers::validateToken( 'invalid' ) );
	}

	/**
	 * Verifies shouldThrottle is false when no failures.
	 *
	 * @return void
	 */
	public function test_should_throttle_false_when_no_failures() {
		delete_transient( 'newfold_sso_failure_count' );
		$this->assertFalse( SSO_Helpers::shouldThrottle() );
	}

	/**
	 * Verifies getSuccessUrl returns default when no query params.
	 *
	 * @return void
	 */
	public function test_get_success_url_default() {
		$_GET = array();
		$url  = SSO_Helpers::getSuccessUrl();
		$this->assertSame( admin_url(), $url );
	}
}
