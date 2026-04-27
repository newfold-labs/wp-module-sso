<?php

namespace NewfoldLabs\WP\Module\SSO;

/**
 * Module loading wpunit tests.
 *
 * @coversNothing
 */
class ModuleLoadingWPUnitTest extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * Verify WordPress factory is available.
	 *
	 * @return void
	 */
	public function test_wordpress_factory_available() {
		$this->assertTrue( function_exists( 'get_option' ) );
		$this->assertNotEmpty( get_option( 'blogname' ) );
	}

	/**
	 * Verify add_action exists (bootstrap uses it).
	 *
	 * @return void
	 */
	public function test_wordpress_hooks_available() {
		$this->assertTrue( function_exists( 'add_action' ) );
		$this->assertTrue( function_exists( 'add_filter' ) );
	}

	/**
	 * Verify SSO classes exist.
	 *
	 * @return void
	 */
	public function test_sso_classes_exist() {
		$this->assertTrue( class_exists( SSO_Helpers::class ) );
		$this->assertTrue( class_exists( SSO_REST_Controller::class ) );
		$this->assertTrue( class_exists( SSO_AJAX_Handler::class ) );
	}

	/**
	 * Verify SSO_Helpers constants.
	 *
	 * @return void
	 */
	public function test_sso_helpers_constants() {
		$this->assertSame( 'newfold_sso_login', SSO_Helpers::ACTION );
		$this->assertSame( 'newfold_sso_token', SSO_Helpers::META_KEY );
	}
}
