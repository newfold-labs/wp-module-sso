<?php

namespace NewFoldLabs\WP\Module\SSO;

class SSO_AJAX_Handler {

	/**
	 * Set up AJAX handlers.
	 */
	public function __construct() {

		$actions = [
			SSO_Helpers::ACTION        => 'login',
			SSO_Helpers_Legacy::ACTION => 'legacyLogin',
		];

		foreach ( $actions as $action => $methodName ) {
			add_action( "wp_ajax_{$action}", [ $this, $methodName ] );
			add_action( "wp_ajax_nopriv_{$action}", [ $this, $methodName ] );
		}

	}

	/**
	 * Handle SSO login attempts.
	 */
	public function login() {
		SSO_Helpers::handleLogin( filter_input( INPUT_GET, 'token', FILTER_SANITIZE_STRING ) );
	}

	/**
	 * Handle legacy SSO login attempts.
	 */
	public function legacyLogin() {

		$nonce = filter_input( INPUT_GET, 'nonce', FILTER_SANITIZE_STRING );
		$salt  = filter_input( INPUT_GET, 'salt', FILTER_SANITIZE_STRING );

		SSO_Helpers_Legacy::handleLegacyLogin( $nonce, $salt );
	}

}
