<?php
/*
Plugin Name: Woo Catering Rules
Description: Leveringsdato og tidsstyring til WooCommerce catering
Version: 1.3.7
Author: Niklas Poulsen
Requires at least: 6.4
Requires PHP: 7.4
WC requires at least: 8.3
WC tested up to: 10.3
*/

if (!defined('ABSPATH')) exit;

define('WCR_PATH', plugin_dir_path(__FILE__));
define('WCR_URL', plugin_dir_url(__FILE__));
define('WCR_VERSION', '1.3.7');


add_action('before_woocommerce_init', function() {
    if (!class_exists('Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        return;
    }

    Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
});

add_action('plugins_loaded', function() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p><strong>Woo Catering Rules:</strong> WooCommerce skal være aktivt, før pluginet kan køre.</p></div>';
        });
        return;
    }

$required_files = [
    WCR_PATH . 'includes/class-session.php',
    WCR_PATH . 'includes/class-admin.php',
    WCR_PATH . 'includes/class-popup.php',
    WCR_PATH . 'includes/class-validation.php',
    WCR_PATH . 'includes/class-order.php',
    WCR_PATH . 'includes/class-shortcodes.php',
    WCR_PATH . 'includes/class-product-rules.php',
];

foreach ($required_files as $file) {
    if (!file_exists($file)) {
        add_action('admin_notices', function() use ($file) {
            echo '<div class="notice notice-error"><p><strong>Woo Catering Rules:</strong> Manglende fil: ' . esc_html(basename($file)) . '</p></div>';
        });
        return;
    }
    require_once $file;
}

if (class_exists('WCR_Session')) new WCR_Session();
if (class_exists('WCR_Admin')) new WCR_Admin();
if (class_exists('WCR_Popup')) new WCR_Popup();
if (class_exists('WCR_Validation')) new WCR_Validation();
if (class_exists('WCR_Order')) new WCR_Order();
if (class_exists('WCR_Shortcodes')) new WCR_Shortcodes();
if (class_exists('WCR_Product_Rules')) new WCR_Product_Rules();
});
