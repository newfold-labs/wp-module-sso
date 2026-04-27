<?php

namespace NewfoldLabs\WP\Module\SSO;

/**
 * Tests for SSO_REST_Controller.
 *
 * @covers \NewfoldLabs\WP\Module\SSO\SSO_REST_Controller
 */
class SSO_REST_ControllerWPUnitTest extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * Controller instance.
	 *
	 * @var SSO_REST_Controller
	 */
	private $controller;

	/**
	 * Set up controller; register routes on rest_api_init to avoid notice.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->controller = new SSO_REST_Controller();
		add_action( 'rest_api_init', array( $this->controller, 'register_routes' ) );
		do_action( 'rest_api_init' );
	}

	/**
	 * Verifies register_routes adds /sso route.
	 *
	 * @return void
	 */
	public function test_register_routes_adds_sso_route() {
		$server = rest_get_server();
		$this->assertNotNull( $server );
		$routes = $server->get_routes();
		$this->assertArrayHasKey( '/newfold-sso/v1/sso', $routes );
	}

	/**
	 * Verifies check_permission returns WP_Error when user cannot read.
	 *
	 * @return void
	 */
	public function test_check_permission_forbidden_when_not_logged_in() {
		wp_set_current_user( 0 );
		$result = $this->controller->check_permission();
		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Verifies check_permission returns true when user can read.
	 *
	 * @return void
	 */
	public function test_check_permission_allowed_when_logged_in() {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		$result = $this->controller->check_permission();
		$this->assertTrue( $result );
	}
}
