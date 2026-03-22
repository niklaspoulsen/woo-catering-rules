<?php
/*
Plugin Name: Woo Catering Rules
Description: Leveringsdato og tidsstyring til WooCommerce catering
Version: 1.3.0
Author: Niklas Poulsen
*/

if (!defined('ABSPATH')) exit;

define('WCR_PATH', plugin_dir_path(__FILE__));
define('WCR_URL', plugin_dir_url(__FILE__));
define('WCR_VERSION', '1.3.0');

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
