<?php
if (!defined('ABSPATH')) exit;

class WCR_Popup {

    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'assets']);
        add_action('wp_footer', [$this, 'render']);
        add_action('woocommerce_before_cart', [$this, 'cart_box']);
        add_action('woocommerce_before_checkout_form', [$this, 'checkout_box']);
        add_shortcode('cor_delivery_summary', [$this, 'shortcode_summary']);
        add_shortcode('cor_delivery_selector', [$this, 'shortcode_selector']);
    }

    public function assets() {
        if (!is_shop() && !is_product() && !is_cart() && !is_checkout()) return;

        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style(
            'jquery-ui-css',
            'https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css',
            [],
            '1.13.2'
        );

        wp_enqueue_style('wcr-style', WCR_URL . 'assets/style.css', [], WCR_VERSION);
        wp_enqueue_script('wcr-js', WCR_URL . 'assets/script.js', ['jquery', 'jquery-ui-datepicker'], WCR_VERSION, true);

        wp_localize_script('wcr-js', 'wcrRules', [
            'isAdmin'     => false,
            'closedDates' => WCR_Session::get_closed_dates(),
            'storeHours'  => WCR_Session::get_hours(),
            'closedToday' => get_option('wcr_closed_today', 'no'),
            'today'       => current_time('d/m/Y'),
            'saved'       => WCR_Session::get_session('wcr_delivery_saved'),
        ]);
    }

    private function time_options($date_value) {
        $ymd = WCR_Session::date_to_ymd($date_value);
        $selected = WCR_Session::get_session('wcr_delivery_time');
        $options = '<option value="">Vælg tidspunkt</option>';

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
        $date = WCR_Session::get_session('wcr_delivery_date');
        ?>
        <div class="wcr-box">
            <h3>Leveringstid</h3>
            <p>Valget gælder for hele ordren.</p>

            <p>
                <label for="wcr_delivery_date"><strong>Dato</strong></label><br>
                <input type="text" id="wcr_delivery_date" name="wcr_delivery_date" class="wcr-datepicker" value="<?php echo esc_attr($date); ?>" placeholder="dd/mm/yyyy" autocomplete="off">
            </p>

            <p>
                <label for="wcr_delivery_time"><strong>Tid</strong></label><br>
                <select id="wcr_delivery_time" name="wcr_delivery_time" class="wcr-time-select">
                    <?php echo $this->time_options($date); ?>
                </select>
            </p>

            <p>
                <button type="submit" class="button" name="wcr_save_delivery" value="1"><?php echo esc_html($button_text); ?></button>
            </p>
        </div>
        <?php
    }

    public function render() {
        if (!is_shop() && !is_product() && !is_cart() && !is_checkout()) return;

        $date = WCR_Session::get_session('wcr_delivery_date');
        $saved = WCR_Session::get_session('wcr_delivery_saved') === 'yes';
        ?>
        <div id="wcr-popup" class="<?php echo $saved ? '' : 'is-open'; ?>">
            <div class="wcr-overlay"></div>

            <div class="wcr-modal">
                <button type="button" class="wcr-close" aria-label="Luk">×</button>

                <h2>Vælg leveringstid</h2>

                <form method="post" class="wcr-form">
                    <label>Dato</label>
                    <input type="text" name="wcr_delivery_date" class="wcr-datepicker" value="<?php echo esc_attr($date); ?>" placeholder="dd/mm/yyyy" autocomplete="off">

                    <label>Tid</label>
                    <select name="wcr_delivery_time" class="wcr-time-select">
                        <?php echo $this->time_options($date); ?>
                    </select>

                    <button type="submit" name="wcr_save_delivery" value="1" class="wcr-primary-button">Færdig</button>
                </form>
            </div>
        </div>
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
