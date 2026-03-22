<?php
if (!defined('ABSPATH')) exit;

class WCR_Validation {

    public function __construct() {
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validate'], 10, 3);
        add_action('woocommerce_check_cart_items', [$this, 'validate_cart']);
        add_action('woocommerce_checkout_process', [$this, 'validate_cart']);
    }

    private function set_popup_error($message) {
        WCR_Session::set_session('wcr_force_popup', 'yes');
        WCR_Session::set_session('wcr_popup_message', (string) $message);
    }

    private function clear_popup_error() {
        WCR_Session::set_session('wcr_force_popup', '');
        WCR_Session::set_session('wcr_popup_message', '');
    }

    private function get_hours_row($ymd) {
        $weekday = (int) date('w', strtotime($ymd));
        $hours   = WCR_Session::get_hours();

        return $hours[$weekday] ?? ['closed' => 'no', 'open' => '08:00', 'close' => '16:00'];
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

        $min_date = WCR_Session::get_min_delivery_date();
        if ($min_date && strcmp($ymd, $min_date) < 0) {
            $display_min = WCR_Session::native_to_display_date($min_date);
            $lead_days   = WCR_Session::get_required_lead_time_days();

            if ($lead_days > 0) {
                return sprintf(
                    'Denne bestilling kræver mindst %d dages varsel. Tidligste mulige dato er %s.',
                    $lead_days,
                    $display_min
                );
            }

            return sprintf(
                'Tidligste mulige dato er %s.',
                $display_min
            );
        }

        if (get_option('wcr_closed_today', 'no') === 'yes' && $ymd === current_time('Y-m-d')) {
            return 'Butikken er midlertidigt lukket for bestillinger i dag.';
        }

        if (in_array($ymd, WCR_Session::get_closed_dates(), true)) {
            return 'Den valgte dato er lukket for bestilling.';
        }

        $row = $this->get_hours_row($ymd);

        if (($row['closed'] ?? 'no') === 'yes') {
            return 'Butikken er lukket på den valgte ugedag.';
        }

        $start = WCR_Session::round_up_quarter($row['open']);
        $end   = WCR_Session::round_down_quarter($row['close']);

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
            $this->set_popup_error('Vælg leveringsdato.');
            return false;
        }

        if (!$time) {
            $this->set_popup_error('Vælg leveringstid.');
            return false;
        }

        $result = $this->validate_store_selection($date, $time);

        if ($result !== true) {
            $this->set_popup_error($result);
            return false;
        }

        $this->clear_popup_error();
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
            $this->set_popup_error($result);

            wc_clear_notices();
            wc_add_notice('Ret leveringsdato/tid for at fortsætte.', 'error');
            return;
        }

        $this->clear_popup_error();
    }
}
