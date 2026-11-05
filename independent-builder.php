<?php
/**
 * Plugin Name: Independent Builder
 * Description: An independent modular visual page-builder framework for WordPress.
 * Version: 0.5.1
 * Author: Independent Builder Project
 * Text Domain: independent-builder
 * Requires at least: 6.4
 * Requires PHP: 8.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
define( 'IB_VERSION', '0.5.1' );
define( 'IB_PATH', plugin_dir_path( __FILE__ ) );
define( 'IB_URL', plugin_dir_url( __FILE__ ) );
require_once IB_PATH . 'includes/class-ib-global-styles.php';
require_once IB_PATH . 'includes/class-ib-renderer.php';
require_once IB_PATH . 'includes/class-ib-rest.php';
require_once IB_PATH . 'includes/class-ib-plugin.php';
register_activation_hook( __FILE__, function () {
    if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die( esc_html__( 'Independent Builder requires PHP 8.0 or newer.', 'independent-builder' ) );
    }
} );
add_action( 'plugins_loaded', function () { \IndependentBuilder\Plugin::instance(); } );
