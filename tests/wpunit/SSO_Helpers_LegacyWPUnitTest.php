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
	 * Set up: turn the redirect+exit at the end of triggerSuccess/triggerFailure
	 * into a catchable exception, and avoid sending real auth cookies in CLI.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		add_filter( 'send_auth_cookies', '__return_false' );
		add_filter(
			'wp_redirect',
			static function () {
				// A fixed control-flow marker, not request output.
				throw new \RuntimeException( self::REDIRECT_SIGNAL ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
		);

		delete_transient( 'sso_token' );
		delete_option( 'sso_token' );
		delete_transient( 'newfold_sso_failure_count' );
	}

	/**
	 * Tear down: clear the token stores and throttle counter.
	 *
	 * @return void
	 */
	public function tearDown(): void {
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
}
