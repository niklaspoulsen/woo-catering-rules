<?php
/*
Plugin Name: Woo Catering Rules
Description: Catering leveringsdato og tidsstyring til WooCommerce
Version: 1.0
Author: Your Company
*/

if (!defined('ABSPATH')) exit;

define('WCR_PATH', plugin_dir_path(__FILE__));
define('WCR_URL', plugin_dir_url(__FILE__));

require_once WCR_PATH . 'includes/class-popup.php';
require_once WCR_PATH . 'includes/class-admin.php';
require_once WCR_PATH . 'includes/class-session.php';
require_once WCR_PATH . 'includes/class-order.php';

new WCR_Popup();
new WCR_Admin();
new WCR_Session();
new WCR_Order();
