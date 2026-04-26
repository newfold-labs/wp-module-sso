<?php

namespace NewfoldLabs\WP\Module\SSO;

/**
 * Tests for SSO_Hosting_Login.
 *
 * @covers \NewfoldLabs\WP\Module\SSO\SSO_Hosting_Login
 */
class SSO_Hosting_LoginWPUnitTest extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * @var SSO_Hosting_Login
	 */
	private $instance;

	public function setUp(): void {
		parent::setUp();
		$this->instance = new SSO_Hosting_Login();
	}

	public function tearDown(): void {
		remove_all_filters( SSO_Hosting_Login::FILTER );
		wp_dequeue_style( SSO_Hosting_Login::STYLE_HANDLE );
		wp_deregister_style( SSO_Hosting_Login::STYLE_HANDLE );
		parent::tearDown();
	}

	/**
	 * Helper — populate the filter with a minimal valid config.
	 */
	private function configure( array $overrides = array() ): void {
		add_filter(
			SSO_Hosting_Login::FILTER,
			function ( $config ) use ( $overrides ) {
				return array_merge(
					array(
						'enabled' => true,
						'url'     => 'https://example.com/portal',
						'label'   => 'Login with Example',
					),
					$overrides
				);
			}
		);
	}

	/**
	 * Capture render() output.
	 */
	private function render(): string {
		ob_start();
		$this->instance->render();
		return (string) ob_get_clean();
	}

	public function test_constructor_registers_login_form_at_max_priority() {
		$this->assertSame(
			PHP_INT_MAX,
			has_action( 'login_form', array( $this->instance, 'render' ) )
		);
	}

	public function test_constructor_registers_login_enqueue_scripts() {
		$this->assertNotFalse(
			has_action( 'login_enqueue_scripts', array( $this->instance, 'enqueue_styles' ) )
		);
	}

	public function test_render_outputs_nothing_when_filter_unset() {
		$this->assertSame( '', $this->render() );
	}

	public function test_render_outputs_nothing_when_disabled() {
		add_filter(
			SSO_Hosting_Login::FILTER,
			function ( $config ) {
				$config['url']   = 'https://example.com';
				$config['label'] = 'Login';
				return $config;
			}
		);
		$this->assertSame( '', $this->render() );
	}

	public function test_render_outputs_nothing_when_url_missing() {
		add_filter(
			SSO_Hosting_Login::FILTER,
			function ( $config ) {
				$config['enabled'] = true;
				$config['label']   = 'Login';
				return $config;
			}
		);
		$this->assertSame( '', $this->render() );
	}

	public function test_render_outputs_nothing_when_label_missing() {
		add_filter(
			SSO_Hosting_Login::FILTER,
			function ( $config ) {
				$config['enabled'] = true;
				$config['url']     = 'https://example.com';
				return $config;
			}
		);
		$this->assertSame( '', $this->render() );
	}

	public function test_render_outputs_button_when_configured() {
		$this->configure();
		$output = $this->render();

		$this->assertStringContainsString( 'class="nfd-sso-hosting-login"', $output );
		$this->assertStringContainsString( 'class="nfd-sso-hosting-login__divider"', $output );
		$this->assertStringContainsString( 'class="nfd-sso-hosting-login__button"', $output );
		$this->assertStringContainsString( 'href="https://example.com/portal"', $output );
		$this->assertStringContainsString( 'Login with Example', $output );
	}

	public function test_render_includes_accent_color_as_css_variable() {
		$this->configure( array( 'accent_color' => '#1b4fd8' ) );
		$this->assertStringContainsString(
			'--nfd-sso-hosting-login-accent: #1b4fd8;',
			$this->render()
		);
	}

	public function test_render_omits_accent_color_when_unset() {
		$this->configure();
		$this->assertStringNotContainsString( '--nfd-sso-hosting-login-accent', $this->render() );
	}

	public function test_render_includes_icon_svg() {
		$svg = '<svg viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg" fill="currentColor"><rect x="0" y="0" width="4" height="4"/></svg>';
		$this->configure( array( 'icon_svg' => $svg ) );
		$output = $this->render();

		$this->assertStringContainsString( 'class="nfd-sso-hosting-login__icon"', $output );
		$this->assertStringContainsString( '<svg', $output );
		$this->assertStringContainsString( '<rect', $output );
	}

	public function test_render_strips_disallowed_svg_tags() {
		$malicious = '<svg><script>alert(1)</script><rect x="0" y="0" width="4" height="4"/></svg>';
		$this->configure( array( 'icon_svg' => $malicious ) );
		$output = $this->render();

		$this->assertStringNotContainsString( '<script', $output );
		$this->assertStringContainsString( '<rect', $output );
	}

	public function test_render_escapes_label() {
		$this->configure( array( 'label' => '<script>alert(1)</script>' ) );
		$output = $this->render();

		$this->assertStringNotContainsString( '<script>alert(1)</script>', $output );
		$this->assertStringContainsString( '&lt;script&gt;', $output );
	}

	public function test_render_blocks_unsafe_url_protocol() {
		$this->configure( array( 'url' => 'javascript:alert(1)' ) );
		$this->assertStringNotContainsString( 'javascript:', $this->render() );
	}

	public function test_render_adds_target_blank_when_new_tab() {
		$this->configure( array( 'new_tab' => true ) );
		$output = $this->render();

		$this->assertStringContainsString( 'target="_blank"', $output );
		$this->assertStringContainsString( 'rel="noopener noreferrer"', $output );
	}

	public function test_render_omits_target_when_new_tab_false() {
		$this->configure();
		$this->assertStringNotContainsString( 'target=', $this->render() );
	}

	public function test_enqueue_styles_does_nothing_when_disabled() {
		$this->instance->enqueue_styles();
		$this->assertFalse( wp_style_is( SSO_Hosting_Login::STYLE_HANDLE, 'enqueued' ) );
	}

	public function test_enqueue_styles_registers_handle_when_configured() {
		$this->configure();
		$this->instance->enqueue_styles();
		$this->assertTrue( wp_style_is( SSO_Hosting_Login::STYLE_HANDLE, 'enqueued' ) );
	}

	public function test_filter_default_config_is_disabled() {
		$config = apply_filters( SSO_Hosting_Login::FILTER, array() );
		$this->assertEmpty( $config );
	}
}
