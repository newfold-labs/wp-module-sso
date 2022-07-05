<?php

namespace NewFoldLabs\WP\Module\SSO;

class SSO_Helpers_Legacy extends SSO_Helpers {

	/**
	 * SSO AJAX action.
	 */
	const ACTION = 'sso-check';

	/**
	 * Handle SSO login.
	 *
	 * @param string $token
	 */
	public static function handleLegacyLogin( $nonce, $salt ) {

		// Not doing sso
		if ( ! $nonce || ! $salt ) {
			wp_safe_redirect( wp_login_url() );
			exit;
		}

		// Too many failed attempts
		if ( self::shouldThrottle() ) {
			self::triggerFailure();
			exit;
		}

		// Find user
		$user = self::getUser();
		if ( ! $user ) {
			self::triggerFailure();
			exit;
		}

		// Validate token
		$token = substr( base64_encode( hash( 'sha256', $nonce . $salt, false ) ), 0, 64 );
		if ( get_transient( 'sso_token' ) !== $token ) {
			self::triggerFailure();
			exit;
		}

		// Do login
		self::triggerSuccess( $user );
	}

	/**
	 * Get the user to login with.
	 *
	 * @return \WP_User|false
	 */
	public static function getUser() {
		$user = false;

		$user_reference = filter_input( INPUT_GET, 'user' );

		if ( $user_reference ) {
			if ( is_email( $user_reference ) ) {
				$user = get_user_by( 'email', sanitize_email( $user_reference ) );
			} else {
				$user_id = absint( $user_reference );
				if ( $user_id ) {
					$user = get_user_by( 'id', $user_id );
				}
			}
		}

		// If user wasn't found, find first admin user
		if ( ! $user ) {
			$users = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
			if ( isset( $users[0] ) && is_a( $users[0], 'WP_User' ) ) {
				$user = $users[0];
			}
		}

		return $user;
	}

}
