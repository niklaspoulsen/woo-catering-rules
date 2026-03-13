<?php
/*
Plugin Name: Woo Catering Rules
Description: Leveringsdato og tidsstyring til WooCommerce catering
Version: 1.1.0
Author: Your Company
*/

if (!defined('ABSPATH')) exit;

define('WCR_PATH', plugin_dir_path(__FILE__));
define('WCR_URL', plugin_dir_url(__FILE__));
define('WCR_VERSION', '1.1.0');

require_once WCR_PATH . 'includes/class-session.php';
require_once WCR_PATH . 'includes/class-admin.php';
require_once WCR_PATH . 'includes/class-popup.php';
require_once WCR_PATH . 'includes/class-validation.php';
require_once WCR_PATH . 'includes/class-order.php';
require_once WCR_PATH . 'includes/class-shortcodes.php';

new WCR_Session();
new WCR_Admin();
new WCR_Popup();
new WCR_Validation();
new WCR_Order();
new WCR_Shortcodes();
