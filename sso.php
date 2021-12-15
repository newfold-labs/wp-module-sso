<?php

use NewFold\SSO\SSO_AJAX_Handler;
use NewFold\SSO\SSO_REST_Controller;

new SSO_AJAX_Handler();

add_action(
	'rest_api_init',
	function () {
		$instance = new SSO_REST_Controller();
		$instance->register_routes();
	}
);
