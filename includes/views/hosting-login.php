<?php
/**
 * Hosting-login button rendered on wp-login.php.
 *
 * Required scope (provided by SSO_Hosting_Login::render):
 *   $config    array  Resolved button config (enabled, url, label, new_tab, accent_color, …).
 *   $icon_html string Pre-sanitized inline SVG markup (may be empty).
 *
 * @package NewfoldLabs\WP\Module\SSO
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="nfd-sso-hosting-login"<?php if ( ! empty( $config['accent_color'] ) ) : ?> style="--nfd-sso-hosting-login-accent: <?php echo esc_attr( $config['accent_color'] ); ?>;"<?php endif; ?>>
	<div class="nfd-sso-hosting-login__divider">
		<span><?php esc_html_e( 'or', 'wp-module-sso' ); ?></span>
	</div>
	<a class="nfd-sso-hosting-login__button" href="<?php echo esc_url( $config['url'] ); ?>"<?php if ( ! empty( $config['new_tab'] ) ) : ?> target="_blank" rel="noopener noreferrer"<?php endif; ?>>
		<?php echo $icon_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses applied in caller. ?>
		<span class="nfd-sso-hosting-login__label"><?php echo esc_html( $config['label'] ); ?></span>
	</a>
</div>
