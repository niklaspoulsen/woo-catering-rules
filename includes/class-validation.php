<?php
if (!defined('ABSPATH')) exit;

class WCR_Validation {

    public function __construct() {
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validate'], 10, 3);
        add_action('woocommerce_check_cart_items', [$this, 'validate_cart']);
        add_action('woocommerce_checkout_process', [$this, 'validate_cart']);
    }

    private function validate_store_selection($date, $time) {
        $ymd = WCR_Session::date_to_ymd($date);

        if (!$ymd) {
            return 'Vælg en leveringsdato i formatet dd/mm/yyyy.';
        }

        if (!WCR_Session::valid_time($time)) {
            return 'Vælg et gyldigt leveringstidspunkt.';
        }

        if (!WCR_Session::valid_quarter_time($time)) {
            return 'Tidspunkt skal være 00, 15, 30 eller 45.';
        }

        if (get_option('wcr_closed_today', 'no') === 'yes' && $ymd === current_time('Y-m-d')) {
            return 'Butikken er midlertidigt lukket i dag.';
        }

        if (in_array($date, WCR_Session::get_closed_dates(), true)) {
            return 'Den valgte dato er lukket for bestilling.';
        }

        $weekday = (int) date('w', strtotime($ymd));
        $hours = WCR_Session::get_hours();
        $row = isset($hours[$weekday]) ? $hours[$weekday] : ['closed' => 'no', 'open' => '08:00', 'close' => '16:00'];

        if (($row['closed'] ?? 'no') === 'yes') {
            return 'Butikken er lukket på den valgte ugedag.';
        }

        $start = WCR_Session::round_up_quarter($row['open']);
        $end = WCR_Session::round_down_quarter($row['close']);

        if ($start && strcmp($time, $start) < 0) {
            return 'Valgt leveringstid er før butikkens åbningstid (' . $row['open'] . ').';
        }

        if ($end && strcmp($time, $end) > 0) {
            return 'Valgt leveringstid er efter butikkens lukketid (' . $row['close'] . ').';
        }

        return true;
    }

    public function validate($passed, $product_id, $qty) {
        $date = WCR_Session::get_session('wcr_delivery_date');
        $time = WCR_Session::get_session('wcr_delivery_time');

        if (!$date) {
            wc_add_notice('Vælg leveringsdato.', 'error');
            return false;
        }

        if (!$time) {
            wc_add_notice('Vælg leveringstid.', 'error');
            return false;
        }

        $result = $this->validate_store_selection($date, $time);

        if ($result !== true) {
            wc_add_notice($result, 'error');
            return false;
        }

        return $passed;
    }

    public function validate_cart() {
        $date = WCR_Session::get_session('wcr_delivery_date');
        $time = WCR_Session::get_session('wcr_delivery_time');

        if (!$date || !$time) {
            return;
        }

        $result = $this->validate_store_selection($date, $time);
        if ($result !== true) {
            wc_add_notice($result, 'error');
        }
    }
}
