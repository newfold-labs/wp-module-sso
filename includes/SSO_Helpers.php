<?php

namespace NewfoldLabs\WP\Module\SSO;

class SSO_Helpers {

	/**
	 * SSO AJAX action.
	 */
	const ACTION = 'newfold_sso_login';

	/**
	 * SSO token meta key.
	 */
	const META_KEY = 'newfold_sso_token';

	/**
	 * Transient key prefix used to protect the SSO landing redirect from
	 * being hijacked by a plugin that fires its own redirect on `admin_init`
	 * (e.g. a first-run onboarding wizard) on the request the SSO redirect
	 * lands on.
	 */
	const REDIRECT_GUARD_KEY = 'newfold_sso_pending_redirect_';

	/**
	 * Default lifetime (seconds) for the landing-page redirect guard.
	 */
	const REDIRECT_GUARD_TTL = 120;

	/**
	 * Currently registered `wp_redirect` pin callback, kept so it can be
	 * removed (tests and re-pinning in the same request).
	 *
	 * @var callable|null
	 */
	protected static $redirect_pin_callback = null;

	/**
	 * Transient key for the in-flight landing guard, if any.
	 *
	 * @var string|null
	 */
	protected static $redirect_guard_key = null;

	/**
	 * Whether a competing `wp_redirect` target was observed while pinned.
	 *
	 * @var bool
	 */
	protected static $redirect_guard_hijacked = false;

	/**
	 * Generate an SSO token for a user.
	 *
	 * @param int $user_id user id
	 *
	 * @return string
	 */
	public static function generateToken( $user_id ) {
		return base64_encode(
			implode(
				':',
				array(
					$user_id,
					time(),
					wp_generate_password( 64, true, true ),
				)
			)
		);
	}

	/**
	 * Save an SSO token for a specific user.
	 *
	 * @param string $token
	 */
	public static function saveToken( $token ) {
		update_user_meta( self::getUserIdFromToken( $token ), self::META_KEY, $token );
	}

	/**
	 * Validate an SSO token.
	 *
	 * @param $token
	 *
	 * @return bool
	 */
	public static function validateToken( $token ) {

		// Decode token
		$parts = explode( ':', base64_decode( $token ), 3 );

		// Validate user
		$user_id = absint( array_shift( $parts ) );
		if ( ! $user_id ) {
			return false;
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user || ! is_a( $user, \WP_User::class ) ) {
			return false;
		}

		// Validate timeframe
		$time = array_shift( $parts );
		if ( ! $time || ( $time + 600 ) < time() ) {
			return false;
		}

		// Validate token
		$user_token = get_user_meta( $user->ID, self::META_KEY, true );
		if ( $token !== $user_token ) {
			return false;
		}

		// Token is valid
		return true;
	}

	/**
	 * Get the WordPress user ID from a token.
	 *
	 * @param string $token
	 *
	 * @return int
	 */
	public static function getUserIdFromToken( $token ) {
		$parts = explode( ':', base64_decode( $token ), 3 );

		return absint( array_shift( $parts ) );
	}

	/**
	 * Get the WordPress user object from a token.
	 *
	 * @param string $token
	 *
	 * @return \WP_User|false
	 */
	public static function getUserFromToken( $token ) {
		return get_user_by( 'id', self::getUserIdFromToken( $token ) );
	}

	/**
	 * Log a failed SSO attempt.
	 */
	public static function logFailure() {
		$key   = 'newfold_sso_failure_count';
		$count = absint( get_transient( $key ) );
		set_transient( $key, $count + 1, MINUTE_IN_SECONDS * 5 );
	}

	/**
	 * Check if we should throttle attempts.
	 *
	 * @return bool
	 */
	public static function shouldThrottle() {
		return absint( get_transient( 'newfold_sso_failure_count' ) ) > 4;
	}

	/**
	 * Trigger an SSO failure.
	 *
	 * @param string $error_type type of error
	 */
	public static function triggerFailure( $error_type = '' ) {

		self::logFailure();

		// Enable legacy action when necessary
		if ( has_action( 'eig_sso_fail' ) ) {
			do_action( 'eig_sso_fail' );
		}

		do_action( 'newfold_sso_fail' );

		if ( empty( $error_type ) ) {
			wp_safe_redirect( wp_login_url() );
		} else {
			wp_safe_redirect(
				add_query_arg(
					array(
						'error' => $error_type,
					),
					wp_login_url()
				)
			);
		}
		exit;
	}

	/**
	 * Trigger an SSO success
	 *
	 * @param \WP_User $user
	 */
	public static function triggerSuccess( \WP_User $user ) {

		wp_set_current_user( $user->ID, $user->user_login );
		wp_set_auth_cookie( $user->ID );

		$redirect = wp_validate_redirect( self::getSuccessUrl(), admin_url() );

		// Pin the redirect target for the rest of this request so that
		// anything hooked to `wp_login` below (e.g. a plugin's own
		// first-time onboarding redirect) can't hijack the destination the
		// user actually asked for.
		self::pinRedirect( $redirect );

		// Protect the next request too. `wp_login` only guards redirects
		// fired during *this* request, but the destination above is a fresh
		// page load in its own request, and some onboarding-style redirects
		// fire on that page's `admin_init` instead - see
		// self::guardPendingRedirect(), hooked to `admin_init` in sso.php.
		set_transient(
			self::REDIRECT_GUARD_KEY . $user->ID,
			$redirect,
			self::getRedirectGuardTtl()
		);

		do_action( 'wp_login', $user->user_login, $user );

		// Enable legacy action when necessary
		if ( has_action( 'eig_sso_success' ) ) {
			do_action( 'eig_sso_success', $user, $redirect );
		}

		do_action( 'newfold_sso_success', $user, $redirect );

		// Ensure the same token can't be used twice
		delete_user_meta( $user->ID, self::META_KEY );

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * TTL for the landing-page redirect-guard transient.
	 *
	 * @return int
	 */
	protected static function getRedirectGuardTtl() {
		return max( 30, (int) apply_filters( 'newfold_sso_redirect_guard_ttl', self::REDIRECT_GUARD_TTL ) );
	}

	/**
	 * Force any `wp_redirect()`/`wp_safe_redirect()` call made for the rest
	 * of this request to resolve to $url, regardless of what it's called
	 * with. Registered at PHP_INT_MAX so it always runs after filters added
	 * earlier in the request (WordPress runs same-priority callbacks in
	 * registration order).
	 *
	 * The callback accepts the `$location` argument WordPress passes to
	 * `wp_redirect` filters so PHP 7.3+ does not warn about extra args.
	 *
	 * @param string $url Intended destination (already validated).
	 *
	 * @return void
	 */
	protected static function pinRedirect( $url ) {
		if ( self::$redirect_pin_callback ) {
			remove_filter( 'wp_redirect', self::$redirect_pin_callback, PHP_INT_MAX );
			self::$redirect_pin_callback = null;
		}

		self::$redirect_guard_hijacked = false;
		self::$redirect_pin_callback   = static function ( $location, $status = 302 ) use ( $url ) {
			unset( $status );
			if ( (string) $location !== (string) $url ) {
				self::$redirect_guard_hijacked = true;
			}

			return $url;
		};

		add_filter( 'wp_redirect', self::$redirect_pin_callback, PHP_INT_MAX, 2 );
	}

	/**
	 * Remove a previously registered redirect pin.
	 *
	 * @return void
	 */
	public static function clearRedirectPin() {
		if ( self::$redirect_pin_callback ) {
			remove_filter( 'wp_redirect', self::$redirect_pin_callback, PHP_INT_MAX );
			self::$redirect_pin_callback = null;
		}
		remove_action( 'shutdown', array( self::class, 'consumeRedirectGuardIfClean' ), PHP_INT_MAX );
		self::$redirect_guard_hijacked = false;
		self::$redirect_guard_key      = null;
	}

	/**
	 * Drop the landing-page guard transient when this request was not
	 * forced through a competing redirect. If a hijack was rewritten back
	 * to the SSO target, keep the transient so the following request
	 * (the browser loading that target again) is still protected.
	 *
	 * Hooked to `shutdown` from guardPendingRedirect().
	 *
	 * @return void
	 */
	public static function consumeRedirectGuardIfClean() {
		if ( self::$redirect_guard_key && ! self::$redirect_guard_hijacked ) {
			delete_transient( self::$redirect_guard_key );
			self::$redirect_guard_key = null;
		}
	}

	/**
	 * Guard against a first-run/onboarding redirect hijacking the page an
	 * SSO login just landed the user on. Hooked to `admin_init` at an early
	 * priority (see sso.php) so it registers its redirect pin before other
	 * `admin_init` callbacks get a chance to redirect away.
	 *
	 * The transient is not deleted up front. Deleting it before remaining
	 * `admin_init` callbacks run would leave the next hop unprotected: a
	 * hijack is rewritten to the SSO URL, the browser requests that URL
	 * again, and the onboarding callback would then succeed.
	 *
	 * @return void
	 */
	public static function guardPendingRedirect() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}

		$key      = self::REDIRECT_GUARD_KEY . $user_id;
		$redirect = get_transient( $key );
		if ( ! $redirect ) {
			return;
		}

		$redirect = wp_validate_redirect( $redirect, false );
		if ( ! $redirect ) {
			delete_transient( $key );
			return;
		}

		self::$redirect_guard_key = $key;
		self::pinRedirect( $redirect );

		add_action( 'shutdown', array( self::class, 'consumeRedirectGuardIfClean' ), PHP_INT_MAX );
	}

	/**
	 * Get the SSO success URL.
	 *
	 * @return string
	 */
	public static function getSuccessUrl() {
		$url = '';

		$params = array( 'bounce', 'redirect' );

		foreach ( $params as $param ) {
			$relative_path = esc_url_raw( filter_input( INPUT_GET, $param ) );
			if ( $relative_path ) {
				$url = admin_url( $relative_path );
				break;
			}
		}

		if ( $url ) {
			$params = $_GET;

			unset( $params['action'] );
			unset( $params['bounce'] );
			unset( $params['nonce'] );
			unset( $params['redirect'] );
			unset( $params['salt'] );
			unset( $params['token'] );
			unset( $params['user'] );

			// Persist all query params not used for SSO
			if ( ! empty( $params ) ) {
				foreach ( $params as $key => $value ) {
					$url = add_query_arg( $key, $value, $url );
				}
			}
		}

		if ( ! $url ) {
			$url = apply_filters( 'newfold_sso_success_url_default', admin_url() );
		}

		// Enable legacy filter when necessary
		if ( has_filter( 'eig_sso_redirect' ) ) {
			$url = (string) apply_filters( 'eig_sso_redirect', $url );
		}

		return (string) apply_filters( 'newfold_sso_success_url', $url );
	}

	/**
	 * Handle SSO login.
	 *
	 * @param string $token
	 */
	public static function handleLogin( $token ) {

		// No token provided
		if ( ! $token ) {
			wp_safe_redirect( wp_login_url() );
			exit;
		}

		// Too many failed attempts
		if ( self::shouldThrottle() ) {
			self::triggerFailure();
			exit;
		}

		$isValid = self::validateToken( $token );

		// Invalid token
		if ( ! $isValid ) {
			self::triggerFailure();
			exit;
		}

		$user = self::getUserFromToken( $token );
		if ( $user ) {
			if ( preg_match( "/['\"\\\\<|]/", $user->user_login ) ) {
				self::triggerFailure( 'invalid_username' );
				exit;
			}
		}
		self::triggerSuccess( $user );
	}
}
