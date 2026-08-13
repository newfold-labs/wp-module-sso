<?php

namespace NewfoldLabs\WP\Module\SSO;

/**
 * Tests for SSO_Helpers_Legacy.
 *
 * @covers \NewfoldLabs\WP\Module\SSO\SSO_Helpers_Legacy
 */
class SSO_Helpers_LegacyWPUnitTest extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * Marker thrown from the wp_redirect filter so the trigger* redirect+exit
	 * becomes a catchable signal instead of ending the test run.
	 */
	const REDIRECT_SIGNAL = 'sso-legacy-redirect';

	/**
	 * The wp_redirect interceptor, kept so it can be removed in tearDown.
	 *
	 * @var callable
	 */
	private $redirect_filter;

	/**
	 * Set up: turn the redirect+exit at the end of triggerSuccess/triggerFailure
	 * into a catchable exception, and avoid sending real auth cookies in CLI.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		$this->redirect_filter = static function () {
			// A fixed control-flow marker, not request output.
			throw new \RuntimeException( self::REDIRECT_SIGNAL ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		};

		add_filter( 'send_auth_cookies', '__return_false' );
		add_filter( 'wp_redirect', $this->redirect_filter );

		delete_transient( 'sso_token' );
		delete_option( 'sso_token' );
		delete_transient( 'newfold_sso_failure_count' );
	}

	/**
	 * Tear down: remove the filters this test added and clear token stores.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		remove_filter( 'send_auth_cookies', '__return_false' );
		remove_filter( 'wp_redirect', $this->redirect_filter );

		delete_transient( 'sso_token' );
		delete_option( 'sso_token' );
		delete_transient( 'newfold_sso_failure_count' );
		parent::tearDown();
	}

	/**
	 * Build the token the legacy handler expects for a given nonce + salt.
	 *
	 * @param string $nonce Nonce.
	 * @param string $salt  Salt.
	 *
	 * @return string
	 */
	private function make_token( $nonce, $salt ) {
		return substr( base64_encode( hash( 'sha256', $nonce . $salt, false ) ), 0, 64 );
	}

	/**
	 * Run the legacy handler, absorbing the redirect+exit signal so the test can
	 * continue and assert on side effects.
	 *
	 * @param string $nonce Nonce.
	 * @param string $salt  Salt.
	 *
	 * @return void
	 */
	private function run_legacy_login( $nonce, $salt ) {
		try {
			SSO_Helpers_Legacy::handleLegacyLogin( $nonce, $salt );
			$this->fail( 'handleLegacyLogin should always end in a redirect (exit).' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( self::REDIRECT_SIGNAL, $e->getMessage() );
		}
	}

	/**
	 * A successful legacy login consumes the token so the same link cannot be replayed.
	 *
	 * @return void
	 */
	public function test_legacy_login_consumes_token_on_success() {
		$nonce = 'valid-nonce'; // No -e<epoch> suffix, so it does not expire on its own.
		$salt  = 'valid-salt';
		set_transient( 'sso_token', $this->make_token( $nonce, $salt ) );

		$this->run_legacy_login( $nonce, $salt );

		$this->assertFalse(
			get_transient( 'sso_token' ),
			'A successful legacy login must delete the sso_token transient.'
		);
		$this->assertFalse(
			get_option( 'sso_token' ),
			'A successful legacy login must not leave the sso_token option behind.'
		);
	}

	/**
	 * After a successful legacy login, replaying the same link fails (the token is gone).
	 *
	 * @return void
	 */
	public function test_legacy_login_cannot_be_replayed() {
		$nonce = 'valid-nonce';
		$salt  = 'valid-salt';
		set_transient( 'sso_token', $this->make_token( $nonce, $salt ) );

		// First use succeeds and consumes the token.
		$this->run_legacy_login( $nonce, $salt );
		$this->assertSame( 0, absint( get_transient( 'newfold_sso_failure_count' ) ) );

		// Replay of the same link is now rejected as a failure.
		$this->run_legacy_login( $nonce, $salt );
		$this->assertGreaterThan(
			0,
			absint( get_transient( 'newfold_sso_failure_count' ) ),
			'Replaying a consumed legacy link must be treated as a failed attempt.'
		);
	}

	/**
	 * A token stored in the option (transient empty) is also consumed on success,
	 * exercising the dual-read / dual-delete path.
	 *
	 * @return void
	 */
	public function test_legacy_login_consumes_token_from_option_store() {
		$nonce = 'valid-nonce';
		$salt  = 'valid-salt';
		delete_transient( 'sso_token' );
		update_option( 'sso_token', $this->make_token( $nonce, $salt ) );

		$this->run_legacy_login( $nonce, $salt );

		$this->assertFalse( get_option( 'sso_token' ), 'The option-store token must be deleted on success.' );
		$this->assertFalse( get_transient( 'sso_token' ), 'No token transient should remain.' );
	}

	/**
	 * A failed attempt (wrong nonce/salt) must NOT consume the stored token, so a
	 * later attempt with the correct link still works.
	 *
	 * @return void
	 */
	public function test_failed_legacy_login_does_not_consume_token() {
		$nonce = 'valid-nonce';
		$salt  = 'valid-salt';
		$token = $this->make_token( $nonce, $salt );
		set_transient( 'sso_token', $token );

		// Wrong salt => computed token will not match the stored one => failure.
		$this->run_legacy_login( $nonce, 'wrong-salt' );

		$this->assertSame(
			$token,
			get_transient( 'sso_token' ),
			'A failed legacy login must leave the stored token intact.'
		);
	}
}
