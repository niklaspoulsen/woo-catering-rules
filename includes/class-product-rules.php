<?php
if (!defined('ABSPATH')) exit;

class WCR_Product_Rules {

    public function __construct() {
        add_action('woocommerce_product_options_general_product_data', [$this, 'fields']);
        add_action('woocommerce_process_product_meta', [$this, 'save']);
        add_action('woocommerce_single_product_summary', [$this, 'render_note'], 26);
    }

    private function weekday_options() {
        return [
            '1' => 'Mandag',
            '2' => 'Tirsdag',
            '3' => 'Onsdag',
            '4' => 'Torsdag',
            '5' => 'Fredag',
            '6' => 'Lørdag',
            '0' => 'Søndag',
        ];
    }

    public static function get_allowed_weekdays($product_id) {
        $value = get_post_meta($product_id, '_wcr_allowed_weekdays', true);

        if (!is_array($value)) {
            return [];
        }

        $clean = [];
        foreach ($value as $day) {
            $day = (string) $day;
            if (in_array($day, ['0', '1', '2', '3', '4', '5', '6'], true)) {
                $clean[] = $day;
            }
        }

        return array_values(array_unique($clean));
    }

    public static function get_allowed_dates($product_id) {
        $value = get_post_meta($product_id, '_wcr_allowed_dates', true);
        if (!is_array($value)) {
            return [];
        }

        $clean = [];
        foreach ($value as $date) {
            $ymd = WCR_Session::date_to_ymd($date);
            if ($ymd) {
                $clean[] = $ymd;
            }
        }

        $clean = array_values(array_unique($clean));
        sort($clean);

        return $clean;
    }

    public static function get_blocked_dates($product_id) {
        $value = get_post_meta($product_id, '_wcr_blocked_dates', true);
        if (!is_array($value)) {
            return [];
        }

        $clean = [];
        foreach ($value as $date) {
            $ymd = WCR_Session::date_to_ymd($date);
            if ($ymd) {
                $clean[] = $ymd;
            }
        }

        $clean = array_values(array_unique($clean));
        sort($clean);

        return $clean;
    }

    public static function get_custom_note($product_id) {
        return trim((string) get_post_meta($product_id, '_wcr_rule_note', true));
    }

    public static function show_note_enabled($product_id) {
        return get_post_meta($product_id, '_wcr_show_rule_note', true) === 'yes';
    }

    public static function get_rule_summary($product_id) {
        $custom = self::get_custom_note($product_id);
        if ($custom !== '') {
            return $custom;
        }

        $parts = [];

        $allowed_weekdays = self::get_allowed_weekdays($product_id);
        if (!empty($allowed_weekdays)) {
            $labels = [];
            $all = (new self())->weekday_options();

            foreach ($allowed_weekdays as $day) {
                if (isset($all[$day])) {
                    $labels[] = mb_strtolower($all[$day]);
                }
            }

            if (!empty($labels)) {
                $parts[] = 'Kan kun bestilles ' . self::join_labels($labels);
            }
        }

        $allowed_dates = self::get_allowed_dates($product_id);
        if (!empty($allowed_dates)) {
            $labels = array_map(function($date) {
                return WCR_Session::native_to_display_date($date);
            }, $allowed_dates);

            $parts[] = 'Kan kun bestilles til ' . self::join_labels($labels);
        }

        $blocked_dates = self::get_blocked_dates($product_id);
        if (!empty($blocked_dates)) {
            $labels = array_map(function($date) {
                return WCR_Session::native_to_display_date($date);
            }, $blocked_dates);

            $parts[] = 'Kan ikke bestilles til ' . self::join_labels($labels);
        }

        return implode('. ', array_filter($parts));
    }

    private static function join_labels($items) {
        $items = array_values(array_filter(array_map('trim', $items)));
        $count = count($items);

        if ($count === 0) return '';
        if ($count === 1) return $items[0];
        if ($count === 2) return $items[0] . ' og ' . $items[1];

        $last = array_pop($items);
        return implode(', ', $items) . ' og ' . $last;
    }

    public function fields() {
        global $post;
        $product_id = $post ? $post->ID : 0;

        $allowed_weekdays = self::get_allowed_weekdays($product_id);
        $allowed_dates    = self::get_allowed_dates($product_id);
        $blocked_dates    = self::get_blocked_dates($product_id);
        $rule_note        = self::get_custom_note($product_id);
        $show_note        = self::show_note_enabled($product_id) ? 'yes' : 'no';

        echo '<div class="options_group">';

        echo '<p class="form-field"><strong>Catering-regler</strong><br><span class="description">Begræns hvilke dage eller datoer dette produkt må bestilles til.</span></p>';

        echo '<p class="form-field">';
        echo '<label>Tilladte ugedage</label>';
        echo '<span class="wrap" style="display:flex;flex-wrap:wrap;gap:8px;max-width:700px;">';

        foreach ($this->weekday_options() as $value => $label) {
            $checked = in_array($value, $allowed_weekdays, true);

            echo '<label style="
                display:inline-flex;
                align-items:center;
                gap:6px;
                padding:6px 10px;
                border:1px solid ' . ($checked ? '#2271b1' : '#dcdcde') . ';
                border-radius:8px;
                background:' . ($checked ? '#f0f6fc' : '#fff') . ';
                cursor:pointer;
                margin:0;
                min-height:36px;
                box-sizing:border-box;
            ">';
            echo '<input type="checkbox" name="_wcr_allowed_weekdays[]" value="' . esc_attr($value) . '" ' . checked($checked, true, false) . ' />';
            echo '<span>' . esc_html($label) . '</span>';
            echo '</label>';
        }

        echo '</span>';
        echo '<span class="description" style="display:block;margin-top:8px;">Hvis der vælges ugedage her, kan produktet kun bestilles til disse ugedage.</span>';
        echo '</p>';

        woocommerce_wp_textarea_input([
            'id'          => '_wcr_allowed_dates_text',
            'label'       => 'Kun tilladte datoer',
            'description' => 'Én dato pr. linje eller kommasepareret. Format: dd/mm/yyyy. Hvis dette felt er udfyldt, kan produktet kun bestilles til disse datoer. Perfekt til månedsmenuer eller nytårsmenu.',
            'desc_tip'    => false,
            'value'       => WCR_Session::format_date_list_for_textarea($allowed_dates),
        ]);

        woocommerce_wp_textarea_input([
            'id'          => '_wcr_blocked_dates_text',
            'label'       => 'Blokerede datoer',
            'description' => 'Én dato pr. linje eller kommasepareret. Format: dd/mm/yyyy. Produktet kan ikke bestilles til disse datoer.',
            'desc_tip'    => false,
            'value'       => WCR_Session::format_date_list_for_textarea($blocked_dates),
        ]);

        woocommerce_wp_textarea_input([
            'id'          => '_wcr_rule_note',
            'label'       => 'Tekst på produktside',
            'description' => 'Valgfri tekst som vises på produktsiden. Fx: "Kan kun bestilles til afhentning mandag til torsdag" eller "Kan kun bestilles til nytårsaften".',
            'desc_tip'    => false,
            'value'       => $rule_note,
        ]);

        woocommerce_wp_checkbox([
            'id'          => '_wcr_show_rule_note',
            'label'       => 'Vis regeltekst på produktsiden',
            'description' => 'Vis automatisk regeltekst eller den tilpassede tekst ovenfor på produktsiden.',
            'value'       => $show_note,
        ]);

        echo '</div>';
    }

    public function save($product_id) {
        $allowed_weekdays = isset($_POST['_wcr_allowed_weekdays']) ? (array) wp_unslash($_POST['_wcr_allowed_weekdays']) : [];
        $clean_weekdays = [];

        foreach ($allowed_weekdays as $day) {
            $day = sanitize_text_field((string) $day);
            if (in_array($day, ['0', '1', '2', '3', '4', '5', '6'], true)) {
                $clean_weekdays[] = $day;
            }
        }

        update_post_meta($product_id, '_wcr_allowed_weekdays', array_values(array_unique($clean_weekdays)));

        $allowed_dates_text = isset($_POST['_wcr_allowed_dates_text']) ? wp_unslash($_POST['_wcr_allowed_dates_text']) : '';
        $blocked_dates_text = isset($_POST['_wcr_blocked_dates_text']) ? wp_unslash($_POST['_wcr_blocked_dates_text']) : '';

        update_post_meta($product_id, '_wcr_allowed_dates', WCR_Session::parse_date_list_text($allowed_dates_text));
        update_post_meta($product_id, '_wcr_blocked_dates', WCR_Session::parse_date_list_text($blocked_dates_text));

        $rule_note = isset($_POST['_wcr_rule_note']) ? sanitize_textarea_field(wp_unslash($_POST['_wcr_rule_note'])) : '';
        update_post_meta($product_id, '_wcr_rule_note', $rule_note);

        $show_note = isset($_POST['_wcr_show_rule_note']) ? 'yes' : 'no';
        update_post_meta($product_id, '_wcr_show_rule_note', $show_note);
    }

    public function render_note() {
        global $product;

        if (!$product || !is_a($product, 'WC_Product')) {
            return;
        }

        $product_id = $product->get_id();

        if (!self::show_note_enabled($product_id)) {
            return;
        }

        $text = self::get_rule_summary($product_id);
        if ($text === '') {
            return;
        }

        echo '<div class="wcr-product-rule-note" style="margin:12px 0 0;padding:12px 14px;border:1px solid #e5e7eb;border-radius:12px;background:#fafafa;">';
        echo wp_kses_post(nl2br(esc_html($text)));
        echo '</div>';
    }
}
