<?php
if (!defined('ABSPATH')) exit;

class WCR_Popup {

    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'assets']);
        add_action('wp_footer', [$this, 'render']);
        add_action('wp_footer', [$this, 'render_floating_button']);
        add_action('woocommerce_before_cart', [$this, 'cart_box']);
        add_action('woocommerce_before_checkout_form', [$this, 'checkout_box']);
        add_shortcode('cor_delivery_summary', [$this, 'shortcode_summary']);
        add_shortcode('cor_delivery_selector', [$this, 'shortcode_selector']);
    }

    public function assets() {
        if (!is_shop() && !is_product() && !is_cart() && !is_checkout()) return;

        wp_enqueue_style('wcr-style', WCR_URL . 'assets/style.css', [], WCR_VERSION);
        wp_enqueue_script('wcr-js', WCR_URL . 'assets/script.js', ['jquery'], WCR_VERSION, true);

        wp_localize_script('wcr-js', 'wcrRules', [
            'isAdmin'     => false,
            'closedDates' => WCR_Session::get_closed_dates(),
            'storeHours'  => WCR_Session::get_hours(),
            'closedToday' => get_option('wcr_closed_today', 'no'),
            'today'       => current_time('Y-m-d'),
            'saved'       => WCR_Session::get_session('wcr_delivery_saved'),
        ]);
    }

    private function display_to_native($date) {
        $ymd = WCR_Session::date_to_ymd($date);
        return $ymd ?: '';
    }

    private function native_to_display($date) {
        $date = trim((string) $date);
        if (!$date) return '';
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m)) {
            return sprintf('%02d/%02d/%04d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }
        return $date;
    }

    private function time_options($date_value, $selected_value = '') {
        $selected = $selected_value ? $selected_value : WCR_Session::get_session('wcr_delivery_time');
        $options = '<option value="">Vælg tidspunkt</option>';

        $ymd = $date_value;
        if (!$ymd) return $options;

        $weekday = (int) date('w', strtotime($ymd));
        $hours = WCR_Session::get_hours();
        $row = isset($hours[$weekday]) ? $hours[$weekday] : ['closed' => 'no', 'open' => '08:00', 'close' => '16:00'];

        if (($row['closed'] ?? 'no') === 'yes') return $options;

        $start = WCR_Session::round_up_quarter($row['open']);
        $end = WCR_Session::round_down_quarter($row['close']);

        if (!$start || !$end || strcmp($start, $end) > 0) return $options;

        for ($t = $start; strcmp($t, $end) <= 0; $t = WCR_Session::add_quarter($t)) {
            $options .= '<option value="' . esc_attr($t) . '"' . selected($selected, $t, false) . '>' . esc_html($t) . '</option>';
            if ($t === '23:45') break;
        }

        return $options;
    }

    private function render_editor($button_text = 'Opdater levering') {
        $display_date = WCR_Session::get_session('wcr_delivery_date');
        $native_date = $this->display_to_native($display_date);
        $time = WCR_Session::get_session('wcr_delivery_time');
        ?>
        <div class="wcr-box">
            <h3>Leveringstid</h3>
            <p>Valget gælder for hele ordren.</p>

            <input type="hidden" name="wcr_delivery_date" class="wcr-delivery-date-hidden" value="<?php echo esc_attr($display_date); ?>">

            <p>
                <label for="wcr_delivery_date_inline_native"><strong>Dato</strong></label><br>
                <input
                    type="date"
                    id="wcr_delivery_date_inline_native"
                    class="wcr-delivery-date-native"
                    value="<?php echo esc_attr($native_date); ?>"
                    min="<?php echo esc_attr(current_time('Y-m-d')); ?>"
                >
            </p>

            <p>
                <label for="wcr_delivery_time_inline"><strong>Tid</strong></label><br>
                <select id="wcr_delivery_time_inline" name="wcr_delivery_time" class="wcr-time-select">
                    <?php echo $this->time_options($native_date, $time); ?>
                </select>
            </p>

            <p>
                <button type="submit" class="button" name="wcr_save_delivery" value="1">
                    <?php echo esc_html($button_text); ?>
                </button>
            </p>
        </div>
        <?php
    }

    public function render() {
        if (!is_shop() && !is_product() && !is_cart() && !is_checkout()) return;

        $display_date = WCR_Session::get_session('wcr_delivery_date');
        $native_date = $this->display_to_native($display_date);
        $time = WCR_Session::get_session('wcr_delivery_time');
        $saved = WCR_Session::get_session('wcr_delivery_saved') === 'yes';
        ?>
        <div id="wcr-popup" class="<?php echo $saved ? '' : 'is-open'; ?>">
            <div class="wcr-overlay"></div>

            <div class="wcr-modal">
                <button type="button" class="wcr-close" aria-label="Luk">×</button>

                <h2>Vælg leveringstid</h2>

                <form method="post" class="wcr-form">
                    <input type="hidden" name="wcr_delivery_date" class="wcr-delivery-date-hidden" value="<?php echo esc_attr($display_date); ?>">

                    <label for="wcr_modal_delivery_date_native">Dato</label>
                    <input
                        type="date"
                        id="wcr_modal_delivery_date_native"
                        class="wcr-delivery-date-native"
                        value="<?php echo esc_attr($native_date); ?>"
                        min="<?php echo esc_attr(current_time('Y-m-d')); ?>"
                    >

                    <label for="wcr_modal_delivery_time">Tid</label>
                    <select id="wcr_modal_delivery_time" name="wcr_delivery_time" class="wcr-time-select">
                        <?php echo $this->time_options($native_date, $time); ?>
                    </select>

                    <button type="submit" name="wcr_save_delivery" value="1" class="wcr-primary-button">
                        Færdig
                    </button>
                </form>
            </div>
        </div>
        <?php
    }

    public function render_floating_button() {
        if (!is_shop() && !is_product() && !is_cart() && !is_checkout()) return;

        $date = WCR_Session::get_session('wcr_delivery_date');
        $time = WCR_Session::get_session('wcr_delivery_time');

        if (!$date && !$time) return;
        ?>
        <button type="button" id="wcr-open-modal" class="wcr-floating-button">
            Ændr levering<br>
            <small><?php echo esc_html(trim($date . ' ' . $time)); ?></small>
        </button>
        <?php
    }

    public function cart_box() {
        if (!function_exists('WC') || !WC()->cart) return;
        $this->render_editor();
    }

    public function checkout_box() {
        $this->render_editor();
    }

    public function shortcode_summary() {
        $date = WCR_Session::get_session('wcr_delivery_date');
        $time = WCR_Session::get_session('wcr_delivery_time');

        if (!$date && !$time) {
            return '<div class="wcr-shortcode-summary"><button type="button" class="button" data-wcr-open-modal="1">Vælg leveringstid</button></div>';
        }

        return '<div class="wcr-shortcode-summary"><strong>Levering:</strong> ' . esc_html(trim($date . ' ' . $time)) . ' <button type="button" class="button button-small" data-wcr-open-modal="1">Ret</button></div>';
    }

    public function shortcode_selector() {
        ob_start();
        $this->render_editor('Gem levering');
        return ob_get_clean();
    }
}
