<?php

namespace NewFold\SSO;

class SSO_AJAX_Handler {

	/**
	 * Set up AJAX handlers.
	 */
	public function __construct() {

		$actions = [ SSO_Helpers::ACTION => 'login' ];

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

}
