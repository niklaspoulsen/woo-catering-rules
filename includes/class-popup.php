<?php
if (!defined('ABSPATH')) exit;

class WCR_Popup {

    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'assets']);
        add_action('wp_footer', [$this, 'render_popup']);
        add_shortcode('wcr_delivery_box', [$this, 'render_box_shortcode']);
    }

    private function should_load() {
        return is_product() || is_cart() || is_checkout() || is_shop() || is_product_category() || is_product_tag();
    }

    private function get_current_product_rules() {
        if (!is_product()) {
            return [
                'allowedWeekdays' => [],
                'allowedDates'    => [],
                'blockedDates'    => [],
            ];
        }

        $product_id = get_queried_object_id();
        if (!$product_id) {
            return [
                'allowedWeekdays' => [],
                'allowedDates'    => [],
                'blockedDates'    => [],
            ];
        }

        $rules = WCR_Product_Rules::get_frontend_rules($product_id);

        return [
            'allowedWeekdays' => array_values($rules['allowed_weekdays']),
            'allowedDates'    => array_values($rules['allowed_dates']),
            'blockedDates'    => array_values($rules['blocked_dates']),
        ];
    }

    public function assets() {
        if (!$this->should_load()) {
            return;
        }

        wp_enqueue_style('wcr-style', WCR_URL . 'assets/style.css', [], WCR_VERSION);
        wp_enqueue_script('wcr-js', WCR_URL . 'assets/script.js', [], WCR_VERSION, true);

        $saved_date = WCR_Session::get_session('wcr_delivery_date', '');
        $saved_time = WCR_Session::get_session('wcr_delivery_time', '');
        $saved_date_ymd = WCR_Session::date_to_ymd($saved_date);

        $popup_message = WCR_Session::get_session('wcr_popup_message', '');
        $force_popup   = WCR_Session::get_session('wcr_force_popup', '') === 'yes';
        $delivery_saved = WCR_Session::get_session('wcr_delivery_saved', '');

        wp_localize_script('wcr-js', 'wcrRules', [
            'today'        => current_time('Y-m-d'),
            'minDate'      => WCR_Session::get_min_delivery_date(),
            'closedToday'  => get_option('wcr_closed_today', 'no'),
            'closedDates'  => WCR_Session::get_closed_dates(),
            'storeHours'   => WCR_Session::get_hours(),
            'saved'        => $delivery_saved,
            'forcePopup'   => $force_popup,
            'popupMessage' => $popup_message,
            'savedDate'    => $saved_date_ymd,
            'savedTime'    => $saved_time,
            'productRules' => $this->get_current_product_rules(),
        ]);
    }

    private function render_fields($context = 'popup') {
        $saved_date = WCR_Session::get_session('wcr_delivery_date', '');
        $saved_time = WCR_Session::get_session('wcr_delivery_time', '');
        $saved_date_ymd = WCR_Session::date_to_ymd($saved_date);
        ?>
        <div class="wcr-box">
            <div class="wcr-form">
                <p>
                    <label for="wcr_delivery_date_<?php echo esc_attr($context); ?>">Dato</label>
                    <input
                        type="date"
                        id="wcr_delivery_date_<?php echo esc_attr($context); ?>"
                        name="wcr_delivery_date"
                        class="wcr-delivery-date-native"
                        value="<?php echo esc_attr($saved_date_ymd); ?>"
                    >
                </p>

                <p>
                    <label for="wcr_delivery_time_<?php echo esc_attr($context); ?>">Tidspunkt</label>
                    <select id="wcr_delivery_time_<?php echo esc_attr($context); ?>" name="wcr_delivery_time">
                        <option value="">Vælg tidspunkt</option>
                        <?php if ($saved_time) : ?>
                            <option value="<?php echo esc_attr($saved_time); ?>" selected><?php echo esc_html($saved_time); ?></option>
                        <?php endif; ?>
                    </select>
                </p>

                <?php if ($context === 'popup') : ?>
                    <p style="margin:0;">
                        <button type="submit" name="wcr_save_delivery" value="1" class="wcr-primary-button">Gem</button>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    public function render_popup() {
        if (!$this->should_load()) {
            return;
        }

        $popup_message = WCR_Session::get_session('wcr_popup_message', '');
        ?>
        <div id="wcr-popup" aria-hidden="true">
            <div class="wcr-overlay"></div>
            <div class="wcr-modal">
                <button type="button" class="wcr-close" aria-label="Luk">×</button>
                <h2>Vælg levering</h2>

                <?php if ($popup_message) : ?>
                    <div class="wcr-popup-message"><?php echo esc_html($popup_message); ?></div>
                <?php endif; ?>

                <form method="post" class="wcr-form">
                    <?php $this->render_fields('popup'); ?>
                </form>
            </div>
        </div>
        <?php
    }

    public function render_box_shortcode() {
        ob_start();
        $this->render_fields('shortcode');
        return ob_get_clean();
    }
}
