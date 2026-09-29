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
	 * Optional wp_login callback added by a test, removed in tearDown.
	 *
	 * @var callable|null
	 */
	private $wp_login_callback;

	/**
	 * Optional wp_redirect interceptor added by a test, removed in tearDown.
	 *
	 * @var callable|null
	 */
	private $wp_redirect_callback;

	/**
	 * Set up test user.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->user_id = $this->factory()->user->create( array( 'user_login' => 'sso_test_user' ) );
		SSO_Helpers::clearRedirectPin();
		delete_transient( SSO_Helpers::REDIRECT_GUARD_KEY . $this->user_id );
	}

	/**
	 * Tear down: drop any redirect pin so later tests are not affected.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		SSO_Helpers::clearRedirectPin();
		delete_transient( SSO_Helpers::REDIRECT_GUARD_KEY . $this->user_id );
		if ( $this->wp_login_callback ) {
			remove_action( 'wp_login', $this->wp_login_callback );
			$this->wp_login_callback = null;
		}
		if ( $this->wp_redirect_callback ) {
			remove_filter( 'wp_redirect', $this->wp_redirect_callback, PHP_INT_MAX );
			$this->wp_redirect_callback = null;
		}
		remove_filter( 'send_auth_cookies', '__return_false' );
		parent::tearDown();
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

	/**
	 * Verifies guardPendingRedirect is a no-op when no user is logged in,
	 * even if a pending redirect transient exists.
	 *
	 * @return void
	 */
	public function test_guard_pending_redirect_noop_when_logged_out() {
		wp_set_current_user( 0 );
		set_transient( SSO_Helpers::REDIRECT_GUARD_KEY . $this->user_id, 'http://example.test/target', 30 );

		SSO_Helpers::guardPendingRedirect();

		$this->assertSame( 'http://example.test/other', apply_filters( 'wp_redirect', 'http://example.test/other' ) );

		delete_transient( SSO_Helpers::REDIRECT_GUARD_KEY . $this->user_id );
	}

	/**
	 * Verifies guardPendingRedirect is a no-op when there is no pending
	 * redirect transient for the current user.
	 *
	 * @return void
	 */
	public function test_guard_pending_redirect_noop_when_no_transient() {
		wp_set_current_user( $this->user_id );
		delete_transient( SSO_Helpers::REDIRECT_GUARD_KEY . $this->user_id );

		SSO_Helpers::guardPendingRedirect();

		$this->assertSame( 'http://example.test/other', apply_filters( 'wp_redirect', 'http://example.test/other' ) );
	}

	/**
	 * Verifies guardPendingRedirect pins wp_redirect() calls to the stored
	 * target - simulating a plugin's admin_init onboarding redirect trying
	 * to hijack the page an SSO login just landed on - and keeps the
	 * transient so a follow-up request (after the Location is rewritten
	 * back to the SSO URL) is still protected.
	 *
	 * @return void
	 */
	public function test_guard_pending_redirect_pins_hijack_and_keeps_transient() {
		wp_set_current_user( $this->user_id );
		$target = admin_url( 'admin.php?page=intended-destination' );
		set_transient( SSO_Helpers::REDIRECT_GUARD_KEY . $this->user_id, $target, SSO_Helpers::REDIRECT_GUARD_TTL );

		SSO_Helpers::guardPendingRedirect();

		$hijacked = admin_url( 'admin.php?page=some-onboarding-wizard' );
		$this->assertSame( $target, apply_filters( 'wp_redirect', $hijacked ) );

		SSO_Helpers::consumeRedirectGuardIfClean();
		$this->assertSame(
			$target,
			get_transient( SSO_Helpers::REDIRECT_GUARD_KEY . $this->user_id ),
			'A rewritten hijack must leave the guard in place for the next hop.'
		);
	}

	/**
	 * Verifies the landing guard is consumed when the request is clean
	 * (no competing redirect), so later admin navigations are not pinned.
	 *
	 * @return void
	 */
	public function test_guard_pending_redirect_consumed_when_request_is_clean() {
		wp_set_current_user( $this->user_id );
		$target = admin_url( 'admin.php?page=intended-destination' );
		set_transient( SSO_Helpers::REDIRECT_GUARD_KEY . $this->user_id, $target, SSO_Helpers::REDIRECT_GUARD_TTL );

		SSO_Helpers::guardPendingRedirect();
		SSO_Helpers::consumeRedirectGuardIfClean();

		$this->assertFalse( get_transient( SSO_Helpers::REDIRECT_GUARD_KEY . $this->user_id ) );
	}

	/**
	 * Verifies triggerSuccess pins wp_redirect before wp_login fires, so a
	 * plugin that redirects+exits on wp_login cannot change the destination.
	 *
	 * @return void
	 */
	public function test_trigger_success_pins_redirect_against_wp_login_hijack() {
		$user     = get_user_by( 'id', $this->user_id );
		$expected = admin_url();
		$wizard   = admin_url( 'admin.php?page=some-onboarding-wizard' );

		add_filter( 'send_auth_cookies', '__return_false' );

		$this->wp_redirect_callback = static function ( $location ) {
			throw new \RuntimeException( $location ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		};

		$this->wp_login_callback = function () use ( $wizard ) {
			add_filter( 'wp_redirect', $this->wp_redirect_callback, PHP_INT_MAX );
			wp_safe_redirect( $wizard );
			exit;
		};

		add_action( 'wp_login', $this->wp_login_callback );

		try {
			SSO_Helpers::triggerSuccess( $user );
			$this->fail( 'triggerSuccess should end in a redirect.' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( $expected, $e->getMessage() );
		}
	}
}
