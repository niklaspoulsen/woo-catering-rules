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

    private function weekday_label($weekday) {
        $labels = [
            1 => 'mandag',
            2 => 'tirsdag',
            3 => 'onsdag',
            4 => 'torsdag',
            5 => 'fredag',
            6 => 'lørdag',
            0 => 'søndag',
        ];

        return $labels[$weekday] ?? '';
    }

    private function join_labels($items) {
        $items = array_values(array_filter(array_map('trim', $items)));
        $count = count($items);

        if ($count === 0) return '';
        if ($count === 1) return $items[0];
        if ($count === 2) return $items[0] . ' og ' . $items[1];

        $last = array_pop($items);
        return implode(', ', $items) . ' og ' . $last;
    }

    private function validate_product_visibility($product_id) {
        if (WCR_Product_Rules::is_product_visible_today($product_id)) {
            return true;
        }

        $message = WCR_Product_Rules::get_visibility_message($product_id);
        return $message !== '' ? $message : 'Dette produkt er ikke tilgængeligt lige nu.';
    }

    private function validate_product_rules($product_id, $ymd) {
        $allowed_dates = WCR_Product_Rules::get_allowed_dates($product_id);
        $blocked_dates = WCR_Product_Rules::get_blocked_dates($product_id);
        $allowed_weekdays = WCR_Product_Rules::get_allowed_weekdays($product_id);

        if (!empty($allowed_dates) && !in_array($ymd, $allowed_dates, true)) {
            $labels = array_map(function($date) {
                return WCR_Session::native_to_display_date($date);
            }, $allowed_dates);

            return 'Dette produkt kan kun bestilles til ' . $this->join_labels($labels) . '.';
        }

        if (!empty($blocked_dates) && in_array($ymd, $blocked_dates, true)) {
            return 'Dette produkt kan ikke bestilles til ' . WCR_Session::native_to_display_date($ymd) . '.';
        }

        if (!empty($allowed_weekdays)) {
            $weekday = (string) date('w', strtotime($ymd));

            if (!in_array($weekday, $allowed_weekdays, true)) {
                $labels = array_map(function($day) {
                    return $this->weekday_label((int) $day);
                }, $allowed_weekdays);

                return 'Dette produkt kan kun bestilles til ' . $this->join_labels($labels) . '.';
            }
        }

        return true;
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

            return sprintf('Tidligste mulige dato er %s.', $display_min);
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

    private function validate_cart_product_rules($ymd) {
        if (!function_exists('WC') || !WC()->cart) {
            return true;
        }

        foreach (WC()->cart->get_cart() as $cart_item) {
            $product_id = 0;

            if (!empty($cart_item['product_id'])) {
                $product_id = absint($cart_item['product_id']);
            } elseif (!empty($cart_item['data']) && is_a($cart_item['data'], 'WC_Product')) {
                $product_id = $cart_item['data']->get_id();
            }

            if (!$product_id) {
                continue;
            }

            $visibility_result = $this->validate_product_visibility($product_id);
            if ($visibility_result !== true) {
                $product_name = get_the_title($product_id);
                return $product_name ? $product_name . ': ' . $visibility_result : $visibility_result;
            }

            $result = $this->validate_product_rules($product_id, $ymd);
            if ($result !== true) {
                $product_name = get_the_title($product_id);
                return $product_name ? $product_name . ': ' . $result : $result;
            }
        }

        return true;
    }

    public function validate($passed, $product_id, $qty) {
        $visibility_result = $this->validate_product_visibility($product_id);
        if ($visibility_result !== true) {
            $this->set_popup_error($visibility_result);
            return false;
        }

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

        $store_result = $this->validate_store_selection($date, $time);
        if ($store_result !== true) {
            $this->set_popup_error($store_result);
            return false;
        }

        $ymd = WCR_Session::date_to_ymd($date);
        $product_result = $this->validate_product_rules($product_id, $ymd);

        if ($product_result !== true) {
            $this->set_popup_error($product_result);
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

        $store_result = $this->validate_store_selection($date, $time);
        if ($store_result !== true) {
            $this->set_popup_error($store_result);
            wc_clear_notices();
            wc_add_notice('Ret leveringsdato/tid for at fortsætte.', 'error');
            return;
        }

        $ymd = WCR_Session::date_to_ymd($date);
        $product_result = $this->validate_cart_product_rules($ymd);

        if ($product_result !== true) {
            $this->set_popup_error($product_result);
            wc_clear_notices();
            wc_add_notice($product_result, 'error');
            return;
        }

        $this->clear_popup_error();
    }
}
