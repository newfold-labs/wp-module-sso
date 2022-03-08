<?php

use function NewfoldLabs\WP\ModuleLoader\register;

if ( function_exists( 'add_action' ) ) {

	add_action(
		'plugins_loaded',
		function () {

			register(
				[
					'name'     => 'sso',
					'label'    => __( 'SSO', 'endurance' ),
					'callback' => function () {
						require __DIR__ . '/sso.php';
					},
					'isActive' => true,
					'isHidden' => true,
				]
			);

		}
	);

}
