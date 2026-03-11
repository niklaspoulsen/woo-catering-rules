<?php
if (!defined('ABSPATH')) exit;

class WCR_Session {

    public static function get_session($key, $default = '') {
        if (!function_exists('WC') || !WC()->session) {
            return $default;
        }
        $value = WC()->session->get($key);
        return $value !== null ? $value : $default;
    }

    public static function set_session($key, $value) {
        if (!function_exists('WC') || !WC()->session) {
            return;
        }
        WC()->session->set($key, $value);
    }

    public static function get_default_hours() {
        return [
            1 => ['closed' => 'no',  'open' => '08:00', 'close' => '16:00'],
            2 => ['closed' => 'no',  'open' => '08:00', 'close' => '16:00'],
            3 => ['closed' => 'no',  'open' => '08:00', 'close' => '16:00'],
            4 => ['closed' => 'no',  'open' => '08:00', 'close' => '16:00'],
            5 => ['closed' => 'no',  'open' => '08:00', 'close' => '16:00'],
            6 => ['closed' => 'no',  'open' => '10:00', 'close' => '14:00'],
            0 => ['closed' => 'yes', 'open' => '10:00', 'close' => '14:00'],
        ];
    }

    public static function get_hours() {
        $hours = get_option('wcr_store_hours', self::get_default_hours());
        return is_array($hours) ? $hours : self::get_default_hours();
    }

    public static function get_closed_dates() {
        $dates = get_option('wcr_closed_dates', []);
        return is_array($dates) ? array_values(array_unique(array_filter($dates))) : [];
    }

    public static function date_to_ymd($date) {
        $date = trim((string) $date);
        if (!$date) return '';

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date, $m)) {
            if (checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
                return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
            }
        }

        return '';
    }

    public static function native_to_display_date($date) {
        $date = trim((string) $date);
        if (!$date) return '';

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m)) {
            if (checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
                return sprintf('%02d/%02d/%04d', (int) $m[3], (int) $m[2], (int) $m[1]);
            }
        }

        return '';
    }

    public static function display_to_native_date($date) {
        return self::date_to_ymd($date);
    }

    public static function valid_time($time) {
        return (bool) preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', (string) $time);
    }

    public static function valid_quarter_time($time) {
        return (bool) preg_match('/^(?:[01]\d|2[0-3]):(?:00|15|30|45)$/', (string) $time);
    }

    public static function round_up_quarter($time) {
        if (!self::valid_time($time)) return '';
        [$h, $m] = array_map('intval', explode(':', $time));
        $total = $h * 60 + $m;
        $rounded = (int) (ceil($total / 15) * 15);
        if ($rounded > 23 * 60 + 45) $rounded = 23 * 60 + 45;
        return sprintf('%02d:%02d', floor($rounded / 60), $rounded % 60);
    }

    public static function round_down_quarter($time) {
        if (!self::valid_time($time)) return '';
        [$h, $m] = array_map('intval', explode(':', $time));
        $total = $h * 60 + $m;
        $rounded = (int) (floor($total / 15) * 15);
        if ($rounded < 0) $rounded = 0;
        return sprintf('%02d:%02d', floor($rounded / 60), $rounded % 60);
    }

    public static function add_quarter($time) {
        $dt = DateTime::createFromFormat('H:i', $time);
        if (!$dt) return '23:45';
        $dt->modify('+15 minutes');
        return $dt->format('H:i');
    }

    public function __construct() {
        add_action('init', [$this, 'capture']);
    }

    public function capture() {
        if (!function_exists('WC')) return;

        if (isset($_POST['wcr_delivery_date'])) {
            $raw_date = sanitize_text_field(wp_unslash($_POST['wcr_delivery_date']));

            $display_date = '';
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw_date)) {
                $display_date = self::native_to_display_date($raw_date);
            } else {
                $display_date = $raw_date;
            }

            self::set_session('wcr_delivery_date', $display_date);
        }

        if (isset($_POST['wcr_delivery_time'])) {
            self::set_session('wcr_delivery_time', sanitize_text_field(wp_unslash($_POST['wcr_delivery_time'])));
        }

        if (!empty($_POST['wcr_save_delivery'])) {
            self::set_session('wcr_delivery_saved', 'yes');
            if (function_exists('wc_add_notice')) {
                wc_add_notice('Leveringsoplysninger gemt.', 'success');
            }
        }
    }
}
