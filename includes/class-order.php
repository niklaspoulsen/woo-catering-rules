<?php
if (!defined('ABSPATH')) exit;

class WCR_Order {

    public function __construct() {
        add_action('woocommerce_checkout_create_order', [$this, 'save'], 10, 2);
        add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'admin_meta']);
    }

    public function save($order) {
        $date = WCR_Session::get_session('wcr_delivery_date');
        $time = WCR_Session::get_session('wcr_delivery_time');

        if ($date) {
            $order->update_meta_data('_delivery_date', $date);
        }

        if ($time) {
            $order->update_meta_data('_delivery_time', $time);
        }
    }

    public function admin_meta($order) {
        $date = $order->get_meta('_delivery_date');
        $time = $order->get_meta('_delivery_time');

        if ($date || $time) {
            echo '<p><strong>Levering:</strong><br>' . esc_html(trim($date . ' ' . $time)) . '</p>';
        }
    }
}
