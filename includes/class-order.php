<?php
if (!defined('ABSPATH')) exit;

class WCR_Order {

    public function __construct() {
        add_action('woocommerce_checkout_create_order', [$this, 'save'], 10, 2);
        add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'admin_meta']);
        add_action('woocommerce_email_order_meta', [$this, 'email_meta'], 15, 4);
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

    private function get_order_label($order) {
        $shipping_method = mb_strtolower((string) $order->get_shipping_method());

        if (
            strpos($shipping_method, 'afhent') !== false ||
            strpos($shipping_method, 'pickup') !== false ||
            strpos($shipping_method, 'local pickup') !== false
        ) {
            return 'Afhentning';
        }

        return 'Levering';
    }

    public function admin_meta($order) {
        $date  = $order->get_meta('_delivery_date');
        $time  = $order->get_meta('_delivery_time');
        $label = $this->get_order_label($order);

        if ($date || $time) {
            echo '<p>';
            echo '<strong>' . esc_html($label . 'sdato') . ':</strong> ' . esc_html($date ?: '-') . '<br>';
            echo '<strong>Tidspunkt:</strong> ' . esc_html($time ?: '-');
            echo '</p>';
        }
    }

    public function email_meta($order, $sent_to_admin, $plain_text, $email) {
        if (!$order instanceof WC_Order) {
            return;
        }

        $date  = $order->get_meta('_delivery_date');
        $time  = $order->get_meta('_delivery_time');
        $label = $this->get_order_label($order);

        if (!$date && !$time) {
            return;
        }

        $date_label = $label . 'sdato';

        if ($plain_text) {
            echo "\n" . $date_label . ': ' . ($date ?: '-') . "\n";
            echo "Tidspunkt: " . ($time ?: '-') . "\n";
        } else {
            echo '<div style="margin:15px 0; padding:12px; border:1px solid #ddd; background:#f8f8f8;">';
            echo '<strong>' . esc_html($date_label) . ':</strong> ' . esc_html($date ?: '-') . '<br>';
            echo '<strong>Tidspunkt:</strong> ' . esc_html($time ?: '-');
            echo '</div>';
        }
    }
}
