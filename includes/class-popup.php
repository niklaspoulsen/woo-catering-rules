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

        $product_rules = [
            'allowedWeekdays' => [],
            'allowedDates'    => [],
            'blockedDates'    => [],
        ];

        if (is_product() && class_exists('WCR_Product_Rules')) {
            $product_id = get_queried_object_id();
            if ($product_id) {
                $rules = WCR_Product_Rules::get_frontend_rules($product_id);
                $product_rules = [
                    'allowedWeekdays' => !empty($rules['allowed_weekdays']) ? array_values($rules['allowed_weekdays']) : [],
                    'allowedDates'    => !empty($rules['allowed_dates']) ? array_values($rules['allowed_dates']) : [],
                    'blockedDates'    => !empty($rules['blocked_dates']) ? array_values($rules['blocked_dates']) : [],
                ];
            }
        }

        wp_localize_script('wcr-js', 'wcrRules', [
            'isAdmin'       => false,
            'closedDates'   => WCR_Session::get_closed_dates(),
            'storeHours'    => WCR_Session::get_hours(),
            'closedToday'   => get_option('wcr_closed_today', 'no'),
            'today'         => current_time('Y-m-d'),
            'saved'         => WCR_Session::get_session('wcr_delivery_saved'),
            'minDate'       => WCR_Session::get_min_delivery_date(),
            'leadTimeDays'  => WCR_Session::get_required_lead_time_days(),
            'forcePopup'    => WCR_Session::get_session('wcr_force_popup') === 'yes',
            'popupMessage'  => WCR_Session::get_session('wcr_popup_message'),
            'productRules'  => $product_rules,
        ]);
    }

    private function display_to_native($date) {
        return WCR_Session::date_to_ymd($date);
    }

    private function native_to_display($date) {
        return WCR_Session::native_to_display_date($date);
    }

    private function get_date_value_for_input() {
        $saved = WCR_Session::get_session('wcr_delivery_date', '');
        return $this->display_to_native($saved);
    }

    private function get_time_value() {
        return WCR_Session::get_session('wcr_delivery_time', '');
    }

    private function render_form_fields($context = 'popup') {
        $date_value = $this->get_date_value_for_input();
        $time_value = $this->get_time_value();
        ?>
        <label for="wcr_delivery_date_<?php echo esc_attr($context); ?>">Dato</label>
        <input
            type="date"
            id="wcr_delivery_date_<?php echo esc_attr($context); ?>"
            class="wcr-delivery-date-native"
            name="wcr_delivery_date"
            value="<?php echo esc_attr($date_value); ?>"
        >

        <label for="wcr_delivery_time_<?php echo esc_attr($context); ?>">Tidspunkt</label>
        <select id="wcr_delivery_time_<?php echo esc_attr($context); ?>" name="wcr_delivery_time">
            <option value="">Vælg tidspunkt</option>
            <?php if ($time_value) : ?>
                <option value="<?php echo esc_attr($time_value); ?>" selected><?php echo esc_html($time_value); ?></option>
            <?php endif; ?>
        </select>
        <?php
    }

    public function render() {
        if (!is_shop() && !is_product() && !is_cart() && !is_checkout()) return;
        ?>
        <div id="wcr-popup">
            <div class="wcr-overlay"></div>
            <div class="wcr-modal">
                <button type="button" class="wcr-close">&times;</button>
                <h2>Vælg levering</h2>

                <?php
                $msg = WCR_Session::get_session('wcr_popup_message');
                if ($msg) {
                    echo '<div class="wcr-popup-message">' . esc_html($msg) . '</div>';
                }
                ?>

                <form method="post" class="wcr-form">
                    <?php $this->render_form_fields('popup'); ?>
                    <button type="submit" name="wcr_save_delivery" value="1" class="wcr-primary-button">Gem</button>
                </form>
            </div>
        </div>
        <?php
    }

    public function render_floating_button() {
        if (!is_shop() && !is_product()) return;

        $date = WCR_Session::get_session('wcr_delivery_date');
        $time = WCR_Session::get_session('wcr_delivery_time');

        $summary = 'Vælg levering';
        if ($date || $time) {
            $summary = trim(($date ? $date : '') . ' ' . ($time ? $time : ''));
        }

        echo '<button type="button" id="wcr-open-modal" class="wcr-floating-button">';
        echo '<strong>Levering</strong><br><small>' . esc_html($summary) . '</small>';
        echo '</button>';
    }

    public function cart_box() {
        echo '<div class="wcr-box"><form method="post" class="wcr-form">';
        $this->render_form_fields('cart');
        echo '<button type="submit" name="wcr_save_delivery" value="1" class="button alt">Gem leveringsoplysninger</button>';
        echo '</form></div>';
    }

    public function checkout_box() {
        echo '<div class="wcr-box"><form method="post" class="wcr-form">';
        $this->render_form_fields('checkout');
        echo '<button type="submit" name="wcr_save_delivery" value="1" class="button alt">Gem leveringsoplysninger</button>';
        echo '</form></div>';
    }

    public function shortcode_summary() {
        $date = WCR_Session::get_session('wcr_delivery_date');
        $time = WCR_Session::get_session('wcr_delivery_time');

        if (!$date && !$time) {
            return '<div class="wcr-box">Ingen levering valgt endnu.</div>';
        }

        $out = '<div class="wcr-box">';
        if ($date) $out .= '<p><strong>Dato:</strong> ' . esc_html($date) . '</p>';
        if ($time) $out .= '<p><strong>Tidspunkt:</strong> ' . esc_html($time) . '</p>';
        $out .= '</div>';

        return $out;
    }

    public function shortcode_selector() {
        ob_start();
        echo '<div class="wcr-box"><form method="post" class="wcr-form">';
        $this->render_form_fields('shortcode');
        echo '<button type="submit" name="wcr_save_delivery" value="1" class="button alt">Gem leveringsoplysninger</button>';
        echo '</form></div>';
        return ob_get_clean();
    }
}
