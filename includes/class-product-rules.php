<?php
if (!defined('ABSPATH')) exit;

class WCR_Product_Rules {

    public function __construct() {
        add_action('woocommerce_product_options_general_product_data', [$this, 'fields']);
        add_action('woocommerce_process_product_meta', [$this, 'save']);
        add_action('woocommerce_single_product_summary', [$this, 'render_note'], 26);

        add_action('admin_footer', [$this, 'admin_footer_script']);

        add_action('pre_get_posts', [$this, 'filter_catalog_queries']);
        add_filter('woocommerce_product_is_visible', [$this, 'filter_product_is_visible'], 10, 2);
        add_filter('woocommerce_related_products', [$this, 'filter_related_products'], 10, 3);
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

    public function admin_footer_script() {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->post_type !== 'product') {
            return;
        }
        ?>
        <script>
        jQuery(function($) {
            function displayToNative(displayDate) {
                var m = $.trim(displayDate).match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
                if (!m) return '';
                return m[3] + '-' + m[2] + '-' + m[1];
            }

            function nativeToDisplay(nativeDate) {
                var m = $.trim(nativeDate).match(/^(\d{4})-(\d{2})-(\d{2})$/);
                if (!m) return '';
                return m[3] + '/' + m[2] + '/' + m[1];
            }

            function appendDateToField($field, value) {
                if (!$field.length) return;

                if ($field.is('textarea')) {
                    var current = $.trim($field.val());
                    var lines = current ? current.split(/\n+/) : [];

                    lines = lines.map(function(item) {
                        return $.trim(item);
                    }).filter(function(item) {
                        return item !== '';
                    });

                    if (lines.indexOf(value) === -1) {
                        lines.push(value);
                    }

                    $field.val(lines.join('\n')).trigger('change');
                    return;
                }

                $field.val(value).trigger('change');
            }

            function closeAllPickers(exceptWrap) {
                $('.wcr-inline-datepicker-wrap').each(function() {
                    if (exceptWrap && $(this).is(exceptWrap)) {
                        return;
                    }
                    $(this).remove();
                });
            }

            function buildPickerWrap(target) {
                return $(
                    '<span class="wcr-inline-datepicker-wrap" style="display:inline-flex;align-items:center;gap:8px;flex-wrap:wrap;">' +
                        '<input type="date" class="wcr-inline-datepicker-field" style="min-width:160px;" />' +
                        '<button type="button" class="button button-primary wcr-inline-datepicker-add">Tilføj</button>' +
                        '<button type="button" class="button wcr-inline-datepicker-cancel">Luk</button>' +
                    '</span>'
                ).attr('data-target', target);
            }

            function syncVisibilityRange() {
                var fromDisplay = $('#_wcr_visible_from').val();
                var untilDisplay = $('#_wcr_visible_until').val();

                var fromNative = displayToNative(fromDisplay);
                var untilNative = displayToNative(untilDisplay);

                if (fromNative && untilNative && untilNative < fromNative) {
                    $('#_wcr_visible_until').val(fromDisplay).trigger('change');
                }
            }

            $(document).on('click', '.wcr-pick-date-button', function(e) {
                e.preventDefault();

                var $button = $(this);
                var target = $button.data('target');
                var $actions = $button.closest('.wcr-date-actions');

                if (!target || !$actions.length) return;

                var $existing = $actions.find('.wcr-inline-datepicker-wrap');
                if ($existing.length) {
                    $existing.remove();
                    return;
                }

                closeAllPickers();

                var $wrap = buildPickerWrap(target);
                var $field = $('#' + target);

                if ($field.length) {
                    var currentVal = $.trim($field.val());
                    if ($field.is('textarea')) {
                        var lines = currentVal ? currentVal.split(/\n+/) : [];
                        if (lines.length) {
                            var nativeValue = displayToNative(lines[lines.length - 1]);
                            if (nativeValue) {
                                $wrap.find('.wcr-inline-datepicker-field').val(nativeValue);
                            }
                        }
                    } else {
                        var nativeSingle = displayToNative(currentVal);
                        if (nativeSingle) {
                            $wrap.find('.wcr-inline-datepicker-field').val(nativeSingle);
                        }
                    }
                }

                if (target === '_wcr_visible_until') {
                    var fromDisplay = $('#_wcr_visible_from').val();
                    var fromNative = displayToNative(fromDisplay);
                    if (fromNative) {
                        $wrap.find('.wcr-inline-datepicker-field').attr('min', fromNative);
                    }
                }

                $actions.append($wrap);

                setTimeout(function() {
                    $wrap.find('.wcr-inline-datepicker-field').trigger('focus');
                }, 10);
            });

            $(document).on('click', '.wcr-inline-datepicker-cancel', function(e) {
                e.preventDefault();
                $(this).closest('.wcr-inline-datepicker-wrap').remove();
            });

            $(document).on('click', '.wcr-inline-datepicker-add', function(e) {
                e.preventDefault();

                var $wrap = $(this).closest('.wcr-inline-datepicker-wrap');
                var target = $wrap.data('target');
                var $field = $('#' + target);
                var nativeValue = $.trim($wrap.find('.wcr-inline-datepicker-field').val());

                if (!$field.length || !nativeValue) return;

                var displayValue = nativeToDisplay(nativeValue);
                if (!displayValue) return;

                appendDateToField($field, displayValue);

                if (target === '_wcr_visible_from' || target === '_wcr_visible_until') {
                    syncVisibilityRange();
                }

                $wrap.remove();
            });

            $(document).on('keydown', '.wcr-inline-datepicker-field', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    $(this).closest('.wcr-inline-datepicker-wrap').find('.wcr-inline-datepicker-add').trigger('click');
                }

                if (e.key === 'Escape') {
                    e.preventDefault();
                    $(this).closest('.wcr-inline-datepicker-wrap').remove();
                }
            });

            $(document).on('click', '.wcr-sort-dates-button', function(e) {
                e.preventDefault();

                var target = $(this).data('target');
                var $textarea = $('#' + target);
                if (!$textarea.length || !$textarea.is('textarea')) return;

                var value = $.trim($textarea.val());
                if (!value) return;

                var parts = value.split(/[\n,;]+/);
                var clean = [];

                function toSortable(dateStr) {
                    var m = $.trim(dateStr).match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
                    if (!m) return null;
                    return m[3] + '-' + m[2] + '-' + m[1];
                }

                parts.forEach(function(part) {
                    var trimmed = $.trim(part);
                    var sortable = toSortable(trimmed);
                    if (trimmed && sortable) {
                        clean.push({
                            display: trimmed,
                            sortable: sortable
                        });
                    }
                });

                clean.sort(function(a, b) {
                    return a.sortable.localeCompare(b.sortable);
                });

                var unique = [];
                var seen = {};

                clean.forEach(function(item) {
                    if (!seen[item.display]) {
                        seen[item.display] = true;
                        unique.push(item.display);
                    }
                });

                $textarea.val(unique.join('\n')).trigger('change');
            });

            $(document).on('click', '.wcr-clear-dates-button', function(e) {
                e.preventDefault();

                var target = $(this).data('target');
                var $field = $('#' + target);
                if (!$field.length) return;

                $field.val('').trigger('change');
                closeAllPickers();
            });

            $('#_wcr_visible_from, #_wcr_visible_until').on('change blur', function() {
                syncVisibilityRange();
            });

            $(document).on('click', function(e) {
                var $target = $(e.target);
                if (!$target.closest('.wcr-date-actions').length) {
                    closeAllPickers();
                }
            });
        });
        </script>
        <?php
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

    public static function get_visible_from($product_id) {
        $value = get_post_meta($product_id, '_wcr_visible_from', true);
        return WCR_Session::date_to_ymd($value);
    }

    public static function get_visible_until($product_id) {
        $value = get_post_meta($product_id, '_wcr_visible_until', true);
        return WCR_Session::date_to_ymd($value);
    }

    public static function get_custom_note($product_id) {
        return trim((string) get_post_meta($product_id, '_wcr_rule_note', true));
    }

    public static function show_note_enabled($product_id) {
        return get_post_meta($product_id, '_wcr_show_rule_note', true) === 'yes';
    }

    public static function is_product_visible_today($product_id) {
        $today = current_time('Y-m-d');
        $from  = self::get_visible_from($product_id);
        $until = self::get_visible_until($product_id);

        if ($from && strcmp($today, $from) < 0) {
            return false;
        }

        if ($until && strcmp($today, $until) > 0) {
            return false;
        }

        return true;
    }

    public static function get_visibility_message($product_id) {
        $today = current_time('Y-m-d');
        $from  = self::get_visible_from($product_id);
        $until = self::get_visible_until($product_id);

        if ($from && strcmp($today, $from) < 0) {
            return 'Dette produkt bliver synligt fra ' . WCR_Session::native_to_display_date($from) . '.';
        }

        if ($until && strcmp($today, $until) > 0) {
            return 'Dette produkt er ikke længere tilgængeligt.';
        }

        return '';
    }

    public static function get_admin_visibility_status($product_id) {
        $today = current_time('Y-m-d');
        $from  = self::get_visible_from($product_id);
        $until = self::get_visible_until($product_id);

        if ($from && strcmp($today, $from) < 0) {
            return [
                'label'   => 'Skjult lige nu',
                'message' => 'Produktet bliver synligt fra ' . WCR_Session::native_to_display_date($from) . '.',
                'color'   => '#996800',
                'bg'      => '#fff8e5',
                'border'  => '#f0d48a',
            ];
        }

        if ($until && strcmp($today, $until) > 0) {
            return [
                'label'   => 'Udløbet',
                'message' => 'Produktets synlighedsperiode er slut.',
                'color'   => '#8a1f1f',
                'bg'      => '#fff5f5',
                'border'  => '#efc2c2',
            ];
        }

        if ($from || $until) {
            $period = [];
            if ($from) {
                $period[] = 'fra ' . WCR_Session::native_to_display_date($from);
            }
            if ($until) {
                $period[] = 'til ' . WCR_Session::native_to_display_date($until);
            }

            return [
                'label'   => 'Synligt nu',
                'message' => 'Produktet er synligt nu' . (!empty($period) ? ' (' . implode(' ', $period) . ')' : '') . '.',
                'color'   => '#137333',
                'bg'      => '#eef8f0',
                'border'  => '#b8dfc0',
            ];
        }

        return [
            'label'   => 'Altid synligt',
            'message' => 'Produktet har ingen synlighedsbegrænsning.',
            'color'   => '#2271b1',
            'bg'      => '#f0f6fc',
            'border'  => '#b6d4ef',
        ];
    }

    public static function get_frontend_rules($product_id) {
        return [
            'allowed_weekdays' => self::get_allowed_weekdays($product_id),
            'allowed_dates'    => self::get_allowed_dates($product_id),
            'blocked_dates'    => self::get_blocked_dates($product_id),
        ];
    }

    public static function is_date_allowed_for_product($product_id, $ymd) {
        $ymd = WCR_Session::date_to_ymd($ymd);
        if (!$ymd) {
            return false;
        }

        $allowed_dates = self::get_allowed_dates($product_id);
        if (!empty($allowed_dates) && !in_array($ymd, $allowed_dates, true)) {
            return false;
        }

        $blocked_dates = self::get_blocked_dates($product_id);
        if (!empty($blocked_dates) && in_array($ymd, $blocked_dates, true)) {
            return false;
        }

        $allowed_weekdays = self::get_allowed_weekdays($product_id);
        if (!empty($allowed_weekdays)) {
            $weekday = (string) date('w', strtotime($ymd));
            if (!in_array($weekday, $allowed_weekdays, true)) {
                return false;
            }
        }

        return true;
    }

    public static function get_rule_summary($product_id) {
        $custom = self::get_custom_note($product_id);
        if ($custom !== '') {
            return $custom;
        }

        $parts = [];

        $from = self::get_visible_from($product_id);
        $until = self::get_visible_until($product_id);

        if ($from && $until) {
            $parts[] = 'Synlig i perioden ' . WCR_Session::native_to_display_date($from) . ' - ' . WCR_Session::native_to_display_date($until);
        } elseif ($from) {
            $parts[] = 'Synlig fra ' . WCR_Session::native_to_display_date($from);
        } elseif ($until) {
            $parts[] = 'Synlig til og med ' . WCR_Session::native_to_display_date($until);
        }

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
        $visible_from     = self::get_visible_from($product_id);
        $visible_until    = self::get_visible_until($product_id);
        $rule_note        = self::get_custom_note($product_id);
        $show_note        = self::show_note_enabled($product_id) ? 'yes' : 'no';
        $status           = self::get_admin_visibility_status($product_id);

        echo '<div class="options_group">';

        echo '<p class="form-field"><strong>Catering-regler</strong><br><span class="description">Begræns synlighed og hvilke dage eller datoer dette produkt må bestilles til.</span></p>';

        echo '<p class="form-field">';
        echo '<label>Status</label>';
        echo '<span class="wrap">';
        echo '<span style="
            display:inline-block;
            padding:10px 12px;
            border:1px solid ' . esc_attr($status['border']) . ';
            background:' . esc_attr($status['bg']) . ';
            color:' . esc_attr($status['color']) . ';
            border-radius:8px;
            max-width:700px;
            line-height:1.5;
        ">';
        echo '<strong>' . esc_html($status['label']) . '</strong><br>';
        echo esc_html($status['message']);
        echo '</span>';
        echo '</span>';
        echo '</p>';

        woocommerce_wp_text_input([
            'id'          => '_wcr_visible_from',
            'label'       => 'Synlig fra dato',
            'description' => 'Produktet vises først i shoppen fra denne dato.',
            'desc_tip'    => false,
            'value'       => $visible_from ? WCR_Session::native_to_display_date($visible_from) : '',
            'placeholder' => 'dd/mm/yyyy',
        ]);

        echo '<p class="form-field" style="margin-top:-10px;">';
        echo '<label></label>';
        echo '<span class="wrap wcr-date-actions" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">';
        echo '<button type="button" class="button wcr-pick-date-button" data-target="_wcr_visible_from">Vælg dato</button>';
        echo '<button type="button" class="button wcr-clear-dates-button" data-target="_wcr_visible_from">Ryd</button>';
        echo '</span>';
        echo '</p>';

        woocommerce_wp_text_input([
            'id'          => '_wcr_visible_until',
            'label'       => 'Synlig til dato',
            'description' => 'Produktet skjules efter denne dato. Hvis du vælger en dato før "Synlig fra", bliver den automatisk rettet.',
            'desc_tip'    => false,
            'value'       => $visible_until ? WCR_Session::native_to_display_date($visible_until) : '',
            'placeholder' => 'dd/mm/yyyy',
        ]);

        echo '<p class="form-field" style="margin-top:-10px;">';
        echo '<label></label>';
        echo '<span class="wrap wcr-date-actions" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">';
        echo '<button type="button" class="button wcr-pick-date-button" data-target="_wcr_visible_until">Vælg dato</button>';
        echo '<button type="button" class="button wcr-clear-dates-button" data-target="_wcr_visible_until">Ryd</button>';
        echo '</span>';
        echo '</p>';

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
            'description' => 'Vælg datoer med kalender-knappen eller skriv én dato pr. linje. Hvis dette felt er udfyldt, kan produktet kun bestilles til disse datoer.',
            'desc_tip'    => false,
            'value'       => WCR_Session::format_date_list_for_textarea($allowed_dates),
        ]);

        echo '<p class="form-field" style="margin-top:-10px;">';
        echo '<label></label>';
        echo '<span class="wrap wcr-date-actions" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">';
        echo '<button type="button" class="button wcr-pick-date-button" data-target="_wcr_allowed_dates_text">Vælg dato</button>';
        echo '<button type="button" class="button wcr-sort-dates-button" data-target="_wcr_allowed_dates_text">Sorter datoer</button>';
        echo '<button type="button" class="button wcr-clear-dates-button" data-target="_wcr_allowed_dates_text">Ryd</button>';
        echo '</span>';
        echo '</p>';

        woocommerce_wp_textarea_input([
            'id'          => '_wcr_blocked_dates_text',
            'label'       => 'Blokerede datoer',
            'description' => 'Vælg datoer med kalender-knappen eller skriv én dato pr. linje. Produktet kan ikke bestilles til disse datoer.',
            'desc_tip'    => false,
            'value'       => WCR_Session::format_date_list_for_textarea($blocked_dates),
        ]);

        echo '<p class="form-field" style="margin-top:-10px;">';
        echo '<label></label>';
        echo '<span class="wrap wcr-date-actions" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">';
        echo '<button type="button" class="button wcr-pick-date-button" data-target="_wcr_blocked_dates_text">Vælg dato</button>';
        echo '<button type="button" class="button wcr-sort-dates-button" data-target="_wcr_blocked_dates_text">Sorter datoer</button>';
        echo '<button type="button" class="button wcr-clear-dates-button" data-target="_wcr_blocked_dates_text">Ryd</button>';
        echo '</span>';
        echo '</p>';

        woocommerce_wp_textarea_input([
            'id'          => '_wcr_rule_note',
            'label'       => 'Tekst på produktside',
            'description' => 'Valgfri tekst som vises på produktsiden. Fx: "Kan kun bestilles til afhentning mandag til torsdag".',
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

        $visible_from = isset($_POST['_wcr_visible_from']) ? sanitize_text_field(wp_unslash($_POST['_wcr_visible_from'])) : '';
        $visible_until = isset($_POST['_wcr_visible_until']) ? sanitize_text_field(wp_unslash($_POST['_wcr_visible_until'])) : '';

        $visible_from_ymd = WCR_Session::date_to_ymd($visible_from);
        $visible_until_ymd = WCR_Session::date_to_ymd($visible_until);

        if ($visible_from_ymd && $visible_until_ymd && strcmp($visible_until_ymd, $visible_from_ymd) < 0) {
            $visible_until_ymd = $visible_from_ymd;
        }

        update_post_meta($product_id, '_wcr_visible_from', $visible_from_ymd);
        update_post_meta($product_id, '_wcr_visible_until', $visible_until_ymd);

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

    public function filter_catalog_queries($query) {
        if (is_admin() || !$query->is_main_query()) {
            return;
        }

        if (
            !$query->is_post_type_archive('product') &&
            !$query->is_tax(get_object_taxonomies('product')) &&
            !$query->is_search()
        ) {
            return;
        }

        $meta_query = (array) $query->get('meta_query');
        $today = current_time('Y-m-d');

        $meta_query[] = [
            'relation' => 'AND',
            [
                'relation' => 'OR',
                [
                    'key'     => '_wcr_visible_from',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key'     => '_wcr_visible_from',
                    'value'   => '',
                    'compare' => '=',
                ],
                [
                    'key'     => '_wcr_visible_from',
                    'value'   => $today,
                    'compare' => '<=',
                    'type'    => 'DATE',
                ],
            ],
            [
                'relation' => 'OR',
                [
                    'key'     => '_wcr_visible_until',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key'     => '_wcr_visible_until',
                    'value'   => '',
                    'compare' => '=',
                ],
                [
                    'key'     => '_wcr_visible_until',
                    'value'   => $today,
                    'compare' => '>=',
                    'type'    => 'DATE',
                ],
            ],
        ];

        $query->set('meta_query', $meta_query);
    }

    public function filter_product_is_visible($visible, $product_id) {
        if (!$visible) {
            return $visible;
        }

        return self::is_product_visible_today($product_id);
    }

    public function filter_related_products($related_posts, $product_id, $args) {
        if (empty($related_posts) || !is_array($related_posts)) {
            return $related_posts;
        }

        $filtered = [];

        foreach ($related_posts as $related_id) {
            if (self::is_product_visible_today($related_id)) {
                $filtered[] = $related_id;
            }
        }

        return $filtered;
    }
}
