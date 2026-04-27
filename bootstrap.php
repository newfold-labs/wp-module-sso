<?php

use function NewfoldLabs\WP\ModuleLoader\register;

if ( function_exists( 'add_action' ) ) {

	add_action(
		'plugins_loaded',
		function () {

			// Unregister actions from the sso.php mu-plugin in case they exist
			// This ensures that this code always takes priority for SSO handling
			remove_action( 'wp_ajax_nopriv_sso-check', 'sso_check' );
			remove_action( 'wp_ajax_sso-check', 'sso_check' );

			register(
				array(
					'name'     => 'sso',
					'label'    => __( 'SSO', 'wp-module-sso' ),
					'callback' => function () {

						if ( ! defined( 'NFD_SSO_DIR' ) ) {
							define( 'NFD_SSO_DIR', __DIR__ );
						}
						if ( ! defined( 'NFD_SSO_URL' ) ) {
							define( 'NFD_SSO_URL', plugins_url( '', __DIR__ . '/sso.php' ) );
						}
						if ( ! defined( 'NFD_SSO_VERSION' ) ) {
							// Update on every release. Used as the asset cache-buster for
							// enqueued styles/scripts. There is currently no `version` field
							// in this module's composer.json, so this is the de-facto source.
							define( 'NFD_SSO_VERSION', '1.3.0' );
						}

						require __DIR__ . '/sso.php';
					},
					'isActive' => true,
					'isHidden' => true,
				)
			);
		}
	);

}
