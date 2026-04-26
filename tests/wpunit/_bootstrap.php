<?php
/**
 * Bootstrap file for wpunit tests.
 *
 * @package NewfoldLabs\WP\Module\SSO
 */

$module_root = dirname( dirname( __DIR__ ) );

require_once $module_root . '/vendor/autoload.php';

if ( ! defined( 'NFD_SSO_DIR' ) ) {
	define( 'NFD_SSO_DIR', $module_root );
}
if ( ! defined( 'NFD_SSO_URL' ) ) {
	define( 'NFD_SSO_URL', 'http://example.test/wp-content/plugins/wp-module-sso' );
}
if ( ! defined( 'NFD_SSO_VERSION' ) ) {
	define( 'NFD_SSO_VERSION', 'test' );
}

require_once $module_root . '/sso.php';
