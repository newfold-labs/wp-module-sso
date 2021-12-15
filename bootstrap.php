<?php

if ( function_exists( 'add_action' ) ) {
	add_action( 'plugins_loaded', 'newfold_module_register_sso' );
}

/**
 * Register the SSO module.
 */
function  newfold_module_register_sso() {
	eig_register_module(
		array(
			'name'     => 'sso',
			'label'    => __( 'SSO', 'endurance' ),
			'callback' => 'newfold_module_load_sso',
			'isActive' => true,
			'isHidden' => false,
		)
	);
}

/**
 * Load the SSO module.
 */
function newfold_module_load_sso() {
	require dirname( __FILE__ ) . '/sso.php';
}
