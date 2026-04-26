<?php

namespace NewfoldLabs\WP\Module\SSO;

/**
 * Renders an optional "Login with <Host>" button on the wp-login.php screen.
 *
 * Brand-agnostic. The button appears only when a consumer (typically a brand
 * plugin) populates the `newfold/sso/hosting_login` filter. The destination
 * is the host customer portal, where the user can use the SSO magic-link
 * flow (handled elsewhere in this module) to return to their WordPress site.
 *
 * Filter shape:
 *   array(
 *     'enabled'  => bool,    // default false
 *     'url'      => string,  // required when enabled
 *     'label'    => string,  // required when enabled
 *     'icon_svg'     => string,  // optional inline <svg>; use fill="currentColor" so it tints with the button text color
 *     'new_tab'      => bool,    // default false
 *     'accent_color' => string,  // optional CSS color used for button background, border, and hover/focus states
 *   )
 *
 * Markup lives in `includes/views/hosting-login.php`; styles in
 * `assets/css/hosting-login.css`.
 */
class SSO_Hosting_Login {

	const FILTER       = 'newfold/sso/hosting_login';
	const STYLE_HANDLE = 'nfd-sso-hosting-login';

	/**
	 * Register the WordPress hooks that enqueue the stylesheet and render the
	 * button on wp-login.php.
	 */
	public function __construct() {
		add_action( 'login_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		// Late priority so our markup renders after any other login_form callbacks
		// (e.g. external SSO providers). Combined with the high `order` value in
		// our CSS, this anchors us at the bottom of the form regardless of what
		// else is hooking in — without us needing to know about those plugins.
		add_action( 'login_form', array( $this, 'render' ), PHP_INT_MAX );
	}

	/**
	 * Resolve the runtime config. Returns null if disabled or incomplete.
	 *
	 * @return array|null
	 */
	protected function get_config() {
		$defaults = array(
			'enabled'      => false,
			'url'          => '',
			'label'        => '',
			'icon_svg'     => '',
			'new_tab'      => false,
			'accent_color' => '',
		);

		$config = (array) apply_filters( self::FILTER, $defaults );
		$config = array_merge( $defaults, $config );

		if ( empty( $config['enabled'] ) || empty( $config['url'] ) || empty( $config['label'] ) ) {
			return null;
		}

		return $config;
	}

	/**
	 * Enqueue the button stylesheet on wp-login.php. Skipped when the filter
	 * is unset or the resolved config is incomplete.
	 */
	public function enqueue_styles() {
		if ( null === $this->get_config() ) {
			return;
		}

		wp_enqueue_style(
			self::STYLE_HANDLE,
			NFD_SSO_URL . '/assets/css/hosting-login.css',
			array( 'login' ),
			defined( 'NFD_SSO_VERSION' ) ? NFD_SSO_VERSION : false
		);
	}

	/**
	 * Render the button markup. No-op when the filter is unset or the resolved
	 * config is missing required fields.
	 */
	public function render() {
		$config = $this->get_config();
		if ( null === $config ) {
			return;
		}

		$icon_html = ! empty( $config['icon_svg'] )
			? sprintf(
				'<span class="nfd-sso-hosting-login__icon" aria-hidden="true">%s</span>',
				wp_kses( $config['icon_svg'], self::allowed_svg_tags() )
			)
			: '';

		require NFD_SSO_DIR . '/includes/views/hosting-login.php';
	}

	/**
	 * Allowed SVG tags/attributes for the icon. Broad enough to accommodate
	 * brand marks that use stroke, transforms, or text — defensive sanitation
	 * against scripts and unknown tags, not a strict shape contract.
	 *
	 * @return array
	 */
	protected static function allowed_svg_tags() {
		return array(
			'svg'    => array(
				'class'        => true,
				'fill'         => true,
				'height'       => true,
				'stroke'       => true,
				'stroke-width' => true,
				'viewbox'      => true,
				'width'        => true,
				'xmlns'        => true,
			),
			'g'      => array(
				'fill'              => true,
				'stroke'            => true,
				'stroke-miterlimit' => true,
				'stroke-width'      => true,
				'transform'         => true,
			),
			'path'   => array(
				'd'               => true,
				'fill'            => true,
				'opacity'         => true,
				'stroke'          => true,
				'stroke-linecap'  => true,
				'stroke-linejoin' => true,
				'stroke-width'    => true,
				'transform'       => true,
			),
			'rect'   => array(
				'fill'      => true,
				'height'    => true,
				'rx'        => true,
				'transform' => true,
				'width'     => true,
				'x'         => true,
				'y'         => true,
			),
			'circle' => array(
				'cx'   => true,
				'cy'   => true,
				'fill' => true,
				'r'    => true,
			),
			'text'   => array(
				'fill'        => true,
				'font-family' => true,
				'font-size'   => true,
				'font-weight' => true,
				'transform'   => true,
				'x'           => true,
				'y'           => true,
			),
		);
	}
}
